<?php
/**
 * SPOTTER — Service account authentication helper
 *
 * Signs a JWT with the service account's private key and exchanges it for
 * an access token. Used by onboard_verify.php and onboard_download.php.
 *
 * No external libraries needed (matches chop.php's dependency-light style) -
 * uses PHP's built-in openssl functions for RS256 signing.
 *
 * NOTE: this has not been tested against the live Google API yet (built in
 * an environment without network access to Google's servers) - test this
 * file first in isolation before relying on the pages that use it.
 */

define('SERVICE_ACCOUNT_KEY_FILE', __DIR__ . '/service_account_key.json');
// ^ path to the spotter-uploader service account JSON key on the server.
//   Keep this OUTSIDE the web root, or protect it via nginx config, so it's
//   never directly downloadable via a URL.

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_service_account_token($scope = 'https://www.googleapis.com/auth/drive') {
    $key_data = json_decode(file_get_contents(SERVICE_ACCOUNT_KEY_FILE), true);
    if (!$key_data) {
        throw new Exception('Could not read service account key file');
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claim_set = [
        'iss' => $key_data['client_email'],
        'scope' => $scope,
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
    ];

    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($claim_set)),
    ];
    $signing_input = implode('.', $segments);

    $private_key = openssl_pkey_get_private($key_data['private_key']);
    if (!$private_key) {
        throw new Exception('Could not load private key from service account file');
    }
    openssl_sign($signing_input, $signature, $private_key, 'SHA256');
    $segments[] = base64url_encode($signature);
    $jwt = implode('.', $segments);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Token exchange failed (HTTP $http_code): $response");
    }

    $token_data = json_decode($response, true);
    return $token_data['access_token'];
}
