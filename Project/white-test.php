<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$cert_id = isset($_GET['cert']) ? (int) $_GET['cert'] : 0;

if ($cert_id <= 0) {
    header('Location: certifications.php');
    exit;
}

$enroll = $pdo->prepare(
    'SELECT progress FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => $cert_id]);
$enroll_row = $enroll->fetch();

if (!$enroll_row || (int) $enroll_row['progress'] < 100) {
    header('Location: course.php?cert=' . $cert_id);
    exit;
}

$cert_stmt = $pdo->prepare('SELECT id, code, name, provider FROM certifications WHERE id = :id LIMIT 1');
$cert_stmt->execute([':id' => $cert_id]);
$cert = $cert_stmt->fetch();

if (!$cert) {
    header('Location: certifications.php');
    exit;
}

$page_title = 'White Test — ' . htmlspecialchars($cert['name']);
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <a href="course.php?cert=<?= (int) $cert_id ?>" class="back-link"><i class="fa fa-arrow-left"></i> Back to the course</a>
          <div class="course-head">
            <h6><?= htmlspecialchars($cert['provider']) ?> &middot; <?= htmlspecialchars($cert['code']) ?></h6>
            <h2>White Test &mdash; <?= htmlspecialchars($cert['name']) ?></h2>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 offset-lg-2">
          <div class="course-stage" style="text-align:center;">
            <i class="fa fa-trophy" style="font-size:48px; color:#c9a44a;"></i>
            <h3 style="margin-top:16px;">You unlocked the white test</h3>
            <p style="margin-top:8px; max-width: 540px; margin-left:auto; margin-right:auto;">
              Congratulations on finishing every video in <?= htmlspecialchars($cert['name']) ?>.
              The white test is a final check of your knowledge before the official certification exam.
            </p>
            <a href="course.php?cert=<?= (int) $cert_id ?>" class="main-button" style="margin-top:16px;">Start the test</a>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
