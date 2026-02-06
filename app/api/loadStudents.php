<?php
session_start();

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Get approved students for dropdown
$approvedStudentsQuery = "SELECT 
    s.id, 
    s.admission_no, 
    s.first_name, 
    s.last_name, 
    s.gender, 
    s.class_id, 
    s.day_boarding, 
    s.admission_fee, 
    s.uniform_fee, 
    s.parent_contact, 
    s.parent_email, 
    s.status,
    c.class_name
FROM admit_students s
LEFT JOIN classes c ON s.class_id = c.id
WHERE s.status IN ('approved', 'unapproved')
ORDER BY s.first_name ASC";

$result = $mysqli->query($approvedStudentsQuery);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $mysqli->error]);
    exit();
}

$students = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($students);
?>
