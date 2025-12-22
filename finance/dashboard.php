<?php
$title = "Bursar Dashboard";
require_once __DIR__ . '/../helper/layout.php';
?>

<div class="row g-4">

    <!-- Receive Payment -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-cash-coin fs-1 text-success"></i>
                </div>
                <h5 class="card-title">Receive Payment</h5>
                <p class="card-text text-muted">
                    Record school fees and other payments.
                </p>
                <a href="payments.php" class="btn btn-success w-100">
                    Open Payments
                </a>
            </div>
        </div>
    </div>

    <!-- Financial Reports -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-bar-chart-line fs-1 text-primary"></i>
                </div>
                <h5 class="card-title">Financial Reports</h5>
                <p class="card-text text-muted">
                    View income, expenses, and audit reports.
                </p>
                <a href="reports.php" class="btn btn-primary w-100">
                    View Reports
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
