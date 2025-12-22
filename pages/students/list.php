<?php
require_once __DIR__ . '/../../app/auth/auth.php';
require_role(['admin','principal']);
require_once __DIR__ . '/../../app/config/db.php';

$result = $mysqli->query("SELECT * FROM students");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Students</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Students</h4>
            <small class="text-muted">Manage registered students</small>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Student
        </a>
    </div>

    <!-- Card -->
    <div class="card shadow-sm border-0">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if ($result->num_rows > 0): ?>
                        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['admission_no']) ?></td>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($row['gender']) ?></td>
                            <td><?= htmlspecialchars($row['class']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="edit.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <a href="delete.php?id=<?= $row['id'] ?>"
                                   onclick="return confirm('Delete this student?')"
                                   class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-people fs-3 d-block mb-2"></i>
                                No students found
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
