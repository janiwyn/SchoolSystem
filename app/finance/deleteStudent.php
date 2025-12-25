<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin', 'principal', 'bursar']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admitStudents.php?error=missing_id");
    exit();
}

$student_id = intval($_GET['id']);

try {
    // Get student details for logging
    $studentStmt = $mysqli->prepare("SELECT admission_no, first_name, last_name FROM admit_students WHERE id = ?");
    $studentStmt->bind_param("i", $student_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();
    $studentStmt->close();

    if (!$student) {
        // Student doesn't exist, redirect with error
        header("Location: admitStudents.php?error=not_found");
        exit();
    }

    // Update student_payments to set student_id to NULL (keep payment history)
    $paymentsStmt = $mysqli->prepare("UPDATE student_payments SET student_id = NULL WHERE student_id = ?");
    $paymentsStmt->bind_param("i", $student_id);
    if (!$paymentsStmt->execute()) {
        throw new Exception("Error updating payment records: " . $paymentsStmt->error);
    }
    $paymentsStmt->close();

    // Delete student_payment_topups (child table)
    $topupsStmt = $mysqli->prepare("DELETE FROM student_payment_topups WHERE student_id = ?");
    $topupsStmt->bind_param("i", $student_id);
    if (!$topupsStmt->execute()) {
        throw new Exception("Error deleting payment topups: " . $topupsStmt->error);
    }
    $topupsStmt->close();

    // Delete the student from admit_students table
    $deleteStmt = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
    $deleteStmt->bind_param("i", $student_id);
    if (!$deleteStmt->execute()) {
        throw new Exception("Error deleting student: " . $deleteStmt->error);
    }
    $deleteStmt->close();

    // Log the deletion
    $logDesc = "Deleted student {$student['first_name']} {$student['last_name']} (Admission No: {$student['admission_no']}). Payment history retained.";
    $logStmt = $mysqli->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, description) VALUES (?, 'DELETE', 'admit_students', ?, ?)");
    $logStmt->bind_param("iis", $_SESSION['user_id'], $student_id, $logDesc);
    $logStmt->execute();
    $logStmt->close();

    // Redirect with success message
    header("Location: admitStudents.php?msg=deleted");
    exit();

} catch (Exception $e) {
    // Redirect with error message instead of dying
    header("Location: admitStudents.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
