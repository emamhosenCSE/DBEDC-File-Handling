<?php
/**
 * Debug user data
 */

require_once __DIR__ . '/includes/api-bootstrap.php';

$user = getCurrentUser();
echo "User data:\n";
print_r($user);

echo "\nUser scope: " . getUserScope() . "\n";

echo "\nSession data:\n";
print_r($_SESSION);
?>