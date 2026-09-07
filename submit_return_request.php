<?php
session_start();
require_once 'auth_check.php';
require_once 'config/db.php';

// --- CONFIGURATION ---
define('UPLOAD_DIR', 'uploads/return_videos/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_MIME_TYPES', ['video/mp4', 'video/quicktime']);

// --- FUNCTION to send error response ---
function returnWithError($message) {
    $_SESSION['error'] = $message;
    header("Location: profile.php?page=orders");
    exit;
}

// --- VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_item_id'], $_POST['reason'], $_POST['quantity'])) {
    header("Location: profile.php?page=orders");
    exit;
}

if (!isset($_FILES['proof_video']) || $_FILES['proof_video']['error'] !== UPLOAD_ERR_OK) {
    returnWithError("A proof video is required. Please upload a video file.");
}

// --- POST DATA ---
$order_item_id = $_POST['order_item_id'];
$reason = trim($_POST['reason']);
$notes = trim($_POST['notes'] ?? ''); // Optional notes
$quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$video_file = $_FILES['proof_video'];

// --- FILE VALIDATION ---
if ($video_file['size'] > MAX_FILE_SIZE) {
    returnWithError("The uploaded video exceeds the maximum size limit of 20MB.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $video_file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
    returnWithError("Invalid file type. Only MP4 and MOV videos are allowed.");
}

// --- VERIFY ITEM OWNERSHIP AND ELIGIBILITY ---
$stmt = $pdo->prepare("SELECT oi.*, o.status as order_status FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.id = ? AND o.user_id = ?");
$stmt->execute([$order_item_id, $user_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    returnWithError("The specified item was not found in your orders.");
}
if ($item['order_status'] !== 'Delivered') {
    returnWithError("This item is not yet delivered and is not eligible for a return request.");
}
if ($item['status'] !== 'Confirmed') { // Check if a return has already been requested
     returnWithError("A return request for this item is already in process.");
}

// Validate quantity
if ($quantity === false || $quantity <= 0 || $quantity > $item['quantity']) {
    returnWithError("Invalid quantity specified for the return.");
}


// --- PROCESS THE REQUEST ---
$new_filename = uniqid('return_', true) . '.' . pathinfo($video_file['name'], PATHINFO_EXTENSION);
$upload_path = UPLOAD_DIR . $new_filename;

// Ensure the upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

try {
    $pdo->beginTransaction();

    // 1. Insert the return request with quantity
    $sql = "INSERT INTO return_requests (order_id, order_item_id, user_id, reason, notes, quantity) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$item['order_id'], $item['id'], $user_id, $reason, $notes, $quantity]);
    $return_id = $pdo->lastInsertId();

    // 2. Move the uploaded file
    if (!move_uploaded_file($video_file['tmp_name'], $upload_path)) {
        throw new Exception("Failed to save the uploaded video.");
    }

    // 3. Insert the media record
    $sql_media = "INSERT INTO return_request_media (return_id, file_name, file_type) VALUES (?, ?, ?)";
    $stmt_media = $pdo->prepare($sql_media);
    $stmt_media->execute([$return_id, $new_filename, $mime_type]);

    // 4. Update the item's status to show a request is pending
    $update_stmt = $pdo->prepare("UPDATE order_items SET status = 'Return Requested' WHERE id = ?");
    $update_stmt->execute([$order_item_id]);

    $pdo->commit();
    $_SESSION['success'] = "Return request submitted successfully. Please wait for admin review.";

} catch (Exception $e) {
    $pdo->rollBack();
    
    // If the file was moved before the error, delete it
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    // Check for duplicate entry error specifically
    if ($e->getCode() == '23000') {
         returnWithError("You have already submitted a return request for this item.");
    } else {
         returnWithError("An error occurred: " . $e->getMessage());
    }
}

header("Location: profile.php?page=orders");
exit;

