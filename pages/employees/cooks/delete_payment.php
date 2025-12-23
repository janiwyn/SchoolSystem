<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_role(['admin']);

$payment_id = $_GET['id'] ?? 0;

$stmt = $mysqli->prepare("DELETE FROM cook_payments WHERE id=?");
$stmt->bind_param("i", $payment_id);
if ($stmt->execute()) {
    header("Location: payments.php?success=Payment+deleted+successfully");
    exit;
} else {
    die("Failed to delete: ".$mysqli->error);
}
