<?php
session_start();
require "config/db.php";

// Security: Check if admin is logged in and an order_id is provided
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' || !isset($_GET['order_id'])) {
    die("Access denied.");
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    die("Invalid Order ID.");
}

// Fetch all order data in one go
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        u.first_name, u.last_name, u.email
    FROM orders o 
    JOIN users u ON o.user_id = u.id
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Fetch order items
$stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$payment_paid = (!empty($order['transaction_id']) && $order['transaction_id'] !== 'PENDING_NULL');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= htmlspecialchars($order['order_id']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #fff; }
        .invoice-container { max-width: 800px; margin: 30px auto; padding: 30px; border: 1px solid #dee2e6; }
        .invoice-header { text-align: center; margin-bottom: 40px; }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .invoice-container { border: none; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="invoice-container">
    <div class="invoice-header">
        <h1>Invoice / Packing Slip</h1>
        <p><strong>New Grounds Gym Equipments Repository</strong></p>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Billed To:</strong><br>
            <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br>
            <?= htmlspecialchars($order['email']) ?>
        </div>
        <div class="col-6 text-end">
            <strong>Order ID:</strong> #<?= htmlspecialchars($order['order_id']) ?><br>
            <strong>Order Date:</strong> <?= date('F j, Y', strtotime($order['order_date'])) ?><br>
            <strong>Order Status:</strong> <?= htmlspecialchars($order['status']) ?>
        </div>
    </div>
    
    <div class="mb-4">
        <strong>Ship To:</strong><br>
        <?= htmlspecialchars($order['shipping_address']) ?><br>
        Contact: <?= htmlspecialchars($order['phone']) ?>
    </div>

    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Item</th>
                <th class="text-center">Quantity</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end">₱<?= number_format($item['price'], 2) ?></td>
                <td class="text-end">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end">Subtotal</td>
                <td class="text-end">₱<?= number_format($order['total_amount'] - $order['shipping_cost'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end">Shipping (<?= htmlspecialchars($order['shipping_method']) ?>)</td>
                <td class="text-end">₱<?= number_format($order['shipping_cost'], 2) ?></td>
            </tr>
            <tr class="fw-bold table-light">
                <td colspan="3" class="text-end">Grand Total</td>
                <td class="text-end">₱<?= number_format($order['total_amount'], 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-4">
        <div class="col-6">
            <strong>Payment Details:</strong><br>
            Method: <?= htmlspecialchars($order['payment_method']) ?><br>
            Status: <?= $payment_paid ? 'Paid' : 'Unpaid' ?><br>
            <?php if ($payment_paid): ?>
                Transaction ID: <?= htmlspecialchars($order['transaction_id']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center mt-5 no-print">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <a href="admin_dashboard.php?view=orders" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

</body>
</html>