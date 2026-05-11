<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']); // Only admin can delete tuition records

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'class';
    
    if ($type === 'category') {
        $stmt = $mysqli->prepare("DELETE FROM category_fees WHERE id = ?");
        $redirect = "tuition.php?deleted=1&tab=category";
    } else {
        $stmt = $mysqli->prepare("DELETE FROM fee_structure WHERE id = ?");
        $redirect = "tuition.php?deleted=1&tab=class";
    }

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: $redirect");
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
