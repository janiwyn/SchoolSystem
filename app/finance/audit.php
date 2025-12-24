<?php
$title = "Tuition Audit";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$group_by = $_GET['group_by'] ?? 'daily'; // daily, weekly, monthly

// Build date filter
$dateFilter = "DATE(sp.payment_date) BETWEEN '$date_from' AND '$date_to'";

// Build the query based on grouping
if ($group_by === 'daily') {
    $groupColumn = "DATE(sp.payment_date) as audit_date";
    $groupBy = "DATE(sp.payment_date), sp.class_name";
    $orderBy = "DATE(sp.payment_date) DESC, sp.class_name ASC";
    $dateDisplay = "Y-m-d";
} elseif ($group_by === 'weekly') {
    $groupColumn = "CONCAT(YEAR(sp.payment_date), '-W', LPAD(WEEK(sp.payment_date), 2, '0')) as audit_date";
    $groupBy = "YEAR(sp.payment_date), WEEK(sp.payment_date), sp.class_name";
    $orderBy = "YEAR(sp.payment_date) DESC, WEEK(sp.payment_date) DESC, sp.class_name ASC";
    $dateDisplay = "week";
} elseif ($group_by === 'monthly') {
    $groupColumn = "DATE_FORMAT(sp.payment_date, '%Y-%m') as audit_date";
    $groupBy = "YEAR(sp.payment_date), MONTH(sp.payment_date), sp.class_name";
    $orderBy = "YEAR(sp.payment_date) DESC, MONTH(sp.payment_date) DESC, sp.class_name ASC";
    $dateDisplay = "month";
}

// Query to get audit data
$auditQuery = "SELECT 
    $groupColumn,
    sp.class_name,
    SUM(sp.expected_tuition) as total_expected,
    SUM(sp.amount_paid) as total_received,
    SUM(sp.expected_tuition) - SUM(sp.amount_paid) as balance
FROM student_payments sp
WHERE $dateFilter
GROUP BY $groupBy
ORDER BY $orderBy";

$auditResult = $mysqli->query($auditQuery);
if (!$auditResult) {
    die("Database error: " . $mysqli->error);
}
$auditData = $auditResult->fetch_all(MYSQLI_ASSOC);

// Calculate grand totals
$grandTotalQuery = "SELECT 
    SUM(sp.expected_tuition) as grand_expected,
    SUM(sp.amount_paid) as grand_received,
    SUM(sp.expected_tuition) - SUM(sp.amount_paid) as grand_balance
FROM student_payments sp
WHERE $dateFilter";

$grandResult = $mysqli->query($grandTotalQuery);
$grandTotals = $grandResult->fetch_assoc();

// Get total expenses
$expensesQuery = "SELECT 
    SUM(amount) as total_expenses,
    COUNT(*) as expense_count
FROM expenses
WHERE DATE(date) BETWEEN '$date_from' AND '$date_to' AND status = 'approved'";

$expensesResult = $mysqli->query($expensesQuery);
$expensesTotals = $expensesResult->fetch_assoc();

// Get detailed expenses for dropdown
$expensesDetailQuery = "SELECT 
    category,
    item,
    amount,
    date,
    recorded_by
FROM expenses
WHERE DATE(date) BETWEEN '$date_from' AND '$date_to' AND status = 'approved'
ORDER BY date DESC";

$expensesDetailResult = $mysqli->query($expensesDetailQuery);
$expensesDetail = $expensesDetailResult->fetch_all(MYSQLI_ASSOC);

// Calculate Net Income = Grand Received - Total Expenses
$netIncome = ($grandTotals['grand_received'] ?? 0) - ($expensesTotals['total_expenses'] ?? 0);

// Get grand balance from audit data (sum of all balances)
$grandBalance = $grandTotals['grand_balance'] ?? 0;
?>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <!-- Net Income Card (Green) -->
    <div class="col-md-12 col-lg-4">
        <div class="card audit-stat-card green">
            <div class="card-body audit-stat-body">
                <div class="audit-stat-content">
                    <div class="audit-stat-label">Net Income</div>
                    <div class="audit-stat-value"><?= number_format($netIncome, 2) ?></div>
                </div>
                <div class="audit-stat-icon">
                    <i class="bi bi-graph-up"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Amount Received Card (Orange) -->
    <div class="col-md-12 col-lg-4">
        <div class="card audit-stat-card orange">
            <div class="card-body audit-stat-body">
                <div class="audit-stat-content">
                    <div class="audit-stat-label">Total Received</div>
                    <div class="audit-stat-value"><?= number_format($grandTotals['grand_received'] ?? 0, 2) ?></div>
                </div>
                <div class="audit-stat-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Expenses Card (Red) -->
    <div class="col-md-12 col-lg-4">
        <div class="card audit-stat-card red">
            <div class="card-body audit-stat-body">
                <div class="audit-stat-content">
                    <div class="audit-stat-label">Total Expenses</div>
                    <div class="audit-stat-value"><?= number_format($expensesTotals['total_expenses'] ?? 0, 2) ?></div>
                </div>
                <div class="audit-stat-icon">
                    <i class="bi bi-calculator"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <label>Group By</label>
                    <select name="group_by" class="form-control">
                        <option value="daily" <?= $group_by === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $group_by === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $group_by === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>

                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="audit.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Audit Table -->
<div class="card shadow-sm border-0">
    <div class="card-header audit-header text-white">
        <h5 class="mb-0">Tuition Audit Report - <?= ucfirst($group_by) ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($auditData)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No data found for the selected period.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Expected Tuition</th>
                            <th>Amount Received</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentDate = '';
                        foreach ($auditData as $row): 
                            $isNewDate = ($currentDate !== $row['audit_date']);
                            if ($isNewDate && $currentDate !== '') {
                                // Add date subtotal row here if needed
                            }
                            $currentDate = $row['audit_date'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['audit_date']) ?></td>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><?= number_format($row['total_expected'], 2) ?></td>
                                <td><?= number_format($row['total_received'], 2) ?></td>
                                <td><?= number_format($row['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Expenses Dropdown Row -->
                        <tr class="expenses-dropdown-row" onclick="toggleExpensesDropdown(event)">
                            <td colspan="5" style="cursor: pointer; background-color: #f0f8ff; padding: 12px; font-weight: 600;">
                                <i class="bi bi-chevron-right" id="expensesToggleIcon" style="transition: transform 0.3s; display: inline-block;"></i>
                                Expenses (<?= $expensesTotals['expense_count'] ?? 0 ?> items)
                                <span style="float: right; color: #e74c3c;">-<?= number_format($expensesTotals['total_expenses'] ?? 0, 2) ?></span>
                            </td>
                        </tr>

                        <!-- Expenses Detail Rows (Hidden by default) -->
                        <tr id="expensesDetailContainer" style="display: none;">
                            <td colspan="5" style="padding: 0; background-color: #f9f9f9;">
                                <div class="expenses-detail-table" style="padding: 15px;">
                                    <table class="table table-sm" style="margin-bottom: 0;">
                                        <thead>
                                            <tr style="background-color: #fff3cd;">
                                                <th>Date</th>
                                                <th>Category</th>
                                                <th>Item</th>
                                                <th>Amount</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($expensesDetail)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No expenses recorded</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($expensesDetail as $expense): 
                                                    $userQuery = "SELECT name FROM users WHERE id = ?";
                                                    $userStmt = $mysqli->prepare($userQuery);
                                                    $userStmt->bind_param("i", $expense['recorded_by']);
                                                    $userStmt->execute();
                                                    $userName = $userStmt->get_result()->fetch_assoc()['name'] ?? 'N/A';
                                                    $userStmt->close();
                                                ?>
                                                    <tr>
                                                        <td><?= date('Y-m-d', strtotime($expense['date'])) ?></td>
                                                        <td><?= htmlspecialchars($expense['category']) ?></td>
                                                        <td><?= htmlspecialchars($expense['item']) ?></td>
                                                        <td><?= number_format($expense['amount'], 2) ?></td>
                                                        <td><?= htmlspecialchars($userName) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Net Income Row -->
                        <tr class="table-totals" style="background-color: #e8f4e8;">
                            <td colspan="2" class="text-end fw-bold">NET INCOME:</td>
                            <td class="totals-expected"><?= number_format($grandTotals['grand_expected'] ?? 0, 2) ?></td>
                            <td class="totals-received"><?= number_format($netIncome, 2) ?></td>
                            <td style="color: #28a745; font-weight: 700;"><?= number_format($grandBalance, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/audit.css">

<!-- Load script INLINE to ensure function is available -->
<script>
function toggleExpensesDropdown(event) {
    event.stopPropagation();
    const container = document.getElementById('expensesDetailContainer');
    const icon = document.getElementById('expensesToggleIcon');
    
    if (container.style.display === 'none') {
        container.style.display = 'table-row';
        icon.style.transform = 'rotate(90deg)';
    } else {
        container.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
