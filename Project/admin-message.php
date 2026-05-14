<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

$mid = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($mid <= 0) {
    header('Location: admin-messages.php');
    exit;
}

// Delete handler (POST) — same page so we can stay on the message context.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare('DELETE FROM contact_messages WHERE id = :id')
        ->execute([':id' => $mid]);
    header('Location: admin-messages.php?flash=deleted');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $mid]);
$msg = $stmt->fetch();

if (!$msg) {
    header('Location: admin-messages.php');
    exit;
}

// Mark as read on view.
if ($msg['status'] === 'unread') {
    $pdo->prepare("UPDATE contact_messages SET status='read' WHERE id = :id")
        ->execute([':id' => $mid]);
    $msg['status'] = 'read';
}

$full_name  = trim($msg['name'] . ' ' . $msg['surname']);
$page_title = 'Message from ' . $full_name . ' — Admin';
$active     = 'admin-messages';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-admin.php';
?>

  <div class="fe-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <a href="admin-messages.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to messages</a>
          <div class="fe-head wow fadeInDown" data-wow-duration="0.6s">
            <h6>TEK-UP Admin Console &middot; Message</h6>
            <h2>From <em><?= htmlspecialchars($full_name) ?></em></h2>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-10 offset-lg-1">
          <div class="msg-detail">
            <div class="msg-detail__head">
              <div class="msg-detail__avatar" aria-hidden="true">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($msg['name'], 0, 1) . mb_substr($msg['surname'], 0, 1))) ?>
              </div>
              <div class="msg-detail__head-meta">
                <div class="msg-detail__name"><?= htmlspecialchars($full_name) ?></div>
                <a class="msg-detail__email" href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>
                <div class="msg-detail__time">
                  <i class="fa fa-clock-o" aria-hidden="true"></i>
                  Received <?= htmlspecialchars(date('d M Y \a\t H:i', strtotime($msg['submitted_at']))) ?>
                </div>
              </div>
              <div class="msg-detail__tag">
                <span class="fe-tag fe-tag--ready"><i class="fa fa-check" aria-hidden="true"></i> Read</span>
              </div>
            </div>

            <div class="msg-detail__body">
              <?= nl2br(htmlspecialchars($msg['message'])) ?>
            </div>

            <div class="msg-detail__actions">
              <a class="wt-btn wt-btn--primary" href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re%3A%20your%20message%20to%20TEK-UP">
                <span>Reply by email</span>
                <span class="wt-btn-arrow" aria-hidden="true">→</span>
              </a>
              <form method="post" action="admin-messages.php">
                <input type="hidden" name="message_id" value="<?= (int) $mid ?>">
                <input type="hidden" name="action" value="mark_unread">
                <button type="submit" class="wt-btn wt-btn--soft">Mark as unread</button>
              </form>
              <form method="post" action="admin-message.php?id=<?= (int) $mid ?>"
                    onsubmit="return confirm('Delete this message from <?= htmlspecialchars($full_name, ENT_QUOTES) ?>? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="wt-btn wt-btn--ghost wt-btn--danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
