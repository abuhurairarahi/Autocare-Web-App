<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    // Create Chat Contacts Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ChatContacts (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(100), 
        role VARCHAR(100), 
        contact_type VARCHAR(50), 
        job_tag VARCHAR(50), 
        avatar_url VARCHAR(255)
    )");

    // Create Chat Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ManagerChatMessages (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        contact_id INT, 
        message TEXT, 
        is_incoming BOOLEAN, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed Data
    $count = (int)$pdo->query("SELECT COUNT(*) FROM ChatContacts")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO ChatContacts (id, name, role, contact_type, job_tag, avatar_url) VALUES 
            (8, 'Mike Davis', 'Tech Bay 4', 'Internal', 'JOB #8492', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80'),
            (4, 'Sarah Jenkins', 'Fleet Mgr', 'Clients', 'General', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=80&q=80'),
            (101, 'David Chui', 'Lead Mechanic', 'Internal', 'General', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=80&q=80')
        ");
        
        $pdo->exec("INSERT INTO ManagerChatMessages (contact_id, message, is_incoming) VALUES 
            (8, 'Hey boss, I\'ve got the F-150 up on the lift. The diagnostic showed a misfire on cylinder 4, but while inspecting I found something else.', 1),
            (8, 'Copy that, Mike. What did you find? Is it going to affect the estimate for Fleet Logistics?', 0),
            (8, 'Found a cracked exhaust manifold. It\'s pretty bad. We\'ll definitely need to update the estimate.', 1),
            (4, 'Can we get an ETA on the delivery vans?', 1)
        ");
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list_contacts') {
        header('Content-Type: application/json; charset=utf-8');
        
        $stmt = $pdo->query("
            SELECT id, name, role, 
                   CASE WHEN role = 'Mechanic' THEN 'Internal' ELSE 'Clients' END as contact_type,
                   'General' as job_tag,
                   'https://ui-avatars.com/api/?background=random&name=' as avatar_url,
                   (SELECT message FROM ManagerChatMessages m WHERE m.contact_id = users.id ORDER BY m.id DESC LIMIT 1) as latest_message,
                   (SELECT DATE_FORMAT(created_at, '%h:%i %p') FROM ManagerChatMessages m WHERE m.contact_id = users.id ORDER BY m.id DESC LIMIT 1) as latest_time
            FROM users 
            WHERE role IN ('Mechanic', 'VehicleOwner')
            ORDER BY latest_message IS NULL ASC, id ASC
        ");
        $contacts = $stmt->fetchAll();
        
        foreach($contacts as &$c) {
            $c['avatar_url'] .= urlencode($c['name']);
        }
        
        echo json_encode(['success' => true, 'contacts' => $contacts]);
    }
    elseif ($action === 'get_messages') {
        header('Content-Type: application/json; charset=utf-8');
        $contactId = (int)($_GET['contact_id'] ?? 0);
        
        if ($contactId > 0) {
            $stmt = $pdo->prepare("SELECT id, message, is_incoming, DATE_FORMAT(created_at, '%h:%i %p') as time FROM ManagerChatMessages WHERE contact_id = ? ORDER BY id ASC");
            $stmt->execute([$contactId]);
            $messages = $stmt->fetchAll();
            
            $stmt2 = $pdo->prepare("SELECT id, name, role, CASE WHEN role = 'Mechanic' THEN 'Internal' ELSE 'Clients' END as contact_type, 'General' as job_tag, CONCAT('https://ui-avatars.com/api/?background=random&name=', REPLACE(name, ' ', '+')) as avatar_url FROM users WHERE id = ?");
            $stmt2->execute([$contactId]);
            $contact = $stmt2->fetch();
            
            echo json_encode(['success' => true, 'messages' => $messages, 'contact' => $contact]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid contact ID']);
        }
    }
    elseif ($action === 'send_message') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $contactId = (int)($data['contact_id'] ?? 0);
        $message = $data['message'] ?? '';
        $isIncoming = (int)($data['is_incoming'] ?? 0);
        
        if ($contactId > 0 && !empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO ManagerChatMessages (contact_id, message, is_incoming) VALUES (?, ?, ?)");
            $stmt->execute([$contactId, $message, $isIncoming]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    }
    elseif ($action === 'new_contact') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($data['id'] ?? 0);
        
        echo json_encode(['success' => true, 'contact_id' => $id]);
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
