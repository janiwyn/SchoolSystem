<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php';

require_role(['admin']);

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total payments for pagination
$countQuery = "SELECT COUNT(*) as total FROM cook_payments cp 
               JOIN cooks c ON c.id = cp.cook_id
               WHERE 1";
$params = [];
$types = '';
if ($search) {
    $countQuery .= " AND (c.id_no LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}
$stmt = $mysqli->prepare($countQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalPayments = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalPayments / $limit);

// Fetch payments
$query = "SELECT cp.*, c.first_name, c.last_name, c.photo, c.id_no 
          FROM cook_payments cp
          JOIN cooks c ON c.id = cp.cook_id
          WHERE 1";
if ($search) {
    $query .= " AND (c.id_no LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
}
$query .= " ORDER BY cp.payment_date DESC LIMIT ?, ?";
$stmt = $mysqli->prepare($query);
if ($search) {
    $stmt->bind_param("ssii", $searchParam, $searchParam, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cook Payments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background: #f4fbff; font-family: "Segoe UI", sans-serif; }
        .page-title { color: #0d6efd; font-weight: 600; }
        .table thead th { background: #0d6efd; color: #fff; }
        .btn { border-radius: 8px; }
        .cook-photo { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #e7f3ff; }
        .search-box { border-radius: 10px; border: 1px solid #cfe2ff; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="page-title"><i class="bi bi-cash-stack"></i> Cook Payments</h4>
        <a href="add_payments.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Add Payment</a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control search-box"
                   placeholder="Search by cook name or ID"
                   value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
        </div>
    </form>

    <!-- Payments Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cook</th>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): $i = $offset + 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['id_no']) ?></td>
                        <td class="text-center">
                                    <img src="uploads/<?= $row['photo'] ?: 'default.png' ?>"
                                         class="rounded-circle cook-photo">
                                </td>                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= date("d M Y", strtotime($row['payment_date'])) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td>
                            <a href="view_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            <a href="edit_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <a href="delete_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this payment?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center">No payments found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php for($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>

    <a href="../../../admin/dashboard.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
