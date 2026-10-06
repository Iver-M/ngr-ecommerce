<?php
// init.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Helper
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
