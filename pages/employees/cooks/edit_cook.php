<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php';
require_role(['admin']);

$error = '';
$success = '';

// Get cook ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Cook ID is missing.");
}
$cook_id = intval($_GET['id']);

// Fetch cook
$stmt = $mysqli->prepare("SELECT * FROM cooks WHERE id = ?");
$stmt->bind_param("i", $cook_id);
$stmt->execute();
$result = $stmt->get_result();
$cook = $result->fetch_assoc();
$stmt->close();

// Stop if cook not found
if (!$cook) {
    die("Cook not found.");
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    // $id_no = trim($_POST['id_no']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $section = trim($_POST['section']);
    $address = trim($_POST['address']);
    $bio = trim($_POST['bio']);
    $photo = '';

    // Update photo if uploaded
    $photo = $cook['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $photo);
    }

    $stmt = $mysqli->prepare(
        "UPDATE cooks SET first_name=?, last_name=?, gender=?, id_no=?, email=?, phone=?, section=?, address=?, bio=?, photo=? WHERE id=?"
    );
    $stmt->bind_param(
        "ssssssssssi",
        $fname, $lname, $gender, $id_no, $email, $phone,
        $section, $address, $bio, $photo, $cook_id
    );

    if ($stmt->execute()) {
        $success = "Cook updated successfully.";
    } else {
        $error = "Error updating cook: " . $stmt->error;
    }
    $stmt->close();
}
?>

 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Edit Cook') ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        .page-wrapper {
            max-width: 1100px;
            margin: auto;
        }

        /* Breadcrumb */
        .breadcrumb-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px 20px;
            box-shadow: 0 8px 25px rgba(13,110,253,0.08);
        }

        .breadcrumb {
            margin: 0;
            font-size: 0.95rem;
        }

        .breadcrumb a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #6c757d;
            font-weight: 500;
        }

        /* Page title */
        .page-title {
            color: #0d6efd;
            font-weight: 600;
            letter-spacing: .2px;
        }

        /* Card */
        .form-card {
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
        }

        /* Inputs */
        .form-control,
        .form-select {
            padding: 12px 14px;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 .2rem rgba(13,110,253,.15);
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 10px 22px;
            font-weight: 500;
        }

        .btn-outline-secondary:hover {
            background-color: #e9f4ff;
            color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>

<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Breadcrumb -->
        <div class="breadcrumb-card mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../../../admin/dashboard.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Cooks
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Title -->
        <h4 class="page-title mb-4">
            <i class="bi bi-pencil-square"></i> Edit Cook
        </h4>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- FORM CARD -->
        <form method="POST" enctype="multipart/form-data" class="card form-card p-5">

            <div class="row g-4">

                <div class="col-md-6">
                    <input class="form-control"
                           name="first_name"
                           value="<?= htmlspecialchars($cook['first_name']) ?>"
                           placeholder="First Name"
                           required>
                </div>

                <div class="col-md-6">
                    <input class="form-control"
                           name="last_name"
                           value="<?= htmlspecialchars($cook['last_name']) ?>"
                           placeholder="Last Name"
                           required>
                </div>

                <div class="col-md-6">
                    <select name="gender" class="form-select">
                        <option value="Male" <?= $cook['gender']=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= $cook['gender']=='Female'?'selected':'' ?>>Female</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <input name="email"
                           class="form-control"
                           value="<?= htmlspecialchars($cook['email']) ?>"
                           placeholder="Email">
                </div>

                <div class="col-md-6">
                    <input name="phone"
                           class="form-control"
                           value="<?= htmlspecialchars($cook['phone']) ?>"
                           placeholder="Phone">
        </div>

                <div class="col-md-6">
                    <input name="section"
                           class="form-control"
                           value="<?= htmlspecialchars($cook['section']) ?>"
                           placeholder="Section">
                </div>

                <div class="col-md-6">
                    <input type="file" name="photo" class="form-control">
                </div>

                <div class="col-12">
                    <textarea name="address"
                              class="form-control"
                              rows="3"
                              placeholder="Address"><?= htmlspecialchars($cook['address']) ?></textarea>
                </div>

                <div class="col-12">
                    <textarea name="bio"
                              class="form-control"
                              rows="3"
                              placeholder="Short Bio"><?= htmlspecialchars($cook['bio']) ?></textarea>
                </div>

                <!-- Buttons -->
                <div class="col-12 d-flex gap-3 mt-4">
                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Cook
                    </button>

                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>

            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
