<?php
// Sidebar navigation based on user role
$role = $_SESSION['role'] ?? 'bursar';
$dashboardLinks = [
    'admin' => '../admin/dashboard.php',
    'principal' => '../principal/dashboard.php',
    'bursar' => '../finance/dashboard.php'
];
$dashboardLink = $dashboardLinks[$role] ?? '../finance/dashboard.php';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="School Logo" class="sidebar-logo">
        <h5 class="mb-0" style="font-size: 16.5px;">Bornwell Academy</h5>
    </div>
    
    <nav class="sidebar-nav">
        <a href="<?= $dashboardLink ?>" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="../finance/tuition.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'tuition.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i>
            <span>Tuition</span>
        </a>
        
        <!-- Pending Requests - Only for Admin -->
        <?php if ($role === 'admin'): ?>
            <a href="../admin/pendingrequest.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pendingrequest.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Pending Requests</span>
            </a>
        <?php endif; ?>
        
        <a href="../finance/admitStudents.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'admitStudents.php' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Admitted Students</span>
        </a>
        
        <a href="../finance/studentPayments.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'studentPayments.php' ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i>
            <span>Student Payments</span>
        </a>
        
        <a href="../finance/audit.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'audit.php' ? 'active' : '' ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Audit</span>
        </a>
        
        <a href="../finance/expenses.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'expenses.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i>
            <span>Expenses</span>
        </a>
        
        <!-- Employees Dropdown -->
        <a href="#" class="nav-item" data-bs-toggle="collapse" data-bs-target="#employeesMenu">
            <i class="bi bi-briefcase-fill"></i>
            <span>Employees</span>
            <i class="bi bi-chevron-down ms-auto"></i>
        </a>

        <div class="collapse ms-3" id="employeesMenu">
            <!-- Teachers Submenu -->
            <a href="#" class="nav-item nav-subitem" data-bs-toggle="collapse" data-bs-target="#teachersMenu">
                <i class="bi bi-person-video3"></i>
                <span>Teachers</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>

            <div class="collapse ms-3" id="teachersMenu">
                <a href="../../pages/employees/teachers/list.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-list-ul"></i>
                    <span>All Teachers</span>
                </a>
                <a href="../../pages/employees/teachers/create.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add Teacher</span>
                </a>
                <a href="../../pages/employees/teachers/view.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-eye"></i>
                    <span>View Teacher</span>
                </a>
                <a href="../../pages/employees/teachers/payments.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-credit-card"></i>
                    <span>Payments</span>
                </a>
            </div>

            <!-- Cooks Submenu -->
            <a href="#" class="nav-item nav-subitem" data-bs-toggle="collapse" data-bs-target="#cooksMenu">
                <i class="bi bi-cup-hot"></i>
                <span>Cooks</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>

            <div class="collapse ms-3" id="cooksMenu">
                <a href="../../pages/employees/cooks/list.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-list-ul"></i>
                    <span>All Cooks</span>
                </a>
                <a href="../../pages/employees/cooks/create.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add Cook</span>
                </a>
                <a href="../../pages/employees/cooks/view.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-eye"></i>
                    <span>View Cook</span>
                </a>
                <a href="../../pages/employees/cooks/payments.php" class="nav-item nav-subitem-child">
                    <i class="bi bi-credit-card"></i>
                    <span>Payments</span>
                </a>
            </div>

            <!-- Security Guards Submenu -->
            <a href="../../pages/employees/security/index.php" class="nav-item nav-subitem">
                <i class="bi bi-shield-check"></i>
                <span>Security Guards</span>
            </a>
        </div>
    </nav>

    <style>
        .nav-subitem {
            padding-left: 20px !important;
            font-size: 14px;
            border-left: 2px solid rgba(255,255,255,0.2);
        }

        .nav-subitem-child {
            padding-left: 30px !important;
            font-size: 13px;
            border-left: 2px solid rgba(255,255,255,0.1);
        }

        .nav-item[data-bs-toggle="collapse"] {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-item[data-bs-toggle="collapse"] .bi-chevron-down {
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .nav-item[data-bs-toggle="collapse"][aria-expanded="true"] .bi-chevron-down {
            transform: rotate(-180deg);
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            object-fit: contain;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
    </style>
</aside>
