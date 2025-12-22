<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_once __DIR__ . '/../helper/layout.php';

require_role(['admin']);

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cooks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
        }

        .page-title {
            color: #0d6efd;
            font-weight: 600;
        }

        .cook-card {
            border: none;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(13,110,253,0.08);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .cook-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(13,110,253,0.15);
        }

        .cook-photo {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #e7f3ff;
        }

        .badge-blue {
            background: #e7f3ff;
            color: #0d6efd;
            font-weight: 500;
        }

        .search-box {
            border-radius: 10px;
            border: 1px solid #cfe2ff;
        }
    </style>
</head>

<body>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="page-title mb-0">
            <i class="bi bi-people-fill"></i> Cooks
        </h4>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control search-box"
                   placeholder="Search by ID or name"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </form>

    <!-- Cards Grid -->
    <div class="row g-4">

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card cook-card h-100 text-center p-3">

                        <div class="mb-3">
                            <img src="../../../<?= htmlspecialchars($row['photo']) ?>"
                                 class="cook-photo"
                                 alt="Cook Photo">
                        </div>

                        <h6 class="fw-bold mb-1">
                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                        </h6>

                        <span class="badge badge-blue mb-2">
                            <?= htmlspecialchars($row['section']) ?>
                        </span>

                        <p class="small text-muted mb-1">
                            <i class="bi bi-card-text"></i> ID: <?= htmlspecialchars($row['id_no']) ?>
                        </p>

                        <p class="small text-muted mb-3">
                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($row['phone']) ?>
                        </p>

                        <div class="d-flex justify-content-center gap-2 mt-auto">
                            <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="delete.php?id=<?= $row['id'] ?>"
                               class="btn btn-outline-danger btn-sm"
                               onclick="return confirm('Delete this cook?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    No cooks found.
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
