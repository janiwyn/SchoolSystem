<?php
require_once __DIR__ . '/../../app/auth/auth.php';
require_role(['admin','principal']);
require_once __DIR__ . '/../../app/config/db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Fetch student
$stmt = $mysqli->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($student) {
    // Delete student
    $stmt = $mysqli->prepare("DELETE FROM students WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Optional: handle prepare error
        die("Database error: " . $mysqli->error);
    }

    // Audit log
    $desc = "Deleted student {$student['first_name']} {$student['last_name']}";
    $log = $mysqli->prepare(
        "INSERT INTO audit_logs 
         (user_id, action, module, record_id, description)
         VALUES (?, 'DELETE', 'students', ?, ?)"
    );

    if ($log) {
        $user_id = $_SESSION['user_id'];
        $log->bind_param("iis", $user_id, $id, $desc);
        $log->execute();
        $log->close();
    } else {
        // Optional: handle prepare error
        die("Audit log error: " . $mysqli->error);
    }
}


header("Location: ../../public/index.php");
exit;
