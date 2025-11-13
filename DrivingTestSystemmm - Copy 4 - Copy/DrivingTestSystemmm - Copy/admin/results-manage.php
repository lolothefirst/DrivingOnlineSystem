<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get all exam results
$query = "SELECT er.*, u.full_name, eb.session_id, es.exam_type, es.exam_date
          FROM exam_results er
          JOIN users u ON er.user_id = u.id
          LEFT JOIN exam_bookings eb ON er.booking_id = eb.id
          LEFT JOIN exam_sessions es ON eb.session_id = es.id
          ORDER BY er.exam_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Exam Results Management</h1>
        <a href="result-create.php" class="btn btn-primary">Enter New Result</a>
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
                    <th>Student</th>
                    <th>Exam Type</th>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Result</th>
                    <th>Certificate</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                    <tr>
                        <td><?php echo $result['id']; ?></td>
                        <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                        <td><?php echo ucfirst($result['exam_type']); ?></td>
                        <td><?php echo formatDate($result['exam_date'], 'M d, Y'); ?></td>
                        <td><?php echo $result['score']; ?> / <?php echo $result['max_score']; ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $result['result'] === 'pass' ? 'success' : 
                                    ($result['result'] === 'fail' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($result['result']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($result['certificate_number'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="result-edit.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <a href="result-view.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
