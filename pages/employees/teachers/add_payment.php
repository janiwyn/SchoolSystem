<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check

$error = '';
$success = '';

// Fetch all teachers for the dropdown
$teachers = $mysqli->query("SELECT id, first_name, last_name, id_no FROM teachers ORDER BY first_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id  = intval($_POST['teacher_id']);
    $amount      = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    if (!$teacher_id || !$amount) {
        $error = "Teacher and amount are required.";
    } else {
        // Insert payment
        $stmt = $mysqli->prepare(
            "INSERT INTO teacher_payments (teacher_id, amount, payment_date, description) VALUES (?, ?, NOW(), ?)"
        );
        $stmt->bind_param("ids", $teacher_id, $amount, $description);

        if ($stmt->execute()) {
            // Get inserted payment ID
            $payment_id = $mysqli->insert_id;

            // Audit log
            $log = $mysqli->prepare(
                "INSERT INTO audit_logs (user_id, action, module, record_id, description) VALUES (?, 'CREATE', 'teacher_payments', ?, ?)"
            );
            $desc = "Added payment of $amount for teacher ID $teacher_id";
            $log->bind_param("iis", $_SESSION['user_id'], $payment_id, $desc);
            $log->execute();
            $log->close();

            $success = "Payment added successfully.";
        } else {
            $error = "Error saving payment: " . $stmt->error;
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
<div class="container mt-4 col-md-8">
<body class="bg-light">

<div class="container mt-5 col-md-6">
    <div class="card shadow-sm p-4">
        <h4 class="mb-4">Add Teacher Payment</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="teacher_id" class="form-label">Teacher</label>
                <select name="teacher_id" id="teacher_id" class="form-select" required>
                    <option value="">-- Select Teacher --</option>
                    <?php while ($t = $teachers->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['id_no'] . ')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount</label>
                <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control" placeholder="Optional"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Add Payment</button>
            <a href="payments.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
</div>

<script src="../../../../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
