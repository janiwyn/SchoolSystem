<?php
session_start();

// Unset all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
