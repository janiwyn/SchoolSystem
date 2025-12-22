<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php';

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
<html>
<head>
    <title>Teacher Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h4>Teacher Payments</h4>
        <a href="add_payment.php" class="btn btn-success">➕ Add Payment</a>
    </div>

    <!-- Search -->
    <form class="mb-3" method="GET">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by teacher name or ID" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary">Search</button>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Teacher ID</th>
                <th>Name</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Description</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if($result->num_rows > 0): ?>
            <?php $i = $offset + 1; while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['id_no']) ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= number_format($row['amount'], 2) ?></td>
                <td><?= date("d M Y", strtotime($row['payment_date'])) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>
                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">View</a>
                    <a href="edit_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="delete_payment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payment?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No payments found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php for($p=1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p==$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>

                <a href="../../../app/admin/dashboard.php" class="btn btn-secondary">Back</a>
</div>

<script src="../../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
