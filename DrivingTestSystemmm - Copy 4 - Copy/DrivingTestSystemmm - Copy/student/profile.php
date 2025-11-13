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

$success = '';
$error = '';

// Get user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/validation.php';
    
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $photo_path = $user['photo_path'];
    
    // Validation
    if (empty($full_name)) {
        $error = "Full name is required";
    } elseif (!Validator::lengthBetween($full_name, 2, 100)) {
        $error = "Full name must be between 2 and 100 characters";
    }
    
    if (empty($error) && !empty($phone) && !Validator::validatePhone($phone)) {
        $error = "Please enter a valid Malaysian phone number";
    }
    
    // Handle photo upload
    if (empty($error) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], ['jpg', 'jpeg', 'png']);
        
        if ($upload_result['success']) {
            $photo_path = 'uploads/' . $upload_result['filename'];
            
            // Delete old photo
            if ($user['photo_path'] && file_exists('../' . $user['photo_path'])) {
                @unlink('../' . $user['photo_path']);
            }
        } else {
            $error = $upload_result['message'];
        }
    }
    
    if (empty($error)) {
        try {
            // Sanitize inputs
            $full_name = Validator::sanitize($full_name);
            $phone = !empty($phone) ? Validator::sanitize($phone) : null;
            $address = !empty($address) ? Validator::cleanText($address) : null;
            
            $query = "UPDATE users SET full_name = :full_name, phone = :phone, address = :address, photo_path = :photo_path WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':photo_path', $photo_path);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch();
                
                logActivity($conn, $user_id, 'Profile updated');
            } else {
                $error = "Failed to update profile.";
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = "Failed to update profile. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">My Profile</h1>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">Profile Information</div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Profile Photo</label>
                    <?php if ($user['photo_path']): ?>
                        <img src="../<?php echo htmlspecialchars($user['photo_path']); ?>" 
                             alt="Profile Photo" class="profile-photo-preview">
                    <?php endif; ?>
                    <input type="file" name="photo" class="form-input" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ID Number</label>
                    <input type="text" class="form-input" 
                           value="<?php echo htmlspecialchars($user['id_number']); ?>" disabled>
                    <small>ID number cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-textarea"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Change Password</div>
            
            <form method="POST" action="change-password.php">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
            
            <div class="account-info mt-3">
                <h3>Account Information</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Account Status:</strong> 
                    <span class="badge badge-success"><?php echo ucfirst($user['status']); ?></span>
                </p>
                <p><strong>Member Since:</strong> <?php echo formatDate($user['created_at'], 'M d, Y'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
