<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

// Lookup the video's parent section + certification.
$lookup = $pdo->prepare(
    "SELECT cs.id           AS section_id,
            c.id            AS cert_id
     FROM course_videos cv
     JOIN course_sections cs ON cs.id = cv.section_id
     JOIN certifications  c  ON c.id  = cs.certification_id
     WHERE cv.id = :vid LIMIT 1"
);
$lookup->execute([':vid' => $video_id]);
$ref = $lookup->fetch();

if (!$ref) {
    http_response_code(404);
    echo json_encode(['error' => 'video_not_found']);
    exit;
}

$cert_id    = (int) $ref['cert_id'];
$section_id = (int) $ref['section_id'];

// Enrolment check.
$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => $cert_id]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'not_enrolled']);
    exit;
}

// Record the watched event (idempotent — keeps the original timestamp on duplicate).
$ins = $pdo->prepare(
    "INSERT INTO video_progress (user_id, video_id)
     VALUES (:uid, :vid)
     ON DUPLICATE KEY UPDATE watched_at = watched_at"
);
$ins->execute([':uid' => $user_id, ':vid' => $video_id]);

// Recompute the cert-level percentage and push it onto user_certifications.
// Placeholders must be unique because PDO::ATTR_EMULATE_PREPARES is false.
$rollup = $pdo->prepare(
    "UPDATE user_certifications uc
     JOIN (
         SELECT cs.certification_id,
                ROUND(100 * SUM(vp.video_id IS NOT NULL) / NULLIF(COUNT(cv.id), 0)) AS pct
         FROM course_sections cs
         JOIN course_videos   cv ON cv.section_id = cs.id
         LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid_inner
         WHERE cs.certification_id = :cid_inner
     ) calc ON calc.certification_id = uc.certification_id
     SET uc.progress = COALESCE(calc.pct, 0)
     WHERE uc.user_id = :uid_outer AND uc.certification_id = :cid_outer"
);
$rollup->execute([
    ':uid_inner' => $user_id, ':cid_inner' => $cert_id,
    ':uid_outer' => $user_id, ':cid_outer' => $cert_id,
]);

// Per-section counts for the response (used to refresh the left rail).
$sec = $pdo->prepare(
    "SELECT COUNT(cv.id)                          AS total,
            SUM(vp.video_id IS NOT NULL)          AS watched
     FROM course_videos cv
     LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid
     WHERE cv.section_id = :sid"
);
$sec->execute([':uid' => $user_id, ':sid' => $section_id]);
$sec_row = $sec->fetch() ?: ['total' => 0, 'watched' => 0];

// Re-read the new percentage.
$pct = (int) $pdo->query(
    "SELECT progress FROM user_certifications
     WHERE user_id = {$user_id} AND certification_id = {$cert_id}"
)->fetchColumn();

echo json_encode([
    'ok'       => true,
    'progress' => $pct,
    'section'  => [
        'id'      => $section_id,
        'total'   => (int) $sec_row['total'],
        'watched' => (int) $sec_row['watched'],
    ],
]);
