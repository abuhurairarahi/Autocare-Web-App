<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    // Using the real servicebroadcasts table.
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $statusFilter = $_GET['status'] ?? 'All';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM servicebroadcasts WHERE 1=1";
        $params = [];
        
        if ($statusFilter !== 'All') {
            $sql .= " AND status = :st";
            $params[':st'] = $statusFilter;
        }
        
        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
        $cStmt = $pdo->prepare($countSql);
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $notices = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $notices, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
    elseif ($action === 'add' || $action === 'update') {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? 'Announcement');
        $priority = trim($_POST['priority'] ?? 'Low');
        $audience = trim($_POST['audience'] ?? 'All Users');
        $content = trim($_POST['content'] ?? '');
        $status = trim($_POST['status'] ?? 'Draft');
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO servicebroadcasts (title, type, priority, audience, content, status) VALUES (:title, :type, :prio, :aud, :content, :status)");
            $stmt->execute([
                ':title' => $title, ':type' => $type, ':prio' => $priority,
                ':aud' => $audience, ':content' => $content, ':status' => $status
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE servicebroadcasts SET title=:title, type=:type, priority=:prio, audience=:aud, content=:content, status=:status WHERE id=:id");
            $stmt->execute([
                ':title' => $title, ':type' => $type, ':prio' => $priority,
                ':aud' => $audience, ':content' => $content, ':status' => $status, ':id' => $id
            ]);
        }
        
        echo json_encode(['success' => true]);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
