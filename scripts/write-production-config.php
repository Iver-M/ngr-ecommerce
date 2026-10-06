<?php
// Run only in CI. Read secrets from the environment, never from command arguments.
$destination = $argv[1] ?? '';
if ($destination === '') {
    fwrite(STDERR, "Specify the production config output path.\n");
    exit(1);
}
$settings = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'PAYPAL_CLIENT_ID',
          'PAYPAL_CLIENT_SECRET', 'SENDGRID_API_KEY', 'MAIL_FROM', 'SITE_URL',
          'PAYPAL_API_URL'] as $name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing setting: $name\n");
        exit(1);
    }
    $settings[$name] = $value;
}
if (!in_array($settings['PAYPAL_API_URL'], [
    'https://api-m.sandbox.paypal.com', 'https://api-m.paypal.com'
], true)) {
    fwrite(STDERR, "PAYPAL_API_URL must be the sandbox or live PayPal API URL.\n");
    exit(1);
}
if (!filter_var($settings['SITE_URL'], FILTER_VALIDATE_URL)
    || strpos($settings['SITE_URL'], 'https://') !== 0) {
    fwrite(STDERR, "SITE_URL must be the complete HTTPS website URL.\n");
    exit(1);
}
$settings['MAIL_FROM_NAME'] = getenv('MAIL_FROM_NAME') ?: 'NewGrounds';
$contents = "<?php\n// Generated private production configuration.\nreturn " . var_export($settings, true) . ";\n";
if (file_put_contents($destination, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write production configuration.\n");
    exit(1);
}
chmod($destination, 0600);
echo "Production configuration generated.\n";
