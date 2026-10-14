<?php
require_once __DIR__ . '/../../api/db.php';

try {
    // 1. Fetch Invoices with details
    $sql = "
        SELECT i.invoice_id as id,
               COALESCE(i.invoice_number, CONCAT('INV-2026-', LPAD(i.invoice_id, 3, '0'))) as invoice_number,
               i.job_id as job_card_id,
               COALESCE(j.code, CONCAT('JC-', i.job_id)) as job_card_code,
               COALESCE(u.name, 'Customer') as customer_name,
               COALESCE(u.email, 'customer@example.com') as customer_email,
               COALESCE(u.phone, 'N/A') as customer_phone,
               COALESCE(CONCAT(v.make, ' ', v.model), 'Vehicle') as vehicle_name,
               COALESCE(v.vin, 'VIN-PENDING') as vin,
               DATE_FORMAT(i.issued_date, '%b %d, %Y') as date,
               i.total_amount,
               i.status,
               i.paid_date,
               i.pdf_url
        FROM Invoices i
        LEFT JOIN JobCards j ON i.job_id = j.job_id
        LEFT JOIN Users u ON i.customer_id = u.user_id
        LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
        LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
        ORDER BY i.invoice_id DESC
    ";
    $invoices = $pdo->query($sql)->fetchAll();

    // 2. Summary stats
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Paid'");
    $totalRevenue = (float) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Pending'");
    $pendingStats = $stmt->fetch(PDO::FETCH_NUM);
    $pendingCount = (int) $pendingStats[0];
    $pendingValue = (float) $pendingStats[1];

    $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Overdue'");
    $overdueStats = $stmt->fetch(PDO::FETCH_NUM);
    $overdueCount = (int) $overdueStats[0];
    $overdueValue = (float) $overdueStats[1];

} catch (Exception $e) {
    $invoices = [];
    $totalRevenue = 0;
    $pendingCount = 0;
    $pendingValue = 0;
    $overdueCount = 0;
    $overdueValue = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Invoice Management</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-invoice-management.css">
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

        <a class="nav-item active" href="manager-invoice-management.php">
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

        <!-- Page Heading + Top Actions -->
        <div class="page-heading">
          <div>
            <h1>Invoice Management</h1>
            <p>Manage billing, track payments, and generate invoices.</p>
          </div>

          <div class="heading-actions">
            <button class="btn-secondary" id="btn-export-invoices">
              <svg viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              Export List
            </button>

            <button class="btn-primary" id="btn-new-invoice-trigger">
              <span>+</span> New Invoice
            </button>
          </div>
        </div>

        <!-- ================= STATS CARDS ================= -->
        <div class="stats-grid">

          <!-- Card 1 -->
          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-title">TOTAL REVENUE (MTD)</span>
              <div class="stat-icon icon-blue">
                <svg viewBox="0 0 24 24">
                  <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                  <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
              </div>
            </div>
            <div class="stat-value">৳<?= number_format($totalRevenue) ?></div>
            <div class="stat-trend trend-up">
              <svg viewBox="0 0 24 24">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                <polyline points="17 6 23 6 23 12"></polyline>
              </svg>
              <span>Database real-time</span>
            </div>
          </div>

          <!-- Card 2 -->
          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-title">PENDING INVOICES</span>
              <div class="stat-icon icon-orange">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
              </div>
            </div>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-subtext">Value: ৳<?= number_format($pendingValue) ?></div>
          </div>

          <!-- Card 3 -->
          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-title title-red">OVERDUE</span>
              <div class="stat-icon icon-red">
                <svg viewBox="0 0 24 24">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z">
                  </path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
              </div>
            </div>
            <div class="stat-value text-red"><?= $overdueCount ?></div>
            <div class="stat-subtext text-red-sub">Value: ৳<?= number_format($overdueValue) ?></div>
          </div>

        </div>

        <!-- ================= INVOICE TABLE CARD ================= -->
        <div class="table-card">

          <div class="table-toolbar">
            <div class="filter-dropdown" id="invoice-filter-toggle">
              <svg class="filter-icon" viewBox="0 0 24 24">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
              </svg>
              <span id="invoice-filter-label">All Statuses</span>
              <svg class="chevron-icon" viewBox="0 0 24 24">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <span class="showing-text">Showing 1-<?= count($invoices) ?> of <?= count($invoices) ?></span>
          </div>

          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>INVOICE #</th>
                  <th>CUSTOMER</th>
                  <th>VEHICLE</th>
                  <th>DATE</th>
                  <th>TOTAL</th>
                  <th>STATUS</th>
                  <th class="actions-th">ACTIONS</th>
                </tr>
              </thead>
              <tbody>

                <?php if (empty($invoices)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No invoices found in database</td>
                </tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv): 
                  $badgeClass = 'badge-pending';
                  if ($inv['status'] === 'Paid') $badgeClass = 'badge-paid';
                  elseif ($inv['status'] === 'Overdue') $badgeClass = 'badge-overdue';
                ?>
                <tr data-invoice-id="<?= $inv['id'] ?>">
                  <td class="bold">
                    <a href="manager-jobCards.php?id=<?= $inv['job_card_id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 700;" title="View Job Card">
                      <?= htmlspecialchars($inv['invoice_number']) ?>
                    </a>
                  </td>
                  <td>
                    <div class="customer-name"><?= htmlspecialchars($inv['customer_name']) ?></div>
                    <div class="sub-email"><?= htmlspecialchars($inv['customer_email']) ?></div>
                  </td>
                  <td>
                    <div class="vehicle-name"><?= htmlspecialchars($inv['vehicle_name']) ?></div>
                    <div class="sub-vin">VIN: <?= htmlspecialchars($inv['vin']) ?></div>
                  </td>
                  <td><?= htmlspecialchars($inv['date']) ?></td>
                  <td class="bold">৳<?= number_format($inv['total_amount']) ?></td>
                  <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                  <td class="action-cell">
                    <button class="icon-btn" title="Actions" onclick="openInvoiceMenu(event, <?= $inv['id'] ?>)">
                      <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination">
            <button class="btn-page disabled">Previous</button>
            <button class="btn-page">Next</button>
          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-invoice-management.js"></script>
</body>

</html>
