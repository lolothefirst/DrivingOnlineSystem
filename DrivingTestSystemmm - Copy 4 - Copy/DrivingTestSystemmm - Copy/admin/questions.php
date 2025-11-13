<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get all questions
$query = "SELECT * FROM questions ORDER BY category, created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$questions = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Question Bank</h1>
        <a href="question-create.php" class="btn btn-primary">Add New Question</a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><?php echo $question['id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 60)) . '...'; ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                        <td><?php echo htmlspecialchars($question['category']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $question['difficulty'] === 'easy' ? 'success' : 
                                    ($question['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($question['difficulty']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $question['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $question['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="question-edit.php?id=<?php echo $question['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <a href="question-delete.php?id=<?php echo $question['id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
