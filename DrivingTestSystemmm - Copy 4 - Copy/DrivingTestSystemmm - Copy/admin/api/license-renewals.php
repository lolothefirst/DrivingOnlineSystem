<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet($conn) {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("
            SELECT lr.*, u.full_name as owner_name 
            FROM license_renewals lr
            LEFT JOIN users u ON lr.user_id = u.id
            WHERE lr.id = ?
        ");
        $stmt->execute([$id]);
        $renewal = $stmt->fetch();
        
        if ($renewal) {
            echo json_encode(['success' => true, 'data' => $renewal]);
        } else {
            echo json_encode(['success' => false, 'message' => 'License renewal not found']);
        }
        return;
    }
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'created_at';
    $sortDirection = isset($_GET['sortDirection']) ? $_GET['sortDirection'] : 'desc';
    $filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];
    
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(lr.license_number LIKE ? OR lr.license_types LIKE ? OR u.full_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if (isset($filters['payment_status']) && $filters['payment_status']) {
        $where[] = "lr.payment_status = ?";
        $params[] = $filters['payment_status'];
    }
    
    if (isset($filters['status']) && $filters['status']) {
        $where[] = "lr.status = ?";
        $params[] = $filters['status'];
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $allowedColumns = ['id', 'license_number', 'renewal_period', 'start_date', 'expiry_date', 'amount', 'payment_status', 'status', 'created_at'];
    if (!in_array($sortColumn, $allowedColumns)) {
        $sortColumn = 'created_at';
    }
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    $countQuery = "SELECT COUNT(*) as total FROM license_renewals lr LEFT JOIN users u ON lr.user_id = u.id {$whereClause}";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // LIMIT and OFFSET must be integers, not bound parameters
    $pageSize = (int)$pageSize;
    $offset = (int)$offset;
    $query = "SELECT lr.*, u.full_name as owner_name 
              FROM license_renewals lr
              LEFT JOIN users u ON lr.user_id = u.id
              {$whereClause}
              ORDER BY lr.{$sortColumn} {$sortDirection} 
              LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    $totalPages = ceil($total / $pageSize);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'pageSize' => $pageSize,
            'start' => $offset + 1,
            'end' => min($offset + $pageSize, $total)
        ]
    ]);
}

function handlePut($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM license_renewals WHERE id = ?");
    $stmt->execute([$id]);
    $renewal = $stmt->fetch();
    
    if (!$renewal) {
        echo json_encode(['success' => false, 'message' => 'License renewal not found']);
        return;
    }
    
    $license_number = strtoupper(trim($data['license_number'] ?? ''));
    $renewal_period = $data['renewal_period'] ?? '1_year';
    $start_date = $data['start_date'] ?? '';
    $expiry_date = $data['expiry_date'] ?? '';
    $amount = (float)($data['amount'] ?? 0);
    $payment_status = $data['payment_status'] ?? 'pending';
    $status = $data['status'] ?? 'active';
    
    if (empty($license_number) || empty($start_date) || empty($expiry_date)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE license_renewals 
        SET license_number = ?, renewal_period = ?, start_date = ?, 
            expiry_date = ?, amount = ?, payment_status = ?, status = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$license_number, $renewal_period, $start_date, $expiry_date, $amount, $payment_status, $status, $id])) {
        logActivity($conn, $_SESSION['user_id'], "Updated license renewal: {$license_number}", 'license_renewals', $id);
        echo json_encode(['success' => true, 'message' => 'License renewal updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update license renewal']);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT license_number FROM license_renewals WHERE id = ?");
    $stmt->execute([$id]);
    $renewal = $stmt->fetch();
    
    if (!$renewal) {
        echo json_encode(['success' => false, 'message' => 'License renewal not found']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM license_renewals WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logActivity($conn, $_SESSION['user_id'], "Deleted license renewal: {$renewal['license_number']}", 'license_renewals', $id);
        echo json_encode(['success' => true, 'message' => 'License renewal deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete license renewal']);
    }
}
?>

