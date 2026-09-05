<?php
/**
 * SPOTTER — On-demand PDF chopper
 * Fetches a PDF from Google Drive, extracts a page range, streams it back.
 * Cache: /tmp/spotter_cache/
 *
 * Usage: /chop.php?file=DRIVE_FILE_ID&start=5&end=9
 */

// ── Config ─────────────────────────────────────────────────────────────────
define('DRIVE_API_KEY', 'AIzaSyBLhhDyaT80ojTut9cjrg7Ki_tTlvqt-RE');
define('CACHE_DIR', '/tmp/spotter_cache');
define('CACHE_TTL', 3600 * 24); // 24 hours

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── Params ─────────────────────────────────────────────────────────────────
$file_id   = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['file'] ?? '');
$start     = max(1, (int)($_GET['start'] ?? 1));
$end       = max(1, (int)($_GET['end']   ?? 1));

if (!$file_id) { http_response_code(400); echo 'Missing file'; exit; }
if ($end < $start) $end = $start;

// ── Cache paths ────────────────────────────────────────────────────────────
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0777, true);

$full_cache  = CACHE_DIR . '/' . $file_id . '_full.pdf';
$chunk_cache = CACHE_DIR . '/' . $file_id . "_p{$start}-{$end}.pdf";

// ── Serve cached chunk if available ────────────────────────────────────────
if (file_exists($chunk_cache) && (time() - filemtime($chunk_cache) < CACHE_TTL)) {
    serve_pdf($chunk_cache);
    exit;
}

// ── Fetch full PDF if not cached ───────────────────────────────────────────
if (!file_exists($full_cache) || (time() - filemtime($full_cache) > CACHE_TTL)) {
    $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id
         . '?alt=media&key=' . DRIVE_API_KEY;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: SPOTTER/1.0'],
    ]);
    $pdf_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$pdf_data) {
        http_response_code(502);
        echo "Failed to fetch PDF (HTTP $http_code)";
        exit;
    }
    
    file_put_contents($full_cache, $pdf_data);
}

// ── Chop using Python/PyMuPDF ──────────────────────────────────────────────
$python_script = <<<'PYEOF'
import sys, fitz

full_path  = sys.argv[1]
chunk_path = sys.argv[2]
start      = int(sys.argv[3]) - 1  # 0-indexed
end        = int(sys.argv[4]) - 1  # 0-indexed

doc = fitz.open(full_path)
total = len(doc)
start = max(0, min(start, total-1))
end   = max(start, min(end, total-1))

new_doc = fitz.open()
for i in range(start, end+1):
    new_doc.insert_pdf(doc, from_page=i, to_page=i)
new_doc.save(chunk_path)
print("OK")
PYEOF;

$py_file = tempnam('/tmp', 'spotter_chop_') . '.py';
file_put_contents($py_file, $python_script);

$cmd = escapeshellcmd("python3 $py_file " 
    . escapeshellarg($full_cache) . ' '
    . escapeshellarg($chunk_cache) . ' '
    . escapeshellarg($start) . ' '
    . escapeshellarg($end));

$output = shell_exec($cmd . ' 2>&1');
unlink($py_file);

if (!file_exists($chunk_cache)) {
    http_response_code(500);
    echo "Chop failed: $output";
    exit;
}

serve_pdf($chunk_cache);

// ── Helper ─────────────────────────────────────────────────────────────────
function serve_pdf($path) {
    $size = filesize($path);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . $size);
    header('Cache-Control: public, max-age=86400');
    header('X-Chop-Size: ' . $size);
    readfile($path);
}
