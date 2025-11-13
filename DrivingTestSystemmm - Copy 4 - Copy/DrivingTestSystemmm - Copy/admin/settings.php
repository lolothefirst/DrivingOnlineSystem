<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get system statistics
$stats = [];

$query = "SELECT COUNT(*) as count FROM users";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch()['count'];

$query = "SELECT COUNT(*) as count FROM exam_bookings";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch()['count'];

$query = "SELECT COUNT(*) as count FROM system_logs";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_logs'] = $stmt->fetch()['count'];

include 'includes/header.php';
?>

<div class="container">
    <h1 class="page-title">System Settings</h1>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">System Information</div>
            <div class="detail-row">
                <strong>Site Name:</strong>
                <span><?php echo SITE_NAME; ?></span>
            </div>
            <div class="detail-row">
                <strong>Site URL:</strong>
                <span><?php echo SITE_URL; ?></span>
            </div>
            <div class="detail-row">
                <strong>PHP Version:</strong>
                <span><?php echo phpversion(); ?></span>
            </div>
            <div class="detail-row">
                <strong>Database:</strong>
                <span>MySQL</span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">System Statistics</div>
            <div class="detail-row">
                <strong>Total Users:</strong>
                <span><?php echo $stats['total_users']; ?></span>
            </div>
            <div class="detail-row">
                <strong>Total Bookings:</strong>
                <span><?php echo $stats['total_bookings']; ?></span>
            </div>
            <div class="detail-row">
                <strong>System Logs:</strong>
                <span><?php echo $stats['total_logs']; ?></span>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">Quick Actions</div>
        <div class="settings-actions">
            <a href="reports.php" class="btn btn-primary">View Reports</a>
            <a href="system-logs.php" class="btn btn-secondary">View System Logs</a>
            <a href="backup.php" class="btn btn-success">Backup Database</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
