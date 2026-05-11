<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'bursar', 'principal'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($payment_id > 0) {
        $stmt = $mysqli->prepare("UPDATE student_payments SET comment = ? WHERE id = ?");
        $stmt->bind_param("si", $comment, $payment_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Comment saved']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving comment: ' . $mysqli->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
