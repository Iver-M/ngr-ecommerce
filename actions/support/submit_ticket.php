<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
require 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $order_id = !empty($_POST['order_id']) ? $_POST['order_id'] : null;

    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO support_tickets (user_id, name, email, subject, message, order_id) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $name, $email, $subject, $message, $order_id]);
            // Set a session message for the success pop-up
            $_SESSION['message'] = "Your message has been sent! Our support team will get back to you shortly.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Sorry, there was an error sending your message. Please try again later.";
        }
    } else {
        $_SESSION['message'] = "Please fill out all required fields.";
    }
}

// Redirect back to the page the user was on
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
