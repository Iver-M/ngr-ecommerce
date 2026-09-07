<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
require 'config/db.php';

$error = ""; 
$success = ""; 

// Define login attempt settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 1);

function log_action($action, $details = null, $user_id = null, $username = null) {
    global $pdo;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
}

// Check for session expiration message
if (isset($_GET['reason']) && $_GET['reason'] === 'session_expired') {
    $error = "Your session has expired due to inactivity. Please log in again.";
}

// Handle form submission for login (email + password)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // --- 1. CHECK IF ACCOUNT IS LOCKED ---
    if ($user && $user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
        $error = "Your account is temporarily locked due to too many failed login attempts. Please try again later.";
    } elseif ($user && password_verify($password, $user['password'])) {
        // --- 3. HANDLE LOGIN SUCCESS ---
        // Reset failed attempts on successful login
        $stmt_reset = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?");
        $stmt_reset->execute([$user['id']]);

        // Proceed with the rest of the login logic
        if ($user['status'] === 'suspended') {
            $error = "Your account has been suspended. Please contact customer support for assistance.";
        } elseif ($user['verified'] == 0) {
            $error = "Your email is not verified. Please check your email to verify your account.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . " " . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            log_action('Login Success', 'User successfully logged in.', $user['id'], $user['email']);

            // Redirect based on user role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        }
    } else {
        // --- 2. HANDLE LOGIN FAILURE ---
        if ($user) {
            $attempts = $user['failed_login_attempts'] + 1;
            $stmt_fail = $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
            $stmt_fail->execute([$attempts, $user['id']]);

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockout_interval = 'INTERVAL ' . LOCKOUT_TIME_MINUTES . ' MINUTE';
                $stmt_lock = $pdo->prepare("UPDATE users SET lockout_until = DATE_ADD(NOW(), $lockout_interval) WHERE id = ?");
                $stmt_lock->execute([$user['id']]);
                $error = "Your account has been locked for " . LOCKOUT_TIME_MINUTES . " minutes due to too many failed login attempts.";
            } else {
                $error = "Invalid email or password."; 
                log_action('Login Failed', 'Invalid credentials for email.', null, $email);
            }
        } else {
            $error = "Invalid email or password."; 
            log_action('Login Failed', 'Invalid credentials for email.', null, $email);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - NewGrounds</title>
    <link rel="stylesheet" href="assets/login_style.css">
</head>
<body>

<div class="container">
  <div class="left">
    <div class="logo">
      <a class="logreg-brand" href="index.php">
        <img src="uploads/images/logo/NGLogo.png" alt="logo" height="50">
      </a>
      <span>NewGrounds</span>
    </div>
    <h1>LOGIN</h1>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success-msg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />

      <label for="password">Password</label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required />
        <span class="toggle-password" onclick="togglePassword()"></span>
      </div>

      <a href="forgot_password.php" class="forgot">FORGOT PASSWORD</a>

      <button type="submit">LOGIN</button>
    </form>

    <p style="margin-top:15px; font-size:12px;">
      Don't have an account? <a href="register.php" style="color:#a0b4ff;">Register here</a>
    </p>
  </div>
  <div class="right"></div>
</div>

<script>
// Function to toggle password visibility
function togglePassword() {
    const passInput = document.getElementById('password');
    passInput.type = passInput.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
