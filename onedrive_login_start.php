<?php
/**
 * SPOTTER — OneDrive Connect Test: Step 1 — redirect to Microsoft login
 * Place in /var/www/html/qspotter/ alongside onedrive_callback.php
 */
session_start();

// ── Config (test credentials — rotate before any real/production use) ───────
$CLIENT_ID = '73f2394d-8192-4391-9899-3db2b03389a5';
$REDIRECT_URI = 'https://qspotter.co.uk/onedrive_callback.php';
$SCOPES = 'offline_access openid profile User.Read Files.Read';

// CSRF protection — random state, checked on callback
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => $CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri'  => $REDIRECT_URI,
    'response_mode' => 'query',
    'scope'         => $SCOPES,
    'state'         => $state,
]);

// "common" endpoint = works for both personal Microsoft accounts and any
// organisational (school) tenant, matching the app's "multi-tenant + personal" setting
$authUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $params;

header('Location: ' . $authUrl);
exit;
