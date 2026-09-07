<?php
session_start();
include 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Fetch cart items
$stmt = $pdo->prepare("SELECT c.*, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirect if cart is empty
if (count($cart_items) == 0) {
    $_SESSION['message'] = "Your cart is empty. Please add items before checking out.";
    header("Location: cart.php");
    exit;
}

// Fetch user's saved addresses
$stmt_addr = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt_addr->execute([$user_id]);
$saved_addresses = $stmt_addr->fetchAll(PDO::FETCH_ASSOC);

// Define shipping options
$shipping_options = [
    'standard' => ['name' => 'Standard Shipping', 'cost' => 100.00, 'eta' => '5-7 Business Days'],
    'express' => ['name' => 'Express Shipping', 'cost' => 250.00, 'eta' => '1-2 Business Days'],
];

// Calculate subtotal
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $address_option = $_POST['shipping_address_option'] ?? '';
    $shipping_option_key = $_POST['shipping_option'] ?? 'standard';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    
    $shipping_address = "";
    $shipping_city = "";
    $shipping_phone = "";
    
    // Determine shipping cost
    $shipping_cost = $shipping_options[$shipping_option_key]['cost'] ?? 0;
    $final_total = $subtotal + $shipping_cost; // Add voucher logic here later

    // Determine shipping details
    if (is_numeric($address_option)) {
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE address_id = ? AND user_id = ?");
        $stmt->execute([$address_option, $user_id]);
        $selected_address = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($selected_address) {
            $shipping_address = $selected_address['address_line'];
            $shipping_city = $selected_address['city'];
            $shipping_phone = $selected_address['phone'];
        }
    } elseif ($address_option === 'new') {
        $shipping_address = filter_input(INPUT_POST, 'new_address', FILTER_SANITIZE_STRING);
        $shipping_city = filter_input(INPUT_POST, 'new_city', FILTER_SANITIZE_STRING);
        $shipping_phone = filter_input(INPUT_POST, 'new_phone', FILTER_SANITIZE_STRING);

        // Phone validation (only numbers, must be 11 digits)
        if (!preg_match('/^[0-9]{11}$/', $shipping_phone)) {
            $error = "Please enter a valid 11-digit phone number.";
        }
    }

    // Validation
    if (empty($shipping_address) || empty($shipping_city) || empty($shipping_phone) || empty($payment_method)) {
        $error = "Please provide a complete shipping address and select a payment method.";
    } elseif (empty($error)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, shipping_address, city, phone, total_amount, payment_method, shipping_method, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $shipping_address, $shipping_city, $shipping_phone, $final_total, $payment_method, $shipping_options[$shipping_option_key]['name'], $shipping_cost]);
            $order_id = $pdo->lastInsertId();

            $stmt_items = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $stmt_items->execute([$order_id, $item['product_id'], $item['product_name'], $item['quantity'], $item['price']]);
            }

            $stmt_clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt_clear->execute([$user_id]);
            
            $pdo->commit();
            $_SESSION['message'] = "Your order has been placed successfully!";
            header("Location: profile.php?page=orders");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to place order. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<style>
    .checkout-container { color: #e0e0e0; }
    .checkout-card, .order-summary-card {
        background-color: #1e1e1e;
        border: 1px solid #333;
        border-radius: 10px;
        padding: 2rem;
    }
    .order-summary-card ul { list-style: none; padding: 0; }
    .order-summary-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        background-color: #2a2a2a;
        border: 1px solid #444;
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }
    .order-summary-item img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
        margin-right: 1rem;
    }
    .select-card {
        position: relative;
        padding: 1.5rem;
        border: 2px solid #444;
        border-radius: 8px;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: border-color 0.2s ease-in-out, background-color 0.2s ease-in-out;
    }
    .select-card:hover { background-color: #2a2a2a; }
    .select-card input[type="radio"] {
        position: absolute; opacity: 0; width: 100%; height: 100%; top: 0; left: 0; cursor: pointer;
    }
    .select-card .content-wrapper { display: flex; align-items: center; }
    .select-card .radio-custom {
        width: 22px; height: 22px; border-radius: 50%; border: 2px solid #888;
        margin-right: 1.5rem; flex-shrink: 0; display: flex; align-items: center;
        justify-content: center; transition: all 0.2s ease-in-out; background-color: transparent;
    }
    .select-card .radio-custom::after {
        content: ''; display: block; width: 6px; height: 12px; border: solid white;
        border-width: 0 3px 3px 0; opacity: 0; transform: rotate(45deg) scale(0.5);
        transition: all 0.2s ease-in-out;
    }
    .select-card input[type="radio"]:checked ~ .content-wrapper .radio-custom {
        background-color: #ffc107; border-color: #ffc107;
    }
    .select-card input[type="radio"]:checked ~ .content-wrapper .radio-custom::after {
        opacity: 1; transform: rotate(45deg) scale(1);
    }
    #new-address-form {
        padding: 1.5rem; background-color: #2a2a2a; border-radius: 8px; margin-top: 1rem; display: none;
    }
    .form-control {
        background-color: #2a2a2a; color: #fff; border-color: #444;
    }
    .form-control:focus {
        background-color: #2a2a2a; color: #fff; border-color: #ffc107;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
    .voucher-input-group {
        display: flex;
    }
    .voucher-input-group .form-control {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    .voucher-input-group .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    /* Hide number input spinners */
    input.no-spinner::-webkit-outer-spin-button,
    input.no-spinner::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input.no-spinner[type=number] {
        -moz-appearance: textfield;
    }
</style>

<div class="container my-5 checkout-container" style="padding-top: 80px;">
    <div class="row">
        <div class="col-12 text-center mb-4"><h1>Checkout</h1></div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-5">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="checkout-card">
                    <!-- Shipping Address -->
                    <h4 class="mb-4">Shipping Address</h4>
                    <?php foreach ($saved_addresses as $address): ?>
                    <label class="select-card">
                        <input type="radio" name="shipping_address_option" value="<?= $address['address_id'] ?>" class="address-radio" <?= $address['is_default'] ? 'checked' : '' ?>>
                        <div class="content-wrapper">
                            <span class="radio-custom"></span>
                            <div>
                                <strong class="d-block"><?= htmlspecialchars($address['address_line']) ?>, <?= htmlspecialchars($address['city']) ?></strong>
                                <span class="text-muted"><?= htmlspecialchars($address['phone']) ?></span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    <label class="select-card">
                        <input type="radio" name="shipping_address_option" value="new" class="address-radio" <?= empty($saved_addresses) ? 'checked' : '' ?>>
                        <div class="content-wrapper"><span class="radio-custom"></span><div><strong>Add a New Address</strong></div></div>
                    </label>
                    <div id="new-address-form">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="new_address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="new_address" name="new_address" placeholder="1234 Main St">
                            </div>
                            <div class="col-12">
                                <label for="new_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="new_city" name="new_city" placeholder="Manila">
                            </div>
                            <div class="col-12">
                                <label for="new_phone" class="form-label">Phone</label>
                                <input type="text" 
                                       class="form-control no-spinner" 
                                       id="new_phone" 
                                       name="new_phone" 
                                       placeholder="09123456789" 
                                       pattern="[0-9]{11}" 
                                       inputmode="numeric" 
                                       maxlength="11"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)">
                            </div>
                        </div>
                    </div>
                    <hr class="my-4" style="border-color: #444;">
                    
                    <!-- Shipping Options -->
                    <h4 class="mb-4">Shipping Options</h4>
                    <?php foreach ($shipping_options as $key => $option): ?>
                    <label class="select-card">
                        <input type="radio" name="shipping_option" value="<?= $key ?>" class="shipping-radio" data-cost="<?= $option['cost'] ?>" <?= $key === 'standard' ? 'checked' : '' ?>>
                        <div class="content-wrapper">
                            <span class="radio-custom"></span>
                            <div>
                                <strong class="d-block"><?= htmlspecialchars($option['name']) ?> - ₱<?= number_format($option['cost'], 2) ?></strong>
                                <span class="text-muted">ETA: <?= htmlspecialchars($option['eta']) ?></span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    <hr class="my-4" style="border-color: #444;">

                    <!-- Payment Method -->
                    <h4 class="mb-3">Payment Method</h4>
                    <label class="select-card">
                         <input type="radio" name="payment_method" value="Cash on Delivery" checked>
                         <div class="content-wrapper"><span class="radio-custom"></span><div><strong>Cash on Delivery</strong></div></div>
                    </label>
                     <label class="select-card opacity-50">
                         <input type="radio" name="payment_method" value="GCash" disabled>
                         <div class="content-wrapper"><span class="radio-custom"></span><div><strong>GCash</strong> <small>(Unavailable)</small></div></div>
                    </label>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary-card" data-subtotal="<?= $subtotal ?>">
                    <h4 class="mb-4">Order Summary</h4>
                    <ul class="mb-4">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="order-summary-item">
                                <img src="uploads/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                <div class="flex-grow-1">
                                    <h6 class="my-0 small"><?= htmlspecialchars($item['product_name']) ?></h6>
                                    <small class="text-muted">Qty: <?= $item['quantity'] ?></small>
                                </div>
                                <span class="text-light small">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mb-3">
                        <label class="form-label">Voucher Code</label>
                        <div class="voucher-input-group">
                            <input type="text" class="form-control" placeholder="Enter code">
                            <button type="button" class="btn btn-secondary">Apply</button>
                        </div>
                    </div>
                    
                    <hr style="border-color: #444;">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
                    <div class="d-flex justify-content-between mb-3"><span>Shipping Fee</span><span id="shipping-fee-display">₱<?= number_format($shipping_options['standard']['cost'], 2) ?></span></div>
                    <hr style="border-color: #444;">
                    <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><strong id="total-display">₱<?= number_format($subtotal + $shipping_options['standard']['cost'], 2) ?></strong></div>
                    <button class="w-100 btn btn-warning btn-lg mt-4" type="submit">Place Order</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Address form toggle logic
    const addressRadios = document.querySelectorAll('.address-radio');
    const newAddressForm = document.getElementById('new-address-form');
    const newAddressInputs = newAddressForm.querySelectorAll('input');
    function toggleNewAddressForm() {
        const selectedOption = document.querySelector('.address-radio:checked').value;
        if (selectedOption === 'new') {
            newAddressForm.style.display = 'block';
            newAddressInputs.forEach(input => input.required = true);
        } else {
            newAddressForm.style.display = 'none';
            newAddressInputs.forEach(input => input.required = false);
        }
    }
    addressRadios.forEach(radio => radio.addEventListener('change', toggleNewAddressForm));
    toggleNewAddressForm();

    // Shipping cost and total update logic
    const shippingRadios = document.querySelectorAll('.shipping-radio');
    const summaryCard = document.querySelector('.order-summary-card');
    const subtotal = parseFloat(summaryCard.dataset.subtotal);
    const shippingFeeDisplay = document.getElementById('shipping-fee-display');
    const totalDisplay = document.getElementById('total-display');

    function updateTotals() {
        const selectedShipping = document.querySelector('.shipping-radio:checked');
        const shippingCost = parseFloat(selectedShipping.dataset.cost);
        const finalTotal = subtotal + shippingCost;
        
        shippingFeeDisplay.textContent = ₱${shippingCost.toFixed(2)};
        totalDisplay.textContent = ₱${finalTotal.toFixed(2)};
    }
    shippingRadios.forEach(radio => radio.addEventListener('change', updateTotals));
});
</script>

<?php include 'includes/footer.php'; ?>
