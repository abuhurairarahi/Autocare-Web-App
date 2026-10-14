<?php
require_once __DIR__ . '/../../api/db.php';

// Stats
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM JobCards WHERE status NOT IN ('Completed', 'Delivered') AND (kanban_stage != 'COMPLETED' OR kanban_stage IS NULL)")->fetchColumn();
$awaitingPartsCount = (int)$pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Pending Approval'")->fetchColumn();
$qcCount = (int)$pdo->query("SELECT COUNT(*) FROM JobCards WHERE status = 'Testing' OR progress_percentage >= 85")->fetchColumn();
$completedCount = (int)$pdo->query("SELECT COUNT(*) FROM JobCards WHERE kanban_stage = 'COMPLETED' OR status IN ('Ready', 'Completed', 'Delivered')")->fetchColumn();

// Job Cards list
$stmt = $pdo->query("
    SELECT j.job_id as id,
           COALESCE(j.code, CONCAT('JC-', j.job_id)) as code,
           COALESCE(j.work_order, CONCAT('#WO-', 2000 + j.job_id)) as work_order,
           COALESCE(u.name, 'Customer') as customer_name,
           CONCAT(v.make, ' ', v.model, ' • ', v.license_plate) as vehicle_details,
           j.mechanic_id,
           COALESCE(m.name, 'Unassigned') as mechanic_name,
           COALESCE(
               CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
               'UA'
           ) as mechanic_initials,
           j.status,
           COALESCE(j.kanban_stage, 'PENDING') as kanban_stage,
           COALESCE(j.progress_percentage, 10) as progress_percentage,
           DATE_FORMAT(j.created_at, '%b %d, %Y') as date_opened,
           COALESCE(j.delivery_date, 'TBD') as delivery_date
    FROM JobCards j
    LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
    LEFT JOIN Users u ON a.owner_id = u.user_id
    LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
    LEFT JOIN Users m ON j.mechanic_id = m.user_id
    ORDER BY j.job_id DESC
");
$jobCards = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare Manager Job Cards</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-jobCards.css">
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

        <a class="nav-item active" href="manager-jobCards.php">
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

      <!-- ================= CONTENT ================= -->
      <section class="content">

        <!-- Page Heading -->
        <div class="page-heading">
          <div>
            <h1>Job Cards</h1>
            <p>Manage and track active repair orders connected to MySQL</p>
          </div>

          <div class="heading-actions">
            <button class="btn-primary" id="btn-create-job">+ Create Job Card</button>
          </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stats">
          <div class="stat-card">
            <span class="label">TOTAL ACTIVE</span>
            <strong><?= $activeCount ?></strong>
            <span class="trend pos">↗ Live</span>
          </div>

          <div class="stat-card">
            <span class="label">AWAITING PARTS</span>
            <strong><?= $awaitingPartsCount ?></strong>
            <span class="trend neg">Part Requests</span>
          </div>

          <div class="stat-card">
            <span class="label">QUALITY CONTROL</span>
            <strong><?= $qcCount ?></strong>
            <span class="trend">Testing Stage</span>
          </div>

          <div class="stat-card">
            <span class="label">COMPLETED</span>
            <strong><?= $completedCount ?></strong>
            <span class="trend pos">Ready</span>
          </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
          <div class="filter-left">
            <select class="filter-select" id="select-status-filter">
              <option value="all">All Status</option>
              <option value="In-Progress">In-Progress</option>
              <option value="Awaiting-Parts">Awaiting-Parts</option>
              <option value="Quality-Control">Quality-Control</option>
            </select>
          </div>
        </div>

        <!-- JOB CARDS TABLE -->
        <div class="table-card">
          <table class="job-table">
            <thead>
              <tr>
                <th>JOB ID</th>
                <th>CUSTOMER &amp; VEHICLE</th>
                <th>DATE OPENED</th>
                <th>ASSIGNED MECHANIC</th>
                <th>PROGRESS</th>
                <th>EST. DELIVERY</th>
                <th>ACTION</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($jobCards)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No job cards found</td></tr>
              <?php else: ?>
                <?php foreach ($jobCards as $card): 
                  $status = $card['status'];
                  $pct = (int)$card['progress_percentage'];
                  $statusClass = 'in-progress';
                  $progressColor = 'blue-progress';

                  if ($status === 'Awaiting Parts' || $card['kanban_stage'] === 'PENDING') {
                    $statusClass = 'awaiting';
                    $progressColor = 'red-progress';
                  } elseif ($status === 'Testing' || $pct >= 85) {
                    $statusClass = 'quality';
                    $progressColor = 'orange-progress';
                  } elseif ($card['kanban_stage'] === 'COMPLETED' || $status === 'Ready' || $status === 'Completed') {
                    $statusClass = 'in-progress';
                    $progressColor = 'green-progress';
                  }
                ?>
                <tr data-card-id="<?= $card['id'] ?>">
                  <td><span class="job-id"><?= htmlspecialchars($card['code']) ?></span></td>
                  <td>
                    <div class="customer">
                      <strong><?= htmlspecialchars($card['customer_name']) ?></strong>
                      <span><?= htmlspecialchars($card['vehicle_details'] ?? 'Vehicle') ?></span>
                    </div>
                  </td>
                  <td><span class="date"><?= htmlspecialchars($card['date_opened']) ?></span></td>
                  <td>
                    <div class="mechanic">
                      <div class="mechanic-avatar initials" style="background: #e2e8f0; color: #334155; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%;">
                        <?= htmlspecialchars($card['mechanic_initials']) ?>
                      </div>
                      <span><?= htmlspecialchars($card['mechanic_name']) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="progress-info">
                      <div class="progress-top">
                        <span class="status <?= $statusClass ?>"><?= htmlspecialchars($card['status']) ?></span>
                        <span class="percentage"><?= $pct ?>%</span>
                      </div>
                      <div class="progress-bar">
                        <div class="progress-fill <?= $progressColor ?>" style="width:<?= $pct ?>%;"></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="delivery"><?= htmlspecialchars($card['delivery_date']) ?></span></td>
                  <td class="action-cell">
                    <button class="action-btn" title="View Options" onclick="openJobCardMenu(event, <?= $card['id'] ?>, this)">
                      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <div class="table-footer">
            <span>Showing 1-<?= count($jobCards) ?> of <?= count($jobCards) ?> job cards</span>
            <div class="pagination">
              <button class="page-btn" disabled>&lt;</button>
              <button class="page-btn active">1</button>
              <button class="page-btn" disabled>&gt;</button>
            </div>
          </div>
        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-jobCards.js"></script>
</body>
</html>
