<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id = (int) ($_SESSION['user']['id'] ?? 0);

// Track voucher requests in session for the demo (no DB writes — keeps the
// page reset-able just by signing out). Map: cert_id => unix ts of request.
if (!isset($_SESSION['voucher_requested']) || !is_array($_SESSION['voucher_requested'])) {
    $_SESSION['voucher_requested'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_voucher_cert_id'])) {
    $req_cid = (int) $_POST['request_voucher_cert_id'];

    if ($req_cid > 0) {
        // Defence in depth: only let the user request a voucher for a cert
        // they've actually finished.
        $check = $pdo->prepare(
            'SELECT 1 FROM user_certifications
             WHERE user_id = :uid AND certification_id = :cid AND progress >= 100
             LIMIT 1'
        );
        $check->execute([':uid' => $user_id, ':cid' => $req_cid]);

        if ($check->fetchColumn()) {
            $_SESSION['voucher_requested'][$req_cid] = time();
        }
    }

    // PRG so refresh doesn't resubmit.
    header('Location: final-exams.php?flash=requested');
    exit;
}

$flash = (isset($_GET['flash']) && $_GET['flash'] === 'requested')
    ? "Voucher request submitted — our certifications office will email you within one business day."
    : null;

// Courses ready for the real exam: progress = 100. (The white test is a
// practice tool and isn't persisted, so progress is the authoritative gate.)
$stmt = $pdo->prepare(
    "SELECT c.id, c.code, c.name, c.provider, c.description, uc.progress
     FROM user_certifications uc
     INNER JOIN certifications c ON c.id = uc.certification_id
     WHERE uc.user_id = :uid AND uc.progress >= 100
     ORDER BY uc.enrolled_at DESC"
);
$stmt->execute([':uid' => $user_id]);
$eligible = $stmt->fetchAll();

$page_title = 'Final Exam Vouchers — TEK-UP';
$active     = 'final-exams';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>Certified Students Portal</h6>
            <h2>Final Exam <em>Vouchers</em></h2>
            <p>Your course material is complete. Request a voucher to schedule the official certification exam at a TEK-UP testing centre &mdash; one business day turnaround.</p>
          </div>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="fe-flash wow fadeIn" data-wow-duration="0.6s">
              <i class="fa fa-paper-plane" aria-hidden="true"></i>
              <span><?= htmlspecialchars($flash) ?></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (empty($eligible)): ?>
        <div class="row">
          <div class="col-lg-8 offset-lg-2">
            <div class="fe-empty wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.15s">
              <div class="fe-empty__icon"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
              <h3>No courses ready yet</h3>
              <p>Finish every video in a certification track, pass the white test, and the cert will appear here so you can request your exam voucher.</p>
              <a href="certifications.php" class="wt-btn wt-btn--primary">
                <span>View my certifications</span>
                <span class="wt-btn-arrow" aria-hidden="true">→</span>
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-12">
            <ul class="fe-list">
              <?php $delay = 0.15; foreach ($eligible as $c):
                $cid          = (int) $c['id'];
                $is_requested = isset($_SESSION['voucher_requested'][$cid]);
              ?>
                <li class="fe-row wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="<?= number_format($delay, 2) ?>s">
                  <div class="fe-row__seal" aria-hidden="true">
                    <i class="fa <?= $is_requested ? 'fa-hourglass-half' : 'fa-check-circle' ?>"></i>
                  </div>
                  <div class="fe-row__meta">
                    <div class="fe-row__provider"><?= htmlspecialchars($c['provider']) ?> &middot; <?= htmlspecialchars($c['code']) ?></div>
                    <h4 class="fe-row__title"><?= htmlspecialchars($c['name']) ?></h4>
                    <p class="fe-row__desc"><?= htmlspecialchars($c['description']) ?></p>
                    <div class="fe-row__tags">
                      <span class="fe-tag fe-tag--ready"><i class="fa fa-check" aria-hidden="true"></i> Course complete</span>
                      <?php if ($is_requested): ?>
                        <span class="fe-tag fe-tag--pending"><i class="fa fa-clock-o" aria-hidden="true"></i> Voucher requested</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="fe-row__action">
                    <?php if ($is_requested): ?>
                      <button type="button" class="wt-btn wt-btn--soft" disabled>Voucher requested</button>
                      <span class="fe-row__note">We'll be in touch soon.</span>
                    <?php else: ?>
                      <form method="post" action="final-exams.php">
                        <input type="hidden" name="request_voucher_cert_id" value="<?= $cid ?>">
                        <button type="submit" class="wt-btn wt-btn--primary">
                          <span>Request voucher</span>
                          <span class="wt-btn-arrow" aria-hidden="true">→</span>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php $delay += 0.08; endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
