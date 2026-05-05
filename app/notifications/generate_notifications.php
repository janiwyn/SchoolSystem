<?php
require_once __DIR__ . '/../config/db.php';

// Notifications for approvals have been disabled as the system now uses automated approvals.
// This file is kept for future notification types if needed.

function generateNotificationsForAdmin($user_id) {
    return [];
}

function generateNotificationsForOtherRoles($user_id) {
    return [];
}

function saveNotifications($notifications) {
    global $mysqli;
    
    foreach ($notifications as $notif) {
        // Check if notification already exists
        $checkQuery = "SELECT id FROM notifications WHERE user_id = ? AND title = ? AND is_read = 0 AND DATE(created_at) = CURDATE()";
        $checkStmt = $mysqli->prepare($checkQuery);
        $checkStmt->bind_param("is", $notif['user_id'], $notif['title']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        // Only insert if notification doesn't exist for today
        if ($checkResult->num_rows === 0) {
            $insertQuery = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
            $insertStmt = $mysqli->prepare($insertQuery);
            $insertStmt->bind_param("isss", $notif['user_id'], $notif['title'], $notif['message'], $notif['type']);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

// Get all active users
$usersQuery = "SELECT id, role FROM users WHERE status = 1";
$usersResult = $mysqli->query($usersQuery);
$users = $usersResult->fetch_all(MYSQLI_ASSOC);

// Generate and save notifications for each user based on role
foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $notifications = generateNotificationsForAdmin($user['id']);
    } else {
        // For principal and bursar
        $notifications = generateNotificationsForOtherRoles($user['id']);
    }
    saveNotifications($notifications);
}
?>
