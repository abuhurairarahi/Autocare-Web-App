<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$dash = mechanic_dashboard($pdo, $mechanicId);

// Donut: conic-gradient segments for not started / in progress / testing
$segments = [
    ['#00175c', $dash['not_started']],
    ['#3e1000', $dash['in_progress']],
    ['#505f76', $dash['testing']],
];
$donutTotal = max(1, $dash['assigned_jobs']);
$stops = [];
$at = 0.0;
foreach ($segments as [$color, $n]) {
    $end = $at + $n / $donutTotal * 100;
    $stops[] = sprintf('%s %.2f%% %.2f%%', $color, $at, $end);
    $at = $end;
}
$donutStyle = $dash['assigned_jobs'] > 0
    ? 'border: none; background: radial-gradient(closest-side, #fff 78%, transparent 80%), conic-gradient(' . implode(', ', $stops) . ');'
    : 'border-color: #e2e8f0;';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/default-sidebar-topbar-style.css">
</head>

<body>
    <div class="app">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="brand">
                <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
                <span class="brand-name">
                    <span style="color: #ffffff;">Auto</span><span style="color: #f97316;">Care</span>
                </span>
            </div>

            <!-- Nav Bar -->
            <nav class="nav">
                <a href="mechanic-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="mechanic-assigned-jobs.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i> Assigned Jobs</a>
                <a href="mechanic-workflow.php" class="nav-item"><i class="fa-solid fa-diagram-project"></i> Progress</a>
                <a href="mechanic-parts-labor.php" class="nav-item"><i class="fa-solid fa-toolbox"></i> Parts &amp; Labor</a>
                <a href="mechanic-repair-photos.php" class="nav-item"><i class="fa-solid fa-camera"></i> Update Photos</a>
                <a href="mechanic-fault-report.php" class="nav-item"><i class="fa-solid fa-triangle-exclamation"></i> Report Faults</a>
                <a href="mechanic-chat.php" class="nav-item"><i class="fa-solid fa-comments"></i> Chats</a>
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

            <!-- Dashboard content -->
            <section class="content">

                <!-- Page heading -->
                <div class="page-head">
                    <div>
                        <h1>Mechanic Dashboard</h1>
                        <p>Overview of your current workload and performance metrics.</p>
                    </div>
                </div>

                <!-- KPI cards - row 1 -->
                <div class="kpi-row">

                    <article class="kpi-card">
                        <div class="kpi-card__top">
                            <span class="kpi-icon indigo"><i class="fa-solid fa-briefcase"></i></span>
                            <span class="kpi-badge up"><i class="fa-solid fa-arrow-trend-up"></i> +2%</span>
                        </div>
                        <div class="kpi-card__body">
                            <p class="kpi-card__label">Assigned Jobs</p>
                            <p class="kpi-card__value"><?= $dash['assigned_jobs'] ?></p>
                        </div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-card__top">
                            <span class="kpi-icon coral"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                            <span class="kpi-badge down"><i class="fa-solid fa-arrow-trend-down"></i> -1%</span>
                        </div>
                        <div class="kpi-card__body">
                            <p class="kpi-card__label">Jobs In Progress</p>
                            <p class="kpi-card__value"><?= $dash['in_progress'] ?></p>
                        </div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-card__top">
                            <span class="kpi-icon slate"><i class="fa-solid fa-clipboard-check"></i></span>
                        </div>
                        <div class="kpi-card__body">
                            <p class="kpi-card__label">Ready For Testing</p>
                            <p class="kpi-card__value"><?= $dash['testing'] ?></p>
                        </div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-card__top">
                            <span class="kpi-icon teal"><i class="fa-solid fa-circle-check"></i></span>
                            <span class="kpi-badge pos"><i class="fa-solid fa-arrow-trend-up"></i> +15%</span>
                        </div>
                        <div class="kpi-card__body">
                            <p class="kpi-card__label">Completed Today</p>
                            <p class="kpi-card__value"><?= $dash['completed_today'] ?></p>
                        </div>
                    </article>

                </div>

                <!-- KPI cards - bento row -->
                <div class="bento-row">

                    <article class="bento-card bento-card--dark">
                        <span class="bento-card__glow"></span>
                        <div class="bento-card__head">
                            <p class="bento-card__label">Total Labor Hours</p>
                            <p class="bento-card__value"><?= e(rtrim(rtrim(number_format($dash['open_labor_hours'], 2), '0'), '.')) ?>h</p>
                        </div>
                        <div class="bento-card__foot">
                            <i class="fa-regular fa-clock"></i>
                            <span>Logged on open jobs</span>
                        </div>
                    </article>

                    <article class="bento-card bento-card--light">
                        <div class="bento-card__head">
                            <p class="bento-card__label">Parts Used Today</p>
                            <p class="bento-card__value"><?= $dash['parts_today'] ?></p>
                        </div>
                        <div class="bento-card__foot">
                            <i class="fa-solid fa-box-archive"></i>
                            <span>Across <?= $dash['parts_today_jobs'] ?> job<?= $dash['parts_today_jobs'] === 1 ? '' : 's' ?></span>
                        </div>
                    </article>

                    <article class="bento-card bento-card--light">
                        <div class="bento-card__head">
                            <p class="bento-card__label">Productivity Score</p>
                            <div class="score-row">
                                <p class="bento-card__value accent">94%</p>
                                <span class="score-note">Excellent</span>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress__fill"></div>
                        </div>
                    </article>

                </div>

                <!-- Charts -->
                <div class="charts-row">

                    <article class="chart-card chart-card--wide">
                        <div class="chart-card__head">
                            <h3 class="chart-card__title">Weekly Repair Performance</h3>
                            <button class="chart-menu"><i class="fa-solid fa-ellipsis"></i></button>
                        </div>
                        <div class="chart-placeholder">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Line/Area Chart Visualization</span>
                        </div>
                    </article>

                    <article class="chart-card chart-card--narrow">
                        <div class="chart-card__head">
                            <h3 class="chart-card__title">Job Status Distribution</h3>
                            <button class="chart-menu"><i class="fa-solid fa-ellipsis"></i></button>
                        </div>
                        <div class="donut-wrap">
                            <div class="donut" style="<?= e($donutStyle) ?>" role="img" aria-label="<?= $dash['assigned_jobs'] ?> open jobs"><span><?= $dash['assigned_jobs'] ?></span></div>
                        </div>
                        <div class="legend">
                            <div class="legend__row">
                                <span class="legend__left"><span class="legend__dot dot-assigned"></span><span class="legend__label">Not Started</span></span>
                                <span class="legend__value"><?= $dash['not_started'] ?></span>
                            </div>
                            <div class="legend__row">
                                <span class="legend__left"><span class="legend__dot dot-progress"></span><span class="legend__label">In Progress</span></span>
                                <span class="legend__value"><?= $dash['in_progress'] ?></span>
                            </div>
                            <div class="legend__row">
                                <span class="legend__left"><span class="legend__dot dot-testing"></span><span class="legend__label">Testing</span></span>
                                <span class="legend__value"><?= $dash['testing'] ?></span>
                            </div>
                        </div>
                    </article>

                </div>

            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
</body>

</html>
