<?php
session_start();
require __DIR__ . "/../../config/db.php";

$error = "";
$success = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if the token exists and if it's not expired (valid for 24 hours)
    $stmt = $pdo->prepare("SELECT id, verification_token, token_created_at FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);

    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch();

        // Check if the token has expired (24 hours expiry)
        $token_created_at = strtotime($user['token_created_at']);
        $current_time = time();
        $expiry_time = 24 * 60 * 60;  // 24 hours in seconds

        if (($current_time - $token_created_at) > $expiry_time) {
            $error = "This verification link has expired.";
        } else {
            // Token is valid, verify the user's email
            $updateStmt = $pdo->prepare("UPDATE users SET verified = 1, verification_token = NULL WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            $success = "Your email has been verified. You can now <a href='login.php'>login</a>.";
            // Optionally, redirect to the login page after a few seconds
            header("refresh:5; url=login.php");
        }
    } else {
        $error = "Invalid or expired token.";
    }
} else {
    $error = "No token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; }
        p { font-size: 16px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>

    <?php if($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <?php if($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>

</body>
</html>
