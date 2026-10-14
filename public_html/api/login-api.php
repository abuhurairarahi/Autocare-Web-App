<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/db.php';

try {
    $pdo = databaseConnection();

    // Create Users Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS AuthUsers (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(100), 
        email VARCHAR(100) UNIQUE, 
        password VARCHAR(255), 
        role VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed Data
    $count = (int)$pdo->query("SELECT COUNT(*) FROM AuthUsers")->fetchColumn();
    if ($count === 0) {
        $defaultPass = password_hash('password123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO AuthUsers (name, email, password, role) VALUES 
            ('Admin User', 'admin@autocare.com', '$defaultPass', 'Administrator'),
            ('Manager User', 'manager@autocare.com', '$defaultPass', 'Workshop Manager'),
            ('Mechanic User', 'mechanic@autocare.com', '$defaultPass', 'Mechanic'),
            ('Vehicle Owner', 'owner@autocare.com', '$defaultPass', 'Vehicle Owner')
        ");
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'login') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM AuthUsers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            // Determine redirect
            $redirect = 'landing.html';
            if ($user['role'] === 'Administrator') $redirect = 'admin/dashboard.html';
            elseif ($user['role'] === 'Workshop Manager') $redirect = 'manager/manager-dashboard.html';
            elseif ($user['role'] === 'Mechanic') $redirect = 'mechanic/mechanic-dashboard.html';
            elseif ($user['role'] === 'Vehicle Owner') $redirect = 'vehicleowner/vehicleowner-dashboard.html';

            echo json_encode([
                'success' => true, 
                'redirect_url' => $redirect,
                'user' => [
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid email or password.', 'debug' => ['input_email' => $email, 'db_user_found' => (bool)$user]]);
        }
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
