<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

function fetchAll(PDO $pdo, string $sql): array
{
    $statement = $pdo->query($sql);
    if ($statement === false) {
        throw new RuntimeException($pdo->errorInfo()[2] ?? 'Unable to execute dashboard query.');
    }

    return $statement->fetchAll();
}

try {
    $pdo = databaseConnection();

    $totalManagers = (int) $pdo->query(
        "SELECT COUNT(*) AS total FROM Users WHERE role = 'Manager'"
    )->fetchColumn();

    $pendingMechanicApprovals = (int) $pdo->query(
        "SELECT COUNT(*) AS total FROM Users WHERE role = 'Mechanic' AND status = 'Pending'"
    )->fetchColumn();

    $activeWorkshops = (int) $pdo->query(
        "SELECT COUNT(*) AS total FROM Workshops WHERE manager_id IS NOT NULL"
    )->fetchColumn();

    $monthlyRevenue = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) AS total FROM Invoices WHERE status = 'Paid' AND issued_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
    )->fetchColumn();

    $revenueTrend = fetchAll($pdo,
        "SELECT DATE_FORMAT(issued_date, '%Y-%m') AS month,
            COALESCE(SUM(total_amount), 0) AS revenue
         FROM Invoices
         WHERE status = 'Paid'
           AND issued_date >= DATE_FORMAT(CURRENT_DATE() - INTERVAL 5 MONTH, '%Y-%m-01')
         GROUP BY DATE_FORMAT(issued_date, '%Y-%m')
         ORDER BY month ASC"
    );

    $topWorkshops = fetchAll($pdo,
        "SELECT w.id, w.name,
                COUNT(DISTINCT j.id) AS jobs
         FROM Workshops w
         LEFT JOIN Appointments a ON a.workshop_id = w.id
         LEFT JOIN JobCards j ON j.appointment_id = a.id
         GROUP BY w.id, w.name
         ORDER BY jobs DESC, w.name ASC
         LIMIT 5"
    );

    $serviceDistribution = fetchAll($pdo,
        "SELECT sc.name AS category,
                COUNT(a.id) AS count
         FROM ServiceCategories sc
         LEFT JOIN Appointments a ON a.service_category_id = sc.id
         GROUP BY sc.id, sc.name
         ORDER BY count DESC, sc.name ASC"
    );

    $activities = fetchAll($pdo,
        "SELECT created_at, title, detail, category, link
         FROM (
             SELECT created_at, title, detail, category, link
             FROM (
                 SELECT created_at AS created_at,
                        CONCAT('New mechanic registration: ', name) AS title,
                        CONCAT(email, ' · ', status) AS detail,
                        'mechanic' AS category,
                        'workshop-mechanics.html' AS link
                 FROM Users
                 WHERE role = 'Mechanic'
                 UNION ALL
                 SELECT created_at,
                        CONCAT('Inventory update: ', name),
                        CONCAT(stock_quantity, ' units left · reorder at ', reorder_level),
                        'inventory',
                        'spare-parts-inventory.html'
                 FROM SpareParts
                 WHERE stock_quantity <= reorder_level
                 UNION ALL
                 SELECT a.created_at,
                        CONCAT('Appointment request: ', sc.name),
                        CONCAT(u.name, ' · ', a.status),
                        'appointment',
                        'vehicle-owners.html'
                 FROM Appointments a
                 JOIN Users u ON u.id = a.owner_id
                 LEFT JOIN ServiceCategories sc ON sc.id = a.service_category_id
                 UNION ALL
                 SELECT i.issued_date,
                        CONCAT('Invoice ', CASE i.status WHEN 'Paid' THEN 'paid' ELSE i.status END),
                        CONCAT('Total ৳', FORMAT(i.total_amount, 2), ' · ', u.name),
                        'invoice',
                        'revenue-reports.html'
                 FROM Invoices i
                 JOIN Users u ON u.id = i.customer_id
                 UNION ALL
                 SELECT created_at,
                        CONCAT('Service broadcast: ', title),
                        CONCAT(audience, ' · ', status),
                        'broadcast',
                        'service-broadcast.html'
                 FROM ServiceBroadcasts
             ) AS events
         ) AS activity
         ORDER BY created_at DESC"
    );

    $response = [
        'success' => true,
        'stats' => [
            'totalManagers' => $totalManagers,
            'pendingMechanicApprovals' => $pendingMechanicApprovals,
            'activeWorkshops' => $activeWorkshops,
            'monthlyRevenue' => $monthlyRevenue,
        ],
        'revenueTrend' => $revenueTrend,
        'topWorkshops' => $topWorkshops,
        'serviceDistribution' => $serviceDistribution,
        'activities' => $activities,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ]);
}
