<?php
session_start();
include __DIR__ . '/../../config/db.php';

// ✅ Check login status
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must log in to submit a review.";
    header("Location: login.php");
    exit;
}

// ✅ Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize & validate input
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $title = trim($_POST['title'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    // Pull user info from session
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['user_name'];
    $email = $_SESSION['user_email'];

    // ✅ Basic validation
    if ($product_id <= 0 || $rating <= 0 || empty($comment)) {
        $_SESSION['error'] = "Please complete all required fields.";
        header("Location: product.php?id=" . $product_id . "#reviews");
        exit;
    }

    // ✅ Insert review into DB
    $stmt = $pdo->prepare("
        INSERT INTO reviews (product_id, user_id, name, email, rating, title, comment, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$product_id, $user_id, $name, $email, $rating, $title, $comment]);

    $_SESSION['success'] = "Thank you for your review!";
    header("Location: product.php?id=" . $product_id . "#reviews");
    exit;

} else {
    header("Location: index.php");
    exit;
}
?>