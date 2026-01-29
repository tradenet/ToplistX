<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_POST = array(
    'r' => 'accountadd',
    'username' => 'testuser',
    'password' => 'testpass123',
    'confirm_password' => 'testpass123',
    'email' => 'test@example.com',
    'site_url' => 'http://example.com',
    'title' => 'Test Site',
    'description' => 'This is a test site description',
    'category_id' => '1'
);
$_REQUEST = $_POST;

echo "Step 1: About to include accounts.php<br>\n";
flush();

// Capture any output from accounts.php
ob_start();
try {
    include('accounts.php');
    $output = ob_get_clean();
    echo "Step 2: accounts.php completed<br>\n";
    echo "Output length: " . strlen($output) . " bytes<br>\n";
    echo "<br>--- OUTPUT START ---<br>\n";
    echo htmlspecialchars(substr($output, 0, 500));
    echo "<br>--- OUTPUT END ---<br>\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "Exception caught: " . $e->getMessage() . "<br>\n";
    echo "Stack trace:<br>\n";
    echo htmlspecialchars($e->getTraceAsString());
}

echo "<br>Script completed!<br>\n";
?>
