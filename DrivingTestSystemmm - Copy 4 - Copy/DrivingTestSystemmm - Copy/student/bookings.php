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

// Get all bookings
$query = "SELECT eb.*, es.exam_type, es.exam_date, es.exam_time, es.status as session_status,
          tc.center_name, tc.city, e.full_name as examiner_name
          FROM exam_bookings eb
          JOIN exam_sessions es ON eb.session_id = es.id
          JOIN test_centers tc ON es.center_id = tc.id
          LEFT JOIN examiners e ON es.examiner_id = e.id
          WHERE eb.user_id = :user_id
          ORDER BY es.exam_date DESC, es.exam_time DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bookings = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">My Exam Bookings</h1>
        <a href="book-exam.php" class="btn btn-primary">Book New Exam</a>
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
    
    <?php if (count($bookings) > 0): ?>
        <div class="bookings-list-full">
            <?php foreach ($bookings as $booking): ?>
                <?php 
                $is_upcoming = strtotime($booking['exam_date']) >= strtotime(date('Y-m-d'));
                $can_cancel = $is_upcoming && $booking['status'] !== 'cancelled' && 
                              strtotime($booking['exam_date'] . ' ' . $booking['exam_time']) > strtotime('+24 hours');
                ?>
                
                <div class="booking-card <?php echo $is_upcoming ? 'upcoming' : 'past'; ?>">
                    <div class="booking-header">
                        <div>
                            <h3><?php echo ucfirst($booking['exam_type']); ?> Test</h3>
                            <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        <div class="booking-id">
                            Booking #<?php echo $booking['id']; ?>
                        </div>
                    </div>
                    
                    <div class="booking-content">
                        <div class="booking-info">
                            <div class="info-item">
                                <strong>Date & Time:</strong>
                                <?php echo formatDate($booking['exam_date'], 'M d, Y'); ?> at 
                                <?php echo date('g:i A', strtotime($booking['exam_time'])); ?>
                            </div>
                            
                            <div class="info-item">
                                <strong>Test Center:</strong>
                                <?php echo htmlspecialchars($booking['center_name']); ?>, 
                                <?php echo htmlspecialchars($booking['city']); ?>
                            </div>
                            
                            <?php if ($booking['examiner_name']): ?>
                                <div class="info-item">
                                    <strong>Examiner:</strong>
                                    <?php echo htmlspecialchars($booking['examiner_name']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <strong>Booked on:</strong>
                                <?php echo formatDate($booking['booking_date'], 'M d, Y'); ?>
                            </div>
                            
                            <?php if ($booking['notes']): ?>
                                <div class="info-item">
                                    <strong>Notes:</strong>
                                    <?php echo htmlspecialchars($booking['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($can_cancel): ?>
                                <a href="booking-cancel.php?id=<?php echo $booking['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                                    Cancel Booking
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'completed'): ?>
                                <a href="results.php" class="btn btn-primary btn-sm">
                                    View Result
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <p class="text-center">You have no exam bookings yet.</p>
            <div class="text-center mt-2">
                <a href="book-exam.php" class="btn btn-primary">Book Your First Exam</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
