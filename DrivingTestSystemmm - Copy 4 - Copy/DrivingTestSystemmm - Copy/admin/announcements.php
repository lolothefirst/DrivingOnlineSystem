<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Handle new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    $announcement_type = sanitizeInput($_POST['announcement_type']);
    $target_audience = sanitizeInput($_POST['target_audience']);
    
    $query = "INSERT INTO announcements (title, content, announcement_type, target_audience, posted_by) 
              VALUES (:title, :content, :type, :audience, :posted_by)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':type', $announcement_type);
    $stmt->bindParam(':audience', $target_audience);
    $stmt->bindParam(':posted_by', $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Announcement posted', 'announcements', $conn->lastInsertId());
        $_SESSION['message'] = "Announcement posted successfully!";
        $_SESSION['message_type'] = "success";
    }
}

// Get all announcements
$query = "SELECT a.*, u.full_name as posted_by_name 
          FROM announcements a
          LEFT JOIN users u ON a.posted_by = u.id
          ORDER BY a.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Announcements</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">Post New Announcement</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="announcement_type" class="form-select" required>
                        <option value="general">General</option>
                        <option value="urgent">Urgent</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="exam">Exam</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_audience" class="form-select" required>
                        <option value="all">All Users</option>
                        <option value="students">Students Only</option>
                        <option value="admins">Admins Only</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Post Announcement</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Recent Announcements</div>
            <div class="announcements-list">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <div class="announcement-header">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <span class="badge badge-<?php 
                                echo $announcement['announcement_type'] === 'urgent' ? 'danger' : 
                                    ($announcement['announcement_type'] === 'exam' ? 'info' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($announcement['announcement_type']); ?>
                            </span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <div class="announcement-meta">
                            <small>Posted by <?php echo htmlspecialchars($announcement['posted_by_name']); ?> on 
                            <?php echo formatDate($announcement['created_at'], 'M d, Y'); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
