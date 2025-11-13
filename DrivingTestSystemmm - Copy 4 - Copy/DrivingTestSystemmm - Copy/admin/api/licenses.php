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
            SELECT dl.*, u.full_name as owner_name 
            FROM driving_licenses dl
            LEFT JOIN users u ON dl.user_id = u.id
            WHERE dl.id = ?
        ");
        $stmt->execute([$id]);
        $license = $stmt->fetch();
        
        if ($license) {
            echo json_encode(['success' => true, 'data' => $license]);
        } else {
            echo json_encode(['success' => false, 'message' => 'License not found']);
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
        $where[] = "(dl.license_number LIKE ? OR dl.license_class LIKE ? OR u.full_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if (isset($filters['status']) && $filters['status']) {
        $where[] = "dl.status = ?";
        $params[] = $filters['status'];
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $allowedColumns = ['id', 'license_number', 'license_class', 'issue_date', 'expiry_date', 'status', 'created_at'];
    if (!in_array($sortColumn, $allowedColumns)) {
        $sortColumn = 'created_at';
    }
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    $countQuery = "SELECT COUNT(*) as total FROM driving_licenses dl LEFT JOIN users u ON dl.user_id = u.id {$whereClause}";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // LIMIT and OFFSET must be integers, not bound parameters
    $pageSize = (int)$pageSize;
    $offset = (int)$offset;
    $query = "SELECT dl.*, u.full_name as owner_name 
              FROM driving_licenses dl
              LEFT JOIN users u ON dl.user_id = u.id
              {$whereClause}
              ORDER BY dl.{$sortColumn} {$sortDirection} 
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
    
    $stmt = $conn->prepare("SELECT * FROM driving_licenses WHERE id = ?");
    $stmt->execute([$id]);
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found']);
        return;
    }
    
    $license_number = strtoupper(trim($data['license_number'] ?? ''));
    $license_class = $data['license_class'] ?? '';
    $issue_date = $data['issue_date'] ?? '';
    $expiry_date = $data['expiry_date'] ?? '';
    $status = $data['status'] ?? 'active';
    
    if (empty($license_number) || empty($license_class) || empty($issue_date) || empty($expiry_date)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    // Check license number uniqueness if changed
    if ($license_number !== $license['license_number']) {
        $stmt = $conn->prepare("SELECT id FROM driving_licenses WHERE license_number = ? AND id != ?");
        $stmt->execute([$license_number, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'License number already exists']);
            return;
        }
    }
    
    $stmt = $conn->prepare("
        UPDATE driving_licenses 
        SET license_number = ?, license_class = ?, issue_date = ?, 
            expiry_date = ?, status = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$license_number, $license_class, $issue_date, $expiry_date, $status, $id])) {
        logActivity($conn, $_SESSION['user_id'], "Updated license: {$license_number}", 'driving_licenses', $id);
        echo json_encode(['success' => true, 'message' => 'License updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update license']);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT license_number FROM driving_licenses WHERE id = ?");
    $stmt->execute([$id]);
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM driving_licenses WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logActivity($conn, $_SESSION['user_id'], "Deleted license: {$license['license_number']}", 'driving_licenses', $id);
        echo json_encode(['success' => true, 'message' => 'License deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete license']);
    }
}
?>

