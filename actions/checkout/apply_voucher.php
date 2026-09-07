<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
require "config/db.php";
header('Content-Type: application/json'); // Set header for JSON response

$response = ['success' => false, 'message' => 'An error occurred.'];

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to apply a voucher.';
    echo json_encode($response);
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Get data from the frontend
$data = json_decode(file_get_contents('php://input'), true);
$voucher_code = trim(strtoupper($data['voucher_code'] ?? ''));
$subtotal = floatval($data['subtotal'] ?? 0);
$shipping = floatval($data['shipping'] ?? 0);

if (empty($voucher_code)) {
    $response['message'] = 'Please enter a voucher code.';
    echo json_encode($response);
    exit;
}

if ($subtotal <= 0) {
    $response['message'] = 'Your cart is empty.';
    echo json_encode($response);
    exit;
}

try {
    // 3. Find the voucher in the database
    $stmt = $pdo->prepare("
        SELECT * FROM vouchers 
        WHERE code = ? 
        AND is_active = 1 
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$voucher_code]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        $response['message'] = 'Invalid or expired voucher code.';
        echo json_encode($response);
        exit;
    }

    // 4. Check if user has already used this voucher
    $stmt_usage = $pdo->prepare("
        SELECT COUNT(*) FROM user_voucher_usage 
        WHERE voucher_id = ? AND user_id = ?
    ");
    $stmt_usage->execute([$voucher['id'], $user_id]);
    $usage_count = $stmt_usage->fetchColumn();

    if ($usage_count >= $voucher['max_uses_customer']) {
        $response['message'] = 'You have already used this voucher code the maximum number of times.';
        echo json_encode($response);
        exit;
    }

    // 5. Calculate the discount
    $discount_amount = 0;
    if ($voucher['discount_type'] === 'percent') {
        $discount_amount = $subtotal * ($voucher['discount_value'] / 100);
    } else { // 'fixed'
        $discount_amount = $voucher['discount_value'];
        // Ensure fixed discount doesn't make subtotal negative
        if ($discount_amount > $subtotal) {
            $discount_amount = $subtotal;
        }
    }

    $new_total = ($subtotal + $shipping) - $discount_amount;

    // 6. Store discount details in session to be used on order creation
    $_SESSION['voucher_id'] = $voucher['id'];
    $_SESSION['voucher_code'] = $voucher['code'];
    $_SESSION['discount_amount'] = $discount_amount;

    // 7. Send success response
    $response = [
        'success' => true,
        'message' => 'Voucher applied successfully!',
        'discount_formatted' => '- ₱' . number_format($discount_amount, 2),
        'total_formatted' => '₱' . number_format($new_total, 2),
        'voucher_code' => $voucher['code']
    ];
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}
?>
