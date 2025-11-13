<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

if (!isset($_SESSION['test_results'])) {
    redirect('mock-test.php');
}

$results = $_SESSION['test_results'];
$passed = $results['score_percentage'] >= 70;

include 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="test-results-header text-center">
            <h1>Test Results</h1>
            <div class="score-circle <?php echo $passed ? 'pass' : 'fail'; ?>">
                <div class="score-value"><?php echo $results['score_percentage']; ?>%</div>
                <div class="score-label"><?php echo $passed ? 'PASSED' : 'FAILED'; ?></div>
            </div>
            
            <div class="results-summary">
                <div class="summary-item">
                    <div class="summary-value"><?php echo $results['correct_answers']; ?>/<?php echo $results['total_questions']; ?></div>
                    <div class="summary-label">Correct Answers</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo floor($results['time_taken'] / 60); ?>m <?php echo $results['time_taken'] % 60; ?>s</div>
                    <div class="summary-label">Time Taken</div>
                </div>
            </div>
            
            <div class="results-actions">
                <a href="mock-test.php" class="btn btn-primary">Take Another Test</a>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">Detailed Review</div>
        
        <?php foreach ($results['questions'] as $index => $question): ?>
            <?php 
            $answer = $results['answers'][$index];
            ?>
            <div class="review-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <div class="review-header">
                    <span class="question-number">Question <?php echo $index + 1; ?></span>
                    <span class="review-status">
                        <?php if ($answer['is_correct']): ?>
                            <span class="badge badge-success">Correct</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Incorrect</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                
                <div class="answer-review">
                    <p><strong>Your Answer:</strong> <?php echo $answer['selected_answer'] ?? 'Not answered'; ?></p>
                    <p><strong>Correct Answer:</strong> <?php echo $answer['correct_answer']; ?></p>
                    <?php if ($answer['explanation']): ?>
                        <p><strong>Explanation:</strong> <?php echo htmlspecialchars($answer['explanation']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php 
unset($_SESSION['test_results']);
include 'includes/footer.php'; 
?>
