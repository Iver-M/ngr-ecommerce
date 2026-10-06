<?php
session_start();
require __DIR__ . "/../../config/db.php";
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';
require __DIR__ . '/../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = ""; 
$success = ""; 

// Handle form submission for Forgot Password (email address)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Fetch user from the database based on the provided email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate a unique password reset token (can use a random string)
        $token = bin2hex(random_bytes(16));

        // Store the token in the database (with an expiry time)
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user['id'], $token]);

        // Send the reset email with the token link
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = app_setting('SMTP_HOST', 'smtp.sendgrid.net');  // SendGrid SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = app_setting('SMTP_USERNAME', 'apikey'); 
            $mail->Password = app_setting('SENDGRID_API_KEY');  // Replace with your SendGrid API key
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) app_setting('SMTP_PORT', 587);

            $mail->setFrom(app_setting('MAIL_FROM'), app_setting('MAIL_FROM_NAME', 'NewGrounds'));
            $mail->addAddress($email, $user['first_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $resetLink = rtrim(app_setting('SITE_URL'), '/') . '/reset_password.php?token=' . $token;
            $mail->Body    = "Hello {$user['first_name']},<br><br>We received a request to reset your password. Click the link below to reset your password:<br><br><a href='{$resetLink}'>Reset Password</a><br><br>If you did not request a password reset, please ignore this email.";

            $mail->send();
            $success = "A password reset link has been sent to your email address.";
        } catch (Exception $e) {
            $error = "Mailer Error: " . $mail->ErrorInfo;
        }
    } else {
        $error = "No account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Forgot Password - NewGrounds</title>
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
    <h1>FORGOT PASSWORD</h1>

    <!-- Display error or success messages -->
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success-msg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Forgot Password Form -->
    <form method="POST" action="">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" required />

      <button type="submit">SEND RESET LINK</button>
    </form>

    <p style="margin-top:15px; font-size:12px;">
      Remembered your password? <a href="login.php" style="color:#a0b4ff;">Login here</a>
    </p>
  </div>
  <div class="right"></div>
</div>

</body>
</html>
