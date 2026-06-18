<?php
/**
 * SPOTTER Authentication System
 * Handles: login, code verification, session management
 * Place at: /var/www/html/qspotter/auth.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ── Config ────────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'spotteruser');
define('DB_PASS', 'SolidScience2026!');
define('DB_NAME', 'spotter');
define('RESEND_API_KEY', 're_Y8M3vGdS_HTQPJc3yoEGWjMEHffVujtZF');
define('FROM_EMAIL', 'noreply@qspotter.co.uk');
define('SESSION_DAYS', 7);
define('CODE_EXPIRY_MINS', 15);

// Personal email domains to block
$blocked_domains = ['gmail.com','yahoo.com','hotmail.com','outlook.com',
                    'icloud.com','live.com','googlemail.com','yahoo.co.uk',
                    'hotmail.co.uk','msn.com'];

// ── Database ──────────────────────────────────────────────────────────────────
function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_domain($email) {
    return strtolower(substr(strrchr($email, '@'), 1));
}

function generate_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generate_token() {
    return bin2hex(random_bytes(32));
}

function send_code_email($email, $code) {
    $html = "
    <div style='font-family:sans-serif;max-width:480px;margin:0 auto;padding:32px;'>
        <div style='font-size:24px;font-weight:700;letter-spacing:3px;color:#2d6ef5;margin-bottom:8px;'>
            SPOT<span style='color:#d97706'>TER</span>
        </div>
        <p style='color:#4a5272;margin-bottom:24px;'>Your verification code:</p>
        <div style='background:#f4f6fb;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px;'>
            <span style='font-size:40px;font-weight:700;letter-spacing:12px;color:#1e2540;font-family:monospace;'>
                {$code}
            </span>
        </div>
        <p style='color:#8a93b0;font-size:13px;'>
            This code expires in 15 minutes.<br>
            If you didn't request this, please ignore this email.
        </p>
        <hr style='border:none;border-top:1px solid #d8dde8;margin:24px 0;'>
        <p style='color:#8a93b0;font-size:11px;'>
            SPOTTER — AQA Science Question Bank<br>
            qspotter.co.uk
        </p>
    </div>";

    $payload = json_encode([
        'from' => FROM_EMAIL,
        'to'   => [$email],
        'subject' => 'Your SPOTTER login code: ' . $code,
        'html' => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status === 200 || $status === 201;
}

// ── Actions ───────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($action)) $action = $input['action'] ?? '';

switch ($action) {

    // ── 1. Request login code ────────────────────────────────────────────────
    case 'request_code':
        global $blocked_domains;
        $email = strtolower(trim($input['email'] ?? $_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Invalid email address'], 400);
        }

        $domain = get_domain($email);

        // Block personal email domains
        if (in_array($domain, $blocked_domains)) {
            json_response([
                'error' => 'Please use your school or college email address.'
            ], 403);
        }

        // Check domain is a registered school
        $stmt = db()->prepare('SELECT id, name FROM schools WHERE domain = ? AND active = 1');
        $stmt->execute([$domain]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$school) {
            json_response([
                'error' => 'Your school is not yet registered with SPOTTER.',
                'hint'  => 'Contact us at hello@qspotter.co.uk to get your school set up.'
            ], 403);
        }

        // Generate and store code
        $code    = generate_code();
        $expires = date('Y-m-d H:i:s', strtotime('+' . CODE_EXPIRY_MINS . ' minutes'));

        // Delete any existing unused codes for this email
        db()->prepare('DELETE FROM auth_codes WHERE email = ? AND used = 0')
             ->execute([$email]);

        db()->prepare('INSERT INTO auth_codes (email, code, expires_at) VALUES (?, ?, ?)')
             ->execute([$email, $code, $expires]);

        // Send email
        $sent = send_code_email($email, $code);

        if (!$sent) {
            json_response(['error' => 'Failed to send email. Please try again.'], 500);
        }

        json_response([
            'success' => true,
            'message' => 'Code sent to ' . $email,
            'school'  => $school['name'],
        ]);
        break;

    // ── 2. Verify code ───────────────────────────────────────────────────────
    case 'verify_code':
        $email = strtolower(trim($input['email'] ?? $_POST['email'] ?? ''));
        $code  = trim($input['code']  ?? $_POST['code']  ?? '');

        if (!$email || !$code) {
            json_response(['error' => 'Email and code required'], 400);
        }

        // Find valid code
        $stmt = db()->prepare(
            'SELECT id FROM auth_codes 
             WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$email, $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            json_response(['error' => 'Invalid or expired code. Please request a new one.'], 401);
        }

        // Mark code as used
        db()->prepare('UPDATE auth_codes SET used = 1 WHERE id = ?')
             ->execute([$row['id']]);

        // Create session
        $token   = generate_token();
        $domain  = get_domain($email);
        $expires = date('Y-m-d H:i:s', strtotime('+' . SESSION_DAYS . ' days'));

        db()->prepare(
            'INSERT INTO sessions (token, email, domain, expires_at) VALUES (?, ?, ?, ?)'
        )->execute([$token, $email, $domain, $expires]);

        json_response([
            'success' => true,
            'token'   => $token,
            'email'   => $email,
            'domain'  => $domain,
            'expires' => $expires,
        ]);
        break;

    // ── 3. Verify session token ──────────────────────────────────────────────
    case 'verify_session':
        $token = trim($input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '');

        if (!$token) {
            json_response(['valid' => false, 'error' => 'No token provided'], 401);
        }

        $stmt = db()->prepare(
            'SELECT email, domain, expires_at FROM sessions 
             WHERE token = ? AND expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            json_response(['valid' => false, 'error' => 'Session expired or invalid'], 401);
        }

        json_response([
            'valid'  => true,
            'email'  => $session['email'],
            'domain' => $session['domain'],
        ]);
        break;

    // ── 4. Logout ────────────────────────────────────────────────────────────
    case 'logout':
        $token = trim($input['token'] ?? $_POST['token'] ?? '');
        if ($token) {
            db()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        }
        json_response(['success' => true]);
        break;

    default:
        json_response(['error' => 'Unknown action', 'actions' => 
            ['request_code', 'verify_code', 'verify_session', 'logout']], 400);
}
