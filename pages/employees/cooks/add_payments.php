<?php
require_once __DIR__ . '/../../../config/db.php'; // Adjust path to your DB
require_once __DIR__ . '/../../../auth/auth.php'; // Login check
require_once __DIR__ . '/../helper/layout.php';

require_role(['admin']); // Optional: restrict to admin

$error = '';
$success = '';

// Fetch all cooks for the dropdown
$cooksResult = $mysqli->query("SELECT id, first_name, last_name, photo FROM cooks ORDER BY first_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cook_id = $_POST['cook_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = $_POST['description'] ?? '';

    // Basic validation
    if (!$cook_id || !$amount) {
        $error = "Please select a cook and enter an amount.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO cook_payments (cook_id, amount, description) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $cook_id, $amount, $description);
        if ($stmt->execute()) {
            $success = "Payment added successfully!";
        } else {
            $error = "Failed to add payment: " . $mysqli->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Cook Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
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

        .btn {
            border-radius: 10px;
            padding: 10px 20px;
        }

        .breadcrumb a {
            text-decoration: none;
        }
    </style>
</head>

<body>
<div class="container py-5">
    <div class="page-wrapper">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../../../admin/dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Add Cook Payment</li>
            </ol>
        </nav>

        <!-- Title -->
        <h4 class="mb-4 text-primary"><i class="bi bi-cash-stack"></i> Add Cook Payment</h4>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <form method="POST" class="card form-card p-5">
            <div class="mb-3">
                <label class="form-label">Select Cook</label>
                <select name="cook_id" class="form-select" required>
                    <option value="">-- Choose Cook --</option>
                    <?php while ($cook = $cooksResult->fetch_assoc()): ?>
                        <option value="<?= $cook['id'] ?>">
                            <?= htmlspecialchars($cook['first_name'] . ' ' . $cook['last_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Payment</button>
                <a href="payments.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
