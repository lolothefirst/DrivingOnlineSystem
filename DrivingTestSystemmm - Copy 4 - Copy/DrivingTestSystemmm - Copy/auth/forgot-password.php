<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $query = "SELECT id, username, full_name FROM users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            
            // Generate reset token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Update user with reset token
            $update = "UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id";
            $stmt = $conn->prepare($update);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiry', $expiry);
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();
            
            // Send reset email
            $reset_link = SITE_URL . "/auth/reset-password.php?token=" . $token;
            $email_subject = "Password Reset Request";
            $email_message = "
                <h2>Password Reset Request</h2>
                <p>Hi {$user['full_name']},</p>
                <p>You requested to reset your password. Click the link below to reset:</p>
                <p><a href='{$reset_link}'>{$reset_link}</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";
            
            sendEmail($email, $email_subject, $email_message);
            
            $message = "Password reset instructions have been sent to your email";
        } else {
            $error = "No account found with that email address";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Forgot Password</h1>
                <p>Enter your email to reset your password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            
            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
