<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower($_SESSION['role'] ?? '');
?>

<!-- SIDEBAR -->
<aside id="sidebar" class="bg-dark text-white">

    <div class="sidebar-header text-center py-4">
        <h5 class="mb-0">School System</h5>
        <small class="text-muted"><?= ucfirst($role) ?></small>
    </div>

    <ul class="nav flex-column px-2">

        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link text-white" href="/schoolSystem/app/<?= $role ?>/dashboard.php">
                🏠 Dashboard
            </a>
        </li>

        <?php if (in_array($role, ['admin','principal'])): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="../../pages/students/list.php">
                    🎓 Students
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="/schoolSystem/app/admin/users.php">
                    👥 Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="/schoolSystem/app/admin/audit-logs.php">
                    🧾 Audit Logs
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'bursar'): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="/schoolSystem/app/finance/payments.php">
                    💰 Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="/schoolSystem/app/finance/expenses.php">
                    📉 Expenses
                </a>
            </li>
        <?php endif; ?>

    </ul>

    <div class="sidebar-footer p-3 mt-auto">
        <div class="small mb-2">
            Logged in as<br>
            <strong><?= htmlspecialchars($_SESSION['user'] ?? '') ?></strong>
        </div>
        <a href="/schoolSystem/app/public/logout.php"
           class="btn btn-outline-warning btn-sm w-100">
            Logout
        </a>
    </div>

</aside>
