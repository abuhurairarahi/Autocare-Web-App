<?php
require_once __DIR__ . '/../api/auth.php';

// Already signed in: go straight to the role's home page
$existing = current_user();
if ($existing && isset(ROLE_HOME[$existing['role']])) {
    header('Location: ' . ROLE_HOME[$existing['role']]);
    exit;
}

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($lockedUntil > time()) {
        $error = 'Too many failed attempts. Please wait a moment and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 200) {
        $error = 'Enter a valid email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, name, role, password_hash FROM Users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            error_log('[AutoCare login] ' . $e->getMessage());
            $row = false;
            $error = 'Sign-in is unavailable right now.';
        }

        if ($row && password_verify($password, $row['password_hash']) && isset(ROLE_HOME[$row['role']])) {
            unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
            login_user($row);
            header('Location: ' . ROLE_HOME[$row['role']]);
            exit;
        }

        if ($error === '') {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 30;
                $_SESSION['login_failures'] = 0;
            }
            $error = 'Incorrect email or password.';
        }
    }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in to AutoForge — AutoCare</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<div class="page">

  <!-- Logo -->
  <a class="logo" href="#">
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

  <!-- Hero copy -->
  <div class="hero-copy">
    <h1>The workshop, <span class="accent">reimagined</span>.</h1>
    <p class="lead">One dashboard for admins, managers, mechanics and owners. Live status, chat, invoices — all in one place.</p>
  </div>

  <!-- Sign-in card -->
  <div class="signin-card">
    <a class="back-link" href="landing.html">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M11 6l-6 6 6 6" stroke="#545E6F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      back to home
    </a>

    <h2>Sign in to AutoForge</h2>
    <p class="card-sub">Choose a role for this demo session.</p>

    <div class="role-grid">
      <a href="admin/dashboard.html" class="role-tag">
      <button type="button" class="role-card" data-role="administrator">
        <span class="role-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.6"/><circle cx="12" cy="10" r="3" stroke="white" stroke-width="1.6"/><path d="M6.2 18.5c1-2.2 3.2-3.5 5.8-3.5s4.8 1.3 5.8 3.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="role-title">Administrator</span>
        <span class="role-desc">Full platform control</span>
      </button></a>

      <a href="manager/manager-dashboard.html" class="role-tag">
      <button type="button" class="role-card" data-role="workshop-manager">
        <span class="role-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.6"/><circle cx="12" cy="10" r="3" stroke="white" stroke-width="1.6"/><path d="M6.2 18.5c1-2.2 3.2-3.5 5.8-3.5s4.8 1.3 5.8 3.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="role-title">Workshop Manager</span>
        <span class="role-desc">Job cards &amp; assignments</span>
      </button></a>

      <a href="#" class="role-tag" data-demo-email="david.c@autocare.com">
      <button type="button" class="role-card" data-role="mechanic">
        <span class="role-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.6"/><circle cx="12" cy="10" r="3" stroke="white" stroke-width="1.6"/><path d="M6.2 18.5c1-2.2 3.2-3.5 5.8-3.5s4.8 1.3 5.8 3.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="role-title">Mechanic</span>
        <span class="role-desc">Assigned repair jobs</span>
      </button></a>

      <a href="#" class="role-tag" data-demo-email="ops@apexlogistics.com">
      <button type="button" class="role-card" data-role="vehicle-owner">
        <span class="role-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.6"/><circle cx="12" cy="10" r="3" stroke="white" stroke-width="1.6"/><path d="M6.2 18.5c1-2.2 3.2-3.5 5.8-3.5s4.8 1.3 5.8 3.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="role-title">Vehicle Owner</span>
        <span class="role-desc">Track the vehicle</span>
      </button></a>
    </div>

    <form class="signin-form" method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <?php if ($error !== ''): ?>
      <p role="alert" style="margin: 0 0 12px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px;"><?= e($error) ?></p>
      <?php endif; ?>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required maxlength="100" value="<?= e($email) ?>">

      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200">

      <button type="submit" class="btn-continue">Continue</button>

      <a class="forgot-link" href="#">Forgot password?</a>
    </form>
  </div>

</div>

<script>
  // Demo role cards for Mechanic / Vehicle Owner pre-fill the seeded account email
  document.querySelectorAll('.role-tag[data-demo-email]').forEach(function (tag) {
    tag.addEventListener('click', function (e) {
      e.preventDefault();
      document.getElementById('email').value = tag.getAttribute('data-demo-email');
      document.getElementById('password').focus();
    });
  });
</script>
</body>
</html>
