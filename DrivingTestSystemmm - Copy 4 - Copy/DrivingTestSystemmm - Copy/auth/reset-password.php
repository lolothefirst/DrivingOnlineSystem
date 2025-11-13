<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';
$valid_token = false;
$user_id = null;

// Check token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $query = "SELECT id FROM users WHERE reset_token = :token AND reset_token_expiry > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $valid_token = true;
        $user = $stmt->fetch();
        $user_id = $user['id'];
    } else {
        $error = "Invalid or expired reset token";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            $success = "Password reset successful! You can now login with your new password.";
            $valid_token = false;
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Reset Password</h1>
                <p>Enter your new password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p class="mt-2"><a href="login.php">Click here to login</a></p>
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" action="" class="auth-form">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-input" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
