<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get test centers
$query = "SELECT * FROM test_centers WHERE is_active = 1 ORDER BY center_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$centers = $stmt->fetchAll();

// Get examiners
$query = "SELECT * FROM examiners WHERE is_active = 1 ORDER BY full_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$examiners = $stmt->fetchAll();

// Get vehicles
$query = "SELECT * FROM test_vehicles WHERE is_available = 1 ORDER BY plate_number";
$stmt = $conn->prepare($query);
$stmt->execute();
$vehicles = $stmt->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_type = sanitizeInput($_POST['exam_type']);
    $exam_date = sanitizeInput($_POST['exam_date']);
    $exam_time = sanitizeInput($_POST['exam_time']);
    $center_id = intval($_POST['center_id']);
    $examiner_id = !empty($_POST['examiner_id']) ? intval($_POST['examiner_id']) : null;
    $vehicle_id = !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
    $total_slots = intval($_POST['total_slots']);
    $notes = sanitizeInput($_POST['notes']);
    
    if (empty($exam_type) || empty($exam_date) || empty($exam_time) || empty($center_id) || empty($total_slots)) {
        $errors[] = "All required fields must be filled";
    }
    
    if (strtotime($exam_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Exam date must be in the future";
    }
    
    if (empty($errors)) {
        $query = "INSERT INTO exam_sessions (exam_type, exam_date, exam_time, center_id, examiner_id, vehicle_id, total_slots, available_slots, notes) 
                  VALUES (:exam_type, :exam_date, :exam_time, :center_id, :examiner_id, :vehicle_id, :total_slots, :available_slots, :notes)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':exam_type', $exam_type);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->bindParam(':exam_time', $exam_time);
        $stmt->bindParam(':center_id', $center_id);
        $stmt->bindParam(':examiner_id', $examiner_id);
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->bindParam(':total_slots', $total_slots);
        $stmt->bindParam(':available_slots', $total_slots);
        $stmt->bindParam(':notes', $notes);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Exam session created', 'exam_sessions', $conn->lastInsertId());
            $_SESSION['message'] = "Exam session created successfully!";
            $_SESSION['message_type'] = "success";
            redirect('exams.php');
        } else {
            $errors[] = "Failed to create exam session.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <a href="exams.php" class="btn btn-secondary btn-sm">← Back to Exams</a>
        <h1 class="page-title mt-2">Schedule New Exam</h1>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <form method="POST">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Exam Type *</label>
                    <select name="exam_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="theory">Theory Test</option>
                        <option value="practical">Practical Test</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Test Center *</label>
                    <select name="center_id" class="form-select" required>
                        <option value="">Select Center</option>
                        <?php foreach ($centers as $center): ?>
                            <option value="<?php echo $center['id']; ?>">
                                <?php echo htmlspecialchars($center['center_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Exam Date *</label>
                    <input type="date" name="exam_date" class="form-input" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Exam Time *</label>
                    <input type="time" name="exam_time" class="form-input" required>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Examiner</label>
                    <select name="examiner_id" class="form-select">
                        <option value="">Select Examiner</option>
                        <?php foreach ($examiners as $examiner): ?>
                            <option value="<?php echo $examiner['id']; ?>">
                                <?php echo htmlspecialchars($examiner['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Test Vehicle (For Practical)</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>">
                                <?php echo htmlspecialchars($vehicle['plate_number']); ?> - 
                                <?php echo htmlspecialchars($vehicle['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Total Slots *</label>
                <input type="number" name="total_slots" class="form-input" min="1" max="50" value="10" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-textarea" 
                          placeholder="Any additional information about this exam session..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Exam Session</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
