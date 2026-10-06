<?php
$root = dirname(__DIR__);
$routes = require $root . '/config/routes.php';
if (!is_array($routes) || !isset($routes['index.php'])) {
    throw new RuntimeException('The route map must contain the homepage.');
}
foreach ($routes as $url => $file) {
    if (!preg_match('/^[a-z_]+\.php$/', $url)
        || strpos($file, 'app/') !== 0 || strpos($file, '..') !== false
        || !is_file($root . '/' . $file)) {
        throw new RuntimeException('Invalid or missing route: ' . $url);
    }
}
if (count(glob($root . '/*.php')) !== 1) {
    throw new RuntimeException('Only index.php should be at the project root.');
}
echo count($routes) . " public routes verified.\n";
