<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT cv.subtitle_filename,
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

if (!$row || !$row['subtitle_filename']) {
    http_response_code(404);
    exit;
}

$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => (int) $row['cert_id']]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    exit;
}

$relative = $row['cert_media_path'] . '/' . $row['section_folder'] . '/' . $row['subtitle_filename'];
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

$srt = @file_get_contents($real);
if ($srt === false) {
    http_response_code(500);
    exit;
}

// SRT -> WebVTT: prepend "WEBVTT\n\n" and swap ',' to '.' in timestamps.
$vtt = "WEBVTT\n\n" . preg_replace(
    '/(\d{2}:\d{2}:\d{2}),(\d{3})/',
    '$1.$2',
    $srt
);

header('Content-Type: text/vtt; charset=utf-8');
header('Content-Length: ' . strlen($vtt));
echo $vtt;
