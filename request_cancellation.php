<?php
session_start();
require_once 'config/db.php';
require_once 'notification_manager.php'; // Include the notification manager

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_item_id'])) {
    
    $order_item_id = filter_input(INPUT_POST, 'order_item_id', FILTER_VALIDATE_INT);
    
    // --- NEW: CAPTURE CANCELLATION REASON ---
    $reason = filter_input(INPUT_POST, 'cancellation_reason', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'cancellation_notes', FILTER_SANITIZE_STRING);

    $full_reason = $reason;
    if ($reason === 'Other' && !empty($notes)) {
        // If they chose "Other" and left a note, combine them.
        $full_reason = "Other: " . $notes;
    } elseif (empty($reason)) {
        // Fallback in case something goes wrong
        $full_reason = "No reason provided.";
    }
    // --- END NEW ---

    if ($order_item_id) {
        try {
            $pdo->beginTransaction();

            // Check if the item belongs to the user and is in a cancellable state
            $stmt_check = $pdo->prepare("
                SELECT oi.id, o.status as order_status
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE oi.id = ? AND o.user_id = ?
            ");
            $stmt_check->execute([$order_item_id, $user_id]);
            $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($item && in_array($item['order_status'], ['Order Placed', 'Processing'])) {
                
                // --- UPDATE: Modify the SQL query to include the reason ---
                $stmt_update = $pdo->prepare("
                    UPDATE order_items 
                    SET status = 'Cancellation Requested', 
                        cancellation_reason = ? 
                    WHERE id = ?
                ");
                $stmt_update->execute([$full_reason, $order_item_id]);
                // --- END UPDATE ---

                
                // --- FIX: Replace function call with direct SQL INSERT ---
                $message = "Your request to cancel item (ID: $order_item_id) has been submitted.";
                $link = "profile.php?page=cancellations";
                $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $stmt_notify->execute([$user_id, $message, $link]);
                // --- END FIX ---
                
                $pdo->commit();

                $_SESSION['success'] = "Cancellation request submitted successfully.";

            } else {
                // Item not found, doesn't belong to user, or order status is not cancellable
                $_SESSION['error'] = "This item cannot be cancelled at this time.";
                $pdo->rollBack();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid item ID.";
    }
} else {
    $_SESSION['error'] = "Invalid request.";
}

// Redirect back to the orders page
header("Location: profile.php?page=orders");
exit;
?>

