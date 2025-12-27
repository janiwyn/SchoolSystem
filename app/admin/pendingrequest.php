<?php
$title = "Pending Requests";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']);

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

// Get active tab - default to student_payments instead of admitted_students
$activeTab = $_GET['tab'] ?? 'student_payments';

// Get pending student payments (initial payments)
$pendingPaymentsQuery = "SELECT 
    sp.id,
    sp.admission_no,
    COALESCE(ads.first_name, 'Unknown') as student_name,
    COALESCE(ads.gender, 'N/A') as gender,
    COALESCE(ads.parent_contact, 'N/A') as parent_contact,
    c.class_name,
    sp.term,
    sp.payment_type,
    sp.expected_tuition,
    sp.amount_paid,
    sp.balance,
    sp.payment_date,
    sp.payment_method,
    u.name as recorded_by
FROM student_payments sp
LEFT JOIN admit_students ads ON sp.admission_no = ads.admission_no
LEFT JOIN classes c ON sp.class_id = c.id
LEFT JOIN users u ON sp.recorded_by = u.id
WHERE sp.status_approved = 'unapproved' 
AND sp.id NOT IN (
    SELECT DISTINCT payment_id 
    FROM student_payment_topups 
    WHERE status_approved = 'unapproved'
)
ORDER BY sp.payment_date DESC";

$pendingPaymentsResult = $mysqli->query($pendingPaymentsQuery);
$pendingPayments = $pendingPaymentsResult->fetch_all(MYSQLI_ASSOC);

// Get pending balance top-ups
$pendingTopupsQuery = "SELECT 
    spt.id,
    spt.payment_id,
    sp.admission_no,
    COALESCE(ads.first_name, 'Unknown') as student_name,
    COALESCE(ads.gender, 'N/A') as gender,
    COALESCE(ads.parent_contact, 'N/A') as parent_contact,
    c.class_name,
    sp.term,
    sp.payment_type,
    spt.topup_amount,
    spt.new_balance,
    spt.topup_date,
    spt.payment_method,
    u.name as recorded_by
FROM student_payment_topups spt
LEFT JOIN student_payments sp ON spt.payment_id = sp.id
LEFT JOIN admit_students ads ON sp.admission_no = ads.admission_no
LEFT JOIN classes c ON sp.class_id = c.id
LEFT JOIN users u ON spt.recorded_by = u.id
WHERE spt.status_approved = 'unapproved'
ORDER BY spt.topup_date DESC";

$pendingTopupsResult = $mysqli->query($pendingTopupsQuery);
$pendingTopups = $pendingTopupsResult->fetch_all(MYSQLI_ASSOC);

// Get unapproved expenses
$expensesQuery = "SELECT 
    id, category, item, amount, date, created_at, recorded_by
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
?>

<!-- Success message -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i> Action completed successfully!
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Pending Requests Tabs -->
<div class="pending-tabs">
    <a href="?tab=student_payments" class="pending-tab-btn <?= $activeTab === 'student_payments' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i> Student Payments
    </a>
    <a href="?tab=expenses" class="pending-tab-btn <?= $activeTab === 'expenses' ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> Expenses
    </a>
    <a href="?tab=salaries" class="pending-tab-btn <?= $activeTab === 'salaries' ? 'active' : '' ?>">
        <i class="bi bi-cash-stack"></i> Salaries
    </a>
</div>

<!-- Student Payments Tab -->
<div id="student_payments-tab" class="tab-content <?= $activeTab === 'student_payments' ? 'active' : '' ?>">
    <!-- Sub-tabs for Payments and Balance Top-ups -->
    <div class="sub-tabs">
        <button class="sub-tab-btn active" onclick="switchSubTab('payments')">
            <i class="bi bi-credit-card"></i> School Payments
        </button>
        <button class="sub-tab-btn" onclick="switchSubTab('topups')">
            <i class="bi bi-cash-coin"></i> Balance Top-ups
        </button>
    </div>

    <!-- School Payments Sub-tab -->
    <div id="payments-content" class="sub-tab-content active">
        <div class="card">
            <div class="card-header pending-card-header text-white">
                <h5 class="mb-0">Unapproved School Payments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingPayments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No unapproved payments pending.
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
                                    <th>Term</th>
                                    <th>Type</th>
                                    <th>Expected</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Parent Contact</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingPayments as $payment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payment['admission_no']) ?></td>
                                        <td><?= htmlspecialchars($payment['student_name']) ?></td>
                                        <td><?= htmlspecialchars($payment['gender']) ?></td>
                                        <td><?= htmlspecialchars($payment['class_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($payment['term']) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_type']) ?></td>
                                        <td><?= number_format($payment['expected_tuition'], 2) ?></td>
                                        <td><?= number_format($payment['amount_paid'], 2) ?></td>
                                        <td><?= number_format($payment['balance'], 2) ?></td>
                                        <td><?= htmlspecialchars($payment['parent_contact']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($payment['recorded_by'] ?? 'System') ?></td>
                                        <td>
                                            <div class="action-button-group">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                    <button type="submit" name="approve_payment" class="btn-approve" onclick="return confirm('Approve this payment?')">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                    <button type="submit" name="reject_payment" class="btn-reject" onclick="return confirm('Reject this payment?')">
                                                        Reject
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

    <!-- Balance Top-ups Sub-tab -->
    <div id="topups-content" class="sub-tab-content">
        <div class="card">
            <div class="card-header pending-card-header text-white">
                <h5 class="mb-0">Unapproved Balance Top-ups</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTopups)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No unapproved balance top-ups pending.
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
                                    <th>Term</th>
                                    <th>Type</th>
                                    <th>Top-up Amount</th>
                                    <th>New Balance</th>
                                    <th>Parent Contact</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingTopups as $topup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($topup['admission_no']) ?></td>
                                        <td><?= htmlspecialchars($topup['student_name']) ?></td>
                                        <td><?= htmlspecialchars($topup['gender']) ?></td>
                                        <td><?= htmlspecialchars($topup['class_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($topup['term']) ?></td>
                                        <td><?= htmlspecialchars($topup['payment_type']) ?></td>
                                        <td><?= number_format($topup['topup_amount'], 2) ?></td>
                                        <td><?= number_format($topup['new_balance'], 2) ?></td>
                                        <td><?= htmlspecialchars($topup['parent_contact']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($topup['topup_date'])) ?></td>
                                        <td><?= htmlspecialchars($topup['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($topup['recorded_by'] ?? 'System') ?></td>
                                        <td>
                                            <div class="action-button-group">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="topup_id" value="<?= $topup['id'] ?>">
                                                    <button type="submit" name="approve_topup" class="btn-approve" onclick="return confirm('Approve this top-up?')">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="topup_id" value="<?= $topup['id'] ?>">
                                                    <button type="submit" name="reject_topup" class="btn-reject" onclick="return confirm('Reject this top-up?')">
                                                        Reject
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
<div class="tab-content <?= $activeTab === 'expenses' ? 'active' : '' ?>" id="expenses-tab">
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
<div class="tab-content <?= $activeTab === 'salaries' ? 'active' : '' ?>" id="salaries-tab">
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
