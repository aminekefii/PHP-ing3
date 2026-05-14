<?php
$active = $active ?? '';
function _admin_active($key, $current) { return $key === $current ? ' class="active"' : ''; }
?>
<header class="header-area header-sticky background-header wow slideInDown" data-wow-duration="0.75s" data-wow-delay="0s">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <nav class="main-nav">
          <a href="admin-dashboard.php" class="logo">
            <img src="assets/images/logo1.png" alt="TEK-UP Admin">
            <h4>TEK-UP<span> Admin Console</span></h4>
          </a>
          <ul class="nav">
            <li><a href="admin-dashboard.php"<?= _admin_active('admin-dashboard', $active) ?>>Dashboard</a></li>
            <li><a href="admin-vouchers.php"<?= _admin_active('admin-vouchers', $active) ?>>Vouchers</a></li>
            <li><a href="admin-stats.php"<?= _admin_active('admin-stats', $active) ?>>Statistics</a></li>
            <li><a href="admin-news.php"<?= _admin_active('admin-news', $active) ?>>News</a></li>
            <li><a href="admin-messages.php"<?= _admin_active('admin-messages', $active) ?>>Messages</a></li>
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
