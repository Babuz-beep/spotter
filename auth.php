<?php
/**
 * SPOTTER Authentication — PIN Login System
 * Schools get a PIN stored per domain in the database.
 * Students/teachers enter school email + PIN → instant login.
 * Place at: /var/www/html/qspotter/auth.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── Config ────────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'spotteruser');
define('DB_PASS', 'SolidScience2026!');
define('DB_NAME', 'spotter');
define('SITE_URL', 'https://qspotter.co.uk');
define('SESSION_DAYS', 7);

// Block personal email domains
$blocked_domains = [
    'gmail.com','yahoo.com','hotmail.com','outlook.com',
    'icloud.com','live.com','googlemail.com','yahoo.co.uk',
    'hotmail.co.uk','msn.com','aol.com','protonmail.com',
    'me.com','mac.com'
];

// ── Database ──────────────────────────────────────────────────────────────────
function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_domain($email) {
    return strtolower(substr(strrchr($email, '@'), 1));
}

function is_personal_domain($domain, $blocked) {
    return in_array($domain, $blocked);
}

function create_session($email, $domain) {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+' . SESSION_DAYS . ' days'));

    db()->prepare(
        'INSERT INTO sessions (token, email, domain, expires_at) VALUES (?, ?, ?, ?)'
    )->execute([$token, $email, $domain, $expires]);

    // Set cookie for mobile compatibility
    setcookie('spotter_token', $token, [
        'expires'  => strtotime('+' . SESSION_DAYS . ' days'),
        'path'     => '/',
        'domain'   => 'qspotter.co.uk',
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    return $token;
}

// ── Ensure schools table has PIN column ───────────────────────────────────────
try {
    db()->exec("ALTER TABLE schools ADD COLUMN IF NOT EXISTS pin VARCHAR(20) DEFAULT NULL");
} catch (Exception $e) {
    // Column may already exist — ignore
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── PIN Login ─────────────────────────────────────────────────────────────
    case 'pin_login':
        global $blocked_domains;
        $email = strtolower(trim($input['email'] ?? ''));
        $pin   = trim($input['pin'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['valid' => false, 'error' => 'Please enter a valid email address.'], 400);
        }

        $domain = get_domain($email);

        if (is_personal_domain($domain, $blocked_domains)) {
            json_response([
                'valid' => false,
                'error' => 'Please use your school or college email address, not a personal one.'
            ], 403);
        }

        if (empty($pin)) {
            json_response(['valid' => false, 'error' => 'Please enter your access PIN.'], 400);
        }

        // Look up school PIN
        $stmt = db()->prepare('SELECT pin, active FROM schools WHERE domain = ? LIMIT 1');
        $stmt->execute([$domain]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$school) {
            json_response([
                'valid' => false,
                'error' => 'Your school is not registered. Please contact your teacher.'
            ], 403);
        }

        if (!$school['active']) {
            json_response([
                'valid' => false,
                'error' => 'Your school account is not active. Please contact your teacher.'
            ], 403);
        }

        if (empty($school['pin'])) {
            json_response([
                'valid' => false,
                'error' => 'No PIN has been set for your school. Please contact your teacher.'
            ], 403);
        }

        // Constant-time PIN comparison to prevent timing attacks
        if (!hash_equals($school['pin'], $pin)) {
            json_response(['valid' => false, 'error' => 'Incorrect PIN. Please try again.'], 401);
        }

        $token = create_session($email, $domain);
        json_response(['valid' => true, 'token' => $token, 'email' => $email]);
        break;

    // ── Set school PIN (admin only — call from server CLI or admin page) ──────
    case 'set_pin':
        $admin_key = trim($input['admin_key'] ?? '');
        $domain    = strtolower(trim($input['domain'] ?? ''));
        $pin       = trim($input['pin'] ?? '');
        $name      = trim($input['name'] ?? $domain);

        // Simple admin key check
        if ($admin_key !== 'SpotterAdmin2026!') {
            json_response(['error' => 'Unauthorised'], 403);
        }

        if (!$domain || !$pin) {
            json_response(['error' => 'domain and pin required'], 400);
        }

        db()->prepare(
            'INSERT INTO schools (domain, name, pin, active)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE pin = ?, name = ?, active = 1'
        )->execute([$domain, $name, $pin, $pin, $name]);

        json_response(['success' => true, 'domain' => $domain, 'pin' => $pin]);
        break;

    // ── Verify session (used by session_check.js) ─────────────────────────────
    case 'verify_session':
        $token = trim($input['token'] ?? '');
        if (!$token) json_response(['valid' => false], 401);

        $stmt = db()->prepare(
            'SELECT email, domain FROM sessions WHERE token = ? AND expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) json_response(['valid' => false], 401);
        json_response(['valid' => true, 'email' => $session['email'], 'domain' => $session['domain']]);
        break;

    // ── Logout ────────────────────────────────────────────────────────────────
    case 'logout':
        $token = trim($input['token'] ?? '');
        if ($token) db()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        // Clear cookie
        setcookie('spotter_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => 'qspotter.co.uk',
            'secure'   => true,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        json_response(['success' => true]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
