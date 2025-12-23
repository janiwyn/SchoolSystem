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
    $stmt = $mysqli->prepare("UPDATE student_payment_topups SET status_approved = 'approved' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $topup_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=student_payments&subtab=topups&approved=1");
            exit();
        }
        $stmt->close();
    }
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

// Handle approval of balance top-up - restore previous status
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

// Include layout AFTER all header operations
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

$message = '';
if (isset($_GET['approved']) && $_GET['approved'] == 1) {
    $message = "Record approved successfully!";
}
if (isset($_GET['rejected']) && $_GET['rejected'] == 1) {
    $message = "Record rejected successfully!";
}
?>

<style>
    .pending-card-header {
        background-color: #17a2b8 !important;
    }
    
    .pending-tabs {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    
    .pending-tab-btn {
        padding: 8px 16px;
        font-weight: 600;
        border-radius: 4px;
        border: 2px solid #17a2b8;
        background-color: white;
        color: #17a2b8;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }
    
    .pending-tab-btn.active {
        background-color: #17a2b8;
        color: white;
    }
    
    .pending-tab-btn:hover {
        background-color: #17a2b8;
        color: white;
    }
    
    .sub-tabs {
        margin-bottom: 15px;
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #17a2b8;
        padding-bottom: 10px;
    }
    
    .sub-tab-btn {
        padding: 6px 14px;
        font-weight: 600;
        border-radius: 4px;
        border: 1px solid #17a2b8;
        background-color: white;
        color: #17a2b8;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 13px;
    }
    
    .sub-tab-btn.active {
        background-color: #17a2b8;
        color: white;
    }
    
    .sub-tab-btn:hover {
        background-color: #17a2b8;
        color: white;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .sub-tab-content {
        display: none;
    }
    
    .sub-tab-content.active {
        display: block;
    }
    
    .action-button-group {
        display: flex;
        gap: 8px;
    }
    
    .btn-approve {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-approve:hover {
        background-color: #218838;
    }
    
    .btn-reject {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-reject:hover {
        background-color: #c82333;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table {
        font-size: 13px;
        margin: 0;
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
</style>

<!-- Message Display -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Pending Request Tabs -->
<div class="pending-tabs">
    <a href="?tab=admitted_students" class="pending-tab-btn <?= $active_tab === 'admitted_students' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Admitted Students
    </a>
    <a href="?tab=student_payments" class="pending-tab-btn <?= $active_tab === 'student_payments' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i> Student Payments
    </a>
    <a href="?tab=payrolls" class="pending-tab-btn <?= $active_tab === 'payrolls' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-text"></i> Payrolls
    </a>
</div>

<!-- Tab 1: Admitted Students -->
<div class="tab-content <?= $active_tab === 'admitted_students' ? 'active' : '' ?>">
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
<div class="tab-content <?= $active_tab === 'student_payments' ? 'active' : '' ?>">
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

<!-- Tab 3: Payrolls -->
<div class="tab-content <?= $active_tab === 'payrolls' ? 'active' : '' ?>">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Payrolls</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Payroll approvals will be displayed here.
            </div>
        </div>
    </div>
</div>

<script>
    function switchSubTab(tabName) {
        // Hide all sub-tab contents
        document.getElementById('payments-content').classList.remove('active');
        document.getElementById('topups-content').classList.remove('active');
        
        // Remove active class from all sub-tab buttons
        document.querySelectorAll('.sub-tab-btn').forEach(btn => btn.classList.remove('active'));
        
        // Show selected sub-tab content
        document.getElementById(tabName + '-content').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
        
        // Update URL
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('subtab', tabName);
        window.history.pushState(null, '', '?' + urlParams.toString());
    }
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
