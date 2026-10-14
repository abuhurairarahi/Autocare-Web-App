<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    // Create a RevenueTransactions table to provide real query results
    $pdo->exec("CREATE TABLE IF NOT EXISTS RevenueTransactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_date DATE NOT NULL,
        workshop_location VARCHAR(100) NOT NULL,
        service_category VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Completed'
    )");

    // Seed data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM RevenueTransactions")->fetchColumn();
    if ($count == 0) {
        $locations = ['Dhaka', 'Rajshahi', 'Chittagong', 'Khulna'];
        $categories = ['Brakes & Suspension', 'Engine Repair', 'Oil Change', 'General Service'];
        
        $insertStmt = $pdo->prepare("INSERT INTO RevenueTransactions (transaction_date, workshop_location, service_category, amount, status) VALUES (:dt, :loc, :cat, :amt, 'Completed')");
        
        for ($i=0; $i<200; $i++) {
            $monthOffset = rand(0, 24); // Past 2 years
            $day = rand(1, 28);
            $date = date('Y-m-d', strtotime("-$monthOffset months"));
            $date = substr($date, 0, 8) . sprintf("%02d", $day);
            
            $loc = $locations[array_rand($locations)];
            $cat = $categories[array_rand($categories)];
            $amt = rand(1000, 15000);
            
            $insertStmt->execute([':dt' => $date, ':loc' => $loc, ':cat' => $cat, ':amt' => $amt]);
        }
    }

    $action = $_GET['action'] ?? 'report';
    
    // Map Month Names to Numbers
    $monthMap = ['January'=>1, 'February'=>2, 'March'=>3, 'April'=>4, 'May'=>5, 'June'=>6, 'July'=>7, 'August'=>8, 'September'=>9, 'October'=>10, 'November'=>11, 'December'=>12];

    if ($action === 'report' || $action === 'csv' || $action === 'pdf') {
        $monthName = $_GET['month'] ?? 'January';
        $monthNum = $monthMap[$monthName] ?? 1;
        $year = (int)($_GET['year'] ?? date('Y'));
        $location = $_GET['location'] ?? 'All Workshops';
        
        $sql = "SELECT id, transaction_date, workshop_location, service_category, amount, status 
                FROM RevenueTransactions 
                WHERE YEAR(transaction_date) = :yr AND MONTH(transaction_date) = :mo";
        
        $params = [':yr' => $year, ':mo' => $monthNum];
        
        if ($location !== 'All Workshops') {
            $sql .= " AND workshop_location = :loc";
            $params[':loc'] = $location;
        }
        
        $sql .= " ORDER BY transaction_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        $totalRevenue = 0;
        foreach($rows as $row) {
            $totalRevenue += (float)$row['amount'];
        }
        
        if ($action === 'report') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'total_revenue' => $totalRevenue,
                'total_transactions' => count($rows),
                'rows' => $rows
            ]);
            exit;
        }
        
        if ($action === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="revenue_report_'.$monthName.'_'.$year.'.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Revenue Report', "$monthName $year", "Location: $location"]);
            fputcsv($output, []);
            fputcsv($output, ['Summary']);
            fputcsv($output, ['Total Revenue', 'Total Transactions']);
            fputcsv($output, [$totalRevenue, count($rows)]);
            fputcsv($output, []);
            fputcsv($output, ['ID', 'Date', 'Workshop', 'Service Category', 'Amount']);
            
            foreach ($rows as $row) {
                fputcsv($output, [$row['id'], $row['transaction_date'], $row['workshop_location'], $row['service_category'], $row['amount']]);
            }
            fclose($output);
            exit;
        }
        
        if ($action === 'pdf') {
            // Generating an HTML print view for PDF
            echo "<!DOCTYPE html><html><head><title>Revenue Report PDF</title><style>
            body { font-family: sans-serif; padding: 40px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
            th { background: #f4f6f9; }
            .header { text-align: center; margin-bottom: 40px; }
            </style></head><body onload='window.print()'>
            <div class='header'>
                <h1>AutoCare Revenue Report</h1>
                <p>{$monthName} {$year} | Location: {$location}</p>
                <h3>Total Revenue: ৳" . number_format($totalRevenue, 2) . "</h3>
                <h3>Total Transactions: " . count($rows) . "</h3>
            </div>
            <table>
                <tr><th>ID</th><th>Date</th><th>Workshop</th><th>Category</th><th>Amount (৳)</th></tr>";
            foreach($rows as $row) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['transaction_date']}</td>
                    <td>{$row['workshop_location']}</td>
                    <td>{$row['service_category']}</td>
                    <td>" . number_format((float)$row['amount'], 2) . "</td>
                </tr>";
            }
            echo "</table></body></html>";
            exit;
        }
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
