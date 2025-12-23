<?php
session_start();
require_once '../app/config/db.php';

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);
    $role     = $_POST['role'];   
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (!$name || !$email || !$role || !$password || !$confirm) {
        $error = "All fields are required";
    } elseif(!in_array($role, ['admin', 'principal', 'bursar'])){
         $error = "Invalid role selected";
    } elseif ($password !== $confirm) {
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
                    $message = "Registration successful! You can now <a href='login.php' class='text-primary fw-bold'>login here</a>.";
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
    <title>Create Account - School System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a3a52 0%, #2c5282 50%, #1a3a52 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 450px;
        }

        .register-card {
            background: white;
            border-radius: 16px;
            padding: 35px 30px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-logo {
            display: inline-block;
            margin-bottom: 16px;
        }

        .register-logo img {
            max-width: 100px;
            height: auto;
            display: block;
        }

        .register-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1a3a52;
            margin-bottom: 6px;
        }

        .register-header p {
            font-size: 13px;
            color: #7f8c8d;
            margin: 0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: #3498db;
            background-color: white;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            outline: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 13px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .btn-register {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 6px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .register-footer {
            text-align: center;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #ecf0f1;
        }

        .register-footer p {
            font-size: 13px;
            color: #7f8c8d;
            margin: 0;
        }

        .register-footer a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-footer a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232c3e50' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-card">
        <!-- Header with Logo -->
        <div class="register-header">
            <div class="register-logo">
                <img src="../assets/images/logo.png" alt="Business Logo">
            </div>
            <h2>Create Account</h2>
            <p>Register to access the Business System</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" required>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Create password" required>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control" placeholder="Confirm password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">Select role</option>
                        <option value="admin">Admin</option>
                        <option value="principal">Principal</option>
                        <option value="bursar">Bursar</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <!-- Footer -->
        <div class="register-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
