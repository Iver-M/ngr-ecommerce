<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
include 'config/db.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "You must log in to use wishlist.";
    header("Location: login.php");
    exit;
}

// Handle the wishlist addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];

    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $product_id]);

    $_SESSION['message'] = "Product added to wishlist!";
}

// --- UPDATED REDIRECT LOGIC ---

// Check if a specific return URL was provided from the form
if (isset($_POST['return_url']) && !empty($_POST['return_url'])) {
    // Redirect back to the page the user was on
    header("Location: " . $_POST['return_url']);
} else {
    // Fallback logic (your original logic) just in case return_url isn't set
    if (isset($_POST['product_id'])) {
        header("Location: product.php?id=" . (int)$_POST['product_id']);
    } else {
        header("Location: products.php");
    }
}
exit;
