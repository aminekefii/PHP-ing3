<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/white-test-questions.php';

if (empty($_SESSION['white_test']) || empty($_SESSION['white_test']['submitted'])) {
    // Try to send the user back to the intro for the cert they were working on,
    // otherwise to the certifications list.
    if (!empty($_SESSION['white_test']['cert_id'])) {
        header('Location: white-test.php?cert=' . (int) $_SESSION['white_test']['cert_id']);
    } else {
        header('Location: certifications.php');
    }
    exit;
}

$wt        = $_SESSION['white_test'];
$cert_id   = (int) $wt['cert_id'];
$cert_code = (string) $wt['cert_code'];
$result    = white_test_score($cert_code, $wt['answers']);

// Cert metadata for the page header (only needs name/provider).
$stmt = $pdo->prepare('SELECT name, provider, code FROM certifications WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $cert_id]);
$cert = $stmt->fetch();

$page_title = 'White Test — Result';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="course-head">
            <h6><?= htmlspecialchars($cert['provider']) ?> &middot; <?= htmlspecialchars($cert['code']) ?></h6>
            <h2>White Test &mdash; Result</h2>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 offset-lg-2">

          <div class="wt-result-header">
            <div class="wt-score"><?= (int) $result['correct'] ?> / <?= (int) $result['total'] ?></div>
            <div class="wt-pct"><?= (int) $result['pct'] ?> %</div>
            <?php if ($result['passed']): ?>
              <span class="wt-badge is-pass">✓ Passed</span>
            <?php else: ?>
              <span class="wt-badge is-fail">✗ Not passed</span>
            <?php endif; ?>
            <div class="wt-result-meta">Passing score: 70 %</div>
          </div>

          <!-- per-question review filled in Task 7 -->
          <!-- action buttons filled in Task 8 -->

        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
