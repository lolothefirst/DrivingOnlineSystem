<?php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Admin credentials
$username = 'admin';
$email = 'admin@drivingtest.com';
$password = 'admin123';
$full_name = 'System Administrator';

// Generate password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bindParam(':username', $username);
$check_stmt->bindParam(':email', $email);
$check_stmt->execute();

if ($check_stmt->rowCount() > 0) {
    // Update existing admin
    $admin = $check_stmt->fetch();
    $update_query = "UPDATE users SET `password` = :password, user_type = 'admin', status = 'active' WHERE id = :id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':password', $password_hash);
    $update_stmt->bindParam(':id', $admin['id']);
    
    if ($update_stmt->execute()) {
        echo "Admin account updated successfully!<br>";
    } else {
        echo "Error updating admin account.<br>";
    }
} else {
    // Create new admin
    $insert_query = "INSERT INTO users (username, email, `password`, full_name, user_type, status, created_at) 
                     VALUES (:username, :email, :password, :full_name, 'admin', 'active', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bindParam(':username', $username);
    $insert_stmt->bindParam(':email', $email);
    $insert_stmt->bindParam(':password', $password_hash);
    $insert_stmt->bindParam(':full_name', $full_name);
    
    if ($insert_stmt->execute()) {
        echo "Admin account created successfully!<br>";
    } else {
        echo "Error creating admin account.<br>";
    }
}

echo "<br><strong>Admin Login Credentials:</strong><br>";
echo "Username: admin<br>";
echo "Email: admin@drivingtest.com<br>";
echo "Password: admin123<br>";
echo "<br><a href='auth/login.php'>Go to Login Page</a>";
echo "<br><br><strong>IMPORTANT:</strong> Delete this file (setup-admin.php) after setup for security reasons.";
?>
