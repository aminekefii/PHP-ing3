<?php
require_once __DIR__ . '/includes/auth.php';

$page_title = 'My Certifications — TEK-UP Certified Students';
$active     = 'certifications';

// Placeholder certifications dataset — replace with a DB query when ready.
$certifications = [
    [
        'provider'    => 'Cisco',
        'name'        => 'CCNA &mdash; Networking Associate',
        'description' => 'Routing, switching, network fundamentals and security basics.',
        'status'      => 'earned',
        'meta_icon'   => 'fa-calendar',
        'meta_text'   => '12 Feb 2026',
        'action'      => 'View certificate',
    ],
    [
        'provider'    => 'Python Institute',
        'name'        => 'PCEP &mdash; Entry Level Python',
        'description' => 'Fundamentals of programming in Python 3, ready for PCAP next.',
        'status'      => 'earned',
        'meta_icon'   => 'fa-calendar',
        'meta_text'   => '14 Jan 2026',
        'action'      => 'View certificate',
    ],
    [
        'provider'    => 'Cisco',
        'name'        => 'CCNP &mdash; Professional',
        'description' => 'Enterprise networking, advanced routing and infrastructure.',
        'status'      => 'progress',
        'progress'    => 65,
        'meta_icon'   => 'fa-clock-o',
        'meta_text'   => 'Exam: 18 Jun 2026',
        'action'      => 'Continue',
    ],
    [
        'provider'    => 'Microsoft',
        'name'        => 'ITS &mdash; IT Specialist',
        'description' => 'Foundational Microsoft track covering networking, security and devices.',
        'status'      => 'progress',
        'progress'    => 40,
        'meta_icon'   => 'fa-clock-o',
        'meta_text'   => 'Exam: 22 Jul 2026',
        'action'      => 'Continue',
    ],
    [
        'provider'    => 'Amazon AWS',
        'name'        => 'AWS Solutions Architect &mdash; Professional',
        'description' => 'Design and deploy large-scale, fault-tolerant systems on AWS.',
        'status'      => 'available',
        'meta_icon'   => 'fa-tag',
        'meta_text'   => 'Included in tuition',
        'action'      => 'Enroll',
    ],
    [
        'provider'    => 'Offensive Security',
        'name'        => 'OSCP &mdash; Penetration Tester',
        'description' => 'Hands-on offensive security: exploitation, privilege escalation, reporting.',
        'status'      => 'available',
        'meta_icon'   => 'fa-tag',
        'meta_text'   => 'Included in tuition',
        'action'      => 'Enroll',
    ],
];

$counts = ['earned' => 0, 'progress' => 0, 'available' => 0];
foreach ($certifications as $c) {
    $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
}

$active_filter = $_GET['filter'] ?? 'all';
if (!in_array($active_filter, ['all', 'earned', 'progress', 'available'], true)) {
    $active_filter = 'all';
}

$status_labels = [
    'earned'    => 'Earned',
    'progress'  => 'In progress',
    'available' => 'Available',
];

$filter_chips = [
    'all'       => 'All',
    'earned'    => 'Earned',
    'progress'  => 'In progress',
    'available' => 'Available',
];

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
            <span class="stat-number"><?= str_pad((string) $counts['progress'], 2, '0', STR_PAD_LEFT) ?></span>
            <span class="stat-caption">In progress</span>
          </div>
        </div>
        <div class="col-md-4 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.45s">
          <div class="stat-card">
            <span class="stat-number">17+</span>
            <span class="stat-caption">Available tracks</span>
          </div>
        </div>
      </div>

      <div class="certs-filters wow fadeIn" data-wow-duration="1s" data-wow-delay="0.4s">
        <?php foreach ($filter_chips as $key => $label): ?>
          <a class="filter-chip<?= $key === $active_filter ? ' is-active' : '' ?>" href="certifications.php?filter=<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="row certs-grid">

        <?php $delay = 0.2; foreach ($certifications as $c):
            if ($active_filter !== 'all' && $c['status'] !== $active_filter) continue;
        ?>
        <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="<?= number_format($delay, 2) ?>s">
          <article class="cert-card">
            <div class="cert-top">
              <span class="cert-provider"><?= htmlspecialchars($c['provider']) ?></span>
              <span class="cert-status is-<?= $c['status'] ?>"><?= $status_labels[$c['status']] ?></span>
            </div>
            <h4><?= $c['name'] ?></h4>
            <p><?= $c['description'] ?></p>
            <?php if ($c['status'] === 'progress' && isset($c['progress'])): ?>
              <div class="cert-progress">
                <div class="bar"><span style="width: <?= (int) $c['progress'] ?>%;"></span></div>
                <span class="progress-label"><?= (int) $c['progress'] ?>%</span>
              </div>
            <?php endif; ?>
            <div class="cert-meta">
              <span><i class="fa <?= htmlspecialchars($c['meta_icon']) ?>"></i> <?= htmlspecialchars($c['meta_text']) ?></span>
              <a href="#" class="cert-action"><?= htmlspecialchars($c['action']) ?> &rarr;</a>
            </div>
          </article>
        </div>
        <?php $delay += 0.1; endforeach; ?>

      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
