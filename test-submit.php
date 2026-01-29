<?php
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

echo "Simulating account add submission...<br>\n";
echo "Including accounts.php...<br>\n";
include('accounts.php');
echo "<br>Completed successfully!<br>\n";
?>
