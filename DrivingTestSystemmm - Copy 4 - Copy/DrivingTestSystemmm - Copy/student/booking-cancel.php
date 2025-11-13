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

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get booking details
$query = "SELECT eb.*, es.exam_date, es.exam_time
          FROM exam_bookings eb
          JOIN exam_sessions es ON eb.session_id = es.id
          WHERE eb.id = :id AND eb.user_id = :user_id AND eb.status != 'cancelled'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $booking_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['message'] = "Booking not found or already cancelled.";
    $_SESSION['message_type'] = "danger";
    redirect('bookings.php');
}

$booking = $stmt->fetch();

// Check if cancellation is allowed (at least 24 hours before)
$exam_datetime = strtotime($booking['exam_date'] . ' ' . $booking['exam_time']);
if ($exam_datetime <= strtotime('+24 hours')) {
    $_SESSION['message'] = "Cannot cancel booking less than 24 hours before the exam.";
    $_SESSION['message_type'] = "danger";
    redirect('bookings.php');
}

try {
    $conn->beginTransaction();
    
    // Update booking status
    $query = "UPDATE exam_bookings SET status = 'cancelled' WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $booking_id);
    $stmt->execute();
    
    // Update available slots
    updateAvailableSlots($conn, $booking['session_id'], true);
    
    // Log activity
    logActivity($conn, $user_id, 'Booking cancelled', 'exam_bookings', $booking_id);
    
    $conn->commit();
    
    $_SESSION['message'] = "Booking cancelled successfully.";
    $_SESSION['message_type'] = "success";
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['message'] = "Failed to cancel booking. Please try again.";
    $_SESSION['message_type'] = "danger";
}

redirect('bookings.php');
?>
