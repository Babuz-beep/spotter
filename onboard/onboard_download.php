<?php
/**
 * SPOTTER — Onboarding: fetch & upload selected papers
 *
 * Streams progress back to the browser as it works (one line per file),
 * so the page can show live status instead of a long silent wait.
 *
 * NOT YET TESTED against the live API - test in isolation first, and note
 * that fetching+uploading many files can take a couple of minutes; check
 * PHP's max_execution_time is raised for this script specifically
 * (set below via set_time_limit) and that nginx isn't buffering/timing out
 * the response before PHP finishes (may need proxy_buffering off in the
 * nginx config for this specific endpoint).
 */

require_once __DIR__ . '/drive_auth.php';
$catalog = require __DIR__ . '/catalog.php';

set_time_limit(300); // 5 minutes - adjust if catalogs grow larger
header('Content-Type: text/plain');
header('X-Accel-Buffering: no'); // hint to nginx: don't buffer this response

function send($line) {
    echo $line . "\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

$folder_id = $_POST['folder_id'] ?? '';
$selected = $_POST['qualifications'] ?? []; // array of catalog keys

if (!$folder_id || empty($selected)) {
    send('ERROR: Missing folder ID or no qualifications selected.');
    exit;
}

try {
    $token = get_service_account_token();
} catch (Exception $e) {
    send('ERROR: Could not authenticate: ' . $e->getMessage());
    exit;
}

$folder_cache = []; // path (as string) -> folder ID, avoids recreating folders repeatedly

function get_or_create_folder_path($token, $root_id, $path_parts, &$cache) {
    $cache_key = implode('/', $path_parts);
    if (isset($cache[$cache_key])) return $cache[$cache_key];

    $parent_id = $root_id;
    $built = [];
    foreach ($path_parts as $name) {
        $built[] = $name;
        $step_key = implode('/', $built);
        if (isset($cache[$step_key])) {
            $parent_id = $cache[$step_key];
            continue;
        }
        $query = "'$parent_id' in parents and name = '" . addslashes($name) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        $ch = curl_init('https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => $query, 'fields' => 'files(id,name)', 'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true', 'corpora' => 'allDrives',
        ]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]);
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($result['files'])) {
            $folder_id = $result['files'][0]['id'];
        } else {
            $ch = curl_init('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
                CURLOPT_POSTFIELDS => json_encode(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parent_id]]),
            ]);
            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);
            $folder_id = $result['id'];
        }
        $cache[$step_key] = $folder_id;
        $parent_id = $folder_id;
    }
    return $parent_id;
}

$total = 0;
$done = 0;
$skipped = 0;
$failed = 0;

foreach ($selected as $key) {
    if (!isset($catalog[$key])) continue;
    $total += count($catalog[$key]['files']);
}
send("START total=$total");

foreach ($selected as $key) {
    if (!isset($catalog[$key])) {
        send("WARN Unknown qualification key: $key");
        continue;
    }
    foreach ($catalog[$key]['files'] as [$path, $filename, $url]) {
        $path_str = implode('/', $path);

        if ($url === 'TODO_URL') {
            get_or_create_folder_path($token, $folder_id, $path, $folder_cache);
            send("SKIP $path_str/$filename (no URL yet)");
            $skipped++;
            continue;
        }

        try {
            $target_folder = get_or_create_folder_path($token, $folder_id, $path, $folder_cache);

            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
            $pdf_data = curl_exec($ch);
            $fetch_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($fetch_code !== 200 || strlen($pdf_data) < 1000) {
                throw new Exception("Fetch failed (HTTP $fetch_code)");
            }

            $boundary = uniqid();
            $metadata = json_encode(['name' => $filename, 'parents' => [$target_folder]]);
            $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadata\r\n"
                  . "--$boundary\r\nContent-Type: application/pdf\r\n\r\n$pdf_data\r\n--$boundary--";

            $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: multipart/related; boundary=$boundary"],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 60,
            ]);
            $upload_result = curl_exec($ch);
            $upload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($upload_code !== 200) {
                throw new Exception("Upload failed (HTTP $upload_code): $upload_result");
            }

            send("OK $path_str/$filename");
            $done++;
        } catch (Exception $e) {
            send("FAIL $path_str/$filename : " . $e->getMessage());
            $failed++;
        }
    }
}

send("DONE done=$done skipped=$skipped failed=$failed total=$total");
