<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
require "config/db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Password validation regex: At least 8 characters, one uppercase, one digit, one special character
    $password_regex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?\":{}|<>])[A-Za-z\d@$!%*?&]{8,}$/";

    if (!ctype_digit($contact)) {
        $error = "Contact number must contain only digits.";
    } elseif (strlen($contact) != 11) {
        $error = "Contact number must be exactly 11 digits.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match($password_regex, $password)) {
        $error = "Password must be at least 8 characters long, contain at least one uppercase letter, one number, and one special character.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user and automatically set them as verified (verified = 1)
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, contact, password, verified) VALUES (?, ?, ?, ?, ?, 1)");
        try {
            $stmt->execute([$first_name, $last_name, $email, $contact, $hashed_password]);

            $success = "Registration successful! You can now log in.";

            // Clear form fields after successful registration
            unset($_POST);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Email or contact already registered.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - NewGrounds</title>
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
      <img src="uploads/images/logo/NGLogo.png" alt="logo" height="50"></a>
      <span>NewGrounds</span></div>
    <h1>REGISTER</h1>

    <?php if($error) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if($success) echo "<p style='color:green;'>$success</p>"; ?>

    <form method="POST" onsubmit="return validatePassword()">
      <div class="form-group">
        <label>First Name</label>
        <input type="text" name="first_name" placeholder="First Name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
      </div>

      <div class="form-group">
        <label>Last Name</label>
        <input type="text" name="last_name" placeholder="Last Name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
      </div>

      <div class="form-group">
        <label>Contact Number</label>
        <input type="text" name="contact" placeholder="Contact Number" maxlength="11" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="password" placeholder="Password" onkeyup="validatePassword()" required>
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

      <button type="submit" id="submit-btn" disabled>REGISTER</button>
    </form>

    <p style="margin-top:15px; font-size:12px;">Already have an account? <a href="login.php" style="color:#a0b4ff;">Login here</a></p>
  </div>

  <div class="right"></div>
</div>

</body>
</html>
