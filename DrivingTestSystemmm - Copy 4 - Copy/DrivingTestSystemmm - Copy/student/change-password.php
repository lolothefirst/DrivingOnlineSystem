<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password)) {
        $_SESSION['message'] = "Current password is required.";
        $_SESSION['message_type'] = "danger";
        redirect('profile.php');
        exit();
    }
    
    if (empty($new_password)) {
        $_SESSION['message'] = "New password is required.";
        $_SESSION['message_type'] = "danger";
        redirect('profile.php');
        exit();
    }
    
    if (!Validator::validatePassword($new_password)) {
        $_SESSION['message'] = "New password must be at least 8 characters and contain both letters and numbers.";
        $_SESSION['message_type'] = "danger";
        redirect('profile.php');
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = "New passwords do not match.";
        $_SESSION['message_type'] = "danger";
        redirect('profile.php');
        exit();
    }
    
    // Get current password hash
    try {
        $query = "SELECT password FROM users WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['message'] = "Current password is incorrect.";
            $_SESSION['message_type'] = "danger";
            redirect('profile.php');
            exit();
        }
        
        // Check if new password is same as current
        if (password_verify($new_password, $user['password'])) {
            $_SESSION['message'] = "New password must be different from current password.";
            $_SESSION['message_type'] = "danger";
            redirect('profile.php');
            exit();
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'Password changed');
            $_SESSION['message'] = "Password changed successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to change password. Please try again.";
            $_SESSION['message_type'] = "danger";
        }
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred. Please try again.";
        $_SESSION['message_type'] = "danger";
    }
}

redirect('profile.php');
?>
