<?php
$title = "Tuition Audit";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

// Build date filter (used for payments and expenses)
$dateFilter = "DATE(sp.payment_date) BETWEEN '$date_from' AND '$date_to'";

// ------------------ MAIN AUDIT DATA: ONE ROW PER CLASS ------------------
// For the selected period, aggregate per class (no repetition of class)
$auditQuery = "
    SELECT 
        sp.class_name,
        SUM(sp.expected_tuition) AS total_expected,
        SUM(sp.amount_paid)      AS total_received,
        SUM(sp.expected_tuition) - SUM(sp.amount_paid) AS balance
    FROM student_payments sp
    WHERE $dateFilter
    GROUP BY sp.class_name
    ORDER BY sp.class_name ASC
";
$auditResult = $mysqli->query($auditQuery);
if (!$auditResult) {
    die("Database error: " . $mysqli->error);
}
$auditData = $auditResult->fetch_all(MYSQLI_ASSOC);

// ------------------ GRAND TOTALS (FILTERED BY DATE) ------------------
$grandTotalQuery = "
    SELECT 
        SUM(sp.expected_tuition) AS grand_expected,
        SUM(sp.amount_paid)      AS grand_received,
        SUM(sp.expected_tuition) - SUM(sp.amount_paid) AS grand_balance
    FROM student_payments sp
    WHERE $dateFilter
";
$grandResult  = $mysqli->query($grandTotalQuery);
$grandTotals  = $grandResult->fetch_assoc() ?: ['grand_expected' => 0, 'grand_received' => 0, 'grand_balance' => 0];

// ------------------ EXPENSES (FILTERED BY DATE) ------------------
$expensesQuery = "
    SELECT 
        SUM(amount) AS total_expenses,
        COUNT(*)    AS expense_count
    FROM expenses
    WHERE DATE(date) BETWEEN '$date_from' AND '$date_to'
      AND status = 'approved'
";
$expensesResult = $mysqli->query($expensesQuery);
$expensesTotals = $expensesResult->fetch_assoc() ?: ['total_expenses' => 0, 'expense_count' => 0];

// Detailed expenses (also filtered by date)
$expensesDetailQuery = "
    SELECT 
        category,
        item,
        amount,
        date,
        recorded_by
    FROM expenses
    WHERE DATE(date) BETWEEN '$date_from' AND '$date_to'
      AND status = 'approved'
    ORDER BY date DESC
";
$expensesDetailResult = $mysqli->query($expensesDetailQuery);
$expensesDetail       = $expensesDetailResult->fetch_all(MYSQLI_ASSOC);

// Net Income = total received (filtered) - total expenses (filtered)
$netIncome    = ($grandTotals['grand_received'] ?? 0) - ($expensesTotals['total_expenses'] ?? 0);
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
        <h5 class="mb-0">
            Tuition Audit Report
            <small class="ms-2" style="font-size: 12px;">
                (<?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>)
            </small>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($auditData)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No data found for the selected period.
            </div>
        <?php else: ?>
            <!-- Scrollable Audit Table Wrapper -->
            <div class="audit-table-wrapper">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Expected Tuition</th>
                            <th>Amount Received</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><?= number_format($row['total_expected'], 2) ?></td>
                                <td><?= number_format($row['total_received'], 2) ?></td>
                                <td><?= number_format($row['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Expenses Dropdown Row -->
                        <tr class="expenses-dropdown-row" onclick="toggleExpensesDropdown(event)">
                            <td colspan="4" style="cursor: pointer; background-color: #f0f8ff; padding: 12px; font-weight: 600;">
                                <i class="bi bi-chevron-right" id="expensesToggleIcon"
                                   style="transition: transform 0.3s; display: inline-block;"></i>
                                Expenses (<?= $expensesTotals['expense_count'] ?? 0 ?> items)
                                <span style="float: right; color: #e74c3c;">
                                    -<?= number_format($expensesTotals['total_expenses'] ?? 0, 2) ?>
                                </span>
                            </td>
                        </tr>

                        <!-- Expenses Detail Rows (Hidden by default) -->
                        <tr id="expensesDetailContainer" style="display: none;">
                            <td colspan="4" style="padding: 0; background-color: #f9f9f9;">
                                <!-- Scrollable Expenses Detail Table -->
                                <div class="expenses-detail-table">
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
                                                    $userStmt  = $mysqli->prepare($userQuery);
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

                        <!-- Net Income Row (Grand Total - Expenses) -->
                        <tr class="table-totals" style="background-color: #e8f4e8; position: sticky; bottom: 0; z-index: 5;">
                            <td class="text-end fw-bold">TOTAL / NET:</td>
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

<script>
// inline to ensure availability
function toggleExpensesDropdown(event) {
    event.stopPropagation();
    const container = document.getElementById('expensesDetailContainer');
    const icon      = document.getElementById('expensesToggleIcon');

    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'table-row';
        if (icon) icon.style.transform = 'rotate(90deg)';
    } else {
        container.style.display = 'none';
        if (icon) icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
