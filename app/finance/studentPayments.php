<?php
$title = "Student Payments";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Handle payment recording
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $student_id = intval($_POST['student_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = trim($_POST['payment_date']);
    $term = trim($_POST['term']);
    
    if (!$student_id || !$amount_paid || !$payment_date || !$term) {
        $error = "All fields are required";
    } elseif ($amount_paid <= 0) {
        $error = "Amount must be greater than zero";
    } else {
        // Get student info
        $studentStmt = $mysqli->prepare("SELECT admission_no, first_name, last_name, gender, class_id, day_boarding, parent_contact, parent_email FROM admit_students WHERE id = ?");
        $studentStmt->bind_param("i", $student_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        
        if ($studentResult->num_rows === 0) {
            $error = "Student not found";
        } else {
            $student = $studentResult->fetch_assoc();
            $studentStmt->close();
            
            // Get class and tuition info
            $classStmt = $mysqli->prepare("SELECT class_name FROM classes WHERE id = ?");
            $classStmt->bind_param("i", $student['class_id']);
            $classStmt->execute();
            $classResult = $classStmt->get_result();
            $classRow = $classResult->fetch_assoc();
            $classStmt->close();
            
            $user_id = $_SESSION['user_id'];
            
            // Insert payment record
            $insertStmt = $mysqli->prepare("INSERT INTO student_payments (student_id, admission_no, full_name, day_boarding, gender, class_id, class_name, term, expected_tuition, amount_paid, balance, admission_fee, uniform_fee, parent_contact, parent_email, payment_date, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($insertStmt) {
                $full_name = $student['first_name'] . ' ' . $student['last_name'];
                $expected_tuition = floatval($_POST['expected_tuition']);
                $balance = $expected_tuition - $amount_paid;
                $admission_fee = floatval($_POST['admission_fee']);
                $uniform_fee = floatval($_POST['uniform_fee']);
                $class_name = $classRow['class_name'];
                
                // Fixed type string: 17 variables = issssissdddddssi
                // i=student_id, s=admission_no, s=full_name, s=day_boarding, s=gender, i=class_id, s=class_name, s=term, d=expected_tuition, d=amount_paid, d=balance, d=admission_fee, d=uniform_fee, s=parent_contact, s=parent_email, s=payment_date, i=recorded_by
                $insertStmt->bind_param("issssissdddddsssi", 
                    $student_id, $student['admission_no'], $full_name, $student['day_boarding'], 
                    $student['gender'], $student['class_id'], $class_name, $term, $expected_tuition, 
                    $amount_paid, $balance, $admission_fee, $uniform_fee, 
                    $student['parent_contact'], $student['parent_email'], $payment_date, $user_id);
                
                if ($insertStmt->execute()) {
                    header("Location: studentPayments.php?success=1");
                    exit();
                } else {
                    $error = "Error recording payment: " . $insertStmt->error;
                }
                $insertStmt->close();
            }
        }
    }
}

// Handle additional payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $additional_amount = floatval($_POST['additional_amount']);
    
    if (!$payment_id || !$additional_amount || $additional_amount <= 0) {
        $error = "Invalid payment information";
    } else {
        // Get current payment record
        $paymentStmt = $mysqli->prepare("SELECT balance, amount_paid, student_id, admission_no, full_name, status_approved FROM student_payments WHERE id = ?");
        $paymentStmt->bind_param("i", $payment_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $paymentRow = $paymentResult->fetch_assoc();
        $paymentStmt->close();
        
        if (!$paymentRow) {
            $error = "Payment record not found";
        } else if ($additional_amount > $paymentRow['balance']) {
            $error = "Payment amount exceeds remaining balance";
        } else {
            $original_balance = $paymentRow['balance'];
            $original_amount_paid = $paymentRow['amount_paid'];
            $previous_status = $paymentRow['status_approved'];
            $new_balance = $original_balance - $additional_amount;
            $new_amount_paid = $original_amount_paid + $additional_amount;
            
            // Update payment record - change status to unapproved for top-up approval
            $new_status = 'unapproved';
            
            $updateStmt = $mysqli->prepare("UPDATE student_payments SET amount_paid = ?, balance = ?, status_approved = ? WHERE id = ?");
            $updateStmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $payment_id);
            
            if ($updateStmt->execute()) {
                // Log the balance top-up with previous status
                // Types: i=payment_id, i=student_id, s=admission_no, s=full_name, d=original_balance, d=topup_amount, d=new_balance, s=previous_status
                $logStmt = $mysqli->prepare("INSERT INTO student_payment_topups (payment_id, student_id, admission_no, full_name, original_balance, topup_amount, new_balance, status_approved, previous_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'unapproved', ?)");
                $logStmt->bind_param("iissddds", $payment_id, $paymentRow['student_id'], $paymentRow['admission_no'], $paymentRow['full_name'], $original_balance, $additional_amount, $new_balance, $previous_status);
                $logStmt->execute();
                $logStmt->close();
                
                header("Location: studentPayments.php?success=1");
                exit();
            } else {
                $error = "Error updating payment: " . $updateStmt->error;
            }
            $updateStmt->close();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Payment updated successfully!";
}

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query
$filterWhere = "1=1";
$search_filter = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$term_filter = $_GET['term'] ?? '';
$approval_filter = $_GET['approval'] ?? '';
$pay_status_filter = $_GET['pay_status'] ?? '';

if ($search_filter) {
    $searchTerm = '%' . $mysqli->real_escape_string($search_filter) . '%';
    $filterWhere .= " AND (full_name LIKE '$searchTerm')";
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
if ($approval_filter) {
    $filterWhere .= " AND status_approved = '" . $mysqli->real_escape_string($approval_filter) . "'";
}
if ($pay_status_filter) {
    if ($pay_status_filter === 'paid') {
        $filterWhere .= " AND balance = 0";
    } elseif ($pay_status_filter === 'unpaid') {
        $filterWhere .= " AND balance > 0";
    }
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
    id, admission_no, full_name, day_boarding, gender, class_name, term,
    expected_tuition, amount_paid, balance, admission_fee, uniform_fee,
    parent_contact, parent_email, payment_date, created_at, status_approved
FROM student_payments
WHERE $filterWhere
ORDER BY created_at DESC
LIMIT $offset, $records_per_page";

$paymentsResult = $mysqli->query($paymentsQuery);
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM student_payments ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
$terms = $termsResult->fetch_all(MYSQLI_ASSOC);

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

// Get approved students for dropdown
$approvedStudentsQuery = "SELECT 
    id, admission_no, first_name, last_name, gender, class_id, day_boarding, 
    admission_fee, uniform_fee, parent_contact, parent_email
FROM admit_students
WHERE status = 'approved'
ORDER BY first_name ASC";

$approvedStudentsResult = $mysqli->query($approvedStudentsQuery);

// Check if query executed successfully
if (!$approvedStudentsResult) {
    die("Database error: " . $mysqli->error);
}

$approved_students = $approvedStudentsResult->fetch_all(MYSQLI_ASSOC);

// Debug: Check if students were found
if (empty($approved_students)) {
    // If no approved students, fetch ALL students to help with debugging
    $debugQuery = "SELECT COUNT(*) as total, 
                          SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count
                   FROM admit_students";
    $debugResult = $mysqli->query($debugQuery);
    $debugRow = $debugResult->fetch_assoc();
    // You can log this or display a message if needed
}
?>

<!-- Toggle Button for Record Payment Form -->
<div class="mb-3">
    <button type="button" class="btn-toggle-form" onclick="togglePaymentForm()">
        <i class="bi bi-chevron-right"></i> Record Student Payment
    </button>
</div>

<!-- Record Student Payment Form (Collapsible) -->
<div class="card shadow-sm border-0 mb-4" id="paymentFormCard" style="display: none;">
    <div class="card-header form-header text-white">
        <h5 class="mb-0">Record Student Payment</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="paymentForm" class="row g-3">
            <!-- Student Selection -->
            <div class="col-md-6">
                <label class="form-label">Select Student</label>
                <select name="student_id" id="studentSelect" class="form-control" required onchange="populateStudentData()">
                    <option value="">-- Select Student --</option>
                    <?php if (!empty($approved_students)): ?>
                        <?php foreach ($approved_students as $student): ?>
                            <option value="<?= $student['id'] ?>" 
                                data-admission="<?= htmlspecialchars($student['admission_no']) ?>"
                                data-first="<?= htmlspecialchars($student['first_name']) ?>"
                                data-last="<?= htmlspecialchars($student['last_name']) ?>"
                                data-gender="<?= htmlspecialchars($student['gender']) ?>"
                                data-class="<?= $student['class_id'] ?>"
                                data-boarding="<?= htmlspecialchars($student['day_boarding']) ?>"
                                data-admission-fee="<?= $student['admission_fee'] ?>"
                                data-uniform-fee="<?= $student['uniform_fee'] ?>"
                                data-contact="<?= htmlspecialchars($student['parent_contact']) ?>"
                                data-email="<?= htmlspecialchars($student['parent_email']) ?>">
                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['admission_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No approved students available</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Auto-filled Student Information -->
            <div class="col-md-6">
                <label class="form-label">Student Name</label>
                <input type="text" id="fullName" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Sex</label>
                <input type="text" id="gender" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <input type="text" id="className" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <input type="text" name="term" id="term" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Day/Boarding</label>
                <input type="text" id="dayBoarding" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Expected Tuition</label>
                <input type="number" name="expected_tuition" id="expectedTuition" class="form-control readonly-field" step="0.01" readonly>
            </div>
            
            <!-- Payment Information -->
            <div class="col-md-3">
                <label class="form-label">Amount Paid</label>
                <input type="number" name="amount_paid" id="amountPaid" class="form-control" step="0.01" min="0" placeholder="0.00" required>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Admission Fee</label>
                <input type="number" name="admission_fee" id="admissionFee" class="form-control readonly-field" step="0.01" readonly>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Uniform Fee</label>
                <input type="number" name="uniform_fee" id="uniformFee" class="form-control readonly-field" step="0.01" readonly>
            </div>
            
            <!-- Parent Information -->
            <div class="col-md-6">
                <label class="form-label">Parent Contact</label>
                <input type="text" id="parentContact" class="form-control readonly-field" readonly>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Parent Email</label>
                <input type="email" id="parentEmail" class="form-control readonly-field" readonly>
            </div>
            
            <!-- Payment Date -->
            <div class="col-md-3">
                <label class="form-label">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" required>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12">
                <button type="submit" name="record_payment" class="btn btn-form-submit">
                    <i class="bi bi-check-circle"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

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
                    <label>Approval</label>
                    <select name="approval" class="form-control">
                        <option value="">All Status</option>
                        <option value="approved" <?= $approval_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="unapproved" <?= $approval_filter === 'unapproved' ? 'selected' : '' ?>>Unapproved</option>
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
        <?php if (empty($payments)): ?>
            <div class="alert alert-info">No payment records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Term</th>
                            <th>Type</th>
                            <th>Sex</th>
                            <th>Expected Tuition</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Admission Fee</th>
                            <th>Uniform Fee</th>
                            <th>Parent Contact</th>
                            <th>Parent Email</th>
                            <th>Payment Date</th>
                            <th>Pay Status</th>
                            <th>Approval Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['admission_no']) ?></td>
                                <td><?= htmlspecialchars($payment['full_name']) ?></td>
                                <td><?= htmlspecialchars($payment['class_name']) ?></td>
                                <td><?= htmlspecialchars($payment['term']) ?></td>
                                <td><?= htmlspecialchars($payment['day_boarding']) ?></td>
                                <td><?= htmlspecialchars($payment['gender']) ?></td>
                                <td><?= number_format($payment['expected_tuition'], 2) ?></td>
                                <td><?= number_format($payment['amount_paid'], 2) ?></td>
                                <td><?= number_format($payment['balance'], 2) ?></td>
                                <td><?= number_format($payment['admission_fee'], 2) ?></td>
                                <td><?= number_format($payment['uniform_fee'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['parent_contact']) ?></td>
                                <td><?= htmlspecialchars($payment['parent_email']) ?></td>
                                <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                                <td>
                                    <?php if ($payment['balance'] == 0): ?>
                                        <span class="status-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-incomplete">Incomplete</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['status_approved'] === 'approved'): ?>
                                        <span class="status-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="status-unapproved">Unapproved</span>
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
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="table-totals">
                            <td colspan="6" class="text-end fw-bold">TOTALS:</td>
                            <td class="totals-expected-tuition"><?= number_format($totals['total_tuition'] ?? 0, 2) ?></td>
                            <td class="totals-amount-paid"><?= number_format($totals['total_paid'] ?? 0, 2) ?></td>
                            <td class="totals-balance"><?= number_format($totals['total_balance'] ?? 0, 2) ?></td>
                            <td class="totals-admission-fee"><?= number_format($totals['total_admission'] ?? 0, 2) ?></td>
                            <td class="totals-uniform-fee"><?= number_format($totals['total_uniform'] ?? 0, 2) ?></td>
                            <td colspan="6"></td>
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
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . $approval_filter : '') . ($pay_status_filter ? '&pay_status=' . $pay_status_filter : ''); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . $approval_filter : '') . ($pay_status_filter ? '&pay_status=' . $pay_status_filter : ''); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . $approval_filter : '') . ($pay_status_filter ? '&pay_status=' . $pay_status_filter : ''); ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . $approval_filter : '') . ($pay_status_filter ? '&pay_status=' . $pay_status_filter : ''); ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . ($term_filter ? '&term=' . urlencode($term_filter) : '') . ($approval_filter ? '&approval=' . $approval_filter : '') . ($pay_status_filter ? '&pay_status=' . $pay_status_filter : ''); ?>" aria-label="Next">
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

<link rel="stylesheet" href="../../assets/css/studentPayments.css">
<script src="../../assets/js/studentPayments.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
