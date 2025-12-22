<?php
$title = "Admin Dashboard";
require_once __DIR__ . '/../helper/layout.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">

    <h4 class="mb-4">Admin Dashboard</h4>

    <div class="row g-4">

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-people-fill fs-1 text-primary mb-3"></i>
                    <h5>Users</h5>
                    <p class="text-muted">Manage system users</p>
                    <a href="users.php" class="btn btn-primary w-100">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-shield-lock-fill fs-1 text-warning mb-3"></i>
                    <h5>Audit Logs</h5>
                    <p class="text-muted">System & financial activity</p>
                    <a href="audit-logs.php" class="btn btn-warning w-100">View</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-gear-fill fs-1 text-secondary mb-3"></i>
                    <h5>Settings</h5>
                    <p class="text-muted">System configuration</p>
                    <a href="settings.php" class="btn btn-secondary w-100">Open</a>
                </div>
            </div>
        </div>

    </div>

</div>
