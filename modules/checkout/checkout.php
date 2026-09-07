<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
include 'config/db.php';
include 'services/shipping_logic.php';
require_once 'services/notification_manager.php'; // Include the notification manager

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = "";
$order_placed_successfully = false;

// Check for a success flag from PayPal capture, then unset it
if (isset($_SESSION['order_placed_successfully']) && $_SESSION['order_placed_successfully']) {
    $order_placed_successfully = true;
    unset($_SESSION['order_placed_successfully']);
}

// Handle Add/Edit Address submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'manage_address') {
    $address_id = $_POST['address_id'] ?? null;
    $recipient_name = trim($_POST['recipient_name']);
    $phone = trim($_POST['phone']);
    $region = trim($_POST['region_text']);
    $province = trim($_POST['province_text']);
    $city = trim($_POST['city_text']);
    $barangay = trim($_POST['barangay_text']);
    $postal_code = trim($_POST['postal_code']);
    $street_address = trim($_POST['street_address']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (!empty($recipient_name) && !empty($phone) && !empty($city) && !empty($street_address)) {
        if ($is_default == 1) {
            $stmt_unset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt_unset->execute([$user_id]);
        }
        if ($address_id) {
            $stmt_manage = $pdo->prepare("UPDATE user_addresses SET recipient_name=?, phone=?, region=?, province=?, city=?, barangay=?, postal_code=?, street_address=?, is_default=? WHERE address_id = ? AND user_id = ?");
            $stmt_manage->execute([$recipient_name, $phone, $region, $province, $city, $barangay, $postal_code, $street_address, $is_default, $address_id, $user_id]);
        } else {
            $stmt_manage = $pdo->prepare("INSERT INTO user_addresses (user_id, recipient_name, phone, region, province, city, barangay, postal_code, street_address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_manage->execute([$user_id, $recipient_name, $phone, $region, $province, $city, $barangay, $postal_code, $street_address, $is_default]);
        }
    }
    header("Location: checkout.php");
    exit;
}

// Fetch SELECTED cart items for checkout
$stmt = $pdo->prepare("SELECT c.*, p.price, p.name as product_name, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND c.is_selected = 1");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle COD ORDER PLACEMENT submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $address_option_id = $_POST['shipping_address_option'] ?? '';
    $shipping_option_key = $_POST['shipping_option'] ?? 'standard';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    
    if ($payment_method !== 'Cash on Delivery') {
        $error = "Invalid payment method for this action.";
    } else {
        if (is_numeric($address_option_id)) {
            $stmt_addr_select = $pdo->prepare("SELECT * FROM user_addresses WHERE address_id = ? AND user_id = ?");
            $stmt_addr_select->execute([$address_option_id, $user_id]);
            $selected_address = $stmt_addr_select->fetch(PDO::FETCH_ASSOC);
        } else { $selected_address = null; }
        
        $subtotal_for_order = 0;
        foreach ($cart_items as $item) { $subtotal_for_order += $item['price'] * $item['quantity']; }

        if (empty($selected_address) || empty($payment_method)) {
            $error = "Please select a valid shipping address and payment method.";
        } else {
            try {
                $pdo->beginTransaction();
                $stmt_cart_stock = $pdo->prepare("SELECT c.product_id, c.quantity, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND c.is_selected = 1 FOR UPDATE");
                $stmt_cart_stock->execute([$user_id]);
                $cart_items_for_processing = $stmt_cart_stock->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cart_items_for_processing as $item) { if ($item['quantity'] > $item['stock']) { throw new Exception("Not enough stock for a selected item."); } }
                
                // Use the flat cost from shipping_logic.php
                $shipping_cost = get_shipping_cost($selected_address['region'], $shipping_option_key, $shipping_options);
                $shipping_method = $shipping_options[$shipping_option_key]['name'] . " to " . $selected_address['region'];
                
                // --- VOUCHER LOGIC (COD) ---
                // Get voucher details from session
                $voucher_code = $_SESSION['voucher_code'] ?? null;
                $discount_amount = $_SESSION['discount_amount'] ?? 0.00;
                $voucher_id = $_SESSION['voucher_id'] ?? null;
                
                $final_total = ($subtotal_for_order + $shipping_cost) - $discount_amount;
                // --- END VOUCHER LOGIC (COD) ---

                $full_shipping_address = "{$selected_address['street_address']}, {$selected_address['barangay']}, {$selected_address['city']}, {$selected_address['province']} {$selected_address['postal_code']}, {$selected_address['region']}";

                // --- UPDATE ORDER INSERT QUERY ---
                $stmt_order = $pdo->prepare("INSERT INTO orders (user_id, shipping_address, city, phone, total_amount, payment_method, shipping_method, shipping_cost, voucher_code, discount_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed')");
                $stmt_order->execute([$user_id, $full_shipping_address, $selected_address['city'], $selected_address['phone'], $final_total, $payment_method, $shipping_method, $shipping_cost, $voucher_code, $discount_amount]);
                // --- END UPDATE ORDER INSERT ---
                $order_id = $pdo->lastInsertId();

                $initial_status = "Order Placed";
                $initial_remarks = "Your order has been received and is waiting for processing.";
                $stmt_history = $pdo->prepare("INSERT INTO order_status_history (order_id, status, remarks) VALUES (?, ?, ?)");
                $stmt_history->execute([$order_id, $initial_status, $initial_remarks]);

                $stmt_items = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                foreach ($cart_items as $item) {
                    $stmt_items->execute([$order_id, $item['product_id'], $item['product_name'], $item['quantity'], $item['price']]);
                    $stmt_update_stock->execute([$item['quantity'], $item['product_id']]);
                }

                $stmt_clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND is_selected = 1");
                $stmt_clear->execute([$user_id]);

                // --- LOG VOUCHER USAGE ---
                if ($voucher_id && $order_id) {
                    $stmt_log_voucher = $pdo->prepare("INSERT INTO user_voucher_usage (user_id, voucher_id, order_id) VALUES (?, ?, ?)");
                    $stmt_log_voucher->execute([$user_id, $voucher_id, $order_id]);
                }
                // --- END LOG VOUCHER USAGE ---
                
                $pdo->commit();
                
                // --- UNSET VOUCHER SESSION ---
                unset($_SESSION['voucher_id']);
                unset($_SESSION['voucher_code']);
                unset($_SESSION['discount_amount']);
                // --- END UNSET VOUCHER ---
                
                // Send the confirmation email
                sendOrderConfirmationEmail($pdo, $order_id);
                
                $order_placed_successfully = true;

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['message'] = $e->getMessage();
                header("Location: cart.php");
                exit;
            }
        }
    }
}


if (!$order_placed_successfully && count($cart_items) == 0) {
    $_SESSION['message'] = "Please select items from your cart before checking out.";
    header("Location: cart.php");
    exit;
}

$stmt_addr = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
$stmt_addr->execute([$user_id]);
$saved_addresses = $stmt_addr->fetchAll(PDO::FETCH_ASSOC);

$default_address = null;
if (!empty($saved_addresses)) {
    $default_address = $saved_addresses[0];
}

$subtotal = 0;
foreach ($cart_items as $item) { $subtotal += $item['price'] * $item['quantity']; }


include 'includes/header.php';
?>
<style>
    /* --- Your existing styles --- */
    .checkout-container{color:#e0e0e0}
    .checkout-card,.order-summary-card{background: radial-gradient(circle, #2a2a2a 0%, #1e1e1e 100%);border:1px solid #333;border-radius:10px;padding:2rem;box-shadow: 0 4px 15px rgba(0,0,0,0.2);}
    .checkout-card h4 {text-shadow: 0 0 8px rgba(255, 92, 28, 0.3);}
    .order-summary-card ul{list-style:none;padding:0}.order-summary-item{display:flex;align-items:center;padding:1rem;background-color:#2a2a2a;border:1px solid #444;border-radius:8px;margin-bottom:.75rem}.order-summary-item img{width:60px;height:60px;object-fit:cover;border-radius:6px;margin-right:1rem}
    .select-card{position:relative;padding:1.5rem;border:1px solid #444;border-radius:8px;margin-bottom:1rem;cursor:pointer;transition:all .2s ease-in-out; background-color: rgba(255,255,255,0.05);}
    .select-card:hover{background-color:rgba(255, 92, 28, 0.1); border-color: #ff5c1c;}
    .select-card input[type="radio"]{position:absolute;opacity:0;width:100%;height:100%;top:0;left:0;cursor:pointer}
    .select-card .content-wrapper{display:flex;align-items:center}
    .select-card .radio-custom{width:22px;height:22px;border-radius:50%;border:2px solid #888;margin-right:1.5rem;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .2s ease-in-out;background-color:transparent}
    .select-card .radio-custom::after{content:'';display:block;width:6px;height:12px;border:solid #fff;border-width:0 3px 3px 0;opacity:0;transform:rotate(45deg) scale(.5);transition:all .2s ease-in-out}
    .select-card input[type="radio"]:checked ~ .content-wrapper .radio-custom{background-color:#ff5c1c;border-color:#ff5c1c}
    .select-card input[type="radio"]:checked ~ .content-wrapper .radio-custom::after{opacity:1;transform:rotate(45deg) scale(1)}
    .form-control,.form-select{background-color:#2a2a2a;color:#fff;border-color:#444}.form-control:focus,.form-select:focus{background-color:#2a2a2a;color:#fff;border-color:#ff5c1c;box-shadow:0 0 0 .25rem rgba(255,92,28,.25)}
    .btn-warning{background-color:#ff5c1c;border-color:#ff5c1c;color:#fff !important;}.btn-warning:hover{background-color:#e04a0f;border-color:#e04a0f}
    .add-address-btn{display:flex;align-items:center;justify-content:center;width:100%;border:2px dashed #ff5c1c;color:#ff5c1c;background-color:transparent;padding:1.5rem;border-radius:8px;transition:all .2s}.add-address-btn:hover{background-color:rgba(255, 92, 28, 0.1);color:#fff;border-color:#ff5c1c}
    .modal-content.bg-dark { color: #e0e0e0; }
    .modal-body .form-label, .modal-body .form-check-label { color: #e0e0e0; }
    #selected-address-display, #selected-address-display * { color: #e0e0e0; }
    .location-icon { stroke: #ff5c1c; }
    .btn-outline-secondary:hover { background-color: #ff5c1c; border-color: #ff5c1c; color: #fff; }
    .modal-body .select-card { color: #e0e0e0; }
    .shipping-eta { color: #6ab04c !important; }
    .order-success-card { padding: 3rem 2rem; text-align: center; }
    .order-success-icon { width: 80px; height: 80px; color: #28a745; margin-bottom: 1.5rem; }
    #paypal-button-container { margin-top: 1.5rem; }
    #loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1060; display: none; justify-content: center; align-items: center; }
</style>

<div id="loader-overlay">
    <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<div class="container my-5 checkout-container" style="padding-top: 80px;">

    <?php if ($order_placed_successfully): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="checkout-card order-success-card">
                    <svg class="order-success-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h1 class="mb-3">Order Placed!</h1>
                    <p class="text-secondary mb-4">Thank you for your purchase. Your order has been successfully processed.</p>
                    <div class="mt-4">
                        <a href="products.php" class="btn btn-secondary btn-lg me-3">Continue Shopping</a>
                        <a href="profile.php?page=orders" class="btn btn-warning btn-lg">View My Orders</a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row"><div class="col-12 text-center mb-4"><h1>Checkout</h1></div></div>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" id="checkout-form">
            <input type="hidden" name="action" value="place_order">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="checkout-card">
                        <!-- Shipping Address Section -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="location-icon me-2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <h4 class="mb-0">Shipping Address</h4>
                            </div>
                            <?php if (count($saved_addresses) > 0): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addressListModal">Change</button>
                            <?php endif; ?>
                        </div>
                        
                        <div id="selected-address-display" data-region="<?= htmlspecialchars($default_address['region'] ?? 'default') ?>">
                            <?php if ($default_address): ?>
                                <strong class="d-block" id="display_recipient_name"><?= htmlspecialchars($default_address['recipient_name']) ?></strong>
                                <span class="d-block" id="display_phone"><?= htmlspecialchars($default_address['phone']) ?></span>
                                <span class="d-block" id="display_address_line1"><?= htmlspecialchars($default_address['street_address']) ?>, <?= htmlspecialchars($default_address['barangay']) ?></span>
                                <span class="d-block" id="display_address_line2"><?= htmlspecialchars($default_address['city']) ?>, <?= htmlspecialchars($default_address['province']) ?> <?= htmlspecialchars($default_address['postal_code']) ?></span>
                            <?php else: ?>
                                <div id="no-address-container">
                                    <p id="no-address-message">No shipping address found. Please add one.</p>
                                    <button type="button" class="add-address-btn mt-3" data-bs-toggle="modal" data-bs-target="#addressModal">Add a New Address</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="shipping_address_option" id="selected_address_id" value="<?= $default_address['address_id'] ?? '' ?>" required>
                        <hr class="my-4" style="border-color: #444;">

                        <!-- Shipping Options Section -->
                        <h4 class="mb-4">Shipping Options</h4>
                        <?php foreach ($shipping_options as $key => $option): ?>
                        <label class="select-card">
                            <!-- REMOVED data-cost attribute -->
                            <input type="radio" name="shipping_option" value="<?= $key ?>" class="shipping-radio" <?= $key === 'standard' ? 'checked' : '' ?>>
                            <div class="content-wrapper">
                                <span class="radio-custom"></span>
                                <div>
                                    <!-- REMOVED static cost from label -->
                                    <strong class="d-block"><?= htmlspecialchars($option['name']) ?></strong>
                                    <span class="shipping-eta">ETA: <?= htmlspecialchars($option['eta']) ?></span>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>

                        <?php if (empty($saved_addresses)): ?>
                            <hr class="my-4" style="border-color: #444;">
                            <div class="alert alert-warning" role="alert">
                                Please add a shipping address to view payment options.
                            </div>
                        <?php else: ?>
                            <!-- Payment Method Section -->
                            <hr class="my-4" style="border-color: #444;">
                            <h4 class="mb-3">Payment Method</h4>
                            <label class="select-card">
                                <input type="radio" name="payment_method" class="payment-method-radio" value="Cash on Delivery" checked>
                                <div class="content-wrapper">
                                    <span class="radio-custom"></span>
                                    <div><strong>Cash on Delivery</strong></div>
                                </div>
                            </label>
                            <label class="select-card">
                                <input type="radio" name="payment_method" class="payment-method-radio" value="PayPal">
                                <div class="content-wrapper">
                                    <span class="radio-custom"></span>
                                    <div><strong>PayPal</strong></div>
                                </div>
                            </label>
                        <?php endif; ?>
                        </div>
                </div>
                
                <!-- Order Summary Column -->
                <div class="col-lg-4">
                    <!-- Voucher Input -->
                    <div class="checkout-card mb-4">
                        <h5 class="mb-3">Have a Voucher?</h5>
                        <form id="voucher-form" class="d-flex gap-2">
                            <input type="text" id="voucher-code-input" class="form-control" placeholder="Enter voucher code" autocomplete="off" style="margin-bottom: 20px;">
                            <button type="button" id="apply-voucher-btn" class="btn btn-warning">Apply</button>
                        </form>
                        <div id="voucher-message" class="mt-3"></div>
                    </div>
                
                    <!-- Order Summary -->
                    <div class="order-summary-card" data-subtotal="<?= $subtotal ?>">
                        <h4 class="mb-4">Order Summary</h4>
                        <ul class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <li class="order-summary-item"><img src="uploads/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>"><div class="flex-grow-1"><h6 class="my-0 small"><?= htmlspecialchars($item['product_name']) ?></h6><small class="text-muted">Qty: <?= $item['quantity'] ?></small></div><span class="text-light small">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <hr style="border-color: #444;">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="cart-subtotal">₱<?= number_format($subtotal, 2, '.', ','); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span id="shipping-fee-label">Shipping</span>
                            <span id="cart-shipping">Calculating...</span>
                        </div>
                        
                        <!-- NEW: Discount Row -->
                        <div id="discount-row" class="d-flex justify-content-between mb-3" style="display: none;">
                            <span class="text-success">Discount (<strong id="applied-voucher-code" style="text-transform: uppercase;"></strong>)</span>
                            <span id="cart-discount" class="text-success">- ₱0.00</span>
                        </div>
                        
                        <hr style="border-color: #444;">
                        <div class="d-flex justify-content-between fs-5 fw-bold">
                            <span>Total</span>
                            <strong id="cart-total">Calculating...</strong>
                        </div>
                        
                        <?php if (!empty($saved_addresses)): ?>
                            <button id="cod-submit-button" class="w-100 btn btn-warning btn-lg mt-4" type="submit">Place Order</button>
                            <div id="paypal-button-container" style="display: none;"></div>
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Address List Modal -->
<div class="modal fade" id="addressListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary"><h5 class="modal-title">Select Shipping Address</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?php foreach ($saved_addresses as $address): ?>
                <div class="select-card address-select-option" data-id="<?= $address['address_id'] ?>" data-recipient-name="<?= htmlspecialchars($address['recipient_name']) ?>" data-phone="<?= htmlspecialchars($address['phone']) ?>" data-street-address="<?= htmlspecialchars($address['street_address']) ?>" data-barangay="<?= htmlspecialchars($address['barangay']) ?>" data-city="<?= htmlspecialchars($address['city']) ?>" data-province="<?= htmlspecialchars($address['province']) ?>" data-postal-code="<?= htmlspecialchars($address['postal_code']) ?>" data-region="<?= htmlspecialchars($address['region']) ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong class="d-block"><?= htmlspecialchars($address['recipient_name']) ?> <?= $address['is_default'] ? '<span class="badge bg-secondary ms-2">Default</span>' : '' ?></strong>
                            <span><?= htmlspecialchars($address['phone']) ?></span><br>
                            <span><?= htmlspecialchars($address['street_address']) ?>, <?= htmlspecialchars($address['barangay']) ?>, <?= htmlspecialchars($address['city']) ?></span>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm edit-address-btn flex-shrink-0 ms-2" data-id="<?= $address['address_id'] ?>" data-recipient-name="<?= htmlspecialchars($address['recipient_name']) ?>" data-phone="<?= htmlspecialchars($address['phone']) ?>" data-region="<?= htmlspecialchars($address['region']) ?>" data-province="<?= htmlspecialchars($address['province']) ?>" data-city="<?= htmlspecialchars($address['city']) ?>" data-barangay="<?= htmlspecialchars($address['barangay']) ?>" data-postal-code="<?= htmlspecialchars($address['postal_code']) ?>" data-street-address="<?= htmlspecialchars($address['street_address']) ?>" data-default="<?= $address['is_default'] ?>">Edit</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-warning w-100" id="addNewAddressBtn">Add a New Address</button></div>
        </div>
    </div>
</div>

<!-- Add/Edit Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <form action="checkout.php" method="POST">
                <input type="hidden" name="action" value="manage_address">
                <input type="hidden" name="address_id" id="modal_address_id">
                <input type="hidden" name="region_text" id="modal_region_text"><input type="hidden" name="province_text" id="modal_province_text"><input type="hidden" name="city_text" id="modal_city_text"><input type="hidden" name="barangay_text" id="modal_barangay_text">
                <div class="modal-header border-secondary"><h5 class="modal-title" id="addressModalLabel">Manage Address</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label for="modal_recipient_name" class="form-label">Full Name</label><input type="text" class="form-control" id="modal_recipient_name" name="recipient_name" required></div>
                    
                    <!-- MODIFICATION FOR PHONE -->
                    <div class="mb-3">
                        <label for="modal_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="modal_phone" name="phone" required 
                               maxlength="11" 
                               pattern="[0-9]{11}" 
                               inputmode="numeric" 
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)"
                               placeholder="09123456789">
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label for="modal_region" class="form-label">Region</label><select class="form-select" id="modal_region" name="region" required></select></div>
                        <div class="col-md-6" id="modal_province_wrapper"><label for="modal_province" class="form-label">Province</label><select class="form-select" id="modal_province" name="province" required></select></div>
                        <div class="col-md-6"><label for="modal_city" class="form-label">City/Municipality</label><select class="form-select" id="modal_city" name="city" required></select></div>
                        <div class="col-md-6"><label for="modal_barangay" class="form-label">Barangay</label><select class="form-select" id="modal_barangay" name="barangay" required></select></div>
                    </div>
                    <div class="mb-3"><label for="modal_street_address" class="form-label">Street Name, Building, House No.</label><input type="text" class="form-control" id="modal_street_address" name="street_address" required></div>
                    
                    <!-- MODIFICATION FOR POSTAL CODE -->
                    <div class="mb-3">
                        <label for="modal_postal_code" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="modal_postal_code" name="postal_code" required 
                               maxlength="4" 
                               pattern="[0-9]{4}" 
                               inputmode="numeric" 
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,4)">
                    </div>
                    
                    <div class="form-check"><input type="checkbox" name="is_default" value="1" id="modal_is_default"><label class="form-check-label" for="modal_is_default">Set as default address</label></div>
                </div>
                <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-warning">Save Address</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=PHP"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shippingOptions = <?= json_encode($shipping_options); ?>;
    
    // UI Elements
    const paymentMethodRadios = document.querySelectorAll('.payment-method-radio');
    const codSubmitButton = document.getElementById('cod-submit-button');
    const paypalButtonContainer = document.getElementById('paypal-button-container');
    const loaderOverlay = document.getElementById('loader-overlay');
    const checkoutForm = document.getElementById('checkout-form');
    let paypalButtonsRendered = false;
    
    const addressListModalEl = document.getElementById('addressListModal');
    const addressModalEl = document.getElementById('addressModal');
    const addressListModal = addressListModalEl ? new bootstrap.Modal(addressListModalEl) : null;
    const addressModal = addressModalEl ? new bootstrap.Modal(addressModalEl) : null;
    const summaryCard = document.querySelector('.order-summary-card');
    const selectedAddressDisplay = document.getElementById('selected-address-display');
    let actionAfterClose = { type: 'none', target: null };

    // --- VOUCHER UI ELEMENTS ---
    const voucherInput = document.getElementById('voucher-code-input');
    const voucherMessage = document.getElementById('voucher-message');
    const discountRow = document.getElementById('discount-row');
    const discountAmountEl = document.getElementById('cart-discount');
    const appliedCodeEl = document.getElementById('applied-voucher-code');
    const totalEl = document.getElementById('cart-total'); // Use the correct ID
    const applyVoucherBtn = document.getElementById('apply-voucher-btn'); // Get the button
    // --- END VOUCHER UI ---
    
    function findShippingCost(costs, region) {
        const upperRegion = (region || 'default').toUpperCase().trim();
        for (const key in costs) {
            if (key.toUpperCase() === upperRegion) { return costs[key]; }
        }
        return costs['default'] || 0;
    }

    function updateTotals() {
        if (!summaryCard) return;
        
        const subtotal = parseFloat(summaryCard.dataset.subtotal);
        const shippingFeeDisplay = document.getElementById("cart-shipping");
        const shippingFeeLabel = document.getElementById("shipping-fee-label");
        const totalDisplay = document.getElementById("cart-total");
        const selectedCourierEl = document.querySelector('.shipping-radio:checked');
        
        if (!selectedCourierEl) {
             // Handle case where no address is selected yet
             const region = selectedAddressDisplay ? selectedAddressDisplay.dataset.region : 'default';
             if (!region) { // No default address at all
                shippingFeeDisplay.textContent = 'Select address';
                totalDisplay.textContent = `₱${subtotal.toFixed(2)}`;
                return;
             }
        }
        
        // --- RESTORED LOGIC ---
        const courierKey = selectedCourierEl ? selectedCourierEl.value : 'standard'; // Default to standard if somehow null
        const region = selectedAddressDisplay ? selectedAddressDisplay.dataset.region : 'default';
        const shippingCost = findShippingCost(shippingOptions[courierKey].costs, region);
        // --- END RESTORED LOGIC ---
        
        shippingFeeLabel.textContent = `Shipping (${shippingOptions[courierKey].name})`;
        shippingFeeDisplay.textContent = `₱${shippingCost.toFixed(2)}`;
        
        // --- VOUCHER CALCULATION ---
        const discountText = discountAmountEl.innerText.replace(/[^0-9.]/g, '');
        const discountAmount = parseFloat(discountText) || 0;
        // --- END VOUCHER CALCULATION ---

        const finalTotal = subtotal + shippingCost - discountAmount; // Updated Total
        totalDisplay.textContent = `₱${finalTotal.toFixed(2)}`;
    }

    // --- VOUCHER SCRIPT ---
    if (applyVoucherBtn) { // Check if the button exists
        applyVoucherBtn.addEventListener('click', function(e) { // Listen for CLICK
            e.preventDefault(); 
            const code = voucherInput.value;
            if (!code) {
                voucherMessage.innerHTML = `<div class="alert alert-danger p-2 small" role="alert">Please enter a code.</div>`;
                return;
            }

            const subtotal = parseFloat(summaryCard.dataset.subtotal);
            // FIX: Read from the calculated text, not a non-existent attribute
            const shippingText = document.getElementById('cart-shipping').innerText.replace(/[^0-9.]/g, '');
            const shipping = parseFloat(shippingText) || 0; 

            voucherMessage.innerHTML = `<div class="alert alert-secondary p-2 small" role="alert">Checking code...</div>`;

            fetch('apply_voucher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    voucher_code: code,
                    subtotal: subtotal,
                    shipping: shipping // Send current shipping
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    voucherMessage.innerHTML = `<div class="alert alert-success p-2 small" role="alert">${data.message}</div>`;
                    discountRow.style.display = 'flex'; // Show discount row
                    appliedCodeEl.innerText = data.voucher_code;
                    discountAmountEl.innerText = data.discount_formatted;
                    totalEl.innerText = data.total_formatted; // Use the total from the server
                } else {
                    voucherMessage.innerHTML = `<div class="alert alert-danger p-2 small" role="alert">${data.message}</div>`;
                    discountRow.style.display = 'none'; // Hide discount row
                    appliedCodeEl.innerText = '';
                    discountAmountEl.innerText = '- ₱0.00';
                    updateTotals(); // Recalculate total without discount
                }
            })
            .catch(error => {
                console.error('Error:', error);
                voucherMessage.innerHTML = `<div class="alert alert-danger p-2 small" role="alert">An unexpected error occurred.</div>`;
            });
        });
    }
    // --- END VOUCHER SCRIPT ---


    function togglePaymentUI() {
        const selectedPaymentMethodRadio = document.querySelector('.payment-method-radio:checked');
        if (!selectedPaymentMethodRadio) return; 
        
        const selectedPaymentMethod = selectedPaymentMethodRadio.value;
        
        if (selectedPaymentMethod === 'PayPal') {
            if (codSubmitButton) codSubmitButton.style.display = 'none';
            if (paypalButtonContainer) paypalButtonContainer.style.display = 'block';
            if (!paypalButtonsRendered) {
                renderPayPalButtons();
                paypalButtonsRendered = true;
            }
        } else { // Cash on Delivery
            if (codSubmitButton) codSubmitButton.style.display = 'block';
            if (paypalButtonContainer) paypalButtonContainer.style.display = 'none';
        }
    }
    
    function renderPayPalButtons() {
        if (!document.getElementById('paypal-button-container')) return;
        paypal.Buttons({
            createOrder: function(data, actions) {
                const addressId = document.getElementById('selected_address_id').value;
                const shippingKey = document.querySelector('.shipping-radio:checked').value;
                if (!addressId) {
                    alert('Please select a shipping address first.');
                    return Promise.reject(new Error('No address selected'));
                }
                // Server will use session to get discount amount
                return fetch('create_paypal_order.php', {
                    method: 'post',
                    headers: { 'content-type': 'application/json' },
                    body: JSON.stringify({ addressId: addressId, shippingKey: shippingKey })
                }).then(res => res.json()).then(data => data.id);
            },
            onApprove: function(data, actions) {
                loaderOverlay.style.display = 'flex';
                const addressId = document.getElementById('selected_address_id').value;
                const shippingKey = document.querySelector('.shipping-radio:checked').value;
                
                // Server will use session to get discount amount
                return fetch('capture_paypal_order.php', {
                    method: 'post',
                    headers: { 'content-type': 'application/json' },
                    body: JSON.stringify({
                        orderID: data.orderID,
                        addressId: addressId,
                        shippingKey: shippingKey
                    })
                }).then(res => res.json()).then(function(details) {
                    if (details.success) {
                        window.location.href = 'checkout.php'; // Will show success message
                    } else {
                        loaderOverlay.style.display = 'none';
                        alert('Transaction failed. Please try again.');
                        console.error('Capture failed:', details);
                    }
                }).catch(err => {
                    loaderOverlay.style.display = 'none';
                    alert('An error occurred. Please check the console for details.');
                    console.error(err);
                });
            },
            onError: function (err) {
                alert('An error occurred with the PayPal payment.');
                console.error('PayPal Error:', err);
            },
            onCancel: function (data) {
                console.log('Payment cancelled. OrderID:', data.orderID);
            }
        }).render('#paypal-button-container');
    }

    // --- Address Modal Logic ---
    document.querySelectorAll('.address-select-option').forEach(option => {
        option.addEventListener('click', function(event) {
            if (event.target.classList.contains('edit-address-btn') || event.target.closest('.edit-address-btn')) return;
            const data = this.dataset;
            document.getElementById('selected_address_id').value = data.id;
            
            // Check if these elements exist before setting textContent
            if(document.getElementById('display_recipient_name')) document.getElementById('display_recipient_name').textContent = data.recipientName;
            if(document.getElementById('display_phone')) document.getElementById('display_phone').textContent = data.phone;
            if(document.getElementById('display_address_line1')) document.getElementById('display_address_line1').textContent = `${data.streetAddress}, ${data.barangay}`;
            if(document.getElementById('display_address_line2')) document.getElementById('display_address_line2').textContent = `${data.city}, ${data.province} ${data.postalCode}`;
            
            selectedAddressDisplay.dataset.region = data.region;
            updateTotals();
            const noAddressContainer = document.getElementById('no-address-container');
            if(noAddressContainer) noAddressContainer.style.display = 'none';
            if(addressListModal) addressListModal.hide();
        });
    });

    document.querySelectorAll('.edit-address-btn').forEach(button => {
        button.addEventListener('click', function () {
            actionAfterClose = { type: 'edit', target: this };
            if (addressListModal) addressListModal.hide();
        });
    });
    
    document.getElementById('addNewAddressBtn')?.addEventListener('click', function() {
        actionAfterClose = { type: 'add', target: null };
        if (addressListModal) addressListModal.hide();
    });

    if (addressListModalEl) {
        addressListModalEl.addEventListener('hidden.bs.modal', function () {
            if (actionAfterClose.type === 'edit' || actionAfterClose.type === 'add') {
                if(addressModal) addressModal.show(actionAfterClose.target);
            }
            actionAfterClose = { type: 'none', target: null };
        });
    }

    if(addressModalEl) {
        // (Address API logic remains unchanged)
        const form = addressModalEl.querySelector('form');
        const modalTitle = addressModalEl.querySelector('.modal-title');
        const fields = { id: form.querySelector('#modal_address_id'), recipient_name: form.querySelector('#modal_recipient_name'), phone: form.querySelector('#modal_phone'), region: form.querySelector('#modal_region'), province: form.querySelector('#modal_province'), city: form.querySelector('#modal_city'), barangay: form.querySelector('#modal_barangay'), street_address: form.querySelector('#modal_street_address'), postal_code: form.querySelector('#modal_postal_code'), is_default: form.querySelector('#modal_is_default') };
        const textFields = { region: form.querySelector('#modal_region_text'), province: form.querySelector('#modal_province_text'), city: form.querySelector('#modal_city_text'), barangay: form.querySelector('#modal_barangay_text') };
        const api = { fetchRegions: () => fetch('https://psgc.gitlab.io/api/regions/').then(res => res.json()), fetchProvinces: code => fetch(`https://psgc.gitlab.io/api/regions/${code}/provinces/`).then(res => res.json()), fetchCitiesFromRegion: code => fetch(`https://psgc.gitlab.io/api/regions/${code}/cities-municipalities/`).then(res => res.json()), fetchCities: code => fetch(`https://psgc.gitlab.io/api/provinces/${code}/cities-municipalities/`).then(res => res.json()), fetchBarangays: code => fetch(`https://psgc.gitlab.io/api/cities-municipalities/${code}/barangays/`).then(res => res.json()) };
        const populateSelect = (el, data) => { el.innerHTML = '<option value="" selected disabled>Select...</option>'; data.sort((a, b) => a.name.localeCompare(b.name)).forEach(item => el.add(new Option(item.name, item.code))); };
        const updateTextField = (selectEl, hiddenEl) => { hiddenEl.value = selectEl.selectedIndex > 0 ? selectEl.options[selectEl.selectedIndex].text : ''; };
        fields.region.addEventListener('change', () => {
            updateTextField(fields.region, textFields.region);
            const provinceWrapper = document.getElementById('modal_province_wrapper');
            ['province', 'city', 'barangay'].forEach(key => { fields[key].innerHTML = ''; if(textFields[key]) textFields[key].value = ''; });
            if (fields.region.value === '130000000') { provinceWrapper.style.display = 'none'; fields.province.required = false; api.fetchCitiesFromRegion(fields.region.value).then(data => populateSelect(fields.city, data)); }
            else { provinceWrapper.style.display = 'block'; fields.province.required = true; if(fields.region.value) api.fetchProvinces(fields.region.value).then(data => populateSelect(fields.province, data)); }
        });
        fields.province.addEventListener('change', () => { updateTextField(fields.province, textFields.province); fields.city.innerHTML = ''; fields.barangay.innerHTML = ''; if(textFields.city) textFields.city.value = ''; if(textFields.barangay) textFields.barangay.value = ''; if(fields.province.value) api.fetchCities(fields.province.value).then(data => populateSelect(fields.city, data)); });
        fields.city.addEventListener('change', () => { updateTextField(fields.city, textFields.city); fields.barangay.innerHTML = ''; if(textFields.barangay) textFields.barangay.value = ''; if(fields.city.value) api.fetchBarangays(fields.city.value).then(data => populateSelect(fields.barangay, data)); });
        fields.barangay.addEventListener('change', () => updateTextField(fields.barangay, textFields.barangay));
        addressModalEl.addEventListener('show.bs.modal', async function (event) {
            form.reset();
            const provinceWrapper = document.getElementById('modal_province_wrapper');
            const button = event.relatedTarget;
            const isEdit = button && button.classList.contains('edit-address-btn');
            modalTitle.textContent = isEdit ? 'Edit Address' : 'Add New Address';
            const regions = await api.fetchRegions();
            populateSelect(fields.region, regions);
            if (isEdit) {
                const data = button.dataset;
                fields.id.value = data.id; fields.recipient_name.value = data.recipientName; fields.phone.value = data.phone; fields.street_address.value = data.streetAddress; fields.postal_code.value = data.postalCode; fields.is_default.checked = data.default == '1';
                const region = data.region ? regions.find(r => r.name.toUpperCase() === data.region.toUpperCase()) : null;
                if (region) {
                    fields.region.value = region.code; updateTextField(fields.region, textFields.region);
                    if(region.code === '130000000'){
                        provinceWrapper.style.display = 'none'; fields.province.required = false;
                        const cities = await api.fetchCitiesFromRegion(region.code); populateSelect(fields.city, cities);
                        const city = data.city ? cities.find(c => c.name.toUpperCase() === data.city.toUpperCase()) : null;
                        if (city) {
                           fields.city.value = city.code; updateTextField(fields.city, textFields.city);
                           const barangays = await api.fetchBarangays(city.code); populateSelect(fields.barangay, barangays);
                           const barangay = data.barangay ? barangays.find(b => b.name.toUpperCase() === data.barangay.toUpperCase()) : null;
                           if (barangay) { fields.barangay.value = barangay.code; updateTextField(fields.barangay, textFields.barangay); }
                        }
                    } else {
                        provinceWrapper.style.display = 'block'; fields.province.required = true;
                        const provinces = await api.fetchProvinces(region.code); populateSelect(fields.province, provinces);
                        const province = data.province ? provinces.find(p => p.name.toUpperCase() === data.province.toUpperCase()) : null;
                        if(province) {
                            fields.province.value = province.code; updateTextField(fields.province, textFields.province);
                            const cities = await api.fetchCities(province.code); populateSelect(fields.city, cities);
                            const city = data.city ? cities.find(c => c.name.toUpperCase() === data.city.toUpperCase()) : null;
                            if(city) {
                               fields.city.value = city.code; updateTextField(fields.city, textFields.city);
                               const barangays = await api.fetchBarangays(city.code); populateSelect(fields.barangay, barangays);
                               const barangay = data.barangay ? barangays.find(b => b.name.toUpperCase() === data.barangay.toUpperCase()) : null;
                               if(barangay) { fields.barangay.value = barangay.code; updateTextField(fields.barangay, textFields.barangay); }
                            }
                        }
                    }
                }
            } else {
                provinceWrapper.style.display = 'block'; fields.province.required = true;
                fields.province.innerHTML = ''; fields.city.innerHTML = ''; fields.barangay.innerHTML = '';
            }
        });
    }

    // --- Final Initialization Calls ---
    document.querySelectorAll('.shipping-radio').forEach(radio => radio.addEventListener('change', updateTotals));
    
    // Only add listeners if payment methods exist on the page
    if (paymentMethodRadios.length > 0) {
        paymentMethodRadios.forEach(radio => radio.addEventListener('change', togglePaymentUI));
        togglePaymentUI(); // Initial check for payment UI
    }
    
    checkoutForm.addEventListener('submit', function() {
        // Show loader only for COD, PayPal handles its own loading.
        const selectedPaymentMethodRadio = document.querySelector('.payment-method-radio:checked');
        if(selectedPaymentMethodRadio && selectedPaymentMethodRadio.value === 'Cash on Delivery') {
            loaderOverlay.style.display = 'flex';
        }
    });

    updateTotals();
});
</script>



<?php include 'includes/footer.php'; ?>
