<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'News — Admin';
$active     = 'admin-news';

$NEWS_DIR     = __DIR__ . '/assets/images/news';
$NEWS_URL     = 'assets/images/news';
$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$MAX_BYTES = 5 * 1024 * 1024; // 5 MB

$upload_saved = false;
$upload_error = '';
$delete_saved = false;
$delete_error = '';

if (!is_dir($NEWS_DIR)) {
    @mkdir($NEWS_DIR, 0755, true);
}

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $name = basename((string) ($_POST['name'] ?? ''));
    if ($name === '' || strpos($name, '..') !== false) {
        $delete_error = 'Invalid filename.';
    } else {
        $target = $NEWS_DIR . '/' . $name;
        $real   = realpath($target);
        $base   = realpath($NEWS_DIR);
        if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
            $delete_error = 'File not found.';
        } elseif (!@unlink($real)) {
            $delete_error = 'Could not delete the file.';
        } else {
            $delete_saved = true;
        }
    }
}

// ---- Handle upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'The file is too large.'
            : 'Upload failed. Please try again.';
    } elseif ($file['size'] > $MAX_BYTES) {
        $upload_error = 'The file is too large (max 5 MB).';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset($ALLOWED_MIME[$mime])) {
            $upload_error = 'Unsupported format. Use JPG, PNG, WEBP or GIF.';
        } else {
            $ext      = $ALLOWED_MIME[$mime];
            $filename = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = $NEWS_DIR . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $upload_error = 'Could not save the file. Please try again.';
            } else {
                $upload_saved = true;
            }
        }
    }
}

// ---- Load current news images ----
$news_images = [];
if (is_dir($NEWS_DIR)) {
    foreach (glob($NEWS_DIR . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE) as $p) {
        $news_images[] = [
            'name'  => basename($p),
            'size'  => filesize($p) ?: 0,
            'mtime' => filemtime($p) ?: 0,
        ];
    }
    usort($news_images, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="fe-head wow fadeInDown" data-wow-duration="0.8s">
            <h6>TEK-UP Admin Console</h6>
            <h2><em>News</em> &amp; Achievements</h2>
            <p>Upload photos to the homepage carousel. Images appear on the public landing page in upload order — newest first. Supported formats: JPG, PNG, WEBP, GIF (max 5 MB).</p>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-10 offset-lg-1">

          <?php if ($upload_saved): ?>
            <div class="form-notice is-success">Image uploaded — it's now live on the homepage.</div>
          <?php endif; ?>
          <?php if ($upload_error !== ''): ?>
            <div class="form-notice is-error"><?= htmlspecialchars($upload_error) ?></div>
          <?php endif; ?>
          <?php if ($delete_saved): ?>
            <div class="form-notice is-success">Image removed.</div>
          <?php endif; ?>
          <?php if ($delete_error !== ''): ?>
            <div class="form-notice is-error"><?= htmlspecialchars($delete_error) ?></div>
          <?php endif; ?>

          <div class="news-admin-upload wow fadeInUp" data-wow-duration="0.8s" data-wow-delay="0.1s">
            <form action="admin-news.php" method="post" enctype="multipart/form-data" id="news-upload-form">
              <input type="hidden" name="action" value="upload">
              <input type="file" name="image" id="news-image" accept="image/jpeg,image/png,image/webp,image/gif" class="news-admin-upload__input" required>
              <label for="news-image" class="news-admin-upload__dropzone" id="news-dropzone">
                <span class="news-admin-upload__icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>
                <span class="news-admin-upload__title">Click to choose an image</span>
                <span class="news-admin-upload__hint">or drag &amp; drop &middot; JPG / PNG / WEBP / GIF &middot; up to 5 MB</span>
                <span class="news-admin-upload__filename" id="news-filename"></span>
              </label>
              <div class="news-admin-upload__actions">
                <button type="submit" class="wt-btn wt-btn--primary" id="news-upload-submit" disabled>
                  <span>Publish image</span>
                  <span class="wt-btn-arrow">&rarr;</span>
                </button>
              </div>
            </form>
          </div>

          <div class="news-admin-gallery-head">
            <h3>Published images <span><?= count($news_images) ?></span></h3>
            <p>Click <strong>Remove</strong> to delete an image from the public carousel. Deletion is permanent.</p>
          </div>

          <?php if (empty($news_images)): ?>
            <div class="news-admin-empty">
              <i class="fa fa-image" aria-hidden="true"></i>
              <p>No news images yet. Upload the first one above.</p>
            </div>
          <?php else: ?>
            <div class="news-admin-grid">
              <?php foreach ($news_images as $img): ?>
                <article class="news-admin-card wow fadeInUp" data-wow-duration="0.6s">
                  <div class="news-admin-card__thumb">
                    <img src="<?= htmlspecialchars($NEWS_URL . '/' . rawurlencode($img['name'])) ?>" alt="">
                  </div>
                  <div class="news-admin-card__meta">
                    <span class="news-admin-card__date">
                      <i class="fa fa-clock-o" aria-hidden="true"></i>
                      <?= htmlspecialchars(date('d M Y &middot; H:i', $img['mtime'])) ?>
                    </span>
                    <span class="news-admin-card__size">
                      <?= number_format($img['size'] / 1024, 0) ?>&nbsp;KB
                    </span>
                  </div>
                  <form action="admin-news.php" method="post"
                        onsubmit="return confirm('Remove this image from the homepage? This cannot be undone.');"
                        class="news-admin-card__delete">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($img['name']) ?>">
                    <button type="submit" class="wt-btn wt-btn--soft">
                      <i class="fa fa-trash-o" aria-hidden="true"></i>
                      <span>Remove</span>
                    </button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>

  <script>
    (function () {
      var input    = document.getElementById('news-image');
      var label    = document.getElementById('news-filename');
      var submit   = document.getElementById('news-upload-submit');
      var dropzone = document.getElementById('news-dropzone');
      if (!input || !label || !submit || !dropzone) return;

      function setFile(file) {
        if (!file) {
          label.textContent = '';
          submit.disabled = true;
          dropzone.classList.remove('is-ready');
          return;
        }
        var kb = Math.round(file.size / 1024);
        label.textContent = file.name + '  ·  ' + kb.toLocaleString() + ' KB';
        submit.disabled = false;
        dropzone.classList.add('is-ready');
      }

      input.addEventListener('change', function () {
        setFile(input.files && input.files[0]);
      });

      ['dragenter', 'dragover'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
          e.preventDefault();
          dropzone.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
          e.preventDefault();
          dropzone.classList.remove('is-dragover');
        });
      });
      dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
          input.files = e.dataTransfer.files;
          setFile(input.files[0]);
        }
      });
    })();
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
