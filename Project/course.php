<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$cert_id = isset($_GET['cert']) ? (int) $_GET['cert'] : 0;

if ($cert_id <= 0) {
    header('Location: certifications.php');
    exit;
}

// Must be enrolled.
$enroll = $pdo->prepare(
    'SELECT progress FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => $cert_id]);
$enroll_row = $enroll->fetch();

if (!$enroll_row) {
    header('Location: catalog.php');
    exit;
}

// Cert metadata.
$cert_stmt = $pdo->prepare('SELECT id, code, name, provider, media_path FROM certifications WHERE id = :id LIMIT 1');
$cert_stmt->execute([':id' => $cert_id]);
$cert = $cert_stmt->fetch();

if (!$cert) {
    header('Location: certifications.php');
    exit;
}

$has_content = !empty($cert['media_path']);

// Sections with per-section watched counts.
$sections = [];
if ($has_content) {
    $sec_stmt = $pdo->prepare(
        "SELECT cs.id, cs.title, cs.position,
                COUNT(cv.id)                        AS total,
                SUM(vp.video_id IS NOT NULL)        AS watched
         FROM course_sections cs
         LEFT JOIN course_videos   cv ON cv.section_id = cs.id
         LEFT JOIN video_progress  vp ON vp.video_id = cv.id AND vp.user_id = :uid
         WHERE cs.certification_id = :cid
         GROUP BY cs.id
         ORDER BY cs.position"
    );
    $sec_stmt->execute([':uid' => $user_id, ':cid' => $cert_id]);
    $sections = $sec_stmt->fetchAll();
}

// Videos grouped by section.
$videos_by_section = [];
if ($has_content) {
    $vid_stmt = $pdo->prepare(
        "SELECT cv.id, cv.section_id, cv.position, cv.title, cv.duration_seconds,
                vp.video_id IS NOT NULL AS watched
         FROM course_videos cv
         JOIN course_sections cs ON cs.id = cv.section_id
         LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid
         WHERE cs.certification_id = :cid
         ORDER BY cs.position, cv.position"
    );
    $vid_stmt->execute([':uid' => $user_id, ':cid' => $cert_id]);
    foreach ($vid_stmt->fetchAll() as $v) {
        $videos_by_section[(int) $v['section_id']][] = $v;
    }
}

// Decide which video to load initially: first unwatched, falling back to the last video.
$initial_video = null;
foreach ($videos_by_section as $vs) {
    foreach ($vs as $v) {
        if (!$v['watched']) { $initial_video = $v; break 2; }
    }
}
if ($initial_video === null) {
    foreach ($videos_by_section as $vs) {
        foreach ($vs as $v) { $initial_video = $v; }
    }
}

$page_title = htmlspecialchars($cert['name']) . ' — TEK-UP Certified Students';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <a href="certifications.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to my certifications</a>
          <div class="course-head">
            <h6><?= htmlspecialchars($cert['provider']) ?> &middot; <?= htmlspecialchars($cert['code']) ?></h6>
            <h2><?= htmlspecialchars($cert['name']) ?></h2>
            <div class="course-progress">
              <div class="bar"><span id="courseBar" style="width: <?= (int) $enroll_row['progress'] ?>%;"></span></div>
              <span class="progress-label"><span id="courseProgressLabel"><?= (int) $enroll_row['progress'] ?></span>% complete</span>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$has_content): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="course-empty">
              <h3>Content not yet uploaded</h3>
              <p>The course material for this certification will be available soon. Check back later.</p>
              <a href="certifications.php" class="main-button">Back</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="row course-layout">
          <div class="col-lg-4 course-rail">
            <?php foreach ($sections as $sec):
                $sec_id      = (int) $sec['id'];
                $sec_watched = (int) $sec['watched'];
                $sec_total   = (int) $sec['total'];
            ?>
              <details class="course-section" <?= ($initial_video && (int) $initial_video['section_id'] === $sec_id) ? 'open' : '' ?>>
                <summary>
                  <span class="sec-title"><?= htmlspecialchars($sec['position'] . '. ' . $sec['title']) ?></span>
                  <span class="sec-count" data-section-id="<?= $sec_id ?>"><?= $sec_watched ?>/<?= $sec_total ?></span>
                </summary>
                <ul class="course-video-list">
                  <?php foreach ($videos_by_section[$sec_id] ?? [] as $v):
                      $vid     = (int) $v['id'];
                      $watched = (int) $v['watched'] === 1;
                      $is_init = $initial_video && (int) $initial_video['id'] === $vid;
                  ?>
                    <li class="course-video-row<?= $is_init ? ' is-playing' : '' ?>"
                        data-video-id="<?= $vid ?>"
                        data-video-title="<?= htmlspecialchars($v['title']) ?>"
                        data-section-id="<?= $sec_id ?>">
                      <span class="status-mark"><?= $watched ? '✓' : ($is_init ? '▶' : '') ?></span>
                      <span class="video-title"><?= htmlspecialchars($v['title']) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endforeach; ?>
          </div>

          <div class="col-lg-8 course-stage">
            <?php if ($initial_video): ?>
              <video id="coursePlayer"
                     controls
                     preload="metadata"
                     data-video-id="<?= (int) $initial_video['id'] ?>">
                <source src="stream.php?video_id=<?= (int) $initial_video['id'] ?>" type="video/mp4">
                <track id="courseSubtitle"
                       kind="subtitles"
                       src="subtitle.php?video_id=<?= (int) $initial_video['id'] ?>"
                       srclang="en"
                       label="English"
                       default>
                Your browser does not support HTML5 video.
              </video>
              <div class="stage-meta">
                <span class="now-playing-label">Now playing</span>
                <h4 id="nowPlayingTitle"><?= htmlspecialchars($initial_video['title']) ?></h4>
              </div>
            <?php else: ?>
              <p>No videos in this course yet.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

<?php if ($has_content && $initial_video): ?>
<script>
(function () {
    const video       = document.getElementById('coursePlayer');
    const titleEl     = document.getElementById('nowPlayingTitle');
    const barEl       = document.getElementById('courseBar');
    const labelEl     = document.getElementById('courseProgressLabel');
    const subtitleTr  = document.getElementById('courseSubtitle');

    if (!video) return;

    // Accordion: only one section open at a time.
    const sectionDetails = document.querySelectorAll('details.course-section');
    sectionDetails.forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            sectionDetails.forEach(function (o) {
                if (o !== d) o.open = false;
            });
        });
    });

    // Per-load flag so we only POST progress.php once per video, even if the user
    // re-seeks past the 90% mark.
    let markedForId = null;

    function markWatched() {
        const id = video.dataset.videoId;
        if (markedForId === id) return;
        markedForId = id;

        // Optimistic UI: tick the row, recompute the cert bar and the section
        // count locally so the user sees feedback even if the server response
        // is slow or unparseable.
        const row = document.querySelector('.course-video-row[data-video-id="' + id + '"]');
        if (row) {
            row.querySelector('.status-mark').textContent = '✓';

            const allRows = document.querySelectorAll('.course-video-row');
            let watched = 0;
            allRows.forEach(function (r) {
                if (r.querySelector('.status-mark').textContent === '✓') watched++;
            });
            const pct = allRows.length ? Math.round(100 * watched / allRows.length) : 0;
            barEl.style.width = pct + '%';
            labelEl.textContent = pct;

            const sectionId = row.dataset.sectionId;
            const secRows = document.querySelectorAll('.course-video-row[data-section-id="' + sectionId + '"]');
            let secWatched = 0;
            secRows.forEach(function (r) {
                if (r.querySelector('.status-mark').textContent === '✓') secWatched++;
            });
            const secCount = document.querySelector('.sec-count[data-section-id="' + sectionId + '"]');
            if (secCount) secCount.textContent = secWatched + '/' + secRows.length;
        }

        const fd = new FormData();
        fd.append('video_id', id);

        // Still POST so the server records the watched event and the
        // certifications page reflects it on next visit.
        fetch('progress.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .catch(() => { markedForId = null; });
    }

    video.addEventListener('timeupdate', function () {
        if (!video.duration || isNaN(video.duration)) return;
        if (video.currentTime / video.duration >= 0.9) {
            markWatched();
        }
    });

    // Click-to-load: change the player source without a full reload.
    function loadVideo(row) {
        const id    = row.dataset.videoId;
        const title = row.dataset.videoTitle;
        const src   = 'stream.php?video_id=' + encodeURIComponent(id);
        const sub   = 'subtitle.php?video_id=' + encodeURIComponent(id);

        // Reset state.
        markedForId = null;

        // Swap sources.
        video.pause();
        const srcEl = video.querySelector('source');
        if (srcEl) srcEl.src = src;
        if (subtitleTr) subtitleTr.src = sub;
        video.dataset.videoId = id;
        video.load();

        // Update right-pane title.
        if (titleEl) titleEl.textContent = title;

        // Update left-rail playing indicator.
        document.querySelectorAll('.course-video-row.is-playing').forEach(function (el) {
            el.classList.remove('is-playing');
            const mark = el.querySelector('.status-mark');
            if (mark && mark.textContent === '▶') mark.textContent = '';
        });
        row.classList.add('is-playing');
        const mark = row.querySelector('.status-mark');
        if (mark && mark.textContent === '') mark.textContent = '▶';

        // Auto-open the parent <details> if it's collapsed.
        const details = row.closest('details');
        if (details && !details.open) details.open = true;

        video.play().catch(function () { /* user gesture not granted; that's fine */ });
    }

    document.querySelectorAll('.course-video-row').forEach(function (row) {
        row.addEventListener('click', function () { loadVideo(row); });
    });

    // Auto-advance: when one video ends, jump to the next one in the rail.
    video.addEventListener('ended', function () {
        const rows = Array.from(document.querySelectorAll('.course-video-row'));
        const currentId = video.dataset.videoId;
        const idx = rows.findIndex(function (r) { return r.dataset.videoId === currentId; });
        if (idx >= 0 && idx + 1 < rows.length) {
            loadVideo(rows[idx + 1]);
        }
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
