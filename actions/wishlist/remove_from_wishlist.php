<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
include 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, send back
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Check if it's a POST request and product_id is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];

    try {
        // Prepare and execute the delete statement
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);

        // Set a success message
        if ($stmt->rowCount() > 0) {
            $_SESSION['message'] = "Item removed from wishlist.";
        } else {
            // This might happen if the user clicks twice quickly
            $_SESSION['message'] = "Item was already removed.";
        }

    } catch (Exception $e) {
        // Handle any database errors
        $_SESSION['message'] = "An error occurred. Please try again.";
        // For debugging: $_SESSION['message'] = "Error: " . $e->getMessage();
    }

} else {
    // If not a POST request or product_id is missing
    $_SESSION['message'] = "Invalid request.";
}

// Redirect back to the page the user came from (e.g., wishlist.php or products.php)
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
