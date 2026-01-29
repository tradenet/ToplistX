<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Step 1: Including common.php<br>\n";
require_once('includes/common.php');

echo "Step 2: Checking \$GLOBALS['L']<br>\n";
echo "Is \$GLOBALS['L'] set? " . (isset($GLOBALS['L']) ? 'YES' : 'NO') . "<br>\n";
echo "Count: " . (isset($GLOBALS['L']) ? count($GLOBALS['L']) : '0') . "<br>\n";

echo "<br>Step 3: Including required files<br>\n";
require_once("{$GLOBALS['BASE_DIR']}/includes/template.class.php");
require_once("{$GLOBALS['BASE_DIR']}/includes/mysql.class.php");
require_once("{$GLOBALS['BASE_DIR']}/includes/http.class.php");
require_once("{$GLOBALS['BASE_DIR']}/includes/validator.class.php");

echo "<br>Step 4: Setting up request<br>\n";
SetupRequest();

echo "<br>Step 5: Creating Template<br>\n";
$t = new Template();

echo "<br>Step 6: Creating DB connection<br>\n";
$DB = new DB($C['db_hostname'], $C['db_username'], $C['db_password'], $C['db_name']);
$DB->Connect();

echo "<br>Step 7: Testing function with global \$L<br>\n";
function testL() {
    global $L;
    echo "Inside function - \$L count: " . count($L) . "<br>\n";
    echo "Sample value: " . $L['INVALID_EMAIL'] . "<br>\n";
}
testL();

echo "<br>Step 8: Testing Validator<br>\n";
$v = new Validator();
echo "Validator created successfully<br>\n";

$DB->Disconnect();
echo "<br>All steps completed successfully!<br>\n";
?>
