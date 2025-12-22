<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Teacher ID is missing.");
}
$teacher_id = intval($_GET['id']);

// Fetch teacher for logging
$stmt = $mysqli->prepare("SELECT first_name, last_name FROM teachers WHERE id=?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found.");
}

// Delete teacher
$stmt = $mysqli->prepare("DELETE FROM teachers WHERE id=?");
$stmt->bind_param("i", $teacher_id);
if ($stmt->execute()) {

    // Optional: Audit log
    $desc = "Deleted teacher {$teacher['first_name']} {$teacher['last_name']}";
    $log = $mysqli->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, description) VALUES (?, 'DELETE', 'teachers', ?, ?)"
    );
    $log->bind_param("iis", $_SESSION['user_id'], $teacher_id, $desc);
    $log->execute();

    header("Location: list.php?msg=deleted");
    exit();
} else {
    die("Error deleting teacher: " . $stmt->error);
}
?>
