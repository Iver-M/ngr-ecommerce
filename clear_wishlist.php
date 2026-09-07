<?php
session_start();
include 'config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ensure the request is a POST request (from the modal's form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    try {
        // Delete all items from the wishlist for this user
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Set a success message
        $_SESSION['message'] = "Your wishlist has been cleared.";

    } catch (Exception $e) {
        // Set an error message
        $_SESSION['message'] = "An error occurred while clearing your wishlist.";
        // For debugging: $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    }
}

// Redirect back to the wishlist page
header("Location: wishlist.php");
exit;
?>