<?php
session_start();
require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/login_throttle.php';

$error = '';
$success = '';
$email = '';
if (isset($_GET['reason']) && $_GET['reason'] === 'session_expired') {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    try {
        $limit = login_throttle_reserve(
            __DIR__ . '/../../config/login-attempts.json',
            $email,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $limit['retry_after']);
            $error = 'Too many login attempts. Please try again later.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $password !== '' && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                header('Location: ' . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'index.php'));
                exit;
            }
            $error = 'Invalid email or password.';
        }
    } catch (Throwable $e) {
        error_log('Login failed: ' . get_class($e));
        http_response_code(503);
        $error = 'Login is temporarily unavailable. Please try again later.';
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
      <div class="error-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

    <?php endif; ?>


    <?php if ($success): ?>

      <div class="success-msg"><?= htmlspecialchars($success) ?></div>

    <?php endif; ?>


    <form method="POST" action="">

      <label for="email">Email Address</label>

      <input
        type="email"
        id="email"
        name="email"
        required
        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
      />


      <label for="password">Password</label>

      <div class="password-wrapper">

        <input
          type="password"
          id="password"
          name="password"
          required
        />

        <span
          class="toggle-password"
          onclick="togglePassword()">
        </span>

      </div>


      <a href="forgot_password.php" class="forgot">
        FORGOT PASSWORD
      </a>


      <button type="submit">
        LOGIN
      </button>

    </form>


    <p style="margin-top:15px; font-size:12px;">

      Don't have an account?

      <a
        href="register.php"
        style="color:#a0b4ff;">

        Register here

      </a>

    </p>

  </div>


  <div class="right"></div>

</div>


<script>

// Function to toggle password visibility

function togglePassword() {

    const passInput =
        document.getElementById('password');

    passInput.type =
        passInput.type === 'password'
        ? 'text'
        : 'password';

}

</script>

</body>
</html>