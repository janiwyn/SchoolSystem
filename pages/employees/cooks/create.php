<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check
require_once __DIR__ . '../../../../app/helper/layout.php';
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
    <h4>Add Cook</h4>

    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card shadow-sm p-4">
        <input class="form-control mb-2" name="first_name" placeholder="First Name" required>
        <input class="form-control mb-2" name="last_name" placeholder="Last Name" required>
        <select name="gender" class="form-control mb-2">
            <option>Male</option><option>Female</option>
        </select>
        <input name="id_no" class="form-control mb-2" placeholder="ID Number" required>
        <input name="email" class="form-control mb-2" placeholder="Email">
        <input name="phone" class="form-control mb-2" placeholder="Phone">
        <input name="section" class="form-control mb-2" placeholder="Section">
        <textarea name="address" class="form-control mb-2" placeholder="Address"></textarea>
        <textarea name="bio" class="form-control mb-2" placeholder="Short Bio"></textarea>
        <input type="file" name="photo" class="form-control mb-3">

        <button class="btn btn-primary">Save Cook</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
    </form>
</div>
