<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$managerId = 2; // Default logged-in manager (Alex Johnson)

try {
    if ($method === 'GET') {
        $otherUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8; // Default Mike Davis

        // 1. Fetch messages between manager and other user
        $stmt = $pdo->prepare("
            SELECT m.message_id as id,
                   m.sender_id,
                   m.receiver_id,
                   u.name as sender_name,
                   COALESCE(u.specialty, u.role) as sender_role,
                   COALESCE(m.job_tag, 'General') as job_tag,
                   m.message_text as message,
                   DATE_FORMAT(m.created_at, '%h:%i %p') as time,
                   (m.sender_id != ?) as is_incoming,
                   m.attachment_url,
                   m.is_read
            FROM ChatMessages m
            JOIN Users u ON m.sender_id = u.user_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC, m.message_id ASC
        ");
        $stmt->execute([$managerId, $managerId, $otherUserId, $otherUserId, $managerId]);
        $messages = $stmt->fetchAll();

        foreach ($messages as &$msg) {
            $msg['is_incoming'] = (bool)$msg['is_incoming'];
            $attachments = [];
            if (!empty($msg['attachment_url'])) {
                $decoded = json_decode($msg['attachment_url'], true);
                $attachments = is_array($decoded) ? $decoded : [$msg['attachment_url']];
            }
            $msg['attachments'] = $attachments;
        }

        // 2. Fetch list of available contacts/threads (Mechanics & VehicleOwners)
        $stmt = $pdo->query("
            SELECT u.user_id as id,
                   u.name,
                   u.role,
                   COALESCE(u.specialty, IF(u.role='Mechanic', 'Bay Tech', 'Fleet Client')) as role_title,
                   COALESCE(u.avatar, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150') as avatar,
                   COALESCE(
                       (SELECT m2.message_text FROM ChatMessages m2 
                        WHERE (m2.sender_id = u.user_id AND m2.receiver_id = 2) 
                           OR (m2.sender_id = 2 AND m2.receiver_id = u.user_id) 
                        ORDER BY m2.created_at DESC LIMIT 1),
                       'No messages yet'
                   ) as last_message,
                   COALESCE(
                       (SELECT DATE_FORMAT(m2.created_at, '%h:%i %p') FROM ChatMessages m2 
                        WHERE (m2.sender_id = u.user_id AND m2.receiver_id = 2) 
                           OR (m2.sender_id = 2 AND m2.receiver_id = u.user_id) 
                        ORDER BY m2.created_at DESC LIMIT 1),
                       '10:00 AM'
                   ) as last_time
            FROM Users u
            WHERE u.user_id != 2 AND u.role IN ('Mechanic', 'VehicleOwner')
            ORDER BY u.role ASC, u.name ASC
        ");
        $contacts = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'threads' => $contacts
        ]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $receiverId = intval($input['receiver_id'] ?? 8);
        $messageText = trim($input['message'] ?? $input['message_text'] ?? '');
        $jobTag = trim($input['job_tag'] ?? 'JOB #8492');
        $attachments = $input['attachments'] ?? [];
        $attachmentUrl = !empty($attachments) ? (is_array($attachments) ? json_encode($attachments) : $attachments) : null;

        if (empty($messageText)) {
            echo json_encode(['error' => 'Message text cannot be empty']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ChatMessages (sender_id, receiver_id, job_tag, message_text, attachment_url, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$managerId, $receiverId, $jobTag, $messageText, $attachmentUrl]);
        $newMsgId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $newMsgId,
                'sender_id' => $managerId,
                'receiver_id' => $receiverId,
                'sender_name' => 'You',
                'sender_role' => 'Workshop Manager',
                'job_tag' => $jobTag,
                'message' => $messageText,
                'time' => date('h:i A'),
                'is_incoming' => false,
                'attachments' => is_array($attachments) ? $attachments : []
            ]
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
