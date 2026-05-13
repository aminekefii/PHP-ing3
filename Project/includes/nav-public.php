<?php
$header_extra = $header_extra ?? '';
$public_active = $public_active ?? 'home';
function _active($key, $current) { return $key === $current ? ' class="active"' : ''; }
?>
<!-- ***** Header Area Start ***** -->
<header class="header-area header-sticky<?= $header_extra ?> wow slideInDown" data-wow-duration="0.75s" data-wow-delay="0s">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <nav class="main-nav">
          <a href="index.php" class="logo">
            <img src="assets/images/logo1.png" alt="TEK-UP Certif">
            <h4>TEK-UP<span> Certified Students</span></h4>
          </a>
          <ul class="nav">
            <li class="scroll-to-section"><a href="index.php#top"<?= _active('home', $public_active) ?>>Home</a></li>
            <li class="scroll-to-section"><a href="index.php#about">About</a></li>
            <li class="scroll-to-section"><a href="index.php#services">Certifications</a></li>
            <li class="scroll-to-section"><a href="index.php#portfolio">Categories</a></li>
            <li class="scroll-to-section"><a href="index.php#blog">Endorsements</a></li>
            <li class="scroll-to-section"><a href="index.php#contact">Contact</a></li>
            <li><div class="main-red-button"><a href="login.php">Login</a></div></li>
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
