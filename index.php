<?php
/**
 * Site entry point. Feature code lives in app/; public URLs stay the same.
 * Apache sends root-level PHP requests here through .htaccess.
 * Local development: php -S localhost:8000 -t . index.php
 */
$projectRoot = __DIR__;
$routes = require $projectRoot . '/config/routes.php';
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/index.php', PHP_URL_PATH) ?: '/');

// Apache may serve the project from a subfolder, e.g. /newgrounds/.
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if (PHP_SAPI === 'cli-server') {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $basePath = substr(str_replace('\\', '/', $projectRoot), strlen(str_replace('\\', '/', $documentRoot)));
}
$basePath = rtrim($basePath, '/') . '/';
$route = strpos($requestPath, $basePath) === 0 ? substr($requestPath, strlen($basePath)) : null;
if ($route === '') {
    $route = 'index.php';
}

// Let PHP's development server serve existing assets and legacy files.
// Feature files are internal and must only be run through a public route.
if (PHP_SAPI === 'cli-server' && $route !== null && !isset($routes[$route])) {
    $existingFile = realpath($projectRoot . '/' . $route);
    $rootPrefix = $projectRoot . DIRECTORY_SEPARATOR;
    $appPrefix = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;
    $configPrefix = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
    $gitPrefix = $projectRoot . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR;
    if ($existingFile !== false && is_file($existingFile)
        && strpos($existingFile, $rootPrefix) === 0
        && strpos($existingFile, $appPrefix) !== 0
        && strpos($existingFile, $configPrefix) !== 0
        && strpos($existingFile, $gitPrefix) !== 0
        && !preg_match('/^(?:\.env(?:\..*)?|.*\.env)$/i', basename($existingFile))) {
        return false;
    }
}

if (!isset($routes[$route])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Page not found.';
    exit;
}

// Existing upload paths are relative to the project root, not app/<feature>/.
chdir($projectRoot);
require $projectRoot . '/' . $routes[$route];
