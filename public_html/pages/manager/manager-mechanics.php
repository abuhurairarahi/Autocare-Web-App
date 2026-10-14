<?php
require_once __DIR__ . '/../../api/db.php';

$stmt = $pdo->query("
    SELECT u.user_id as id,
           u.name,
           u.email,
           u.phone,
           COALESCE(u.specialty, 'Senior Mechanic') as specialty,
           COALESCE(u.experience, '5 Years') as experience,
           COALESCE(u.status, 'Available') as status,
           COALESCE(u.avatar, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150') as avatar,
           COUNT(j.job_id) as active_jobs
    FROM Users u
    LEFT JOIN JobCards j ON u.user_id = j.mechanic_id AND j.status NOT IN ('Completed', 'Delivered') AND (j.kanban_stage != 'COMPLETED' OR j.kanban_stage IS NULL)
    WHERE u.role = 'Mechanic'
    GROUP BY u.user_id
    ORDER BY u.name ASC
");
$mechanics = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare Mechanic Management</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-mechanics.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

  <div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">

      <a class="brand" href="manager-dashboard.php" style="text-decoration: none;">
        <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
        <span class="brand-name">
          <span style="color: #ffffff;">Auto</span><span style="color: #f97316;">Care</span>
        </span>
      </a>

      <!-- Nav Bar -->
      <nav class="nav">
        <a class="nav-item" href="manager-dashboard.php">
          <span class="icon"><i class="fa-solid fa-border-all"></i></span>Dashboard
        </a>

        <a class="nav-item" href="manager-booking-request.php">
          <span class="icon"><i class="fa-regular fa-calendar-check"></i></span>Booking Requests
        </a>

        <a class="nav-item" href="manager-jobCards.php">
          <span class="icon"><i class="fa-regular fa-clipboard"></i></span>Job Cards
        </a>

        <a class="nav-item active" href="manager-mechanics.php">
          <span class="icon"><i class="fa-solid fa-users-gear"></i></span>Mechanics
        </a>

        <a class="nav-item" href="manager-process-tracker.php">
          <span class="icon"><i class="fa-solid fa-list-check"></i></span>Job Tracking
        </a>

        <a class="nav-item" href="manager-cost-estimation.php">
          <span class="icon"><i class="fa-solid fa-calculator"></i></span>Cost Estimates
        </a>

        <a class="nav-item" href="manager-invoice-management.php">
          <span class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>Invoices
        </a>

        <a class="nav-item" href="manager-payment-approval.php">
          <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>Spare Parts Approval
        </a>

        <a class="nav-item" href="manager-chat.php">
          <span class="icon"><i class="fa-regular fa-comments"></i></span>Chats
        </a>

        <a class="nav-item" href="manager-performance.php">
          <span class="icon"><i class="fa-solid fa-chart-line"></i></span>Report
        </a>
      </nav>

      <a class="logout" href="../login.html">
        <span><i class="fa-solid fa-right-from-bracket"></i></span>Logout
      </a>

    </aside>


    <!-- MAIN -->
    <main class="main">

      <!-- Top Bar -->
      <header class="topbar">
        <div class="search">
          <div class="search-icon">
            <svg viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>
          <input type="text" placeholder="Search managers, workshops...">
        </div>

        <div class="top-actions">
          <div class="notification">
            <svg viewBox="0 0 24 24">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <b></b>
          </div>

          <div class="mail">
            <svg viewBox="0 0 24 24">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
          </div>

          <div class="avatar">A</div>
        </div>
      </header>


      <!-- CONTENT -->
      <section class="content">

        <!-- Page Heading -->
        <div class="page-heading">
          <div>
            <h1>Workshop Mechanics</h1>
            <p>Monitor workloads, specializations, and real-time bay assignments connected to MySQL</p>
          </div>

          <div class="filters">
            <select id="spec-filter" class="filter-select">
              <option value="all">Specialization: All</option>
              <option value="Engine">Engine</option>
              <option value="Electrician">Electrical</option>
              <option value="Transmission">Transmission</option>
              <option value="Suspension">Suspension</option>
              <option value="Diagnostic">Diagnostics</option>
            </select>

            <select id="avail-filter" class="filter-select">
              <option value="all">Availability: All</option>
              <option value="Available">Available</option>
              <option value="Busy">Busy</option>
              <option value="Off Shift">Off Shift</option>
            </select>
          </div>
        </div>


        <!-- MECHANIC CARDS GRID -->
        <div class="mechanic-grid">
          <?php foreach ($mechanics as $m): 
            $activeJobs = (int)$m['active_jobs'];
            $workload = min(100, $activeJobs * 25);
            $isOffShift = ($m['status'] === 'Off Shift');
            $isBusy = ($workload >= 80 || $m['status'] === 'Busy');
            $badgeClass = $isOffShift ? 'off-shift' : ($isBusy ? 'busy' : 'available');
            $statusText = strtoupper($m['status']);
            $progressClass = ($workload >= 80) ? 'high' : 'low';
          ?>
          <article class="mechanic-card" data-mechanic-id="<?= $m['id'] ?>">
            <div class="card-header">
              <img src="<?= htmlspecialchars($m['avatar']) ?>" alt="<?= htmlspecialchars($m['name']) ?>" class="mechanic-avatar">
              <div class="mechanic-info">
                <div class="name-status">
                  <h3><?= htmlspecialchars($m['name']) ?></h3>
                  <span class="status-badge <?= $badgeClass ?>"><?= $statusText ?></span>
                </div>
                <p class="specialty"><?= htmlspecialchars($m['specialty']) ?></p>
              </div>
            </div>

            <div class="card-body">
              <div class="info-row">
                <span class="label">Experience</span>
                <span class="value"><?= htmlspecialchars($m['experience']) ?></span>
              </div>

              <div class="workload-section">
                <div class="workload-header">
                  <span class="label">CURRENT WORKLOAD</span>
                  <span class="percentage <?= $progressClass ?>"><?= $workload ?>%</span>
                </div>
                <div class="progress-bar">
                  <div class="progress-fill <?= $progressClass ?>" style="width: <?= $workload ?>%;"></div>
                </div>
              </div>

              <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;">
                <?php if ($isOffShift): ?>
                  <button class="btn btn-disabled" disabled style="opacity: 0.5; cursor: not-allowed;">Off Shift</button>
                <?php else: ?>
                  <button class="btn btn-primary" onclick="openAssignJobModal(<?= $m['id'] ?>)"><?= $isBusy ? 'Reassign / Add' : 'Assign Job' ?></button>
                <?php endif; ?>
                <button class="btn" onclick="window.location.href='manager-chat.php'" style="background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 12.5px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                  Chat with <?= htmlspecialchars(explode(' ', $m['name'])[0]) ?>
                </button>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-mechanics.js"></script>
</body>
</html>
