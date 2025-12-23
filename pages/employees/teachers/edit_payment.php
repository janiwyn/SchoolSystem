<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php';


$error = '';
$success = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Payment ID is missing.");
}
$payment_id = intval($_GET['id']);

// Fetch payment
$stmt = $mysqli->prepare("
    SELECT tp.*, t.first_name, t.last_name 
    FROM teacher_payments tp
    JOIN teachers t ON tp.teacher_id = t.id
    WHERE tp.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    die("Payment not found.");
}

// Fetch all teachers for dropdown
$teachers = $mysqli->query("SELECT id, first_name, last_name FROM teachers ORDER BY first_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id  = intval($_POST['teacher_id']);
    $amount      = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    if (!$teacher_id || !$amount) {
        $error = "Teacher and amount are required.";
    } else {
        $stmt = $mysqli->prepare("
            UPDATE teacher_payments 
            SET teacher_id=?, amount=?, description=? 
            WHERE id=?
        ");
        $stmt->bind_param("idsi", $teacher_id, $amount, $description, $payment_id);

        if ($stmt->execute()) {
            $success = "Payment updated successfully.";
        } else {
            $error = "Error updating payment: " . $stmt->error;
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
    <title><?= htmlspecialchars($title ?? 'Edit Payment') ?></title>

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
            max-width: 800px;
            margin: auto;
        }

        .form-card {
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
        }

        .page-title {
            color: #0d6efd;
            font-weight: 600;
        }

        .breadcrumb a {
            color: #0d6efd;
            text-decoration: none;
        }

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

        label {
            font-weight: 500;
            color: #495057;
        }

        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }
    </style>
</head>

<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="../../../admin/dashboard.php">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="payments.php">Payments</a>
                </li>
                <li class="breadcrumb-item active">Edit Payment</li>
            </ol>
        </nav>

        <!-- Title -->
        <h4 class="page-title mb-4">
            <i class="bi bi-pencil-square"></i> Edit Payment
        </h4>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="card form-card p-5">

            <div class="mb-4">
                <label class="form-label">Teacher</label>
                <select name="teacher_id" class="form-select" required>
                    <?php while ($t = $teachers->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$payment['teacher_id']?'selected':'' ?>>
                            <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Amount</label>
                <input type="number"
                       step="0.01"
                       name="amount"
                       class="form-control"
                       value="<?= htmlspecialchars($payment['amount']) ?>"
                       required>
            </div>

            <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea name="description"
                          class="form-control"
                          rows="3"><?= htmlspecialchars($payment['description']) ?></textarea>
            </div>

            <!-- Buttons -->
            <div class="d-flex gap-3">
                <button class="btn btn-primary">
                    <i class="bi bi-save"></i> Update Payment
                </button>

                <a href="payments.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
