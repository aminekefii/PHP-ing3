<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'My Certifications — TEK-UP Certified Students';
$active     = 'certifications';
$user_id    = (int) ($_SESSION['user']['id'] ?? 0);

// Pull this user's certifications joined with the catalog.
$stmt = $pdo->prepare(
    "SELECT
        c.id, c.code, c.name, c.provider, c.description,
        uc.status, uc.progress, uc.enrolled_at, uc.earned_at
     FROM user_certifications uc
     INNER JOIN certifications c ON c.id = uc.certification_id
     WHERE uc.user_id = :uid
     ORDER BY uc.status DESC, uc.enrolled_at DESC"
);
$stmt->execute([':uid' => $user_id]);
$user_certs = $stmt->fetchAll();

// Count totals for the stat strip.
$counts = ['earned' => 0, 'in_progress' => 0];
foreach ($user_certs as $c) {
    $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
}

$catalog_size = (int) $pdo->query('SELECT COUNT(*) FROM certifications')->fetchColumn();
$available_count = max(0, $catalog_size - count($user_certs));

$active_filter = $_GET['filter'] ?? 'all';
$valid_filters = ['all', 'earned', 'in_progress'];
if (!in_array($active_filter, $valid_filters, true)) {
    $active_filter = 'all';
}

$status_labels = [
    'earned'      => 'Earned',
    'in_progress' => 'In progress',
];

$filter_chips = [
    'all'         => 'All',
    'earned'      => 'Earned',
    'in_progress' => 'In progress',
];

$is_empty = count($user_certs) === 0;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="certs-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="certs-hello">
            <h6>Certified Students Portal</h6>
            <h2>My <em>Certifications</em> &amp; <span>Tracks</span></h2>
            <p>Every certificate you have earned, every exam in progress, and the tracks still open to you at TEK-UP.</p>
          </div>
        </div>
      </div>

      <div class="row certs-stats">
        <div class="col-md-4 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.25s">
          <div class="stat-card">
            <span class="stat-number"><?= str_pad((string) $counts['earned'], 2, '0', STR_PAD_LEFT) ?></span>
            <span class="stat-caption">Earned</span>
          </div>
        </div>
        <div class="col-md-4 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.35s">
          <div class="stat-card">
            <span class="stat-number"><?= str_pad((string) $counts['in_progress'], 2, '0', STR_PAD_LEFT) ?></span>
            <span class="stat-caption">In progress</span>
          </div>
        </div>
        <div class="col-md-4 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.45s">
          <div class="stat-card">
            <span class="stat-number"><?= $available_count ?>+</span>
            <span class="stat-caption">Available tracks</span>
          </div>
        </div>
      </div>

      <?php if (!$is_empty): ?>
      <div class="certs-filters wow fadeIn" data-wow-duration="1s" data-wow-delay="0.4s">
        <?php foreach ($filter_chips as $key => $label): ?>
          <a class="filter-chip<?= $key === $active_filter ? ' is-active' : '' ?>" href="certifications.php?filter=<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <a class="filter-chip catalog-chip" href="catalog.php"><i class="fa fa-plus"></i> Enroll in more</a>
      </div>

      <div class="row certs-grid">
        <?php $delay = 0.2; foreach ($user_certs as $c):
            if ($active_filter !== 'all' && $c['status'] !== $active_filter) continue;
        ?>
        <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="<?= number_format($delay, 2) ?>s">
          <article class="cert-card">
            <div class="cert-top">
              <span class="cert-provider"><?= htmlspecialchars($c['provider']) ?></span>
              <span class="cert-status is-<?= $c['status'] === 'earned' ? 'earned' : 'progress' ?>"><?= $status_labels[$c['status']] ?></span>
            </div>
            <h4><?= htmlspecialchars($c['name']) ?></h4>
            <p><?= htmlspecialchars($c['description']) ?></p>
            <?php if ($c['status'] === 'in_progress'): ?>
              <div class="cert-progress">
                <div class="bar"><span style="width: <?= (int) $c['progress'] ?>%;"></span></div>
                <span class="progress-label"><?= (int) $c['progress'] ?>%</span>
              </div>
            <?php endif; ?>
            <div class="cert-meta">
              <?php if ($c['status'] === 'earned'): ?>
                <span><i class="fa fa-calendar"></i> Earned <?= htmlspecialchars(date('d M Y', strtotime($c['earned_at']))) ?></span>
                <a href="#" class="cert-action">View certificate &rarr;</a>
              <?php else: ?>
                <span><i class="fa fa-clock-o"></i> Enrolled <?= htmlspecialchars(date('d M Y', strtotime($c['enrolled_at']))) ?></span>
                <a href="#" class="cert-action">Continue &rarr;</a>
              <?php endif; ?>
            </div>
          </article>
        </div>
        <?php $delay += 0.1; endforeach; ?>
      </div>
      <?php else: ?>
      <div class="certs-empty wow fadeIn" data-wow-duration="1s" data-wow-delay="0.3s">
        <div class="empty-icon"><i class="fa fa-certificate"></i></div>
        <h3>No certifications yet</h3>
        <p>You have not enrolled in any certification. Browse the TEK-UP catalog to get started.</p>
        <a href="catalog.php" class="main-button">Start Enrolling</a>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <?php if ($is_empty): ?>
  <!-- Onboarding modal: shown automatically the first time the user lands here -->
  <div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content enroll-modal">
        <div class="modal-body">
          <div class="enroll-icon"><i class="fa fa-graduation-cap"></i></div>
          <h4 id="enrollModalLabel">Get started with your first certification</h4>
          <p>You don't have any certifications yet. Pick one from the TEK-UP catalog — <?= $catalog_size ?> tracks across Cisco, AWS, Red Hat, Microsoft, Python, IELTS and more.</p>
          <div class="enroll-actions">
            <a href="catalog.php" class="main-button">Start Enrolling</a>
            <button type="button" class="cancel-link" data-bs-dismiss="modal">Maybe later</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('enrollModal');
      if (el && window.bootstrap) {
        new bootstrap.Modal(el).show();
      }
    });
  </script>
  <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
