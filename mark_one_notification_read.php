<?php
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Get the notification ID from the POSTed JSON data
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['id'] ?? null;

if (empty($notification_id)) {
    echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Security: Ensure we only update a notification belonging to the current user
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}