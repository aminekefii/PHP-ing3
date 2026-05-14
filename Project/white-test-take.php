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

<?php
$questions = white_test_questions($wt['cert_code']);
$q         = $questions[$wt['current_q']];
$selected  = $wt['answers'][$wt['current_q']] ?? null;
$is_last   = ((int) $wt['current_q'] === count($questions) - 1);
$mm        = str_pad((string) intdiv($remaining, 60), 2, '0', STR_PAD_LEFT);
$ss        = str_pad((string) ($remaining % 60), 2, '0', STR_PAD_LEFT);
?>

  <div class="course-page section">
    <div class="container">

      <div class="wt-header">
        <span class="wt-counter">Question <?= (int) $wt['current_q'] + 1 ?> / <?= count($questions) ?></span>
        <span class="wt-timer" id="wtTimer"><?= $mm ?>:<?= $ss ?></span>
      </div>

      <form id="wtForm" method="post" action="white-test-take.php">
        <div class="wt-card">
          <div class="wt-question">
            <?= htmlspecialchars($q['question']) ?>
          </div>
          <div class="wt-choices">
            <?php foreach ($q['choices'] as $letter => $text):
              $is_selected = ($selected === $letter);
            ?>
              <label class="wt-choice<?= $is_selected ? ' is-selected' : '' ?>">
                <input type="radio" name="answer" value="<?= htmlspecialchars($letter) ?>" <?= $is_selected ? 'checked' : '' ?>>
                <span><strong><?= $letter ?>)</strong> <?= htmlspecialchars($text) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="wt-submit-row">
          <button type="submit" class="main-button">
            <?= $is_last ? 'Submit' : 'Next →' ?>
          </button>
        </div>
      </form>

    </div>
  </div>

  <script>
  // Highlight the selected choice card live (no submit yet — Task 4 wires POST).
  document.querySelectorAll('.wt-choice input[type="radio"]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.wt-choice').forEach(function (c) { c.classList.remove('is-selected'); });
      r.closest('.wt-choice').classList.add('is-selected');
    });
  });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
