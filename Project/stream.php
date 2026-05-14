<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'bad_request']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT cv.filename,
            cs.folder       AS section_folder,
            c.media_path    AS cert_media_path,
            c.id            AS cert_id
     FROM course_videos cv
     JOIN course_sections cs ON cs.id = cv.section_id
     JOIN certifications  c  ON c.id  = cs.certification_id
     WHERE cv.id = :vid LIMIT 1"
);
$stmt->execute([':vid' => $video_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit;
}

// Enrolment check: the signed-in user must have a user_certifications row for this cert.
$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => (int) $row['cert_id']]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    exit;
}

// Release the session lock before the long I/O. PHP's default file-based
// sessions hold an exclusive lock for the whole request, so without this any
// concurrent request from the same user (progress.php POSTs, refreshing
// course.php, navigating back) blocks until this stream ends.
session_write_close();

// Resolve disk path and confine it under MEDIA_ROOT.
$relative = $row['cert_media_path'] . '/' . $row['section_folder'] . '/' . $row['filename'];
$path     = MEDIA_ROOT . '/' . $relative;
$real     = realpath($path);
$root     = realpath(MEDIA_ROOT);

if ($real === false || $root === false || strncmp($real, $root, strlen($root)) !== 0) {
    http_response_code(403);
    exit;
}

if (!is_file($real)) {
    http_response_code(404);
    exit;
}

// Stream binary content straight to the network: kill any active output buffers
// (XAMPP's default output_buffering = 4096 would otherwise hold chunks back),
// disable zlib compression for this response, lift PHP's execution timeout
// (a long video could exceed the default 30s), and keep streaming even if the
// user navigates away mid-video.
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@set_time_limit(0);
@ignore_user_abort(true);
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Defensive: stop Apache/intermediaries from compressing or buffering the body.
header_remove('Content-Encoding');
header('X-Accel-Buffering: no');

$size  = filesize($real);
$start = 0;
$end   = $size - 1;
$mime  = 'video/mp4';

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);

if (isset($_SERVER['HTTP_RANGE'])
    && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int) $m[1];
    if ($m[2] !== '') $end   = (int) $m[2];

    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */{$size}");
        exit;
    }
    if ($end >= $size) $end = $size - 1;

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

$length = $end - $start + 1;
header("Content-Length: {$length}");

$fp = fopen($real, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);

// 1 MB chunks. With 8 KB the loop runs ~10 000 times for an 86 MB video and
// the per-iteration overhead (echo+flush) dwarfs the I/O. 1 MB is a sweet spot
// for HTML5 video on a LAN: still streamy enough to seek, much less overhead.
$chunk_size = 1024 * 1024;
$remaining  = $length;
while ($remaining > 0 && !feof($fp)) {
    $read = $remaining < $chunk_size ? $remaining : $chunk_size;
    echo fread($fp, $read);
    flush();
    if (connection_status() !== CONNECTION_NORMAL) break;
    $remaining -= $read;
}
fclose($fp);
exit;
