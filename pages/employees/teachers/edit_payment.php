<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/auth/auth.php'; // login check
require_once __DIR__ . '../../../../app/helper/layout.php';


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
    <h4>Edit Payment</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm p-4">
        <div class="mb-3">
            <label>Teacher</label>
            <select name="teacher_id" class="form-control" required>
                <?php while ($t = $teachers->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id']==$payment['teacher_id']?'selected':'' ?>>
                        <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($payment['amount']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($payment['description']) ?></textarea>
        </div>

        <button class="btn btn-primary">Update Payment</button>
        <a href="payments.php" class="btn btn-secondary">Back</a>
    </form>
</div>
<script src="../../../../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
