<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php';
require_role(['admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    $id_no = trim($_POST['id_no']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $section = trim($_POST['section']);
    $address = trim($_POST['address']);
    $bio = trim($_POST['bio']);
    $photo = '';

    // Validate required fields
    if (!$fname || !$lname || !$gender || !$id_no) {
        $error = "First name, last name, gender, and ID number are required.";
    }

    // Check for duplicate email BEFORE inserting
    if (!$error) {
        $check = $mysqli->prepare("SELECT id FROM cooks WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This email is already registered!";
        }
        $check->close();
    }

    // Handle photo upload
    if (!$error && isset($_FILES['photo']) && $_FILES['photo']['name'] != '') {
        $uploadDir = __DIR__ . '/uploads/'; 
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $photo = time() . '_' . basename($_FILES['photo']['name']);
        $target = $uploadDir . $photo;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
            $error = "Failed to upload photo. Check folder permissions.";
        }
    }

    // Insert cook
    if (!$error) {
        $stmt = $mysqli->prepare("
            INSERT INTO cooks (first_name, last_name, gender, id_no, email, phone, section, address, bio, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssssss", $fname, $lname, $gender, $id_no, $email, $phone, $section, $address, $bio, $photo);

        if ($stmt->execute()) {
            $success = "Cook added successfully.";
        } else {
            $error = "Error adding cook: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Add Cook') ?></title>

    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
            font-family: "Segoe UI", sans-serif;
        }

        .page-wrapper {
            max-width: 1000px; /* Make the card wider */
            margin: auto;
        }

        .form-card {
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
            padding: 50px; /* Increased padding */
        }

        .page-title {
            color: #0d6efd;
            font-weight: 600;
        }

        .form-control,
        .form-select,
        textarea {
            padding: 14px 16px;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 .25rem rgba(13,110,253,.2);
        }

        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
        }
    </style>
</head>

<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Title -->
        <h3 class="page-title mb-4"><i class="bi bi-person-plus-fill"></i> Add Cook</h3>

        <!-- Alerts -->
        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <form method="POST" enctype="multipart/form-data" class="card form-card">

            <div class="row g-4">

                <div class="col-md-6">
                    <input class="form-control" name="first_name" placeholder="First Name" required>
                </div>

                <div class="col-md-6">
                    <input class="form-control" name="last_name" placeholder="Last Name" required>
                </div>

                <div class="col-md-6">
                    <select name="gender" class="form-select">
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <input name="id_no" class="form-control" placeholder="ID Number" required>
                </div>

                <div class="col-md-6">
                    <input name="email" type="email" class="form-control" placeholder="Email">
                </div>

                <div class="col-md-6">
                    <input name="phone" class="form-control" placeholder="Phone">
                </div>

                <div class="col-md-12">
                    <input name="section" class="form-control" placeholder="Section">
                </div>

                <div class="col-md-12">
                    <textarea name="address" class="form-control" placeholder="Address" rows="3"></textarea>
                </div>

                <div class="col-md-12">
                    <textarea name="bio" class="form-control" placeholder="Short Bio" rows="3"></textarea>
                </div>

                <div class="col-md-12">
                    <input type="file" name="photo" class="form-control">
                </div>

                <div class="col-md-12 d-flex gap-3 mt-4">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Save Cook</button>
                    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>

            </div>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
