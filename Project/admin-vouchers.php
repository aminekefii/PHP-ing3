<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid    = isset($_POST['voucher_id']) ? (int) $_POST['voucher_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($vid > 0 && in_array($action, ['approve', 'reject', 'reset'], true)) {
        // Approve requires re-entering the admin password as a final guard.
        if ($action === 'approve') {
            $entered = (string) ($_POST['admin_password'] ?? '');
            if (!hash_equals('admin', $entered)) {
                header('Location: admin-vouchers.php?flash=bad_password');
                exit;
            }
        }

        $new_status = ['approve' => 'approved', 'reject' => 'rejected', 'reset' => 'pending'][$action];
        $upd = $pdo->prepare(
            'UPDATE voucher_requests SET status = :st WHERE id = :id'
        );
        $upd->execute([':st' => $new_status, ':id' => $vid]);

        // After a successful approve, pre-compose a mailto URL so the admin
        // can fire off the voucher email in one click.
        if ($action === 'approve') {
            $info_stmt = $pdo->prepare(
                "SELECT u.email, u.firstname, u.lastname,
                        c.name AS cert_name, c.code AS cert_code
                 FROM voucher_requests vr
                 INNER JOIN users          u ON u.id = vr.user_id
                 INNER JOIN certifications c ON c.id = vr.certification_id
                 WHERE vr.id = :id LIMIT 1"
            );
            $info_stmt->execute([':id' => $vid]);
            $info = $info_stmt->fetch();

            if ($info) {
                $cert_name    = (string) $info['cert_name'];
                $student_name = trim($info['firstname'] . ' ' . $info['lastname']);
                if ($student_name === '') $student_name = (string) $info['email'];
                $expiration   = date('d M Y', strtotime('+10 days'));
                $subject      = 'Your TEK-UP Voucher — ' . $cert_name;

                $body = "Dear {$student_name},\n\n"
                      . "Congratulations on successfully passing the certification requirements.\n\n"
                      . "Please find below your voucher information for the certificate:\n\n"
                      . "---\n\n"
                      . "Voucher Code: [INSERT VOUCHER CODE]\n"
                      . "Certificate: {$cert_name}\n"
                      . "Expiration Date: {$expiration}\n"
                      . "----------------------------------\n\n"
                      . "You can use this voucher to complete your certification process according to the provided instructions.\n\n"
                      . "If you have any questions or need assistance, feel free to contact us.\n\n"
                      . "Best regards,\n"
                      . "TEKUP Certified Student Office";

                $_SESSION['voucher_mailto'] = [
                    'href'  => 'mailto:' . rawurlencode($info['email'])
                               . '?subject=' . rawurlencode($subject)
                               . '&body='    . rawurlencode($body),
                    'email' => (string) $info['email'],
                ];
            }
        }
    }

    header('Location: admin-vouchers.php?flash=' . urlencode($action));
    exit;
}

// One-shot pickup: read and clear the pending mailto so refresh doesn't refire.
$pending_mailto = $_SESSION['voucher_mailto'] ?? null;
if ($pending_mailto !== null) {
    unset($_SESSION['voucher_mailto']);
}

$filter   = $_GET['filter'] ?? 'all';
$valid    = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $valid, true)) $filter = 'all';

$sql = "SELECT vr.id, vr.status, vr.requested_at,
               u.id AS user_id, u.email, u.firstname, u.lastname,
               c.code AS cert_code, c.name AS cert_name, c.provider
        FROM voucher_requests vr
        INNER JOIN users          u ON u.id = vr.user_id
        INNER JOIN certifications c ON c.id = vr.certification_id";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE vr.status = :st';
    $params[':st'] = $filter;
}
$sql .= ' ORDER BY vr.requested_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Counts for the filter chips.
$count_rows = $pdo->query(
    "SELECT status, COUNT(*) AS n FROM voucher_requests GROUP BY status"
)->fetchAll();
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($count_rows as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $counts['all']       += (int) $r['n'];
}

$flash_action = $_GET['flash'] ?? null;
$flash_text   = [
    'approve'      => 'Voucher approved.',
    'reject'       => 'Voucher request declined.',
    'reset'        => 'Status reset to pending.',
    'bad_password' => 'Incorrect admin password — the voucher was not approved.',
][$flash_action] ?? null;
$flash_is_error = $flash_action === 'bad_password';

$page_title = 'Voucher Requests — Admin';
$active     = 'admin-vouchers';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>TEK-UP Admin Console</h6>
            <h2>Voucher <em>Requests</em></h2>
            <p>Review and action exam-voucher requests submitted by students. Approving a request notifies the student that their voucher is ready.</p>
          </div>
        </div>
      </div>

      <?php if ($flash_text || $pending_mailto): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="fe-flash<?= $flash_is_error ? ' is-error' : '' ?><?= $pending_mailto ? ' has-action' : '' ?>">
              <i class="fa <?= $flash_is_error ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>" aria-hidden="true"></i>
              <span class="fe-flash__text">
                <?php if ($flash_text): ?>
                  <?= htmlspecialchars($flash_text) ?>
                <?php endif; ?>
                <?php if ($pending_mailto): ?>
                  <span class="fe-flash__sub">Open the voucher email for <strong><?= htmlspecialchars($pending_mailto['email']) ?></strong> in your mail client.</span>
                <?php endif; ?>
              </span>
              <?php if ($pending_mailto): ?>
                <a class="wt-btn wt-btn--primary fe-flash__action"
                   href="<?= htmlspecialchars($pending_mailto['href']) ?>">
                  <span>Compose voucher email</span>
                  <span class="wt-btn-arrow" aria-hidden="true">→</span>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-12">
          <div class="admin-filters">
            <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
              <a class="admin-chip<?= $filter === $key ? ' is-active' : '' ?>" href="admin-vouchers.php?filter=<?= htmlspecialchars($key) ?>">
                <?= htmlspecialchars($label) ?>
                <span class="admin-chip-count"><?= $counts[$key] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if (empty($requests)): ?>
        <div class="row">
          <div class="col-lg-8 offset-lg-2">
            <div class="fe-empty">
              <div class="fe-empty__icon"><i class="fa fa-inbox" aria-hidden="true"></i></div>
              <h3>Inbox empty</h3>
              <p>No voucher requests match this filter. Approved and rejected requests stay here for the audit trail.</p>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-12">
            <ul class="fe-list">
              <?php foreach ($requests as $r):
                $status = (string) $r['status'];
                $name   = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                if ($name === '') $name = (string) $r['email'];
              ?>
                <li class="fe-row">
                  <div class="fe-row__seal" aria-hidden="true">
                    <?php
                      $seal_icon = 'fa-hourglass-half';
                      if ($status === 'approved') $seal_icon = 'fa-trophy';
                      if ($status === 'rejected') $seal_icon = 'fa-times-circle';
                    ?>
                    <i class="fa <?= $seal_icon ?>"></i>
                  </div>
                  <div class="fe-row__meta">
                    <div class="fe-row__provider"><?= htmlspecialchars($r['provider']) ?> &middot; <?= htmlspecialchars($r['cert_code']) ?></div>
                    <h4 class="fe-row__title"><?= htmlspecialchars($r['cert_name']) ?></h4>
                    <p class="fe-row__desc">
                      Requested by <strong><?= htmlspecialchars($name) ?></strong>
                      &lt;<?= htmlspecialchars($r['email']) ?>&gt;
                      &middot; <?= htmlspecialchars(date('d M Y, H:i', strtotime($r['requested_at']))) ?>
                    </p>
                    <div class="fe-row__tags">
                      <?php if ($status === 'pending'): ?>
                        <span class="fe-tag fe-tag--pending"><i class="fa fa-clock-o" aria-hidden="true"></i> Pending</span>
                      <?php elseif ($status === 'approved'): ?>
                        <span class="fe-tag fe-tag--approved"><i class="fa fa-check-circle" aria-hidden="true"></i> Approved</span>
                      <?php elseif ($status === 'rejected'): ?>
                        <span class="fe-tag fe-tag--rejected"><i class="fa fa-times-circle" aria-hidden="true"></i> Rejected</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="fe-row__action admin-actions">
                    <?php if ($status === 'pending'): ?>
                      <button type="button"
                              class="wt-btn wt-btn--primary"
                              data-bs-toggle="modal"
                              data-bs-target="#approveVoucherModal"
                              data-voucher-id="<?= (int) $r['id'] ?>"
                              data-student-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                              data-cert-name="<?= htmlspecialchars($r['cert_name'], ENT_QUOTES) ?>"
                              data-cert-code="<?= htmlspecialchars($r['cert_code'], ENT_QUOTES) ?>">
                        <span>Approve</span>
                        <span class="wt-btn-arrow" aria-hidden="true">→</span>
                      </button>
                      <form method="post" action="admin-vouchers.php">
                        <input type="hidden" name="voucher_id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="wt-btn wt-btn--ghost">Decline</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="admin-vouchers.php">
                        <input type="hidden" name="voucher_id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="wt-btn wt-btn--soft">Reset to pending</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Approve-with-password modal (shared by every pending row) -->
  <div class="modal fade" id="approveVoucherModal" tabindex="-1" aria-labelledby="approveVoucherLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content enroll-modal approve-modal">
        <form method="post" action="admin-vouchers.php" autocomplete="off">
          <div class="modal-body">
            <div class="enroll-icon approve-icon"><i class="fa fa-key"></i></div>
            <h4 id="approveVoucherLabel">Confirm voucher approval</h4>
            <p>You are about to approve the voucher for
              <strong id="approveStudentName">&mdash;</strong>
              on <strong id="approveCertName">&mdash;</strong>
              <span class="approve-cert-code" id="approveCertCode"></span>.
              Re-enter your admin password to confirm.</p>

            <input type="hidden" name="voucher_id" id="approveVoucherId" value="">
            <input type="hidden" name="action" value="approve">

            <div class="approve-pw-wrap">
              <label for="approveAdminPw" class="approve-pw-label">Admin password</label>
              <input type="password"
                     name="admin_password"
                     id="approveAdminPw"
                     class="approve-pw-input"
                     placeholder="••••••••"
                     autocomplete="current-password"
                     required>
            </div>

            <div class="enroll-actions">
              <button type="submit" class="wt-btn wt-btn--primary">
                <span>Confirm approval</span>
                <span class="wt-btn-arrow" aria-hidden="true">→</span>
              </button>
              <button type="button" class="cancel-link" data-bs-dismiss="modal">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var modalEl = document.getElementById('approveVoucherModal');
      if (!modalEl) return;

      var idEl   = document.getElementById('approveVoucherId');
      var sName  = document.getElementById('approveStudentName');
      var cName  = document.getElementById('approveCertName');
      var cCode  = document.getElementById('approveCertCode');
      var pwEl   = document.getElementById('approveAdminPw');

      modalEl.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        idEl.value      = btn.getAttribute('data-voucher-id') || '';
        sName.textContent = btn.getAttribute('data-student-name') || '—';
        cName.textContent = btn.getAttribute('data-cert-name')   || '—';
        var code = btn.getAttribute('data-cert-code');
        cCode.textContent = code ? '(' + code + ')' : '';
        pwEl.value = '';
      });

      modalEl.addEventListener('shown.bs.modal', function () {
        pwEl.focus();
      });
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
