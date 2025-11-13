<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get all exam sessions
$query = "SELECT es.*, tc.center_name, e.full_name as examiner_name, tv.plate_number,
          (SELECT COUNT(*) FROM exam_bookings WHERE session_id = es.id) as total_bookings
          FROM exam_sessions es
          JOIN test_centers tc ON es.center_id = tc.id
          LEFT JOIN examiners e ON es.examiner_id = e.id
          LEFT JOIN test_vehicles tv ON es.vehicle_id = tv.id
          ORDER BY es.exam_date DESC, es.exam_time DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$sessions = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Exam Management</h1>
        <a href="exam-create.php" class="btn btn-primary">Schedule New Exam</a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Date & Time</th>
                    <th>Center</th>
                    <th>Examiner</th>
                    <th>Slots</th>
                    <th>Bookings</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?php echo $session['id']; ?></td>
                        <td><?php echo ucfirst($session['exam_type']); ?></td>
                        <td>
                            <?php echo formatDate($session['exam_date'], 'M d, Y'); ?><br>
                            <small><?php echo date('g:i A', strtotime($session['exam_time'])); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($session['center_name']); ?></td>
                        <td><?php echo htmlspecialchars($session['examiner_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $session['available_slots']; ?> / <?php echo $session['total_slots']; ?></td>
                        <td><?php echo $session['total_bookings']; ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $session['status'] === 'scheduled' ? 'info' : 
                                    ($session['status'] === 'completed' ? 'success' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="exam-edit.php?id=<?php echo $session['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <a href="exam-bookings.php?id=<?php echo $session['id']; ?>" class="btn btn-sm btn-primary">View Bookings</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
