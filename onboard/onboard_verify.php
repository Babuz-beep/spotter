<?php
/**
 * SPOTTER — Onboarding: verify folder access
 *
 * Given a Drive folder/Shared-Drive link, checks whether the service account
 * (spotter-uploader@spotter-499815.iam.gserviceaccount.com) can actually see it.
 *
 * IMPORTANT: per today's testing, this MUST be a Shared Drive, not a regular
 * "My Drive" personal folder - service accounts have zero storage quota of
 * their own, so writing into a personal folder fails even with Editor access.
 * The frontend copy must make this explicit to avoid confused users.
 *
 * NOT YET TESTED against the live API - test in isolation first.
 */

require_once __DIR__ . '/drive_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://qspotter.co.uk');

function extract_folder_id($link) {
    // handles both /folders/ID and /drive/folders/ID style links
    if (preg_match('/folders\/([a-zA-Z0-9_-]+)/', $link, $m)) {
        return $m[1];
    }
    // fallback: maybe they just pasted the raw ID
    if (preg_match('/^[a-zA-Z0-9_-]{20,}$/', trim($link))) {
        return trim($link);
    }
    return null;
}

$input = json_decode(file_get_contents('php://input'), true);
$link = $input['folder_link'] ?? '';
$folder_id = extract_folder_id($link);

if (!$folder_id) {
    echo json_encode(['ok' => false, 'error' => 'Could not find a folder ID in that link. Paste the full Drive folder URL.']);
    exit;
}

try {
    $token = get_service_account_token();

    $ch = curl_init("https://www.googleapis.com/drive/v3/files/$folder_id?fields=id,name,mimeType,driveId&supportsAllDrives=true");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code === 404) {
        echo json_encode(['ok' => false, 'error' => "Can't see this folder. Make sure it's shared with spotter-uploader@spotter-499815.iam.gserviceaccount.com (Editor access)."]);
        exit;
    }
    if ($http_code !== 200) {
        echo json_encode(['ok' => false, 'error' => "Unexpected error checking access (HTTP $http_code)."]);
        exit;
    }
    if ($data['mimeType'] !== 'application/vnd.google-apps.folder') {
        echo json_encode(['ok' => false, 'error' => 'That link is not a folder.']);
        exit;
    }
    if (empty($data['driveId'])) {
        echo json_encode(['ok' => false, 'error' => 'This looks like a personal "My Drive" folder, not a Shared Drive. Please create a Shared Drive instead and share that with us - regular folders don\'t work for automated uploads (Google restriction on service accounts, not something we can change).']);
        exit;
    }

    echo json_encode(['ok' => true, 'folder_id' => $folder_id, 'folder_name' => $data['name']]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
