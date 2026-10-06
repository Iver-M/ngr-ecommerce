<?php
session_start();
require __DIR__ . '/../../config/db.php';

$error = ""; 
$success = ""; 

/*
|--------------------------------------------------------------------------
| INTENTIONALLY VULNERABLE LAB VERSION
|--------------------------------------------------------------------------
| Use only with a separate dummy database/table on localhost, XAMPP,
| Laragon, or an isolated VM.
|
| DO NOT replace the publicly accessible NewGrounds login page with this.
|--------------------------------------------------------------------------
*/

// Check for session expiration message
if (isset($_GET['reason']) && $_GET['reason'] === 'session_expired') {
    $error = "Your session has expired due to inactivity. Please log in again.";
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    /*
    |--------------------------------------------------------------------------
    | INTENTIONALLY NOT SANITIZED
    |--------------------------------------------------------------------------
    */

    $email = $_POST['email'];
    $password = $_POST['password'];


    /*
    |--------------------------------------------------------------------------
    | INTENTIONAL SQL INJECTION VULNERABILITY
    |--------------------------------------------------------------------------
    |
    | Your original secure version:
    |
    | $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    | $stmt->execute([$email]);
    |
    | This lab version directly inserts the input into SQL.
    |
    */

    $sql = "SELECT * FROM users
            WHERE email = '$email'
            LIMIT 1";

    try {

        $stmt = $pdo->query($sql);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

		 if (!$user || !password_verify($password, $user['password'])) {
            $user = false;
        }
        
        if ($user) {

            /*
            |--------------------------------------------------------------------------
            | LOGIN SUCCESS
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user_name'] =
                $user['first_name'] . " " . $user['last_name'];

            $_SESSION['user_email'] = $user['email'];

            $_SESSION['user_role'] = $user['role'];

            $_SESSION['last_activity'] = time();


            /*
            |--------------------------------------------------------------------------
            | Redirect based on role
            |--------------------------------------------------------------------------
            */

            if ($user['role'] === 'admin') {

                header("Location: admin_dashboard.php");

            } else {

                header("Location: index.php");

            }

            exit();


        } else {

            /*
            |--------------------------------------------------------------------------
            | INTENTIONAL REFLECTED XSS VULNERABILITY
            |--------------------------------------------------------------------------
            |
            | Password input is intentionally reflected into the response.
            |
            | The HTML below also intentionally prints $error without
            | htmlspecialchars().
            |
            */

            $error = "Invalid email or password. " . $password;

        }


    } catch (PDOException $e) {

        $error = "Invalid email or password.";

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

      <!--
          INTENTIONALLY VULNERABLE TO XSS

          Your original secure version was:

          <div class="error-msg">
              <?= htmlspecialchars($error) ?>
          </div>

          For the laboratory version, htmlspecialchars()
          is intentionally removed.
      -->

      <div class="error-msg"><?= $error ?></div>

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
        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
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