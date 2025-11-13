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

$session_id = isset($_GET['session']) ? intval($_GET['session']) : 0;

// Get session details
$query = "SELECT es.*, tc.center_name, tc.city, tc.address, tc.phone, e.full_name as examiner_name, tv.plate_number
          FROM exam_sessions es
          JOIN test_centers tc ON es.center_id = tc.id
          LEFT JOIN examiners e ON es.examiner_id = e.id
          LEFT JOIN test_vehicles tv ON es.vehicle_id = tv.id
          WHERE es.id = :session_id AND es.exam_date >= CURDATE() AND es.available_slots > 0";
$stmt = $conn->prepare($query);
$stmt->bindParam(':session_id', $session_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['message'] = "Session not available.";
    $_SESSION['message_type'] = "danger";
    redirect('book-exam.php');
}

$session = $stmt->fetch();

// Check if user already has a booking for this session
$query = "SELECT * FROM exam_bookings WHERE user_id = :user_id AND session_id = :session_id AND status != 'cancelled'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':session_id', $session_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $_SESSION['message'] = "You already have a booking for this session.";
    $_SESSION['message_type'] = "warning";
    redirect('bookings.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = sanitizeInput($_POST['notes']);
    
    // Check slot availability again
    if (isSlotAvailable($conn, $session_id)) {
        try {
            $conn->beginTransaction();
            
            // Create booking
            $query = "INSERT INTO exam_bookings (user_id, session_id, status, notes) 
                      VALUES (:user_id, :session_id, 'confirmed', :notes)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            // Update available slots
            updateAvailableSlots($conn, $session_id, false);
            
            // Log activity
            logActivity($conn, $user_id, 'Exam booked', 'exam_bookings', $conn->lastInsertId());
            
            $conn->commit();
            
            $_SESSION['message'] = "Exam booked successfully!";
            $_SESSION['message_type'] = "success";
            redirect('bookings.php');
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['message'] = "Failed to book exam. Please try again.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "This session is now fully booked.";
        $_SESSION['message_type'] = "warning";
        redirect('book-exam.php');
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <a href="book-exam.php" class="btn btn-secondary btn-sm">← Back to Available Sessions</a>
        <h1 class="page-title mt-2">Confirm Booking</h1>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">Session Details</div>
            
            <div class="detail-row">
                <strong>Exam Type:</strong>
                <span class="badge badge-<?php echo $session['exam_type'] === 'theory' ? 'info' : 'success'; ?>">
                    <?php echo ucfirst($session['exam_type']); ?> Test
                </span>
            </div>
            
            <div class="detail-row">
                <strong>Date:</strong>
                <span><?php echo formatDate($session['exam_date'], 'l, F d, Y'); ?></span>
            </div>
            
            <div class="detail-row">
                <strong>Time:</strong>
                <span><?php echo date('g:i A', strtotime($session['exam_time'])); ?></span>
            </div>
            
            <div class="detail-row">
                <strong>Test Center:</strong>
                <span><?php echo htmlspecialchars($session['center_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <strong>Location:</strong>
                <span>
                    <?php echo htmlspecialchars($session['address']); ?>, 
                    <?php echo htmlspecialchars($session['city']); ?>
                </span>
            </div>
            
            <div class="detail-row">
                <strong>Contact:</strong>
                <span><?php echo htmlspecialchars($session['phone']); ?></span>
            </div>
            
            <?php if ($session['examiner_name']): ?>
                <div class="detail-row">
                    <strong>Examiner:</strong>
                    <span><?php echo htmlspecialchars($session['examiner_name']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($session['plate_number']): ?>
                <div class="detail-row">
                    <strong>Vehicle:</strong>
                    <span><?php echo htmlspecialchars($session['plate_number']); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <strong>Available Slots:</strong>
                <span><?php echo $session['available_slots']; ?></span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Confirm Your Booking</div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Additional Notes (Optional)</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="Any special requirements or notes..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>Please Note:</strong>
                    <ul>
                        <li>Arrive at least 15 minutes before your scheduled time</li>
                        <li>Bring a valid ID and any required documents</li>
                        <li>You can cancel or reschedule up to 24 hours before the exam</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">Confirm Booking</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
