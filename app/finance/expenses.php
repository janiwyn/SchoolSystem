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
    $quantity = floatval($_POST['quantity']);
    $amount = floatval($_POST['amount']);
    $date = trim($_POST['date']);

    if (!$category || !$item || !$date || !$amount) {
        $error = "Category, Item, Amount, and Date are required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero";
    } else {
        $user_id = $_SESSION['user_id'];
        $stmt = $mysqli->prepare("INSERT INTO expenses (category, item, quantity, amount, date, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        if ($stmt) {
            $stmt->bind_param("ssddsi", $category, $item, $quantity, $amount, $date, $user_id);
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

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';

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
?>

<!-- Toggle Button -->
<div class="expense-form-toggle">
    <h4 class="mb-0">Expense Management</h4>
    <button type="button" class="btn-toggle-form" onclick="toggleExpenseForm()">
        <i class="bi bi-chevron-down" id="toggleIcon"></i>
        <span id="toggleText">Show Form</span>
    </button>
</div>

<!-- Add Expense Form (Hidden by default) -->
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

        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" placeholder="e.g., Supplies, Maintenance" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Item</label>
                <input type="text" name="item" class="form-control" placeholder="e.g., Whiteboard Markers" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" step="0.01" min="0" placeholder="0" value="0">
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

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
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
                        <a href="expenses.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="mb-3">Expenses Records</h5>
        <?php if (empty($expenses)): ?>
            <div class="alert alert-info">No expense records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Amount ($)</th>
                            <th>Recorded By</th>
                            <th>Status</th>
                            <th>Recorded Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($expense['date'])) ?></td>
                                <td><?= htmlspecialchars($expense['category']) ?></td>
                                <td><?= htmlspecialchars($expense['item']) ?></td>
                                <td><?= number_format($expense['quantity'], 2) ?></td>
                                <td><?= number_format($expense['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($expense['recorded_by'] ?? 'System') ?></td>
                                <td>
                                    <?php if ($expense['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unapproved</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($expense['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="table-totals">
                            <td colspan="4" class="text-end fw-bold">TOTALS:</td>
                            <td><?= number_format($totals['total_amount'] ?? 0, 2) ?></td>
                            <td colspan="3"></td>
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

<link rel="stylesheet" href="../../assets/css/expenses.css">
<script src="../../assets/js/expenses.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
