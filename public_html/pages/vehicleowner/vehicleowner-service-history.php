<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$vehicles = owner_vehicles($pdo, $ownerId);
$vehicleFilter = input_id($_GET['vehicle_id'] ?? null);
$history = owner_service_history($pdo, $ownerId, $vehicleFilter);
$records = $history['records'];
$last = $history['last_service'];

$perPage = 10;
$total = count($records);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$pageRows = array_slice($records, ($pageNo - 1) * $perPage, $perPage);

function history_page_url(int $n, ?int $vehicleId): string
{
    return 'vehicleowner-service-history.php?' . http_build_query(array_filter(['vehicle_id' => $vehicleId, 'page' => $n]));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Service History</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-service-history.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/vehicleowner-sidebar-topbar-style.css">
</head>

<body>
    <div class="app">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-brand">
                <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
                <span class="brand-name"><span style="color: #00175c;">Auto</span><span
                        style="color: #fc4204;">Care</span></span>
            </div>

            <!-- Nav Bar -->
            <nav class="nav">
                <a href="vehicleowner-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="vehicleowner-vehicles.php" class="nav-item"><i class="fa-solid fa-car"></i> My Vehicles</a>
                <a href="vehicleowner-book-appointment.php" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Service Appointments</a>
                <a href="vehicleowner-repair-estimates.php" class="nav-item"><i class="fa-solid fa-file-invoice-dollar"></i> Repair Estimates</a>
                <a href="vehicleowner-repair-tracking.php" class="nav-item"><i class="fa-solid fa-wrench"></i> Live Repair Tracking</a>
                <a href="vehicleowner-service-history.php" class="nav-item active"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
                <a href="vehicleowner-invoices.php" class="nav-item"><i class="fa-solid fa-receipt"></i> Invoices</a>
                <a href="vehicleowner-chat.php" class="nav-item"><i class="fa-solid fa-comment"></i> Chat</a>
                <a href="vehicleowner-notifications.php" class="nav-item"><i class="fa-solid fa-bell"></i> Notifications</a>
            </nav>

            <a class="logout" href="../logout.php">
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
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                            </path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>

                    <div class="avatar" title="<?= e($user['name']) ?>"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                </div>
            </header>

            <!-- Page content -->
            <section class="content">
                <div class="vo-wrap">

                    <!-- Page header -->
                    <div class="vo-header">
                        <h1>Service History</h1>
                        <p>Review your past maintenance and repair records across all vehicles.</p>
                    </div>

                    <!-- Summary cards -->
                    <div class="vo-summary-cards">
                        <div class="vo-summary-card vo-summary-card--navy">
                            <div class="vo-summary-card__top">
                                <p class="vo-summary-card__label">Total Services</p>
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <p class="vo-summary-card__value"><?= $history['total_services'] ?> <span>Records</span></p>
                        </div>
                        <div class="vo-summary-card vo-summary-card--gray">
                            <div class="vo-summary-card__top">
                                <p class="vo-summary-card__label">Last Service Date</p>
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                            <?php if ($last): ?>
                            <p class="vo-summary-card__date"><?= e(date('M d, Y', strtotime($last['service_date']))) ?></p>
                            <p class="vo-summary-card__sub"><?= e($last['year'] . ' ' . $last['make'] . ' ' . $last['model']) ?></p>
                            <?php else: ?>
                            <p class="vo-summary-card__date">&mdash;</p>
                            <?php endif; ?>
                        </div>
                        <div class="vo-summary-card vo-summary-card--amber">
                            <div class="vo-summary-card__top">
                                <p class="vo-summary-card__label">Total Spend (YTD)</p>
                                <i class="fa-solid fa-sack-dollar"></i>
                            </div>
                            <p class="vo-summary-card__value"><?= e(money($history['spend_ytd'])) ?></p>
                        </div>
                    </div>

                    <!-- Detailed records table -->
                    <div class="vo-table-card">
                        <div class="vo-table-card__head">
                            <h2>Detailed Records</h2>
                            <div class="vo-table-card__actions">
                                <form method="get" action="vehicleowner-service-history.php" id="vo-history-filter" style="display: inline-flex;">
                                    <label class="vo-btn-outline" style="cursor: pointer;"><i class="fa-solid fa-filter"></i>
                                        <select name="vehicle_id" aria-label="Filter by vehicle" style="border: none; background: transparent; font: inherit; color: inherit; cursor: pointer;">
                                            <option value="">All vehicles</option>
                                            <?php foreach ($vehicles as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>"<?= $vehicleFilter === $v['id'] ? ' selected' : '' ?>><?= e($v['make'] . ' ' . $v['model']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <noscript><button type="submit" class="vo-btn-outline">Apply</button></noscript>
                                </form>
                                <button type="button" class="vo-btn vo-btn--primary" id="vo-history-export"<?= $records ? '' : ' disabled' ?>><i class="fa-solid fa-download"></i> Export</button>
                            </div>
                        </div>

                        <div class="vo-table-wrap">
                            <table class="vo-history-table">
                                <thead>
                                    <tr>
                                        <th>Service Date</th>
                                        <th>Vehicle</th>
                                        <th>Workshop</th>
                                        <th>Category</th>
                                        <th>Mechanic</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th class="vo-num">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pageRows)): ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 24px; color: #757684;">No completed services yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($pageRows as $r): ?>
                                    <tr data-job-id="<?= (int) $r['id'] ?>">
                                        <td>
                                            <p class="vo-cell-primary"><?= e(date('M d, Y', strtotime($r['service_date']))) ?></p>
                                            <p class="vo-cell-secondary"><?= e(date('g:i A', strtotime($r['service_date']))) ?></p>
                                        </td>
                                        <td>
                                            <div class="vo-vehicle-cell">
                                                <!-- No vehicle photo column: neutral placeholder -->
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; background: #eeedf6; color: #757684;"><i class="fa-solid fa-car-side" aria-hidden="true"></i></span>
                                                <div>
                                                    <p class="vo-cell-primary vo-cell-primary--tight"><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></p>
                                                    <p class="vo-cell-secondary"><?= e($r['license_plate']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="vo-cell-body"><?= e($r['workshop_name'] ?: '—') ?></td>
                                        <td><span class="vo-pill"><i class="fa-solid fa-tag"></i> <?= e($r['category_name'] ?: $r['service_text']) ?></span></td>
                                        <td>
                                            <?php if ($r['mechanic_name']): ?>
                                            <div class="vo-mechanic-cell">
                                                <span class="vo-mechanic-avatar"><?= e(initials($r['mechanic_name'])) ?></span>
                                                <span class="vo-cell-body"><?= e($r['mechanic_name']) ?></span>
                                            </div>
                                            <?php else: ?>
                                            <span class="vo-cell-body">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="vo-cell-primary"><?= e(money($r['cost'])) ?></td>
                                        <td><span class="vo-status vo-status--completed"><?= e($r['status']) ?></span></td>
                                        <td class="vo-num">
                                            <div class="vo-row-actions">
                                                <a class="vo-icon-btn" href="vehicleowner-repair-tracking.php?job_id=<?= (int) $r['id'] ?>" aria-label="View record details"><i class="fa-solid fa-eye"></i></a>
                                                <?php if ($r['invoice_id']): ?>
                                                <a class="vo-icon-btn" href="vehicleowner-invoices.php?<?= e(http_build_query(['vehicle_id' => $r['vehicle_id']])) ?>" aria-label="View invoice <?= e($r['invoice_number']) ?>" title="<?= e($r['invoice_number']) ?>"><i class="fa-solid fa-receipt"></i></a>
                                                <?php else: ?>
                                                <button type="button" class="vo-icon-btn" disabled title="No invoice issued" aria-label="No invoice issued" style="opacity: 0.4;"><i class="fa-solid fa-receipt"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="vo-table-card__footer">
                            <p class="vo-showing"><?= $total ? 'Showing ' . (($pageNo - 1) * $perPage + 1) . '-' . (($pageNo - 1) * $perPage + count($pageRows)) . ' of ' . $total . ' records' : 'Showing 0 records' ?></p>
                            <nav class="vo-pagination" aria-label="Service history pages">
                                <?php if ($pageNo > 1): ?>
                                <a class="vo-page-btn vo-page-btn--nav" href="<?= e(history_page_url($pageNo - 1, $vehicleFilter)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                                <?php else: ?>
                                <button type="button" class="vo-page-btn vo-page-btn--nav" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                                <?php endif; ?>
                                <?php for ($n = 1; $n <= $pages; $n++): ?>
                                <?php if ($n === $pageNo): ?>
                                <button type="button" class="vo-page-btn vo-page-btn--active" aria-current="page"><?= $n ?></button>
                                <?php else: ?>
                                <a class="vo-page-btn" href="<?= e(history_page_url($n, $vehicleFilter)) ?>"><?= $n ?></a>
                                <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($pageNo < $pages): ?>
                                <a class="vo-page-btn vo-page-btn--nav" href="<?= e(history_page_url($pageNo + 1, $vehicleFilter)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                                <?php else: ?>
                                <button type="button" class="vo-page-btn vo-page-btn--nav" disabled aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-service-history.js"></script>
</body>

</html>
