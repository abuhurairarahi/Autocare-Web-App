<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$categories = $pdo->query("SELECT id, name FROM ServiceCategories ORDER BY name")->fetchAll();

// Filters come from the GET form; unknown values are ignored
$filters = ['status' => 'open'];
$statusFilter = $_GET['status'] ?? 'open';
if ($statusFilter === 'all') {
    unset($filters['status']);
} elseif (in_array($statusFilter, ['open', 'done'], true)) {
    $filters['status'] = $statusFilter;
} else {
    $statusFilter = 'open';
}
$priorityFilter = in_array($_GET['priority'] ?? '', ['Low', 'Normal', 'High'], true) ? $_GET['priority'] : '';
if ($priorityFilter) {
    $filters['priority'] = $priorityFilter;
}
$categoryFilter = input_id($_GET['category_id'] ?? null);
if ($categoryFilter) {
    $filters['category_id'] = $categoryFilter;
}
$sortFilter = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'priority'], true) ? $_GET['sort'] : 'newest';
$filters['sort'] = $sortFilter;

$jobs = mechanic_jobs($pdo, $mechanicId, $filters);
$perPage = 10;
$total = count($jobs);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$pageRows = array_slice($jobs, ($pageNo - 1) * $perPage, $perPage);

$query = array_filter([
    'status' => $statusFilter, 'priority' => $priorityFilter,
    'category_id' => $categoryFilter, 'sort' => $sortFilter,
]);

function job_page_url(int $n, array $query): string
{
    return 'mechanic-assigned-jobs.php?' . http_build_query($query + ['page' => $n]);
}

function priority_pill(string $priority): string
{
    return ['High' => 'pill--high', 'Low' => 'pill--low'][$priority] ?? 'pill--medium';
}

function status_pill(string $status): string
{
    if (in_array($status, ['In Progress', 'Repairing', 'Testing'], true)) return 'pill--progress';
    if ($status === 'Awaiting Parts') return 'pill--parts';
    return 'pill--ready';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Assigned Jobs</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-assigned-jobs.css">
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
                <a href="mechanic-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="mechanic-assigned-jobs.php" class="nav-item active"><i class="fa-solid fa-clipboard-list"></i> Assigned Jobs</a>
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

            <!-- Page content -->
            <section class="content">

                <!-- Page heading + filters -->
                <div class="page-head">
                    <div class="page-head__text">
                        <h1>Assigned Jobs</h1>
                        <p>Manage and track your current workshop queue.</p>
                    </div>
                    <form class="filters" method="get" action="mechanic-assigned-jobs.php">
                        <label class="filter-btn"><i class="fa-solid fa-list-check"></i>
                            <select name="status" aria-label="Status" style="border: none; background: transparent; font: inherit; color: inherit;">
                                <option value="open"<?= $statusFilter === 'open' ? ' selected' : '' ?>>Open jobs</option>
                                <option value="done"<?= $statusFilter === 'done' ? ' selected' : '' ?>>Completed</option>
                                <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All jobs</option>
                            </select>
                        </label>
                        <label class="filter-btn"><i class="fa-solid fa-filter"></i>
                            <select name="category_id" aria-label="Category" style="border: none; background: transparent; font: inherit; color: inherit;">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= $categoryFilter === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="filter-btn"><i class="fa-solid fa-exclamation"></i>
                            <select name="priority" aria-label="Priority" style="border: none; background: transparent; font: inherit; color: inherit;">
                                <option value="">Any priority</option>
                                <?php foreach (['High', 'Normal', 'Low'] as $p): ?>
                                <option value="<?= $p ?>"<?= $priorityFilter === $p ? ' selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="filter-btn"><i class="fa-solid fa-arrow-down-wide-short"></i>
                            <select name="sort" aria-label="Sort" style="border: none; background: transparent; font: inherit; color: inherit;">
                                <option value="newest"<?= $sortFilter === 'newest' ? ' selected' : '' ?>>Sort: Newest</option>
                                <option value="oldest"<?= $sortFilter === 'oldest' ? ' selected' : '' ?>>Sort: Oldest</option>
                                <option value="priority"<?= $sortFilter === 'priority' ? ' selected' : '' ?>>Sort: Priority</option>
                            </select>
                        </label>
                        <noscript><button type="submit" class="filter-btn">Apply</button></noscript>
                    </form>
                </div>

                <!-- Jobs table -->
                <div class="table-card">
                    <div class="table-scroll">
                        <table class="jobs">
                            <thead>
                                <tr>
                                    <th>Job ID</th>
                                    <th>Vehicle</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pageRows)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 24px; color: #757684;">No jobs match these filters.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pageRows as $job): ?>
                                <tr data-job-id="<?= (int) $job['id'] ?>">
                                    <td><span class="job-id">#<?= e($job['code']) ?></span></td>
                                    <td>
                                        <?php if ($job['vehicle']): ?>
                                        <div class="vehicle-plate"><?= e($job['license_plate']) ?></div>
                                        <div class="vehicle-model"><?= e($job['vehicle']) ?></div>
                                        <?php else: ?>
                                        <div class="vehicle-model">Walk-in (no booking linked)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($job['category_name'] ?: $job['service_text']) ?></td>
                                    <td><span class="pill <?= priority_pill($job['priority']) ?>"><?= e($job['priority']) ?></span></td>
                                    <td><span class="pill <?= status_pill($job['status']) ?>"><?= e($job['status']) ?></span></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <button type="button" class="btn btn--ghost" data-action="details">Details</button>
                                            <?php if (!$job['is_open']): ?>
                                            <a href="mechanic-workflow.php?job_id=<?= (int) $job['id'] ?>" class="btn btn--muted">View</a>
                                            <?php elseif (in_array($job['status'], ['Assigned', 'Diagnosis'], true)): ?>
                                            <a href="mechanic-workflow.php?job_id=<?= (int) $job['id'] ?>" class="btn btn--primary">Start</a>
                                            <?php else: ?>
                                            <a href="mechanic-workflow.php?job_id=<?= (int) $job['id'] ?>" class="btn btn--primary">Resume</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pager">
                        <span class="pager__info">
                            <?php if ($total): ?>
                            Showing <?= ($pageNo - 1) * $perPage + 1 ?> to <?= ($pageNo - 1) * $perPage + count($pageRows) ?> of <?= $total ?> entries
                            <?php else: ?>
                            Showing 0 entries
                            <?php endif; ?>
                        </span>
                        <div class="pager__controls">
                            <?php if ($pageNo > 1): ?>
                            <a class="pager__nav" href="<?= e(job_page_url($pageNo - 1, $query)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                            <?php else: ?>
                            <button class="pager__nav is-disabled" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                            <?php endif; ?>
                            <?php for ($n = 1; $n <= $pages; $n++): ?>
                            <?php if ($n === $pageNo): ?>
                            <button class="pager__btn is-active" aria-current="page"><?= $n ?></button>
                            <?php else: ?>
                            <a class="pager__btn" href="<?= e(job_page_url($n, $query)) ?>"><?= $n ?></a>
                            <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($pageNo < $pages): ?>
                            <a class="pager__nav" href="<?= e(job_page_url($pageNo + 1, $query)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                            <?php else: ?>
                            <button class="pager__nav is-disabled" disabled aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-assigned-jobs.js"></script>
</body>

</html>
