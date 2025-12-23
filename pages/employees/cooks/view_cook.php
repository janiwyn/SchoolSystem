<?php
require_once __DIR__ . '/../../../config/db.php'; // database connection
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php'; // layout helper

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Cook ID is missing.");
}

$cook_id = intval($_GET['id']);

// Fetch cook from DB
$stmt = $mysqli->prepare("SELECT * FROM cooks WHERE id = ?");
$stmt->bind_param("i", $cook_id);
$stmt->execute();
$result = $stmt->get_result();
$cook = $result->fetch_assoc();
$stmt->close();

if (!$cook) {
    die("Cook not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Cook Details') ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
        background: #f4fbff;
    }

    .page-wrapper {
        max-width: 1100px;
        margin: auto;
    }

    .profile-card {
        border: none;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(13,110,253,0.12);
    }

    .profile-header {
        background: linear-gradient(135deg, #0d6efd, #5bc0de);
        border-radius: 18px 18px 0 0;
        padding: 25px;
        color: #fff;
        text-align: center;
    }

    /* ✅ FIXED PHOTO STYLE */
    .profile-photo {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #e7f3ff;
        background: #fff;
    }

    .profile-name {
        font-weight: 600;
        color: #0d6efd;
    }

    .details-table th {
        width: 35%;
        background: #f1f8ff;
        color: #0d6efd;
        font-weight: 500;
    }

    .details-table td {
        background: #ffffff;
    }

    .btn {
        border-radius: 10px;
        padding: 10px 20px;
    }
</style>

</head>

<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Card -->
        <div class="card profile-card">

            <!-- Header -->
            <div class="profile-header">
                <h4 class="mb-0">
                    <i class="bi bi-person-badge-fill"></i> Cook Profile
                </h4>
            </div>

            <!-- Body -->
            <div class="card-body px-5 pb-5">

                <!-- Photo -->
                <div class="text-center mb-4">
                    <?php if (!empty($cook['photo']) && file_exists("uploads/" . $cook['photo'])): ?>
                        <img src="uploads/<?= htmlspecialchars($cook['photo']) ?>"
                             class="profile-photo"
                             alt="Cook Photo">
                    <?php else: ?>
                        <img src="../../../../assets/images/default-avatar.png"
                             class="profile-photo"
                             alt="Default Photo">
                    <?php endif; ?>
                </div>

                <!-- Name -->
                <h5 class="text-center profile-name mb-4">
                    <?= htmlspecialchars($cook['first_name'] . ' ' . $cook['last_name']) ?>
                </h5>

                <!-- Details -->
                <div class="table-responsive">
                    <table class="table table-bordered details-table align-middle">
                        <tr>
                            <th>Gender</th>
                            <td><?= htmlspecialchars($cook['gender']) ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?= htmlspecialchars($cook['dob']) ?></td>
                        </tr>
                        <tr>
                            <th>ID Number</th>
                            <td><?= htmlspecialchars($cook['id_no']) ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?= htmlspecialchars($cook['email']) ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?= htmlspecialchars($cook['phone']) ?></td>
                        </tr>
                        <tr>
                            <th>Section</th>
                            <td><?= htmlspecialchars($cook['section']) ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?= htmlspecialchars($cook['address']) ?></td>
                        </tr>
                        <tr>
                            <th>Bio</th>
                            <td><?= htmlspecialchars($cook['bio']) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Actions -->
                <div class="text-end mt-4">
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
