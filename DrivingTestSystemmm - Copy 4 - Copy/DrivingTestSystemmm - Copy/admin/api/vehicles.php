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
            SELECT vr.*, u.full_name as owner_name 
            FROM vehicle_registrations vr
            LEFT JOIN users u ON vr.user_id = u.id
            WHERE vr.id = ?
        ");
        $stmt->execute([$id]);
        $vehicle = $stmt->fetch();
        
        if ($vehicle) {
            echo json_encode(['success' => true, 'data' => $vehicle]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
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
        $where[] = "(vr.registration_number LIKE ? OR vr.vehicle_make LIKE ? OR vr.vehicle_model LIKE ? OR u.full_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    if (isset($filters['vehicle_type']) && $filters['vehicle_type']) {
        $where[] = "vr.vehicle_type = ?";
        $params[] = $filters['vehicle_type'];
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $allowedColumns = ['id', 'registration_number', 'vehicle_make', 'vehicle_model', 'vehicle_year', 'engine_capacity', 'created_at'];
    if (!in_array($sortColumn, $allowedColumns)) {
        $sortColumn = 'created_at';
    }
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    $countQuery = "SELECT COUNT(*) as total FROM vehicle_registrations vr LEFT JOIN users u ON vr.user_id = u.id {$whereClause}";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // LIMIT and OFFSET must be integers, not bound parameters
    $pageSize = (int)$pageSize;
    $offset = (int)$offset;
    $query = "SELECT vr.*, u.full_name as owner_name 
              FROM vehicle_registrations vr
              LEFT JOIN users u ON vr.user_id = u.id
              {$whereClause}
              ORDER BY vr.{$sortColumn} {$sortDirection} 
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
    
    $stmt = $conn->prepare("SELECT * FROM vehicle_registrations WHERE id = ?");
    $stmt->execute([$id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        return;
    }
    
    $registration_number = strtoupper(trim($data['registration_number'] ?? ''));
    $vehicle_make = $data['vehicle_make'] ?? '';
    $vehicle_model = $data['vehicle_model'] ?? '';
    $vehicle_year = (int)($data['vehicle_year'] ?? 0);
    $vehicle_type = $data['vehicle_type'] ?? 'car';
    $engine_capacity = (int)($data['engine_capacity'] ?? 0);
    
    if (empty($registration_number) || empty($vehicle_make) || empty($vehicle_model)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    // Check registration number uniqueness if changed
    if ($registration_number !== $vehicle['registration_number']) {
        $stmt = $conn->prepare("SELECT id FROM vehicle_registrations WHERE registration_number = ? AND id != ?");
        $stmt->execute([$registration_number, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Registration number already exists']);
            return;
        }
    }
    
    $stmt = $conn->prepare("
        UPDATE vehicle_registrations 
        SET registration_number = ?, vehicle_make = ?, vehicle_model = ?, 
            vehicle_year = ?, vehicle_type = ?, engine_capacity = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$registration_number, $vehicle_make, $vehicle_model, $vehicle_year, $vehicle_type, $engine_capacity, $id])) {
        logActivity($conn, $_SESSION['user_id'], "Updated vehicle: {$registration_number}", 'vehicle_registrations', $id);
        echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update vehicle']);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT registration_number FROM vehicle_registrations WHERE id = ?");
    $stmt->execute([$id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM vehicle_registrations WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logActivity($conn, $_SESSION['user_id'], "Deleted vehicle: {$vehicle['registration_number']}", 'vehicle_registrations', $id);
        echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete vehicle']);
    }
}
?>

