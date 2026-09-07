<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$added_count = 0;
$out_of_stock_count = 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Only allow POST requests
    header("Location: wishlist.php");
    exit;
}

$pdo->beginTransaction();
try {
    // Get all wishlist items with their current product status and stock
    $stmt = $pdo->prepare("
        SELECT w.product_id, p.status, p.stock 
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wishlist_items as $item) {
        
        // --- *** THE FIX: This logic now matches wishlist.php *** ---
        
        // Check if status is NOT 'out of stock' (case-insensitive and trimmed)
        $is_not_explicitly_out_of_stock = (trim(strtolower($item['status'])) !== 'out of stock');
        // Check if there is numerical stock
        $has_stock = ((int)$item['stock'] > 0);

        // Item is available IF it's not marked "out of stock" AND has a stock quantity > 0
        if ($is_not_explicitly_out_of_stock && $has_stock) {
            
            // --- *** END OF FIX *** ---

            // Item is IN STOCK, add it to the cart
            
            // 1. Check if it's already in the cart
            $cart_stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
            $cart_stmt->execute([$user_id, $item['product_id']]);
            $cart_item = $cart_stmt->fetch();

            if ($cart_item) {
                // 2. If it is, update the quantity (add 1)
                $update_stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ?");
                $update_stmt->execute([$cart_item['cart_id']]);
            } else {
                // 3. If it's not, insert a new cart record
                $insert_stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert_stmt->execute([$user_id, $item['product_id'], 1]);
            }

            // 4. Remove the item from the wishlist
            $delete_stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $delete_stmt->execute([$user_id, $item['product_id']]);
            
            $added_count++;
        } else {
            // Item is OUT OF STOCK, leave it in the wishlist
            $out_of_stock_count++;
        }
    }

    // All operations were successful
    $pdo->commit();

    // Set a feedback message for the user
    if ($added_count > 0 && $out_of_stock_count > 0) {
        $_SESSION['message'] = "Added $added_count item(s) to your cart. $out_of_stock_count item(s) were out of stock and remain in your wishlist.";
    } elseif ($added_count > 0 && $out_of_stock_count == 0) {
        $_SESSION['message'] = "Successfully added all $added_count item(s) from your wishlist to your cart.";
    } elseif ($added_count == 0 && $out_of_stock_count > 0) {
        $_SESSION['message'] = "Could not add items. All $out_of_stock_count item(s) in your wishlist are out of stock.";
    } elseif ($added_count == 0 && $out_of_stock_count == 0) {
        $_SESSION['message'] = "Your wishlist was already empty.";
    } else {
        $_SESSION['message'] = "Wishlist processed."; // Fallback
    }

} catch (Exception $e) {
    // Something went wrong, roll back all changes
    $pdo->rollBack();
    
    // Show the REAL Error Message
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
}

// Redirect back to the wishlist page
header("Location: wishlist.php");
exit;
?>
