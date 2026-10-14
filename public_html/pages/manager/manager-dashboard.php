<?php
require_once __DIR__ . '/../../api/db.php';

// Direct Database Queries for Server-Side Rendering
$pendingBookings = (int)$pdo->query("SELECT COUNT(*) FROM Appointments WHERE status = 'Pending'")->fetchColumn();
$activeJobs = (int)$pdo->query("SELECT COUNT(*) FROM JobCards WHERE status NOT IN ('Completed', 'Delivered') AND (kanban_stage != 'COMPLETED' OR kanban_stage IS NULL)")->fetchColumn();
$totalMechs = (int)$pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Mechanic'")->fetchColumn();
$busyMechs = (int)$pdo->query("SELECT COUNT(DISTINCT mechanic_id) FROM JobCards WHERE mechanic_id IS NOT NULL AND status NOT IN ('Completed', 'Delivered') AND (kanban_stage != 'COMPLETED' OR kanban_stage IS NULL)")->fetchColumn();
$waitingApprovals = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM JobParts WHERE status = 'Pending Approval') + (SELECT COUNT(*) FROM RepairEstimates WHERE status = 'Draft')")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Paid'")->fetchColumn();

// Fetch Mechanics
$mechsStmt = $pdo->query("
    SELECT u.user_id as id, u.name, u.email, u.phone, u.specialty, u.experience, u.status, u.avatar,
           COUNT(j.job_id) as active_jobs
    FROM Users u
    LEFT JOIN JobCards j ON u.user_id = j.mechanic_id AND j.status NOT IN ('Completed', 'Delivered') AND (j.kanban_stage != 'COMPLETED' OR j.kanban_stage IS NULL)
    WHERE u.role = 'Mechanic'
    GROUP BY u.user_id
    ORDER BY u.name ASC
");
$mechanicsList = $mechsStmt->fetchAll();

// Fetch Recent Activities
$activitiesStmt = $pdo->query("SELECT * FROM ActivityLogs ORDER BY created_at DESC, activity_id DESC LIMIT 10");
$recentActivities = $activitiesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare Manager Overview</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-dashboard.css">
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
        <a class="nav-item active" href="manager-dashboard.php">
          <span class="icon"><i class="fa-solid fa-border-all"></i></span>Dashboard
        </a>

        <a class="nav-item" href="manager-booking-request.php">
          <span class="icon"><i class="fa-regular fa-calendar-check"></i></span>Booking Requests
        </a>

        <a class="nav-item" href="manager-jobCards.php">
          <span class="icon"><i class="fa-regular fa-clipboard"></i></span>Job Cards
        </a>

        <a class="nav-item" href="manager-mechanics.php">
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
            <h1>Overview</h1>
            <p>Welcome back. Here is the current status of the workshop connected to MySQL.</p>
          </div>

          <button class="new-job">+ New Job Card</button>
        </div>


        <!-- STAT CARDS -->
        <div class="stats">

          <!-- Card 1 -->
          <article class="stat-card" onclick="window.location.href='manager-booking-request.php'" style="cursor: pointer;">
            <div class="stat-top">
              <div class="stat-icon light-blue">
                <svg viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              <span class="badge red">Live</span>
            </div>
            <p class="one">PENDING BOOKINGS</p>
            <strong><?= $pendingBookings ?></strong>
          </article>

          <!-- Card 2 -->
          <article class="stat-card" onclick="window.location.href='manager-jobCards.php'" style="cursor: pointer;">
            <div class="stat-top">
              <div class="stat-icon blue">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                  <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
              </div>
              <span class="badge green">Active</span>
            </div>
            <p class="one">ACTIVE JOB CARDS</p>
            <strong><?= $activeJobs ?></strong>
          </article>

          <!-- Card 3 -->
          <article class="stat-card" onclick="window.location.href='manager-mechanics.php'" style="cursor: pointer;">
            <div class="stat-top one">
              <div class="stat-icon dark">
                <svg viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </div>
            </div>
            <p class="one">ASSIGNED<br>MECHANICS</p>
            <strong><?= $busyMechs ?><span class="total">/<?= $totalMechs ?></span></strong>
          </article>

          <!-- Card 4 -->
          <article class="stat-card" onclick="window.location.href='manager-payment-approval.php'" style="cursor: pointer;">
            <div class="stat-top">
              <div class="stat-icon pink">
                <svg viewBox="0 0 24 24">
                  <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                  <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
              </div>
              <span class="badge red">Action Req</span>
            </div>
            <p class="one">WAITING APPROVAL</p>
            <strong><?= $waitingApprovals ?></strong>
          </article>

          <!-- Card 5 (Revenue) -->
          <article class="stat-card revenue" onclick="window.location.href='manager-invoice-management.php'" style="cursor: pointer;">
            <div class="stat-top">
              <div class="stat-icon revenue-icon">৳</div>
              <span class="badge navy">Paid</span>
            </div>
            <div class="revenue-period">
              <button class="active">Today</button>
              <button>Week</button>
              <button>Month</button>
            </div>
            <p class="revenue-title">REVENUE (PAID)</p>
            <strong>৳<?= number_format($totalRevenue, 2) ?></strong>
          </article>

        </div>
        
        <!-- DASH GRID -->
        <div class="dashboard-grid">

          <!-- Revenue Trend Panel -->
          <section class="panel revenue-panel">
            <div class="panel-header">
              <h2>Revenue Trend</h2>
              <div class="period">
                <button>Weekly</button>
                <button class="selected">Monthly</button>
              </div>
            </div>

            <div class="chart">
              <div class="chart-grid">
                <span></span><span></span><span></span><span></span>
              </div>
              <div class="area"></div>
              <svg class="chart-line-svg" preserveAspectRatio="none" viewBox="0 0 600 200">
                <polyline points="0,166 90,120 180,136 276,74 366,96 462,34 600,54"></polyline>
              </svg>
              <div class="months">
                <span>Jan</span>
                <span>Feb</span>
                <span>Mar</span>
                <span>Apr</span>
                <span>May</span>
                <span>Jun</span>
              </div>
            </div>
          </section>

          <!-- MECHANICS WORKLOAD -->
          <section class="panel workload mechanics-panel">
            <div class="panel-header">
              <h2>Workshop Mechanics Workload</h2>
              <a class="view-all" href="manager-mechanics.php">View All</a>
            </div>

            <div class="mechanics-grid workload-body">
              <?php foreach ($mechanicsList as $m): 
                $activeJobs = (int)$m['active_jobs'];
                $workload = min(100, $activeJobs * 25);
                $isOffShift = ($m['status'] === 'Off Shift');
                $badgeClass = $isOffShift ? 'off-shift' : (($workload >= 80 || $m['status'] === 'Busy') ? 'busy' : 'available');
                $badgeText = strtoupper($m['status']);
                $fillClass = ($workload >= 80) ? 'high' : 'low';
              ?>
              <div class="mechanic-card mechanic" style="cursor: pointer;" onclick="window.location.href='manager-mechanics.php'">
                <div class="mechanic-top" style="display:flex; justify-content:space-between; width: 100%;">
                  <div><span><?= htmlspecialchars($m['name']) ?></span> <b><?= $activeJobs ?> <?= $activeJobs === 1 ? 'Job' : 'Jobs' ?></b></div>
                </div>
                <div class="bar progress-bar"><i class="<?= $fillClass === 'high' ? 'danger' : '' ?>" style="width: <?= $workload ?>%"></i></div>
              </div>
              <?php endforeach; ?>
            </div>
          </section>
          
        </div>


        <!-- RECENT ACTIVITY -->
        <section class="panel activity-panel">
          <div class="panel-header">
            <h2>Recent Activities</h2>
            <span style="font-size: 12px; color: #94a3b8;">Real-time database feed</span>
          </div>

          <?php if (empty($recentActivities)): ?>
            <p style="text-align: center; color: #94a3b8; padding: 20px;">No recent activities logged.</p>
          <?php else: ?>
            <?php foreach ($recentActivities as $act): 
              $circleClass = ($act['type'] === 'red') ? 'red-circle' : (($act['type'] === 'gray') ? 'gray-circle' : 'blue-circle');
            ?>
            <div class="activity-row">
              <div class="activity-icon <?= $circleClass ?>">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                </svg>
              </div>
              <div class="activity-text">
                <div><?= $act['text'] ?></div>
                <small><?= date('h:i A', strtotime($act['created_at'])) ?> <span>•</span> <em><?= htmlspecialchars($act['subtext'] ?? '') ?></em></small>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </section>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-dashboard.js"></script>
</body>

</html>
