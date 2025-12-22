<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Teacher ID is missing.");
}

$teacher_id = intval($_GET['id']);

// Fetch teacher from DB
$stmt = $mysqli->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found.");
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
<body class="bg-light">

<div class="container mt-5 col-md-6">
    <div class="card shadow-sm p-4">
        <h4 class="mb-4">Teacher Details</h4>

        <div class="text-center mb-4">
            <?php if (!empty($teacher['photo']) && file_exists("uploads/" . $teacher['photo'])): ?>
                <img src="uploads/<?= htmlspecialchars($teacher['photo']) ?>" class="img-thumbnail" style="max-width: 150px;">
            <?php else: ?>
                <img src="../../../../assets/images/default-avatar.png" class="img-thumbnail" style="max-width: 150px;">
            <?php endif; ?>
        </div>

        <table class="table table-bordered">
            <tr>
                <th>Full Name</th>
                <td><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></td>
            </tr>
            <tr>
                <th>Gender</th>
                <td><?= htmlspecialchars($teacher['gender']) ?></td>
            </tr>
            <tr>
                <th>Date of Birth</th>
                <td><?= htmlspecialchars($teacher['dob']) ?></td>
            </tr>
            <tr>
                <th>ID Number</th>
                <td><?= htmlspecialchars($teacher['id_no']) ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($teacher['email']) ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?= htmlspecialchars($teacher['phone']) ?></td>
            </tr>
            <tr>
                <th>Class</th>
                <td><?= htmlspecialchars($teacher['class']) ?></td>
            </tr>
            <tr>
                <th>Section</th>
                <td><?= htmlspecialchars($teacher['section']) ?></td>
            </tr>
            <tr>
                <th>Subject</th>
                <td><?= htmlspecialchars($teacher['subject']) ?></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?= htmlspecialchars($teacher['address']) ?></td>
            </tr>
            <tr>
                <th>Bio</th>
                <td><?= htmlspecialchars($teacher['bio']) ?></td>
            </tr>
        </table>

        <a href="list.php" class="btn btn-secondary mt-3">Back</a>
    </div>
</div>

<script src="../../../../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
