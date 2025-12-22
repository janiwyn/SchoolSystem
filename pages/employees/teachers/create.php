<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php';
require_role(['admin']);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tid = 'TCH-' . rand(1000,9999);
    $fn  = $_POST['first_name'];
    $ln  = $_POST['last_name'];
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $idno = $_POST['id_no'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $class = $_POST['class'];
    $section = $_POST['section'];
    $subject = $_POST['subject'];
    $address = $_POST['address'];
    $bio = $_POST['bio'];

    $photo = '';
 $uploadDir = __DIR__ . '/uploads/'; // ensures the path is correct
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true); // create the folder if it doesn't exist
}

$filename = time() . '_' . basename($_FILES['photo']['name']);
$target = $uploadDir . $filename;

if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
    // Success
} else {
    $error = "Failed to upload photo. Check folder permissions.";
}


    $stmt = $mysqli->prepare(
        "INSERT INTO teachers 
        (teacher_id,first_name,last_name,gender,dob,id_no,email,phone,class,section,subject,address,bio,photo)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        "ssssssssssssss",
        $tid,$fn,$ln,$gender,$dob,$idno,$email,$phone,$class,$section,$subject,$address,$bio,$photo
    );

    if ($stmt->execute()) {
        $success = "Teacher added successfully";
    } else {
        $error = "Error saving teacher";
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
<div class="container mt-4 col-md-8">

    <h4 class="mb-4">Add Teacher</h4>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card shadow-sm p-4">
        <div class="row g-3">

            <div class="col-md-6">
                <input class="form-control" name="first_name" placeholder="First Name" required>
            </div>
            <div class="col-md-6">
                <input class="form-control" name="last_name" placeholder="Last Name" required>
            </div>

            <div class="col-md-6">
                <select name="gender" class="form-select">
                    <option value="">Select Gender</option>
                    <option>Male</option>
                    <option>Female</option>
                </select>
            </div>

            <div class="col-md-6">
                <input type="date" name="dob" class="form-control" placeholder="Date of Birth">
            </div>

            <div class="col-md-6">
                <input name="id_no" class="form-control" placeholder="ID Number">
            </div>
            <div class="col-md-6">
                <input name="email" type="email" class="form-control" placeholder="Email">
            </div>

            <div class="col-md-6">
                <input name="phone" class="form-control" placeholder="Phone">
            </div>
            <div class="col-md-6">
                <input name="class" class="form-control" placeholder="Class">
            </div>

            <div class="col-md-6">
                <input name="section" class="form-control" placeholder="Section">
            </div>
            <div class="col-md-6">
                <input name="subject" class="form-control" placeholder="Subject">
            </div>

            <div class="col-12">
                <textarea name="address" class="form-control" placeholder="Address" rows="2"></textarea>
            </div>

            <div class="col-12">
                <textarea name="bio" class="form-control" placeholder="Short Bio" rows="2"></textarea>
            </div>

            <div class="col-12">
                <input type="file" name="photo" class="form-control">
            </div>

            <div class="col-12 d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save Teacher</button>
                <a href="../../../app/admin/dashboard.php" class="btn btn-secondary">Back</a>
            </div>

        </div>
    </form>
</div>


</div>
</form>
</div>
</body>
</html>
