<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_once __DIR__ . '/../helper/layout.php';
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teachers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
        }

        .page-wrapper {
            max-width: 1500px;
            margin: auto;
        }

        .page-title {
            color: #0d6efd;
            font-weight: 600;
        }

        .table-card {
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
        }

        .table thead th {
            vertical-align: middle;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .form-control {
            border-radius: 10px;
            padding: 10px 14px;
        }

        .btn {
            border-radius: 10px;
        }

        .teacher-photo {
            width: 42px;
            height: 42px;
            object-fit: cover;
        }
    </style>
</head>

<body>

<div class="container-fluid py-4">
    <div class="page-wrapper">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="page-title mb-0">
                <i class="bi bi-people-fill"></i> Teachers
            </h4>

            <a href="create.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Add Teacher
            </a>
        </div>

        <!-- Breadcrumb -->
        <nav class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="../../../admin/dashboard.php">Home</a>
                </li>
                <li class="breadcrumb-item active">All Teachers</li>
            </ol>
        </nav>

        <!-- Search -->
        <form class="row g-3 mb-4">
            <div class="col-lg-4 col-md-6">
                <input type="text"
                       name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       class="form-control"
                       placeholder="Search by ID or name">
            </div>

            <div class="col-lg-2 col-md-3">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>

        <!-- TABLE CARD -->
        <div class="card table-card">
            <div class="card-body p-0">

                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0">

                        <thead class="table-primary text-center">
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php $i = $offset + 1; while($t = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>

                                <td class="text-center">
                                    <img src="uploads/<?= $t['photo'] ?: 'default.png' ?>"
                                         class="rounded-circle teacher-photo">
                                </td>

                                <td><?= htmlspecialchars($t['teacher_id']) ?></td>
                                <td><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></td>
                                <td><?= htmlspecialchars($t['gender']) ?></td>
                                <td><?= htmlspecialchars($t['class']) ?></td>
                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                <td><?= htmlspecialchars($t['phone']) ?></td>
                                <td><?= htmlspecialchars($t['email']) ?></td>

                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="view.php?id=<?= $t['id'] ?>"
                                           class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <a href="edit_teacher.php?id=<?= $t['id'] ?>"
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="payments.php?id=<?= $t['id'] ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-cash-coin"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="pagination justify-content-end">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link"
                           href="?page=<?= $page-1 ?>&search=<?= $search ?>">
                            Prev
                        </a>
                    </li>
                <?php endif; ?>

                <?php if($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link"
                           href="?page=<?= $page+1 ?>&search=<?= $search ?>">
                            Next
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>