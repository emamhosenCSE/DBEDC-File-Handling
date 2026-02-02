<?php
// Reset installation flag - delete .installed file
if (file_exists(__DIR__ . '/.installed')) {
    unlink(__DIR__ . '/.installed');
    echo '.installed file deleted successfully! Installation reset.';
} else {
    echo '.installed file not found.';
}

// Also clear any installation session data
session_start();
unset($_SESSION['install_step']);
session_destroy();

echo '<br><a href="install.php">Go to Installation</a>';
?>