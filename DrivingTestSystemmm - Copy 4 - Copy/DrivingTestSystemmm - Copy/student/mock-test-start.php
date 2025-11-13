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

$num_questions = isset($_GET['questions']) ? intval($_GET['questions']) : 20;

// Get random questions
$query = "SELECT * FROM questions WHERE is_active = 1 ORDER BY RAND() LIMIT :limit";
$stmt = $conn->prepare($query);
$stmt->bindParam(':limit', $num_questions, PDO::PARAM_INT);
$stmt->execute();
$questions = $stmt->fetchAll();

if (count($questions) === 0) {
    $_SESSION['message'] = "No questions available at this time.";
    $_SESSION['message_type'] = "warning";
    redirect('mock-test.php');
}

// Store questions in session
$_SESSION['mock_test_questions'] = $questions;
$_SESSION['mock_test_answers'] = [];
$_SESSION['mock_test_start_time'] = time();

include 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="test-header">
            <h1>Mock Theory Test</h1>
            <div class="test-info">
                <span>Total Questions: <?php echo count($questions); ?></span>
                <span id="timer">Time: 0:00</span>
            </div>
        </div>
    </div>
    
    <form method="POST" action="mock-test-submit.php" id="testForm">
        <?php foreach ($questions as $index => $question): ?>
            <div class="card question-card">
                <div class="question-number">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></div>
                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                
                <div class="options">
                    <?php if ($question['question_type'] === 'true_false'): ?>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="A" required>
                            <span>True</span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="B" required>
                            <span>False</span>
                        </label>
                    <?php else: ?>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="A" required>
                            <span>A. <?php echo htmlspecialchars($question['option_a']); ?></span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="B" required>
                            <span>B. <?php echo htmlspecialchars($question['option_b']); ?></span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="C" required>
                            <span>C. <?php echo htmlspecialchars($question['option_c']); ?></span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="answer_<?php echo $question['id']; ?>" value="D" required>
                            <span>D. <?php echo htmlspecialchars($question['option_d']); ?></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card">
            <button type="submit" class="btn btn-primary btn-lg">Submit Test</button>
        </div>
    </form>
</div>

<script>
let startTime = <?php echo time(); ?>;
let timerInterval = setInterval(function() {
    let elapsed = Math.floor(Date.now() / 1000) - startTime;
    let minutes = Math.floor(elapsed / 60);
    let seconds = elapsed % 60;
    document.getElementById('timer').textContent = 'Time: ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>
