<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid    = isset($_POST['voucher_id']) ? (int) $_POST['voucher_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($vid > 0 && in_array($action, ['approve', 'reject', 'reset'], true)) {
        $new_status = ['approve' => 'approved', 'reject' => 'rejected', 'reset' => 'pending'][$action];
        $upd = $pdo->prepare(
            'UPDATE voucher_requests SET status = :st WHERE id = :id'
        );
        $upd->execute([':st' => $new_status, ':id' => $vid]);
    }

    header('Location: admin-vouchers.php?flash=' . urlencode($action));
    exit;
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
    'approve' => 'Voucher approved.',
    'reject'  => 'Voucher request declined.',
    'reset'   => 'Status reset to pending.',
][$flash_action] ?? null;

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

      <?php if ($flash_text): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="fe-flash"><i class="fa fa-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($flash_text) ?></span></div>
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
                      <form method="post" action="admin-vouchers.php">
                        <input type="hidden" name="voucher_id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="wt-btn wt-btn--primary">
                          <span>Approve</span>
                          <span class="wt-btn-arrow" aria-hidden="true">→</span>
                        </button>
                      </form>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
