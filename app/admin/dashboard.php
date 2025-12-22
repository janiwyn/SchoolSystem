<?php
$title = "Admin Dashboard";
require_once __DIR__ . '/../helper/layout.php';
?>

<div class="row g-4">

    <!-- Users Management -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-people-fill fs-1 text-primary"></i>
                </div>
                <h5 class="card-title">Users</h5>
                <p class="text-muted">
                    Manage admins, teachers, bursars, and students.
                </p>
                <a href="users.php" class="btn btn-primary w-100">
                    Manage Users
                </a>
            </div>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-shield-lock-fill fs-1 text-warning"></i>
                </div>
                <h5 class="card-title">Audit Logs</h5>
                <p class="text-muted">
                    Track all financial and system activities.
                </p>
                <a href="audit-logs.php" class="btn btn-warning w-100">
                    View Logs
                </a>
            </div>
        </div>
    </div>

    <!-- System Settings -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-gear-fill fs-1 text-secondary"></i>
                </div>
                <h5 class="card-title">System Settings</h5>
                <p class="text-muted">
                    Configure school system settings.
                </p>
                <a href="settings.php" class="btn btn-secondary w-100">
                    Settings
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
