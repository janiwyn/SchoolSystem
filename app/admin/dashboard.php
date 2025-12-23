<?php
$title = "Admin Dashboard";
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
$tuitionQuery = "SELECT SUM(amount_paid) as total FROM student_payments WHERE status_approved = 'approved'";
$tuitionResult = $mysqli->query($tuitionQuery);
$totalTuition = $tuitionResult->fetch_assoc()['total'] ?? 0;

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
        'received' => 0
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

// Populate data into dateRange
foreach ($chartData as $row) {
    $dateRange[$row['chart_date']] = [
        'expected' => (float)$row['expected'],
        'received' => (float)$row['received']
    ];
}

// Prepare data for chart with all dates
$months = [];
$expectedData = [];
$receivedData = [];

foreach ($dateRange as $date => $data) {
    $months[] = date('M d', strtotime($date));
    $expectedData[] = $data['expected'];
    $receivedData[] = $data['received'];
}

$monthsJson = json_encode($months);
$expectedJson = json_encode($expectedData);
$receivedJson = json_encode($receivedData);
?>

<style>
    .stat-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 12px rgba(0,0,0,0.12);
    }

    .stat-card.blue {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
    }

    .stat-card.green {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
    }

    .stat-card.orange {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
    }

    .stat-card.red {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
    }

    .stat-card-body {
        padding: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-content {
        flex: 1;
    }

    .stat-label {
        font-size: 14px;
        font-weight: 600;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
    }

    .stat-icon {
        font-size: 48px;
        opacity: 0.3;
        margin-left: 20px;
    }

    .chart-filter-bar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        padding: 20px 30px;
        margin-top: 30px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
    }

    .filter-label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #17a2b8;
        background-color: white;
        color: #17a2b8;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        background-color: #f0f8ff;
    }

    .filter-btn.active {
        background-color: #17a2b8;
        color: white;
    }

    .chart-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        padding: 30px;
    }

    .chart-header {
        margin-bottom: 25px;
    }

    .chart-header h4 {
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 8px 0;
    }

    .chart-header p {
        color: #7f8c8d;
        margin: 0;
        font-size: 14px;
    }

    .chart-legend {
        display: flex;
        gap: 30px;
        margin-bottom: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
    }

    .legend-color.expected {
        background-color: #3498db;
    }

    .legend-color.received {
        background-color: #27ae60;
    }

    .legend-label {
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
    }

    #tuitionChart {
        max-height: 400px;
    }
</style>

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
        <i class="bi bi-funnel"></i> Filter Chart:
    </div>
    <div class="filter-buttons">
        <a href="?days=7" class="filter-btn <?= $days === 7 ? 'active' : '' ?>">Last 7 Days</a>
        <a href="?days=15" class="filter-btn <?= $days === 15 ? 'active' : '' ?>">Last 15 Days</a>
        <a href="?days=30" class="filter-btn <?= $days === 30 ? 'active' : '' ?>">Last 30 Days</a>
        <a href="?days=60" class="filter-btn <?= $days === 60 ? 'active' : '' ?>">Last 60 Days</a>
        <a href="?days=90" class="filter-btn <?= $days === 90 ? 'active' : '' ?>">Last 90 Days</a>
    </div>
</div>

<!-- Chart Section -->
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

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // Tuition Chart Data
    const months = <?= $monthsJson ?>;
    const expectedData = <?= $expectedJson ?>;
    const receivedData = <?= $receivedJson ?>;

    const ctx = document.getElementById('tuitionChart').getContext('2d');
    const tuitionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Expected Tuition',
                    data: expectedData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Received Tuition',
                    data: receivedData,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#27ae60',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    borderColor: '#ddd',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(value);
                        },
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
