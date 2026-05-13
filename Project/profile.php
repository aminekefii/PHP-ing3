<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Profile — TEK-UP Certified Students';
$active     = 'profile';
$user_id    = (int) ($_SESSION['user']['id'] ?? 0);

// Always read the user record fresh from the DB.
$stmt = $pdo->prepare('SELECT id, email, firstname, lastname, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    // The session is stale (user deleted, etc.) — force a fresh login.
    session_destroy();
    header('Location: login.php');
    exit;
}

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        $upsert = $pdo->prepare(
            'INSERT INTO profiles (user_id, phone, address)
             VALUES (:user_id, :phone, :address)
             ON DUPLICATE KEY UPDATE phone = VALUES(phone), address = VALUES(address)'
        );
        $upsert->execute([
            ':user_id' => $user_id,
            ':phone'   => $phone !== '' ? $phone : null,
            ':address' => $address !== '' ? $address : null,
        ]);
        $saved = true;
    } catch (PDOException $e) {
        $error = 'Could not save your profile. Please try again.';
    }
}

// Load the (possibly just-updated) profile row.
$pstmt = $pdo->prepare('SELECT phone, address FROM profiles WHERE user_id = :id LIMIT 1');
$pstmt->execute([':id' => $user_id]);
$profile = $pstmt->fetch() ?: ['phone' => '', 'address' => ''];

$full_name  = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?: 'Student';
$student_id = 'TU-' . str_pad((string) $user['id'], 5, '0', STR_PAD_LEFT);
$member_since_year = $user['created_at']
    ? date('Y', strtotime($user['created_at']))
    : date('Y');

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="profile-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="profile-hello">
            <h6>Account Settings</h6>
            <h2>Your <em>profile</em> &amp; <span>credentials</span></h2>
            <p>Your name, email and student ID come from your university record and cannot be edited here. You can update your phone and address.</p>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-4 wow fadeInLeft" data-wow-duration="1s" data-wow-delay="0.3s">
          <aside class="profile-summary">
            <div class="profile-avatar">
              <i class="fa fa-user"></i>
            </div>
            <h3><?= htmlspecialchars($full_name) ?></h3>
            <p class="role">TEK-UP &middot; <?= htmlspecialchars($student_id) ?></p>
            <span class="status-badge">Active student</span>

            <ul class="profile-stats">
              <li>
                <span class="stat-label">Certifications earned</span>
                <span class="stat-value">0</span>
              </li>
              <li>
                <span class="stat-label">Exams scheduled</span>
                <span class="stat-value">0</span>
              </li>
              <li>
                <span class="stat-label">Member since</span>
                <span class="stat-value"><?= htmlspecialchars($member_since_year) ?></span>
              </li>
            </ul>

            <a href="#" class="profile-secondary-link">Change password &rarr;</a>
          </aside>
        </div>

        <div class="col-lg-8 wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.3s">
          <form id="profile" action="profile.php" method="post" class="profile-form">
            <div class="profile-form-heading">
              <h3>Personal information</h3>
              <p>Update the details that appear on your student record.</p>
            </div>

            <?php if ($saved): ?>
              <div class="form-notice is-success">Profile saved.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="form-notice is-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row">
              <div class="col-lg-6">
                <fieldset>
                  <label for="firstname">First name</label>
                  <input type="text" id="firstname" value="<?= htmlspecialchars($user['firstname']) ?>" disabled>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="lastname">Last name</label>
                  <input type="text" id="lastname" value="<?= htmlspecialchars($user['lastname']) ?>" disabled>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="email">Email</label>
                  <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="studentid">Student ID</label>
                  <input type="text" id="studentid" value="<?= htmlspecialchars($student_id) ?>" disabled>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="phone">Phone</label>
                  <input type="tel" name="phone" id="phone" placeholder="+216 70 250 000" autocomplete="tel" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="address">Address</label>
                  <input type="text" name="address" id="address" placeholder="Street, city, country" autocomplete="street-address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                </fieldset>
              </div>

              <div class="col-lg-12 profile-form-actions">
                <button type="submit" class="main-button">Save changes</button>
                <a href="dashboard.php" class="cancel-link">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
