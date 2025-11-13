<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get statistics
$stats = [];

// Total students
$query = "SELECT COUNT(*) as count FROM users WHERE user_type = 'student'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_students'] = $stmt->fetch()['count'];

// Total bookings
$query = "SELECT COUNT(*) as count FROM exam_bookings";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch()['count'];

// Upcoming exams
$query = "SELECT COUNT(*) as count FROM exam_sessions WHERE exam_date >= CURDATE() AND status = 'scheduled'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['upcoming_exams'] = $stmt->fetch()['count'];

// Total questions
$query = "SELECT COUNT(*) as count FROM questions WHERE is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_questions'] = $stmt->fetch()['count'];

// Recent bookings
$query = "SELECT eb.*, u.full_name, es.exam_type, es.exam_date, es.exam_time
          FROM exam_bookings eb
          JOIN users u ON eb.user_id = u.id
          JOIN exam_sessions es ON eb.session_id = es.id
          ORDER BY eb.booking_date DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_bookings = $stmt->fetchAll();

// Recent students
$query = "SELECT * FROM users WHERE user_type = 'student' ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_students = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard">
    <div class="container">
        <h1 class="page-title">Admin Dashboard</h1>
        
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="stat-value"><?php echo $stats['upcoming_exams']; ?></div>
                <div class="stat-label">Upcoming Exams</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <div class="stat-value"><?php echo $stats['total_questions']; ?></div>
                <div class="stat-label">Active Questions</div>
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">Recent Bookings</div>
                <?php if (count($recent_bookings) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam Type</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                    <td><?php echo ucfirst($booking['exam_type']); ?></td>
                                    <td><?php echo formatDate($booking['exam_date'], 'M d'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No recent bookings.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">Recent Students</div>
                <?php if (count($recent_students) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo formatDate($student['created_at'], 'M d, Y'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No students registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
