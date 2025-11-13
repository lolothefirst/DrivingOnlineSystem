<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

$id = $_GET['id'] ?? 0;
$errors = [];

// Get material
$query = "SELECT * FROM learning_materials WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$material = $stmt->fetch();

if (!$material) {
    $_SESSION['error'] = "Material not found";
    redirect('learning-materials.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $content = trim($_POST['content']);
    $material_type = $_POST['material_type'];
    $category = trim($_POST['category']);
    $video_url = ''; // video materials removed
    $display_order = intval($_POST['display_order']);
    
    if (empty($title)) $errors[] = "Title is required";
    if (empty($category)) $errors[] = "Category is required";
    
    $file_path = $material['file_path'];
    if ($material_type === 'pdf') {
        if ((!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) && empty($file_path)) {
            // If switching to PDF type and no existing file, require upload
            $errors[] = "PDF file is required for material type 'PDF'";
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
            $upload_result = uploadFile($_FILES['file'], ['pdf'], 'materials');
            if ($upload_result['success']) {
                // Delete old file if exists
                if (!empty($material['file_path'])) {
                    $old_path = '../' . ltrim($material['file_path'], '/');
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
                $file_path = 'uploads/' . $upload_result['path'];
            } else {
                $errors[] = $upload_result['message'] ?? "Failed to upload file";
            }
        }
    }
    
    if (empty($errors)) {
        $query = "UPDATE learning_materials SET title = :title, description = :description, content = :content, 
                  material_type = :material_type, file_path = :file_path, video_url = :video_url, 
                  category = :category, display_order = :display_order WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':material_type', $material_type);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':video_url', $video_url);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':display_order', $display_order);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Update learning material', 'learning_materials', $id);
            $_SESSION['success'] = "Learning material updated successfully!";
            redirect('learning-materials.php');
        } else {
            $errors[] = "Failed to update learning material";
        }
    }
}

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <h1 class="page-title">Edit Learning Material</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data" class="form">
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($material['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="material_type">Material Type *</label>
                    <?php $current_type = $_POST['material_type'] ?? $material['material_type']; ?>
                    <select id="material_type" name="material_type" class="form-control" required>
                        <option value="text" <?php echo $current_type === 'text' ? 'selected' : ''; ?>>Text Content</option>
                        <option value="pdf" <?php echo $current_type === 'pdf' ? 'selected' : ''; ?>>PDF Document</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Category *</label>
                    <input type="text" id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($material['category']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($material['description']); ?></textarea>
                </div>

                <div class="form-group" id="content-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="10"><?php echo htmlspecialchars($material['content']); ?></textarea>
                </div>

                <!-- video materials removed -->

                <div class="form-group" id="file-group">
                    <label for="file">PDF File</label>
                    <?php if ($material['file_path']): ?>
                        <p>Current file: <?php echo basename($material['file_path']); ?></p>
                    <?php endif; ?>
                    <input type="file" id="file" name="file" class="form-control" accept=".pdf">
                </div>

                <div class="form-group" id="file-preview-container" style="display: none;">
                    <label>File Preview</label>
                    <iframe id="file-preview-frame" class="pdf-preview" title="PDF preview" loading="lazy"></iframe>
                </div>

                <div class="form-group">
                    <label for="display_order">Display Order</label>
                    <input type="number" id="display_order" name="display_order" class="form-control" value="<?php echo $material['display_order']; ?>" min="0">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Material</button>
                    <a href="learning-materials.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const typeSelect = document.getElementById('material_type');
    const contentGroup = document.getElementById('content-group');
    const videoGroup = document.getElementById('video-group');
    const fileGroup = document.getElementById('file-group');
    const fileInput = document.getElementById('file');
    const previewContainer = document.getElementById('file-preview-container');
    const previewFrame = document.getElementById('file-preview-frame');
    const existingFile = <?php echo $material['file_path'] ? json_encode('../' . ltrim($material['file_path'], '/')) : 'null'; ?>;
    let previewUrl = null;

    function updateFormDisplay() {
        const type = typeSelect.value;
        contentGroup.style.display = type === 'text' ? 'block' : 'none';
        videoGroup.style.display = type === 'video' ? 'block' : 'none';
        fileGroup.style.display = type === 'pdf' ? 'block' : 'none';
        if (type !== 'pdf') {
            clearPreview();
        } else if (!previewUrl && existingFile) {
            previewFrame.src = existingFile;
            previewContainer.style.display = 'block';
        }
    }

    function clearPreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        previewFrame.removeAttribute('src');
        previewContainer.style.display = 'none';
    }

    function showPreview(file) {
        if (!file || file.type !== 'application/pdf') {
            if (existingFile) {
                previewFrame.src = existingFile;
                previewContainer.style.display = typeSelect.value === 'pdf' ? 'block' : 'none';
            } else {
                clearPreview();
            }
            return;
        }
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(file);
        previewFrame.src = previewUrl;
        previewContainer.style.display = 'block';
    }

    typeSelect.addEventListener('change', updateFormDisplay);

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            showPreview(this.files[0]);
        });
    }

    window.addEventListener('beforeunload', function() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    });

    if (existingFile && typeSelect.value === 'pdf') {
        previewFrame.src = existingFile;
        previewContainer.style.display = 'block';
    }

    updateFormDisplay();
})();
</script>

<?php include 'includes/footer.php'; ?>
