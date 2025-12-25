<?php
$title = "Pending Requests";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

// Only admin can access this page
requireRole(['admin']);

// Handle approval for admitted students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_student'])) {
    $student_id = intval($_POST['student_id']);
    $stmt = $mysqli->prepare("UPDATE admit_students SET status = 'approved' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=admitted_students&approved=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle rejection for admitted students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_student'])) {
    $student_id = intval($_POST['student_id']);
    $stmt = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=admitted_students&rejected=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle approvals and rejections for student payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $stmt = $mysqli->prepare("UPDATE student_payments SET status_approved = 'approved' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=student_payments&subtab=payments&approved=1");
            exit();
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $stmt = $mysqli->prepare("DELETE FROM student_payments WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=student_payments&subtab=payments&rejected=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle approvals and rejections for balance top-ups
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_topup'])) {
    $topup_id = intval($_POST['topup_id']);
    
    // Get topup details to retrieve previous_status
    $getStmt = $mysqli->prepare("SELECT payment_id, previous_status FROM student_payment_topups WHERE id = ?");
    $getStmt->bind_param("i", $topup_id);
    $getStmt->execute();
    $topupRow = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();
    
    if ($topupRow) {
        // Update topup status to approved
        $stmt = $mysqli->prepare("UPDATE student_payment_topups SET status_approved = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $topup_id);
        $stmt->execute();
        $stmt->close();
        
        // Update payment status back to previous status (usually 'approved')
        $updatePaymentStmt = $mysqli->prepare("UPDATE student_payments SET status_approved = ? WHERE id = ?");
        $updatePaymentStmt->bind_param("si", $topupRow['previous_status'], $topupRow['payment_id']);
        $updatePaymentStmt->execute();
        $updatePaymentStmt->close();
    }
    
    header("Location: pendingrequest.php?tab=student_payments&subtab=topups&approved=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_topup'])) {
    $topup_id = intval($_POST['topup_id']);
    
    // Get topup details
    $getStmt = $mysqli->prepare("SELECT payment_id, original_balance, topup_amount, previous_status FROM student_payment_topups WHERE id = ?");
    $getStmt->bind_param("i", $topup_id);
    $getStmt->execute();
    $topupRow = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();
    
    if ($topupRow) {
        // Calculate original amount_paid by subtracting the topup_amount from current amount_paid
        $getPaymentStmt = $mysqli->prepare("SELECT amount_paid FROM student_payments WHERE id = ?");
        $getPaymentStmt->bind_param("i", $topupRow['payment_id']);
        $getPaymentStmt->execute();
        $paymentRow = $getPaymentStmt->get_result()->fetch_assoc();
        $getPaymentStmt->close();
        
        $original_amount_paid = $paymentRow['amount_paid'] - $topupRow['topup_amount'];
        
        // Revert BOTH balance and amount_paid, and restore previous status
        $revertStmt = $mysqli->prepare("UPDATE student_payments SET balance = ?, amount_paid = ?, status_approved = ? WHERE id = ?");
        $revertStmt->bind_param("ddsi", $topupRow['original_balance'], $original_amount_paid, $topupRow['previous_status'], $topupRow['payment_id']);
        $revertStmt->execute();
        $revertStmt->close();
        
        // Delete the topup record
        $delStmt = $mysqli->prepare("DELETE FROM student_payment_topups WHERE id = ?");
        $delStmt->bind_param("i", $topup_id);
        $delStmt->execute();
        $delStmt->close();
    }
    
    header("Location: pendingrequest.php?tab=student_payments&subtab=topups&rejected=1");
    exit();
}

// Handle approval for expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_expense'])) {
    $expense_id = intval($_POST['expense_id']);
    $stmt = $mysqli->prepare("UPDATE expenses SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $stmt->close();
    header("Location: pendingrequest.php?tab=expenses&success=1");
    exit();
}

// Handle rejection for expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_expense'])) {
    $expense_id = intval($_POST['expense_id']);
    $stmt = $mysqli->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $stmt->close();
    header("Location: pendingrequest.php?tab=expenses&success=1");
    exit();
}

// Handle approval/rejection for payroll/salaries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payroll'])) {
    $payroll_id = intval($_POST['payroll_id']);
    
    // Start transaction to ensure both updates succeed
    $mysqli->begin_transaction();
    
    try {
        // Update payroll status to approved
        $stmt = $mysqli->prepare("UPDATE payroll SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $payroll_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating payroll: " . $stmt->error);
        }
        $stmt->close();
        
        // Get payroll details to find matching expense
        $payrollStmt = $mysqli->prepare("SELECT name, salary FROM payroll WHERE id = ?");
        $payrollStmt->bind_param("i", $payroll_id);
        $payrollStmt->execute();
        $payrollResult = $payrollStmt->get_result();
        $payroll = $payrollResult->fetch_assoc();
        $payrollStmt->close();
        
        if ($payroll) {
            // Update corresponding expense record to approved
            $expenseStmt = $mysqli->prepare("UPDATE expenses SET status = 'approved' WHERE category = 'Salaries' AND item = ? AND amount = ?");
            $expenseStmt->bind_param("sd", $payroll['name'], $payroll['salary']);
            if (!$expenseStmt->execute()) {
                throw new Exception("Error updating expense: " . $expenseStmt->error);
            }
            $expenseStmt->close();
        }
        
        // Commit transaction
        $mysqli->commit();
        
        header("Location: pendingrequest.php?tab=salaries&success=1");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $mysqli->rollback();
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payroll'])) {
    $payroll_id = intval($_POST['payroll_id']);
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Get payroll details before deletion
        $payrollStmt = $mysqli->prepare("SELECT name, salary FROM payroll WHERE id = ?");
        $payrollStmt->bind_param("i", $payroll_id);
        $payrollStmt->execute();
        $payrollResult = $payrollStmt->get_result();
        $payroll = $payrollResult->fetch_assoc();
        $payrollStmt->close();
        
        // Delete payroll
        $stmt = $mysqli->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->bind_param("i", $payroll_id);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting payroll: " . $stmt->error);
        }
        $stmt->close();
        
        // Delete corresponding expense record
        if ($payroll) {
            $expenseStmt = $mysqli->prepare("DELETE FROM expenses WHERE category = 'Salaries' AND item = ? AND amount = ?");
            $expenseStmt->bind_param("sd", $payroll['name'], $payroll['salary']);
            if (!$expenseStmt->execute()) {
                throw new Exception("Error deleting expense: " . $expenseStmt->error);
            }
            $expenseStmt->close();
        }
        
        // Commit transaction
        $mysqli->commit();
        
        header("Location: pendingrequest.php?tab=salaries&success=1");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $mysqli->rollback();
        $error = $e->getMessage();
    }
}

// Include layout AFTER all operations
require_once __DIR__ . '/../helper/layout.php';

// Get active tab and subtab
$active_tab = $_GET['tab'] ?? 'admitted_students';
$active_subtab = $_GET['subtab'] ?? 'payments';

// Get unapproved admitted students
$admittedQuery = "SELECT 
    admit_students.id,
    admit_students.admission_no,
    admit_students.first_name,
    admit_students.last_name,
    admit_students.gender,
    c.class_name,
    admit_students.day_boarding,
    admit_students.admission_fee,
    admit_students.uniform_fee,
    admit_students.parent_contact,
    admit_students.parent_email,
    admit_students.status,
    admit_students.created_at
FROM admit_students
LEFT JOIN classes c ON admit_students.class_id = c.id
WHERE admit_students.status = 'unapproved'
ORDER BY admit_students.created_at DESC";

$admittedResult = $mysqli->query($admittedQuery);
$admitted_students = $admittedResult->fetch_all(MYSQLI_ASSOC);

// Get unapproved student payments (excluding those with pending top-ups)
$paymentsQuery = "SELECT 
    sp.id, sp.admission_no, sp.full_name, sp.day_boarding, sp.gender, sp.class_name, sp.term,
    sp.expected_tuition, sp.amount_paid, sp.balance, sp.admission_fee, sp.uniform_fee,
    sp.parent_contact, sp.parent_email, sp.payment_date, sp.created_at, sp.status_approved
FROM student_payments sp
WHERE sp.status_approved = 'unapproved' 
AND sp.id NOT IN (
    SELECT DISTINCT payment_id FROM student_payment_topups 
    WHERE status_approved = 'unapproved'
)
ORDER BY sp.created_at DESC";

$paymentsResult = $mysqli->query($paymentsQuery);
$unapproved_payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);

// Get pending balance top-ups
$topupsQuery = "SELECT 
    id, payment_id, student_id, admission_no, full_name, 
    original_balance, topup_amount, new_balance, status_approved, created_at
FROM student_payment_topups
WHERE status_approved = 'unapproved'
ORDER BY created_at DESC";

$topupsResult = $mysqli->query($topupsQuery);
$pending_topups = $topupsResult->fetch_all(MYSQLI_ASSOC);

// Get unapproved expenses
$expensesQuery = "SELECT 
    id, category, item, amount, date, recorded_by, status, created_at
FROM expenses
WHERE status = 'unapproved'
ORDER BY created_at DESC";

$expensesResult = $mysqli->query($expensesQuery);
$unapproved_expenses = $expensesResult->fetch_all(MYSQLI_ASSOC);

// Get unapproved payroll/salaries
$salariesQuery = "SELECT 
    id, name, department, salary, date, created_at
FROM payroll
WHERE status = 'unapproved'
ORDER BY created_at DESC";

$salariesResult = $mysqli->query($salariesQuery);
$unapproved_salaries = $salariesResult->fetch_all(MYSQLI_ASSOC);

$message = '';
if (isset($_GET['approved']) && $_GET['approved'] == 1) {
    $message = "Record approved successfully!";
}
if (isset($_GET['rejected']) && $_GET['rejected'] == 1) {
    $message = "Record rejected successfully!";
}
?>

<!-- Message Display -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Pending Request Tabs -->
<div class="pending-tabs">
    <a href="?tab=admitted_students" class="pending-tab-btn <?= $active_tab === 'admitted_students' ? 'active' : '' ?>">
        <i class="bi bi-person-check"></i> Admitted Students
    </a>
    <a href="?tab=student_payments" class="pending-tab-btn <?= $active_tab === 'student_payments' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i> Student Payments
    </a>
    <a href="?tab=expenses" class="pending-tab-btn <?= $active_tab === 'expenses' ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> Expenses
    </a>
    <a href="?tab=salaries" class="pending-tab-btn <?= $active_tab === 'salaries' ? 'active' : '' ?>">
        <i class="bi bi-cash-stack"></i> Salaries
    </a>
</div>

<!-- Tab 1: Admitted Students -->
<div class="tab-content <?= $active_tab === 'admitted_students' ? 'active' : '' ?>" id="admitted_students-tab">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Admitted Students</h5>
        </div>
        <div class="card-body">
            <?php if (empty($admitted_students)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No pending student admissions.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Adm No</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Class</th>
                                <th>Type</th>
                                <th>Fees</th>
                                <th>Parent Contact</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admitted_students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['admission_no']) ?></td>
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['gender']) ?></td>
                                    <td><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($student['day_boarding']) ?></span></td>
                                    <td><?= number_format($student['admission_fee'] + $student['uniform_fee'], 2) ?></td>
                                    <td><?= htmlspecialchars($student['parent_contact']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($student['created_at'])) ?></td>
                                    <td>
                                        <div class="action-button-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" name="approve_student" class="btn-approve" onclick="return confirm('Approve this student?')">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" name="reject_student" class="btn-reject" onclick="return confirm('Reject this student?')">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab 2: Student Payments (with sub-tabs) -->
<div class="tab-content <?= $active_tab === 'student_payments' ? 'active' : '' ?>" id="student_payments-tab">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Student Payments</h5>
        </div>
        <div class="card-body">
            <!-- Sub-tabs -->
            <div class="sub-tabs">
                <button type="button" class="sub-tab-btn <?= $active_subtab === 'payments' ? 'active' : '' ?>" onclick="switchSubTab('payments')">
                    <i class="bi bi-credit-card"></i> Payments
                </button>
                <button type="button" class="sub-tab-btn <?= $active_subtab === 'topups' ? 'active' : '' ?>" onclick="switchSubTab('topups')">
                    <i class="bi bi-arrow-up-circle"></i> Balance Top-ups
                </button>
            </div>

            <!-- Sub-tab 1: Payments -->
            <div class="sub-tab-content <?= $active_subtab === 'payments' ? 'active' : '' ?>" id="payments-content">
                <?php if (empty($unapproved_payments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No pending student payment approvals.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Adm No</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Term</th>
                                    <th>Type</th>
                                    <th>Gender</th>
                                    <th>Expected Tuition</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Parent Contact</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unapproved_payments as $payment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payment['admission_no']) ?></td>
                                        <td><?= htmlspecialchars($payment['full_name']) ?></td>
                                        <td><?= htmlspecialchars($payment['class_name']) ?></td>
                                        <td><?= htmlspecialchars($payment['term']) ?></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($payment['day_boarding']) ?></span></td>
                                        <td><?= htmlspecialchars($payment['gender']) ?></td>
                                        <td><?= number_format($payment['expected_tuition'], 2) ?></td>
                                        <td><?= number_format($payment['amount_paid'], 2) ?></td>
                                        <td><?= number_format($payment['balance'], 2) ?></td>
                                        <td><?= htmlspecialchars($payment['parent_contact']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                                        <td>
                                            <div class="action-button-group">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                    <button type="submit" name="approve_payment" class="btn-approve" onclick="return confirm('Approve this payment?')">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                    <button type="submit" name="reject_payment" class="btn-reject" onclick="return confirm('Reject this payment?')">
                                                        <i class="bi bi-x-circle"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sub-tab 2: Balance Top-ups -->
            <div class="sub-tab-content <?= $active_subtab === 'topups' ? 'active' : '' ?>" id="topups-content">
                <?php if (empty($pending_topups)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No pending balance top-ups.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Adm No</th>
                                    <th>Name</th>
                                    <th>Original Balance</th>
                                    <th>Top-up Amount</th>
                                    <th>New Balance</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_topups as $topup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($topup['admission_no']) ?></td>
                                        <td><?= htmlspecialchars($topup['full_name']) ?></td>
                                        <td><?= number_format($topup['original_balance'], 2) ?></td>
                                        <td><?= number_format($topup['topup_amount'], 2) ?></td>
                                        <td><?= number_format($topup['new_balance'], 2) ?></td>
                                        <td><?= date('Y-m-d', strtotime($topup['created_at'])) ?></td>
                                        <td>
                                            <div class="action-button-group">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="topup_id" value="<?= $topup['id'] ?>">
                                                    <button type="submit" name="approve_topup" class="btn-approve" onclick="return confirm('Approve this top-up?')">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="topup_id" value="<?= $topup['id'] ?>">
                                                    <button type="submit" name="reject_topup" class="btn-reject" onclick="return confirm('Reject this top-up? Balance will be reverted.')">
                                                        <i class="bi bi-x-circle"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tab 3: Expenses -->
<div class="tab-content <?= $active_tab === 'expenses' ? 'active' : '' ?>" id="expenses-tab">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Expenses</h5>
        </div>
        <div class="card-body">
            <?php if (empty($unapproved_expenses)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No pending expenses.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Item</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Recorded By</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unapproved_expenses as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['category']) ?></td>
                                    <td><?= htmlspecialchars($expense['item']) ?></td>
                                    <td><?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= date('Y-m-d', strtotime($expense['date'])) ?></td>
                                    <td>
                                        <?php
                                        $userQuery = "SELECT name FROM users WHERE id = ?";
                                        $userStmt = $mysqli->prepare($userQuery);
                                        $userStmt->bind_param("i", $expense['recorded_by']);
                                        $userStmt->execute();
                                        $userName = $userStmt->get_result()->fetch_assoc()['name'] ?? 'N/A';
                                        $userStmt->close();
                                        echo htmlspecialchars($userName);
                                        ?>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($expense['created_at'])) ?></td>
                                    <td>
                                        <div class="action-button-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                                <button type="submit" name="approve_expense" class="btn-approve" onclick="return confirm('Approve this expense?')">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                                <button type="submit" name="reject_expense" class="btn-reject" onclick="return confirm('Reject this expense?')">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab 4: Salaries -->
<div class="tab-content <?= $active_tab === 'salaries' ? 'active' : '' ?>" id="salaries-tab">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Unapproved Salaries (Payroll)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($unapproved_salaries)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No unapproved salaries pending.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Salary</th>
                                <th>Date</th>
                                <th>Recorded Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unapproved_salaries as $salary): ?>
                                <tr>
                                    <td><?= htmlspecialchars($salary['name']) ?></td>
                                    <td><?= htmlspecialchars($salary['department']) ?></td>
                                    <td><?= number_format($salary['salary'], 2) ?></td>
                                    <td><?= date('Y-m-d', strtotime($salary['date'])) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($salary['created_at'])) ?></td>
                                    <td>
                                        <div class="action-button-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="payroll_id" value="<?= $salary['id'] ?>">
                                                <button type="submit" name="approve_payroll" class="btn-approve" onclick="return confirm('Approve this salary?')">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="payroll_id" value="<?= $salary['id'] ?>">
                                                <button type="submit" name="reject_payroll" class="btn-reject" onclick="return confirm('Reject this salary?')">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/pendingrequest.css">
<script src="../../assets/js/pendingrequest.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
