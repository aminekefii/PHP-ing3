<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$total_users     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_certs     = (int) $pdo->query("SELECT COUNT(*) FROM certifications")->fetchColumn();
$total_enrol     = (int) $pdo->query("SELECT COUNT(*) FROM user_certifications")->fetchColumn();
$active_enrol    = (int) $pdo->query("SELECT COUNT(*) FROM user_certifications WHERE status='in_progress'")->fetchColumn();
$earned_enrol    = (int) $pdo->query("SELECT COUNT(*) FROM user_certifications WHERE status='earned'")->fetchColumn();
$ready_courses   = (int) $pdo->query("SELECT COUNT(*) FROM user_certifications WHERE progress >= 100")->fetchColumn();
$vouchers_total  = (int) $pdo->query("SELECT COUNT(*) FROM voucher_requests")->fetchColumn();
$vouchers_pend   = (int) $pdo->query("SELECT COUNT(*) FROM voucher_requests WHERE status='pending'")->fetchColumn();

// Top 3 certifications by enrolment count.
$top_certs = $pdo->query(
    "SELECT c.name, c.code, c.provider, COUNT(uc.id) AS n
     FROM certifications c
     LEFT JOIN user_certifications uc ON uc.certification_id = c.id
     GROUP BY c.id
     ORDER BY n DESC, c.code ASC
     LIMIT 5"
)->fetchAll();

$page_title = 'Statistics — Admin';
$active     = 'admin-stats';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>TEK-UP Admin Console</h6>
            <h2>Portal <em>Statistics</em></h2>
            <p>A snapshot of the certified-students portal: enrolment activity, course completion and voucher pipeline.</p>
          </div>
        </div>
      </div>

      <div class="row admin-stats-grid">
        <div class="col-lg-3 col-md-6">
          <div class="admin-stat">
            <div class="admin-stat__label">Registered students</div>
            <div class="admin-stat__value"><?= $total_users ?></div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="admin-stat">
            <div class="admin-stat__label">Certifications in catalog</div>
            <div class="admin-stat__value"><?= $total_certs ?></div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="admin-stat">
            <div class="admin-stat__label">Enrolments (total)</div>
            <div class="admin-stat__value"><?= $total_enrol ?></div>
            <div class="admin-stat__note"><?= $active_enrol ?> in progress &middot; <?= $earned_enrol ?> earned</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="admin-stat">
            <div class="admin-stat__label">Courses ready for exam</div>
            <div class="admin-stat__value"><?= $ready_courses ?></div>
            <div class="admin-stat__note">student-cert pairs at 100&nbsp;%</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="admin-stat">
            <div class="admin-stat__label">Voucher requests</div>
            <div class="admin-stat__value"><?= $vouchers_total ?></div>
            <div class="admin-stat__note"><?= $vouchers_pend ?> pending</div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 28px;">
        <div class="col-lg-12">
          <div class="admin-panel">
            <h3>Top 5 certifications by enrolment</h3>
            <?php if (empty($top_certs)): ?>
              <p class="admin-empty-line">No enrolments yet.</p>
            <?php else: ?>
              <ol class="admin-rank">
                <?php $max_n = max(1, (int) $top_certs[0]['n']); foreach ($top_certs as $row): $pct = $max_n > 0 ? (int) round(100 * $row['n'] / $max_n) : 0; ?>
                  <li class="admin-rank__item">
                    <div class="admin-rank__head">
                      <span class="admin-rank__provider"><?= htmlspecialchars($row['provider']) ?> &middot; <?= htmlspecialchars($row['code']) ?></span>
                      <span class="admin-rank__count"><?= (int) $row['n'] ?></span>
                    </div>
                    <div class="admin-rank__title"><?= htmlspecialchars($row['name']) ?></div>
                    <div class="admin-rank__bar"><span style="width: <?= $pct ?>%;"></span></div>
                  </li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
