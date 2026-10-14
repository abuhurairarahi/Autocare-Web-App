<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = databaseConnection();

    // Create Services Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS Services (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        title VARCHAR(100), 
        price DECIMAL(10,2), 
        category VARCHAR(50),
        duration VARCHAR(50)
    )");

    // Create Locations Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS Locations (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(100), 
        address VARCHAR(255), 
        hours VARCHAR(100)
    )");

    // Seed Services
    $count = (int)$pdo->query("SELECT COUNT(*) FROM Services")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO Services (title, price, category, duration) VALUES 
            ('Engine Overhaul', 20000.00, 'Engine', '2-3 days'),
            ('Electrical Diagnostics', 15000.00, 'Electrical', '3-5 hrs'),
            ('Denting & Painting', 25000.00, 'Body', '3-4 days'),
            ('Full Servicing', 10000.00, 'Servicing', '1 day'),
            ('Brake Replacement', 8000.00, 'Servicing', '4 hrs'),
            ('AC Repair', 6500.00, 'Electrical', '1 day')
        ");
    }

    // Seed Locations
    $lCount = (int)$pdo->query("SELECT COUNT(*) FROM Locations")->fetchColumn();
    if ($lCount === 0) {
        $pdo->exec("INSERT INTO Locations (name, address, hours) VALUES 
            ('AutoCare Dhaka', '3/A, Tejgaon Industrial Zone', 'Mon-Sat 8:00-20:00'),
            ('AutoCare Rajshahi', '4/E, New Airport Road', 'Mon-Sat 8:00-20:00'),
            ('AutoCare Chittagong', 'Neval, Chittagonn Link Road', 'Mon-Sat 8:00-20:00')
        ");
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'data') {
        header('Content-Type: application/json; charset=utf-8');
        
        $services = $pdo->query("SELECT * FROM Services")->fetchAll();
        $locations = $pdo->query("SELECT * FROM Locations")->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'services' => $services,
            'locations' => $locations
        ]);
    }

} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
