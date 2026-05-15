<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

$is_logged_in = !empty($_SESSION['user']);

$page_title    = 'Contact — TEK-UP Certified Students';
$active        = 'contact';        // for nav-portal
$public_active = 'contact';        // for nav-public

$sent  = isset($_GET['sent']) && $_GET['sent'] === '1';
$error = '';
$form  = ['name' => '', 'surname' => '', 'email' => '', 'message' => ''];

// Pre-fill identity fields from the session for logged-in users so they
// don't have to retype.
if ($is_logged_in) {
    $form['name']    = (string) ($_SESSION['user']['firstname'] ?? '');
    $form['surname'] = (string) ($_SESSION['user']['lastname']  ?? '');
    $form['email']   = (string) ($_SESSION['user']['email']     ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_logged_in) {
        // Trust the session, not the POST — disabled fields wouldn't submit
        // anyway, but this also blocks any forged form values.
        $form['name']    = trim((string) ($_SESSION['user']['firstname'] ?? ''));
        $form['surname'] = trim((string) ($_SESSION['user']['lastname']  ?? ''));
        $form['email']   = trim((string) ($_SESSION['user']['email']     ?? ''));
    } else {
        $form['name']    = trim($_POST['name']    ?? '');
        $form['surname'] = trim($_POST['surname'] ?? '');
        $form['email']   = trim($_POST['email']   ?? '');
    }
    $form['message'] = trim($_POST['message'] ?? '');

    if ($form['name'] === '' || $form['surname'] === '' || $form['email'] === '' || $form['message'] === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO contact_messages (name, surname, email, message)
             VALUES (:name, :surname, :email, :message)'
        );
        $ins->execute([
            ':name'    => mb_substr($form['name'], 0, 100),
            ':surname' => mb_substr($form['surname'], 0, 100),
            ':email'   => mb_substr($form['email'], 0, 255),
            ':message' => $form['message'],
        ]);

        // PRG so refresh doesn't resubmit.
        header('Location: contact.php?sent=1');
        exit;
    }
}

require_once __DIR__ . '/includes/head.php';
if ($is_logged_in) {
    require_once __DIR__ . '/includes/nav-portal.php';
} else {
    require_once __DIR__ . '/includes/nav-public.php';
}
?>

  <div class="contact-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-6 align-self-center wow fadeInLeft" data-wow-duration="0.5s" data-wow-delay="0.25s">
          <div class="section-heading">
            <h6>Certified Students Portal</h6>
            <h2>Talk to the <em>Certifications</em> <span>Office</span></h2>
            <p>Use the form below to reach the Certifications Office. We answer within one business day.</p>
            <div class="phone-info">
              <h4>Certifications Office: <span><i class="fa fa-phone"></i> <a href="#">+216 70 250 000</a></span></h4>
            </div>
          </div>
        </div>
        <div class="col-lg-6 wow fadeInRight" data-wow-duration="0.5s" data-wow-delay="0.25s">
          <form id="contact" action="contact.php" method="post">

            <?php if ($sent): ?>
              <div class="form-notice is-success">Thanks &mdash; your message has been received. The Certifications Office will reply within one business day.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="form-notice is-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
              <p class="contact-signed-in">
                <i class="fa fa-user-circle" aria-hidden="true"></i>
                Signed in as <strong><?= htmlspecialchars(trim($form['name'] . ' ' . $form['surname'])) ?></strong> — your details are filled in below.
              </p>
            <?php endif; ?>

            <div class="row">
              <div class="col-lg-6">
                <fieldset>
                  <input type="text" name="name" id="name" placeholder="Name" autocomplete="on"
                         value="<?= htmlspecialchars($form['name']) ?>"
                         <?= $is_logged_in ? 'disabled' : 'required' ?>>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <input type="text" name="surname" id="surname" placeholder="Surname" autocomplete="on"
                         value="<?= htmlspecialchars($form['surname']) ?>"
                         <?= $is_logged_in ? 'disabled' : 'required' ?>>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <input type="email" name="email" id="email" placeholder="Your Email"
                         value="<?= htmlspecialchars($form['email']) ?>"
                         <?= $is_logged_in ? 'disabled' : 'required' ?>>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <textarea name="message" id="message" class="form-control" placeholder="Message" required><?= htmlspecialchars($form['message']) ?></textarea>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <button type="submit" id="form-submit" class="main-button">Send Message</button>
                </fieldset>
              </div>
            </div>
            <div class="contact-dec">
              <img src="assets/images/contact-decoration.png" alt="">
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
