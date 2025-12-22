<?php
require_once __DIR__ . '/../config/db.php';

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id === 0) {
    echo json_encode(['tuition' => 0]);
    exit;
}

// Get the expected tuition for the class (assuming it's from fee_structure table)
$query = "SELECT SUM(amount) as tuition FROM fee_structure WHERE class_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

echo json_encode(['tuition' => $row['tuition'] ?? 0]);
?>
