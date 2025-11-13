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

// Get all exam results
$query = "SELECT er.*, e.full_name as examiner_name, eb.booking_date
          FROM exam_results er
          LEFT JOIN examiners e ON er.examiner_id = e.id
          LEFT JOIN exam_bookings eb ON er.booking_id = eb.id
          WHERE er.user_id = :user_id
          ORDER BY er.exam_date DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$results = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">My Exam Results</h1>
    </div>
    
    <?php if (count($results) > 0): ?>
        <div class="results-grid">
            <?php foreach ($results as $result): ?>
                <div class="card result-card">
                    <div class="result-header">
                        <h3><?php echo ucfirst($result['exam_type']); ?> Test</h3>
                        <span class="badge badge-<?php echo $result['result'] === 'pass' ? 'success' : ($result['result'] === 'fail' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($result['result']); ?>
                        </span>
                    </div>
                    
                    <div class="result-score">
                        <div class="score-display">
                            <span class="score-number"><?php echo $result['score']; ?></span>
                            <span class="score-max">/ <?php echo $result['max_score']; ?></span>
                        </div>
                        <div class="score-percentage">
                            <?php echo round(($result['score'] / $result['max_score']) * 100, 2); ?>%
                        </div>
                    </div>
                    
                    <div class="result-details">
                        <div class="detail-item">
                            <strong>Exam Date:</strong>
                            <?php echo formatDate($result['exam_date'], 'M d, Y'); ?>
                        </div>
                        
                        <?php if ($result['examiner_name']): ?>
                            <div class="detail-item">
                                <strong>Examiner:</strong>
                                <?php echo htmlspecialchars($result['examiner_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($result['certificate_number']): ?>
                            <div class="detail-item">
                                <strong>Certificate Number:</strong>
                                <?php echo htmlspecialchars($result['certificate_number']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($result['feedback']): ?>
                            <div class="detail-item">
                                <strong>Feedback:</strong>
                                <p><?php echo nl2br(htmlspecialchars($result['feedback'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($result['result'] === 'pass' && $result['certificate_number']): ?>
                        <a href="certificate.php?id=<?php echo $result['id']; ?>" class="btn btn-primary btn-sm" target="_blank">
                            Download Certificate
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <p class="text-center">No exam results available yet.</p>
            <div class="text-center mt-2">
                <a href="book-exam.php" class="btn btn-primary">Book an Exam</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
