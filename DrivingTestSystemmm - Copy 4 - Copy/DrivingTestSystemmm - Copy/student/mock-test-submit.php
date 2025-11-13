<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

if (!isset($_SESSION['mock_test_questions'])) {
    redirect('mock-test.php');
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

$questions = $_SESSION['mock_test_questions'];
$start_time = $_SESSION['mock_test_start_time'];
$time_taken = time() - $start_time;

$correct_count = 0;
$answers = [];

// Check answers
foreach ($questions as $question) {
    $question_id = $question['id'];
    $selected_answer = isset($_POST['answer_' . $question_id]) ? $_POST['answer_' . $question_id] : null;
    $is_correct = ($selected_answer === $question['correct_answer']);
    
    if ($is_correct) {
        $correct_count++;
    }
    
    $answers[] = [
        'question_id' => $question_id,
        'selected_answer' => $selected_answer,
        'correct_answer' => $question['correct_answer'],
        'is_correct' => $is_correct,
        'explanation' => $question['explanation']
    ];
}

$total_questions = count($questions);
$score_percentage = calculatePercentage($correct_count, $total_questions);

// Save attempt
$query = "INSERT INTO mock_test_attempts (user_id, total_questions, correct_answers, score_percentage, time_taken) 
          VALUES (:user_id, :total_questions, :correct_answers, :score_percentage, :time_taken)";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':total_questions', $total_questions);
$stmt->bindParam(':correct_answers', $correct_count);
$stmt->bindParam(':score_percentage', $score_percentage);
$stmt->bindParam(':time_taken', $time_taken);
$stmt->execute();

$attempt_id = $conn->lastInsertId();

// Save individual answers
foreach ($answers as $answer) {
    $query = "INSERT INTO mock_test_answers (attempt_id, question_id, selected_answer, is_correct) 
              VALUES (:attempt_id, :question_id, :selected_answer, :is_correct)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':attempt_id', $attempt_id);
    $stmt->bindParam(':question_id', $answer['question_id']);
    $stmt->bindParam(':selected_answer', $answer['selected_answer']);
    $stmt->bindParam(':is_correct', $answer['is_correct'], PDO::PARAM_BOOL);
    $stmt->execute();
}

// Store results in session for display
$_SESSION['test_results'] = [
    'total_questions' => $total_questions,
    'correct_answers' => $correct_count,
    'score_percentage' => $score_percentage,
    'time_taken' => $time_taken,
    'answers' => $answers,
    'questions' => $questions
];

// Clear test session
unset($_SESSION['mock_test_questions']);
unset($_SESSION['mock_test_answers']);
unset($_SESSION['mock_test_start_time']);

redirect('mock-test-results.php');
?>
