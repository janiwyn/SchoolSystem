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
    <!-- Layout CSS -->
    <link rel="stylesheet" href="../../assets/css/layout.css">
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