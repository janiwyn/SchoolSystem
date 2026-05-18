<?php
$title = "Student Payments";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get System Settings for dynamic permissions
$settingsRes = $mysqli->query("SELECT setting_key, setting_value FROM system_settings");
$sys_settings = [];
if ($settingsRes) {
    while ($row = $settingsRes->fetch_assoc()) {
        $sys_settings[$row['setting_key']] = (int)$row['setting_value'];
    }
}

$canPrincipalEdit = (isset($sys_settings['principal_edit_payments']) && $sys_settings['principal_edit_payments'] === 1);
$canBursarEdit = (isset($sys_settings['bursar_edit_payments']) && $sys_settings['bursar_edit_payments'] === 1);

// Helper to generate next serial number for new admissions
function generateSerialNumber($mysqli) {
    $query = "SELECT MAX(CAST(admission_no AS UNSIGNED)) as max_sn FROM admit_students";
    $result = $mysqli->query($query);
    $row = $result->fetch_assoc();
    $nextSN = ($row['max_sn'] ?? 0) + 1;
    return $nextSN;
}

// ADMIN‑ONLY: bulk delete payments by date range
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_by_date'])
    && (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
) {
    $deleteFrom = $_POST['delete_from'] ?? '';
    $deleteTo   = $_POST['delete_to']   ?? '';

    if ($deleteFrom && $deleteTo) {
        // Normalize order if user swaps dates
        if ($deleteFrom > $deleteTo) {
            [$deleteFrom, $deleteTo] = [$deleteTo, $deleteFrom];
        }

        // Begin transaction
        $mysqli->begin_transaction();
        $deletedCount = 0;

        try {
            // Delete related topups first to avoid FK issues
            $topupSql = "
                DELETE FROM student_payment_topups
                WHERE payment_id IN (
                    SELECT id FROM student_payments
                    WHERE DATE(payment_date) BETWEEN ? AND ?
                )";
            $topupStmt = $mysqli->prepare($topupSql);
            if ($topupStmt) {
                $topupStmt->bind_param('ss', $deleteFrom, $deleteTo);
                $topupStmt->execute();
                $topupStmt->close();
            }

            // Delete main payments
            $paySql = "DELETE FROM student_payments WHERE DATE(payment_date) BETWEEN ? AND ?";
            $payStmt = $mysqli->prepare($paySql);
            if ($payStmt) {
                $payStmt->bind_param('ss', $deleteFrom, $deleteTo);
                $payStmt->execute();
                $deletedCount = $payStmt->affected_rows;
                $payStmt->close();
            }

            $mysqli->commit();
            header("Location: studentPayments.php?deleted=" . (int)$deletedCount);
            exit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            // Optional: log error; for now fall through and reload page
        }
    }
}

// Handle ADD PAYMENT (Top-up) - Admin, Bursar, Principal (if allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $additional_amount = floatval($_POST['additional_amount']);
    
    if ($payment_id > 0 && $additional_amount > 0) {
        $mysqli->begin_transaction();
        try {
            // Get current payment details
            $stmt = $mysqli->prepare("SELECT student_id, amount_paid, expected_tuition, balance, status_approved FROM student_payments WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $payment_id);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if ($row = $res->fetch_assoc()) {
                    $student_id = $row['student_id'];
                    $original_balance = $row['balance'];
                    $current_paid = $row['amount_paid'];
                    $previous_status = $row['status_approved'];
                    
                    // Validation: Ensure we don't overpay (optional, but good practice)
                    if ($additional_amount > $original_balance + 0.01) { // small buffer for float precision
                         throw new Exception("Amount exceeds remaining balance.");
                    }
                    
                    $new_amount_paid = $current_paid + $additional_amount;
                    $new_balance = $original_balance - $additional_amount;
                    if ($new_balance < 0) $new_balance = 0;
                    
                    // 1. Insert into student_payment_topups
                    $topupSql = "INSERT INTO student_payment_topups (payment_id, student_id, topup_amount, original_balance, new_balance, previous_status, status_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())";
                    $topupStmt = $mysqli->prepare($topupSql);
                    if ($topupStmt) {
                        $topupStmt->bind_param("iiddds", $payment_id, $student_id, $additional_amount, $original_balance, $new_balance, $previous_status);
                        if (!$topupStmt->execute()) {
                            throw new Exception("Failed to record top-up: " . $topupStmt->error);
                        }
                        $topupStmt->close();
                        
                        // 2. Update student_payments
                        // Mark as approved immediately
                        $updateSql = "UPDATE student_payments SET amount_paid = ?, balance = ?, status_approved = 'approved' WHERE id = ?";
                        $updateStmt = $mysqli->prepare($updateSql);
                        if ($updateStmt) {
                            $updateStmt->bind_param("ddi", $new_amount_paid, $new_balance, $payment_id);
                            if (!$updateStmt->execute()) {
                                throw new Exception("Failed to update payment record: " . $updateStmt->error);
                            }
                            $updateStmt->close();
                            
                            $mysqli->commit();
                            header("Location: studentPayments.php?topup_success=1");
                            exit();
                        }
                    } else {
                         throw new Exception("Database error (prepare topup): " . $mysqli->error);
                    }
                } else {
                    throw new Exception("Payment record not found.");
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid payment ID or amount.";
    }
}

// Handle EDIT payment record (Admin, Bursar, Principal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payment_record'])) {
    $edit_id = intval($_POST['edit_payment_id']);
    $edit_student_id = intval($_POST['edit_student_id']);
    $edit_full_name = trim($_POST['edit_full_name']);
    $edit_amount_paid = floatval($_POST['edit_amount_paid']);
    $edit_admission_fee = floatval($_POST['edit_admission_fee']);
    $edit_uniform_fee = floatval($_POST['edit_uniform_fee']);
    $edit_class_id = intval($_POST['edit_class_id']);
    $edit_category = trim($_POST['edit_category'] ?? 'Normal');
    $edit_day_boarding = trim($_POST['edit_day_boarding']);
    $edit_expected_tuition = floatval($_POST['edit_expected_tuition']);

    if ($edit_id > 0 && $edit_full_name !== '' && $edit_amount_paid >= 0 && $edit_admission_fee >= 0 && $edit_uniform_fee >= 0) {
        $mysqli->begin_transaction();
        try {
                // 1. Get existing data to enforce role-based restrictions
                $oldDataStmt = $mysqli->prepare("SELECT full_name, amount_paid, admission_fee, uniform_fee, expected_tuition, comment FROM student_payments WHERE id = ?");
                $oldDataStmt->bind_param("i", $edit_id);
                $oldDataStmt->execute();
                $old = $oldDataStmt->get_result()->fetch_assoc();
                $oldDataStmt->close();

                if ($old) {
                    $userRole = strtolower($_SESSION['role'] ?? '');
                    
                    // Role-based field enforcement
                    if ($userRole === 'principal') {
                        // Principal cannot change money
                        $edit_amount_paid = (float)$old['amount_paid'];
                        $edit_admission_fee = (float)$old['admission_fee'];
                        $edit_uniform_fee = (float)$old['uniform_fee'];
                    } elseif ($userRole === 'bursar') {
                        // Bursar cannot change name
                        $edit_full_name = $old['full_name'];
                    }

                    $new_balance = $edit_expected_tuition - $edit_amount_paid;
                    
                    // Get new class name
                    $cnStmt = $mysqli->prepare("SELECT class_name FROM classes WHERE id = ?");
                    $cnStmt->bind_param("i", $edit_class_id);
                    $cnStmt->execute();
                    $cnRow = $cnStmt->get_result()->fetch_assoc();
                    $edit_class_name = $cnRow['class_name'] ?? 'N/A';
                    $cnStmt->close();

                    // 1. Update student_payments
                    $updateStmt = $mysqli->prepare("UPDATE student_payments SET full_name = ?, amount_paid = ?, admission_fee = ?, uniform_fee = ?, balance = ?, class_id = ?, class_name = ?, category = ?, day_boarding = ?, expected_tuition = ?, status_approved = 'approved' WHERE id = ?");
                    $updateStmt->bind_param("sddddisssdi", $edit_full_name, $edit_amount_paid, $edit_admission_fee, $edit_uniform_fee, $new_balance, $edit_class_id, $edit_class_name, $edit_category, $edit_day_boarding, $edit_expected_tuition, $edit_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    // 2. Sync to admit_students
                    if ($edit_student_id > 0) {
                        $admitUpdate = $mysqli->prepare("UPDATE admit_students SET first_name = ?, class_id = ?, category = ?, day_boarding = ?, expected_tuition = ? WHERE id = ?");
                        // s(1):full_name, i(2):class_id, s(3):category, s(4):day_boarding, d(5):expected_tuition, i(6):student_id
                        $admitUpdate->bind_param("sissdi", $edit_full_name, $edit_class_id, $edit_category, $edit_day_boarding, $edit_expected_tuition, $edit_student_id);
                        $admitUpdate->execute();
                        $admitUpdate->close();
                    }

                    $mysqli->commit();
                    header("Location: studentPayments.php?corrected=1");
                    exit();
                }
        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = "Error updating record: " . $e->getMessage();
        }
    }
}

// Handle DELETE single payment record (Admin and Principal only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_single_payment'])) {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'])) {
        $delete_id = intval($_POST['delete_payment_id']);

        if ($delete_id > 0) {
            $mysqli->begin_transaction();
            try {
                // Get the student_id before deleting the payment
                $getSid = $mysqli->prepare("SELECT student_id FROM student_payments WHERE id = ?");
                $getSid->bind_param("i", $delete_id);
                $getSid->execute();
                $sidRes = $getSid->get_result()->fetch_assoc();
                $student_to_check = $sidRes['student_id'] ?? 0;
                $getSid->close();

                // Delete related topups first
                $delTopups = $mysqli->prepare("DELETE FROM student_payment_topups WHERE payment_id = ?");
                $delTopups->bind_param("i", $delete_id);
                $delTopups->execute();
                $delTopups->close();

                // Delete the payment record
                $delPayment = $mysqli->prepare("DELETE FROM student_payments WHERE id = ?");
                $delPayment->bind_param("i", $delete_id);
                $delPayment->execute();
                $delPayment->close();

                // Check if student has any other payments left
                if ($student_to_check > 0) {
                    $otherPays = $mysqli->query("SELECT id FROM student_payments WHERE student_id = $student_to_check LIMIT 1");
                    if ($otherPays->num_rows === 0) {
                        // No other payments, delete from admit_students too
                        $delAdmit = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
                        $delAdmit->bind_param("i", $student_to_check);
                        $delAdmit->execute();
                        $delAdmit->close();
                    }
                }

                $mysqli->commit();
                header("Location: studentPayments.php?deleted=1");
                exit();
            } catch (Throwable $e) {
                $mysqli->rollback();
            }
        }
    } else {
        // Unauthorized attempt
        header("Location: studentPayments.php?error=unauthorized_delete");
        exit();
    }
}

// Handle payment recording (Admit & Pay)
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $full_name = trim($_POST['full_name'] ?? '');
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? '');
    $term = trim($_POST['term'] ?? '');
    
    // Additional fields for new admission
    $gender = trim($_POST['gender'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 0);
    $category = trim($_POST['category'] ?? 'Normal');
    $day_boarding = trim($_POST['day_boarding'] ?? '');
    $admission_fee = floatval($_POST['admission_fee'] ?? 0);
    $uniform_fee = floatval($_POST['uniform_fee'] ?? 0);
    $expected_tuition = floatval($_POST['expected_tuition'] ?? 0);
    $parent_contact = trim($_POST['parent_contact'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    
    if (!$full_name || !$payment_date || !$term || !$gender || !$class_id || !$category || !$day_boarding) {
        $error = "All required fields must be filled (Name, Gender, Class, Category, Day/Boarding, Term, Date)";
    } elseif ($amount_paid < 0) {
        $error = "Amount cannot be negative";
    } elseif ($payment_date > date('Y-m-d')) {
        $error = "Payment date cannot be in the future.";
    } else {
        $mysqli->begin_transaction();
        try {
            // 1. If student_id is 0, this is a new admission
            if ($student_id === 0) {
                // GLOBAL CHECK: Check if student with same name already exists
                $checkStmt = $mysqli->prepare("SELECT id FROM admit_students WHERE first_name = ?");
                $checkStmt->bind_param("s", $full_name);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                
                if ($checkRes->num_rows > 0) {
                    $existing = $checkRes->fetch_assoc();
                    $existing_id = $existing['id'];
                    
                    // Check if they have any payments
                    $pCheck = $mysqli->query("SELECT id FROM student_payments WHERE student_id = $existing_id LIMIT 1");
                    if ($pCheck->num_rows > 0) {
                        // They have payments, so they are truly a duplicate
                        throw new Exception("A student named '$full_name' is already registered with active payments. Please select them from the search list instead.");
                    } else {
                        // They exist in admissions but have no payments (Ghost Student)
                        // We will automatically use this ID instead of creating a new one
                        $student_id = $existing_id;
                        
                        // Update their admission details to match current form
                        $admitUpdate = $mysqli->prepare("UPDATE admit_students SET gender = ?, class_id = ?, category = ?, day_boarding = ?, admission_fee = ?, uniform_fee = ?, expected_tuition = ?, parent_contact = ?, parent_email = ? WHERE id = ?");
                        $admitUpdate->bind_param("sissddsssi", $gender, $class_id, $category, $day_boarding, $admission_fee, $uniform_fee, $expected_tuition, $parent_contact, $parent_email, $student_id);
                        $admitUpdate->execute();
                        
                        // Get their admission number
                        $admNoRes = $mysqli->query("SELECT admission_no FROM admit_students WHERE id = $student_id");
                        $admNoRow = $admNoRes->fetch_assoc();
                        $admission_no = $admNoRow['admission_no'];
                    }
                } else {
                    // Truly new student
                    $admission_no = generateSerialNumber($mysqli);
                    $status = 'approved';
                    $user_id = $_SESSION['user_id'];

                    $admitStmt = $mysqli->prepare(
                        "INSERT INTO admit_students 
                            (admission_no, first_name, gender, class_id, category, day_boarding, 
                             admission_fee, uniform_fee, expected_tuition, parent_contact, 
                             parent_email, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $admitStmt->bind_param("sssissdddsssi", 
                        $admission_no, $full_name, $gender, $class_id, $category, $day_boarding,
                        $admission_fee, $uniform_fee, $expected_tuition, $parent_contact,
                        $parent_email, $status, $user_id);
                    
                    if (!$admitStmt->execute()) {
                        throw new Exception("Error admitting student: " . $admitStmt->error);
                    }
                    $student_id = $admitStmt->insert_id;
                    $admitStmt->close();
                }
                $checkStmt->close();
            } else {
                // For existing students, verify they exist
                $verifyStmt = $mysqli->prepare("SELECT admission_no FROM admit_students WHERE id = ?");
                $verifyStmt->bind_param("i", $student_id);
                $verifyStmt->execute();
                $vRes = $verifyStmt->get_result();
                if ($vRes->num_rows === 0) {
                    throw new Exception("Student ID not found in admission records.");
                }
                $studentData = $vRes->fetch_assoc();
                $admission_no = $studentData['admission_no'];
                $verifyStmt->close();
            }

            // 2. Check for duplicate payment record
            $dupCheck = $mysqli->prepare("SELECT id FROM student_payments WHERE student_id = ? AND term = ? AND payment_date = ? AND amount_paid = ? LIMIT 1");
            $dupCheck->bind_param("issd", $student_id, $term, $payment_date, $amount_paid);
            $dupCheck->execute();
            if ($dupCheck->get_result()->num_rows > 0) {
                throw new Exception("Duplicate payment detected for this student.");
            }
            $dupCheck->close();

            // 3. Get class name
            $classStmt = $mysqli->prepare("SELECT class_name FROM classes WHERE id = ?");
            $classStmt->bind_param("i", $class_id);
            $classStmt->execute();
            $classRow = $classStmt->get_result()->fetch_assoc();
            $class_name = $classRow['class_name'] ?? 'N/A';
            $classStmt->close();

            // 4. Record payment
            $balance = $expected_tuition - $amount_paid;
            $user_id = $_SESSION['user_id'];
            $status_approved = 'approved';
            
            $insertPay = $mysqli->prepare("INSERT INTO student_payments (student_id, admission_no, full_name, day_boarding, gender, class_id, class_name, category, term, expected_tuition, amount_paid, balance, admission_fee, uniform_fee, parent_contact, parent_email, payment_date, status_approved, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $insertPay->bind_param("issssisssdddddssssi", 
                $student_id, $admission_no, $full_name, $day_boarding, 
                $gender, $class_id, $class_name, $category, $term, $expected_tuition, 
                $amount_paid, $balance, $admission_fee, $uniform_fee, 
                $parent_contact, $parent_email, $payment_date, $status_approved, $user_id);
            
            if (!$insertPay->execute()) {
                throw new Exception("Error recording payment: " . $insertPay->error);
            }
            $insertPay->close();

            $mysqli->commit();
            header("Location: studentPayments.php?payment_recorded=1");
            exit();

        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Payment updated successfully!";
}
if (isset($_GET['corrected']) && $_GET['corrected'] == 1) {
    $message = "Payment record corrected successfully!";
}

// Sorting logic for Student Payments
$sort_by = $_GET['sort_by'] ?? $_COOKIE['student_payments_sort_by'] ?? 'payment_date';
$sort_order = $_GET['sort_order'] ?? $_COOKIE['student_payments_sort_order'] ?? 'DESC';

// Sanitize
$allowed_cols = ['admission_no', 'payment_date'];
if (!in_array($sort_by, $allowed_cols)) $sort_by = 'payment_date';
if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) $sort_order = 'DESC';

// Persist via cookie if explicitly changed in URL
if (isset($_GET['sort_by']) || isset($_GET['sort_order'])) {
    setcookie('student_payments_sort_by', $sort_by, time() + (86400 * 30 * 12), "/"); 
    setcookie('student_payments_sort_order', $sort_order, time() + (86400 * 30 * 12), "/");
}

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query
$filterWhere = "1=1";
$search_filter = $_GET['search'] ?? '';
$class_filter = $_GET['class'] ?? '';
$term_filter = $_GET['term'] ?? '';
$pay_status_filter = $_GET['pay_status'] ?? ''; // Standardized to match form
$show_duplicates = $_GET['duplicates'] ?? ''; // Added duplicates filter
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$category_filter = $_GET['category'] ?? ''; // New Category Filter
// These are now handled by the sorting logic above
// $sort_order = $_GET['sort'] ?? 'ASC';

if ($search_filter) {
    $searchTerm = '%' . $mysqli->real_escape_string($search_filter) . '%';
    $filterWhere .= " AND (full_name LIKE '$searchTerm' OR admission_no LIKE '$searchTerm')";
}
if ($date_from) {
    $filterWhere .= " AND DATE(payment_date) >= '" . $mysqli->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $filterWhere .= " AND DATE(payment_date) <= '" . $mysqli->real_escape_string($date_to) . "'";
}
if ($term_filter) {
    $filterWhere .= " AND term = '" . $mysqli->real_escape_string($term_filter) . "'";
}
if ($show_duplicates) {
    $filterWhere .= " AND full_name IN (SELECT full_name FROM student_payments GROUP BY full_name HAVING COUNT(*) > 1)";
}

if ($pay_status_filter) {
    switch ($pay_status_filter) {
        case 't1_paid':
            $filterWhere .= " AND amount_paid >= (expected_tuition / 3 - 0.01)";
            break;
        case 't1_partial':
            $filterWhere .= " AND amount_paid > 0.01 AND amount_paid < (expected_tuition / 3 - 0.01)";
            break;
        case 't2_paid':
            $filterWhere .= " AND amount_paid >= (2 * expected_tuition / 3 - 0.01)";
            break;
        case 't2_partial':
            $filterWhere .= " AND amount_paid > (expected_tuition / 3 + 0.01) AND amount_paid < (2 * expected_tuition / 3 - 0.01)";
            break;
        case 't3_paid':
            $filterWhere .= " AND amount_paid >= (expected_tuition - 0.01)";
            break;
        case 't3_partial':
            $filterWhere .= " AND amount_paid > (2 * expected_tuition / 3 + 0.01) AND amount_paid < (expected_tuition - 0.01)";
            break;
        case 'unpaid':
            $filterWhere .= " AND amount_paid <= 0.01";
            break;
        case 'unpaid_admission':
            $filterWhere .= " AND admission_fee <= 0.01";
            break;
        case 'unpaid_uniform':
            $filterWhere .= " AND uniform_fee <= 0.01";
            break;
    }
}
if ($class_filter) {
    $filterWhere .= " AND class_id = '" . intval($class_filter) . "'";
}
if ($category_filter) {
    $filterWhere .= " AND category = '" . $mysqli->real_escape_string($category_filter) . "'";
}

// Pagination setup
$records_per_page = 60;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM student_payments WHERE $filterWhere";
$countResult = $mysqli->query($countQuery);
$countRow = $countResult->fetch_assoc();
$total_records = $countRow['total'];
$total_pages = ceil($total_records / $records_per_page);

// Build Order By clause
$orderBy = "payment_date DESC"; // Default
if ($sort_by === 'admission_no') {
    $orderBy = "CAST(admission_no AS UNSIGNED) $sort_order";
} else {
    $orderBy = "payment_date $sort_order";
}
$orderBy .= ", id DESC"; // Stability

// Get all payments recorded with filter and pagination
$paymentsQuery = "SELECT 
    id, student_id, admission_no, full_name, day_boarding, gender, class_id, class_name, category, term,
    expected_tuition, amount_paid, balance, admission_fee, uniform_fee,
    parent_contact, payment_date, created_at, status_approved, comment
FROM student_payments
WHERE $filterWhere
ORDER BY $orderBy
LIMIT $offset, $records_per_page";

$paymentsResult = $mysqli->query($paymentsQuery);
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM student_payments ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
$terms = $termsResult ? $termsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get active classes (those with fees or students) for filter and modals
$classesQuery = "SELECT id, class_name FROM classes 
                 WHERE id IN (SELECT DISTINCT class_id FROM fee_structure) 
                 OR id IN (SELECT DISTINCT class_id FROM admit_students)
                 ORDER BY class_name ASC";
$classesResult = $mysqli->query($classesQuery);
$all_classes = $classesResult ? $classesResult->fetch_all(MYSQLI_ASSOC) : [];

// Calculate totals for all payments (not just current page)
$totalsQuery = "SELECT 
    SUM(expected_tuition) as total_tuition,
    SUM(amount_paid) as total_paid,
    SUM(balance) as total_balance,
    SUM(admission_fee) as total_admission,
    SUM(uniform_fee) as total_uniform
FROM student_payments
WHERE $filterWhere";

$totalsResult = $mysqli->query($totalsQuery);
$totals = $totalsResult->fetch_assoc();

// Get current user role - MUST be before student loading
$userRole = strtolower($_SESSION['role'] ?? '');
$canRecordPayment = in_array($userRole, ['admin', 'bursar', 'principal']);

// Get approved students for dropdown - LOAD DIRECTLY
$approved_students = [];
if ($canRecordPayment) {
    $approvedStudentsQuery = "SELECT 
        s.id, s.admission_no, s.first_name, s.gender, s.class_id, 
        s.category, s.day_boarding, s.admission_fee, s.uniform_fee, 
        s.parent_contact, s.parent_email, s.status,
        c.class_name
    FROM admit_students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.status IN ('approved', 'unapproved')
    ORDER BY s.first_name ASC";
    
    $approvedStudentsResult = $mysqli->query($approvedStudentsQuery);
    if ($approvedStudentsResult) {
        $approved_students = $approvedStudentsResult->fetch_all(MYSQLI_ASSOC);
    }
}

// Build expected tuition map: [class_id][term] => total_amount
$classTermExpected = [];
$classNameTuition = [];
$classNames = [];

// Use JOIN to get class names along with IDs for name-based fallback
$classExpectedQuery = "SELECT fs.class_id, c.class_name, fs.term, SUM(fs.amount) AS total_expected 
                       FROM fee_structure fs 
                       LEFT JOIN classes c ON fs.class_id = c.id
                       GROUP BY fs.class_id, fs.term";
$classExpectedResult = $mysqli->query($classExpectedQuery);
if ($classExpectedResult) {
    while ($row = $classExpectedResult->fetch_assoc()) {
        $cid = (int)$row['class_id'];
        $name = strtolower(trim($row['class_name'] ?? ''));
        $trm = $row['term'];
        
        if (!isset($classTermExpected[$cid])) {
            $classTermExpected[$cid] = [];
        }
        $classTermExpected[$cid][$trm] = (float)$row['total_expected'];

        if ($name) {
            $classNameTuition[$name][$trm] = (float)$row['total_expected'];
            // Store a dot-free version for fuzzy matching (e.g. S.4S matches S4S)
            $cleanName = str_replace('.', '', $name);
            $classNameTuition[$cleanName][$trm] = (float)$row['total_expected'];
        }
    }
}

// Build category-based expected tuition map: [category_name][term] => amount
$categoryExpected = [];
$catQuery = "SELECT category_name, term, amount FROM category_fees";
$catResult = $mysqli->query($catQuery);
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $cname = strtolower(trim($row['category_name']));
        $trm = $row['term'];
        if (!isset($categoryExpected[$cname])) {
            $categoryExpected[$cname] = [];
        }
        $categoryExpected[$cname][$trm] = (float)$row['amount'];
    }
}

// Also get class names for the map
$cnResult = $mysqli->query("SELECT id, class_name FROM classes");
if ($cnResult) {
    while ($row = $cnResult->fetch_assoc()) {
        $classNames[(int)$row['id']] = $row['class_name'];
    }
}

// Get a default/current term (latest term defined in fee_structure)
$currentTerm = '';
$currentTermQuery = "SELECT term FROM fee_structure ORDER BY id DESC LIMIT 1";
$currentTermResult = $mysqli->query($currentTermQuery);
if ($currentTermResult) {
    $termRow = $currentTermResult->fetch_assoc();
    $currentTerm = $termRow['term'] ?? '';
}

// Get dynamic student categories from category_fees table
$db_categories = [];
$catNamesQuery = "SELECT DISTINCT category_name FROM category_fees ORDER BY category_name ASC";
$catNamesRes = $mysqli->query($catNamesQuery);
if ($catNamesRes) {
    while ($cRow = $catNamesRes->fetch_assoc()) {
        if (strtolower($cRow['category_name']) !== 'normal') {
            $db_categories[] = $cRow['category_name'];
        }
    }
}
$student_categories = array_merge(['Normal'], $db_categories);
?>

<?php if (isset($_GET['corrected'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> Payment record corrected and approved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['resequenced'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> Admission numbers have been re-sequenced successfully. All gaps have been removed.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
    <?php if ($canRecordPayment): ?>
        <button type="button" class="btn-toggle-form" onclick="togglePaymentForm()">
            <i class="bi bi-chevron-right"></i> Admit
        </button>

    <?php else: ?>
        <button type="button" class="btn-toggle-form" data-bs-toggle="modal" data-bs-target="#restrictionModal">
            <i class="bi bi-chevron-right"></i> Admit 
        </button>
    <?php endif; ?>
</div>

<!-- Student Preview Card (Shows when student is selected) -->
<div id="studentPreviewCard" class="student-preview-card">
    <div class="preview-card-content">
        <div class="preview-logo-container">
            <img src="../../assets/images/logo.png" alt="School Logo" onerror="this.src='../../assets/images/default-logo.png'">
        </div>
        <div class="preview-details">
            <div class="preview-student-name" id="previewStudentName">-</div>
            <div class="preview-info-grid">
                <div class="preview-info-item">
                    <div class="preview-info-label">Class</div>
                    <div class="preview-info-value" id="previewClass">-</div>
                </div>
                <div class="preview-info-item">
                    <div class="preview-info-label">Term</div>
                    <div class="preview-info-value" id="previewTerm">-</div>
                </div>
                <div class="preview-info-item">
                    <div class="preview-info-label">Status</div>
                    <div class="preview-info-value">
                        <span class="preview-status-badge" id="previewDayBoarding">-</span>
                    </div>
                </div>
                <div class="preview-info-item">
                    <div class="preview-info-label">Gender</div>
                    <div class="preview-info-value" id="previewGender">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Student Payment Form (Collapsible) -->
<?php if ($canRecordPayment): ?>
    <div class="card shadow-sm border-0 mb-4" id="paymentFormCard" style="display: none;">
        <div class="card-header form-header text-white">
            <h5 class="mb-0">Admit</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="paymentForm" class="row g-3">
                <input type="hidden" name="record_payment" value="1">
                <input type="hidden" name="student_id" id="studentIdHidden" value="0">
                
                <!-- Student Selection (Text with Datalist) -->
                <div class="col-md-6">
                    <label class="form-label">Type Student Name</label>
                    <input type="text" name="full_name" id="studentNameInput" class="form-control" placeholder="Search or type new name..." list="studentList" oninput="handleStudentInput()" required>
                    <datalist id="studentList">
                        <?php foreach ($approved_students as $s): ?>
                            <option value="<?= htmlspecialchars($s['first_name']) ?>" 
                                    data-id="<?= $s['id'] ?>"
                                    data-gender="<?= htmlspecialchars($s['gender']) ?>"
                                    data-class="<?= $s['class_id'] ?>"
                                    data-boarding="<?= htmlspecialchars($s['day_boarding']) ?>"
                                    data-admission-fee="<?= $s['admission_fee'] ?>"
                                    data-uniform-fee="<?= $s['uniform_fee'] ?>"
                                    data-contact="<?= htmlspecialchars($s['parent_contact']) ?>"
                                    data-email="<?= htmlspecialchars($s['parent_email'] ?? '') ?>"
                                    data-status="<?= htmlspecialchars($s['status']) ?>"
                                    data-class-name="<?= htmlspecialchars($s['class_name'] ?? 'N/A') ?>"
                                    data-category="<?= htmlspecialchars($s['category'] ?? 'Normal') ?>">
                                (SN: <?= htmlspecialchars($s['admission_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-info-circle"></i> If the student is new, just type the full name and fill other fields below.
                    </small>
                </div>
                
                <!-- Active Form Fields -->
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <input type="text" id="studentStatus" class="form-control" readonly placeholder="Auto-set on save">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Sex</label>
                    <select name="gender" id="gender" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select name="class_id" id="classSelect" class="form-control" required onchange="document.getElementById('category').value = 'Normal'; handleClassChange()">
                        <option value="">Select Class</option>
                        <?php foreach ($all_classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" id="category" class="form-control" onchange="handleClassChange()" required>
                        <?php foreach ($student_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <input type="text" name="term" id="term" class="form-control" readonly placeholder="Auto-filled from tuition">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Day/Boarding</label>
                    <select name="day_boarding" id="dayBoarding" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Day">Day</option>
                        <option value="Boarding">Boarding</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Expected Tuition</label>
                    <input type="number" name="expected_tuition" id="expectedTuition" class="form-control" step="0.01" readonly placeholder="Auto-filled">
                </div>
                
                <!-- Payment Information -->
                <div class="col-md-3">
                    <label class="form-label">Amount Paid</label>
                    <input type="number" name="amount_paid" id="amountPaid" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Admission Fee</label>
                    <input type="number" name="admission_fee" id="admissionFee" class="form-control" step="0.01">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Uniform Fee</label>
                    <input type="number" name="uniform_fee" id="uniformFee" class="form-control" step="0.01">
                </div>
                
                <!-- Parent Information -->
                <div class="col-md-6">
                    <label class="form-label">Parent Contact</label>
                    <input type="text" name="parent_contact" id="parentContact" class="form-control" placeholder="07...">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Parent Email</label>
                    <input type="email" name="parent_email" id="parentEmail" class="form-control" placeholder="optional@email.com">
                </div>
                
                <!-- Payment Date -->
                <div class="col-md-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <!-- Submit Button -->
                <div class="col-12">
                    <button type="submit" name="record_payment" class="btn btn-form-submit">
                        <i class="bi bi-person-plus-fill"></i> Admit & Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Name</label>
                    <input type="text" name="search" class="form-control" placeholder="Student name" value="<?= htmlspecialchars($search_filter) ?>">
                </div>

                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <label>Term</label>
                    <select name="term" class="form-control">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?= htmlspecialchars($t['term']) ?>" <?= $term_filter === $t['term'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['term']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Class</label>
                    <select name="class" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($all_classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$class_filter === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($student_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>



                <div class="filter-group">
                    <label>Pay Status</label>
                  <select name="pay_status" class="form-select form-select-sm">
                <option value="">All Pay Statuses</option>
                <option value="unpaid" <?= $pay_status_filter === 'unpaid' ? 'selected' : '' ?>>Not Paid Anything</option>
                <option value="t1_partial" <?= $pay_status_filter === 't1_partial' ? 'selected' : '' ?>>Term 1: Partial</option>
                <option value="t1_paid" <?= $pay_status_filter === 't1_paid' ? 'selected' : '' ?>>Term 1: Cleared</option>
                <option value="t2_partial" <?= $pay_status_filter === 't2_partial' ? 'selected' : '' ?>>Term 2: Partial</option>
                <option value="t2_paid" <?= $pay_status_filter === 't2_paid' ? 'selected' : '' ?>>Term 2: Cleared</option>
                <option value="t3_partial" <?= $pay_status_filter === 't3_partial' ? 'selected' : '' ?>>Term 3: Partial</option>
                <option value="t3_paid" <?= $pay_status_filter === 't3_paid' ? 'selected' : '' ?>>Term 3: Cleared (Annual)</option>
                <option value="unpaid_admission" <?= $pay_status_filter === 'unpaid_admission' ? 'selected' : '' ?>>Unpaid Admission Fee</option>
                <option value="unpaid_uniform" <?= $pay_status_filter === 'unpaid_uniform' ? 'selected' : '' ?>>Unpaid Uniform Fee</option>
            </select>
                </div>

                <div class="filter-group">
                    <label>View</label>
                    <select name="duplicates" class="form-control">
                        <option value="">Standard View</option>
                        <option value="1" <?= $show_duplicates === '1' ? 'selected' : '' ?>>Show Duplicates</option>
                    </select>
                </div>

                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="studentPayments.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Payment Records Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Payment Records</h5>
            <div class="text-primary fw-bold" style="font-size: 1.1rem; letter-spacing: 0.5px;">
                <?php
                $summary_text = "TOTAL: " . number_format($total_records) . " STUDENTS";
                if ($class_filter) {
                    $selected_class_name = 'N/A';
                    foreach ($all_classes as $cls) {
                        if ((int)$cls['id'] === (int)$class_filter) {
                            $selected_class_name = $cls['class_name'];
                            break;
                        }
                    }
                    $summary_text = "TOTAL ($selected_class_name): " . number_format($total_records) . " STUDENTS";
                }
                echo htmlspecialchars($summary_text);
                ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Admin‑only bulk delete button, just above the student payments table -->
            <!-- <button type="button"
                    class="btn btn-danger mb-3"
                    data-bs-toggle="modal"
                    data-bs-target="#deletePaymentsByDateModal">
                <i class="bi bi-trash"></i> Delete Payments by Date
            </button> -->

            <!-- Admin‑only re-sequence button -->
            <form method="POST" action="../admin/resequence_adm_nos.php" style="display:inline;" onsubmit="return confirm('WARNING: This will re-assign Admission Numbers for ALL students in the system to remove gaps (1, 2, 3...). Existing numbers will be changed. This action is irreversible. Proceed?');">
                <button type="submit" name="resequence_adm_nos" class="btn btn-warning mb-3 ms-2 text-dark fw-bold">
                    <i class="bi bi-sort-numeric-down"></i> Fix Adm No Gaps
                </button>
            </form>
        <?php endif; ?>
        
        <?php if (empty($payments)): ?>
            <div class="alert alert-info">No payment records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped student-payments-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'admission_no', 'sort_order' => ($sort_by === 'admission_no' && $sort_order === 'ASC' ? 'DESC' : 'ASC')])) ?>" class="text-decoration-none text-white d-flex align-items-center justify-content-between">
                                    Adm No
                                    <i class="bi <?= ($sort_by === 'admission_no' ? ($sort_order === 'ASC' ? 'bi-sort-numeric-down' : 'bi-sort-numeric-up-alt') : 'bi-arrow-down-up') ?> ms-1"></i>
                                </a>
                            </th>
                            <th>Name</th>
                            <th>F/M</th>
                            <th>Class</th>
                            <th>Category</th>
                            <th>Day/Boarding</th>
                            <th>Expected Tuition</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Tuition Progress</th>
                            <th>Terms Cleared</th>
                            <th>Admission Fee</th>
                            <th>Uniform Fee</th>
                            <th>Parent Contact</th>
                            <th>Payment Date</th>
                            <th>Pay Status</th>
                            <th>Topup</th>
                            <th>Invoice/Receipt</th>
                            <th>Corrections</th>
                            <th style="min-width: 300px;">Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['admission_no']) ?></td>
                                <td><?= htmlspecialchars($payment['full_name']) ?></td>
                                <td><?= $payment['gender'] === 'Male' ? 'M' : 'F' ?></td>
                                <td><?= htmlspecialchars($payment['class_name']) ?></td>
                                <td><?= htmlspecialchars($payment['category'] ?? 'Normal') ?></td>
                                <td><?= htmlspecialchars($payment['day_boarding']) ?></td>
                                <td><?= number_format($payment['expected_tuition'], 2) ?></td>
                                <td><?= number_format($payment['amount_paid'], 2) ?></td>
                                <td><?= number_format($payment['balance'], 2) ?></td>
                                <td>
                                    <?php
                                    $total = (float)$payment['expected_tuition'];
                                    $paid = (float)$payment['amount_paid'];
                                    $t_threshold = $total / 3;
                                    
                                    // Calculate segment widths (each term is 33.33% of the bar)
                                    $w1 = ($total > 0) ? (min($paid, $t_threshold) / $total) * 100 : 0;
                                    $w2 = ($total > 0) ? (max(0, min($paid - $t_threshold, $t_threshold)) / $total) * 100 : 0;
                                    $w3 = ($total > 0) ? (max(0, min($paid - (2 * $t_threshold), $t_threshold)) / $total) * 100 : 0;
                                    ?>
                                    <div class="term-progress-container" title="Paid: <?= number_format($paid, 2) ?> / <?= number_format($total, 2) ?>">
                                        <div class="term-progress">
                                            <div class="term-segment t1" style="width: <?= $w1 ?>%"></div>
                                            <div class="term-segment t2" style="width: <?= $w2 ?>%"></div>
                                            <div class="term-segment t3" style="width: <?= $w3 ?>%"></div>
                                        </div>
                                        <div class="term-calibrations">
                                            <span>T1</span>
                                            <span>T2</span>
                                            <span>T3</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="terms-paid-badges">
                                        <span class="term-badge <?= ($paid >= $t_threshold - 0.01) ? 'paid' : 'unpaid' ?>">T1</span>
                                        <span class="term-badge <?= ($paid >= (2 * $t_threshold) - 0.01) ? 'paid' : 'unpaid' ?>">T2</span>
                                        <span class="term-badge <?= ($paid >= $total - 0.01) ? 'paid' : 'unpaid' ?>">T3</span>
                                    </div>
                                </td>
                                <td><?= number_format($payment['admission_fee'], 2) ?></td>
                                <td><?= number_format($payment['uniform_fee'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['parent_contact']) ?></td>
                                <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                                <td>
                                    <?php if ($payment['balance'] == 0): ?>
                                        <span class="status-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-incomplete">Incomplete</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($payment['balance'] > 0): ?>
                                        <button type="button" class="btn-pay" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="setPaymentId(<?= $payment['id'] ?>, <?= $payment['balance'] ?>)">
                                            <i class="bi bi-cash-coin"></i> Pay
                                        </button>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['balance'] == 0): ?>
                                        <!-- Receipt Button - for fully paid (any approval status) -->
                                        <a href="print-receipt.php?id=<?= $payment['id'] ?>" class="btn-receipt" target="_blank" title="Print Receipt">
                                            <i class="bi bi-receipt"></i> Receipt
                                        </a>
                                    <?php elseif ($payment['balance'] > 0): ?>
                                        <!-- Invoice Button - for unpaid balance (any approval status) -->
                                        <a href="print-invoice.php?id=<?= $payment['id'] ?>" class="btn-invoice" target="_blank" title="Print Invoice">
                                            <i class="bi bi-file-text"></i> Invoice
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <!-- NEW: Corrections Column -->
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-primary" title="Edit Payment"
                                                data-bs-toggle="modal" data-bs-target="#editPaymentModal"
                                                onclick="loadEditPayment(<?= $payment['id'] ?>, <?= $payment['amount_paid'] ?>, <?= $payment['admission_fee'] ?>, <?= $payment['uniform_fee'] ?>, <?= $payment['expected_tuition'] ?>, '<?= htmlspecialchars($payment['full_name'], ENT_QUOTES) ?>', <?= $payment['student_id'] ?>, '<?= htmlspecialchars($payment['class_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($payment['category'] ?? 'Normal', ENT_QUOTES) ?>', '<?= htmlspecialchars($payment['day_boarding'], ENT_QUOTES) ?>', '<?= htmlspecialchars($payment['term'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this payment record for <?= htmlspecialchars($payment['full_name'], ENT_QUOTES) ?>? This action cannot be undone.');">
                                                <input type="hidden" name="delete_payment_id" value="<?= $payment['id'] ?>">
                                                <button type="submit" name="delete_single_payment" class="btn btn-sm btn-danger" title="Delete Payment">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <!-- Inline Comment Column -->
                                <td>
                                    <div class="d-flex align-items-start gap-1">
                                        <div class="input-group input-group-sm" style="width: 240px;">
                                            <textarea class="form-control comment-input auto-expand" 
                                                   id="comment_<?= $payment['id'] ?>" 
                                                   placeholder="Type comment..."
                                                   rows="1"
                                                   oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                                                   onblur="saveComment(<?= $payment['id'] ?>)"><?= htmlspecialchars($payment['comment'] ?? '') ?></textarea>
                                            <button class="btn btn-success" type="button" onclick="saveComment(<?= $payment['id'] ?>)" style="align-self: flex-start;">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </div>
                                        <div id="comment_status_<?= $payment['id'] ?>" class="ms-1" style="min-width: 60px; line-height: 1; padding-top: 5px;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="table-totals">
                            <td colspan="5" class="text-end fw-bold">TOTALS:</td>
                            <td class="totals-expected"><?= number_format($totals['total_tuition'] ?? 0, 2) ?></td>
                            <td class="totals-paid"><?= number_format($totals['total_paid'] ?? 0, 2) ?></td>
                            <td class="totals-balance"><?= number_format($totals['total_balance'] ?? 0, 2) ?></td>
                            <td colspan="2"></td> <!-- Progress & Terms columns -->
                            <td class="totals-admission"><?= number_format($totals['total_admission'] ?? 0, 2) ?></td>
                            <td class="totals-uniform"><?= number_format($totals['total_uniform'] ?? 0, 2) ?></td>
                            <td colspan="4"></td>
                            <td></td> <!-- Topup -->
                            <td></td> <!-- Invoice/Receipt -->
                            <td></td> <!-- Corrections -->
                            <td></td> <!-- Comment column -->
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . urlencode($approval_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>

            <?php endif; ?>
            
            <!-- Pagination Info - Always show if records exist -->
            <?php if ($total_records > 0): ?>
                <div class="text-center mt-3">
                    <p class="text-muted" style="font-size: 13px;">
                        Showing <?= ($total_records > 0 ? $offset + 1 : 0) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> payments
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Additional Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header form-header text-white">
                <h5 class="modal-title">Make Additional Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="payment_id" id="modalPaymentId">
                
                <div class="mb-3">
                    <label class="form-label">Remaining Balance</label>
                    <input type="text" id="modalBalance" class="form-control readonly-field" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Amount to Pay</label>
                    <input type="number" name="additional_amount" id="modalAmount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_payment" class="btn btn-form-submit">
                        <i class="bi bi-check-circle"></i> Pay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restriction Modal for Principal -->
<div class="modal fade" id="restrictionModal" tabindex="-1" aria-labelledby="restrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="restrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Record Student Payment</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong>, <strong>Bursar</strong>, and <strong>Principal</strong> roles have permission to record student payments.
                </p>
                <p class="text-muted mt-2">
                    Please contact the system administrator if you believe this is an error.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Admin‑only Bootstrap modal for delete by date range -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div class="modal fade" id="deletePaymentsByDateModal" tabindex="-1" aria-labelledby="deletePaymentsByDateLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deletePaymentsByDateLabel">
                        <i class="bi bi-trash"></i> Delete Payments by Date Range
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        This will permanently delete all student payment records (and their top‑ups)
                        whose payment date falls between the selected dates (inclusive).
                    </p>
                    <div class="mb-3">
                        <label for="delete_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="delete_from" name="delete_from" required>
                    </div>
                    <div class="mb-3">
                        <label for="delete_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="delete_to" name="delete_to" required>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        This action cannot be undone. Please double‑check the dates before confirming.
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="delete_by_date" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Records
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- NEW: Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header form-header text-white py-3">
                    <h5 class="modal-title" id="editPaymentModalLabel">
                        <i class="bi bi-pencil-square me-2"></i> Edit Payment Record & Transition Management
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="edit_payment_id" id="editPaymentId">
                    <input type="hidden" name="edit_student_id" id="editStudentId">

                    <div class="row g-4">
                        <!-- Left Column: Student Details -->
                        <div class="col-lg-4 border-end">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">Student Information</h6>
                            <div class="mb-3">
                                <label for="editPaymentStudentName" class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="edit_full_name" id="editPaymentStudentName" required>
                            </div>
                            <div class="mb-3">
                                <label for="editPaymentClass" class="form-label fw-bold">Class</label>
                                <select class="form-select" name="edit_class_id" id="editPaymentClass" onchange="document.getElementById('editPaymentCategory').value = 'Normal'; handleEditClassChange()" required>
                                    <?php foreach ($all_classes as $cls): ?>
                                        <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editPaymentCategory" class="form-label fw-bold">Category</label>
                                <select class="form-select" name="edit_category" id="editPaymentCategory" onchange="handleEditClassChange()" required>
                                    <?php foreach ($student_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editPaymentBoarding" class="form-label fw-bold">Day/Boarding Status</label>
                                <select class="form-select" name="edit_day_boarding" id="editPaymentBoarding" required>
                                    <option value="Day">Day</option>
                                    <option value="Boarding">Boarding</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editPaymentTerm" class="form-label fw-bold">Current Term</label>
                                <input type="text" class="form-control" name="edit_term" id="editPaymentTerm" oninput="handleEditClassChange()" placeholder="e.g. Term 1" required>
                            </div>
                        </div>

                        <!-- Middle Column: Transition & Fees -->
                        <div class="col-lg-4 border-end">
                            <h6 class="text-success fw-bold mb-3 border-bottom pb-2">Tuition & Transitions</h6>
                            
                            <div class="alert alert-soft-info py-2 mb-3 small">
                                <i class="bi bi-info-circle me-1"></i> Use this section if the student changed from Day to Boarding (or vice versa) mid-year.
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Amount Paid So Far</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control bg-light" id="editPaidSoFar" readonly>
                                </div>
                                <small class="text-muted">Total payments recorded before this edit.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-success">Remaining Expected Tuition</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success text-white">$</span>
                                    <input type="number" class="form-control border-success" id="editRemainingExpected" step="0.01" min="0" placeholder="Enter remaining amount" oninput="calculateTransitionTuition()">
                                </div>
                                <small class="text-success">Enter the new amount for remaining terms.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Final Annual Expected Tuition</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">$</span>
                                    <input type="number" class="form-control fw-bold" name="edit_expected_tuition" id="editPaymentExpected" readonly style="background-color: #f8f9fa; border: 2px solid #0d6efd;">
                                </div>
                                <small class="text-primary">Auto-calculated: Paid + Remaining</small>
                            </div>
                        </div>

                        <!-- Right Column: Balance & Extras -->
                        <div class="col-lg-4">
                            <h6 class="text-warning fw-bold mb-3 border-bottom pb-2">Payments & Balance</h6>
                            
                            <div class="mb-3">
                                <label for="editPaymentAmountPaid" class="form-label fw-bold">New Payment Amount</label>
                                <input type="number" class="form-control" id="editPaymentAmountPaid" name="edit_amount_paid" step="0.01" min="0" required oninput="calculateEditBalance()" <?= $moneyReadonly ?>>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Calculated New Balance</label>
                                <input type="number" class="form-control text-danger fw-bold" id="editPaymentNewBalance" readonly style="background-color: #f8f9fa;">
                            </div>

                            <div class="row g-2">
                                <div class="col-6 mb-3">
                                    <label for="editPaymentAdmissionFee" class="form-label small fw-bold">Admission Fee</label>
                                    <input type="number" class="form-control form-control-sm" id="editPaymentAdmissionFee" name="edit_admission_fee" step="0.01" min="0" required <?= $moneyReadonly ?>>
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="editPaymentUniformFee" class="form-label small fw-bold">Uniform Fee</label>
                                    <input type="number" class="form-control form-control-sm" id="editPaymentUniformFee" name="edit_uniform_fee" step="0.01" min="0" required <?= $moneyReadonly ?>>
                                </div>
                            </div>

                            <div class="alert alert-warning small mt-3">
                                <i class="bi bi-shield-check me-1"></i> Saving will automatically set status to <strong>Approved</strong>.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_payment_record" class="btn btn-form-submit px-4">
                        <i class="bi bi-check-circle me-2"></i> Save Correction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Expose expected tuition map + current term to JS -->
<script>
window.classTermTuition = <?= json_encode($classTermExpected, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT) ?>;
window.classNameTuition = <?= json_encode($classNameTuition, JSON_FORCE_OBJECT) ?>;
window.categoryTuition = <?= json_encode($categoryExpected, JSON_FORCE_OBJECT) ?>;
window.classNames = <?= json_encode($classNames, JSON_FORCE_OBJECT) ?>;
window.currentTerm = <?= json_encode($currentTerm ?: 'Term 1') ?>;

// Helper to normalize different term strings (e.g. "Term 3", "term 3", "3", "t3" all become "term 3")
function normalizeTermName(term) {
    if (!term) return '';
    const t = String(term).trim().toLowerCase();
    
    // Check for annual
    if (t.includes('annual') || t.includes('annually') || t.includes('year') || t === 'yr') {
        return 'annual';
    }
    
    // Extract numbers to match Term 1, Term 2, Term 3
    const numMatch = t.match(/\d+/);
    if (numMatch) {
        return 'term ' + numMatch[0];
    }
    
    // Default return trimmed lowercased
    return t;
}

// Inline override of handleClassChange to guarantee cache bypass online
function handleClassChange() {
    const classSelect = document.getElementById('classSelect');
    const classId = classSelect.value;
    const termInput = document.getElementById('term');
    const tuitionInput = document.getElementById('expectedTuition');
    
    if (!classId) {
        termInput.value = '';
        tuitionInput.value = '';
        return;
    }

    const selectedOption = classSelect.options[classSelect.selectedIndex];
    const className = selectedOption ? selectedOption.text : '';
    const category = document.getElementById('category').value;
    console.log('Class Change triggered:', { classId, className, category });
    
    // 1. Try to find tuition data by Category first (if not Normal)
    let tuitionData = null;
    const termToUse = document.getElementById('term').value || window.currentTerm || 'Term 1';

    if (category && category.toLowerCase() !== 'normal') {
        const catTuition = window.categoryTuition[category.toLowerCase().trim()];
        if (catTuition) {
            // Advanced fuzzy lookup for term
            const normTerm = normalizeTermName(termToUse);
            const matchedKey = Object.keys(catTuition).find(k => normalizeTermName(k) === normTerm);
            if (matchedKey !== undefined && catTuition[matchedKey] !== undefined) {
                tuitionData = { [termToUse]: catTuition[matchedKey] };
            }
        }
    }

    // 2. Fallback to Class Tuition Data if no category fee found
    if (!tuitionData) {
        tuitionData = window.classTermTuition[classId] || window.classTermTuition[parseInt(classId)];
    }

    // 2. Fallback: Try to find by exact Name (normalized for casing/spaces)
    if (!tuitionData && className) {
        const normalizedName = className.trim().toLowerCase();
        tuitionData = window.classNameTuition[normalizedName];
        
        // 3. Fallback: Try fuzzy matching (no dots)
        if (!tuitionData) {
            const fuzzyName = normalizedName.replace(/\./g, '');
            tuitionData = window.classNameTuition[fuzzyName];
        }
    }

    if (tuitionData) {
        const availableTerms = Object.keys(tuitionData);
        let termToSelect = '';

        // Prefer the current system term if it's available for this class
        if (availableTerms.includes(window.currentTerm)) {
            termToSelect = window.currentTerm;
        } else if (availableTerms.length > 0) {
            // Otherwise pick the first available one (e.g. Term 1 or Annual)
            termToSelect = availableTerms[0];
        }

        if (termToSelect) {
            termInput.value = termToSelect;
        }

        // Now update tuition based on the auto-filled term
        const finalTerm = termInput.value;
        if (tuitionData[finalTerm] !== undefined) {
            tuitionInput.value = parseFloat(tuitionData[finalTerm]).toFixed(2);
        } else {
            tuitionInput.value = '';
        }
    } else {
        console.warn('No tuition data found for:', className);
        termInput.value = '';
        tuitionInput.value = '';
    }
    
    // Update preview if name is present
    const name = document.getElementById('studentNameInput').value;
    if (name) {
        const boarding = document.getElementById('dayBoarding').value;
        const gender = document.getElementById('gender').value;
        updatePreview(name, className, termInput.value, boarding, gender, category);
    }
}

// Inline override of handleEditClassChange to guarantee cache bypass online
function handleEditClassChange() {
    const classId = document.getElementById('editPaymentClass').value;
    const termInput = document.getElementById('editPaymentTerm');
    const tuitionInput = document.getElementById('editPaymentExpected');

    if (!classId) return;

    const category = document.getElementById('editPaymentCategory').value;
    const termToUse = termInput.value || window.currentTerm || 'Term 1';
    
    // 1. Try category fee first
    let tuitionData = null;
    if (category && category.toLowerCase() !== 'normal') {
        const catTuition = window.categoryTuition[category.toLowerCase().trim()];
        if (catTuition) {
            // Advanced fuzzy lookup for term
            const normTerm = normalizeTermName(termToUse);
            const matchedKey = Object.keys(catTuition).find(k => normalizeTermName(k) === normTerm);
            if (matchedKey !== undefined && catTuition[matchedKey] !== undefined) {
                tuitionData = { [termToUse]: catTuition[matchedKey] };
            }
        }
    }

    // 2. Fallback to Class Tuition Data
    if (!tuitionData) {
        tuitionData = window.classTermTuition[classId] || window.classTermTuition[parseInt(classId)];
    }
    
    if (tuitionData) {
        const availableTerms = Object.keys(tuitionData);
        let currentVal = termInput.value;

        // If the current term typed is not in this new class, auto-pick one
        if (!availableTerms.includes(currentVal)) {
            if (availableTerms.includes(window.currentTerm)) {
                termInput.value = window.currentTerm;
            } else if (availableTerms.length > 0) {
                termInput.value = availableTerms[0];
            }
        }

        const finalTerm = termInput.value;
        if (tuitionData[finalTerm] !== undefined) {
            tuitionInput.value = parseFloat(tuitionData[finalTerm]).toFixed(2);
        } else {
            tuitionInput.value = '';
        }
    } else {
        console.warn('No tuition data found for this class in edit modal');
        tuitionInput.value = '';
    }

    calculateEditBalance();
}

// Re-bind the category event listener online
document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('category');
    if (catSelect) {
        catSelect.removeEventListener('change', handleClassChange);
        catSelect.addEventListener('change', handleClassChange);
    }
    const editCatSelect = document.getElementById('editPaymentCategory');
    if (editCatSelect) {
        editCatSelect.removeEventListener('change', handleEditClassChange);
        editCatSelect.addEventListener('change', handleEditClassChange);
    }
});
</script>

<link rel="stylesheet" href="../../assets/css/studentPayments.css">
<link rel="stylesheet" href="../../assets/css/studentPreviewCard.css">
<script src="../../assets/js/studentPayments.js?v=<?= time() ?>"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
