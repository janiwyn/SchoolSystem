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
                    header("Location: ../app/bursar/dashboard.php");
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1c1c1c, #2a5298);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem;
      box-shadow: 0 10px 35px rgba(0,0,0,0.25);
      max-width: 420px;
      width: 100%;
    }
    .form-control {
      border-radius: 50px;
      padding: 0.7rem 1rem;
    }
    .btn-school {
      background: linear-gradient(90deg, #2a5298, #1e3c72);
      color: #fff;
      font-weight: 600;
      border-radius: 50px;
    }
  </style>
</head>
<body>

<div class="login-card">

  <div class="text-center mb-3">
    <h4>School Management System</h4>
    <p class="text-muted">Secure Login</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger text-center">
      <?= htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label class="fw-semibold">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="fw-semibold">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>

    <button class="btn btn-school w-100">Login</button>
  </form>

  <div class="text-center mt-3">
    <p>Don't have an account? <a href="register.php">Register</a></p>
  </div>

</div>

</body>
</html>

<?php ob_end_flush(); ?>

