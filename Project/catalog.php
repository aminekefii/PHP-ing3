<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Catalog — TEK-UP Certified Students';
$active     = 'certifications';
$user_id    = (int) ($_SESSION['user']['id'] ?? 0);

$flash = '';
$flash_type = '';

// Enroll the user in a certification (POST cert_id + password).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cert_id'])) {
    $cert_id  = (int) $_POST['cert_id'];
    $password = $_POST['password'] ?? '';

    // Compute the currently allowed (featured) certification ids to guard the POST.
    $featured_check = $pdo->query(
        "SELECT id FROM certifications
         ORDER BY
            category,
            CASE WHEN code = 'PCEP' THEN 0
                 WHEN code = 'PCAP' THEN 1
                 ELSE 2 END,
            provider,
            code
         LIMIT 3"
    )->fetchAll();
    $allowed_ids = array_map(static fn($r) => (int) $r['id'], $featured_check);

    if (!in_array($cert_id, $allowed_ids, true)) {
        $flash = 'That certification is not currently open for enrolment.';
        $flash_type = 'error';
    } elseif ($password === '') {
        $flash = 'Please enter your password to confirm the enrolment.';
        $flash_type = 'error';
    } else {
        // Verify the user's password against the users table.
        $pwStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $pwStmt->execute([':id' => $user_id]);
        $userRow = $pwStmt->fetch();

        if (!$userRow || !hash_equals((string) $userRow['password'], (string) $password)) {
            $flash = 'Wrong password. Enrolment cancelled.';
            $flash_type = 'error';
        } else {
            try {
                $check = $pdo->prepare('SELECT id FROM certifications WHERE id = :id');
                $check->execute([':id' => $cert_id]);

                if (!$check->fetch()) {
                    $flash = 'That certification could not be found.';
                    $flash_type = 'error';
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO user_certifications (user_id, certification_id, status, progress)
                         VALUES (:uid, :cid, "in_progress", 0)
                         ON DUPLICATE KEY UPDATE status = status'
                    );
                    $ins->execute([':uid' => $user_id, ':cid' => $cert_id]);

                    if ($ins->rowCount() === 1) {
                        $flash = 'Enrolled successfully. The certification is now in progress on your dashboard.';
                        $flash_type = 'success';
                    } else {
                        $flash = 'You are already enrolled in that certification.';
                        $flash_type = 'info';
                    }
                }
            } catch (PDOException $e) {
                $flash = 'Enrollment failed. Please try again.';
                $flash_type = 'error';
            }
        }
    }
}

// Get IDs the user is already enrolled in so we disable those rows.
$mine = $pdo->prepare('SELECT certification_id FROM user_certifications WHERE user_id = :uid');
$mine->execute([':uid' => $user_id]);
$enrolled_ids = array_map('intval', array_column($mine->fetchAll(), 'certification_id'));

// Read full catalog and group by category.
// PCEP then PCAP are pinned to the top of the Technical section.
$all = $pdo->query(
    "SELECT id, code, name, provider, category, description
     FROM certifications
     ORDER BY
        category,
        CASE WHEN code = 'PCEP' THEN 0
             WHEN code = 'PCAP' THEN 1
             ELSE 2 END,
        provider,
        code"
)->fetchAll();

// Only the first three certifications in the rendering order can be enrolled in for now.
$featured_limit = 3;
$featured_ids   = array_map(static fn($r) => (int) $r['id'], array_slice($all, 0, $featured_limit));

$grouped = ['technical' => [], 'linguistic' => [], 'other' => []];
foreach ($all as $c) {
    $grouped[$c['category']][] = $c;
}

$category_labels = [
    'technical'  => 'Technical Certifications',
    'linguistic' => 'Linguistic Certifications',
    'other'      => 'Other Certifications',
];

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="catalog-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="catalog-hello">
            <h6>Certifications Catalog</h6>
            <h2>Pick a <em>track</em> &amp; <span>start your journey</span></h2>
            <p>All certifications are included in your TEK-UP tuition. Enroll in one to add it to your dashboard and start preparing.</p>
            <a href="certifications.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to my certifications</a>
          </div>
        </div>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="form-notice is-<?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($grouped as $cat_key => $list): if (empty($list)) continue; ?>
        <div class="catalog-section">
          <h3 class="catalog-category"><?= htmlspecialchars($category_labels[$cat_key]) ?> <span class="cat-count"><?= count($list) ?></span></h3>

          <div class="row catalog-grid">
            <?php $delay = 0.15; foreach ($list as $c):
                $is_enrolled = in_array((int) $c['id'], $enrolled_ids, true);
            ?>
            <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="<?= number_format($delay, 2) ?>s">
              <article class="catalog-card<?= $is_enrolled ? ' is-enrolled' : '' ?>">
                <div class="cat-top">
                  <span class="cat-provider"><?= htmlspecialchars($c['provider']) ?></span>
                  <span class="cat-code"><?= htmlspecialchars($c['code']) ?></span>
                </div>
                <h4><?= htmlspecialchars($c['name']) ?></h4>
                <p><?= htmlspecialchars($c['description']) ?></p>
                <div class="cat-action">
                  <?php
                    $is_featured = in_array((int) $c['id'], $featured_ids, true);
                  ?>
                  <?php if ($is_enrolled): ?>
                    <span class="enrolled-pill"><i class="fa fa-check"></i> Enrolled</span>
                  <?php elseif ($is_featured): ?>
                    <button type="button"
                            class="enroll-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#enrollConfirmModal"
                            data-cert-id="<?= (int) $c['id'] ?>"
                            data-cert-name="<?= htmlspecialchars($c['name']) ?>"
                            data-cert-code="<?= htmlspecialchars($c['code']) ?>">
                      Enroll &rarr;
                    </button>
                  <?php else: ?>
                    <button type="button" class="enroll-btn is-locked" disabled aria-disabled="true" title="Not open for enrolment yet">
                      <i class="fa fa-lock"></i> Locked
                    </button>
                  <?php endif; ?>
                </div>
              </article>
            </div>
            <?php $delay = min($delay + 0.05, 0.6); endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

  <!-- Password confirmation modal (one per page, reused for every Enroll button) -->
  <div class="modal fade" id="enrollConfirmModal" tabindex="-1" aria-labelledby="enrollConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content enroll-modal">
        <form action="catalog.php" method="post" autocomplete="off">
          <div class="modal-body">
            <div class="enroll-icon"><i class="fa fa-lock"></i></div>
            <h4 id="enrollConfirmLabel">Confirm enrolment</h4>
            <p>You are about to enroll in <strong id="enrollCertName">&mdash;</strong> <span class="enroll-cert-code" id="enrollCertCode"></span>. Please enter your password to confirm.</p>

            <input type="hidden" name="cert_id" id="enrollCertId" value="">

            <fieldset>
              <label for="enrollPassword" class="visually-hidden">Password</label>
              <input type="password"
                     name="password"
                     id="enrollPassword"
                     class="enroll-password-input"
                     placeholder="Your password"
                     autocomplete="current-password"
                     required>
            </fieldset>

            <div class="enroll-actions">
              <button type="submit" class="main-button">Confirm enrolment</button>
              <button type="button" class="cancel-link" data-bs-dismiss="modal">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var modalEl = document.getElementById('enrollConfirmModal');
      if (!modalEl) return;

      modalEl.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('enrollCertId').value     = btn.getAttribute('data-cert-id') || '';
        document.getElementById('enrollCertName').textContent = btn.getAttribute('data-cert-name') || '';
        var codeEl = document.getElementById('enrollCertCode');
        var code = btn.getAttribute('data-cert-code');
        codeEl.textContent = code ? '(' + code + ')' : '';
      });

      modalEl.addEventListener('shown.bs.modal', function () {
        document.getElementById('enrollPassword').focus();
      });

      modalEl.addEventListener('hidden.bs.modal', function () {
        document.getElementById('enrollPassword').value = '';
      });
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
