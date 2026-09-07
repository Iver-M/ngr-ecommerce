<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
include 'config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Handle Form Submissions ---

// 🔹 Handle select/deselect all items
if (isset($_POST['toggle_all_selection'])) {
    // The value "1" will select all, "0" will deselect all.
    $select_status = filter_input(INPUT_POST, 'select_status', FILTER_VALIDATE_INT);
    if (in_array($select_status, [0, 1])) {
        $stmt = $pdo->prepare("UPDATE cart SET is_selected = ? WHERE user_id = ?");
        $stmt->execute([$select_status, $user_id]);
    }
    header("Location: cart.php");
    exit;
}

// Handle remove item from cart
if (isset($_POST['remove_cart_id'])) {
    $cart_id = filter_input(INPUT_POST, 'remove_cart_id', FILTER_VALIDATE_INT);
    if ($cart_id) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        $_SESSION['message'] = "Item removed from cart.";
    }
    header("Location: cart.php");
    exit;
}

// Handle update item quantity
if (isset($_POST['update_cart_id'], $_POST['quantity'])) {
    $cart_id = filter_input(INPUT_POST, 'update_cart_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($cart_id) {
        $stmt = $pdo->prepare("SELECT product_id FROM cart WHERE cart_id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        $cart_item = $stmt->fetch();

        if ($cart_item) {
            $product_id = $cart_item['product_id'];

            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            $stock = $product ? (int)$product['stock'] : 0;

            $new_quantity = max(1, $quantity); 

            if ($new_quantity > $stock) {
                $new_quantity = $stock;
                $_SESSION['message'] = "Quantity adjusted. Only {$stock} items are available.";
            } else {
                 $_SESSION['message'] = "Cart updated successfully.";
            }
            
            if ($stock <= 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $user_id]);
                $_SESSION['message'] = "An item was removed as it is now out of stock.";
            } else {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
                $stmt->execute([$new_quantity, $cart_id, $user_id]);
            }
        }
    }
    header("Location: cart.php");
    exit;
}

// Handle single item selection toggle (DB update)
if (isset($_POST['toggle_selection'])) {
    $cart_id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
    $is_selected = isset($_POST['is_selected']) ? 1 : 0; 
    
    if ($cart_id) {
        $stmt = $pdo->prepare("UPDATE cart SET is_selected = ? WHERE cart_id = ? AND user_id = ?");
        $stmt->execute([$is_selected, $cart_id, $user_id]);
    }
    
    header("Location: cart.php");
    exit;
}


// --- Fetch and Sync Cart Data ---

$stmt = $pdo->prepare("
    SELECT c.cart_id, c.product_id, c.product_name, c.price, c.quantity, c.is_selected, p.image, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cart_was_updated = false;
foreach ($cart_items as $key => &$item) {
    $stock = (int)$item['stock'];
    if ($stock <= 0) {
        $delete_stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $delete_stmt->execute([$item['cart_id'], $user_id]);
        unset($cart_items[$key]); 
        $cart_was_updated = true;
    } elseif ($item['quantity'] > $stock) {
        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
        $update_stmt->execute([$stock, $item['cart_id'], $user_id]);
        $item['quantity'] = $stock; 
        $cart_was_updated = true;
    }
}
unset($item); 

if ($cart_was_updated) {
     $_SESSION['cart_sync_message'] = "Your cart was updated based on stock availability.";
}


// --- Calculate Totals & Check Selection State ---
$total = 0;
$selected_item_count = 0;
$all_items_selected = true; // Assume all are selected initially

if (empty($cart_items)) {
    $all_items_selected = false;
} else {
    foreach ($cart_items as $item) {
        if ($item['is_selected']) {
            $total += $item['price'] * $item['quantity'];
            $selected_item_count++;
        } else {
            // If we find even one unselected item, not all are selected
            $all_items_selected = false; 
        }
    }
}

include 'includes/header.php';
?>

<!-- Custom styles -->
<style>
    .cart-item { background-color: #1a1a1a; border: 1px solid #333; border-radius: 10px; padding: 20px; margin-bottom: 20px; display: flex; align-items: center; flex-wrap: wrap; gap: 15px; }
    .select-checkbox { width: 22px; height: 22px; margin-right: 20px; accent-color: #ff5c1c; background-color: #333; border: 1px solid #555; cursor: pointer; flex-shrink: 0; }
    .cart-item-image img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; }
    .cart-item-image { flex-shrink: 0; }
    .cart-item-details { flex: 1; min-width: 0; }
    .cart-item-details h5 { margin-bottom: 5px; color: #fff; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cart-item-details .text-muted { color: #aaa !important; }
    .cart-item-actions { display: flex; align-items: center; gap: 20px; margin-left: auto; }
    .quantity-controls { display: flex; align-items: center; background-color: #333; border-radius: 50px; border: 1px solid #555; overflow: hidden; flex-shrink: 0; }
    .quantity-controls .quantity-display { width: auto; padding: 0 15px; text-align: center; font-size: 1.1rem; font-weight: bold; color: #fff; }
    .quantity-controls .btn { width: 40px; height: 40px; padding: 0; font-size: 1.2rem; background-color: #2a2a2a; border: none; color: #fff; border-radius: 0; border-left: 1px solid #555; transition: background-color 0.2s; }
    .quantity-controls .btn:disabled { background-color: #222; color: #555; cursor: not-allowed; }
    .quantity-controls .btn:hover:not(:disabled) { background-color: #ff5c1c; }
    .order-summary { background-color: #1a1a1a; border: 1px solid #333; border-radius: 10px; padding: 25px; position: sticky; top: 100px; }
    .remove-btn { background: none; border: none; color: #888; padding: 5px; cursor: pointer; transition: color 0.2s ease-in-out; display: flex; align-items: center; justify-content: center; }
    .remove-btn:hover { color: #ff5c1c; }
    .remove-btn svg { width: 22px; height: 22px; }
    .cart-header { background-color: #1a1a1a; border: 1px solid #333; border-radius: 10px; padding: 15px 20px; margin-bottom: 1rem; }

    /* Custom orange checkout button */
    .btn-orange {
        background-color: #ff5c1c;
        border-color: #ff5c1c;
        color: white;
    }
    .btn-orange:hover {
        background-color: #e04a0f; /* A darker orange for hover */
        border-color: #d4460e;
        color: white;
    }
    .btn-orange.disabled, .btn-orange:disabled {
        background-color: #ff5c1c;
        border-color: #ff5c1c;
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<div class="container my-5 text-light" style="padding-top: 80px;">
    <div class="row">
        <div class="col-12 text-center mb-4">
            <h1>Shopping Cart</h1>
        </div>
    </div>

    <!-- Session Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_sync_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['cart_sync_message']); unset($_SESSION['cart_sync_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="text-center p-5 bg-dark rounded">
            <h4>Your cart is empty.</h4>
            <a href="products.php" class="btn btn-warning mt-3">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Cart Items Column -->
            <div class="col-lg-8">
                <!-- 🔹 Select All / Deselect All Header -->
                <div class="cart-header d-flex justify-content-between align-items-center">
                    <form method="POST" class="m-0 d-flex align-items-center" id="selectAllForm">
                        <input type="hidden" name="toggle_all_selection" value="1">
                        <input type="hidden" name="select_status" id="selectAllStatusInput">
                        
                        <input class="select-checkbox" type="checkbox" id="selectAllCheckbox"
                               <?= $all_items_selected ? 'checked' : '' ?>
                               onchange="document.getElementById('selectAllStatusInput').value = this.checked ? 1 : 0; document.getElementById('selectAllForm').submit();">
                        
                        <label for="selectAllCheckbox" class="fw-bold" style="cursor: pointer; margin-bottom: 0;">
                            Select All
                        </label>
                    </form>
                    <span class="fw-bold">Total Items: <?= count($cart_items) ?></span>
                </div>
                
                <?php foreach ($cart_items as $item): $stock = (int)$item['stock']; ?>
                    <div class="cart-item">
                        <!-- Selection Checkbox Form -->
                        <form method="POST" class="m-0">
                            <input type="hidden" name="toggle_selection" value="1">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <input class="select-checkbox" type="checkbox" name="is_selected" value="1" 
                                   <?= !empty($item['is_selected']) ? 'checked' : '' ?> 
                                   onchange="this.form.submit()">
                        </form>

                        <div class="cart-item-image">
                            <img src="uploads/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                        </div>
                        <div class="cart-item-details">
                            <h5 title="<?= htmlspecialchars($item['product_name']) ?>"><?= htmlspecialchars($item['product_name']) ?></h5>
                            <p class="text-muted mb-0">Price: ₱<?= number_format($item['price'], 2) ?></p>
                            <?php if ($stock > 0 && $stock <= 5): ?>
                                <p class="text-warning mb-0 mt-1"><small>Low stock: Only <?= $stock ?> left!</small></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cart-item-actions">
                            <!-- Quantity Controls Form -->
                            <form method="POST" class="quantity-controls">
                                <input type="hidden" name="update_cart_id" value="<?= $item['cart_id'] ?>">
                                <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="btn" <?= ($item['quantity'] <= 1) ? 'disabled' : '' ?>>-</button>
                                <span class="quantity-display"><?= $item['quantity'] ?></span>
                                <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="btn" <?= ($item['quantity'] >= $stock) ? 'disabled' : '' ?>>+</button>
                            </form>

                            <div class="text-end" style="min-width: 100px;">
                                <strong>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                            </div>
                            <!-- Remove Item Form -->
                            <form method="POST">
                                <input type="hidden" name="remove_cart_id" value="<?= $item['cart_id'] ?>">
                                <button type="submit" class="remove-btn" aria-label="Remove item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Order Summary Column -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h4 class="mb-4">Order Summary</h4>
                    <p class="text-muted"><small>Total for <?= $selected_item_count ?> selected item(s)</small></p>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>₱<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping</span>
                        <span class="text-muted">Calculated at checkout</span>
                    </div>
                    <hr class="border-secondary">
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                        <span>Total</span>
                        <span>₱<?= number_format($total, 2) ?></span>
                    </div>
                    <a href="checkout.php" class="btn btn-orange w-100 btn-lg  <?= ($selected_item_count == 0) ? 'disabled' : '' ?>" >
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
