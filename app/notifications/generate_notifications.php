<?php
require_once __DIR__ . '/../config/db.php';

function generateNotificationsForAdmin($user_id) {
    global $mysqli;
    
    // Get pending student payments count
    $paymentQuery = "SELECT COUNT(*) as count FROM student_payments WHERE status_approved = 'unapproved' AND id NOT IN (SELECT DISTINCT payment_id FROM student_payment_topups WHERE status_approved = 'unapproved')";
    $paymentResult = $mysqli->query($paymentQuery);
    $paymentCount = $paymentResult->fetch_assoc()['count'];
    
    // Get pending balance top-ups count
    $topupQuery = "SELECT COUNT(*) as count FROM student_payment_topups WHERE status_approved = 'unapproved'";
    $topupResult = $mysqli->query($topupQuery);
    $topupCount = $topupResult->fetch_assoc()['count'];
    
    // Get pending admitted students count
    $studentQuery = "SELECT COUNT(*) as count FROM admit_students WHERE status = 'unapproved'";
    $studentResult = $mysqli->query($studentQuery);
    $studentCount = $studentResult->fetch_assoc()['count'];
    
    $notifications = [];
    
    // Create notification for pending payments
    if ($paymentCount > 0) {
        $notifications[] = [
            'user_id' => $user_id,
            'title' => 'Pending School Payments',
            'message' => "You have {$paymentCount} pending school payment(s) awaiting approval.",
            'type' => 'warning'
        ];
    }
    
    // Create notification for pending balance top-ups
    if ($topupCount > 0) {
        $notifications[] = [
            'user_id' => $user_id,
            'title' => 'Pending Balance Top-ups',
            'message' => "You have {$topupCount} pending balance top-up(s) awaiting approval.",
            'type' => 'info'
        ];
    }
    
    // Create notification for pending admitted students
    if ($studentCount > 0) {
        $notifications[] = [
            'user_id' => $user_id,
            'title' => 'Pending Student Admissions',
            'message' => "You have {$studentCount} pending student admission(s) awaiting approval.",
            'type' => 'info'
        ];
    }
    
    return $notifications;
}

function generateNotificationsForOtherRoles($user_id) {
    global $mysqli;
    
    // Get unapproved student payments count for this user's context
    $paymentQuery = "SELECT COUNT(*) as count FROM student_payments WHERE status_approved = 'unapproved'";
    $paymentResult = $mysqli->query($paymentQuery);
    $paymentCount = $paymentResult->fetch_assoc()['count'];
    
    // Get unapproved admitted students count
    $studentQuery = "SELECT COUNT(*) as count FROM admit_students WHERE status = 'unapproved'";
    $studentResult = $mysqli->query($studentQuery);
    $studentCount = $studentResult->fetch_assoc()['count'];
    
    $notifications = [];
    
    // Create notification for pending payments
    if ($paymentCount > 0) {
        $notifications[] = [
            'user_id' => $user_id,
            'title' => 'Unapproved Student Payments',
            'message' => "There are {$paymentCount} unapproved student payment(s) in the system.",
            'type' => 'warning'
        ];
    }
    
    // Create notification for unapproved admitted students
    if ($studentCount > 0) {
        $notifications[] = [
            'user_id' => $user_id,
            'title' => 'Unapproved Student Admissions',
            'message' => "There are {$studentCount} unapproved student admission(s) in the system.",
            'type' => 'info'
        ];
    }
    
    return $notifications;
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
