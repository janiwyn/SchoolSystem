<?php
$title = "Principal Dashboard";
require_once __DIR__ . '/../helper/layout.php';
?>

<div class="row g-4">

    <!-- Approvals -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-check2-circle fs-1 text-success"></i>
                </div>
                <h5 class="card-title">Approvals</h5>
                <p class="text-muted">
                    Review and approve payment reversals and adjustments.
                </p>
                <a href="approvals.php" class="btn btn-success w-100">
                    View Approvals
                </a>
            </div>
        </div>
    </div>

    <!-- Reports -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-file-earmark-text-fill fs-1 text-primary"></i>
                </div>
                <h5 class="card-title">Reports</h5>
                <p class="text-muted">
                    View academic and financial summary reports.
                </p>
                <a href="reports.php" class="btn btn-primary w-100">
                    View Reports
                </a>
            </div>
        </div>
    </div>

</div>
