<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
require "config/db.php";

header('Content-Type: application/json');

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$error = '';
$success = '';

// Server-side validation
if (!$firstName || !$lastName || !$email || !$contact || !$password || !$confirm) {
    $error = "All fields are required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email format.";
} elseif (!preg_match("/^\d{11}$/", $contact)) {
    $error = "Contact must be exactly 11 digits.";
} elseif (!ctype_digit($contact)) {
    $error = "Contact must contain only numbers.";
} elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters.";
} elseif ($password !== $confirm) {
    $error = "Passwords do not match.";
} else {
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $error = "Email already registered.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, contact, password) VALUES (?,?,?,?,?)");
        $stmt->execute([$firstName, $lastName, $email, $contact, $hashedPassword]);
        $success = "Registration successful! You can now <a href='index.php'>login</a>.";
    }
}

echo json_encode(['error' => $error, 'success' => $success]);
exit;
