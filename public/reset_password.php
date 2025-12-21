<?php
require_once '/../app/config/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

$stmt = $mysqli->prepare(
    "SELECT * FROM password_resets
     WHERE token = ? AND used = 0 AND expires_at > NOW()
     LIMIT 1"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();

if (!$reset) {
    die("Invalid or expired reset token");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $reset['user_id']);
        $stmt->execute();

        $stmt = $mysqli->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->bind_param("i", $reset['id']);
        $stmt->execute();

        $success = "Password reset successful. You can login now.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-4">
    <div class="card shadow">
        <div class="card-body">

            <h5 class="text-center">Reset Password</h5>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <a href="login.php" class="btn btn-success w-100">Login</a>
            <?php else: ?>
                <form method="POST">
                    <input type="password" name="password" class="form-control mb-3" required placeholder="New password">
                    <input type="password" name="confirm" class="form-control mb-3" required placeholder="Confirm password">
                    <button class="btn btn-primary w-100">Reset Password</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
