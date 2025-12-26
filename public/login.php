<?php
ob_start();
session_start();
require_once '../app/config/db.php'; // Make sure path to db.php is correct

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username && $password) {

     // Fetch user
$query = "SELECT id, name, username, password, role 
          FROM users 
          WHERE username = ? AND status = 1 
          LIMIT 1";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

        if ($user && password_verify($password, $user['password'])) {

            // Store session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']    = strtolower(trim($user['role']));

            // Redirect based on role
            switch ($_SESSION['role']) {
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
                    $error = "Unauthorized role";
            }
            exit;

        } else {
            $error = "Invalid username or password";
        }

    } else {
        $error = "Please enter username and password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>School System Login</title>
  
  <!-- Favicon - School Logo in Browser Tab -->
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <link rel="shortcut icon" href="../assets/images/logo.png">
  <link rel="apple-touch-icon" href="../assets/images/logo.png">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

    .login-container {
      width: 100%;
      max-width: 420px;
    }

    .login-card {
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

    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .login-logo {
      display: inline-block;
      margin-bottom: 20px;
    }

    .login-logo img {
      max-width: 100px;
      height: auto;
      display: block;
    }

    .login-header h5 {
      font-size: 13px;
      color: #7f8c8d;
      margin: 0;
      font-weight: 500;
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

    .btn-login {
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

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .login-footer {
      text-align: center;
      margin-top: 18px;
      padding-top: 18px;
      border-top: 1px solid #ecf0f1;
    }

    .login-footer p {
      font-size: 13px;
      color: #7f8c8d;
      margin: 0;
    }

    .login-footer a {
      color: #3498db;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .login-footer a:hover {
      color: #2980b9;
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-card">
    <!-- Header with Logo -->
    <div class="login-header">
      <div class="login-logo">
        <img src="../assets/images/logo.png" alt="Business Logo">
      </div>
      <h5>Secure Login</h5>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn-login">Login</button>
    </form>

    <!-- Footer -->
    <div class="login-footer">
      <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
  </div>
</div>

</body>
</html>

<?php ob_end_flush(); ?>

