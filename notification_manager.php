<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

function sendOrderConfirmationEmail($pdo, $order_id) {
    // 1. Fetch all necessary order and customer data
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            u.first_name, 
            u.last_name, 
            u.email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Log error or handle case where order is not found
        error_log("Could not find order #{$order_id} to send confirmation email.");
        return; 
    }

    // 2. Fetch the items in the order
    $stmt_items = $pdo->prepare("
        SELECT oi.product_name, oi.quantity, oi.price 
        FROM order_items oi 
        WHERE oi.order_id = ?
    ");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // 3. Build the HTML email body
    $item_list_html = "";
    foreach ($items as $item) {
        $item_list_html .= "<tr>
            <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($item['product_name']) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>" . $item['quantity'] . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>₱" . number_format($item['price'], 2) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>₱" . number_format($item['quantity'] * $item['price'], 2) . "</td>
        </tr>";
    }

    $email_body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #fd7e14;'>Thank You for Your Order!</h2>
                <p>Hello " . htmlspecialchars($order['first_name']) . ",</p>
                <p>We have received your order and are getting it ready for shipment. Here are the details:</p>
                
                <h3>Order Summary</h3>
                <p>
                    <strong>Order ID:</strong> #{$order['order_id']}<br>
                    <strong>Order Date:</strong> " . date('F j, Y, g:i A', strtotime($order['order_date'])) . "
                </p>

                <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                    <thead>
                        <tr style='background-color: #f2f2f2;'>
                            <th style='padding: 10px; text-align: left;'>Product</th>
                            <th style='padding: 10px;'>Quantity</th>
                            <th style='padding: 10px; text-align: right;'>Price</th>
                            <th style='padding: 10px; text-align: right;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$item_list_html}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='text-align: right; padding: 10px; border-top: 2px solid #ddd;'><strong>Subtotal:</strong></td>
                            <td style='text-align: right; padding: 10px; border-top: 2px solid #ddd;'>₱" . number_format($order['total_amount'] - $order['shipping_cost'], 2) . "</td>
                        </tr>
                         <tr>
                            <td colspan='3' style='text-align: right; padding: 10px;'><strong>Shipping:</strong></td>
                            <td style='text-align: right; padding: 10px;'>₱" . number_format($order['shipping_cost'], 2) . "</td>
                        </tr>
                        <tr style='font-size: 1.2em;'>
                            <td colspan='3' style='text-align: right; padding: 10px;'><strong>Total Amount:</strong></td>
                            <td style='text-align: right; padding: 10px;'><strong>₱" . number_format($order['total_amount'], 2) . "</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <h3>Shipping Information</h3>
                <p style='background-color: #f8f8f8; padding: 15px; border-radius: 5px;'>" . nl2br(htmlspecialchars($order['shipping_address'])) . "</p>

                <p>You can view your order details anytime by visiting the 'My Orders' section in your profile on our website.</p>
                <p>Thank you for shopping with us!</p>
                <p><strong>NewGrounds Gym Equipment Repository</strong></p>
            </div>
        </div>
    ";

    // 4. Send the email using your existing PHPMailer setup
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.sendgrid.net';
        $mail->SMTPAuth = true;
        $mail->Username = 'apikey'; 
        $mail->Password = getenv('SENDGRID_API_KEY');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        //Recipients
        $mail->setFrom('tainakaritsu348@gmail.com', 'New Grounds Gym Equipment Repository');
        $mail->addAddress($order['email'], $order['first_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your NewGrounds Order Confirmation ";
        $mail->Body    = $email_body;

        $mail->send();
    } catch (Exception $e) {
        // Optional: Log the error if the email fails to send
        error_log("Mailer Error for order #{$order_id}: " . $mail->ErrorInfo);
    }
}

