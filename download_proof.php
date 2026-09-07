<?php
// admin/download_proof.php

session_start();
// Use the same admin check as in admin_dashboard.php
require 'auth_check.php'; // Ensure only authenticated admins can proceed
// require 'config/db.php'; // May not be needed if path is passed directly

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied.";
    exit;
}

$file_path = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($file_path) || !file_exists($file_path)) {
    header("HTTP/1.1 404 Not Found");
    echo "File not found or path is invalid.";
    exit;
}

// Security Check: Ensure the file path starts with the expected secure directory
// This prevents an admin from trying to access arbitrary files on the server.
$base_dir = __DIR__ . 'uploads/return_videos/'; // MUST MATCH the path in submit_return_request.php

// A simple path validation check (can be made more robust)
if (strpos($file_path, $base_dir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    echo "Invalid file path access attempt.";
    exit;
}

// Serve the file
$filename = basename($file_path);
$mime_type = mime_content_type($file_path);
$filesize = filesize($file_path);

// Set headers for file download/streaming
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $filesize);
header('Content-Disposition: inline; filename="' . $filename . '"'); // 'inline' tries to display in browser, 'attachment' forces download
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');

// Output the file contents
readfile($file_path);
exit;