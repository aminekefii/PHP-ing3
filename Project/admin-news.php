<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'News — Admin';
$active     = 'admin-news';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>TEK-UP Admin Console</h6>
            <h2><em>News</em> &amp; Announcements</h2>
            <p>Publish news posts that surface on the public homepage and the student portal. The editor lives here.</p>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 offset-lg-2">
          <div class="fe-empty wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.15s">
            <div class="fe-empty__icon"><i class="fa fa-newspaper-o" aria-hidden="true"></i></div>
            <h3>Editor coming soon</h3>
            <p>This page will host a rich-text editor for announcements (cohort intakes, exam-session calendars, partner events) plus a feed of published posts with edit and unpublish actions.</p>
            <a href="admin-dashboard.php" class="wt-btn wt-btn--ghost">Back to dashboard</a>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
