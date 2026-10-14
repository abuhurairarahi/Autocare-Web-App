<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    // Create SpareParts table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS SpareParts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        part_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        brand VARCHAR(100) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        supplier VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'In Stock',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, sku as part_id, name, category, brand, stock_quantity as quantity, price as unit_price, supplier, status, created_at FROM SpareParts WHERE 1=1";
        $params = [];
        
        if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
            $sql .= " AND category = :category";
            $params[':category'] = $_GET['category'];
        }
        
        if (!empty($_GET['brand']) && $_GET['brand'] !== 'All Brands') {
            $sql .= " AND brand = :brand";
            $params[':brand'] = $_GET['brand'];
        }
        
        if (!empty($_GET['status']) && $_GET['status'] !== 'All') {
            $sql .= " AND status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        $countSql = "SELECT COUNT(*) as total FROM SpareParts WHERE 1=1";
        if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') $countSql .= " AND category = :category";
        if (!empty($_GET['brand']) && $_GET['brand'] !== 'All Brands') $countSql .= " AND brand = :brand";
        if (!empty($_GET['status']) && $_GET['status'] !== 'All') $countSql .= " AND status = :status";
        
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $parts = $stmt->fetchAll();
        
        // Stats
        $statsStmt = $pdo->query("SELECT COUNT(*) as total_parts, SUM(IF(stock_quantity <= 25 AND stock_quantity > 0, 1, 0)) as low_stock, SUM(stock_quantity * price) as inventory_value FROM SpareParts");
        $stats = $statsStmt->fetch();
        
        echo json_encode(['success' => true, 'data' => $parts, 'total' => $total, 'page' => $page, 'limit' => $limit, 'stats' => $stats]);
    }
    elseif ($action === 'metadata') {
        header('Content-Type: application/json; charset=utf-8');
        $categoriesStmt = $pdo->query("SELECT DISTINCT name FROM ServiceCategories ORDER BY name");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $brandsStmt = $pdo->query("SELECT DISTINCT brand FROM SpareParts WHERE brand != '' ORDER BY brand");
        $brands = $brandsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $partsStmt = $pdo->query("SELECT DISTINCT name FROM SpareParts ORDER BY name");
        $partsList = $partsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'categories' => $categories, 'brands' => $brands, 'parts' => $partsList]);
    }
    elseif ($action === 'restock') {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        
        $status = 'In Stock';
        if ($quantity == 0) $status = 'Out of Stock';
        elseif ($quantity <= 25) $status = 'Low Stock';
        
        // Generate a random part ID
        $sku = 'PRT-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        $stmt = $pdo->prepare("INSERT INTO SpareParts (sku, name, category, brand, stock_quantity, price, supplier, status) VALUES (:sku, :name, :category, :brand, :quantity, :unit_price, :supplier, :status)");
        $stmt->execute([
            ':sku' => $sku,
            ':name' => $name,
            ':category' => $category,
            ':brand' => $brand,
            ':quantity' => $quantity,
            ':unit_price' => $unit_price,
            ':supplier' => $supplier,
            ':status' => $status
        ]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update_stock') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT stock_quantity FROM SpareParts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetchColumn();
        
        $new_quantity = $current + $quantity;
        
        $status = 'In Stock';
        if ($new_quantity == 0) $status = 'Out of Stock';
        elseif ($new_quantity <= 25) $status = 'Low Stock';
        
        $stmt = $pdo->prepare("UPDATE SpareParts SET stock_quantity = :new_quantity, status = :status WHERE id = :id");
        $stmt->execute([':new_quantity' => $new_quantity, ':status' => $status, ':id' => $id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="spare_parts_inventory.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['PART ID', 'NAME', 'CATEGORY', 'BRAND', 'QUANTITY', 'UNIT PRICE', 'SUPPLIER', 'STATUS']);
        
        $sql = "SELECT sku as part_id, name, category, brand, stock_quantity as quantity, price as unit_price, supplier, status FROM SpareParts WHERE 1=1";
        $params = [];
        
        if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
            $sql .= " AND category = :category";
            $params[':category'] = $_GET['category'];
        }
        
        if (!empty($_GET['brand']) && $_GET['brand'] !== 'All Brands') {
            $sql .= " AND brand = :brand";
            $params[':brand'] = $_GET['brand'];
        }
        
        if (!empty($_GET['status']) && $_GET['status'] !== 'All') {
            $sql .= " AND status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
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
