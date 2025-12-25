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
        
        <?php if (in_array($_SESSION['role'], ['bursar', 'admin', 'principal'])): ?>
            <a href="../finance/payroll.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'payroll.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Payroll</span>
            </a>
        <?php endif; ?>
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
