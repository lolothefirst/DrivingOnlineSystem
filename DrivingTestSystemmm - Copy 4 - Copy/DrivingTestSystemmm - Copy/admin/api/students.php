<?php
// Start output buffering to catch any unexpected output
ob_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Suppress error display but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

try {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle different request methods
    switch ($method) {
        case 'GET':
            handleGet($conn);
            break;
        case 'POST':
            handlePost($conn);
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
} catch (Exception $e) {
    ob_clean();
    error_log("Error in students API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function handleGet($conn) {
    try {
        // Handle single record request
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'student'");
            $stmt->execute([$id]);
            $student = $stmt->fetch();
            
            if ($student) {
                echo json_encode(['success' => true, 'data' => $student]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Student not found']);
            }
            return;
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'created_at';
        $sortDirection = isset($_GET['sortDirection']) ? $_GET['sortDirection'] : 'desc';
        $filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];
        
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 10;
        
        $offset = ($page - 1) * $pageSize;
        
        // Build WHERE clause
        $where = ["user_type = 'student'"];
        $params = [];
        
        if ($search) {
            $where[] = "(full_name LIKE ? OR email LIKE ? OR id_number LIKE ? OR phone LIKE ?)";
            $searchParam = "%{$search}%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Validate sort column
        $allowedColumns = ['id', 'full_name', 'email', 'id_number', 'phone', 'status', 'created_at'];
        if (!in_array($sortColumn, $allowedColumns)) {
            $sortColumn = 'created_at';
        }
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM users WHERE {$whereClause}";
        $stmt = $conn->prepare($countQuery);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];
        
        // Get data - LIMIT and OFFSET must be integers, not bound parameters
        $pageSize = (int)$pageSize;
        $offset = (int)$offset;
        $query = "SELECT * FROM users WHERE {$whereClause} ORDER BY {$sortColumn} {$sortDirection} LIMIT {$pageSize} OFFSET {$offset}";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'pageSize' => $pageSize,
                'start' => $total > 0 ? $offset + 1 : 0,
                'end' => min($offset + $pageSize, $total)
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Database error in handleGet: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("Error in handleGet: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function handlePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    $id_number = $data['id_number'] ?? '';
    $phone = $data['phone'] ?? '';
    $password = $data['password'] ?? '';
    $status = $data['status'] ?? 'active';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($id_number)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Check if ID number exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
    $stmt->execute([$id_number]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ID number already exists']);
        return;
    }
    
    $hashedPassword = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, id_number, phone, password, user_type, status) VALUES (?, ?, ?, ?, ?, 'student', ?)");
    
    if ($stmt->execute([$full_name, $email, $id_number, $phone, $hashedPassword, $status])) {
        $id = $conn->lastInsertId();
        logActivity($conn, $_SESSION['user_id'], "Created student: {$full_name}", 'users', $id);
        echo json_encode(['success' => true, 'message' => 'Student created successfully', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create student']);
    }
}

function handlePut($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    // Get existing user
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'student'");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    
    $full_name = $data['full_name'] ?? $user['full_name'];
    $email = $data['email'] ?? $user['email'];
    $id_number = $data['id_number'] ?? $user['id_number'];
    $phone = $data['phone'] ?? $user['phone'];
    $status = $data['status'] ?? $user['status'];
    $password = $data['password'] ?? '';
    
    // Check email uniqueness if changed
    if ($email !== $user['email']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
    }
    
    // Check ID number uniqueness if changed
    if ($id_number !== $user['id_number']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
        $stmt->execute([$id_number, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ID number already exists']);
            return;
        }
    }
    
    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, id_number = ?, phone = ?, password = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$full_name, $email, $id_number, $phone, $hashedPassword, $status, $id]);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, id_number = ?, phone = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$full_name, $email, $id_number, $phone, $status, $id]);
    }
    
    if ($result) {
        logActivity($conn, $_SESSION['user_id'], "Updated student: {$full_name}", 'users', $id);
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update student']);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    // Get user info for logging
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND user_type = 'student'");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND user_type = 'student'");
    
    if ($stmt->execute([$id])) {
        logActivity($conn, $_SESSION['user_id'], "Deleted student: {$user['full_name']}", 'users', $id);
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
    }
}
?>

