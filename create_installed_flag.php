<?php
// Create the .installed flag file to indicate system is installed
file_put_contents(__DIR__ . '/.installed', 'installed');
echo '.installed flag file created successfully!';
?>