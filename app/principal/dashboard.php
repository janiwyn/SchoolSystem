<?php
$title = "Principal Dashboard";
require_once __DIR__ . '/../helper/layout.php';

// Get statistics for dashboard cards
// Total Users
$usersQuery = "SELECT COUNT(*) as total FROM users WHERE status = 1";
$usersResult = $mysqli->query($usersQuery);
$totalUsers = $usersResult->fetch_assoc()['total'] ?? 0;

// Total Admitted Students
$studentsQuery = "SELECT COUNT(*) as total FROM admit_students WHERE status = 'approved'";
$studentsResult = $mysqli->query($studentsQuery);
$totalStudents = $studentsResult->fetch_assoc()['total'] ?? 0;

// Total Tuition Collected
$tuitionBaseQuery = "SELECT SUM(amount_paid) as total FROM student_payments"; // removed status filter
$tuitionBaseResult = $mysqli->query($tuitionBaseQuery);
$baseTuition = $tuitionBaseResult ? (float)($tuitionBaseResult->fetch_assoc()['total'] ?? 0) : 0;

// Only use amount_paid from student_payments
$totalTuition = $baseTuition;

// Pending Approvals
$pendingQuery = "SELECT COUNT(*) as total FROM student_payments WHERE status_approved = 'unapproved' AND id NOT IN (SELECT DISTINCT payment_id FROM student_payment_topups WHERE status_approved = 'unapproved')";
$pendingResult = $mysqli->query($pendingQuery);
$pendingPayments = $pendingResult->fetch_assoc()['total'] ?? 0;

// Get chart filter parameter (default to 30 days)
$days = intval($_GET['days'] ?? 30);
$validDays = [7, 15, 30, 60, 90];
if (!in_array($days, $validDays)) {
    $days = 30;
}

// Generate all dates in the range
$startDate = date('Y-m-d', strtotime("-$days days"));
$endDate = date('Y-m-d');
$dateRange = [];
$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);

while ($currentDate <= $endDateTime) {
    $dateRange[$currentDate->format('Y-m-d')] = [
        'expected' => 0,
        'received' => 0,
        'admitted' => 0,
        'expenses' => 0
    ];
    $currentDate->modify('+1 day');
}

// Get chart data based on selected days
$chartQuery = "SELECT 
    DATE(payment_date) as chart_date,
    SUM(expected_tuition) as expected,
    SUM(amount_paid) as received
FROM student_payments
WHERE payment_date >= '$startDate' AND payment_date <= '$endDate'
GROUP BY DATE(payment_date)
ORDER BY payment_date ASC";

$chartResult = $mysqli->query($chartQuery);
$chartData = $chartResult->fetch_all(MYSQLI_ASSOC);

// Get student admissions data
$admissionsQuery = "SELECT 
    DATE(created_at) as admission_date,
    COUNT(*) as admitted_count
FROM admit_students
WHERE DATE(created_at) >= '$startDate' AND DATE(created_at) <= '$endDate' AND status = 'approved'
GROUP BY DATE(created_at)
ORDER BY DATE(created_at) ASC";

$admissionsResult = $mysqli->query($admissionsQuery);
$admissionsData = $admissionsResult->fetch_all(MYSQLI_ASSOC);

// Get expenses data
$expensesQuery = "SELECT 
    DATE(date) as expense_date,
    SUM(amount) as total_expenses
FROM expenses
WHERE date >= '$startDate' AND date <= '$endDate'
GROUP BY DATE(date)
ORDER BY date ASC";

$expensesResult = $mysqli->query($expensesQuery);
$expensesResultData = $expensesResult->fetch_all(MYSQLI_ASSOC);

// Populate data into dateRange
foreach ($chartData as $row) {
    $dateRange[$row['chart_date']] = [
        'expected' => (float)$row['expected'],
        'received' => (float)$row['received'],
        'admitted' => $dateRange[$row['chart_date']]['admitted'] ?? 0,
        'expenses' => $dateRange[$row['chart_date']]['expenses'] ?? 0
    ];
}

foreach ($admissionsData as $row) {
    if (isset($dateRange[$row['admission_date']])) {
        $dateRange[$row['admission_date']]['admitted'] = (int)$row['admitted_count'];
    }
}

foreach ($expensesResultData as $row) {
    if (isset($dateRange[$row['expense_date']])) {
        $dateRange[$row['expense_date']]['expenses'] = (float)$row['total_expenses'];
    }
}

// Prepare data for charts
$months = [];
$expectedData = [];
$receivedData = [];
$admittedData = [];
$expensesChartData = [];

foreach ($dateRange as $date => $data) {
    $months[] = date('M d', strtotime($date));
    $expectedData[] = $data['expected'];
    $receivedData[] = $data['received'];
    $admittedData[] = $data['admitted'];
    $expensesChartData[] = $data['expenses'];
}
?>

<div class="row g-4 mb-4">
    <!-- Total Users Card -->
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card blue">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $totalUsers ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Admitted Students Card -->
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card green">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Admitted Students</div>
                    <div class="stat-value"><?= $totalStudents ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Tuition Collected Card -->
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card orange">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Tuition Collected</div>
                    <div class="stat-value"><?= number_format($totalTuition, 0) ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Approvals Card -->
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card red">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Pending Approvals</div>
                    <div class="stat-value"><?= $pendingPayments ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Filter Bar -->
<div class="chart-filter-bar">
    <div class="filter-label">
        <i class="bi bi-funnel"></i> Filter Charts:
    </div>
    <div class="filter-buttons">
        <a href="?days=7" class="filter-btn <?= $days === 7 ? 'active' : '' ?>">Last 7 Days</a>
        <a href="?days=15" class="filter-btn <?= $days === 15 ? 'active' : '' ?>">Last 15 Days</a>
        <a href="?days=30" class="filter-btn <?= $days === 30 ? 'active' : '' ?>">Last 30 Days</a>
        <a href="?days=60" class="filter-btn <?= $days === 60 ? 'active' : '' ?>">Last 60 Days</a>
        <a href="?days=90" class="filter-btn <?= $days === 90 ? 'active' : '' ?>">Last 90 Days</a>
    </div>
</div>

<!-- Tuition Analysis Chart -->
<div class="chart-container">
    <div class="chart-header">
        <h4>Tuition Analysis</h4>
        <p>Expected vs Received Tuition (Last <?= $days ?> Days)</p>
    </div>

    <div class="chart-legend">
        <div class="legend-item">
            <div class="legend-color expected"></div>
            <div class="legend-label">Expected Tuition</div>
        </div>
        <div class="legend-item">
            <div class="legend-color received"></div>
            <div class="legend-label">Received Tuition</div>
        </div>
    </div>

    <canvas id="tuitionChart"></canvas>
</div>

<!-- Student Admissions Chart -->
<div class="chart-container" style="margin-top: 30px;">
    <div class="chart-header">
        <h4>Student Admissions</h4>
        <p>Number of Students Admitted Per Day (Last <?= $days ?> Days)</p>
    </div>

    <div class="chart-legend">
        <div class="legend-item">
            <div class="legend-color" style="background-color: #9b59b6;"></div>
            <div class="legend-label">Students Admitted</div>
        </div>
    </div>

    <canvas id="admissionsChart"></canvas>
</div>

<!-- Expenses Chart -->
<div class="chart-container" style="margin-top: 30px;">
    <div class="chart-header">
        <h4>Expenses Tracking</h4>
        <p>Total Expenses Per Day (Last <?= $days ?> Days)</p>
    </div>

    <div class="chart-legend">
        <div class="legend-item">
            <div class="legend-color" style="background-color: #e74c3c;"></div>
            <div class="legend-label">Total Expenses</div>
        </div>
    </div>

    <canvas id="expensesChart"></canvas>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Pass data to JavaScript -->
<script>
    window.chartMonths = <?= json_encode($months) ?>;
    window.expectedData = <?= json_encode($expectedData) ?>;
    window.receivedData = <?= json_encode($receivedData) ?>;
    window.admittedData = <?= json_encode($admittedData) ?>;
    window.expensesData = <?= json_encode($expensesChartData) ?>;
</script>

<link rel="stylesheet" href="../../assets/css/principalDashboard.css">
<script src="../../assets/js/principalDashboard.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
