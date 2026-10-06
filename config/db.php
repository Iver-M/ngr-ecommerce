<?php
require_once __DIR__ . '/settings.php';

$host = app_setting('DB_HOST', 'localhost');
$db = app_setting('DB_NAME');
$user = app_setting('DB_USER');
$pass = app_setting('DB_PASSWORD');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

define('PAYPAL_CLIENT_ID', app_setting('PAYPAL_CLIENT_ID'));
define('PAYPAL_CLIENT_SECRET', app_setting('PAYPAL_CLIENT_SECRET'));
define('PAYPAL_API_URL', app_setting('PAYPAL_API_URL', 'https://api-m.sandbox.paypal.com'));
