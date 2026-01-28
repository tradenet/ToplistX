<?PHP
$C = array();

// Provide safe default configuration values to avoid "undefined array key"
// notices when the installation/configuration file hasn't been populated yet.
$C = array_merge(array(
	'db_hostname' => 'localhost',
	'db_username' => '',
	'db_password' => '',
	'db_name'     => '',

	'dec_point'      => '.',
	'thousands_sep'  => ',',
	'timezone'       => 0,

	'using_cron'     => false,
	'php_cli'        => '',
	'mysqldump'      => '',
	'mysql'          => '',

	'cookie_domain'  => '',
	'redirect_code'  => 301,
	'date_format'    => 'Y-m-d',
	'time_format'    => 'H:i:s',

	'install_url'    => '',
	'document_root'  => '',
	'banner_dir'     => 'images',
	'banner_url'     => '',
	'secret_key'     => '',
	'in_url'         => '',
	'forward_url'    => '',
	'alternate_out_url' => '',

	'rebuild_interval' => 0,
	'max_rating'       => 5,
	'min_comment_length'=> 0,
	'max_comment_length'=> 1000,
	'comment_interval'  => 0,
	'review_comments'   => false,
	'return_percent'    => 0,
), $C);

?>