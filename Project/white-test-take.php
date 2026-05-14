<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/white-test-questions.php';

if (empty($_SESSION['white_test'])) {
    header('Location: certifications.php');
    exit;
}

$wt = &$_SESSION['white_test'];

if (!empty($wt['submitted'])) {
    header('Location: white-test-result.php');
    exit;
}

$remaining = 600 - (time() - $wt['started_at']);
if ($remaining <= 0) {
    $wt['submitted'] = true;
    header('Location: white-test-result.php');
    exit;
}

$page_title = 'White Test — In progress';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">
      <p>White-test take page — question <?= (int) $wt['current_q'] + 1 ?> / 5. Remaining seconds: <?= (int) $remaining ?>.</p>
    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
