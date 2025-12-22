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
        $paymentStmt = $mysqli->prepare("SELECT balance FROM student_payments WHERE id = ?");
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
            // Update payment record
            $new_balance = $paymentRow['balance'] - $additional_amount;
            $updateStmt = $mysqli->prepare("UPDATE student_payments SET amount_paid = amount_paid + ?, balance = ? WHERE id = ?");
            $updateStmt->bind_param("ddi", $additional_amount, $new_balance, $payment_id);
            
            if ($updateStmt->execute()) {
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

// Get approved students for dropdown
$approvedStudentsQuery = "SELECT 
    id, admission_no, first_name, last_name, gender, class_id, day_boarding, 
    admission_fee, uniform_fee, parent_contact, parent_email
FROM admit_students
WHERE status = 'approved'
ORDER BY first_name ASC";

$approvedStudentsResult = $mysqli->query($approvedStudentsQuery);
$approved_students = $approvedStudentsResult->fetch_all(MYSQLI_ASSOC);

// Get all payments recorded
$paymentsQuery = "SELECT 
    id, admission_no, full_name, day_boarding, gender, class_name, term,
    expected_tuition, amount_paid, balance, admission_fee, uniform_fee,
    parent_contact, parent_email, payment_date, created_at, status_approved
FROM student_payments
ORDER BY created_at DESC
LIMIT 500";

$paymentsResult = $mysqli->query($paymentsQuery);
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);
?>

<style>
    .form-header {
        background-color: #17a2b8 !important;
    }
    
    .btn-form-submit {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: white;
        font-weight: 600;
        padding: 10px 24px;
    }
    
    .btn-form-submit:hover {
        background-color: #138496;
        border-color: #138496;
        color: white;
    }
    
    .form-section {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table {
        margin: 0;
        font-size: 13px;
    }
    
    .table thead th {
        background-color: #17a2b8;
        color: white;
        font-weight: 600;
        border: none;
        padding: 12px;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }
    
    .table tbody td {
        padding: 10px 12px;
        border-color: #eee;
        vertical-align: middle;
    }
    
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .readonly-field {
        background-color: #e9ecef;
        cursor: not-allowed;
    }
    
    .status-paid {
        background-color: #28a745;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
    }
    
    .status-incomplete {
        background-color: #dc3545;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
    }
    
    .status-approved {
        background-color: #28a745;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
    }
    
    .status-unapproved {
        background-color: #dc3545;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
    }
    
    .btn-pay {
        background-color: #17a2b8;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-pay:hover {
        background-color: #138496;
    }
    
    .btn-pay:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }
</style>

<!-- Record Payment Form -->
<div class="card shadow-sm border-0 mb-4">
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
                    </tbody>
                </table>
            </div>
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

<script>
    function populateStudentData() {
        const select = document.getElementById('studentSelect');
        const option = select.options[select.selectedIndex];
        
        if (option.value === '') {
            // Clear all fields
            document.getElementById('fullName').value = '';
            document.getElementById('gender').value = '';
            document.getElementById('className').value = '';
            document.getElementById('dayBoarding').value = '';
            document.getElementById('term').value = '';
            document.getElementById('expectedTuition').value = '';
            document.getElementById('admissionFee').value = '';
            document.getElementById('uniformFee').value = '';
            document.getElementById('parentContact').value = '';
            document.getElementById('parentEmail').value = '';
            return;
        }
        
        // Populate fields from data attributes
        document.getElementById('fullName').value = option.dataset.first + ' ' + option.dataset.last;
        document.getElementById('gender').value = option.dataset.gender;
        document.getElementById('className').value = option.dataset.class;
        document.getElementById('dayBoarding').value = option.dataset.boarding;
        document.getElementById('admissionFee').value = option.dataset.admissionFee;
        document.getElementById('uniformFee').value = option.dataset.uniformFee;
        document.getElementById('parentContact').value = option.dataset.contact;
        document.getElementById('parentEmail').value = option.dataset.email;
        
        // Get expected tuition and term from server
        fetch(`../api/getStudentTuition.php?class_id=${option.dataset.class}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('expectedTuition').value = data.tuition || 0;
                document.getElementById('term').value = data.term || '';
            })
            .catch(error => console.error('Error:', error));
    }
    
    function setPaymentId(paymentId, balance) {
        document.getElementById('modalPaymentId').value = paymentId;
        document.getElementById('modalBalance').value = balance.toFixed(2);
        document.getElementById('modalAmount').value = '';
        document.getElementById('modalAmount').max = balance;
    }
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
