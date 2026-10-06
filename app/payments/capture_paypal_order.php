<?php
// capture_paypal_order.php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../includes/shipping_logic.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

$json_str = file_get_contents('php://input');
$json_obj = json_decode($json_str);

$user_id = $_SESSION['user_id'];
$paypal_order_id = $json_obj->orderID ?? null;
$address_id = $json_obj->addressId ?? null;
$shipping_key = $json_obj->shippingKey ?? 'standard';

// Get Access Token
$ch_auth = curl_init();
curl_setopt($ch_auth, CURLOPT_URL, PAYPAL_API_URL . '/v1/oauth2/token');
curl_setopt($ch_auth, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_auth, CURLOPT_POST, 1);
curl_setopt($ch_auth, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch_auth, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
curl_setopt($ch_auth, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
$result_auth = curl_exec($ch_auth);
curl_close($ch_auth);
$auth_data = json_decode($result_auth);
$access_token = $auth_data->access_token;

// Capture Payment
$ch_capture = curl_init();
curl_setopt($ch_capture, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders/' . $paypal_order_id . '/capture');
curl_setopt($ch_capture, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_capture, CURLOPT_POST, 1);
curl_setopt($ch_capture, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch_capture, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
$result_capture = curl_exec($ch_capture);
curl_close($ch_capture);
$capture_data = json_decode($result_capture);


if (isset($capture_data->status) && $capture_data->status === 'COMPLETED') {
    $transaction_id = $capture_data->purchase_units[0]->payments->captures[0]->id;

    try {
        $pdo->beginTransaction();

        $stmt_addr = $pdo->prepare("SELECT * FROM user_addresses WHERE address_id = ? AND user_id = ?");
        $stmt_addr->execute([$address_id, $user_id]);
        $selected_address = $stmt_addr->fetch(PDO::FETCH_ASSOC);

        $stmt_cart = $pdo->prepare("SELECT c.*, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND c.is_selected = 1 FOR UPDATE");
        $stmt_cart->execute([$user_id]);
        $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0;
        foreach ($cart_items as $item) {
            if ($item['quantity'] > $item['stock']) {
                throw new Exception("Not enough stock for a selected item.");
            }
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shipping_cost = get_shipping_cost($selected_address['region'], $shipping_key, $shipping_options);
        $final_total = $subtotal + $shipping_cost;
        $shipping_method = $shipping_options[$shipping_key]['name'] . " to " . $selected_address['region'];
        $full_shipping_address = "{$selected_address['street_address']}, {$selected_address['barangay']}, {$selected_address['city']}, {$selected_address['province']} {$selected_address['postal_code']}, {$selected_address['region']}";

            // Update the INSERT statement to include the 'status' column
        $stmt_order = $pdo->prepare("INSERT INTO orders (user_id, shipping_address, city, phone, total_amount, payment_method, transaction_id, shipping_method, shipping_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed')");
        $stmt_order->execute([$user_id, $full_shipping_address, $selected_address['city'], $selected_address['phone'], $final_total, 'PayPal', $transaction_id, $shipping_method, $shipping_cost]);
        $order_id = $pdo->lastInsertId();

        // --- ADD THIS BLOCK ---
        // Create the first entry in the order status history for the timeline
        $initial_status = "Order Placed";
        $initial_remarks = "Your payment has been successfully processed.";
        $stmt_history = $pdo->prepare("INSERT INTO order_status_history (order_id, status, remarks) VALUES (?, ?, ?)");
        $stmt_history->execute([$order_id, $initial_status, $initial_remarks]);
        // --- END OF ADDED BLOCK ---

        $stmt_items = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
        $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($cart_items as $item) {
            $stmt_items->execute([$order_id, $item['product_id'], $item['product_name'], $item['quantity'], $item['price']]);
            $stmt_update_stock->execute([$item['quantity'], $item['product_id']]);
        }

        $stmt_clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND is_selected = 1");
        $stmt_clear->execute([$user_id]);

        $pdo->commit();
        
        $_SESSION['order_placed_successfully'] = true; // Set a flag for the redirect
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Payment capture failed.', 'details' => $capture_data]);
}