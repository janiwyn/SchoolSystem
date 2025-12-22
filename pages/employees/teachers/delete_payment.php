<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Payment ID is missing.");
}
$payment_id = intval($_GET['id']);

// Fetch payment for logging
$stmt = $mysqli->prepare("
    SELECT tp.id, t.first_name, t.last_name, tp.amount 
    FROM teacher_payments tp 
    JOIN teachers t ON tp.teacher_id = t.id
    WHERE tp.id=?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    die("Payment not found.");
}

// Delete payment
$stmt = $mysqli->prepare("DELETE FROM teacher_payments WHERE id=?");
$stmt->bind_param("i", $payment_id);

if ($stmt->execute()) {
    // Optional: Audit log
    $desc = "Deleted payment for {$payment['first_name']} {$payment['last_name']}, Amount: {$payment['amount']}";
    $log = $mysqli->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, description) VALUES (?, 'DELETE', 'teacher_payments', ?, ?)"
    );
    $log->bind_param("iis", $_SESSION['user_id'], $payment_id, $desc);
    $log->execute();

    header("Location: payments.php?msg=deleted");
    exit();
} else {
    die("Error deleting payment: " . $stmt->error);
}
?>
