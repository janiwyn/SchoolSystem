<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Cook ID is missing.");
}
$cook_id = intval($_GET['id']);

// Fetch cook for logging
$stmt = $mysqli->prepare("SELECT first_name, last_name FROM cooks WHERE id=?");
$stmt->bind_param("i", $cook_id);
$stmt->execute();
$result = $stmt->get_result();
$cook = $result->fetch_assoc();
$stmt->close();

if (!$cook) {
    die("Cook not found.");
}

// Delete cook
$stmt = $mysqli->prepare("DELETE FROM cooks WHERE id=?");
$stmt->bind_param("i", $cook_id);
if ($stmt->execute()) {

    // Optional: Audit log
    $desc = "Deleted cook {$cook['first_name']} {$cook['last_name']}";
    $log = $mysqli->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, description) VALUES (?, 'DELETE', 'cooks', ?, ?)"
    );
    $log->bind_param("iis", $_SESSION['user_id'], $cook_id, $desc);
    $log->execute();

    header("Location: list.php?msg=deleted");
    exit();
} else {
    die("Error deleting cook: " . $stmt->error);
}
?>
