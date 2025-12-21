<?php
echo __DIR__ . '/../app/config/db.php';
echo "<br>";
echo realpath(__DIR__ . '/../app/config/db.php');
exit;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $mysqli->prepare(
            "INSERT INTO password_resets (user_id, token, expires_at)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("isss", $user['id'], $token, $expires);
        $stmt->execute();

        // TEMP: show reset link (later email/SMS)
        $message = "Reset link: <br>
        <a href='reset-password.php?token=$token'>Reset Password</a>";
    } else {
        $message = "If the email exists, a reset link has been generated.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-4">
    <div class="card shadow">
        <div class="card-body">
            <h5 class="text-center">Forgot Password</h5>

            <?php if ($message): ?>
                <div class="alert alert-info"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="email" name="email" class="form-control mb-3" required placeholder="Enter your email">
                <button class="btn btn-primary w-100">Generate Reset</button>
            </form>

            <a href="login.php" class="d-block text-center mt-3">Back to login</a>
        </div>
    </div>
</div>

</body>
</html>
