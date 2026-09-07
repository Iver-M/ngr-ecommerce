<?php
// create_paypal_order.php
session_start();
include 'config/db.php';
include 'shipping_logic.php';

header('Content-Type: application/json');

// --- Basic Security Checks ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

$json_str = file_get_contents('php://input');
$json_obj = json_decode($json_str);

$user_id = $_SESSION['user_id'];
$address_id = $json_obj->addressId ?? null;
$shipping_key = $json_obj->shippingKey ?? 'standard';

if (!$address_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Shipping address ID is missing.']);
    exit;
}

try {
    // --- 1. Calculate the Order Total ---

    // Get selected address
    $stmt_addr = $pdo->prepare("SELECT region FROM user_addresses WHERE address_id = ? AND user_id = ?");
    $stmt_addr->execute([$address_id, $user_id]);
    $selected_address = $stmt_addr->fetch(PDO::FETCH_ASSOC);

    if (!$selected_address) {
        throw new Exception("Selected address not found for the user.");
    }
    
    // Get selected cart items to calculate subtotal
    $stmt_cart = $pdo->prepare("SELECT c.price, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND c.is_selected = 1");
    $stmt_cart->execute([$user_id]);
    $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);
    
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    // Get shipping cost
    $shipping_cost = get_shipping_cost($selected_address['region'], $shipping_key, $shipping_options);

    // Get voucher discount from the session
    $discount_amount = $_SESSION['discount_amount'] ?? 0.00;

    // Calculate the final total
    $final_total = ($subtotal + $shipping_cost) - $discount_amount;
    
    // Ensure total is not negative or zero, as PayPal requires a positive value
    $final_total = max(0.01, $final_total);
    
    // --- 2. Get PayPal Access Token ---
    $ch_auth = curl_init();
    curl_setopt($ch_auth, CURLOPT_URL, PAYPAL_API_URL . '/v1/oauth2/token');
    curl_setopt($ch_auth, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch_auth, CURLOPT_POST, 1);
    curl_setopt($ch_auth, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch_auth, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch_auth, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
    $result_auth = curl_exec($ch_auth);
    if(curl_errno($ch_auth)){ throw new Exception(curl_error($ch_auth)); }
    curl_close($ch_auth);
    $auth_data = json_decode($result_auth);
    $access_token = $auth_data->access_token;
    
    // --- 3. Create PayPal Order with Final Amount ---
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => 'PHP',
                'value' => number_format($final_total, 2, '.', '') // Use the calculated final total
            ]
        ]]
    ];

    $ch_order = curl_init();
    curl_setopt($ch_order, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders');
    curl_setopt($ch_order, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch_order, CURLOPT_POST, 1);
    curl_setopt($ch_order, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch_order, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    
    $result_order = curl_exec($ch_order);
    if(curl_errno($ch_order)){ throw new Exception(curl_error($ch_order)); }
    curl_close($ch_order);
    
    $order_data = json_decode($result_order);
    
    if (isset($order_data->id)) {
        echo json_encode(['id' => $order_data->id]);
    } else {
        http_response_code(500);
        echo json_encode($order_data); // Output PayPal error for debugging
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>