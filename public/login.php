<?php
session_start();
require_once '../app/config/db.php'; // Make sure path to db.php is correct

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if ($email && $password) {
        // Fetch user
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                // Login success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['user']    = $user['name'];

                // Update last login
                $updateStmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../app/admin/dashboard.php");
                        break;
                    case 'principal':
                        header("Location: ../app/principal/dashboard.php");
                        break;
                    case 'bursar':
                        header("Location: ../app/finance/dashboard.php");
                        break;
                    default:
                        header("Location: ../app/finance/dashboard.php");
                        break;
                }
                exit();

            } else {
                $error = "Invalid email or password";
            }

            $stmt->close();
        } else {
            $error = "Database error: " . $mysqli->error;
        }
    } else {
        $error = "Please enter email and password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School System Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-4">
    <div class="card shadow-sm">
        <div class="card-header text-center bg-primary text-white">
            <h4 class="mb-0">Login</h4>
        </div>
        <div class="card-body">

            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <p class="mt-3 text-center">
                Don't have an account? <a href="register.php" class="text-decoration-none">Register</a>
            </p>

        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
