<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/validation.php';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation using Validator class
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!Validator::validateUsername($username)) {
        $errors[] = "Username must be 3-20 characters and contain only letters, numbers, underscores, or dashes";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!Validator::validateEmail($email)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!Validator::validatePassword($password)) {
        $errors[] = "Password must be at least 8 characters and contain both letters and numbers";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (!Validator::lengthBetween($full_name, 2, 100)) {
        $errors[] = "Full name must be between 2 and 100 characters";
    }
    
    if (empty($id_number)) {
        $errors[] = "IC/ID number is required";
    } elseif (!Validator::validateIC($id_number)) {
        $errors[] = "Please enter a valid Malaysian IC number (12 digits)";
    }
    
    if (!empty($phone) && !Validator::validatePhone($phone)) {
        $errors[] = "Please enter a valid Malaysian phone number";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists";
        }
    }
    
    // Insert new user
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Sanitize inputs
            $username = Validator::sanitize($username);
            $email = Validator::sanitize($email);
            $full_name = Validator::sanitize($full_name);
            $id_number = preg_replace('/[^0-9]/', '', $id_number); // Clean IC number
            $phone = !empty($phone) ? Validator::sanitize($phone) : null;
            $address = !empty($address) ? Validator::cleanText($address) : null;
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password, full_name, id_number, phone, address, user_type) 
                      VALUES (:username, :email, :password, :full_name, :id_number, :phone, :address, 'student')";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':id_number', $id_number);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success = "Registration successful! You can now login.";
                // Clear form
                $_POST = [];
            } else {
                throw new Exception("Failed to insert user");
            }
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Register for your driving test</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-input" 
                               value="<?php echo $_POST['username'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-input" 
                           value="<?php echo $_POST['full_name'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ID Number *</label>
                    <input type="text" name="id_number" class="form-input" 
                           value="<?php echo $_POST['id_number'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?php echo $_POST['phone'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-textarea"><?php echo $_POST['address'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
            
            <div class="auth-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>
