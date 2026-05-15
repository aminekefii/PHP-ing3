<?php
$page_title       = 'TEK-UP Certified Students';
$page_description = 'TEK-UP University Certified Students &mdash; Excellence & Savoir-Faire.';
$public_active    = 'home';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-public.php';
?>

  <div class="main-banner wow fadeIn" id="top" data-wow-duration="1s" data-wow-delay="0.5s">
    <div class="container">
      <div class="row">
        <div class="col-lg-12">
          <div class="row">
            <div class="col-lg-6 align-self-center">
              <div class="left-content header-text wow fadeInLeft" data-wow-duration="1s" data-wow-delay="1s">
                <h6>Welcome to TEK-UP University</h6>
                <h2>We place <em>great emphasis</em> on <span>knowledge</span> &amp; <span>expertise</span></h2>
                <ul class="pillars">
                  <li>Knowledge through academic courses aligned with Industry 4.0.</li>
                  <li>Expertise through professional training by GAFAM, Cisco, Oracle and other global tech leaders.</li>
                </ul>

                <div class="hero-social">
                  <span class="hero-social-label">Check us out</span>
                  <a href="https://www.facebook.com/" target="_blank" rel="noopener" class="hero-social-link" aria-label="Facebook">
                    <i class="fa fa-facebook" aria-hidden="true"></i>
                  </a>
                  <a href="https://www.linkedin.com/" target="_blank" rel="noopener" class="hero-social-link" aria-label="LinkedIn">
                    <i class="fa fa-linkedin" aria-hidden="true"></i>
                  </a>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="right-image wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.5s">
                <img src="assets/images/banner-right-image.png" alt="team meeting">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="about" class="about-us section">
    <div class="container">
      <div class="row">
        <div class="col-lg-4">
          <div class="left-image wow fadeIn" data-wow-duration="1s" data-wow-delay="0.2s">
            <img src="assets/images/about-left-image.png" alt="person graphic">
          </div>
        </div>
        <div class="col-lg-8 align-self-center">
          <div class="services">
            <div class="row">
              <div class="col-lg-6">
                <div class="item wow fadeIn" data-wow-duration="1s" data-wow-delay="0.5s">
                  <div class="icon">
                    <img src="assets/images/service-icon-01.png" alt="academic knowledge">
                  </div>
                  <div class="right-text">
                    <h4>Savoir</h4>
                    <p>Academic courses aligned with Industry 4.0, the driving force behind today's economies.</p>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="item wow fadeIn" data-wow-duration="1s" data-wow-delay="0.7s">
                  <div class="icon">
                    <img src="assets/images/service-icon-02.png" alt="professional certifications">
                  </div>
                  <div class="right-text">
                    <h4>Savoir-Faire</h4>
                    <p>Professional training from the world's top tech leaders: GAFAM, Cisco, Oracle, Red Hat, OffSec.</p>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="item wow fadeIn" data-wow-duration="1s" data-wow-delay="0.9s">
                  <div class="icon">
                    <img src="assets/images/service-icon-03.png" alt="certifications issued">
                  </div>
                  <div class="right-text">
                    <h4>1,400+ Certifications</h4>
                    <p>Issued to our students &mdash; weekly exam sessions, fully included in the tuition.</p>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="item wow fadeIn" data-wow-duration="1s" data-wow-delay="1.1s">
                  <div class="icon">
                    <img src="assets/images/service-icon-04.png" alt="international placements">
                  </div>
                  <div class="right-text">
                    <h4>Global Placements</h4>
                    <p>Alumni at Cisco Paris, Amazon France, plus Germany, Japan, Austria and Andorra.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php
  // Auto-discover every image in assets/images/news/.
  // Newest files first (mtime), so freshly-uploaded photos open the carousel.
  $news_dir = __DIR__ . '/assets/images/news';
  $news_images = [];
  if (is_dir($news_dir)) {
      foreach (glob($news_dir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE) as $path) {
          $news_images[] = [
              'name'  => basename($path),
              'mtime' => filemtime($path) ?: 0,
          ];
      }
      usort($news_images, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
  }
  ?>
  <?php if (!empty($news_images)): ?>
  <div id="news" class="our-news section">
    <div class="container">
      <div class="row">
        <div class="col-lg-8 offset-lg-2">
          <div class="section-heading wow bounceIn" data-wow-duration="1s" data-wow-delay="0.2s">
            <h2><em>News</em> &amp; <span>Achievements</span></h2>
            <p>Our latest students achievements</p>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-10 offset-lg-1">
          <div id="newsCarousel" class="carousel slide news-carousel" data-bs-ride="carousel" data-bs-interval="3500" data-bs-pause="false" data-bs-wrap="true">
            <?php if (count($news_images) > 1): ?>
            <div class="carousel-indicators">
              <?php foreach ($news_images as $i => $img): ?>
                <button type="button" data-bs-target="#newsCarousel" data-bs-slide-to="<?= $i ?>"
                        <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                        aria-label="Slide <?= $i + 1 ?>"></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="carousel-inner">
              <?php foreach ($news_images as $i => $img): ?>
                <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                  <img src="assets/images/news/<?= htmlspecialchars(rawurlencode($img['name'])) ?>"
                       class="d-block w-100" alt="TEK-UP student achievement">
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (count($news_images) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#newsCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#newsCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($news_images) && count($news_images) > 1): ?>
  <script>
    (function () {
      function startNewsCarousel() {
        var el = document.getElementById('newsCarousel');
        if (!el || !window.bootstrap || !window.bootstrap.Carousel) return;
        var c = bootstrap.Carousel.getOrCreateInstance(el, {
          interval: 3500,
          ride: 'carousel',
          pause: false,
          wrap: true,
          touch: true
        });
        c.cycle();
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startNewsCarousel);
      } else {
        // Bootstrap JS is loaded at the end of the page, so wait one tick.
        setTimeout(startNewsCarousel, 0);
      }
      window.addEventListener('load', startNewsCarousel);
    })();
  </script>
  <?php endif; ?>

  <div id="services" class="our-services section">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 align-self-center  wow fadeInLeft" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="left-image">
            <img src="assets/images/services-left-image.png" alt="">
          </div>
        </div>
        <div class="col-lg-6 wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.2s">
          <div class="section-heading">
            <h2>Most Earned <em>Certifications</em> from our <span>Global Partners</span></h2>
            <p>Each year our students sit weekly exam sessions across 17+ vendors &mdash; from networking and cloud to offensive security. Below are the top tracks our graduates complete before joining the workforce.</p>
          </div>
          <div class="row">
            <div class="col-lg-12">
              <div class="first-bar progress-skill-bar">
                <h4>Cisco Professional &mdash; CCNP</h4>
                <span>90%</span>
                <div class="filled-bar"></div>
                <div class="full-bar"></div>
              </div>
            </div>
            <div class="col-lg-12">
              <div class="second-bar progress-skill-bar">
                <h4>Amazon AWS Professional &mdash; CSAP</h4>
                <span>85%</span>
                <div class="filled-bar"></div>
                <div class="full-bar"></div>
              </div>
            </div>
            <div class="col-lg-12">
              <div class="third-bar progress-skill-bar">
                <h4>Offensive Security &mdash; OSCP</h4>
                <span>78%</span>
                <div class="filled-bar"></div>
                <div class="full-bar"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="portfolio" class="our-portfolio section">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 offset-lg-3">
          <div class="section-heading  wow bounceIn" data-wow-duration="1s" data-wow-delay="0.2s">
            <h2>Our Certification <em>Tracks</em> &amp; <span>Specialties</span></h2>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-3 col-sm-6">
          <a href="#">
            <div class="item wow bounceInUp" data-wow-duration="1s" data-wow-delay="0.3s">
              <div class="hidden-content">
                <h4>Cloud Technologies</h4>
                <p>AWS, Google Cloud, AI/ML, containers, DevOps, SysOps and data engineering.</p>
              </div>
              <div class="showed-content">
                <img src="assets/images/portfolio-image.png" alt="cloud track">
              </div>
            </div>
          </a>
        </div>
        <div class="col-lg-3 col-sm-6">
          <a href="#">
            <div class="item wow bounceInUp" data-wow-duration="1s" data-wow-delay="0.4s">
              <div class="hidden-content">
                <h4>Cybersecurity</h4>
                <p>Offensive (OSCP, Red Team), defensive (Blue Team), network and IT governance.</p>
              </div>
              <div class="showed-content">
                <img src="assets/images/portfolio-image.png" alt="cybersecurity track">
              </div>
            </div>
          </a>
        </div>
        <div class="col-lg-3 col-sm-6">
          <a href="#">
            <div class="item wow bounceInUp" data-wow-duration="1s" data-wow-delay="0.5s">
              <div class="hidden-content">
                <h4>DevOps &amp; Automation</h4>
                <p>Jenkins, Terraform, Kubernetes, CI/CD pipelines and infrastructure as code.</p>
              </div>
              <div class="showed-content">
                <img src="assets/images/portfolio-image.png" alt="devops track">
              </div>
            </div>
          </a>
        </div>
        <div class="col-lg-3 col-sm-6">
          <a href="#">
            <div class="item wow bounceInUp" data-wow-duration="1s" data-wow-delay="0.6s">
              <div class="hidden-content">
                <h4>Networking &amp; Telecom</h4>
                <p>Cisco, Juniper, Huawei, Fortinet, Palo Alto &mdash; from CCNA to expert level.</p>
              </div>
              <div class="showed-content">
                <img src="assets/images/portfolio-image.png" alt="networking track">
              </div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div id="blog" class="our-blog section">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.25s">
          <div class="section-heading">
            <h2>Endorsed by Global <em>Industry</em> <span>Leaders</span></h2>
          </div>
        </div>
        <div class="col-lg-6 wow fadeInDown" data-wow-duration="1s" data-wow-delay="0.25s">
          <div class="top-dec"></div>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.25s">
          <div class="left-image">
            <a href="#"><img src="assets/images/partners/Satya%20NADELLA.jpg" alt="Satya Nadella, CEO Microsoft"></a>
            <div class="info">
              <div class="inner-content">
                <ul>
                  <li><i class="fa fa-user"></i> Satya Nadella</li>
                  <li><i class="fa fa-building"></i> Microsoft</li>
                  <li><i class="fa fa-certificate"></i> ITS Track</li>
                </ul>
                <a href="#"><h4>Microsoft Certifications &mdash; signed by the CEO</h4></a>
                <p>Every TEK-UP graduate completing the Microsoft ITS track receives a certificate signed at the executive level &mdash; opening doors at Microsoft partner networks worldwide.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.25s">
          <div class="right-list">
            <ul>
              <li>
                <div class="left-content align-self-center">
                  <span><i class="fa fa-user"></i> Chuck Robbins &middot; CEO, Cisco</span>
                  <a href="#"><h4>Cisco Networking &amp; CCNP Track</h4></a>
                  <p>Industry-recognized certificates across networking, security and collaboration.</p>
                </div>
                <div class="right-image">
                  <a href="#"><img src="assets/images/partners/Chuck%20Robbins.jpg" alt="Chuck Robbins, CEO Cisco"></a>
                </div>
              </li>
              <li>
                <div class="left-content align-self-center">
                  <span><i class="fa fa-user"></i> Thomas Kurian &middot; CEO, Google Cloud</span>
                  <a href="#"><h4>Google Cloud Developer Track</h4></a>
                  <p>Application development, data engineering and ML on Google Cloud Platform.</p>
                </div>
                <div class="right-image">
                  <a href="#"><img src="assets/images/partners/Thomas%20Kurian.jpg" alt="Thomas Kurian, CEO Google Cloud"></a>
                </div>
              </li>
              <li>
                <div class="left-content align-self-center">
                  <span><i class="fa fa-user"></i> Maureen Lonergan &middot; Director, AWS Academy</span>
                  <a href="#"><h4>AWS Professional &mdash; CSAP</h4></a>
                  <p>From AWS Cloud Practitioner to Solutions Architect Professional, fully proctored.</p>
                </div>
                <div class="right-image">
                  <a href="#"><img src="assets/images/partners/Maureen%20Lonergan.jpg" alt="Maureen Lonergan, Director AWS Academy"></a>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="contact" class="contact-us section">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 align-self-center wow fadeInLeft" data-wow-duration="0.5s" data-wow-delay="0.25s">
          <div class="section-heading">
            <h2>Talk to us about your <em>Career</em> &amp; <span>Certification Path</span></h2>
            <p>Whether you are a future student, a recruiter or an industry partner, our certification office answers within one business day. Tell us which track interests you and we'll get back with the full roadmap.</p>
            <div class="phone-info">
              <h4>Certifications Office: <span><i class="fa fa-phone"></i> <a href="#">+216 70 250 000</a></span></h4>
            </div>
          </div>
        </div>
        <div class="col-lg-6 wow fadeInRight" data-wow-duration="0.5s" data-wow-delay="0.25s">
          <form id="contact" action="contact.php" method="post">
            <div class="row">
              <div class="col-lg-6">
                <fieldset>
                  <input type="text" name="name" id="name" placeholder="Name" autocomplete="on" required>
                </fieldset>
              </div>
              <div class="col-lg-6">
                <fieldset>
                  <input type="text" name="surname" id="surname" placeholder="Surname" autocomplete="on" required>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <input type="email" name="email" id="email" placeholder="Your Email" required>
                </fieldset>
              </div>
              <div class="col-lg-12">
                <fieldset>
                  <textarea name="message" id="message" class="form-control" placeholder="Message" required></textarea>
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
