<?php
$title = "Payroll Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Handle EDIT payroll record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payroll'])) {
    $payroll_id         = intval($_POST['payroll_id']);
    $new_name           = trim($_POST['edit_name']);
    $new_department     = trim($_POST['edit_department']);
    $new_expected_salary = floatval($_POST['edit_expected_salary']);
    $new_salary         = floatval($_POST['edit_salary']); // paid salary
    $new_date           = trim($_POST['edit_date']);

    if ($payroll_id > 0 && $new_name && $new_department && $new_expected_salary >= 0 && $new_salary >= 0 && $new_date) {
        // Get OLD payroll details
        $getStmt = $mysqli->prepare("SELECT name, salary, expected_salary, date, department FROM payroll WHERE id = ?");
        $getStmt->bind_param("i", $payroll_id);
        $getStmt->execute();
        $oldPayroll = $getStmt->get_result()->fetch_assoc();
        $getStmt->close();

        if ($oldPayroll) {
            $mysqli->begin_transaction();

            try {
                // Update payroll table
                $updPayroll = $mysqli->prepare("UPDATE payroll SET name = ?, department = ?, expected_salary = ?, salary = ?, date = ? WHERE id = ?");
                $updPayroll->bind_param("ssddsi", $new_name, $new_department, $new_expected_salary, $new_salary, $new_date, $payroll_id);
                $updPayroll->execute();
                $updPayroll->close();

                // Update matching Salaries expense (set quantity=0, unit_price=0)
                $updExpense = $mysqli->prepare("
                    UPDATE expenses 
                    SET item = ?, 
                        quantity = 0,
                        unit_price = 0,
                        amount = ?, 
                        expected = ?, 
                        date = ?
                    WHERE category = 'Salaries'
                      AND item = ?
                      AND date = ?
                      AND amount = ?
                ");
                $updExpense->bind_param(
                    "sddssssd",
                    $new_name,           // new item
                    $new_salary,         // new amount
                    $new_expected_salary,// new expected
                    $new_date,           // new date
                    $oldPayroll['name'], // old item
                    $oldPayroll['date'], // old date
                    $oldPayroll['salary']// old amount
                );
                $updExpense->execute();
                $updExpense->close();

                $mysqli->commit();
                header("Location: payroll.php?updated=1");
                exit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                error_log("Payroll edit error: " . $e->getMessage());
                header("Location: payroll.php?error=edit_failed");
                exit();
            }
        }
    }
}

// Handle DELETE payroll record (ADMIN ONLY)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_payroll'])
    && strtolower($_SESSION['role'] ?? '') === 'admin'
) {
    $payroll_id = intval($_POST['payroll_id']);
    
    if ($payroll_id > 0) {
        // Get payroll details first (to find matching expense)
        $getStmt = $mysqli->prepare("SELECT name, salary, date FROM payroll WHERE id = ?");
        $getStmt->bind_param("i", $payroll_id);
        $getStmt->execute();
        $payrollRow = $getStmt->get_result()->fetch_assoc();
        $getStmt->close();
        
        if ($payrollRow) {
            $mysqli->begin_transaction();
            
            try {
                // Delete from payroll table
                $delPayroll = $mysqli->prepare("DELETE FROM payroll WHERE id = ?");
                $delPayroll->bind_param("i", $payroll_id);
                $delPayroll->execute();
                $delPayroll->close();
                
                // Delete matching expense (Salaries category, same name/item, date, amount)
                $delExpense = $mysqli->prepare("DELETE FROM expenses WHERE category = 'Salaries' AND item = ? AND date = ? AND amount = ? LIMIT 1");
                $delExpense->bind_param("ssd", $payrollRow['name'], $payrollRow['date'], $payrollRow['salary']);
                $delExpense->execute();
                $delExpense->close();
                
                $mysqli->commit();
                header("Location: payroll.php?deleted=1");
                exit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                // Fall through
            }
        }
    }
}

// Handle PAY salary (add payment to existing paid salary)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_payroll'])) {
    $payroll_id = intval($_POST['payroll_id']);
    $payment_amount = floatval($_POST['payment_amount']);

    if ($payroll_id > 0 && $payment_amount > 0) {
        // Get current payroll details
        $getStmt = $mysqli->prepare("SELECT name, salary, expected_salary, date FROM payroll WHERE id = ?");
        $getStmt->bind_param("i", $payroll_id);
        $getStmt->execute();
        $payroll = $getStmt->get_result()->fetch_assoc();
        $getStmt->close();

        if ($payroll) {
            $new_paid = $payroll['salary'] + $payment_amount;
            
            // Don't allow overpayment
            if ($new_paid > $payroll['expected_salary']) {
                header("Location: payroll.php?error=overpayment");
                exit();
            }

            $mysqli->begin_transaction();

            try {
                // Update payroll table (add to paid salary)
                $updPayroll = $mysqli->prepare("UPDATE payroll SET salary = ? WHERE id = ?");
                $updPayroll->bind_param("di", $new_paid, $payroll_id);
                $updPayroll->execute();
                $updPayroll->close();

                // Update matching expense (set quantity=0, unit_price=0)
                $updExpense = $mysqli->prepare("
                    UPDATE expenses 
                    SET amount = ?,
                        quantity = 0,
                        unit_price = 0
                    WHERE category = 'Salaries' 
                      AND item = ? 
                      AND date = ?
                ");
                $updExpense->bind_param(
                    "dss",
                    $new_paid,
                    $payroll['name'],
                    $payroll['date']
                );
                $updExpense->execute();
                $updExpense->close();

                $mysqli->commit();
                header("Location: payroll.php?paid=1");
                exit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                error_log("Payroll pay error: " . $e->getMessage());
                header("Location: payroll.php?error=pay_failed");
                exit();
            }
        }
    }
}

// Handle form submission (ADD payroll)
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payroll'])) {
    $name               = trim($_POST['name']);
    $department         = trim($_POST['department']);
    $custom_department  = trim($_POST['custom_department'] ?? '');
    $expected_salary    = floatval($_POST['expected_salary']);
    $salary             = floatval($_POST['salary']); // paid salary (can be 0)
    $date               = trim($_POST['date']);

    if ($department === 'other' && !empty($custom_department)) {
        $department = $custom_department;
    }

    if (empty($date)) {
        $date = date('Y-m-d');
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        $date = date('Y-m-d');
    }

    if (!$name || !$department) {
        $error = "Name and department are required";
    } elseif ($expected_salary < 0 || $salary < 0) {
        $error = "Salaries cannot be negative";
    } else {
        $user_id = $_SESSION['user_id'];
        $mysqli->begin_transaction();

        try {
            // Insert into payroll (expected_salary + salary)
            $stmt = $mysqli->prepare("INSERT INTO payroll (name, department, expected_salary, salary, date, recorded_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'unapproved')");
            if ($stmt) {
                $stmt->bind_param("ssddsi", $name, $department, $expected_salary, $salary, $date, $user_id);
                if ($stmt->execute()) {
                    $payroll_id = $mysqli->insert_id;
                    $stmt->close();

                    // Insert into expenses (use quantity=0, unit_price=0 for Salaries)
                    $category = 'Salaries';
                    $item = $name;
                    $quantity = 0;        // Changed from 1 to 0
                    $unit_price = 0;      // Changed from $salary to 0
                    $status = 'unapproved';

                    $expenseStmt = $mysqli->prepare("INSERT INTO expenses (category, item, quantity, unit_price, expected, amount, date, recorded_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                    if ($expenseStmt) {
                        // quantity(d)=0, unit_price(d)=0
                        $expenseStmt->bind_param("ssddddsis", $category, $item, $quantity, $unit_price, $expected_salary, $salary, $date, $user_id, $status);
                        if ($expenseStmt->execute()) {
                            $expenseStmt->close();
                            $mysqli->commit();
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
            $mysqli->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get current user role
$userRole = $_SESSION['role'] ?? '';
$canRecordPayroll = in_array($userRole, ['admin', 'bursar']);
$isAdmin = strtolower($userRole) === 'admin';

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Show success message if redirected
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Payroll recorded successfully! It has been automatically added to the Salaries expenses.";
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Payroll record deleted successfully (also removed from Salaries expenses).";
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Payroll record updated successfully (also updated in Salaries expenses).";
} elseif (isset($_GET['paid']) && $_GET['paid'] == 1) {
    $message = "Salary payment recorded successfully!";
} elseif (isset($_GET['error']) && $_GET['error'] == 'overpayment') {
    $error = "Payment amount exceeds remaining balance.";
} elseif (isset($_GET['error']) && $_GET['error'] == 'pay_failed') {
    $error = "Payment failed. Please try again.";
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
    payroll.expected_salary,
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

// Calculate totals (expected + paid)
$totalsQuery = "SELECT 
    COUNT(*) as total_count,
    SUM(expected_salary) as total_expected,
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
                    <label class="form-label">Expected Annual Salary</label>
                    <input type="number" name="expected_salary" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Paid Salary</label>
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

<!-- Show success/error messages -->
<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Payroll recorded successfully! It has been automatically added to the Salaries expenses.
    </div>
<?php elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Payroll record deleted successfully (also removed from Salaries expenses).
    </div>
<?php elseif (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Payroll record updated successfully (also updated in Salaries expenses).
    </div>
<?php elseif (isset($_GET['paid']) && $_GET['paid'] == 1): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Salary payment recorded successfully!
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 'overpayment'): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle"></i> Payment amount exceeds remaining balance.
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 'pay_failed'): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle"></i> Payment failed. Please try again.
    </div>
<?php endif; ?>

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
                            <th>Expected Annual Salary ($)</th>
                            <th>Annual Salary Paid ($)</th>
                            <th>Balance ($)</th> <!-- NEW -->
                            <th>Recorded By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payroll_records as $payroll): ?>
                            <?php
                            $expected = (float)($payroll['expected_salary'] ?? 0);
                            $paid     = (float)($payroll['salary'] ?? 0);
                            $balance  = max(0, $expected - $paid);
                            ?>
                            <tr>
                                <td>
                                    <?php
                                    $rawDate = $payroll['date'] ?? null;
                                    if ($rawDate && $rawDate !== '0000-00-00' && $rawDate !== '0000-00-00 00:00:00') {
                                        echo date('Y-m-d', strtotime($rawDate));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($payroll['name']) ?></td>
                                <td><?= htmlspecialchars($payroll['department']) ?></td>
                                <td><?= number_format($expected, 2) ?></td>
                                <td><?= number_format($paid, 2) ?></td>
                                <td><?= number_format($balance, 2) ?></td> <!-- NEW -->
                                <td><?= htmlspecialchars($payroll['recorded_by'] ?? 'System') ?></td>
                                <td>
                                    <?php if ($payroll['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unapproved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Pay button (if paid < expected) -->
                                    <?php if ($payroll['salary'] < $payroll['expected_salary']): ?>
                                        <button type="button" class="btn btn-sm btn-success" title="Pay Salary"
                                                data-bs-toggle="modal" data-bs-target="#payPayrollModal"
                                                onclick="loadPayModal(<?= $payroll['id'] ?>, '<?= htmlspecialchars($payroll['name'], ENT_QUOTES) ?>', <?= $payroll['expected_salary'] ?>, <?= $payroll['salary'] ?>)">
                                            <i class="bi bi-cash"></i> Pay
                                        </button>
                                    <?php endif; ?>

                                    <!-- Edit button -->
                                    <button type="button" class="btn btn-sm btn-warning" title="Edit Payroll"
                                            data-bs-toggle="modal" data-bs-target="#editPayrollModal"
                                            onclick="loadEditPayroll(<?= $payroll['id'] ?>, '<?= htmlspecialchars($payroll['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($payroll['department'], ENT_QUOTES) ?>', <?= $payroll['expected_salary'] ?>, <?= $payroll['salary'] ?>, '<?= htmlspecialchars($payroll['date'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>

                                    <!-- Print button -->
                                    <button class="btn btn-sm btn-primary" onclick="printPayroll(<?= $payroll['id'] ?>)" title="Print Payroll">
                                        <i class="bi bi-printer"></i> Print
                                    </button>

                                    <?php if ($isAdmin): ?>
                                        <!-- Delete button (admin only) -->
                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('Are you sure you want to delete this payroll record? This will also remove it from the Salaries expenses.');">
                                            <input type="hidden" name="payroll_id" value="<?= $payroll['id'] ?>">
                                            <button type="submit" name="delete_payroll" class="btn btn-sm btn-danger" title="Delete Payroll">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-danger" title="Delete Payroll"
                                                data-bs-toggle="modal" data-bs-target="#payrollRestrictionModal">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Totals Row -->
                        <?php
                        $totalExpected = (float)($totals['total_expected'] ?? 0);
                        $totalPaid     = (float)($totals['total_salary'] ?? 0);
                        $totalBalance  = max(0, $totalExpected - $totalPaid);
                        ?>
                        <tr class="table-totals">
                            <td colspan="3" class="text-end fw-bold">TOTALS:</td>
                            <td><?= number_format($totalExpected, 2) ?></td>
                            <td><?= number_format($totalPaid, 2) ?></td>
                            <td><?= number_format($totalBalance, 2) ?></td> <!-- NEW -->
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

<!-- Edit Payroll Modal -->
<div class="modal fade" id="editPayrollModal" tabindex="-1" aria-labelledby="editPayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header form-header text-white">
                    <h5 class="modal-title" id="editPayrollModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Payroll Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="payroll_id" id="editPayrollId">

                    <div class="mb-3">
                        <label for="editPayrollName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="editPayrollName" name="edit_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editPayrollDepartment" class="form-label">Department</label>
                        <select class="form-control" id="editPayrollDepartment" name="edit_department" required>
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

                    <div class="mb-3">
                        <label for="editExpectedSalary" class="form-label">Expected Annual Salary</label>
                        <input type="number" class="form-control" id="editExpectedSalary" name="edit_expected_salary" step="0.01" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label for="editPayrollSalary" class="form-label">Paid Salary</label>
                        <input type="number" class="form-control" id="editPayrollSalary" name="edit_salary" step="0.01" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label for="editPayrollDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="editPayrollDate" name="edit_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_payroll" class="btn btn-form-submit">
                        <i class="bi bi-check-circle"></i> Update Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pay Salary Modal -->
<div class="modal fade" id="payPayrollModal" tabindex="-1" aria-labelledby="payPayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header form-header text-white">
                    <h5 class="modal-title" id="payPayrollModalLabel">
                        <i class="bi bi-cash"></i> Pay Salary
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="payroll_id" id="payPayrollId">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Employee Name</label>
                        <p class="form-control-plaintext" id="payEmployeeName"></p>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Expected Annual Salary</label>
                            <p class="form-control-plaintext fw-bold text-primary" id="payExpectedSalary"></p>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Already Paid</label>
                            <p class="form-control-plaintext fw-bold text-success" id="payAlreadyPaid"></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remaining Balance</label>
                        <p class="form-control-plaintext fw-bold text-danger" style="font-size: 20px;" id="payRemainingBalance"></p>
                    </div>

                    <div class="mb-3">
                        <label for="paymentAmount" class="form-label">Payment Amount</label>
                        <input type="number" class="form-control" id="paymentAmount" name="payment_amount" step="0.01" min="0.01" placeholder="Enter amount to pay" required>
                        <small class="form-text text-muted">Cannot exceed remaining balance</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="pay_payroll" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
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
<script src="../../assets/js/payroll.js?v=4"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
