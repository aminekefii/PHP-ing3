<?php
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Profile — TEK-UP Certified Students';
$active     = 'profile';
$user_email = $_SESSION['user']['email'] ?? '';

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Placeholder: would normally persist to a database.
    $_SESSION['profile'] = [
        'firstname' => trim($_POST['firstname'] ?? ''),
        'lastname'  => trim($_POST['lastname'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'studentid' => trim($_POST['studentid'] ?? ''),
        'address'   => trim($_POST['address'] ?? ''),
        'bio'       => trim($_POST['bio'] ?? ''),
    ];
    $saved = true;
}

$profile = $_SESSION['profile'] ?? [
    'firstname' => '',
    'lastname'  => '',
    'email'     => $user_email,
    'phone'     => '',
    'studentid' => '',
    'address'   => '',
    'bio'       => '',
];

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
            <p>Manage the information attached to your TEK-UP Certified Students account. Changes are saved against your student ID.</p>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-4 wow fadeInLeft" data-wow-duration="1s" data-wow-delay="0.3s">
          <aside class="profile-summary">
            <div class="profile-avatar">
              <i class="fa fa-user"></i>
            </div>
            <h3><?= htmlspecialchars(trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? '')) ?: 'Student Name') ?></h3>
            <p class="role">TEK-UP &middot; Certifications Track</p>
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
                <span class="stat-value"><?= date('Y') ?></span>
              </li>
            </ul>

            <a href="#" class="profile-secondary-link">Change password &rarr;</a>
          </aside>
        </div>

        <div class="col-lg-8 wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.3s">
          <form id="profile" action="profile.php" method="post" class="profile-form">
            <div class="profile-form-heading">
              <h3>Personal information</h3>
              <p>Update the details that appear on your certificates and student record.</p>
            </div>

            <?php if ($saved): ?>
              <div class="form-notice is-success">Profile saved.</div>
            <?php endif; ?>

            <div class="row">
              <div class="col-lg-6">
                <fieldset>
                  <label for="firstname">First name</label>
                  <input type="text" name="firstname" id="firstname" placeholder="First name" autocomplete="given-name" value="<?= htmlspecialchars($profile['firstname']) ?>" required>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="lastname">Last name</label>
                  <input type="text" name="lastname" id="lastname" placeholder="Last name" autocomplete="family-name" value="<?= htmlspecialchars($profile['lastname']) ?>" required>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="email">Email</label>
                  <input type="email" name="email" id="email" placeholder="you@tek-up.de" autocomplete="email" value="<?= htmlspecialchars($profile['email']) ?>" required>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="phone">Phone</label>
                  <input type="tel" name="phone" id="phone" placeholder="+216 70 250 000" autocomplete="tel" value="<?= htmlspecialchars($profile['phone']) ?>">
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <label for="studentid">Student ID</label>
                  <input type="text" name="studentid" id="studentid" placeholder="TU-00000" autocomplete="off" value="<?= htmlspecialchars($profile['studentid']) ?>">
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="address">Address</label>
                  <input type="text" name="address" id="address" placeholder="Street, city, country" autocomplete="street-address" value="<?= htmlspecialchars($profile['address']) ?>">
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="bio">About you</label>
                  <textarea name="bio" id="bio" placeholder="A few lines about yourself, the track you are following, and your goals."><?= htmlspecialchars($profile['bio']) ?></textarea>
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
