<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_once __DIR__ . '/../helper/layout.php';

require_role(['admin']);

$payment_id = $_GET['id'] ?? 0;
$stmt = $mysqli->prepare("SELECT * FROM cook_payments WHERE id=?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) die("Payment not found");

// Fetch cooks for dropdown
$cooksResult = $mysqli->query("SELECT id, first_name, last_name FROM cooks ORDER BY first_name ASC");

$error = $success = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $cook_id = $_POST['cook_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = $_POST['description'] ?? '';

    if (!$cook_id || !$amount) $error="Please select a cook and enter amount";
    else {
        $stmt = $mysqli->prepare("UPDATE cook_payments SET cook_id=?, amount=?, description=? WHERE id=?");
        $stmt->bind_param("idsi", $cook_id, $amount, $description, $payment_id);
        if ($stmt->execute()) $success="Payment updated successfully";
        else $error="Failed to update: ".$mysqli->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Cook Payment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background: #f4fbff; }
    .page-wrapper { max-width: 700px; margin: auto; }
    .card { border-radius: 18px; box-shadow: 0 15px 40px rgba(13,110,253,0.12); padding: 30px; }
    .btn { border-radius: 10px; padding: 10px 20px; }
    .form-control, .form-select { padding: 12px 14px; border-radius: 10px; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
</style>
</head>
<body>
<div class="container py-5">
<div class="page-wrapper">

    <h4 class="mb-4 text-primary"><i class="bi bi-pencil-square"></i> Edit Cook Payment</h4>

    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form method="POST" class="card">
        <div class="mb-3">
            <label class="form-label">Select Cook</label>
            <select name="cook_id" class="form-select" required>
                <?php while($cook = $cooksResult->fetch_assoc()): ?>
                    <option value="<?= $cook['id'] ?>" <?= $cook['id']==$payment['cook_id']?'selected':'' ?>>
                        <?= htmlspecialchars($cook['first_name'].' '.$cook['last_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" value="<?= $payment['amount'] ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($payment['description']) ?></textarea>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Update Payment</button>
            <a href="payments.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </form>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
