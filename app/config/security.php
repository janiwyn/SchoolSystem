<?php
// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Only if using HTTPS

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// XSS protection
header('X-XSS-Protection: 1; mode=block');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Force HTTPS (if available)
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
}
?>
