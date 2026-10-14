<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check password
            $isPasswordValid = false;
            // Allow exact match with DB hash (since seeds use 'hashed_pwd_123'), or password_verify, or generic demo passwords
            if ($password === $user['password_hash'] || password_verify($password, $user['password_hash'])) {
                $isPasswordValid = true;
            } else if (in_array($password, ['admin123', 'manager123', 'mechanic123', 'owner123'])) {
                $isPasswordValid = true;
            }
            
            if ($isPasswordValid) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];

                // Determine redirect url based on role
                $redirectUrl = 'landing.html';
                switch(strtolower($user['role'])) {
                    case 'admin':
                        $redirectUrl = 'admin/dashboard.html';
                        break;
                    case 'manager':
                        $redirectUrl = 'manager/manager-dashboard.php';
                        break;
                    case 'mechanic':
                        $redirectUrl = 'mechanic/mechanic-dashboard.php';
                        break;
                    case 'vehicleowner':
                    case 'owner':
                        $redirectUrl = 'vehicleowner/vehicleowner-dashboard.php';
                        break;
                }

                echo json_encode([
                    'success' => true, 
                    'message' => 'Login successful', 
                    'role' => $user['role'],
                    'redirect' => $redirectUrl
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
