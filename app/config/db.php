<?php
// Check if we're in development or production
$isProduction = ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';

if ($isProduction) {
    // Production settings (InfinityFree)
    // IMPORTANT: Get these values from InfinityFree Control Panel → MySQL Databases
    
    $host = 'sql113.infinityfree.com';           // MySQL Hostname (NOT account label)
    $db   = 'if0_40763730_school_system';        // Database Name (with if0_ prefix)
    $user = 'if0_40763730';                      // Database Username (with if0_ prefix)
    $password = '1P4z11p2g8jX';    // Database Password (NOT account password)
} else {
    // Development settings (localhost)
    $host = 'localhost';
    $db   = 'school_system';
    $user = 'root';
    $password = '';
}

// Create MySQLi connection
$mysqli = new mysqli($host, $user, $password, $db);

// Check connection
if ($mysqli->connect_error) {
    // In production, don't show detailed errors
    if ($isProduction) {
        die("Database connection failed. Please contact support.");
    } else {
        die("Database connection failed: " . $mysqli->connect_error);
    }
}

// Set charset to UTF-8
$mysqli->set_charset("utf8mb4");
?>
