<?php
require_once __DIR__ . '/../../api/db.php';

$stmt = $pdo->query("
    SELECT j.job_id as id,
           COALESCE(j.code, CONCAT('JC-', j.job_id)) as code,
           COALESCE(j.work_order, CONCAT('#WO-', 2000 + j.job_id)) as work_order,
           COALESCE(u.name, 'Customer') as customer_name,
           CONCAT(v.make, ' ', v.model, ' • ', v.license_plate) as vehicle_details,
           CONCAT(v.make, ' ', v.model) as vehicle_title,
           COALESCE(v.vin, 'VIN-PENDING') as vin,
           COALESCE(j.service_text, j.fault_report, 'General Service') as service_text,
           COALESCE(a.priority, 'Normal') as priority,
           j.status,
           COALESCE(j.kanban_stage, 'PENDING') as kanban_stage,
           COALESCE(j.progress_percentage, 10) as progress_percentage,
           j.mechanic_id,
           COALESCE(m.name, 'Unassigned') as mechanic_name,
           COALESCE(
               CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
               'UA'
           ) as mechanic_initials,
           DATE_FORMAT(j.created_at, '%b %d, %Y') as date_opened,
           COALESCE(j.delivery_date, 'TBD') as delivery_date
    FROM JobCards j
    LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
    LEFT JOIN Users u ON a.owner_id = u.user_id
    LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
    LEFT JOIN Users m ON j.mechanic_id = m.user_id
    ORDER BY j.job_id DESC
");
$allCards = $stmt->fetchAll();

$pendingCards = [];
$inProgressCards = [];
$completedCards = [];

foreach ($allCards as $card) {
    if ($card['kanban_stage'] === 'COMPLETED' || $card['status'] === 'Ready' || $card['status'] === 'Completed') {
        $completedCards[] = $card;
    } elseif ($card['kanban_stage'] === 'IN PROGRESS' || $card['status'] === 'Repairing' || $card['status'] === 'Testing') {
        $inProgressCards[] = $card;
    } else {
        $pendingCards[] = $card;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Job Progress Tracking</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-process-tracker.css">
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

        <a class="nav-item active" href="manager-process-tracker.php">
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

        <!-- Page Header -->
        <div class="page-heading">
          <div>
            <h1>Job Tracking</h1>
            <p>Live Kanban drag-and-drop repair progress tracking connected to MySQL</p>
          </div>
        </div>

        <!-- ================= KANBAN BOARD ================= -->
        <div class="kanban-board">

          <!-- 1. PENDING -->
          <div class="kanban-column" data-stage="PENDING">
            <div class="column-header">
              <div class="column-title">
                <span class="status-dot dot-pending"></span>
                <span>PENDING</span>
              </div>
              <span class="column-count"><?= count($pendingCards) ?></span>
            </div>

            <div class="column-body">
              <?php foreach ($pendingCards as $c): ?>
              <div class="job-card" draggable="true" data-card-id="<?= $c['id'] ?>">
                <div class="card-header">
                  <span class="job-id"><?= htmlspecialchars($c['work_order']) ?></span>
                  <span class="badge badge-standard"><?= htmlspecialchars($c['priority']) ?></span>
                </div>
                <h4 class="vehicle-title"><?= htmlspecialchars($c['vehicle_title'] ?? 'Vehicle') ?></h4>
                <p class="service-desc"><?= htmlspecialchars($c['service_text']) ?></p>
                <div class="card-footer">
                  <div class="assignee">
                    <span><?= htmlspecialchars($c['mechanic_name']) ?></span>
                  </div>
                  <span class="time-status"><?= htmlspecialchars($c['delivery_date']) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 2. IN PROGRESS -->
          <div class="kanban-column" data-stage="IN PROGRESS">
            <div class="column-header">
              <div class="column-title">
                <span class="status-dot dot-progress"></span>
                <span>IN PROGRESS</span>
              </div>
              <span class="column-count"><?= count($inProgressCards) ?></span>
            </div>

            <div class="column-body">
              <?php foreach ($inProgressCards as $c): ?>
              <div class="job-card" draggable="true" data-card-id="<?= $c['id'] ?>">
                <div class="card-header">
                  <span class="job-id"><?= htmlspecialchars($c['work_order']) ?></span>
                  <span class="badge badge-high"><?= htmlspecialchars($c['priority']) ?></span>
                </div>
                <h4 class="vehicle-title"><?= htmlspecialchars($c['vehicle_title'] ?? 'Vehicle') ?></h4>
                <p class="service-desc"><?= htmlspecialchars($c['service_text']) ?></p>
                <div class="progress-section" style="margin: 12px 0;">
                  <div class="progress-info" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                    <span>Progress</span>
                    <span style="font-weight: 700;"><?= $c['progress_percentage'] ?>%</span>
                  </div>
                  <div class="progress-bar" style="background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
                    <div class="progress-fill" style="width: <?= $c['progress_percentage'] ?>%; background: #3b82f6; height: 100%;"></div>
                  </div>
                </div>
                <div class="card-footer">
                  <div class="assignee">
                    <span><?= htmlspecialchars($c['mechanic_name']) ?></span>
                  </div>
                  <span class="time-status"><?= htmlspecialchars($c['delivery_date']) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 3. COMPLETED -->
          <div class="kanban-column" data-stage="COMPLETED">
            <div class="column-header">
              <div class="column-title">
                <span class="status-dot dot-completed"></span>
                <span>COMPLETED</span>
              </div>
              <span class="column-count"><?= count($completedCards) ?></span>
            </div>

            <div class="column-body">
              <?php foreach ($completedCards as $c): ?>
              <div class="job-card" draggable="true" data-card-id="<?= $c['id'] ?>">
                <div class="card-header">
                  <span class="job-id"><?= htmlspecialchars($c['work_order']) ?></span>
                  <span class="badge badge-standard">Completed</span>
                </div>
                <h4 class="vehicle-title"><?= htmlspecialchars($c['vehicle_title'] ?? 'Vehicle') ?></h4>
                <p class="service-desc"><?= htmlspecialchars($c['service_text']) ?></p>
                <div class="card-footer">
                  <div class="assignee">
                    <span><?= htmlspecialchars($c['mechanic_name']) ?></span>
                  </div>
                  <span class="time-status" style="color: #10b981; font-weight: 600;">Ready</span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-process-tracker.js"></script>
</body>
</html>
