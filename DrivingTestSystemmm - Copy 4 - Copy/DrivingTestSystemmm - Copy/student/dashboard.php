<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get student statistics
$query = "SELECT COUNT(*) as total_attempts FROM mock_test_attempts WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$mock_stats = $stmt->fetch();

$query = "SELECT COUNT(*) as total_bookings FROM exam_bookings WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$booking_stats = $stmt->fetch();

$query = "SELECT COUNT(*) as total_results FROM exam_results WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$result_stats = $stmt->fetch();

// Get recent mock test attempts
$query = "SELECT * FROM mock_test_attempts WHERE user_id = :user_id ORDER BY completed_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recent_attempts = $stmt->fetchAll();

// Get upcoming bookings
$query = "SELECT eb.*, es.exam_type, es.exam_date, es.exam_time, tc.center_name 
          FROM exam_bookings eb
          JOIN exam_sessions es ON eb.session_id = es.id
          JOIN test_centers tc ON es.center_id = tc.id
          WHERE eb.user_id = :user_id AND es.exam_date >= CURDATE() AND eb.status != 'cancelled'
          ORDER BY es.exam_date, es.exam_time LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$upcoming_bookings = $stmt->fetchAll();

// Get announcements
$query = "SELECT * FROM announcements 
          WHERE is_active = 1 AND (target_audience = 'all' OR target_audience = 'students')
          ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard">
    <div class="container">
        <h1 class="page-title">Student Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        
        <div class="grid grid-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo $mock_stats['total_attempts']; ?></div>
                <div class="stat-label">Mock Tests Taken</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="stat-value"><?php echo $booking_stats['total_bookings']; ?></div>
                <div class="stat-label">Exam Bookings</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="stat-value"><?php echo $result_stats['total_results']; ?></div>
                <div class="stat-label">Test Results</div>
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">Recent Mock Tests</div>
                <?php if (count($recent_attempts) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Questions</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo formatDate($attempt['completed_at'], 'M d, Y'); ?></td>
                                    <td><?php echo $attempt['total_questions']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $attempt['score_percentage'] >= 70 ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $attempt['score_percentage']; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="mock-test.php" class="btn btn-primary btn-sm mt-2">Take Mock Test</a>
                <?php else: ?>
                    <p>No mock tests taken yet.</p>
                    <a href="mock-test.php" class="btn btn-primary mt-2">Start Your First Mock Test</a>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">Upcoming Exams</div>
                <?php if (count($upcoming_bookings) > 0): ?>
                    <div class="bookings-list">
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-type">
                                    <strong><?php echo ucfirst($booking['exam_type']); ?> Test</strong>
                                </div>
                                <div class="booking-details">
                                    <?php echo formatDate($booking['exam_date'], 'M d, Y'); ?> at 
                                    <?php echo date('g:i A', strtotime($booking['exam_time'])); ?>
                                </div>
                                <div class="booking-location"><?php echo htmlspecialchars($booking['center_name']); ?></div>
                                <div class="booking-status">
                                    <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="bookings.php" class="btn btn-primary btn-sm mt-2">View All Bookings</a>
                <?php else: ?>
                    <p>No upcoming exams.</p>
                    <a href="book-exam.php" class="btn btn-primary mt-2">Book an Exam</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Announcements</div>
            <?php if (count($announcements) > 0): ?>
                <div class="announcements-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <div class="announcement-header">
                                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <span class="announcement-date"><?php echo formatDate($announcement['created_at'], 'M d, Y'); ?></span>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No announcements at this time.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
