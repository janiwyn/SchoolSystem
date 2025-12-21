<?php 
require_once __DIR__ . '/../auth/auth.php'; 
requireLogin(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">School System</a>
        <div class="d-flex align-items-center">
            <span class="text-white me-3"><?= htmlspecialchars($_SESSION['user']) ?></span>
            <a href="../app/public/logout.php" class="btn btn-outline-warning btn-sm">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="container mt-4">
