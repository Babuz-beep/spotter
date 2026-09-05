<?php
/**
 * SPOTTER — OneDrive Connect Test: Step 2 — callback
 * Exchanges the auth code for an access token, then calls Microsoft Graph
 * to list files in the signed-in user's OneDrive root — proof the whole
 * chain actually works end to end.
 */
session_start();

// ── Config (test credentials — rotate before any real/production use) ───────
$CLIENT_ID     = '73f2394d-8192-4391-9899-3db2b03389a5';
$CLIENT_SECRET = '4gv8Q~6UX2c7kGqufijMxpp6CBCysOfoewzgzcod';
$REDIRECT_URI  = 'https://qspotter.co.uk/onedrive_callback.php';

function render($title, $body) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>$title</title>
    <style>
      body{font-family:'Segoe UI',sans-serif;background:#F0F6FF;color:#1A2E3E;padding:40px 20px;}
      .wrap{max-width:700px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #D4E4F0;padding:32px;}
      h1{font-size:1.3rem;margin-bottom:16px;}
      .ok{color:#059669;} .err{color:#991B1B;}
      pre{background:#F7FAFC;border-radius:8px;padding:16px;overflow-x:auto;font-size:0.85rem;}
      ul{margin:10px 0 0 20px;} li{margin-bottom:6px;}
    </style></head><body><div class='wrap'>$body</div></body></html>";
    exit;
}

// ── Step A: check for errors from Microsoft / missing code ──────────────────
if (isset($_GET['error'])) {
    render('SPOTTER — Error', "<h1 class='err'>✗ Microsoft returned an error</h1><pre>" .
        htmlspecialchars($_GET['error'] . ': ' . ($_GET['error_description'] ?? '')) . "</pre>");
}

if (!isset($_GET['code'])) {
    render('SPOTTER — Error', "<h1 class='err'>✗ No authorization code received</h1><p>Something went wrong before Microsoft redirected back here.</p>");
}

// CSRF check
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    render('SPOTTER — Error', "<h1 class='err'>✗ State mismatch</h1><p>Possible session issue — try connecting again from the start.</p>");
}

// ── Step B: exchange the code for an access token ────────────────────────────
$tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
$postData = http_build_query([
    'client_id'     => $CLIENT_ID,
    'client_secret' => $CLIENT_SECRET,
    'code'          => $_GET['code'],
    'redirect_uri'  => $REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 20,
]);
$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);

if ($tokenHttpCode !== 200 || !isset($tokenData['access_token'])) {
    render('SPOTTER — Token exchange failed', "<h1 class='err'>✗ Token exchange failed (HTTP $tokenHttpCode)</h1><pre>" .
        htmlspecialchars(json_encode($tokenData, JSON_PRETTY_PRINT)) . "</pre>");
}

$accessToken = $tokenData['access_token'];

// ── Step C: call Microsoft Graph — who am I? ─────────────────────────────────
function graphGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($resp, true)];
}

[$meCode, $me] = graphGet('https://graph.microsoft.com/v1.0/me', $accessToken);

if ($meCode !== 200) {
    render('SPOTTER — Graph call failed', "<h1 class='err'>✗ /me call failed (HTTP $meCode)</h1><pre>" .
        htmlspecialchars(json_encode($me, JSON_PRETTY_PRINT)) . "</pre>");
}

// ── Step D: list files in OneDrive root — the real proof ────────────────────
[$filesCode, $files] = graphGet('https://graph.microsoft.com/v1.0/me/drive/root/children', $accessToken);

$fileListHtml = '';
if ($filesCode === 200 && isset($files['value'])) {
    $fileListHtml = '<ul>';
    foreach ($files['value'] as $item) {
        $type = isset($item['folder']) ? '📁' : '📄';
        $fileListHtml .= '<li>' . $type . ' ' . htmlspecialchars($item['name']) . '</li>';
    }
    $fileListHtml .= '</ul>';
    if (empty($files['value'])) $fileListHtml = '<p>(OneDrive root is empty)</p>';
} else {
    $fileListHtml = "<pre class='err'>Failed to list files (HTTP $filesCode):\n" .
        htmlspecialchars(json_encode($files, JSON_PRETTY_PRINT)) . '</pre>';
}

$name = htmlspecialchars($me['displayName'] ?? 'Unknown');
$email = htmlspecialchars($me['mail'] ?? $me['userPrincipalName'] ?? 'Unknown');

render('SPOTTER — Connected!', "
<h1 class='ok'>✓ Connected successfully</h1>
<p><strong>Signed in as:</strong> $name ($email)</p>
<p><strong>Files/folders found in your OneDrive root:</strong></p>
$fileListHtml
<p style='margin-top:20px;color:#6B8FA8;font-size:0.85rem;'>
This proves SPOTTER can authenticate with your OneDrive and read real file listings via Microsoft Graph — the actual test we needed.
</p>
");
