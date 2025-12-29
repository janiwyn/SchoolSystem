<?php
$title = "Expenses Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_expense'])) {
    $category = trim($_POST['category']);
    $item = trim($_POST['item']);
    $amount = floatval($_POST['amount']);
    $date = trim($_POST['date']);
    
    // Only process quantity and unit_price for Cooks category
    $quantity = 0;
    $unit_price = 0;
    $expected = 0;
    
    if ($category === 'Cooks') {
        $quantity = floatval($_POST['quantity']);
        $unit_price = floatval($_POST['unit_price']);
        $expected = $quantity * $unit_price;
    }

    if (!$category || !$item || !$date || !$amount) {
        $error = "Category, Item, Amount, and Date are required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero";
    } else {
        $user_id = $_SESSION['user_id'];
        $stmt = $mysqli->prepare("INSERT INTO expenses (category, item, quantity, unit_price, expected, amount, date, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if ($stmt) {
            $stmt->bind_param("ssddddsi", $category, $item, $quantity, $unit_price, $expected, $amount, $date, $user_id);
            if ($stmt->execute()) {
                $message = "Expense recorded successfully!";
                // Clear form fields
                $_POST = [];
            } else {
                $error = "Error recording expense: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Admin‑only: bulk delete expenses by category + date range
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_expenses_by_filter'])
    && (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
) {
    $deleteCategory = $_POST['delete_category'] ?? 'all';
    $deleteFrom     = $_POST['delete_from'] ?? '';
    $deleteTo       = $_POST['delete_to']   ?? '';

    if ($deleteFrom && $deleteTo) {
        // Normalize dates if user swapped them
        if ($deleteFrom > $deleteTo) {
            [$deleteFrom, $deleteTo] = [$deleteTo, $deleteFrom];
        }

        $deletedCount = 0;
        $mysqli->begin_transaction();

        try {
            if ($deleteCategory === 'all') {
                $sql  = "DELETE FROM expenses WHERE DATE(date) BETWEEN ? AND ?";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $deleteFrom, $deleteTo);
                    $stmt->execute();
                    $deletedCount = $stmt->affected_rows;
                    $stmt->close();
                }
            } else {
                $sql  = "DELETE FROM expenses WHERE DATE(date) BETWEEN ? AND ? AND category = ?";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('sss', $deleteFrom, $deleteTo, $deleteCategory);
                    $stmt->execute();
                    $deletedCount = $stmt->affected_rows;
                    $stmt->close();
                }
            }

            $mysqli->commit();
            header("Location: expenses.php?deleted=" . (int)$deletedCount);
            exit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            // Optional: log error
        }
    }
}

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

// Get active tab
$active_tab = $_GET['tab'] ?? 'salaries';

// Build filter query
$filterWhere = "1=1";
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if ($date_from) {
    $filterWhere .= " AND DATE(expenses.date) >= '" . $mysqli->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $filterWhere .= " AND DATE(expenses.date) <= '" . $mysqli->real_escape_string($date_to) . "'";
}

// Add category filter based on active tab
if ($active_tab === 'salaries') {
    $filterWhere .= " AND expenses.category = 'Salaries'";
} elseif ($active_tab === 'cooks') {
    $filterWhere .= " AND expenses.category = 'Cooks'";
} elseif ($active_tab === 'utilities') {
    $filterWhere .= " AND expenses.category = 'Utilities'";
} elseif ($active_tab === 'administrative') {
    $filterWhere .= " AND expenses.category = 'Administrative'";
}

// Pagination setup
$records_per_page = 50;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM expenses WHERE $filterWhere";
$countResult = $mysqli->query($countQuery);
$countRow = $countResult->fetch_assoc();
$total_records = $countRow['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get expenses with user info
$query = "SELECT 
    expenses.id,
    expenses.category,
    expenses.item,
    expenses.quantity,
    expenses.unit_price,
    expenses.expected,
    expenses.amount,
    expenses.date,
    expenses.status,
    users.name as recorded_by,
    expenses.created_at
FROM expenses
LEFT JOIN users ON expenses.recorded_by = users.id
WHERE $filterWhere
ORDER BY expenses.date DESC, expenses.created_at DESC
LIMIT $offset, $records_per_page";

$result = $mysqli->query($query);
$expenses = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalsQuery = "SELECT 
    COUNT(*) as total_count,
    SUM(amount) as total_amount,
    SUM(quantity) as total_quantity
FROM expenses
WHERE $filterWhere";

$totalsResult = $mysqli->query($totalsQuery);
$totals = $totalsResult->fetch_assoc();

// Get current user role
$userRole = $_SESSION['role'] ?? '';
$canRecordExpense = in_array($userRole, ['admin', 'bursar']);
?>

<!-- Toggle Button for Expense Form -->
<div class="mb-3">
    <?php if ($canRecordExpense): ?>
        <button type="button" class="btn-toggle-form" onclick="toggleExpenseForm()">
            <i class="bi bi-chevron-down" id="toggleIcon"></i>
            <span id="toggleText">Show Form</span>
        </button>
    <?php else: ?>
        <button type="button" class="btn-toggle-form" data-bs-toggle="modal" data-bs-target="#expenseRestrictionModal">
            <i class="bi bi-chevron-down"></i>
            <span>Show Form</span>
        </button>
    <?php endif; ?>
</div>

<!-- Expense Form (Collapsible) - Only show if user can record -->
<?php if ($canRecordExpense): ?>
    <div class="card shadow-sm border-0 mb-4" id="expenseFormCard" style="display: none;">
        <div class="card-header form-header text-white">
            <h5 class="mb-0">Record Expense</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="row g-3" id="expenseForm">
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category" id="category" class="form-control" required onchange="handleCategoryChange()">
                        <option value="">Select Category</option>
                        <option value="Cooks">Cooks (Food)</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Administrative">Administrative</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Item</label>
                    <input type="text" name="item" class="form-control" placeholder="e.g., Rice, Electricity, Office Supplies" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" step="0.01" min="0" placeholder="0" value="0" oninput="calculateExpected()">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Unit Price</label>
                    <input type="number" name="unit_price" id="unit_price" class="form-control" step="0.01" min="0" placeholder="0.00" value="0" oninput="calculateExpected()">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Expected</label>
                    <input type="number" name="expected" id="expected" class="form-control readonly-field" step="0.01" placeholder="0.00" value="0.00" readonly style="background-color: #e9ecef;">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Amount Paid ($)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="record_expense" class="btn btn-form-submit w-100">
                        <i class="bi bi-plus-circle"></i> Record Expense
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
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <div class="filter-row">
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
                        <a href="expenses.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Expense Tabs -->
<div class="expense-tabs-container">
    <div class="expense-tabs">
        <a href="?tab=salaries<?= $date_from ? '&date_from=' . $date_from : '' ?><?= $date_to ? '&date_to=' . $date_to : '' ?>" 
           class="expense-tab-btn <?= $active_tab === 'salaries' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> Salaries
        </a>
        <a href="?tab=cooks<?= $date_from ? '&date_from=' . $date_from : '' ?><?= $date_to ? '&date_to=' . $date_to : '' ?>" 
           class="expense-tab-btn <?= $active_tab === 'cooks' ? 'active' : '' ?>">
            <i class="bi bi-egg-fried"></i>Food
        </a>
        <a href="?tab=utilities<?= $date_from ? '&date_from=' . $date_from : '' ?><?= $date_to ? '&date_to=' . $date_to : '' ?>" 
           class="expense-tab-btn <?= $active_tab === 'utilities' ? 'active' : '' ?>">
            <i class="bi bi-lightning-charge"></i> Utilities
        </a>
        <a href="?tab=administrative<?= $date_from ? '&date_from=' . $date_from : '' ?><?= $date_to ? '&date_to=' . $date_to : '' ?>" 
           class="expense-tab-btn <?= $active_tab === 'administrative' ? 'active' : '' ?>">
            <i class="bi bi-briefcase"></i> Administrative
        </a>
    </div>
</div>

<!-- Success Message for Deleted Records -->
<?php if (isset($_GET['deleted']) && is_numeric($_GET['deleted'])): ?>
    <div class="alert alert-success alert-sm">
        <i class="bi bi-check-circle"></i>
        <?= (int)$_GET['deleted'] ?> expense record(s) deleted successfully.
    </div>
<?php endif; ?>

<!-- Expenses Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="mb-3">Expenses Records</h5>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Admin‑only bulk delete button -->
            <button type="button"
                    class="btn btn-danger mb-3"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteExpensesByFilterModal">
                <i class="bi bi-trash"></i> Delete Expenses
            </button>
        <?php endif; ?>

        <?php if (empty($expenses)): ?>
            <div class="alert alert-info">No expenses found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Expected</th>
                            <th>Amount Paid</th>
                            <th>Recorded By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($expense['date'])) ?></td>
                                <td><?= htmlspecialchars($expense['category']) ?></td>
                                <td><?= htmlspecialchars($expense['item']) ?></td>
                                <td><?= number_format($expense['quantity'], 2) ?></td>
                                <td><?= number_format($expense['unit_price'], 2) ?></td>
                                <td><?= number_format($expense['expected'], 2) ?></td>
                                <td><?= number_format($expense['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($expense['recorded_by'] ?? 'System') ?></td>
                                <td>
                                    <?php if ($expense['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unapproved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="table-totals">
                            <td colspan="6" class="text-end fw-bold">TOTALS:</td>
                            <td><?= number_format($totals['total_amount'] ?? 0, 2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?><?php echo ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?><?php echo ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?php echo ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?><?php echo ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Pagination Info -->
                <div class="text-center mt-3">
                    <p class="text-muted" style="font-size: 13px;">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> expenses
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <!-- Admin‑only modal: delete expenses by category + date range -->
    <div class="modal fade" id="deleteExpensesByFilterModal" tabindex="-1" aria-labelledby="deleteExpensesByFilterLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteExpensesByFilterLabel">
                        <i class="bi bi-trash"></i> Delete Expenses
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        This will permanently delete all expense records that match the selected
                        category and date range.
                    </p>

                    <div class="mb-3">
                        <label for="delete_category" class="form-label">Category</label>
                        <select class="form-control" id="delete_category" name="delete_category" required>
                            <option value="all">All Categories</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Food">Food</option>
                            <option value="Administrative">Administrative</option>
                            <option value="Utilities">Utilities</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="delete_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="delete_from" name="delete_from" required>
                    </div>
                    <div class="mb-3">
                        <label for="delete_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="delete_to" name="delete_to" required>
                    </div>

                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        This action cannot be undone. Please make sure the category and dates are correct.
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="delete_expenses_by_filter" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Records
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Restriction Modal for Principal -->
<div class="modal fade" id="expenseRestrictionModal" tabindex="-1" aria-labelledby="expenseRestrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="expenseRestrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Record Expense</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong> and <strong>Bursar</strong> roles have permission to record expenses.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary">Principal</strong>, you can view expense records but cannot create new ones.
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

<link rel="stylesheet" href="../../assets/css/expenses.css">
<script src="../../assets/js/expenses.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
