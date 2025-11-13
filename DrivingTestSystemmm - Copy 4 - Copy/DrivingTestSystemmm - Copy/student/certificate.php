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

$result_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get result details
$query = "SELECT er.*, u.full_name, u.id_number, e.full_name as examiner_name
          FROM exam_results er
          JOIN users u ON er.user_id = u.id
          LEFT JOIN examiners e ON er.examiner_id = e.id
          WHERE er.id = :id AND er.user_id = :user_id AND er.result = 'pass'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $result_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    redirect('results.php');
}

$result = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/certificate.css">
</head>
<body>
    <div class="certificate-container">
        <div class="certificate">
            <div class="certificate-border">
                <div class="certificate-content">
                    <div class="certificate-header">
                        <h1><?php echo SITE_NAME; ?></h1>
                        <h2>Certificate of Achievement</h2>
                    </div>
                    
                    <div class="certificate-body">
                        <p class="certificate-text">This is to certify that</p>
                        <h3 class="certificate-name"><?php echo htmlspecialchars($result['full_name']); ?></h3>
                        <p class="certificate-id">ID: <?php echo htmlspecialchars($result['id_number']); ?></p>
                        
                        <p class="certificate-text">has successfully completed the</p>
                        <h4 class="certificate-exam"><?php echo ucfirst($result['exam_type']); ?> Driving Test</h4>
                        
                        <p class="certificate-text">with a score of</p>
                        <div class="certificate-score">
                            <?php echo $result['score']; ?> / <?php echo $result['max_score']; ?>
                        </div>
                    </div>
                    
                    <div class="certificate-footer">
                        <div class="certificate-detail">
                            <strong>Certificate Number:</strong><br>
                            <?php echo htmlspecialchars($result['certificate_number']); ?>
                        </div>
                        
                        <div class="certificate-detail">
                            <strong>Date of Issue:</strong><br>
                            <?php echo formatDate($result['exam_date'], 'F d, Y'); ?>
                        </div>
                        
                        <?php if ($result['examiner_name']): ?>
                            <div class="certificate-detail">
                                <strong>Examiner:</strong><br>
                                <?php echo htmlspecialchars($result['examiner_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="certificate-actions">
            <button onclick="window.print()" class="btn btn-primary">Print Certificate</button>
            <a href="results.php" class="btn btn-secondary">Back to Results</a>
        </div>
    </div>
</body>
</html>
