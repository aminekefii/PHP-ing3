<?php
require_once __DIR__ . '/includes/auth-admin.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid    = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($mid > 0) {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM contact_messages WHERE id = :id')
                ->execute([':id' => $mid]);
            header('Location: admin-messages.php?flash=deleted');
            exit;
        }
        if ($action === 'mark_unread') {
            $pdo->prepare("UPDATE contact_messages SET status='unread' WHERE id = :id")
                ->execute([':id' => $mid]);
            header('Location: admin-messages.php?flash=marked_unread');
            exit;
        }
    }

    header('Location: admin-messages.php');
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$valid  = ['all', 'unread', 'read'];
if (!in_array($filter, $valid, true)) $filter = 'all';

$sql = "SELECT id, name, surname, email, message, status, submitted_at FROM contact_messages";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE status = :st';
    $params[':st'] = $filter;
}
$sql .= ' ORDER BY submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$count_rows = $pdo->query(
    "SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status"
)->fetchAll();
$counts = ['all' => 0, 'unread' => 0, 'read' => 0];
foreach ($count_rows as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $counts['all']       += (int) $r['n'];
}

$flash_action = $_GET['flash'] ?? null;
$flash_text   = [
    'deleted'       => 'Message deleted.',
    'marked_unread' => 'Marked as unread.',
][$flash_action] ?? null;

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

      <?php if ($flash_text): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="fe-flash"><i class="fa fa-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($flash_text) ?></span></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-12">
          <div class="admin-filters">
            <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label): ?>
              <a class="admin-chip<?= $filter === $key ? ' is-active' : '' ?>" href="admin-messages.php?filter=<?= htmlspecialchars($key) ?>">
                <?= htmlspecialchars($label) ?>
                <span class="admin-chip-count"><?= $counts[$key] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if (empty($messages)): ?>
        <div class="row">
          <div class="col-lg-8 offset-lg-2">
            <div class="fe-empty">
              <div class="fe-empty__icon"><i class="fa fa-inbox" aria-hidden="true"></i></div>
              <h3>Inbox empty</h3>
              <p>No messages match this filter. New submissions through the public contact form will appear here.</p>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-12">
            <ul class="fe-list msg-list">
              <?php foreach ($messages as $m):
                $mid       = (int) $m['id'];
                $status    = (string) $m['status'];
                $full_name = trim($m['name'] . ' ' . $m['surname']);
                $preview   = mb_substr($m['message'], 0, 140);
                if (mb_strlen($m['message']) > 140) $preview .= '…';
              ?>
                <li class="fe-row msg-row<?= $status === 'unread' ? ' is-unread' : '' ?>">
                  <div class="fe-row__seal" aria-hidden="true">
                    <i class="fa <?= $status === 'unread' ? 'fa-envelope' : 'fa-envelope-open-o' ?>"></i>
                  </div>
                  <div class="fe-row__meta">
                    <div class="fe-row__provider"><?= htmlspecialchars($m['email']) ?> &middot; <?= htmlspecialchars(date('d M Y, H:i', strtotime($m['submitted_at']))) ?></div>
                    <h4 class="fe-row__title"><?= htmlspecialchars($full_name) ?></h4>
                    <p class="fe-row__desc msg-preview"><?= htmlspecialchars($preview) ?></p>
                    <div class="fe-row__tags">
                      <?php if ($status === 'unread'): ?>
                        <span class="fe-tag fe-tag--pending"><i class="fa fa-circle" aria-hidden="true"></i> Unread</span>
                      <?php else: ?>
                        <span class="fe-tag fe-tag--ready"><i class="fa fa-check" aria-hidden="true"></i> Read</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="fe-row__action admin-actions">
                    <a class="wt-btn wt-btn--primary" href="admin-message.php?id=<?= $mid ?>">
                      <span>View</span>
                      <span class="wt-btn-arrow" aria-hidden="true">→</span>
                    </a>
                    <form method="post" action="admin-messages.php"
                          onsubmit="return confirm('Delete this message from <?= htmlspecialchars($full_name, ENT_QUOTES) ?>? This cannot be undone.');">
                      <input type="hidden" name="message_id" value="<?= $mid ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="wt-btn wt-btn--ghost wt-btn--danger">Delete</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
