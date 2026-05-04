<?php
$title = "Student Payments";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

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
    $edit_amount_paid = floatval($_POST['edit_amount_paid']);
    $edit_admission_fee = floatval($_POST['edit_admission_fee']);
    $edit_uniform_fee = floatval($_POST['edit_uniform_fee']);

    if ($edit_id > 0 && $edit_amount_paid >= 0 && $edit_admission_fee >= 0 && $edit_uniform_fee >= 0) {
        // Get current expected_tuition to recalculate balance
        $getStmt = $mysqli->prepare("SELECT expected_tuition FROM student_payments WHERE id = ?");
        $getStmt->bind_param("i", $edit_id);
        $getStmt->execute();
        $currentRow = $getStmt->get_result()->fetch_assoc();
        $getStmt->close();

        if ($currentRow) {
            $new_balance = $currentRow['expected_tuition'] - $edit_amount_paid;
            
            // Set status to approved after correction
            $updateStmt = $mysqli->prepare("UPDATE student_payments SET amount_paid = ?, admission_fee = ?, uniform_fee = ?, balance = ?, status_approved = 'approved' WHERE id = ?");
            $updateStmt->bind_param("ddddi", $edit_amount_paid, $edit_admission_fee, $edit_uniform_fee, $new_balance, $edit_id);

            if ($updateStmt->execute()) {
                $updateStmt->close();
                $mysqli->commit();
                header("Location: studentPayments.php?corrected=1");
                exit();
            }
            $updateStmt->close();
        }
    }
}

// Handle DELETE single payment record (Admin, Bursar, Principal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_single_payment'])) {
    $delete_id = intval($_POST['delete_payment_id']);

    if ($delete_id > 0) {
        $mysqli->begin_transaction();
        try {
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

            $mysqli->commit();
            header("Location: studentPayments.php?deleted=1");
            exit();
        } catch (Throwable $e) {
            $mysqli->rollback();
        }
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
    $day_boarding = trim($_POST['day_boarding'] ?? '');
    $admission_fee = floatval($_POST['admission_fee'] ?? 0);
    $uniform_fee = floatval($_POST['uniform_fee'] ?? 0);
    $expected_tuition = floatval($_POST['expected_tuition'] ?? 0);
    $parent_contact = trim($_POST['parent_contact'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    
    if (!$full_name || !$payment_date || !$term || !$gender || !$class_id || !$day_boarding) {
        $error = "All required fields must be filled (Name, Gender, Class, Day/Boarding, Term, Date)";
    } elseif ($amount_paid < 0) {
        $error = "Amount cannot be negative";
    } elseif ($payment_date > date('Y-m-d')) {
        $error = "Payment date cannot be in the future.";
    } else {
        $mysqli->begin_transaction();
        try {
            // 1. If student_id is 0, this is a new admission
            if ($student_id === 0) {
                // GLOBAL CHECK: Check if student with same name already exists anywhere in the school
                $checkStmt = $mysqli->prepare("SELECT id FROM admit_students WHERE first_name = ?");
                $checkStmt->bind_param("s", $full_name);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    throw new Exception("A student named '$full_name' is already registered in the school. Please select them from the list instead of creating a new admission.");
                }
                $checkStmt->close();

                // Admit student
                $admission_no = generateSerialNumber($mysqli);
                $status = 'approved';
                $user_id = $_SESSION['user_id'];

                $admitStmt = $mysqli->prepare(
                    "INSERT INTO admit_students 
                        (admission_no, first_name, gender, class_id, day_boarding, 
                         admission_fee, uniform_fee, expected_tuition, parent_contact, 
                         parent_email, status, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                
                if (!$admitStmt) {
                    throw new Exception("Database prepare error: " . $mysqli->error);
                }

                // s,s,s,i,s,d,d,d,s,s,s,i  => "sssisdddsssi"
                $admitStmt->bind_param("sssisdddsssi", 
                    $admission_no, $full_name, $gender, $class_id, $day_boarding,
                    $admission_fee, $uniform_fee, $expected_tuition, $parent_contact,
                    $parent_email, $status, $user_id);
                
                if (!$admitStmt->execute()) {
                    throw new Exception("Error admitting student: " . $admitStmt->error);
                }
                $student_id = $admitStmt->insert_id;
                $admitStmt->close();
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
            
            $insertPay = $mysqli->prepare("INSERT INTO student_payments (student_id, admission_no, full_name, day_boarding, gender, class_id, class_name, term, expected_tuition, amount_paid, balance, admission_fee, uniform_fee, parent_contact, parent_email, payment_date, status_approved, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $insertPay->bind_param("issssissdddddssssi", 
                $student_id, $admission_no, $full_name, $day_boarding, 
                $gender, $class_id, $class_name, $term, $expected_tuition, 
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
$sort_order = $_GET['sort'] ?? 'ASC';

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
    if ($pay_status_filter === 'paid') {
        $filterWhere .= " AND balance <= 0";
    } elseif ($pay_status_filter === 'unpaid') {
        $filterWhere .= " AND balance > 0";
    }
}
if ($class_filter) {
    $filterWhere .= " AND class_id = '" . intval($class_filter) . "'";
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

// Get all payments recorded with filter and pagination
$paymentsQuery = "SELECT 
    id, student_id, admission_no, full_name, day_boarding, gender, class_name, term,
    expected_tuition, amount_paid, balance, admission_fee, uniform_fee,
    parent_contact, payment_date, created_at, status_approved
FROM student_payments
WHERE $filterWhere
ORDER BY payment_date DESC, id DESC
LIMIT $offset, $records_per_page";

$paymentsResult = $mysqli->query($paymentsQuery);
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM student_payments ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
$terms = $termsResult ? $termsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get classes for filter
$classesQuery = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
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
$userRole = $_SESSION['role'] ?? '';
$canRecordPayment = in_array($userRole, ['admin', 'bursar']);

// Get approved students for dropdown - LOAD DIRECTLY
$approved_students = [];
if ($canRecordPayment) {
    $approvedStudentsQuery = "SELECT 
        s.id, s.admission_no, s.first_name, s.gender, s.class_id, 
        s.day_boarding, s.admission_fee, s.uniform_fee, 
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
?>

<div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
    <?php if ($canRecordPayment): ?>
        <button type="button" class="btn-toggle-form" onclick="togglePaymentForm()">
            <i class="bi bi-chevron-right"></i> Record Student Payment
        </button>
        <a href="../../sync_students.php" class="btn btn-outline-info btn-sm rounded-pill px-3" target="_blank" title="Sync students from Admission to Payments">
            <i class="bi bi-arrow-repeat"></i> Sync Missing Students
        </a>
    <?php else: ?>
        <button type="button" class="btn-toggle-form" data-bs-toggle="modal" data-bs-target="#restrictionModal">
            <i class="bi bi-chevron-right"></i> Record Student Payment
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
            <h5 class="mb-0">Record Student Payment</h5>
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
                                    data-class-name="<?= htmlspecialchars($s['class_name'] ?? 'N/A') ?>">
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
                    <select name="class_id" id="classSelect" class="form-control" required onchange="handleClassChange()">
                        <option value="">Select Class</option>
                        <?php foreach ($all_classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
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
                    <label>Pay Status</label>
                    <select name="pay_status" class="form-control">
                        <option value="">All Status</option>
                        <option value="paid" <?= $pay_status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= $pay_status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
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
        <h5 class="mb-3">Payment Records</h5>
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Admin‑only bulk delete button, just above the student payments table -->
            <button type="button"
                    class="btn btn-danger mb-3"
                    data-bs-toggle="modal"
                    data-bs-target="#deletePaymentsByDateModal">
                <i class="bi bi-trash"></i> Delete Payments by Date
            </button>
        <?php endif; ?>
        
        <?php if (empty($payments)): ?>
            <div class="alert alert-info">No payment records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped student-payments-table">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Name</th>
                            <th>F/M</th>
                            <th>Class</th>
                            <th>Day/Boarding</th>
                            <th>Expected Tuition</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Admission Fee</th>
                            <th>Uniform Fee</th>
                            <th>Parent Contact</th>
                            <th>Payment Date</th>
                            <th>Pay Status</th>

                            <th>Actions</th>
                            <th>Invoice/Receipt</th>
                            <th>Corrections</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['admission_no']) ?></td>
                                <td><?= htmlspecialchars($payment['full_name']) ?></td>
                                <td><?= $payment['gender'] === 'Male' ? 'M' : 'F' ?></td>
                                <td><?= htmlspecialchars($payment['class_name']) ?></td>
                                <td><?= htmlspecialchars($payment['day_boarding']) ?></td>
                                <td><?= number_format($payment['expected_tuition'], 2) ?></td>
                                <td><?= number_format($payment['amount_paid'], 2) ?></td>
                                <td><?= number_format($payment['balance'], 2) ?></td>
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
                                                onclick="loadEditPayment(<?= $payment['id'] ?>, <?= $payment['amount_paid'] ?>, <?= $payment['admission_fee'] ?>, <?= $payment['uniform_fee'] ?>, <?= $payment['expected_tuition'] ?>, '<?= htmlspecialchars($payment['full_name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this payment record for <?= htmlspecialchars($payment['full_name'], ENT_QUOTES) ?>? This action cannot be undone.');">
                                            <input type="hidden" name="delete_payment_id" value="<?= $payment['id'] ?>">
                                            <button type="submit" name="delete_single_payment" class="btn btn-sm btn-danger" title="Delete Payment">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
                            <td class="totals-admission"><?= number_format($totals['total_admission'] ?? 0, 2) ?></td>
                            <td class="totals-uniform"><?= number_format($totals['total_uniform'] ?? 0, 2) ?></td>
                            <td colspan="4"></td>
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
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . urlencode($approval_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($pay_status_filter ? '&pay_status=' . urlencode($pay_status_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($show_duplicates ? '&duplicates=' . $show_duplicates : ''); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Pagination Info -->
                <div class="text-center mt-3">
                    <p class="text-muted" style="font-size: 13px;">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> payments
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
                    Only <strong>Admin</strong> and <strong>Bursar</strong> roles have permission to record student payments.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary">Principal</strong>, you can view payment records but cannot create new ones.
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header form-header text-white">
                    <h5 class="modal-title" id="editPaymentModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Payment Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_payment_id" id="editPaymentId">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Student Name</label>
                        <p class="form-control-plaintext" id="editPaymentStudentName" style="font-weight: 600; color: #2c3e50;"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expected Tuition</label>
                        <input type="number" class="form-control" id="editPaymentExpected" readonly style="background-color: #e9ecef;">
                    </div>

                    <div class="mb-3">
                        <label for="editPaymentAmountPaid" class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="editPaymentAmountPaid" name="edit_amount_paid" step="0.01" min="0" required oninput="calculateEditBalance()">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Balance</label>
                        <input type="number" class="form-control" id="editPaymentNewBalance" readonly style="background-color: #e9ecef; font-weight: 700;">
                    </div>

                    <div class="mb-3">
                        <label for="editPaymentAdmissionFee" class="form-label">Admission Fee</label>
                        <input type="number" class="form-control" id="editPaymentAdmissionFee" name="edit_admission_fee" step="0.01" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label for="editPaymentUniformFee" class="form-label">Uniform Fee</label>
                        <input type="number" class="form-control" id="editPaymentUniformFee" name="edit_uniform_fee" step="0.01" min="0" required>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle"></i> After correction, the payment status will be set to <strong>Unapproved</strong> and will need re-approval.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_payment_record" class="btn btn-form-submit">
                        <i class="bi bi-check-circle"></i> Save Correction
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
window.classNames = <?= json_encode($classNames, JSON_FORCE_OBJECT) ?>;
window.currentTerm = <?= json_encode($currentTerm ?: 'Term 1') ?>;
</script>

<link rel="stylesheet" href="../../assets/css/studentPayments.css">
<link rel="stylesheet" href="../../assets/css/studentPreviewCard.css">
<script src="../../assets/js/studentPayments.js?v=9"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
