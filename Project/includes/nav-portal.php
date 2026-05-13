<?php
$active = $active ?? '';
function _portal_active($key, $current) { return $key === $current ? ' class="active"' : ''; }
?>
<!-- ***** Header Area Start ***** -->
<header class="header-area header-sticky background-header wow slideInDown" data-wow-duration="0.75s" data-wow-delay="0s">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <nav class="main-nav">
          <a href="index.php" class="logo">
            <img src="assets/images/logo1.png" alt="TEK-UP Certif">
            <h4>TEK-UP<span> Certified Students</span></h4>
          </a>
          <ul class="nav">
            <li><a href="dashboard.php"<?= _portal_active('dashboard', $active) ?>>Dashboard</a></li>
            <li><a href="certifications.php"<?= _portal_active('certifications', $active) ?>>My Certifications</a></li>
            <li><a href="profile.php"<?= _portal_active('profile', $active) ?>>Profile</a></li>
            <li><a href="contact.php"<?= _portal_active('contact', $active) ?>>Contact</a></li>
            <li><div class="main-red-button"><a href="logout.php">Sign Out</a></div></li>
          </ul>
          <a class='menu-trigger'>
              <span>Menu</span>
          </a>
        </nav>
      </div>
    </div>
  </div>
</header>
<!-- ***** Header Area End ***** -->
