<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Messages — Admin';
$active     = 'admin-messages';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>TEK-UP Admin Console</h6>
            <h2>Inbound <em>Messages</em></h2>
            <p>Read and respond to enquiries submitted through the public contact form and the student portal.</p>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 offset-lg-2">
          <div class="fe-empty wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.15s">
            <div class="fe-empty__icon"><i class="fa fa-envelope-o" aria-hidden="true"></i></div>
            <h3>Inbox coming soon</h3>
            <p>The contact form on the public site currently doesn't persist messages. Once a <code>contact_messages</code> table is added, this page will display a threaded inbox with reply, archive and mark-as-read actions.</p>
            <a href="admin-dashboard.php" class="wt-btn wt-btn--ghost">Back to dashboard</a>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
