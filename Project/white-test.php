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

// Start-the-test POST handler — initialises the session and hands off to the
// quiz page. Re-validates progress as defence in depth.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $enroll_row['progress'] >= 100) {
    $_SESSION['white_test'] = [
        'cert_id'    => (int) $cert['id'],
        'cert_code'  => (string) $cert['code'],
        'started_at' => time(),
        'current_q'  => 0,
        'answers'    => [null, null, null, null, null],
        'submitted'  => false,
    ];
    header('Location: white-test-take.php');
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
            <form method="post" action="white-test.php?cert=<?= (int) $cert_id ?>" style="margin-top:16px;">
              <button type="submit" class="main-button">Start the test</button>
            </form>

            <div class="test-meta">
              <div class="test-meta-card">
                <i class="fa fa-clock-o" aria-hidden="true"></i>
                <span class="test-meta-caption">Time limit</span>
                <span class="test-meta-value">10 minutes</span>
              </div>
              <div class="test-meta-card">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <span class="test-meta-caption">Questions</span>
                <span class="test-meta-value">5</span>
              </div>
              <div class="test-meta-card">
                <i class="fa fa-percent" aria-hidden="true"></i>
                <span class="test-meta-caption">Passing score</span>
                <span class="test-meta-value">70%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
