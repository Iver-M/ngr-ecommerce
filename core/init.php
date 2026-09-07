<?php
// init.php

require_once __DIR__ . '/paths.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once NGR_ROOT . '/config/db.php';

// Helper
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
