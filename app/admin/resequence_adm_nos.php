<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

// Only Admin can perform this heavy operation
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resequence_adm_nos'])) {
    $mysqli->begin_transaction();
    try {
        // 1. Get all students from admit_students ordered by current admission_no (numeric)
        // We use CAST to ensure 10 comes after 2, not 1
        $query = "SELECT id, admission_no FROM admit_students ORDER BY CAST(admission_no AS UNSIGNED) ASC, id ASC";
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception("Error fetching students: " . $mysqli->error);
        }

        $counter = 1;
        while ($row = $result->fetch_assoc()) {
            $student_id = $row['id'];
            $new_adm_no = (string)$counter;
            
            // Update admit_students table
            $stmt1 = $mysqli->prepare("UPDATE admit_students SET admission_no = ? WHERE id = ?");
            $stmt1->bind_param("si", $new_adm_no, $student_id);
            if (!$stmt1->execute()) {
                throw new Exception("Error updating admit_students (ID $student_id): " . $stmt1->error);
            }
            $stmt1->close();
            
            // Update student_payments table (to keep in sync)
            $stmt2 = $mysqli->prepare("UPDATE student_payments SET admission_no = ? WHERE student_id = ?");
            $stmt2->bind_param("si", $new_adm_no, $student_id);
            if (!$stmt2->execute()) {
                throw new Exception("Error updating student_payments (Student ID $student_id): " . $stmt2->error);
            }
            $stmt2->close();
            
            $counter++;
        }
        
        $mysqli->commit();
        header("Location: ../finance/studentPayments.php?resequenced=1");
        exit();
    } catch (Exception $e) {
        $mysqli->rollback();
        die("Critical Error during re-sequencing: " . $e->getMessage());
    }
} else {
    header("Location: ../finance/studentPayments.php");
    exit();
}
?>
