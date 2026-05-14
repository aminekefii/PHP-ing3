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
if (!$cert) {
    header('Location: certifications.php');
    exit;
}

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

          <div class="wt-review">
            <?php foreach ($result['per_question'] as $i => $r):
              $user_letter   = $r['user_answer'];
              $correct_letter= $r['correct_answer'];
              $user_text     = $user_letter ? $user_letter . ') ' . $r['choices'][$user_letter] : '— no answer —';
              $correct_text  = $correct_letter . ') ' . $r['choices'][$correct_letter];
              $item_class    = $r['is_correct'] ? 'is-correct' : 'is-wrong';
              $pick_class    = $r['is_correct'] ? 'is-correct' : ($user_letter ? 'is-wrong' : 'is-missing');
            ?>
              <div class="wt-review-item <?= $item_class ?>">
                <div class="wt-review-q"><?= ($i + 1) ?>. <?= htmlspecialchars($r['question']) ?></div>
                <div class="wt-review-line">
                  <span class="wt-label">Your answer</span>
                  <span class="wt-pick <?= $pick_class ?>"><?= htmlspecialchars($user_text) ?></span>
                </div>
                <?php if (!$r['is_correct']): ?>
                  <div class="wt-review-line">
                    <span class="wt-label">Correct answer</span>
                    <span class="wt-pick is-correct"><?= htmlspecialchars($correct_text) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="wt-actions">
            <?php if ($result['passed']): ?>
              <a href="#" class="wt-btn wt-btn--primary">
                <span>Final exam</span>
                <span class="wt-btn-arrow" aria-hidden="true">→</span>
              </a>
              <a href="dashboard.php" class="wt-btn wt-btn--ghost">Back to dashboard</a>
            <?php else: ?>
              <form method="post" action="white-test.php?cert=<?= (int) $cert_id ?>">
                <input type="hidden" name="retake" value="1">
                <button type="submit" class="wt-btn wt-btn--soft">Retake exam</button>
              </form>
              <a href="dashboard.php" class="wt-btn wt-btn--ghost">Back to dashboard</a>
            <?php endif; ?>
          </div>

        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
