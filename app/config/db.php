<?php
// Check if we're in development or production
// When running via CLI (command line), use localhost
$isProduction = false;

if (php_sapi_name() === 'cli') {
    // Running from command line - always use localhost
    $isProduction = false;
} elseif (isset($_SERVER['HTTP_HOST'])) {
    // Running via web server - check the host
    $isProduction = ($_SERVER['HTTP_HOST'] !== 'localhost' && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false);
}

if ($isProduction) {
    // Production settings (InfinityFree)
    $host = 'sql113.infinityfree.com';
    $db   = 'if0_40763730_school_system';
    $user = 'if0_40763730';
    $password = '1P4z11p2g8jX';
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

// NEW: Connection optimization settings
// Set connection timeout (prevent hanging connections)
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Enable result buffering for better memory usage
$mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

// Set query cache (helps with repeated queries)
// Note: This is just a hint to MySQL, actual caching depends on server config
?>
