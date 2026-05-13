<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$error = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    if ($email_input === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email, password, firstname, lastname FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email_input]);
        $user = $stmt->fetch();

        // Plain-text password check, as agreed for simplicity.
        // For production, store password_hash() values and use password_verify($password, $user['password']).
        if ($user && hash_equals((string) $user['password'], (string) $password)) {
            $_SESSION['user'] = [
                'id'           => (int) $user['id'],
                'email'        => $user['email'],
                'firstname'    => $user['firstname'],
                'lastname'     => $user['lastname'],
                'logged_in_at' => time(),
            ];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

$page_title    = 'Sign In — TEK-UP Certified Students';
$header_extra  = ' background-header';
$public_active = 'login';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-public.php';
?>

  <div class="login-page section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 wow fadeInLeft" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="login-intro">
            <h6>Certified Students Portal</h6>
            <h2>Welcome <em>back</em> to your <span>Certifications</span> dashboard</h2>
            <p>Track your enrolment, schedule exam sessions, and download every certificate earned along your TEK-UP journey &mdash; from Cisco to AWS, Microsoft, OffSec and beyond.</p>
            <ul class="pillars">
              <li>1,400+ certifications issued to TEK-UP students.</li>
              <li>Weekly proctored exam slots across 17+ global vendors.</li>
            </ul>
          </div>
        </div>

        <div class="col-lg-6 wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.2s">
          <form id="login" action="login.php" method="post" class="login-card">
            <div class="login-card-heading">
              <h3>Sign in</h3>
              <p>Use your TEK-UP credentials to continue.</p>
            </div>

            <?php if ($error !== ''): ?>
              <div class="login-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row">
              <div class="col-lg-12">
                <fieldset>
                  <label for="email">Email</label>
                  <input type="email" name="email" id="email" placeholder="you@tek-up.de" value="<?= htmlspecialchars($email_input) ?>" required>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <label for="password">Password</label>
                  <input type="password" name="password" id="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <div class="login-row">
                  <label class="remember"><input type="checkbox" name="remember"> <span>Remember me</span></label>
                  <a href="#" class="forgot">Forgot password?</a>
                </div>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <button type="submit" id="form-submit" class="main-button">Sign In</button>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <p class="signup-line">Not a student yet? <a href="index.php#contact">Talk to the Certifications Office &rarr;</a></p>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
