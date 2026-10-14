<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$vehicles = owner_vehicles($pdo, $ownerId);

// Filters come from the GET form; invalid values are ignored
$filters = [];
$vehicleFilter = input_id($_GET['vehicle_id'] ?? null);
if ($vehicleFilter) {
    $filters['vehicle_id'] = $vehicleFilter;
}
$dateFilter = (string) ($_GET['date'] ?? '');
$d = DateTime::createFromFormat('!Y-m-d', $dateFilter);
if ($d && $d->format('Y-m-d') === $dateFilter) {
    $filters['date'] = $dateFilter;
} else {
    $dateFilter = '';
}

$invoices = owner_invoices($pdo, $ownerId, $filters);
$stats = owner_invoice_stats($pdo, $ownerId);

$perPage = 10;
$total = count($invoices);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$pageRows = array_slice($invoices, ($pageNo - 1) * $perPage, $perPage);

function invoice_status_class(string $status): string
{
    if ($status === 'Paid') return 'paid';
    if (in_array($status, ['Pending Approval', 'Refunded'], true)) return 'processing';
    return 'pending';
}

function invoice_page_url(int $n, array $filters): string
{
    return 'vehicleowner-invoices.php?' . http_build_query($filters + ['page' => $n]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Invoice Management</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-invoices.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/vehicleowner-sidebar-topbar-style.css">
    <style media="print">
        /* Printing an invoice prints only the open invoice dialog */
        body > *:not(#autocare-modal-overlay) { display: none !important; }
        #autocare-modal-overlay { position: static !important; background: none !important; }
        #autocare-modal-overlay button { display: none !important; }
    </style>
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
                <a href="vehicleowner-service-history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
                <a href="vehicleowner-invoices.php" class="nav-item active"><i class="fa-solid fa-receipt"></i> Invoices</a>
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
                        <div class="vo-header__text">
                            <h1>Invoice Management</h1>
                            <p>Review and manage your billing history and outstanding payments.</p>
                        </div>
                        <form class="vo-filters" method="get" action="vehicleowner-invoices.php">
                            <div class="vo-select-wrap">
                                <select name="vehicle_id" aria-label="Filter by vehicle">
                                    <option value="">All Vehicles</option>
                                    <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= (int) $v['id'] ?>"<?= $vehicleFilter === $v['id'] ? ' selected' : '' ?>><?= e($v['make'] . ' ' . $v['model']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-chevron-down vo-select-chevron"></i>
                            </div>
                            <input class="vo-date-input" type="date" name="date" value="<?= e($dateFilter) ?>" aria-label="Filter by invoice date">
                            <button type="submit" class="vo-btn vo-btn--primary"><i class="fa-solid fa-filter"></i> Filter</button>
                            <?php if ($filters): ?>
                            <a href="vehicleowner-invoices.php" class="vo-btn" style="text-decoration: none;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- KPI cards -->
                    <div class="vo-kpis">
                        <div class="vo-kpi">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--paid"><i class="fa-solid fa-circle-check"></i></span>
                                <span class="vo-kpi__pill vo-kpi__pill--blue"><?= $stats['paid_this_month'] ?> this month</span>
                            </div>
                            <p class="vo-kpi__label">Paid Invoices</p>
                            <p class="vo-kpi__value"><?= $stats['paid_count'] ?></p>
                        </div>
                        <div class="vo-kpi">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--pending"><i class="fa-solid fa-hourglass-half"></i></span>
                                <span class="vo-kpi__pill vo-kpi__pill--gray"><?= $stats['overdue_count'] ?> Overdue</span>
                            </div>
                            <p class="vo-kpi__label">Pending Payments</p>
                            <p class="vo-kpi__value"><?= $stats['pending_count'] ?></p>
                        </div>
                        <div class="vo-kpi">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--total"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                            </div>
                            <p class="vo-kpi__label">Total Expenditure (YTD)</p>
                            <p class="vo-kpi__value"><?= e(money($stats['spend_ytd'])) ?></p>
                        </div>
                    </div>

                    <!-- Invoices table -->
                    <div class="vo-table-card">
                        <div class="vo-table-wrap">
                            <table class="vo-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Vehicle</th>
                                        <th>Service Date</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th class="vo-num">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pageRows)): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #757684; padding: 24px;"><?= $filters ? 'No invoices match these filters.' : 'You have no invoices yet.' ?></td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($pageRows as $inv):
                                        $label = $inv['invoice_number'];
                                    ?>
                                    <tr data-invoice-id="<?= (int) $inv['id'] ?>">
                                        <td><a href="#" class="vo-invoice-link" data-action="view"><?= e($label) ?></a></td>
                                        <td><?= e($inv['make'] ? $inv['make'] . ' ' . $inv['model'] : '—') ?></td>
                                        <td><?= e(date('M d, Y', strtotime($inv['issued_date']))) ?></td>
                                        <td class="vo-cell-amount"><?= e(money($inv['total_amount'])) ?></td>
                                        <td><span class="vo-status vo-status--<?= invoice_status_class($inv['status']) ?>"><span class="vo-status__dot"></span> <?= e($inv['status']) ?></span></td>
                                        <td class="vo-num">
                                            <div class="vo-row-actions">
                                                <button type="button" class="vo-icon-btn" data-action="view" aria-label="View invoice <?= e($label) ?>"><i class="fa-solid fa-eye"></i></button>
                                                <?php $pdf = safe_image_url($inv['pdf_url']); ?>
                                                <?php if ($pdf): ?>
                                                <a class="vo-icon-btn" href="<?= e($pdf) ?>" target="_blank" rel="noopener" aria-label="Download invoice <?= e($label) ?>"><i class="fa-solid fa-download"></i></a>
                                                <?php else: ?>
                                                <button type="button" class="vo-icon-btn" disabled title="No PDF has been issued for this invoice" aria-label="No PDF for invoice <?= e($label) ?>" style="opacity: 0.4; cursor: not-allowed;"><i class="fa-solid fa-download"></i></button>
                                                <?php endif; ?>
                                                <button type="button" class="vo-icon-btn" data-action="print" aria-label="Print invoice <?= e($label) ?>"><i class="fa-solid fa-print"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="vo-table-card__footer">
                            <p class="vo-showing">
                                <?php if ($total): ?>
                                Showing <?= ($pageNo - 1) * $perPage + 1 ?> to <?= ($pageNo - 1) * $perPage + count($pageRows) ?> of <?= $total ?> entries
                                <?php else: ?>
                                Showing 0 entries
                                <?php endif; ?>
                            </p>
                            <nav class="vo-pagination" aria-label="Invoice pages">
                                <?php if ($pageNo > 1): ?>
                                <a class="vo-page-btn vo-page-btn--nav" href="<?= e(invoice_page_url($pageNo - 1, $filters)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                                <?php else: ?>
                                <button type="button" class="vo-page-btn vo-page-btn--nav" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                                <?php endif; ?>
                                <?php for ($n = 1; $n <= $pages; $n++): ?>
                                <?php if ($n === $pageNo): ?>
                                <button type="button" class="vo-page-btn vo-page-btn--active" aria-current="page"><?= $n ?></button>
                                <?php else: ?>
                                <a class="vo-page-btn" href="<?= e(invoice_page_url($n, $filters)) ?>"><?= $n ?></a>
                                <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($pageNo < $pages): ?>
                                <a class="vo-page-btn vo-page-btn--nav" href="<?= e(invoice_page_url($pageNo + 1, $filters)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
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
    <script src="../../assets/js/vehicleowner/vehicleowner-invoices.js"></script>
</body>

</html>
