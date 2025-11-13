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

// Get previous attempts
$query = "SELECT * FROM mock_test_attempts WHERE user_id = :user_id ORDER BY completed_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$attempts = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Mock Theory Tests</h1>
        <p class="page-subtitle">Practice your knowledge before the real exam</p>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">Start New Mock Test</div>
            
            <div class="test-options">
                <div class="option-card">
                    <h3>Quick Test</h3>
                    <p>10 random questions</p>
                    <a href="mock-test-start.php?questions=10" class="btn btn-primary">Start Test</a>
                </div>
                
                <div class="option-card">
                    <h3>Standard Test</h3>
                    <p>20 random questions</p>
                    <a href="mock-test-start.php?questions=20" class="btn btn-primary">Start Test</a>
                </div>
                
                <div class="option-card">
                    <h3>Full Test</h3>
                    <p>40 random questions</p>
                    <a href="mock-test-start.php?questions=40" class="btn btn-primary">Start Test</a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Test History</div>
            
            <?php if (count($attempts) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Questions</th>
                            <th>Correct</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?php echo formatDate($attempt['completed_at'], 'M d, Y'); ?></td>
                                <td><?php echo $attempt['total_questions']; ?></td>
                                <td><?php echo $attempt['correct_answers']; ?></td>
                                <td>
                                    <span class="badge <?php echo $attempt['score_percentage'] >= 70 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $attempt['score_percentage']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No test attempts yet. Start your first mock test!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
