<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

$search = $_GET['search'] ?? '';

$query = "SELECT * FROM cooks WHERE 1";
if ($search) {
    $query .= " AND (id_no LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ?)";
    $stmt = $mysqli->prepare($query);
    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $mysqli->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();
?>
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Optional Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 col-md-6">
<div class="container mt-4">
    <h4>Cooks</h4>

    <form class="mb-3" method="GET">
        <input type="text" name="search" class="form-control" placeholder="Search by ID or name" value="<?= htmlspecialchars($search) ?>">
    </form>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>ID No</th>
                <th>Section</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><img src="../../../<?= $row['photo'] ?>" width="50"></td>
                <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                <td><?= $row['id_no'] ?></td>
                <td><?= $row['section'] ?></td>
                <td><?= $row['phone'] ?></td>
                <td>
                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View</a>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this cook?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
