<?php 
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php'; 
requireLogin(); 

// Get unread notifications count
$notifCountQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $mysqli->prepare($notifCountQuery);
$notifStmt->bind_param("i", $_SESSION['user_id']);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifRow = $notifResult->fetch_assoc();
$unreadCount = $notifRow['unread_count'] ?? 0;
$notifStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Page-specific CSS -->
<?php if (($title ?? '') === "Tuition Management"): ?>
        <link rel="stylesheet" href="../../assets/css/tuition.css">
    <?php elseif ($title === "Admit Students"): ?>
        <link rel="stylesheet" href="../../assets/css/admitStudents.css">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            display: flex;
            /* min-height: 100vh; */
            height: 150px;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }
        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }
        .nav-item.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border-left-color: #3498db;
        }
        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }
        .main-wrapper {
            margin-left: 250px;
            width: calc(100% - 250px);
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-text {
            color: white !important;
            font-weight: 600;
            font-size: 18px;
        }
        .container {
            padding: 30px !important;
        }

        .notification-icon {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-right: 20px;
            font-size: 24px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .notification-icon:hover {
            transform: scale(1.1);
            color: #3498db;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            border: 2px solid #2c3e50;
        }

        .notification-badge.hidden {
            display: none;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-wrapper">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <span class="navbar-text"><?= $title ?? 'Dashboard' ?></span>
            <div class="d-flex align-items-center ms-auto">
                <!-- Notifications Icon -->
                <a href="../../app/notifications/notifications.php" class="notification-icon" title="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge" id="notifBadge"><?= $unreadCount ?></span>
                    <?php else: ?>
                        <span class="notification-badge hidden" id="notifBadge">0</span>
                    <?php endif; ?>
                </a>

                <span class="me-3 text-white"><?= htmlspecialchars($_SESSION['name']) ?></span>
                <a href="../../public/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container mt-4">