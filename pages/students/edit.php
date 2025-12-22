<?php
require_once __DIR__ . '/../../app/auth/auth.php';
require_role(['admin','principal']);
require_once __DIR__ . '/../../app/config/db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Fetch student
$stmt = $mysqli->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fn  = trim($_POST['first_name']);
    $ln  = trim($_POST['last_name']);
    $gen = $_POST['gender'];
    $cls = trim($_POST['class']);
    $status = $_POST['status'];

    if (!$fn || !$ln || !$gen) {
        $error = "Required fields missing";
    } else {

        $update = $mysqli->prepare(
            "UPDATE students 
             SET first_name=?, last_name=?, gender=?, class=?, status=? 
             WHERE id=?"
        );
        $update->bind_param("sssssi", $fn, $ln, $gen, $cls, $status, $id);

        if ($update->execute()) {

            // Audit log
            $desc = "Updated student {$student['first_name']} {$student['last_name']}";
            $log = $mysqli->prepare(
                "INSERT INTO audit_logs 
                 (user_id, action, module, record_id, description)
                 VALUES (?, 'UPDATE', 'students', ?, ?)"
            );
            $log->bind_param("iis", $_SESSION['user_id'], $id, $desc);
            $log->execute();

            $success = "Student updated successfully";
        } else {
            $error = "Update failed";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-6">

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Student</h5>
        </div>
        <div class="card-body">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>

                <div class="mb-3">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control"
                           value="<?= htmlspecialchars($student['first_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control"
                           value="<?= htmlspecialchars($student['last_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="Male" <?= $student['gender']=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= $student['gender']=='Female'?'selected':'' ?>>Female</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Class</label>
                    <input type="text" name="class" class="form-control"
                           value="<?= htmlspecialchars($student['class']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $student['status']=='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $student['status']=='inactive'?'selected':'' ?>>Inactive</option>
                        <option value="graduated" <?= $student['status']=='graduated'?'selected':'' ?>>Graduated</option>
                    </select>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update
                    </button>
                                    <a href="../../app/admin/dashboard.php" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Back
</a>
                </div>

            </form>

        </div>
    </div>

</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Optional: form validation -->
<script>
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>

</body>
</html>

