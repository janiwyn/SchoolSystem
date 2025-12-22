<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

$error = '';
$success = '';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Teacher ID is missing.");
}
$teacher_id = intval($_GET['id']);

// Fetch teacher
$stmt = $mysqli->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $gender     = $_POST['gender'];
    $dob        = $_POST['dob'];
    $email      = $_POST['email'];
    $phone      = $_POST['phone'];
    $class      = $_POST['class'];
    $section    = $_POST['section'];
    $subject    = $_POST['subject'];
    $address    = $_POST['address'];
    $bio        = $_POST['bio'];

    // Update photo if uploaded
    $photo = $teacher['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $photo);
    }

    $stmt = $mysqli->prepare(
        "UPDATE teachers SET first_name=?, last_name=?, gender=?, dob=?, email=?, phone=?, class=?, section=?, subject=?, address=?, bio=?, photo=? WHERE id=?"
    );
    $stmt->bind_param(
        "ssssssssssssi",
        $first_name, $last_name, $gender, $dob, $email, $phone,
        $class, $section, $subject, $address, $bio, $photo, $teacher_id
    );

    if ($stmt->execute()) {
        $success = "Teacher updated successfully.";
    } else {
        $error = "Error updating teacher: " . $stmt->error;
    }
    $stmt->close();
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

<body>
<div class="container mt-5 col-md-6">
    <h4>Edit Teacher</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card shadow-sm p-4">
        <input class="form-control mb-2" name="first_name" value="<?= htmlspecialchars($teacher['first_name']) ?>" placeholder="First Name" required>
        <input class="form-control mb-2" name="last_name" value="<?= htmlspecialchars($teacher['last_name']) ?>" placeholder="Last Name" required>
        <select name="gender" class="form-control mb-2">
            <option <?= $teacher['gender']=='Male'?'selected':'' ?>>Male</option>
            <option <?= $teacher['gender']=='Female'?'selected':'' ?>>Female</option>
        </select>
        <input type="date" name="dob" value="<?= htmlspecialchars($teacher['dob']) ?>" class="form-control mb-2">
        <input name="email" class="form-control mb-2" value="<?= htmlspecialchars($teacher['email']) ?>" placeholder="Email">
        <input name="phone" class="form-control mb-2" value="<?= htmlspecialchars($teacher['phone']) ?>" placeholder="Phone">
        <input name="class" class="form-control mb-2" value="<?= htmlspecialchars($teacher['class']) ?>" placeholder="Class">
        <input name="section" class="form-control mb-2" value="<?= htmlspecialchars($teacher['section']) ?>" placeholder="Section">
        <input name="subject" class="form-control mb-2" value="<?= htmlspecialchars($teacher['subject']) ?>" placeholder="Subject">
        <textarea name="address" class="form-control mb-2" placeholder="Address"><?= htmlspecialchars($teacher['address']) ?></textarea>
        <textarea name="bio" class="form-control mb-2" placeholder="Short Bio"><?= htmlspecialchars($teacher['bio']) ?></textarea>
        <input type="file" name="photo" class="form-control mb-2">

        <button class="btn btn-primary mt-2">Update Teacher</button>
        <a href="list.php" class="btn btn-secondary mt-2">Back</a>
    </form>
</div>
<script src="../../../../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
