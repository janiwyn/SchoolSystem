<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin', 'principal']);

// Re-sequence admission_no to 1..N and propagate to related tables
function resequenceAdmissionNumbers(mysqli $mysqli) {
    // Get all students ordered by current admission_no (numeric)
    $result = $mysqli->query("SELECT id, admission_no FROM admit_students ORDER BY CAST(admission_no AS UNSIGNED) ASC");
    if (!$result) {
        return;
    }

    $students = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    if (empty($students)) {
        return;
    }

    // Build mapping old_sn => new_sn (1..N)
    $mapping = [];
    $new_sn = 1;
    foreach ($students as $row) {
        $old_sn = (int)$row['admission_no'];
        $mapping[$old_sn] = $new_sn;
        $new_sn++;
    }

    $mysqli->begin_transaction();
    try {
        foreach ($students as $row) {
            $old_sn = (int)$row['admission_no'];
            $new_sn = $mapping[$old_sn];
            if ($old_sn === $new_sn) {
                continue; // nothing to change
            }
            $id = (int)$row['id'];

            // Update admit_students
            $s1 = $mysqli->prepare("UPDATE admit_students SET admission_no = ? WHERE id = ?");
            $s1->bind_param("ii", $new_sn, $id);
            $s1->execute();
            $s1->close();

            // Update student_payments
            $s2 = $mysqli->prepare("UPDATE student_payments SET admission_no = ? WHERE admission_no = ?");
            $s2->bind_param("ii", $new_sn, $old_sn);
            $s2->execute();
            $s2->close();

            // Update student_payment_topups
            $s3 = $mysqli->prepare("UPDATE student_payment_topups SET admission_no = ? WHERE admission_no = ?");
            $s3->bind_param("ii", $new_sn, $old_sn);
            $s3->execute();
            $s3->close();
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        // silently ignore on failure to avoid breaking delete
    }
}

$student_id = intval($_GET['id'] ?? 0);

if ($student_id > 0) {
    // Get student details BEFORE deletion for logging
    $getStudentQuery = "SELECT * FROM admit_students WHERE id = ?";
    $getStmt = $mysqli->prepare($getStudentQuery);
    if ($getStmt) {
        $getStmt->bind_param("i", $student_id);
        $getStmt->execute();
        $studentResult = $getStmt->get_result();
        $studentData = $studentResult->fetch_assoc();
        $getStmt->close();
    } else {
        $studentData = null;
    }

    if ($studentData) {
        $stmt = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute()) {

                // Detailed log if principal deleted
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'principal') {
                    $sn     = $studentData['admission_no'] ?? 'N/A';
                    $name   = $studentData['first_name'] ?? '';
                    $gender = $studentData['gender'] ?? '';
                    $class  = $studentData['class_id'] ?? '';
                    $type   = $studentData['day_boarding'] ?? '';
                    $admFee = number_format((float)($studentData['admission_fee'] ?? 0), 2);
                    $uniFee = number_format((float)($studentData['uniform_fee'] ?? 0), 2);
                    $parent = $studentData['parent_contact'] ?? '';

                    // entity_name = current name only
                    $entityName = $name;

                    // details can keep the ORIGINAL SN (for history), but UI will show current SN separately
                    $details =
                        "Principal deleted admitted student (original SN {$sn}). " .
                        "Name: {$name}, Gender: {$gender}, Class ID: {$class}, " .
                        "Type: {$type}, Admission Fee: {$admFee}, Uniform Fee: {$uniFee}, " .
                        "Parent Contact: {$parent}";

                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                    $logSql = "INSERT INTO activity_logs
                        (user_id, user_name, user_role, action, entity_type, entity_id, entity_name, details, ip_address, is_acknowledged, created_at)
                        VALUES (?, ?, ?, 'delete', 'admit_student', ?, ?, ?, ?, 0, NOW())";
                    $logStmt = $mysqli->prepare($logSql);
                    if ($logStmt) {
                        // i,s,s,i,s,s,s → "ississs"
                        $logStmt->bind_param(
                            "ississs",
                            $_SESSION['user_id'],
                            $_SESSION['name'],
                            $_SESSION['role'],
                            $student_id,
                            $entityName,
                            $details,
                            $ipAddress
                        );
                        $logStmt->execute();
                        $logStmt->close();
                    }
                }

                $stmt->close();

                // Re-number admission_no after deletion
                resequenceAdmissionNumbers($mysqli);

                header("Location: admitStudents.php?deleted=1");
                exit();
            } else {
                $stmt->close();
                header("Location: admitStudents.php?error=1");
                exit();
            }
        } else {
            header("Location: admitStudents.php?error=1");
            exit();
        }
    } else {
        header("Location: admitStudents.php?error=1");
        exit();
    }
} else {
    header("Location: admitStudents.php?error=1");
    exit();
}
?>
