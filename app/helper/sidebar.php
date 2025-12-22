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
        <h5 class="mb-0">School System</h5>
    </div>
    
    <nav class="sidebar-nav">
        <a href="<?= $dashboardLink ?>" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="tuition.php" class="nav-item">
            <i class="bi bi-cash-coin"></i>
            <span>Tuition</span>
        </a>
        
        <a href="pending-requests.php" class="nav-item">
            <i class="bi bi-clock-history"></i>
            <span>Pending Requests</span>
        </a>
        
        <a href="admitted-students.php" class="nav-item">
            <i class="bi bi-people-fill"></i>
            <span>Admitted Students</span>
        </a>
        
        <a href="student-payments.php" class="nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Student Payments</span>
        </a>
        
        <a href="payroll.php" class="nav-item">
            <i class="bi bi-file-earmark-text"></i>
            <span>Payroll</span>
        </a>
        
        <a href="employees.php" class="nav-item">
            <i class="bi bi-briefcase-fill"></i>
            <span>Employees</span>
        </a>
        
        <a href="audit.php" class="nav-item">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Audit</span>
        </a>
    </nav>
</aside>
