<?php
require_once('includes/common.php');

echo "Direct access to \$GLOBALS['L']:<br>\n";
echo "CURLE_UNSUPPORTED_PROTOCOL = " . (isset($GLOBALS['L']['CURLE_UNSUPPORTED_PROTOCOL']) ? $GLOBALS['L']['CURLE_UNSUPPORTED_PROTOCOL'] : 'NOT SET') . "<br>\n";

function testGlobalL() {
    global $L;
    echo "<br>Inside function with 'global \$L':<br>\n";
    echo "Is \$L an array? " . (is_array($L) ? 'YES' : 'NO') . "<br>\n";
    if (is_array($L)) {
        echo "Count: " . count($L) . "<br>\n";
        echo "CURLE_UNSUPPORTED_PROTOCOL = " . (isset($L['CURLE_UNSUPPORTED_PROTOCOL']) ? $L['CURLE_UNSUPPORTED_PROTOCOL'] : 'NOT SET') . "<br>\n";
    } else {
        echo "\$L is: " . var_export($L, true) . "<br>\n";
    }
}

testGlobalL();

echo "<br>Checking \$GLOBALS array directly:<br>\n";
echo "Is \$GLOBALS['L'] set? " . (isset($GLOBALS['L']) ? 'YES' : 'NO') . "<br>\n";
if (isset($GLOBALS['L'])) {
    echo "Is \$GLOBALS['L'] an array? " . (is_array($GLOBALS['L']) ? 'YES' : 'NO') . "<br>\n";
    echo "Count of \$GLOBALS['L']: " . count($GLOBALS['L']) . "<br>\n";
}
?>
