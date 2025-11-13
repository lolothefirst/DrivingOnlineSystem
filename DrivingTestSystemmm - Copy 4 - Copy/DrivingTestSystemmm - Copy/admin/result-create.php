<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get completed bookings without results
$query = "SELECT eb.*, u.full_name, u.id as user_id, es.exam_type, es.exam_date, es.exam_time
          FROM exam_bookings eb
          JOIN users u ON eb.user_id = u.id
          JOIN exam_sessions es ON eb.session_id = es.id
          WHERE eb.status = 'confirmed' 
          AND NOT EXISTS (SELECT 1 FROM exam_results WHERE booking_id = eb.id)
          AND es.exam_date <= CURDATE()
          ORDER BY es.exam_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$bookings = $stmt->fetchAll();

// Get examiners
$query = "SELECT * FROM examiners WHERE is_active = 1 ORDER BY full_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$examiners = $stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = intval($_POST['booking_id']);
    $score = floatval($_POST['score']);
    $max_score = floatval($_POST['max_score']);
    $result = sanitizeInput($_POST['result']);
    $examiner_id = !empty($_POST['examiner_id']) ? intval($_POST['examiner_id']) : null;
    $feedback = sanitizeInput($_POST['feedback']);
    
    // Get booking details
    $query = "SELECT eb.*, es.exam_type, es.exam_date FROM exam_bookings eb 
              JOIN exam_sessions es ON eb.session_id = es.id WHERE eb.id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $booking_id);
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if ($booking) {
        $certificate_number = null;
        if ($result === 'pass') {
            $certificate_number = generateCertificateNumber();
        }
        
        try {
            $conn->beginTransaction();
            
            // Insert result
            $query = "INSERT INTO exam_results (booking_id, user_id, exam_type, score, max_score, result, examiner_id, feedback, certificate_number, exam_date) 
                      VALUES (:booking_id, :user_id, :exam_type, :score, :max_score, :result, :examiner_id, :feedback, :certificate_number, :exam_date)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->bindParam(':user_id', $booking['user_id']);
            $stmt->bindParam(':exam_type', $booking['exam_type']);
            $stmt->bindParam(':score', $score);
            $stmt->bindParam(':max_score', $max_score);
            $stmt->bindParam(':result', $result);
            $stmt->bindParam(':examiner_id', $examiner_id);
            $stmt->bindParam(':feedback', $feedback);
            $stmt->bindParam(':certificate_number', $certificate_number);
            $stmt->bindParam(':exam_date', $booking['exam_date']);
            $stmt->execute();
            
            // Update booking status
            $query = "UPDATE exam_bookings SET status = 'completed' WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $booking_id);
            $stmt->execute();
            
            logActivity($conn, $_SESSION['user_id'], 'Exam result created', 'exam_results', $conn->lastInsertId());
            
            $conn->commit();
            
            $_SESSION['message'] = "Exam result saved successfully!";
            $_SESSION['message_type'] = "success";
            redirect('results-manage.php');
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Failed to save result. Please try again.";
        }
    } else {
        $errors[] = "Booking not found.";
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <a href="results-manage.php" class="btn btn-secondary btn-sm">← Back to Results</a>
        <h1 class="page-title mt-2">Enter Exam Result</h1>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select Exam Booking *</label>
                <select name="booking_id" class="form-select" required onchange="updateBookingInfo(this)">
                    <option value="">Select a booking</option>
                    <?php foreach ($bookings as $booking): ?>
                        <option value="<?php echo $booking['id']; ?>" 
                                data-type="<?php echo $booking['exam_type']; ?>"
                                data-date="<?php echo formatDate($booking['exam_date'], 'M d, Y'); ?>">
                            <?php echo htmlspecialchars($booking['full_name']); ?> - 
                            <?php echo ucfirst($booking['exam_type']); ?> - 
                            <?php echo formatDate($booking['exam_date'], 'M d, Y'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Score *</label>
                    <input type="number" name="score" class="form-input" min="0" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Maximum Score *</label>
                    <input type="number" name="max_score" class="form-input" min="0" step="0.1" value="100" required>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Result *</label>
                    <select name="result" class="form-select" required>
                        <option value="">Select Result</option>
                        <option value="pass">Pass</option>
                        <option value="fail">Fail</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Examiner</label>
                    <select name="examiner_id" class="form-select">
                        <option value="">Select Examiner</option>
                        <?php foreach ($examiners as $examiner): ?>
                            <option value="<?php echo $examiner['id']; ?>">
                                <?php echo htmlspecialchars($examiner['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Feedback</label>
                <textarea name="feedback" class="form-textarea" 
                          placeholder="Enter examiner feedback and comments..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Result</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
