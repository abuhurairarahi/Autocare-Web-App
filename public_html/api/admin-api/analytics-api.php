<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    $action = $_GET['action'] ?? 'data';

    if ($action === 'data') {
        header('Content-Type: application/json; charset=utf-8');

        // 1. Stats from real jobcards
        $avgTime = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, start_date, completion_date)) FROM jobcards WHERE status = 'Completed'")->fetchColumn();
        $totalServices = $pdo->query("SELECT COUNT(*) FROM jobcards WHERE status = 'Completed'")->fetchColumn();
        
        // Mock rating and rework since real DB doesn't have them yet
        $avgRating = 4.8;
        $reworkRate = 2.5;

        // 2. Trends (Last 7 days completed services)
        $trends = $pdo->query("
            SELECT DATE_FORMAT(completion_date, '%a') as day_name, COUNT(*) as count 
            FROM jobcards 
            WHERE status = 'Completed' AND completion_date >= CURDATE() - INTERVAL 6 DAY 
            GROUP BY DATE(completion_date) 
            ORDER BY DATE(completion_date) ASC
        ")->fetchAll();
        
        // If no trends data, provide empty array so UI handles it gracefully
        if (!$trends) {
            $trends = [];
        }

        // 3. Top Workshops
        // Fetch real workshops and calculate a dummy score based on their completed jobs
        $workshops = $pdo->query("
            SELECT w.name, 
                   COALESCE((SELECT COUNT(*) FROM jobcards jc WHERE jc.manager_id = w.manager_id AND jc.status = 'Completed'), 0) * 10 + 75 as score 
            FROM Workshops w 
            ORDER BY score DESC 
            LIMIT 3
        ")->fetchAll();

        // 4. Mechanics Productivity
        $search = $_GET['search'] ?? '';
        $filter = $_GET['filter'] ?? 'All';

        $mechSql = "SELECT u.id, u.name, u.status as db_status,
            COALESCE((SELECT COUNT(*) FROM jobcards jc WHERE jc.mechanic_id = u.id AND jc.status = 'Completed'), 0) as completed_jobs,
            COALESCE((SELECT AVG(TIMESTAMPDIFF(MINUTE, start_date, completion_date)) FROM jobcards jc WHERE jc.mechanic_id = u.id AND jc.status = 'Completed'), 0) as avg_mins,
            w.name as workshop_name
            FROM Users u 
            LEFT JOIN Workshops w ON u.workshop_id = w.id
            WHERE u.role = 'Mechanic'";
            
        $params = [];
        if ($search !== '') {
            $mechSql .= " AND u.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }
        
        $stmt = $pdo->prepare($mechSql);
        $stmt->execute($params);
        $dbMechanics = $stmt->fetchAll();
        
        $mechanics = [];
        foreach ($dbMechanics as $m) {
            $jobs = (int)$m['completed_jobs'];
            $avgMins = (int)$m['avg_mins'];
            $score = 70 + ($jobs * 5); // Real calculated score based on jobs
            if ($score > 100) $score = 100;
            
            $effStatus = ($score >= 85) ? 'High Efficiency' : 'Low Efficiency';
            
            if ($filter !== 'All' && $filter !== $effStatus) continue;

            $mechanics[] = [
                'name' => $m['name'],
                'workshop' => $m['workshop_name'] ?? 'Unassigned',
                'jobs' => $jobs,
                'avg_time' => $avgMins > 0 ? (floor($avgMins/60) . 'h ' . ($avgMins%60) . 'm') : '--',
                'score' => $score,
                'status' => $m['db_status']
            ];
        }
        
        usort($mechanics, function($a, $b) { return $b['score'] <=> $a['score']; });

        echo json_encode([
            'success' => true,
            'stats' => [
                'avg_time' => $avgTime > 0 ? (floor($avgTime/60) . 'h ' . round($avgTime%60) . 'm') : '--',
                'rating' => number_format((float)$avgRating, 1),
                'total' => number_format((int)$totalServices),
                'rework' => number_format((float)$reworkRate, 1) . '%'
            ],
            'trends' => $trends,
            'workshops' => $workshops,
            'mechanics' => $mechanics
        ]);
    }
    elseif ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="performance_analytics.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Performance Analytics Export']);
        fputcsv($output, []);
        
        $avgTime = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, start_date, completion_date)) FROM jobcards WHERE status = 'Completed'")->fetchColumn();
        $totalServices = $pdo->query("SELECT COUNT(*) FROM jobcards WHERE status = 'Completed'")->fetchColumn();
        
        fputcsv($output, ['METRIC', 'VALUE']);
        fputcsv($output, ['Average Service Time (mins)', round((float)$avgTime)]);
        fputcsv($output, ['Customer Satisfaction', '4.8']);
        fputcsv($output, ['Total Services', (int)$totalServices]);
        fputcsv($output, ['Rework Rate (%)', '2.5']);
        fputcsv($output, []);
        
        fputcsv($output, ['MECHANIC NAME', 'WORKSHOP', 'JOBS', 'SCORE']);
        
        $search = $_GET['search'] ?? '';
        $filter = $_GET['filter'] ?? 'All';

        $mechSql = "SELECT u.id, u.name, 
            COALESCE((SELECT COUNT(*) FROM jobcards jc WHERE jc.mechanic_id = u.id AND jc.status = 'Completed'), 0) as completed_jobs,
            w.name as workshop_name
            FROM Users u 
            LEFT JOIN Workshops w ON u.workshop_id = w.id
            WHERE u.role = 'Mechanic'";
            
        $params = [];
        if ($search !== '') {
            $mechSql .= " AND u.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }
        
        $stmt = $pdo->prepare($mechSql);
        $stmt->execute($params);
        
        while ($m = $stmt->fetch()) {
            $jobs = (int)$m['completed_jobs'];
            $score = 70 + ($jobs * 5); 
            if ($score > 100) $score = 100;
            
            $effStatus = ($score >= 85) ? 'High Efficiency' : 'Low Efficiency';
            if ($filter !== 'All' && $filter !== $effStatus) continue;
            
            fputcsv($output, [$m['name'], $m['workshop_name'] ?? 'Unassigned', $jobs, $score]);
        }
        
        fclose($output);
        exit;
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
