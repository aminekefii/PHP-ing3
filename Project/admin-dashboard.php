<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Admin Dashboard — TEK-UP';
$active     = 'admin-dashboard';

// Headline counts for each card.
$pending_vouchers = (int) $pdo->query(
    "SELECT COUNT(*) FROM voucher_requests WHERE status = 'pending'"
)->fetchColumn();

$total_users = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_enrol = (int) $pdo->query("SELECT COUNT(*) FROM user_certifications")->fetchColumn();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="dashboard-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="dashboard-hello">
            <h6>TEK-UP Admin Console</h6>
            <h2>Good day, <em>Administrator</em></h2>
            <p>Manage voucher requests, monitor portal usage, post announcements and respond to student messages from one place.</p>
          </div>
        </div>
      </div>

      <div class="row dashboard-cards admin-cards">

        <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.30s">
          <a class="dash-card-link" href="admin-vouchers.php">
            <div class="dash-card">
              <div class="dash-icon"><i class="fa fa-ticket"></i></div>
              <h4>Vouchers</h4>
              <p>
                <?php if ($pending_vouchers > 0): ?>
                  <strong><?= $pending_vouchers ?></strong> pending request<?= $pending_vouchers === 1 ? '' : 's' ?> awaiting approval.
                <?php else: ?>
                  Inbox empty &mdash; no voucher requests pending.
                <?php endif; ?>
              </p>
              <span class="dash-link">Review &rarr;</span>
            </div>
          </a>
        </div>

        <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.40s">
          <a class="dash-card-link" href="admin-stats.php">
            <div class="dash-card">
              <div class="dash-icon"><i class="fa fa-line-chart"></i></div>
              <h4>Statistics</h4>
              <p><strong><?= $total_users ?></strong> student<?= $total_users === 1 ? '' : 's' ?> &middot; <strong><?= $total_enrol ?></strong> active enrolment<?= $total_enrol === 1 ? '' : 's' ?>.</p>
              <span class="dash-link">View report &rarr;</span>
            </div>
          </a>
        </div>

        <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.50s">
          <a class="dash-card-link" href="admin-news.php">
            <div class="dash-card">
              <div class="dash-icon"><i class="fa fa-newspaper-o"></i></div>
              <h4>News</h4>
              <p>Publish announcements that appear on the public homepage and student portal.</p>
              <span class="dash-link">Open editor &rarr;</span>
            </div>
          </a>
        </div>

        <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.60s">
          <a class="dash-card-link" href="admin-messages.php">
            <div class="dash-card">
              <div class="dash-icon"><i class="fa fa-envelope-o"></i></div>
              <h4>Messages</h4>
              <p>Inbound contact-form messages from students, recruiters and partners.</p>
              <span class="dash-link">Open inbox &rarr;</span>
            </div>
          </a>
        </div>

      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
