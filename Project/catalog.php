<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Catalog — TEK-UP Certified Students';
$active     = 'certifications';
$user_id    = (int) ($_SESSION['user']['id'] ?? 0);

$flash = '';
$flash_type = '';

// Enroll the user in a certification (POST cert_id).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cert_id'])) {
    $cert_id = (int) $_POST['cert_id'];

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

// Get IDs the user is already enrolled in so we disable those rows.
$mine = $pdo->prepare('SELECT certification_id FROM user_certifications WHERE user_id = :uid');
$mine->execute([':uid' => $user_id]);
$enrolled_ids = array_map('intval', array_column($mine->fetchAll(), 'certification_id'));

// Read full catalog and group by category.
$all = $pdo->query('SELECT id, code, name, provider, category, description FROM certifications ORDER BY category, provider, code')->fetchAll();

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
                  <?php if ($is_enrolled): ?>
                    <span class="enrolled-pill"><i class="fa fa-check"></i> Enrolled</span>
                  <?php else: ?>
                    <form action="catalog.php" method="post">
                      <input type="hidden" name="cert_id" value="<?= (int) $c['id'] ?>">
                      <button type="submit" class="enroll-btn">Enroll &rarr;</button>
                    </form>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
