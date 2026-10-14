<?php
/**
 * auth.php
 * Session, role guard, CSRF and JSON response helpers shared by the
 * Mechanic and VehicleOwner pages and endpoints.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Last-resort handler: never show exception details (e.g. SQL errors) to the browser.
set_exception_handler(function (Throwable $e) {
    error_log('[AutoCare] Uncaught ' . get_class($e) . ' in ' . ($_SERVER['SCRIPT_NAME'] ?? '') . ': ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
    } else {
        echo '<p style="font-family: sans-serif; padding: 24px;">Something went wrong loading this page. Please try again.</p>';
    }
});

require_once __DIR__ . '/db.php';

// One shared connection for every Mechanic/VehicleOwner page and endpoint
$pdo = databaseConnection();

/**
 * Current time from the database clock. PHP and MySQL may run in different time zones
 * (XAMPP: PHP Europe/Berlin, MySQL system time), so "today"/"ago" comparisons against
 * DB timestamps must use this, not time().
 */
function db_now(): string
{
    static $now = null;
    if ($now === null) {
        global $pdo;
        $now = (string) $pdo->query("SELECT NOW()")->fetchColumn();
    }
    return $now;
}

function now_ts(): int
{
    return strtotime(db_now());
}

// Team login (api/login-api.php) role names => Users.role values used by these panels
const SESSION_ROLE_MAP = [
    'Vehicle Owner' => 'VehicleOwner',
    'Mechanic' => 'Mechanic',
];

// Landing page per team login role, relative to public_html/pages/
const ROLE_HOME = [
    'Administrator' => 'admin/dashboard.html',
    'Workshop Manager' => 'manager/manager-dashboard.html',
    'Mechanic' => 'mechanic/mechanic-dashboard.php',
    'Vehicle Owner' => 'vehicleowner/vehicleowner-dashboard.php',
];

/**
 * The logged-in user from the team login session, or null when not logged in.
 * 'id' is the Users.id (matched by email to AuthUsers) that every query here uses;
 * it is null when the login account has no matching Users row of the same role.
 * 'role' is the Users.role form ('VehicleOwner', 'Mechanic'), or the raw session role
 * for other roles; 'session_role' is always the raw session value.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        return null;
    }
    $authId = (int) $_SESSION['user_id'];
    $sessionRole = (string) $_SESSION['user_role'];
    $role = SESSION_ROLE_MAP[$sessionRole] ?? $sessionRole;

    $cache = $_SESSION['om_link'] ?? null;
    if (!is_array($cache) || $cache['auth_id'] !== $authId || $cache['role'] !== $role || $cache['id'] === null) {
        $id = null;
        $name = null;
        if (isset(SESSION_ROLE_MAP[$sessionRole])) {
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT u.id, u.name FROM AuthUsers a JOIN Users u ON u.email = a.email
                 WHERE a.id = ? AND u.role = ? LIMIT 1");
            $stmt->execute([$authId, $role]);
            if ($row = $stmt->fetch()) {
                $id = (int) $row['id'];
                $name = $row['name'];
            }
        }
        $cache = ['auth_id' => $authId, 'role' => $role, 'id' => $id, 'name' => $name];
        $_SESSION['om_link'] = $cache;
    }

    return [
        'id' => $cache['id'],
        'auth_id' => $authId,
        'role' => $role,
        'session_role' => $sessionRole,
        'name' => (string) ($_SESSION['user_name'] ?? $cache['name'] ?? ''),
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

function json_ok($data, int $status = 200): void
{
    json_response($status, ['success' => true, 'data' => $data]);
}

function json_error(string $message, int $status): void
{
    json_response($status, ['success' => false, 'error' => $message]);
}

/** Log the real exception server-side and return a generic 500. */
function json_server_error(Throwable $e): void
{
    error_log('[AutoCare API] ' . ($_SERVER['SCRIPT_NAME'] ?? '') . ': ' . $e->getMessage());
    json_error('Something went wrong. Please try again.', 500);
}

/**
 * Guard for API endpoints: requires a logged-in user with $role.
 * Any non-GET request must carry the session CSRF token in X-CSRF-Token.
 */
function require_api_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        json_error('Not authenticated', 401);
    }
    if ($user['role'] !== $role) {
        json_error('Forbidden', 403);
    }
    if ($user['id'] === null) {
        json_error('This login has no linked ' . $role . ' profile.', 403);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && !csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_error('Invalid or missing CSRF token', 403);
    }
    return $user;
}

/** Guard for pages: redirects to login, or to the user's own home on a role mismatch. */
function require_page_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ../login.html');
        exit;
    }
    if ($user['role'] !== $role) {
        header('Location: ../' . (ROLE_HOME[$user['session_role']] ?? 'login.html'));
        exit;
    }
    if ($user['id'] === null) {
        // Logged in with the right role but no Users row shares this email
        http_response_code(403);
        echo '<p style="font-family: sans-serif; padding: 24px;">Your login is not linked to a ' . e($role)
            . ' profile yet. Please contact the workshop. <a href="../logout.php">Log out</a></p>';
        exit;
    }
    return $user;
}

function require_method(array $allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        json_error('Method not allowed', 405);
    }
    return $method;
}

function read_json_body(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_error('Request body must be a JSON object', 400);
    }
    return $data;
}

/** Positive integer from input, or null. */
function input_id($value): ?int
{
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $n = (int) $value;
        return $n > 0 ? $n : null;
    }
    return null;
}

/** Trimmed string within a length range, or null. */
function input_text($value, int $min, int $max): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    $len = mb_strlen($value);
    return ($len >= $min && $len <= $max) ? $value : null;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return '৳' . number_format((float) $amount, 2);
}

function initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part !== '') {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    return mb_substr($out, 0, 2) ?: '?';
}

/** Only allow relative asset/upload paths for stored image URLs. */
function safe_image_url(?string $url, string $fallback = ''): string
{
    if (is_string($url) && preg_match('#^\.\./\.\./(assets/images|uploads)/[A-Za-z0-9._/-]+$#', $url) && strpos($url, '..', 6) === false) {
        return $url;
    }
    return $fallback;
}

/** Users.avatar holds https URLs (seed) or relative paths; anything else falls back. */
function safe_avatar_url(?string $url): string
{
    if (is_string($url) && preg_match('#^https://[^\s"\'<>]+$#', $url)) {
        return $url;
    }
    return safe_image_url($url);
}
