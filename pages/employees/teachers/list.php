<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php';
require_once __DIR__ . '../../../../app/helper/layout.php';
require_role(['admin','principal']);

$search = $_GET['search'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];
$types  = '';

if ($search) {
    $where = "WHERE teacher_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
    $s = "%$search%";
    $params = [$s,$s,$s];
    $types = "sss";
}

$countSql = "SELECT COUNT(*) total FROM teachers $where";
$countStmt = $mysqli->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $limit);

$sql = "SELECT * FROM teachers $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!-- <!DOCTYPE html>
<html>
<head>
    <title>Teachers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4"> -->

<h4>Teachers</h4>
<nav>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../../app/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">All Teachers</li>
    </ol>
</nav>

<form class="row mb-3">
    <div class="col-md-4">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by ID or name">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Search</button>
    </div>
    <div class="col-md-3 ms-auto text-end">
        <a href="create.php" class="btn btn-success">+ Add Teacher</a>
    </div>
</form>

<div class="card shadow-sm">
<div class="card-body p-0">
<table class="table table-bordered table-hover mb-0">
<thead class="table-dark">
<tr>
<th>#</th><th>Photo</th><th>ID</th><th>Name</th><th>Gender</th>
<th>Class</th><th>Subject</th><th>Phone</th><th>Email</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php $i=$offset+1; while($t=$result->fetch_assoc()): ?>
<tr>
<td><?= $i++ ?></td>
<td><img src="uploads/<?= $t['photo'] ?: 'default.png' ?>" width="40" class="rounded-circle"></td>
<td><?= $t['teacher_id'] ?></td>
<td><?= $t['first_name'].' '.$t['last_name'] ?></td>
<td><?= $t['gender'] ?></td>
<td><?= $t['class'] ?></td>
<td><?= $t['subject'] ?></td>
<td><?= $t['phone'] ?></td>
<td><?= $t['email'] ?></td>
<td>
<a href="view.php?id=<?= $t['teacher_id'] ?>" class="btn btn-sm btn-info">View</a>
<a href="edit_teacher.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
<a href="payments.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-success">Payments</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<nav class="mt-3">
<ul class="pagination justify-content-end">
<?php if($page>1): ?>
<li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&search=<?= $search ?>">Prev</a></li>
<?php endif; ?>
<?php if($page<$pages): ?>
<li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&search=<?= $search ?>">Next</a></li>
<?php endif; ?>
</ul>
</nav>

</div>
</body>
</html>
