<?php
/** Isolated checks: no production credentials or database connection. */
require_once __DIR__ . '/../includes/login_throttle.php';

if (($argv[1] ?? '') === '--reserve') {
    echo json_encode(login_throttle_reserve($argv[2], 'parallel@example.test', '192.0.2.1', 1000));
    exit;
}

$testRoot = sys_get_temp_dir() . '/ngr-login-' . bin2hex(random_bytes(8));
mkdir($testRoot);
$checks = 0;

function login_check(bool $passed, string $label): void
{
    global $checks;
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
    echo "PASS: $label\n";
}

function child_result(array $command): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHP test process.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || $error !== '') {
        throw new RuntimeException('PHP test process failed: ' . $error . $output);
    }
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}

function request_login(array $input = [], string $ip = '192.0.2.1', array $options = []): array
{
    global $testRoot;
    return child_result([PHP_BINARY, '-n', $testRoot . '/request.php', json_encode([
        'input' => $input, 'ip' => $ip, 'options' => $options,
    ], JSON_THROW_ON_ERROR)]);
}

function reset_login_store(): void
{
    global $testRoot;
    $file = $testRoot . '/config/login-attempts.json';
    if (is_file($file)) {
        unlink($file);
    }
}

try {
    $store = $testRoot . '/account.json';
    for ($i = 0; $i < 5; $i++) {
        login_check(login_throttle_reserve($store, 'member@example.test', '192.0.2.1', 1000)['allowed'], 'Account attempt ' . ($i + 1));
    }
    $blocked = login_throttle_reserve($store, ' MEMBER@example.test ', '192.0.2.2', 1001);
    login_check(!$blocked['allowed'] && $blocked['retry_after'] === 899, 'Account limit survives case, whitespace and IP changes');
    login_check(login_throttle_reserve($store, 'member@example.test', '192.0.2.1', 1900)['allowed'], 'Expired counters permit a new attempt');

    $store = $testRoot . '/ip.json';
    for ($i = 0; $i < 20; $i++) {
        $allowed = login_throttle_reserve($store, 'user' . $i . '@example.test', '192.0.2.3', 1000);
        if (!$allowed['allowed']) {
            throw new RuntimeException('IP budget exhausted too early.');
        }
    }
    login_check(!login_throttle_reserve($store, 'another@example.test', '192.0.2.3', 1000)['allowed'], 'IP limit covers different accounts');
    login_throttle_clear_account($store, 'user0@example.test', 1000);
    login_check(!login_throttle_reserve($store, 'user0@example.test', '192.0.2.3', 1000)['allowed'], 'A successful account reset does not clear the IP budget');
    login_check(login_throttle_reserve($store, 'user0@example.test', '192.0.2.4', 1000)['allowed'], 'Account reset permits a new attempt from an unblocked IP');
    login_check(strpos(file_get_contents($store), '@example.test') === false, 'Attempt store contains hashed identifiers');

    $parallelStore = $testRoot . '/parallel.json';
    $workers = [];
    for ($i = 0; $i < 12; $i++) {
        $process = proc_open([PHP_BINARY, '-n', __FILE__, '--reserve', $parallelStore],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start parallel throttle check.');
        }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $allowedCount = 0;
    foreach ($workers as [$process, $pipes]) {
        $result = json_decode(stream_get_contents($pipes[1]), true, 8, JSON_THROW_ON_ERROR);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $error !== '') {
            throw new RuntimeException('Parallel worker failed: ' . $error);
        }
        $allowedCount += $result['allowed'] ? 1 : 0;
    }
    login_check($allowedCount === 5, 'Twelve concurrent requests cannot exceed five account attempts');

    foreach (['app', 'app/auth', 'includes', 'config', 'sessions'] as $directory) {
        mkdir($testRoot . '/' . $directory);
    }
    copy(__DIR__ . '/../app/auth/login.php', $testRoot . '/app/auth/login.php');
    copy(__DIR__ . '/../includes/login_throttle.php', $testRoot . '/includes/login_throttle.php');
    file_put_contents($testRoot . '/config/db.php', <<<'PHP'
<?php
class FixtureStatement {
    private array $params = [];
    public function execute(array $params): void { $this->params = $params; $GLOBALS['bound_email'] = $params[0]; }
    public function fetch(int $mode) {
        $email = strtolower($this->params[0]);
        if (!in_array($email, ['admin@example.test', 'member@example.test', "o'connor@example.test"], true)) { return false; }
        return ['id' => $email === 'admin@example.test' ? 1 : 2, 'first_name' => 'Test', 'last_name' => 'User',
            'email' => $email, 'role' => $email === 'admin@example.test' ? 'admin' : 'customer',
            'password' => password_hash('FixturePass123!', PASSWORD_BCRYPT, ['cost' => 4])];
    }
}
class FixtureDatabase {
    public function prepare(string $sql): FixtureStatement {
        if ($sql !== 'SELECT * FROM users WHERE email = ? LIMIT 1') { throw new RuntimeException('Email must use a bound query.'); }
        if ($GLOBALS['fixture_options']['db_error'] ?? false) { throw new PDOException('Private database details'); }
        return new FixtureStatement();
    }
}
$pdo = new FixtureDatabase();
PHP
    );
    file_put_contents($testRoot . '/request.php', <<<'PHP'
<?php
$request = json_decode($argv[1], true, 32, JSON_THROW_ON_ERROR);
$fixture_options = $request['options'];
session_save_path(__DIR__ . '/sessions');
session_start();
$initialId = session_id();
$_SESSION['login_csrf'] = 'fixture-csrf-token';
session_write_close();
$_SERVER['REQUEST_METHOD'] = $fixture_options['method'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = $request['ip'];
$_SERVER['HTTPS'] = 'on';
$_POST = $request['input'];
if (!($fixture_options['missing_csrf'] ?? false)) { $_POST['csrf_token'] = $_POST['csrf_token'] ?? 'fixture-csrf-token'; }
$_GET = [];
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/private-errors.log');
ob_start();
register_shutdown_function(function () use ($initialId) {
    $html = ob_get_clean();
    echo json_encode(['status' => http_response_code() ?: 200, 'html' => $html, 'session' => $_SESSION,
        'initial_id' => $initialId, 'session_id' => session_id(), 'email' => $GLOBALS['bound_email'] ?? null,
        'cookie' => session_get_cookie_params()], JSON_THROW_ON_ERROR);
});
require __DIR__ . '/app/auth/login.php';
PHP
    );

    $result = request_login([], '192.0.2.1', ['method' => 'GET']);
    login_check($result['status'] === 200 && str_contains($result['html'], 'name="csrf_token"'), 'Login form includes a CSRF token');
    login_check($result['cookie']['httponly'] && $result['cookie']['secure'] && $result['cookie']['samesite'] === 'Lax', 'HTTPS session cookies use secure attributes');

    $credentials = ['email' => 'admin@example.test', 'password' => 'FixturePass123!'];
    $result = request_login($credentials, '192.0.2.1', ['missing_csrf' => true]);
    login_check($result['status'] === 400 && !isset($result['session']['user_id']), 'Missing CSRF token prevents login');
    login_check(!file_exists($testRoot . '/config/login-attempts.json'), 'Invalid CSRF requests do not consume account attempts');

    $result = request_login($credentials);
    login_check(($result['session']['user_role'] ?? '') === 'admin' && $result['html'] === '', 'Correct admin credentials authenticate');
    login_check($result['session_id'] !== $result['initial_id'], 'Successful login regenerates the session ID');
    login_check(!isset($result['session']['login_csrf']), 'Successful login invalidates the form token');

    reset_login_store();
    $result = request_login(['email' => "o'connor@example.test", 'password' => 'FixturePass123!']);
    login_check(($result['session']['user_role'] ?? '') === 'customer' && $result['email'] === "o'connor@example.test", 'Apostrophe email is bound as data and customer login works');

    reset_login_store();
    $attack = '<svg onload=alert(1)>';
    $result = request_login(['email' => 'admin@example.test', 'password' => $attack]);
    login_check(!isset($result['session']['user_id']) && !str_contains($result['html'], $attack), 'Wrong password is rejected and never reflected');
    $result = request_login(['email' => "' OR 1=1 -- ", 'password' => 'FixturePass123!']);
    login_check(!isset($result['session']['user_id']) && str_contains($result['html'], 'Invalid email or password.'), 'SQL injection cannot authenticate');
    $result = request_login(['email' => ['bad'], 'password' => ['bad']]);
    login_check($result['status'] === 200 && !isset($result['session']['user_id']), 'Array input is safely rejected');

    reset_login_store();
    for ($i = 0; $i < 5; $i++) {
        $result = request_login(['email' => 'admin@example.test', 'password' => 'wrong']);
        if ($result['status'] !== 200) { throw new RuntimeException('Login blocked before fifth attempt.'); }
    }
    $result = request_login($credentials, '192.0.2.2');
    login_check($result['status'] === 429 && !isset($result['session']['user_id']), 'Sixth account attempt is blocked even with a new session and IP');

    reset_login_store();
    file_put_contents($testRoot . '/config/login-attempts.json', '{corrupted');
    $result = request_login($credentials);
    login_check($result['status'] === 503 && !isset($result['session']['user_id']), 'Corrupt attempt storage fails closed');
    reset_login_store();
    mkdir($testRoot . '/config/login-attempts.json');
    $result = request_login($credentials);
    login_check($result['status'] === 503 && !isset($result['session']['user_id']), 'Unavailable attempt storage fails closed');
    rmdir($testRoot . '/config/login-attempts.json');
    $result = request_login($credentials, '192.0.2.1', ['db_error' => true]);
    login_check($result['status'] === 503 && !str_contains($result['html'], 'Private database details'), 'Database errors do not leak private details');

    echo "$checks login security checks passed. No hosted database was accessed.\n";
} finally {
    $resolved = realpath($testRoot);
    if ($resolved !== false && dirname($resolved) === realpath(sys_get_temp_dir())
        && str_starts_with(basename($resolved), 'ngr-login-')) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) { rmdir($item->getPathname()); }
            else { unlink($item->getPathname()); }
        }
        rmdir($resolved);
    }
}
