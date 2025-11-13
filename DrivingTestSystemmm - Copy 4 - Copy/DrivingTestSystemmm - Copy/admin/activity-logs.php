<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$filter_user = $_GET['user_id'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_user) {
    $where[] = "sl.user_id = :user_id";
    $params[':user_id'] = $filter_user;
}

if ($filter_action) {
    $where[] = "sl.action LIKE :action";
    $params[':action'] = "%$filter_action%";
}

if ($filter_date) {
    $where[] = "DATE(sl.created_at) = :date";
    $params[':date'] = $filter_date;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM system_logs sl $where_clause";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_logs = $stmt->fetch()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get logs
$query = "SELECT sl.*, u.username, u.full_name 
          FROM system_logs sl 
          LEFT JOIN users u ON sl.user_id = u.id 
          $where_clause
          ORDER BY sl.created_at DESC 
          LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll();

// Get users for filter
$users_query = "SELECT id, username, full_name FROM users ORDER BY full_name";
$stmt = $conn->prepare($users_query);
$stmt->execute();
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <h1 class="page-title">System Activity Logs</h1>
        
        <!-- Filters -->
        <div class="card">
            <form method="GET" class="form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label for="user_id">User</label>
                    <select id="user_id" name="user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action">Action</label>
                    <input type="text" id="action" name="action" class="form-control" value="<?php echo htmlspecialchars($filter_action); ?>" placeholder="Search action...">
                </div>
                
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="activity-logs.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <div class="card">
            <p>Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> logs</p>
            
            <?php if (count($logs) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDate($log['created_at'], 'M d, Y H:i:s'); ?></td>
                                <td><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                <td><?php echo $log['record_id']; ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" 
                               class="btn btn-sm <?php echo $page === $i ? 'btn-primary' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>No activity logs found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
