<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id === 0) {
    header("Location: admitStudents.php");
    exit();
}

// Delete the student from admit_students table
$stmt = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        $stmt->close();
        // Redirect back to admitStudents page with success message
        header("Location: admitStudents.php?deleted=1");
        exit();
    } else {
        $stmt->close();
        // If deletion fails, redirect with error
        header("Location: admitStudents.php?error=1");
        exit();
    }
} else {
    header("Location: admitStudents.php?error=1");
    exit();
}
