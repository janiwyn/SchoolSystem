<?php
$title = "Notifications";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin', 'principal', 'bursar']);

// Trigger notification generation for all users
require_once __DIR__ . '/generate_notifications.php';

// Mark notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php?success=1");
    exit();
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php?success=1");
    exit();
}

// Delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'])) {
    $notif_id = intval($_POST['delete_notif']);
    $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php?success=1");
    exit();
}

// Include layout AFTER all operations
require_once __DIR__ . '/../helper/layout.php';

// Get all notifications
$notifQuery = "SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
$notifStmt = $mysqli->prepare($notifQuery);
$notifStmt->bind_param("i", $_SESSION['user_id']);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifications = $notifResult->fetch_all(MYSQLI_ASSOC);
$notifStmt->close();

// Get unread count
$unreadQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$unreadStmt = $mysqli->prepare($unreadQuery);
$unreadStmt->bind_param("i", $_SESSION['user_id']);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result();
$unreadRow = $unreadResult->fetch_assoc();
$unreadCount = $unreadRow['unread_count'] ?? 0;
$unreadStmt->close();

$isAdmin = $_SESSION['role'] === 'admin';
?>

<style>
    .notifications-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .notification-header h4 {
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .notification-item {
        background: white;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 16px;
        border-left: 5px solid #17a2b8;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .notification-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .notification-item.unread {
        background-color: #f0f8ff;
    }

    .notification-item.read {
        background-color: #ffffff;
    }

    .notification-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .notification-title-section {
        flex: 1;
        margin-right: 20px;
    }

    .notification-title {
        font-weight: 700;
        color: #2c3e50;
        font-size: 16px;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notification-type-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .type-success {
        background-color: #28a745;
        color: white;
    }

    .type-warning {
        background-color: #ffc107;
        color: #333;
    }

    .type-danger {
        background-color: #dc3545;
        color: white;
    }

    .type-info {
        background-color: #17a2b8;
        color: white;
    }

    .notification-message {
        color: #555;
        font-size: 15px;
        line-height: 1.6;
        margin-bottom: 14px;
    }

    .notification-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid #f0f0f0;
    }

    .notification-time {
        font-size: 13px;
        color: #999;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .notification-actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 16px;
    }

    .btn-mark-read {
        background-color: #17a2b8;
        color: white;
    }

    .btn-mark-read:hover {
        background-color: #138496;
        transform: scale(1.05);
    }

    .btn-delete {
        background-color: #e0e0e0;
        color: #666;
    }

    .btn-delete:hover {
        background-color: #d0d0d0;
        color: #333;
        transform: scale(1.05);
    }

    .btn-view {
        background-color: #28a745;
        color: white;
    }

    .btn-view:hover {
        background-color: #218838;
        transform: scale(1.05);
    }

    .empty-state {
        text-align: center;
        padding: 100px 20px;
        background: white;
        border-radius: 8px;
    }

    .empty-state i {
        font-size: 64px;
        color: #ccc;
        margin-bottom: 20px;
    }

    .empty-state h5 {
        color: #2c3e50;
        font-weight: 700;
        font-size: 18px;
    }

    .empty-state p {
        color: #999;
        font-size: 14px;
    }

    .mark-all-btn {
        background-color: #17a2b8;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .mark-all-btn:hover {
        background-color: #138496;
    }

    .success-message {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 14px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 14px;
    }
</style>

<div class="notifications-container">
    <div class="notification-header">
        <h4>Notifications</h4>
        <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="mark-all-btn">
                    <i class="bi bi-check-all"></i> Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <i class="bi bi-check-circle"></i> Action completed successfully!
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <h5>No Notifications</h5>
            <p>You're all caught up!</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>">
                <div class="notification-top">
                    <div class="notification-title-section">
                        <p class="notification-title">
                            <?= htmlspecialchars($notif['title']) ?>
                            <span class="notification-type-badge type-<?= htmlspecialchars($notif['type']) ?>">
                                <?= ucfirst(htmlspecialchars($notif['type'])) ?>
                            </span>
                        </p>
                    </div>
                    <div class="notification-actions">
                        <!-- Only show view button for admin with pending notifications -->
                        <?php if ($isAdmin && strpos($notif['title'], 'Pending') !== false): ?>
                            <a href="../admin/pendingrequest.php" class="action-btn btn-view" title="View Pending">
                                <i class="bi bi-eye"></i>
                            </a>
                        <?php elseif (!$isAdmin && strpos($notif['title'], 'Unapproved') !== false): ?>
                            <!-- For other roles, show view button but link to student payments/admit students -->
                            <?php if (strpos($notif['title'], 'Payments') !== false): ?>
                                <a href="../finance/studentPayments.php" class="action-btn btn-view" title="View Payments">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php elseif (strpos($notif['title'], 'Admissions') !== false): ?>
                                <a href="../finance/admitStudents.php" class="action-btn btn-view" title="View Admissions">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!$notif['is_read']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                <button type="submit" name="mark_read" class="action-btn btn-mark-read" title="Mark as Read">
                                    <i class="bi bi-check"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="delete_notif" value="<?= $notif['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Delete this notification?')">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <p class="notification-message"><?= htmlspecialchars($notif['message']) ?></p>

                <div class="notification-footer">
                    <div class="notification-time">
                        <i class="bi bi-clock"></i>
                        <?= date('Y-m-d H:i', strtotime($notif['created_at'])) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
