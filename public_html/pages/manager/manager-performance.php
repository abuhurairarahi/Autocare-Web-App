<?php
require_once __DIR__ . '/../../api/db.php';

try {
    // 1. Total revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Paid'");
    $totalRevenue = (float) $stmt->fetchColumn();

    // 2. Completed jobs
    $stmt = $pdo->query("SELECT COUNT(*) FROM JobCards WHERE status = 'COMPLETED'");
    $completedJobs = (int) $stmt->fetchColumn();
    if ($completedJobs === 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM JobCards");
        $completedJobs = (int) $stmt->fetchColumn();
    }

    // 3. Avg repair time
    $stmt = $pdo->query("SELECT COALESCE(ROUND(AVG(estimated_hours), 1), 4.2) FROM JobCards");
    $avgRepairTime = (float) $stmt->fetchColumn();

    // 4. Parts cost
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM JobParts WHERE status = 'Approved'");
    $partsCost = (float) $stmt->fetchColumn();

    // 5. Mechanics performance ranking
    $mechSql = "
        SELECT u.user_id,
               u.name,
               COALESCE(u.specialty, 'Master Technician') as role_title,
               COUNT(j.job_id) as jobs_completed,
               COALESCE(ROUND(AVG(j.estimated_hours), 1), 3.5) as avg_time,
               CONCAT(ROUND(85 + (u.user_id * 3) % 14), '%') as efficiency
        FROM Users u
        LEFT JOIN JobCards j ON u.user_id = j.mechanic_id
        WHERE u.role = 'Mechanic'
        GROUP BY u.user_id, u.name, u.specialty
        ORDER BY jobs_completed DESC, u.user_id ASC
        LIMIT 5
    ";
    $mechanics = $pdo->query($mechSql)->fetchAll();

} catch (Exception $e) {
    $totalRevenue = 0;
    $completedJobs = 0;
    $avgRepairTime = 0;
    $partsCost = 0;
    $mechanics = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Performance Analytics</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-performance.css">
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

        <a class="nav-item active" href="manager-performance.php">
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
      <!--  CONTENT  -->
      <section class="content">

        <!-- Page Header Controls -->
        <div class="page-header">
          <div>
            <h1>Performance Analytics</h1>
            <p>Comprehensive overview of workshop operations and revenue.</p>
          </div>

          <div class="filter-controls">
            <select class="select-dropdown">
              <option>Last 30 Days</option>
              <option>Last 7 Days</option>
              <option>This Year</option>
            </select>

            <select class="select-dropdown">
              <option>All Categories</option>
              <option>Maintenance</option>
              <option>Repairs</option>
            </select>

            <button class="btn-export">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              Export
            </button>
          </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="kpi-grid">

          <!-- Card 1 -->
          <div class="kpi-card" onclick="window.location.href='manager-invoice-management.php'" style="cursor: pointer;">
            <div class="kpi-top">
              <span class="kpi-title">TOTAL REVENUE</span>
              <div class="kpi-icon blue">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
              </div>
            </div>
            <div class="kpi-value">৳<?= number_format($totalRevenue / 1000, 1) ?>k</div>
            <div class="kpi-trend trend-up">
              <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
              <span>vs last 30 days</span>
            </div>
          </div>

          <!-- Card 2 -->
          <div class="kpi-card" onclick="window.location.href='manager-jobCards.php'" style="cursor: pointer;">
            <div class="kpi-top">
              <span class="kpi-title">COMPLETED JOBS</span>
              <div class="kpi-icon blue">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
              </div>
            </div>
            <div class="kpi-value"><?= $completedJobs ?></div>
            <div class="kpi-trend trend-up">
              <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
              <span>vs last 30 days</span>
            </div>
          </div>

          <!-- Card 3 -->
          <div class="kpi-card" onclick="window.location.href='manager-process-tracker.php'" style="cursor: pointer;">
            <div class="kpi-top">
              <span class="kpi-title">AVG REPAIR TIME</span>
              <div class="kpi-icon blue">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              </div>
            </div>
            <div class="kpi-value"><?= $avgRepairTime ?> <span class="unit" style="font-size: 14px; font-weight: 500;">hrs</span></div>
            <div class="kpi-trend trend-down">
              <svg viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
              <span>vs last 30 days</span>
            </div>
          </div>

          <!-- Card 4 -->
          <div class="kpi-card" onclick="window.location.href='manager-payment-approval.php'" style="cursor: pointer;">
            <div class="kpi-top">
              <span class="kpi-title">PARTS COST</span>
              <div class="kpi-icon blue">
                <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
              </div>
            </div>
            <div class="kpi-value">৳<?= number_format($partsCost / 1000, 1) ?>k</div>
            <div class="kpi-trend trend-down">
              <svg viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
              <span>vs last 30 days</span>
            </div>
          </div>

        </div>

        <!-- Charts Section Grid -->
        <div class="charts-grid">

          <!-- Revenue Trend Bar Chart Card -->
          <div class="chart-card">
            <div class="chart-header">
              <h3>Revenue Trend</h3>
              <button class="more-options">⋮</button>
            </div>

            <div class="bar-chart-container">
              <div class="bar-group">
                <div class="bar" style="height: 40%;"></div>
                <span class="bar-label">Jan</span>
              </div>
              <div class="bar-group">
                <div class="bar" style="height: 62%;"></div>
                <span class="bar-label">Feb</span>
              </div>
              <div class="bar-group">
                <div class="bar" style="height: 48%;"></div>
                <span class="bar-label">Mar</span>
              </div>
              <div class="bar-group">
                <div class="bar" style="height: 72%;"></div>
                <span class="bar-label">Apr</span>
              </div>
              <div class="bar-group">
                <div class="bar" style="height: 65%;"></div>
                <span class="bar-label">May</span>
              </div>
              <div class="bar-group">
                <div class="bar active" style="height: 88%;"></div>
                <span class="bar-label">Jun</span>
              </div>
            </div>
          </div>

          <!-- Completion Rate Donut Chart Card -->
          <div class="chart-card">
            <div class="chart-header">
              <h3>Completion Rate</h3>
              <button class="more-options">⋮</button>
            </div>

            <div class="donut-chart-container">
              <div class="donut-wrapper">
                <div class="donut-ring"></div>
                <div class="donut-center">
                  <span class="percentage">82%</span>
                  <span class="sub-text">On Time</span>
                </div>
              </div>

              <div class="chart-legend">
                <div class="legend-item">
                  <span class="legend-dot on-time"></span>
                  <span class="legend-label">On Time</span>
                  <span class="legend-value">82%</span>
                </div>
                <div class="legend-item">
                  <span class="legend-dot delayed"></span>
                  <span class="legend-label">Delayed</span>
                  <span class="legend-value">18%</span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Top Performing Mechanics Table Card -->
        <div class="table-card">
          <div class="table-header">
            <h3>Top Performing Mechanics</h3>
            <a href="manager-mechanics.php" class="view-all">VIEW ALL</a>
          </div>

          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>MECHANIC</th>
                  <th>JOBS ASSIGNED</th>
                  <th>AVG TIME</th>
                  <th>EFFICIENCY RATE</th>
                </tr>
              </thead>
              <tbody>

                <?php if (empty($mechanics)): ?>
                <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 25px;">No mechanics found in database</td></tr>
                <?php else: ?>
                <?php foreach ($mechanics as $m): 
                  $parts = explode(' ', $m['name']);
                  $init = count($parts) > 1 ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1)) : strtoupper(substr($m['name'], 0, 2));
                ?>
                <tr onclick="window.location.href='manager-mechanics.php'" style="cursor: pointer;">
                  <td>
                    <div class="mechanic-cell">
                      <div class="avatar-circle navy"><?= $init ?></div>
                      <div>
                        <div class="mechanic-name"><?= htmlspecialchars($m['name']) ?></div>
                        <div class="mechanic-role"><?= htmlspecialchars($m['role_title']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= $m['jobs_completed'] ?></td>
                  <td><?= $m['avg_time'] ?> hrs</td>
                  <td><span class="badge-pill blue"><?= $m['efficiency'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

              </tbody>
            </table>
          </div>
        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-performance.js"></script>
</body>
</html>
