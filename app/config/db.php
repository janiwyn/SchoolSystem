<?php
$host = 'localhost';
$db   = 'school_system';
$user = 'root';
$password = '';

// Create MySQLi connection
$mysqli = new mysqli($host, $user, $password, $db);

// Check connection
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}
?>
