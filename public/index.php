<?php
require_once '../app/auth/auth.php';
session_start();

if (isset($_SESSION['user_id'])) {
    // Already logged in, redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: app/admin/dashboard.php"); break;
        case 'principal':
            header("Location: app/principal/dashboard.php"); break;
        case 'bursar':
            header("Location: app/finance/dashboard.php"); break;
        default:
            header("Location: app/finance/dashboard.php"); break;
    }
    exit;
}

// Not logged in → redirect to login
header("Location: login.php");
exit;
