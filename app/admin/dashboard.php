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

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
