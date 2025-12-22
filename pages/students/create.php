<?php
require_once __DIR__ . '/../../app/auth/auth.php';
require_role(['admin','principal']);
require_once __DIR__ . '/../../app/config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $adm  = trim($_POST['admission_no']);
    $fn   = trim($_POST['first_name']);
    $ln   = trim($_POST['last_name']);
    $gen  = $_POST['gender'];
    $cls  = trim($_POST['class']);

    if (!$adm || !$fn || !$ln || !$gen) {
        $error = "All required fields must be filled";
    } else {

        // Check if admission number exists
        $check = $mysqli->prepare("SELECT id FROM students WHERE admission_no = ? LIMIT 1");
        $check->bind_param("s", $adm);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Admission number already exists!";
            $check->close();
        } else {
            $check->close();

            // Insert student
            $stmt = $mysqli->prepare(
                "INSERT INTO students (admission_no, first_name, last_name, gender, class, status) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $status = 'active';
            $stmt->bind_param("ssssss", $adm, $fn, $ln, $gen, $cls, $status);

            if ($stmt->execute()) {

                // Audit log
                $insert_id = $stmt->insert_id;
                $desc = "Added student $fn $ln";
                $user_id = $_SESSION['user_id'];

                $log = $mysqli->prepare(
                    "INSERT INTO audit_logs (user_id, action, module, record_id, description)
                     VALUES (?, 'CREATE', 'students', ?, ?)"
                );
                $log->bind_param("iis", $user_id, $insert_id, $desc);
                $log->execute();
                $log->close();

                $success = "Student added successfully";
            } else {
                $error = "Database error: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Student</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-6">

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-person-plus"></i> Add Student</h5>
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
                    <label class="form-label">Admission No <span class="text-danger">*</span></label>
                    <input type="text" name="admission_no" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Class</label>
                    <input type="text" name="class" class="form-control">
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Student
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

<!-- Optional: Form validation script -->
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
