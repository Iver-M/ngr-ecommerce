<?php
/** Environment variables override the ignored config/local.php settings. */
function app_setting($name, $default = '') {
    static $settings = null;
    if ($settings === null) {
        $privateFile = __DIR__ . '/local.php';
        $settings = is_file($privateFile) ? require $privateFile : [];
        if (!is_array($settings)) {
            throw new RuntimeException('config/local.php must return an array.');
        }
    }
    $environment = getenv($name);
    return $environment !== false ? $environment : ($settings[$name] ?? $default);
}
