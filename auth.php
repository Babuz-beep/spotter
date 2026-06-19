<?php
/**
 * SPOTTER Authentication — Magic Link System
 * Open to all school/college emails (non-personal domains)
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
define('RESEND_API_KEY', 're_Y8M3vGdS_HTQPJc3yoEGWjMEHffVujtZF');
define('FROM_EMAIL', 'noreply@qspotter.co.uk');
define('SITE_URL', 'https://qspotter.co.uk');
define('SESSION_DAYS', 7);
define('LINK_EXPIRY_MINS', 15);

// Block personal email domains only
$blocked_domains = [
    'gmail.com','yahoo.com','hotmail.com','outlook.com',
    'icloud.com','live.com','googlemail.com','yahoo.co.uk',
    'hotmail.co.uk','msn.com','aol.com','protonmail.com',
    'icloud.com','me.com','mac.com'
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

function send_magic_link($email, $token) {
    $link = SITE_URL . '/auth.php?action=verify_link&token=' . $token;
    $html = "
    <div style='font-family:sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#fff;'>
        <div style='font-size:24px;font-weight:700;letter-spacing:3px;color:#2d6ef5;margin-bottom:4px;'>
            SPOT<span style='color:#d97706'>TER</span>
        </div>
        <p style='color:#8a93b0;font-size:12px;margin-bottom:28px;'>AQA Science Question Bank</p>
        <p style='color:#1e2540;font-size:16px;font-weight:600;margin-bottom:8px;'>Your login link is ready</p>
        <p style='color:#4a5272;margin-bottom:28px;font-size:14px;line-height:1.6;'>
            Click the button below to access SPOTTER.<br>
            This link expires in 15 minutes and can only be used once.
        </p>
        <div style='text-align:center;margin-bottom:28px;'>
            <a href='{$link}' style='display:inline-block;background:#2d6ef5;color:#fff;text-decoration:none;padding:16px 40px;border-radius:8px;font-size:16px;font-weight:600;letter-spacing:0.5px;'>
                Access SPOTTER →
            </a>
        </div>
        <p style='color:#8a93b0;font-size:12px;line-height:1.8;'>
            If the button doesn't work, copy this link into your browser:<br>
            <a href='{$link}' style='color:#2d6ef5;word-break:break-all;font-size:11px;'>{$link}</a>
        </p>
        <hr style='border:none;border-top:1px solid #d8dde8;margin:24px 0;'>
        <p style='color:#8a93b0;font-size:11px;'>
            If you didn't request this email, you can safely ignore it.<br>
            SPOTTER · qspotter.co.uk
        </p>
    </div>";

    $payload = json_encode([
        'from'    => FROM_EMAIL,
        'to'      => [$email],
        'subject' => 'Your SPOTTER login link',
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status === 200 || $status === 201;
}

// ── Handle GET — verify magic link click ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'verify_link') {
        $token = trim($_GET['token'] ?? '');

        if (!$token) {
            header('Location: ' . SITE_URL . '/login.html?error=invalid');
            exit;
        }

        $stmt = db()->prepare(
            'SELECT email FROM magic_links
             WHERE token = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            header('Location: ' . SITE_URL . '/login.html?error=expired');
            exit;
        }

        // Mark used
        db()->prepare('UPDATE magic_links SET used = 1 WHERE token = ?')
             ->execute([$token]);

        // Create session
        $session_token = bin2hex(random_bytes(32));
        $email   = $row['email'];
        $domain  = get_domain($email);
        $expires = date('Y-m-d H:i:s', strtotime('+' . SESSION_DAYS . ' days'));

        db()->prepare(
            'INSERT INTO sessions (token, email, domain, expires_at) VALUES (?, ?, ?, ?)'
        )->execute([$session_token, $email, $domain, $expires]);

        // Auto-register school domain if not already registered
        db()->prepare(
            'INSERT IGNORE INTO schools (domain, name, active) VALUES (?, ?, 1)'
        )->execute([$domain, $domain]);

        header('Location: ' . SITE_URL . '/login.html?session=' . $session_token . '&email=' . urlencode($email));
        exit;
    }

    if ($action === 'verify_session') {
        header('Content-Type: application/json');
        $token = trim($_GET['token'] ?? '');
        if (!$token) json_response(['valid' => false], 401);

        $stmt = db()->prepare(
            'SELECT email, domain FROM sessions WHERE token = ? AND expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) json_response(['valid' => false], 401);
        json_response(['valid' => true, 'email' => $session['email'], 'domain' => $session['domain']]);
    }

    json_response(['error' => 'Unknown action'], 400);
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'request_link':
        global $blocked_domains;
        $email = strtolower(trim($input['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Please enter a valid email address.'], 400);
        }

        $domain = get_domain($email);

        // Block personal domains
        if (is_personal_domain($domain, $blocked_domains)) {
            json_response([
                'error' => 'Please use your school or college email address, not a personal one.'
            ], 403);
        }

        // Generate magic link token
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . LINK_EXPIRY_MINS . ' minutes'));

        // Delete old unused links for this email
        db()->prepare('DELETE FROM magic_links WHERE email = ? AND used = 0')
             ->execute([$email]);

        // Store new link
        db()->prepare('INSERT INTO magic_links (email, token, expires_at) VALUES (?, ?, ?)')
             ->execute([$email, $token, $expires]);

        // Send email
        $sent = send_magic_link($email, $token);

        if (!$sent) {
            json_response(['error' => 'Failed to send email. Please try again.'], 500);
        }

        json_response([
            'success' => true,
            'message' => 'Login link sent to ' . $email,
        ]);
        break;

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

    case 'logout':
        $token = trim($input['token'] ?? '');
        if ($token) db()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        json_response(['success' => true]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
