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
?>

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
            <div class="table-container">
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
                        
                        <!-- Grand Totals Row -->
                        <tr class="table-totals">
                            <td colspan="2" class="text-end fw-bold">GRAND TOTALS:</td>
                            <td class="totals-expected"><?= number_format($grandTotals['grand_expected'] ?? 0, 2) ?></td>
                            <td class="totals-received"><?= number_format($grandTotals['grand_received'] ?? 0, 2) ?></td>
                            <td class="totals-balance"><?= number_format($grandTotals['grand_balance'] ?? 0, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/audit.css">
<script src="../../assets/js/audit.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
