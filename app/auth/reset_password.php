<?php
session_start();
require __DIR__ . "/../../config/db.php";
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';
require __DIR__ . '/../../PHPMailer/src/Exception.php';
require(__DIR__ . "/../../sendgrid/sendgrid-php.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

// If token exists in URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token exists in the database
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();

    if ($reset_request) {
        // User exists, now we allow resetting the password
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Password validation regex
            $password_regex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?\":{}|<>])[A-Za-z\d@$!%*?&]{8,}$/";

            if ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (!preg_match($password_regex, $password)) {
                $error = "Password must be at least 8 characters long, contain at least one uppercase letter, one number, and one special character.";
            } else {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update password in the users table
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $reset_request['user_id']]);

                // Delete the reset token after use
                $delete_stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $delete_stmt->execute([$token]);

                $success = "Your password has been reset successfully. You can now login with your new password.";
            }
        }
    } else {
        $error = "Invalid or expired reset token.";
    }
} else {
    $error = "No reset token found.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - NewGrounds</title>
    <link rel="stylesheet" href="assets/login_style.css">
    <script>
        // Password validation function
        function validatePassword() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirm_password").value;
            
            var lengthValid = password.length >= 8;
            var uppercaseValid = /[A-Z]/.test(password);
            var numberValid = /\d/.test(password);
            var specialCharValid = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            // Update password strength feedback
            document.getElementById("length-valid").style.display = lengthValid ? 'none' : 'block';
            document.getElementById("uppercase-valid").style.display = uppercaseValid ? 'none' : 'block';
            document.getElementById("number-valid").style.display = numberValid ? 'none' : 'block';
            document.getElementById("special-char-valid").style.display = specialCharValid ? 'none' : 'block';
            
            // Password match validation
            var passwordMatchValid = password === confirmPassword;
            document.getElementById("password-match-valid").style.display = passwordMatchValid ? 'none' : 'block';

            // Enable or disable the submit button based on all validations
            document.getElementById("submit-btn").disabled = !(lengthValid && uppercaseValid && numberValid && specialCharValid && passwordMatchValid);
        }
        
        // Confirm password match
        function validateConfirmPassword() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirm_password").value;
            
            var passwordMatchValid = password === confirmPassword;
            document.getElementById("password-match-valid").style.display = passwordMatchValid ? 'none' : 'block';
            
            document.getElementById("submit-btn").disabled = !passwordMatchValid;
        }
    </script>
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
        <h1>RESET PASSWORD</h1>

        <!-- Display error or success messages -->
        <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
        <?php if ($success) echo "<p style='color:green;'>$success</p>"; ?>

        <!-- Reset Password Form -->
        <form method="POST" onsubmit="return validatePassword()">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" id="password" placeholder="New Password" onkeyup="validatePassword()" required>
                <div id="length-valid" style="color: red; display: none;">Password must be at least 8 characters long.</div>
                <div id="uppercase-valid" style="color: red; display: none;">Password must contain at least one uppercase letter.</div>
                <div id="number-valid" style="color: red; display: none;">Password must contain at least one number.</div>
                <div id="special-char-valid" style="color: red; display: none;">Password must contain at least one special character (e.g., !@#$%^&*).</div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" onkeyup="validateConfirmPassword()" required>
                <div id="password-match-valid" style="color: red; display: none;">Passwords do not match.</div>
            </div>

            <button type="submit" id="submit-btn" disabled>Reset Password</button>
        </form>

        <p style="margin-top:15px; font-size:12px;">
            Remember your password? <a href="login.php" style="color:#a0b4ff;">Login here</a>
        </p>
    </div>

    <div class="right"></div>
</div>

</body>
</html>
