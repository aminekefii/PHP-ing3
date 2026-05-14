<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (($_SESSION['user']['role'] ?? '') === 'admin') {
    header('Location: admin-dashboard.php');
    exit;
}

$page_title = 'Dashboard — TEK-UP Certified Students';
$active     = 'dashboard';
$user_id    = (int) ($_SESSION['user']['id'] ?? 0);
$user_email = $_SESSION['user']['email'] ?? 'Student';
$display_name = $_SESSION['user']['firstname']
    ?? (strstr($user_email, '@', true) ?: $user_email);

// Live counts from the user_certifications table.
$count_stmt = $pdo->prepare(
    "SELECT
        SUM(status = 'earned')      AS earned,
        SUM(status = 'in_progress') AS in_progress
     FROM user_certifications
     WHERE user_id = :uid"
);
$count_stmt->execute([':uid' => $user_id]);
$row = $count_stmt->fetch();
$cert_earned   = (int) ($row['earned'] ?? 0);
$cert_progress = (int) ($row['in_progress'] ?? 0);

// Courses ready for the real exam (videos complete).
$ready_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM user_certifications
     WHERE user_id = :uid AND progress >= 100"
);
$ready_stmt->execute([':uid' => $user_id]);
$exam_ready = (int) $ready_stmt->fetchColumn();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="dashboard-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="dashboard-hello">
            <h6>Certified Students Portal</h6>
            <h2>Welcome <em>back</em>, <span><?= htmlspecialchars($display_name) ?></span></h2>
            <p>You are signed in as <?= htmlspecialchars($user_email) ?>. Track your certifications, exam schedule and profile from here.</p>
          </div>
        </div>
      </div>

      <div class="row dashboard-cards">
        <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.3s">
          <div class="dash-card">
            <div class="dash-icon"><i class="fa fa-certificate"></i></div>
            <h4>My Certifications</h4>
            <p><?= $cert_earned ?> earned &middot; <?= $cert_progress ?> in progress</p>
            <a href="certifications.php" class="dash-link">View all &rarr;</a>
          </div>
        </div>
        <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.45s">
          <div class="dash-card">
            <div class="dash-icon"><i class="fa fa-calendar"></i></div>
            <h4>Final Exams</h4>
            <p>
              <?php if ($exam_ready > 0): ?>
                <?= $exam_ready ?> course<?= $exam_ready === 1 ? '' : 's' ?> ready for the official exam.
              <?php else: ?>
                Finish a course to unlock voucher requests.
              <?php endif; ?>
            </p>
            <a href="final-exams.php" class="dash-link">Request a voucher &rarr;</a>
          </div>
        </div>
        <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.6s">
          <div class="dash-card">
            <div class="dash-icon"><i class="fa fa-user"></i></div>
            <h4>Profile</h4>
            <p>Manage your TEK-UP credentials.</p>
            <a href="profile.php" class="dash-link">Edit profile &rarr;</a>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
