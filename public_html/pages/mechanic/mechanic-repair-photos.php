<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$openJobs = mechanic_jobs($pdo, $mechanicId, ['status' => 'open']);
$requestedId = input_id($_GET['job_id'] ?? null) ?: ($openJobs[0]['id'] ?? null);
$job = $requestedId ? mechanic_job($pdo, $mechanicId, $requestedId) : null;
$photos = $job ? job_photos($pdo, $job['id']) : [];
$canEdit = $job && $job['is_open'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Repair Photos</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-repair-photos.css">
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
                <a href="mechanic-assigned-jobs.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i> Assigned Jobs</a>
                <a href="mechanic-workflow.php" class="nav-item"><i class="fa-solid fa-diagram-project"></i> Progress</a>
                <a href="mechanic-parts-labor.php" class="nav-item"><i class="fa-solid fa-toolbox"></i> Parts &amp; Labor</a>
                <a href="mechanic-repair-photos.php" class="nav-item active"><i class="fa-solid fa-camera"></i> Update Photos</a>
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
                <div class="rp-wrap" data-job-id="<?= $job ? (int) $job['id'] : '' ?>">

                    <!-- Page header -->
                    <div class="rp-header">
                        <div class="rp-header__text">
                            <h1>Vehicle Repair Gallery</h1>
                            <p><?= $job ? 'Job #' . e($job['code']) . ' &bull; ' . e($job['vehicle'] ?: $job['service_text']) : 'No job selected' ?></p>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <?php if (count($openJobs) > 1 || ($job && !$job['is_open'])): ?>
                            <select id="rp-job-switch" aria-label="Choose job" style="padding: 8px 10px; border-radius: 6px; border: 1px solid #c5c5d4; font: inherit;">
                                <?php if ($job && !$job['is_open']): ?>
                                <option value="<?= (int) $job['id'] ?>" selected><?= e($job['code']) ?> (<?= e($job['status']) ?>)</option>
                                <?php endif; ?>
                                <?php foreach ($openJobs as $oj): ?>
                                <option value="<?= (int) $oj['id'] ?>"<?= $job && $oj['id'] === $job['id'] ? ' selected' : '' ?>><?= e($oj['code'] . ' · ' . ($oj['vehicle'] ?: $oj['service_text'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <button type="button" class="rp-btn rp-btn--primary" data-action="upload"><i class="fa-solid fa-upload"></i> Upload New</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$job): ?>
                    <p><?= isset($_GET['job_id']) ? 'This job does not exist or is not assigned to you.' : 'You have no open jobs.' ?> <a href="mechanic-assigned-jobs.php">Back to Assigned Jobs</a></p>
                    <?php else: ?>
                    <!-- Gallery grid. All JobCardPhotos rows for the job share one gallery. -->
                    <div class="rp-grid">

                        <section class="rp-col">
                            <h2 class="rp-col__title"><span class="rp-dot rp-dot--before"></span> Photos (<?= count($photos) ?>)</h2>

                            <div class="rp-cards">
                                <?php if (empty($photos)): ?>
                                <p style="color: #757684;">No photos uploaded for this job yet.</p>
                                <?php endif; ?>
                                <?php foreach ($photos as $p): ?>
                                <figure class="rp-card" data-photo-id="<?= (int) $p['id'] ?>" data-photo-src="<?= e($p['photo_url']) ?>" data-photo-caption="<?= e($p['description'] ?? '') ?>">
                                    <div class="rp-card__image">
                                        <img src="<?= e($p['photo_url']) ?>" alt="<?= e($p['description'] ?: 'Repair photo') ?>">
                                        <div class="rp-card__overlay">
                                            <button type="button" class="rp-overlay-btn rp-overlay-btn--view" data-action="view" aria-label="View photo"><i class="fa-solid fa-eye"></i></button>
                                            <?php if ($canEdit): ?>
                                            <button type="button" class="rp-overlay-btn rp-overlay-btn--delete" data-action="delete" aria-label="Delete photo"><i class="fa-solid fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <figcaption class="rp-card__caption">
                                        <span class="rp-card__name"><?= e($p['description'] ?: 'Untitled photo') ?></span>
                                        <span class="rp-card__date"><?= e(date('M d, H:i', strtotime($p['uploaded_at']))) ?></span>
                                    </figcaption>
                                </figure>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="rp-col">
                            <h2 class="rp-col__title"><span class="rp-dot rp-dot--after"></span> Add Photo</h2>

                            <?php if ($canEdit): ?>
                            <button type="button" class="rp-upload-placeholder" data-action="upload">
                                <i class="fa-solid fa-camera"></i>
                                <span>Add Photo (JPEG, PNG or WebP, max 5 MB)</span>
                            </button>
                            <?php else: ?>
                            <p style="color: #757684;">This job is <?= e($job['status']) ?>; photos can no longer be added.</p>
                            <?php endif; ?>
                        </section>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-repair-photos.js"></script>
</body>

</html>
