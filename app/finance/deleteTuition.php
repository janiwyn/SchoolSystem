<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

if (isset($_GET['id'])) {
    $tuition_id = intval($_GET['id']);
    
    $stmt = $mysqli->prepare("DELETE FROM fee_structure WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $tuition_id);
        if ($stmt->execute()) {
            header("Location: tuition.php?deleted=1");
        } else {
            header("Location: tuition.php?error=1");
        }
        $stmt->close();
    } else {
        header("Location: tuition.php?error=1");
    }
} else {
    header("Location: tuition.php");
}
?>
