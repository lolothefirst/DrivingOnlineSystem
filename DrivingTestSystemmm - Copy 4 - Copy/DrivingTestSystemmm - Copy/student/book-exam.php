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

// Get available exam sessions
$query = "SELECT es.*, tc.center_name, tc.city, tc.address, e.full_name as examiner_name
          FROM exam_sessions es
          JOIN test_centers tc ON es.center_id = tc.id
          LEFT JOIN examiners e ON es.examiner_id = e.id
          WHERE es.exam_date >= CURDATE() AND es.available_slots > 0 AND es.status = 'scheduled'
          ORDER BY es.exam_date, es.exam_time";
$stmt = $conn->prepare($query);
$stmt->execute();
$sessions = $stmt->fetchAll();

// Group sessions by exam type
$theory_sessions = [];
$practical_sessions = [];

foreach ($sessions as $session) {
    if ($session['exam_type'] === 'theory') {
        $theory_sessions[] = $session;
    } else {
        $practical_sessions[] = $session;
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Book an Exam</h1>
        <p class="page-subtitle">Select an available exam session</p>
    </div>
    
    <div class="card">
        <div class="card-header">Theory Test Sessions</div>
        
        <?php if (count($theory_sessions) > 0): ?>
            <div class="sessions-grid">
                <?php foreach ($theory_sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-type">
                            <span class="badge badge-info">Theory Test</span>
                        </div>
                        <div class="session-details">
                            <div class="session-date">
                                <strong><?php echo formatDate($session['exam_date'], 'l, F d, Y'); ?></strong>
                            </div>
                            <div class="session-time">
                                <?php echo date('g:i A', strtotime($session['exam_time'])); ?>
                            </div>
                            <div class="session-location">
                                <strong><?php echo htmlspecialchars($session['center_name']); ?></strong><br>
                                <?php echo htmlspecialchars($session['city']); ?>
                            </div>
                            <div class="session-slots">
                                Available Slots: <strong><?php echo $session['available_slots']; ?></strong>
                            </div>
                        </div>
                        <a href="book-exam-confirm.php?session=<?php echo $session['id']; ?>" class="btn btn-primary btn-sm">
                            Book This Slot
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No theory test sessions available at this time.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">Practical Test Sessions</div>
        
        <?php if (count($practical_sessions) > 0): ?>
            <div class="sessions-grid">
                <?php foreach ($practical_sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-type">
                            <span class="badge badge-success">Practical Test</span>
                        </div>
                        <div class="session-details">
                            <div class="session-date">
                                <strong><?php echo formatDate($session['exam_date'], 'l, F d, Y'); ?></strong>
                            </div>
                            <div class="session-time">
                                <?php echo date('g:i A', strtotime($session['exam_time'])); ?>
                            </div>
                            <div class="session-location">
                                <strong><?php echo htmlspecialchars($session['center_name']); ?></strong><br>
                                <?php echo htmlspecialchars($session['city']); ?>
                            </div>
                            <?php if ($session['examiner_name']): ?>
                                <div class="session-examiner">
                                    Examiner: <?php echo htmlspecialchars($session['examiner_name']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="session-slots">
                                Available Slots: <strong><?php echo $session['available_slots']; ?></strong>
                            </div>
                        </div>
                        <a href="book-exam-confirm.php?session=<?php echo $session['id']; ?>" class="btn btn-primary btn-sm">
                            Book This Slot
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No practical test sessions available at this time.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
