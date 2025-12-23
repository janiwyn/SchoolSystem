<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_once __DIR__ . '/../helper/layout.php';


$error = '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch payments with optional search
if ($search) {
    $stmt = $mysqli->prepare(
        "SELECT p.id, t.first_name, t.last_name, t.id_no, p.amount, p.payment_date, p.description 
         FROM teacher_payments p
         JOIN teachers t ON t.id = p.teacher_id
         WHERE t.first_name LIKE ? OR t.last_name LIKE ? OR t.id_no LIKE ?
         ORDER BY p.payment_date DESC
         LIMIT ? OFFSET ?"
    );
    $searchTerm = "%$search%";
    $stmt->bind_param("sssii", $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
} else {
    $stmt = $mysqli->prepare(
        "SELECT p.id, t.first_name, t.last_name, t.id_no, p.amount, p.payment_date, p.description 
         FROM teacher_payments p
         JOIN teachers t ON t.id = p.teacher_id
         ORDER BY p.payment_date DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Count total for pagination
$countResult = $mysqli->query(
    "SELECT COUNT(*) as total FROM teacher_payments"
);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Payments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        .page-wrapper {
            max-width: 1200px;
            margin: auto;
        }

        /* Header */
        .page-header {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 25px;
            box-shadow: 0 10px 30px rgba(13,110,253,0.08);
        }

        .page-title {
            color: #0d6efd;
            font-weight: 600;
        }

        /* Cards */
        .content-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
            padding: 25px;
        }

        /* Search */
        .search-input {
            border-radius: 10px 0 0 10px;
            padding: 12px;
        }

        .search-btn {
            border-radius: 0 10px 10px 0;
        }

        /* Table */
        table {
            vertical-align: middle;
        }

        thead {
            background: #e9f4ff;
        }

        thead th {
            color: #0d6efd;
            font-weight: 600;
            border-bottom: none;
        }

        tbody tr:hover {
            background-color: #f1f9ff;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            font-weight: 500;
        }

        .btn-info {
            background-color: #0dcaf0;
            border: none;
        }

        .btn-info:hover {
            background-color: #31d2f2;
        }

        /* Pagination */
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 4px;
            color: #0d6efd;
        }

        .pagination .active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>

<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Header -->
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title mb-0">
                <i class="bi bi-cash-stack"></i> Teacher Payments
            </h4>
            <a href="add_payment.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Payment
            </a>
        </div>

        <!-- Content -->
        <div class="content-card">

            <!-- Search -->
            <form class="mb-4" method="GET">
                <div class="input-group">
                    <input type="text"
                           name="search"
                           class="form-control search-input"
                           placeholder="Search by teacher name or ID"
                           value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary search-btn">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Teacher ID</th>
                            <th>Name</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php $i = $offset + 1; while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['id_no']) ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="fw-semibold text-primary">
                                <?= number_format($row['amount'], 2) ?>
                            </td>
                            <td><?= date("d M Y", strtotime($row['payment_date'])) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-center">
                                <a href="view_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="delete_payment.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this payment?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No payments found
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for($p=1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p==$page?'active':'' ?>">
                            <a class="page-link"
                               href="?page=<?= $p ?>&search=<?= urlencode($search) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

            <!-- Back -->
            <div class="text-end mt-3">
                <a href="../../../admin/dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
