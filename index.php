<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoCare — Your car, handled.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="public_html/assets/css/landing.css">
</head>
<body>

<!-- Header -->
<header class="site-header">
  <div class="wrap header-inner">
    <a class="logo" href="#top">
      <span class="logo-mark">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 16.5V11l2.2-4.4A2 2 0 0 1 8 5.5h8a2 2 0 0 1 1.8 1.1L20 11v5.5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V16H7v.5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z" stroke="white" stroke-width="1.6" stroke-linejoin="round"/>
          <circle cx="7.5" cy="14" r="1.3" fill="white"/>
          <circle cx="16.5" cy="14" r="1.3" fill="white"/>
          <path d="M4 11h16" stroke="white" stroke-width="1.6"/>
        </svg>
      </span>
      <span class="logo-text">Auto<span class="accent">Care</span></span>
    </a>

    <nav class="main-nav">
      <a href="#services">Service</a>
      <a href="#locations">Locations</a>
      <a href="#contact">Contact</a>
    </nav>

    <div class="header-actions">
      <a class="sign-in" href="public_html/pages/login.html">Sign in</a>
      <a class="btn btn-primary btn-sm" href="#contact">Book a service</a>
    </div>
  </div>
</header>

<!-- Hero -->
<section class="hero" id="top">
  <div class="wrap hero-inner">
    <div class="hero-copy">
      <span class="badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l1.8 4.9L19 8l-4.9 1.8L12 15l-1.8-5.2L5 8l5.2-1.1L12 2z" fill="#FF6A24"/></svg>
        New — live repair tracking
      </span>

      <h1>Your car, <span class="accent">handled</span>.<br>From booking to invoice.</h1>

      <p class="lead">AutoCare connects owners, mechanics, and workshop managers on one platform — with job cards, cost approvals, live repair stages, and secure invoicing.</p>

      <div class="hero-ctas">
        <a class="btn btn-primary" href="#contact">Book a service</a>
        <a class="btn btn-outline" href="#services">View services &amp; rates</a>
      </div>

      <ul class="hero-features">
        <li>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#FF6A24" stroke-width="1.6"/><path d="M8.5 12.5l2.2 2.2L15.8 9.5" stroke="#FF6A24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Certified mechanics
        </li>
        <li>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 19c4-1 5-11 9-12M13 7l6-1-1 6" stroke="#FF6A24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Live repair stages
        </li>
        <li>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v6A2.5 2.5 0 0 1 17.5 15H10l-4 3.5V15h-.5A2.5 2.5 0 0 1 3 12.5v-6z" stroke="#FF6A24" stroke-width="1.6" stroke-linejoin="round"/></svg>
          Direct chat
        </li>
      </ul>
    </div>

    <div class="hero-visual">
      <div class="visual-frame">
        <div class="job-card">
          <div class="job-card-top">
            <span class="job-card-label">Job Card</span>
            <span class="pill pill-orange">Testing</span>
          </div>
          <h3>Mitsubishi Pajero Sports</h3>
          <p class="token">Token no. AC-1001</p>

          <ol class="steps">
            <li class="done"><span class="step-index">1</span><span class="step-name">Diagnosis</span><span class="step-status">Done</span></li>
            <li class="done"><span class="step-index">2</span><span class="step-name">Repairing</span><span class="step-status">Done</span></li>
            <li class="active"><span class="step-index">3</span><span class="step-name">Testing</span><span class="step-status status-progress">In progress</span></li>
            <li class="pending"><span class="step-index">4</span><span class="step-name">Ready</span><span class="step-status"></span></li>
          </ol>

          <div class="estimate">
            <div>
              <span class="estimate-label">Estimate</span>
              <span class="estimate-amount">৳ 10,000.00</span>
            </div>
            <span class="pill pill-dark">Approved</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Services -->
<section class="services" id="services">
  <div class="wrap">
    <p class="eyebrow">Services &amp; rates</p>
    <h2>Everything your vehicle needs</h2>

    <div class="card-grid">

      <?php
      require_once 'public_html/api/db.php';
      try {
          $stmt = $pdo->query("SELECT * FROM ServiceCategories LIMIT 4");
          $categories = $stmt->fetchAll();
          if (count($categories) > 0) {
              foreach ($categories as $cat) {
                  // Fake base price and meta for landing page visual appeal since they aren't in DB yet
                  $basePrice = rand(5, 25) * 1000;
                  $metaText = "Service · 1-3 days";
                  echo '<article class="service-card">
        <div class="service-top">
          <span class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z" stroke="white" stroke-width="1.6"/><path d="M12 3.5v2M12 18.5v2M20.5 12h-2M5.5 12h-2M17.8 6.2l-1.4 1.4M7.6 16.4l-1.4 1.4M17.8 17.8l-1.4-1.4M7.6 7.6L6.2 6.2" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <div class="service-price"><span class="amount">৳' . number_format($basePrice) . '</span><span class="starting">Starting</span></div>
        </div>
        <h3>' . htmlspecialchars($cat['name']) . '</h3>
        <p class="service-meta">' . htmlspecialchars($cat['description']) . '</p>
        <a class="service-link" href="#contact">Book this service <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="#0B1B3A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      </article>';
              }
          } else {
              echo '<p>No services currently available.</p>';
          }
      } catch (Exception $e) {
          echo '<p>Error loading services.</p>';
      }
      ?>

    </div>
  </div>
</section>

<!-- Locations -->
<section class="locations" id="locations">
  <div class="wrap">
    <p class="eyebrow">Workshop locations</p>
    <h2>Find us near you</h2>

    <div class="card-grid locations-grid">
      <?php
      try {
          $stmt = $pdo->query("SELECT * FROM Workshops LIMIT 3");
          $workshops = $stmt->fetchAll();
          if (count($workshops) > 0) {
              foreach ($workshops as $ws) {
                  echo '<article class="location-card">
        <span class="pin-box">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" stroke="#FF6A24" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4" stroke="#FF6A24" stroke-width="1.6"/></svg>
        </span>
        <h3>' . htmlspecialchars($ws['name']) . '</h3>
        <p class="loc-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" stroke="#545E6F" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.2" stroke="#545E6F" stroke-width="1.6"/></svg>
          ' . htmlspecialchars($ws['location']) . '
        </p>
        <p class="loc-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="#545E6F" stroke-width="1.6"/><path d="M12 8v4l2.6 2.6" stroke="#545E6F" stroke-width="1.6" stroke-linecap="round"/></svg>
          Mon–Sat 8:00–20:00
        </p>
        <a class="text-link" href="#">Get directions <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="#FB7C00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      </article>';
              }
          } else {
              echo '<p>No locations currently available.</p>';
          }
      } catch (Exception $e) {
          echo '<p>Error loading locations.</p>';
      }
      ?>
    </div>
  </div>
</section>

<!-- Contact -->
<section class="contact" id="contact">
  <div class="wrap contact-inner">
    <div class="contact-copy">
      <p class="eyebrow">Contact</p>
      <h2>We're here to help</h2>
      <p class="lead lead-dark">Questions about a booking, a repair estimate, or a spare part? Drop us a message and we'll respond within one business hour.</p>

      <div class="contact-methods">
        <div class="contact-row">
          <span class="icon-box dark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6.5 4h2.7l1.3 3.6-1.7 1.6a12 12 0 0 0 5.5 5.5l1.6-1.7 3.6 1.3v2.7c0 1-.8 1.9-1.9 1.8-8-.6-13.9-6.5-14.5-14.5C3 4.8 3.9 4 4.9 4h1.6z" stroke="#FF8D1D" stroke-width="1.5" stroke-linejoin="round"/></svg></span>
          <div>
            <p class="contact-main">+94 11 555 0132</p>
            <p class="contact-sub">Mon–Sat, 8am–8pm</p>
          </div>
        </div>
        <div class="contact-row">
          <span class="icon-box dark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v6A2.5 2.5 0 0 1 17.5 15H10l-4 3.5V15h-.5A2.5 2.5 0 0 1 3 12.5v-6z" stroke="#FF8D1D" stroke-width="1.5" stroke-linejoin="round"/></svg></span>
          <div>
            <p class="contact-main">care@autocare.com</p>
            <p class="contact-sub">Chat available after sign-in</p>
          </div>
        </div>
        <div class="contact-row">
          <span class="icon-box dark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 16V8.5a1.5 1.5 0 0 1 1.5-1.5H13v9H4.5A1.5 1.5 0 0 1 3 14.5V16zM13 10h3.5L19 12.8V16h-2M13 16h3" stroke="#FF8D1D" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/><circle cx="7.5" cy="16.5" r="1.5" stroke="#FF8D1D" stroke-width="1.5"/><circle cx="16.5" cy="16.5" r="1.5" stroke="#FF8D1D" stroke-width="1.5"/></svg></span>
          <div>
            <p class="contact-main">3 workshops country wide</p>
            <p class="contact-sub">Pickup &amp; drop available</p>
          </div>
        </div>
      </div>
    </div>

    <form class="contact-form">
      <h3>Send a message</h3>
      <div class="form-row">
        <input type="text" placeholder="Full name" required>
        <input type="email" placeholder="Email" required>
      </div>
      <div class="form-row single">
        <select required>
          <option value="" selected disabled>Service Type</option>
          <option>Engine Overhaul</option>
          <option>Electrical Diagnostics</option>
          <option>Denting &amp; Painting</option>
          <option>Full Servicing</option>
          <option>Brake Replacement</option>
          <option>AC Repair</option>
        </select>
      </div>
      <div class="form-row single">
        <textarea rows="4" placeholder="How can we help?" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Send message</button>
    </form>
  </div>
</section>

<!-- Footer -->
<footer class="site-footer">
  <div class="wrap footer-inner">
    <p class="footer-copy">
      © 2026 UI by Team Dualdot.
    </p>
    <nav class="footer-nav">
      <a href="#top">Home</a>
      <a href="#services">Services</a>
      <a href="#locations">Locations</a>
      <a href="#contact">Contact</a>
    </nav>
  </div>
</footer>

</body>
</html>
