<?php
// Copyright 2011 JMB Software, Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//    http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.


// Initialization
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);
// get_magic_quotes_gpc removed in PHP 5.4, no longer needed
date_default_timezone_set('America/Chicago');


// Load configuration settings
require_once('includes/config.php');
require_once('includes/mysql.class.php');
require_once('includes/common.php');

// Set safe defaults for common request keys to avoid undefined index notices (PHP 8.2)
$_REQUEST['id'] = $_REQUEST['id'] ?? '';

$send_to = $C['alternate_out_url'];

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $raw_out = false;
    $raw_click = false;
    $account = null;
    $referrer_account = !empty($_COOKIE['tlxreferrer']) ? $_COOKIE['tlxreferrer'] : null;
    $first_click = empty($_COOKIE['tlxfirst']) ? true : false;
    $sites_sent_to = !empty($_COOKIE['tlxsent']) ? unserialize(stripslashes($_COOKIE['tlxsent'])) : [];
    $send_to_trade = true;
    $now = time() + 3600 * ($C['timezone'] ?? 0);
    $today = gmdate('Y-m-d', $now);
    $this_hour = gmdate('G', $now);
    $datetime = "$today-$this_hour";

    // Connect to database
    $DB = new DB($C['db_hostname'], $C['db_username'], $C['db_password'], $C['db_name']);
    $DB->Connect();

    if (!$C['using_cron']) {
        // Check if it is time for a page rebuild
        $rebuild_row = $DB->Row("SELECT `value` FROM `tlx_stored_values` WHERE `name`='last_rebuild'");
        $last_rebuild = $rebuild_row['value'] ?? 0;

        if ($last_rebuild <= $now - $C['rebuild_interval']) {
            shell_exec("{$C['php_cli']} admin/cron.php --rebuild >/dev/null 2>&1 &");
        }

        // Check if it is time for a daily or hourly update
        $result = $DB->Row("SELECT `value` FROM `tlx_stored_values` WHERE `name`='last_updates'");
        $last_updates = unserialize($result['value']);

        if ($last_updates['daily'] != $today) {
            shell_exec("{$C['php_cli']} admin/cron.php --daily-stats >/dev/null 2>&1 &");
        }

        if ($last_updates['hourly'] != $datetime) {
            shell_exec("{$C['php_cli']} admin/cron.php --hourly-stats >/dev/null 2>&1 &");
        }
    }

    // SKIM MODE
    if (!empty($_GET['s']) || !empty($_GET['f'])) {
        // Set the first click cookie
        setcookie('tlxfirst', '1', time()+86400, '/', $C['cookie_domain']);

        $_GET['s'] = !empty($_GET['s']) && is_numeric($_GET['s']) ? $_GET['s'] : 70;

        // Skim is set to 100 or this is a first click
        if( $_GET['s'] == 100 || (!empty($_GET['f']) && $first_click) )
        {
            $send_to_trade = FALSE;
            $send_to = $_GET['u'] ?? '';
        }
        else
        {
            // Check ratio of trades to links
            $result = $DB->Row('SELECT (`sent_trades`/`sent_total`)*100 AS `trade_percent` FROM `tlx_skim_ratio`');
            if( $result )
            {
                $trade_percent = $result['trade_percent'];
            }

            // Determine - based on ratio - if we should send to a trade
            if( 100 - $_GET['s'] < $trade_percent )
            {
                $send_to_trade = FALSE;
                $send_to = $_GET['u'] ?? '';
            }
            else
            {
                $sites_sent_to[$referrer_account] = 1;

                // Select the click tracking mode
                switch($_GET['m'] ?? 'default')
                {
                    default:
                        $owed = '(`clicks_total`-`unique_out_total`)*`return_percent`';
                        $where = '`clicks_total` > `unique_out_total`';
                        break;
                }

                $result = $DB->Query("SELECT *,$owed AS `owed` FROM `tlx_accounts` JOIN `tlx_account_hourly_stats` USING (`username`) WHERE $where ORDER BY `owed` DESC");

                if( $result )
                {
                    while( $row = $DB->NextRow($result) )
                    {
                        if( $sites_sent_to[$row['username']] )
                        {
                            continue;
                        }

                        $account = $row;
                        break;
                    }
                    $DB->Free($result);
                }
            }

			$DB->Update('UPDATE `tlx_skim_ratio` SET `sent_total`=`sent_total`+1,`sent_trades`=`sent_trades`+?', [$send_to_trade ? 1 : 0]);
        }
    }


    // SEND TO RANDOM ACCOUNT
    else if( !empty($_GET['rand']) )
    {
        // Get a random account
        $account = $DB->Row('SELECT * FROM `tlx_accounts` WHERE `status`="active" AND `disabled`=0 ORDER BY RAND() LIMIT 1');
    }



    // TOPLIST MODE
    else
    {
        // Get the account
        $account = $DB->Row('SELECT * FROM `tlx_accounts` WHERE `username`=?', [$_GET['id']]);
    }

    $long_ip = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

    // Account that surfer is being sent to has been selected
    if( $send_to_trade && $account )
    {
        $send_to = $account['site_url'];

        // Check if surfer has been sent to this site already
        if( isset($sites_sent_to[$account['username']]) )
        {
            $raw_out = TRUE;
        }

        // GeoIP lookup
        $geoip = $DB->Row('SELECT * FROM `tlx_ip2country` WHERE `ip_end` >= ?', [$long_ip]);

        // Update the IP log
        $affected = $DB->Update('UPDATE `tlx_ip_log_out` SET `raw_out`=`raw_out`+1,`last_visit`=NOW() WHERE `username`=? AND `ip_address`=?', [$account['username'], $long_ip]);
        if( $affected == 0 )
        {
            $DB->Update('INSERT INTO `tlx_ip_log_out` VALUES (?,?,?,NOW())', [$account['username'], $long_ip, 1]);
        }
        else
        {
            $raw_out = TRUE;
        }

        // Update raw and unique click counts
        if( $raw_out )
        {
            $DB->Update('UPDATE `tlx_account_hourly_stats` SET #=#+1,`raw_out_total`=`raw_out_total`+1 WHERE `username`=?',
                                       ["raw_out_$this_hour", "raw_out_$this_hour", $account['username']]);

            $affected = $DB->Update('UPDATE `tlx_account_country_stats` SET `raw_out`=`raw_out`+1 WHERE `username`=? AND `country`=?',
                                       [$account['username'], $geoip['country']]);

            if( $affected == 0 )
            {
                $DB->Update('INSERT INTO `tlx_account_country_stats` VALUES (?,?,?,?,?,?,?)', [$account['username'], $geoip['country'], 0, 0, 1, 1, 0]);
            }

            $DB->Update('UPDATE `tlx_country_stats` SET `raw_out`=`raw_out`+1 WHERE `country`=?', [$geoip['country']]);
        }
        else
        {
            $DB->Update('UPDATE `tlx_account_hourly_stats` SET #=#+1,#=#+1,`raw_out_total`=`raw_out_total`+1,`unique_out_total`=`unique_out_total`+1 WHERE `username`=?',
                                       ["raw_out_$this_hour", "raw_out_$this_hour", "unique_out_$this_hour", "unique_out_$this_hour", $account['username']]);

            $affected = $DB->Update('UPDATE `tlx_account_country_stats` SET `raw_out`=`raw_out`+1,`unique_out`=`unique_out`+1 WHERE `username`=? AND `country`=?',
                                       [$account['username'], $geoip['country']]);

            if( $affected == 0 )
            {
                $DB->Update('INSERT INTO `tlx_account_country_stats` VALUES (?,?,?,?,?,?,?)', [$account['username'], $geoip['country'], 0, 0, 1, 1, 0]);
            }

            $DB->Update('UPDATE `tlx_country_stats` SET `raw_out`=`raw_out`+1,`unique_out`=`unique_out`+1 WHERE `country`=?', [$geoip['country']]);
        }

        // Update cookie to mark that surfer has been sent to this site
        $sites_sent_to[$account['username']] = 1;
        setcookie('tlxsent', serialize($sites_sent_to), time()+86400, '/', $C['cookie_domain']);
    }

    // Update stats for the referrer account
    if( $referrer_account && $referrer_account != $account['username'] )
    {
        // Update the IP click log
        $affected = $DB->Update('UPDATE `tlx_ip_log_clicks` SET `clicks`=`clicks`+1,`last_visit`=NOW() WHERE `username`=? AND `ip_address`=? AND `url_hash`=?',
                                   [$referrer_account,
                                         $long_ip,
                                         sha1($send_to)]);

        if( $affected == 0 )
        {
            $DB->Update('INSERT INTO `tlx_ip_log_clicks` VALUES (?,?,?,?,NOW())', [$referrer_account, $long_ip, sha1($send_to), 1]);
            $DB->Update('UPDATE `tlx_account_hourly_stats` SET #=#+1,`clicks_total`=`clicks_total`+1 WHERE `username`=?',
                                       ["clicks_$this_hour", "clicks_$this_hour", $referrer_account]);
        }
    }

    $DB->Disconnect();
}


if( !isset($C['redirect_code']) )
{
    $C['redirect_code'] = 301;
}

header("Location: $send_to", true, $C['redirect_code']);

// Legacy mysql_prepare function - no longer used, kept for compatibility
// The DB class now handles all query preparation internally
?>