<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get material details
$query = "SELECT * FROM learning_materials WHERE id = :id AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $material_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    redirect('learning.php');
}

$material = $stmt->fetch();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <a href="learning.php" class="btn btn-secondary btn-sm">← Back to Learning</a>
        <h1 class="page-title mt-2"><?php echo htmlspecialchars($material['title']); ?></h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($material['description']); ?></p>
    </div>
    
    <div class="card">
        <?php if ($material['material_type'] === 'video' && $material['video_url']): ?>
            <div class="video-container">
                <iframe src="<?php echo htmlspecialchars($material['video_url']); ?>" 
                        frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        
        <?php if ($material['material_type'] === 'pdf' && $material['file_path']): ?>
            <div class="pdf-container">
                <embed src="../<?php echo htmlspecialchars($material['file_path']); ?>" 
                       type="application/pdf" width="100%" height="800px">
            </div>
        <?php endif; ?>
        
        <?php if ($material['content']): ?>
            <div class="material-content">
                <?php echo nl2br(htmlspecialchars($material['content'])); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
