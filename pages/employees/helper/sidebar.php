<?php
// Sidebar navigation based on user role
$role = $_SESSION['role'] ?? 'bursar';
$dashboardLinks = [
    'admin' => 'admin/dashboard.php',
    'principal' => 'principal/dashboard.php',
    'bursar' => 'finance/dashboard.php'
];
$dashboardLink = $dashboardLinks[$role] ?? 'finance/dashboard.php';
?>


<aside class="sidebar">
    <div class="sidebar-header">
        <h5 class="mb-0">School System</h5>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../../../<?= $dashboardLink ?>" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="../../../finance/tuition.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'tuition.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i>
            <span>Tuition</span>
        </a>
        
        <a href="#pending-requests.php" class="nav-item">
            <i class="bi bi-clock-history"></i>
            <span>Pending Requests</span>
        </a>
        
        <a href="#admitted-students.php" class="nav-item">
            <i class="bi bi-people-fill"></i>
            <span>Admitted Students</span>
        </a>
        
        <a href="#student-payments.php" class="nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Student Payments</span>
        </a>
        
        <a href="#payroll.php" class="nav-item">
            <i class="bi bi-file-earmark-text"></i>
            <span>Payroll</span>
        </a>
        
<!-- EMPLOYEES -->
<li class="nav-item">
    <a class="nav-link text-white d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#employeesMenu"
       role="button">
        <span>👷 Employees</span>
        <span class="small">▾</span>
    </a>

    <div class="collapse ms-3" id="employeesMenu">
        <ul class="nav flex-column">

            <!-- TEACHERS -->
            <li class="nav-item">
                <a class="nav-link text-white"
                   data-bs-toggle="collapse"
                   href="#teachersMenu">
                    👩‍🏫 Teachers
                </a>

                <div class="collapse ms-3" id="teachersMenu">
                    <ul class="nav flex-column">

                        <li class="nav-item">
                            <a href="../teachers/list.php"
                               class="nav-link text-white">
                                📋 All Teachers
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="../teachers/create.php"
                               class="nav-link text-white">
                                ➕ Add Teacher
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="../teachers/view.php"
                               class="nav-link text-white">
                                📋 View Teacher
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="../teachers/payments.php"
                               class="nav-link text-white">
                                💰 Payments
                            </a>
                        </li>

                    </ul>
                </div>
            </li>

         <!-- COOKS -->
<li class="nav-item">
    <a class="nav-link text-white" data-bs-toggle="collapse" href="#cooksMenu">
        🍳 Cooks
    </a>
    <div class="collapse ms-3" id="cooksMenu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="../cooks/list.php" class="nav-link text-white">📋 All Cooks</a>
            </li>
            <li class="nav-item">
                <a href="../cooks/create.php" class="nav-link text-white">➕ Add Cook</a>
            </li>
            <li class="nav-item">
                <a href="../cooks/view.php" class="nav-link text-white">📋 View Cook</a>
            </li>
            <li class="nav-item">
                <a href="../cooks/payments.php" class="nav-link text-white">💰 Payments</a>
            </li>
        </ul>
    </div>
</li>


            <!-- SECURITY -->
            <li class="nav-item">
                <a href="/security/index.php"
                   class="nav-link text-white">
                    🛡️ Security Guards
                </a>
            </li>

        </ul>
    </div>
</li>


        
        <a href="audit.php" class="nav-item">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Audit</span>
        </a>
    </nav>
</aside>
