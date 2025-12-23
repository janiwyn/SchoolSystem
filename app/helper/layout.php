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

// Get recent unread notifications for modal (max 5)
$recentNotifQuery = "SELECT id, title, message, type, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$recentNotifStmt = $mysqli->prepare($recentNotifQuery);
$recentNotifStmt->bind_param("i", $_SESSION['user_id']);
$recentNotifStmt->execute();
$recentNotifResult = $recentNotifStmt->get_result();
$recentNotifications = $recentNotifResult->fetch_all(MYSQLI_ASSOC);
$recentNotifStmt->close();

// Check if user just logged in (first page load in session)
$justLoggedIn = !isset($_SESSION['notif_shown']) && !empty($recentNotifications);
if ($justLoggedIn) {
    $_SESSION['notif_shown'] = true;
}
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
    <?php elseif ($title === "Tuition Audit"): ?>
        <link rel="stylesheet" href="../../assets/css/audit.css">
    <?php elseif ($title === "Student Payments"): ?>
        <link rel="stylesheet" href="../../assets/css/studentPayments.css">
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

        /* Notification Modal Styles */
        .notification-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }

        .notification-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .notification-modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
            max-height: 600px;
            display: flex;
            flex-direction: column;
            transform: scale(0.9) translateY(-30px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .notification-modal-overlay.show .notification-modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .notification-modal-header {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-modal-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 18px;
        }

        .notification-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-modal-close:hover {
            transform: rotate(90deg);
        }

        .notification-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .notification-item-modal {
            background: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .notification-item-modal:hover {
            background: #e3f2fd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .notification-item-modal:last-child {
            margin-bottom: 0;
        }

        .notification-item-modal-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-item-modal-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .type-warning {
            background-color: #ffc107;
            color: #333;
        }

        .type-info {
            background-color: #17a2b8;
            color: white;
        }

        .notification-item-modal-message {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-item-modal-time {
            font-size: 11px;
            color: #999;
        }

        .notification-modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            border-radius: 0 0 12px 12px;
            background: #f8f9fa;
        }

        .notification-modal-footer button {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-close-modal {
            background-color: #6c757d;
            color: white;
        }

        .btn-close-modal:hover {
            background-color: #5a6268;
        }

        .btn-view-all {
            background-color: #17a2b8;
            color: white;
        }

        .btn-view-all:hover {
            background-color: #138496;
        }

        .notification-modal-empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .notification-modal-empty i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
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

    <!-- Notification Modal -->
    <div class="notification-modal-overlay <?= $justLoggedIn ? 'show' : '' ?>" id="notificationModal">
        <div class="notification-modal-content">
            <div class="notification-modal-header">
                <h5><i class="bi bi-bell-fill"></i> New Notifications</h5>
                <button class="notification-modal-close" onclick="closeNotificationModal()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="notification-modal-body">
                <?php if (empty($recentNotifications)): ?>
                    <div class="notification-modal-empty">
                        <i class="bi bi-bell-slash"></i>
                        <p>No new notifications</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentNotifications as $notif): ?>
                        <div class="notification-item-modal">
                            <div class="notification-item-modal-title">
                                <?= htmlspecialchars($notif['title']) ?>
                                <span class="notification-item-modal-badge type-<?= htmlspecialchars($notif['type']) ?>">
                                    <?= ucfirst(htmlspecialchars($notif['type'])) ?>
                                </span>
                            </div>
                            <p class="notification-item-modal-message"><?= htmlspecialchars($notif['message']) ?></p>
                            <div class="notification-item-modal-time">
                                <i class="bi bi-clock"></i> <?= date('Y-m-d H:i', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="notification-modal-footer">
                <button class="btn-close-modal" onclick="closeNotificationModal()">Close</button>
                <button class="btn-view-all" onclick="goToNotifications()">View All</button>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container mt-4">