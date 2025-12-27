<?php
$title = "Payroll Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payroll'])) {
    $name = trim($_POST['name']);
    $department = trim($_POST['department']);
    $custom_department = trim($_POST['custom_department'] ?? '');
    $salary = floatval($_POST['salary']);
    $date = trim($_POST['date']);

    // Use custom department if "other" selected
    if ($department === 'other' && !empty($custom_department)) {
        $department = $custom_department;
    }

    if (!$name || !$department || !$date || !$salary) {
        $error = "All fields are required";
    } elseif ($salary <= 0) {
        $error = "Salary must be greater than zero";
    } else {
        $user_id = $_SESSION['user_id'];
        
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Insert into payroll table
            $stmt = $mysqli->prepare("INSERT INTO payroll (name, department, salary, date, recorded_by, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'unapproved')");
            
            if ($stmt) {
                $stmt->bind_param("sssdi", $name, $department, $salary, $date, $user_id);
                if ($stmt->execute()) {
                    $payroll_id = $mysqli->insert_id;
                    $stmt->close();
                    
                    // Insert into expenses table automatically
                    $category = 'Salaries';
                    $item = $name; // Employee name goes in item column
                    $quantity = 1; // Default quantity
                    $unit_price = $salary; // Unit price is the salary
                    $expected = $salary; // Expected = salary
                    $status = 'unapproved'; // Same status as payroll
                    
                    $expenseStmt = $mysqli->prepare("INSERT INTO expenses (category, item, quantity, unit_price, expected, amount, date, recorded_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                    
                    if ($expenseStmt) {
                        $expenseStmt->bind_param("ssddddsss", $category, $item, $quantity, $unit_price, $expected, $salary, $date, $user_id, $status);
                        if ($expenseStmt->execute()) {
                            $expenseStmt->close();
                            
                            // Commit transaction
                            $mysqli->commit();
                            
                            // Redirect to prevent form resubmission
                            header("Location: payroll.php?success=1");
                            exit();
                        } else {
                            throw new Exception("Error recording expense: " . $expenseStmt->error);
                        }
                    } else {
                        throw new Exception("Error preparing expense statement: " . $mysqli->error);
                    }
                } else {
                    throw new Exception("Error recording payroll: " . $stmt->error);
                }
            } else {
                throw new Exception("Error preparing payroll statement: " . $mysqli->error);
            }
        } catch (Exception $e) {
            // Rollback on error
            $mysqli->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get current user role
$userRole = $_SESSION['role'] ?? '';
$canRecordPayroll = in_array($userRole, ['admin', 'bursar']);

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Show success message if redirected
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Payroll recorded successfully! It has been automatically added to the Salaries expenses.";
}

// Build filter query
$filterWhere = "1=1";
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

if ($date_from) {
    $filterWhere .= " AND DATE(payroll.date) >= '" . $mysqli->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $filterWhere .= " AND DATE(payroll.date) <= '" . $mysqli->real_escape_string($date_to) . "'";
}
if ($department) {
    $filterWhere .= " AND payroll.department = '" . $mysqli->real_escape_string($department) . "'";
}
if ($search) {
    $filterWhere .= " AND payroll.name LIKE '%" . $mysqli->real_escape_string($search) . "%'";
}

// Pagination setup
$records_per_page = 50;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM payroll WHERE $filterWhere";
$countResult = $mysqli->query($countQuery);
$countRow = $countResult->fetch_assoc();
$total_records = $countRow['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get payroll with user info
$query = "SELECT 
    payroll.id,
    payroll.name,
    payroll.department,
    payroll.salary,
    payroll.date,
    payroll.status,
    users.name as recorded_by,
    payroll.created_at
FROM payroll
LEFT JOIN users ON payroll.recorded_by = users.id
WHERE $filterWhere
ORDER BY payroll.date DESC, payroll.created_at DESC
LIMIT $offset, $records_per_page";

$result = $mysqli->query($query);
$payroll_records = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalsQuery = "SELECT 
    COUNT(*) as total_count,
    SUM(salary) as total_salary
FROM payroll
WHERE $filterWhere";

$totalsResult = $mysqli->query($totalsQuery);
$totals = $totalsResult->fetch_assoc();

// Get all unique departments for dropdown
$departmentsQuery = "SELECT DISTINCT department FROM payroll WHERE department IS NOT NULL AND department != '' ORDER BY department ASC";
$departmentsResult = $mysqli->query($departmentsQuery);
$departments = $departmentsResult->fetch_all(MYSQLI_ASSOC);
?>

<!-- Toggle Button for Payroll Form -->
<div class="mb-3">
    <?php if ($canRecordPayroll): ?>
        <button type="button" class="btn-toggle-form" onclick="togglePayrollForm()">
            <i class="bi bi-chevron-down" id="toggleIcon"></i>
            <span id="toggleText">Show Form</span>
        </button>
    <?php else: ?>
        <button type="button" class="btn-toggle-form" data-bs-toggle="modal" data-bs-target="#payrollRestrictionModal">
            <i class="bi bi-chevron-down"></i>
            <span>Show Form</span>
        </button>
    <?php endif; ?>
</div>

<!-- Payroll Form (Collapsible) - Only show if user can record -->
<?php if ($canRecordPayroll): ?>
    <div class="card shadow-sm border-0 mb-4" id="payrollFormCard" style="display: none;">
        <div class="card-header form-header text-white">
            <h5 class="mb-0">Record Payroll</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter employee name" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department" id="department" class="form-control" required onchange="handleDepartmentChange()">
                        <option value="">Select Department</option>
                        <option value="finance">Finance</option>
                        <option value="teacher">Teacher</option>
                        <option value="cleaner">Cleaner</option>
                        <option value="security">Security</option>
                        <option value="cook">Cook</option>
                        <option value="driver">Driver</option>
                        <option value="matron">Matron</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-md-4" id="customDepartmentField" style="display: none;">
                    <label class="form-label">Specify Department</label>
                    <input type="text" name="custom_department" class="form-control" placeholder="Enter department name">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Salary</label>
                    <input type="number" name="salary" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="record_payroll" class="btn btn-form-submit w-100">
                        <i class="bi bi-plus-circle"></i> Record Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search by Name</label>
                    <input type="text" name="search" class="form-control" placeholder="Enter employee name" value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['department']) ?>" <?= $department === $dept['department'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="payroll.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Payroll Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="mb-3">Payroll Records</h5>
        <?php if (empty($payroll_records)): ?>
            <div class="alert alert-info">No payroll records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Amount ($)</th>
                            <th>Recorded By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payroll_records as $payroll): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($payroll['date'])) ?></td>
                                <td><?= htmlspecialchars($payroll['name']) ?></td>
                                <td><?= htmlspecialchars($payroll['department']) ?></td>
                                <td><?= number_format($payroll['salary'], 2) ?></td>
                                <td><?= htmlspecialchars($payroll['recorded_by'] ?? 'System') ?></td>
                                <td>
                                    <?php if ($payroll['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unapproved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="printPayroll(<?= $payroll['id'] ?>)" title="Print Payroll">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="table-totals">
                            <td colspan="3" class="text-end fw-bold">TOTALS:</td>
                            <td><?= number_format($totals['total_salary'] ?? 0, 2) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?><?php echo ($search ? '&search=' . urlencode($search) : '') . ($department ? '&department=' . urlencode($department) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo ($search ? '&search=' . urlencode($search) : '') . ($department ? '&department=' . urlencode($department) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?><?php echo ($search ? '&search=' . urlencode($search) : '') . ($department ? '&department=' . urlencode($department) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?php echo ($search ? '&search=' . urlencode($search) : '') . ($department ? '&department=' . urlencode($department) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?><?php echo ($search ? '&search=' . urlencode($search) : '') . ($department ? '&department=' . urlencode($department) : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="text-center mt-3">
                    <p class="text-muted" style="font-size: 13px;">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> records
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Restriction Modal for Principal -->
<div class="modal fade" id="payrollRestrictionModal" tabindex="-1" aria-labelledby="payrollRestrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="payrollRestrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Record Payroll</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong> and <strong>Bursar</strong> roles have permission to record payroll.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary">Principal</strong>, you can view payroll records but cannot create new ones.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/payroll.css">
<script src="../../assets/js/payroll.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
