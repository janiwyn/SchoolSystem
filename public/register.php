<?php
session_start();
require_once '../config/db.php';

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']); // Using email as username
    $role     = $_POST['role'];   
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (!$name || !$email || !$role || !$password || !$confirm) {
        $error = "All fields are required";
    } elseif(!in_array($role, ['admin', 'principal', 'bursar'])){
         $error = "Invalid role selected";

    } 
    
    elseif ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {
        // Hash the password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "Email already exists";
        } else {
            // Insert new user
            $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, role, username) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $name, $email, $hash, $role, $username);
                if ($stmt->execute()) {
                    $message = "Registration successful! You can now ";
                } else {
                    $error = "Something went wrong: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Database error: " . $mysqli->error;
            }
        }

        $checkStmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 col-md-5">
    <div class="card shadow-sm">
        <div class="card-header text-center bg-primary text-white">
            <h4 class="mb-0">Register</h4>
        </div>
        <div class="card-body">

            <?php if (!empty($message)) : ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" required>
                </div>

                  <div class="mb-3">
                    <label for="username" class="form-label">UserName</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>

                 <div class="mb-3">
                    <label>Register As</label>
                    <select name="role" class="form-control" required>
                        <option value="">Select role</option>
                        <option value="admin">Admin</option>
                        <option value="principal">Principal</option>
                        <option value="bursar">Bursar</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>

                <div class="mb-3">
                    <label for="confirm" class="form-label">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control" placeholder="Confirm password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>

            <p class="mt-3 text-center">
                Already have an account? <a href="login.php" class="text-decoration-none">Login</a>
            </p>

        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
