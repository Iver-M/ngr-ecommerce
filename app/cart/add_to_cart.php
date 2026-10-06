<?php
session_start();
include __DIR__ . '/../../config/db.php';

// Check if this is an AJAX request
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please login first.']);
        exit;
    }
    $_SESSION['message'] = "Please login first.";
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = (int) $_POST['product_id'];
    $quantity_to_add = (int) ($_POST['quantity'] ?? 1);

    // Fetch product details including stock
    $stmt = $pdo->prepare("SELECT name, price, stock FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }
        $_SESSION['message'] = "Invalid product.";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
        exit;
    }
    
    $stock = (int)$product['stock'];

    if ($stock <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sorry, this product is out of stock.']);
            exit;
        }
        $_SESSION['message'] = "Sorry, this product is out of stock.";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
        exit;
    }

    $product_name = $product['name'];
    $price = $product['price'];

    // Check current quantity in cart
    $check_stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $check_stmt->execute([$user_id, $product_id]);
    $existing_cart_item = $check_stmt->fetch();
    
    $quantity_in_cart = $existing_cart_item ? (int)$existing_cart_item['quantity'] : 0;
    
    // Validate against available stock
    if (($quantity_in_cart + $quantity_to_add) > $stock) {
        $can_add = $stock - $quantity_in_cart;
        $message = '';
        if ($can_add > 0) {
            $message = "Cannot add that many. You can only add {$can_add} more of this item.";
        } else {
            $message = "Cannot add more. Your cart already has all available stock for this item.";
        }
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['message'] = $message;
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
        exit;
    }

    // Add or Update cart
    if ($existing_cart_item) {
        // Update quantity and ensure it's selected
        $update = $pdo->prepare("UPDATE cart SET quantity = quantity + ?, is_selected = 1 WHERE user_id = ? AND product_id = ?");
        $update->execute([$quantity_to_add, $user_id, $product_id]);
    } else {
        // Insert new cart item, ensuring it's selected by default
        $insert = $pdo->prepare("
            INSERT INTO cart (user_id, product_id, product_name, price, quantity, is_selected) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $insert->execute([$user_id, $product_id, $product_name, $price, $quantity_to_add]);
    }

    // Remove from wishlist if it exists there
    $removeWishlist = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $removeWishlist->execute([$user_id, $product_id]);

    // Get updated cart count
    $stmt_cart = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt_cart->execute([$user_id]);
    $cart_count = $stmt_cart->fetchColumn() ?: 0;

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Product added to cart!',
            'cart_count' => $cart_count
        ]);
        exit;
    }

    $_SESSION['message'] = "Product added to cart!";
}

// Redirect back to the previous page (non-AJAX)
$redirect = $_SERVER['HTTP_REFERER'] ?? 'products.php';
header("Location: " . $redirect);
exit;