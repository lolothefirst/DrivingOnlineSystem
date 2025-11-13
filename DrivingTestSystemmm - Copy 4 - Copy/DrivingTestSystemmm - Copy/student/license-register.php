<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Malaysian states for dropdown
$states = [
    'JHR' => 'Johor',
    'KDH' => 'Kedah', 
    'KTN' => 'Kelantan',
    'MLK' => 'Melaka',
    'NSN' => 'Negeri Sembilan',
    'PHG' => 'Pahang',
    'PRK' => 'Perak',
    'PLS' => 'Perlis',
    'PNG' => 'Pulau Pinang',
    'SBH' => 'Sabah',
    'SWK' => 'Sarawak',
    'SGR' => 'Selangor',
    'TRG' => 'Terengganu',
    'WP' => 'Wilayah Persekutuan'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_number = strtoupper(trim(Validator::sanitize($_POST['license_number'] ?? '')));
    $license_class = Validator::sanitize($_POST['license_class'] ?? '');
    $issue_date = Validator::sanitize($_POST['issue_date'] ?? '');
    $expiry_date = Validator::sanitize($_POST['expiry_date'] ?? '');
    
    // Store form data for repopulation
    $form_data = [
        'license_number' => htmlspecialchars($license_number),
        'license_class' => htmlspecialchars($license_class),
        'issue_date' => htmlspecialchars($issue_date),
        'expiry_date' => htmlspecialchars($expiry_date)
    ];
    
    // Validate all inputs with field-specific errors
    if (empty($license_number)) {
        $errors['license_number'] = 'License number is required';
    } elseif (strlen($license_number) < 5 || strlen($license_number) > 50) {
        $errors['license_number'] = 'Must be between 5 and 50 characters';
    } else {
        // Check if user already has a license registered
        $stmt = $conn->prepare("SELECT id FROM driving_licenses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            $errors['general'] = 'You already have a license registered. You can only register one license per account.';
        } else {
            // Check for duplicate license number across all users
            $stmt = $conn->prepare("SELECT id FROM driving_licenses WHERE license_number = ?");
            $stmt->execute([$license_number]);
            if ($stmt->fetch()) {
                $errors['license_number'] = 'This license number is already registered';
            }
        }
    }
    
    if (empty($license_class)) {
        $errors['license_class'] = 'Please select a license class';
    }
    
    if (empty($issue_date)) {
        $errors['issue_date'] = 'Issue date is required';
    }
    
    if (empty($expiry_date)) {
        $errors['expiry_date'] = 'Expiry date is required';
    } elseif (!empty($issue_date) && strtotime($expiry_date) <= strtotime($issue_date)) {
        $errors['expiry_date'] = 'Expiry date must be after issue date';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO driving_licenses (user_id, license_number, license_class, issue_date, expiry_date, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$user_id, $license_number, $license_class, $issue_date, $expiry_date]);
            
            // Log activity
            if (function_exists('logActivity')) {
                logActivity($conn, $user_id, 'License registration', 'driving_licenses', $conn->lastInsertId());
            }
            
            $success = 'License registered successfully! You can now renew your license.';
            
            // Clear form by redirecting
            $_SESSION['success'] = $success;
            header('Location: license-register.php');
            exit();
        } catch (Exception $e) {
            $errors['general'] = 'Failed to register license. Please try again.';
            error_log("License registration error: " . $e->getMessage());
        }
    }
} else {
    $form_data = [
        'license_number' => '',
        'license_class' => '',
        'issue_date' => '',
        'expiry_date' => ''
    ];
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Fetch user's license
$stmt = $conn->prepare("SELECT * FROM driving_licenses WHERE user_id = ?");
$stmt->execute([$user_id]);
$license = $stmt->fetch();

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <div class="jpj-logo">🪪</div>
        <h1>License Registration</h1>
        <p>Register your driving license for renewal</p>
    </div>
    
    <div class="renewal-card">
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #721c24;">
                <strong>Error:</strong> <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #155724;">
                <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($license): ?>
            <h2>Your Registered License</h2>
            <div class="vehicle-card" style="margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h4>License Number: <?php echo htmlspecialchars($license['license_number']); ?></h4>
                <div class="vehicle-info">
                    <div class="info-item">
                        <span class="info-label">License Class</span>
                        <span class="info-value"><?php echo htmlspecialchars($license['license_class']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Issue Date</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($license['issue_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Expiry Date</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($license['expiry_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value"><?php echo ucfirst($license['status']); ?></span>
                    </div>
                </div>
            </div>
            <a href="license-renewal.php" class="btn-proceed" style="text-align: center; display: block; text-decoration: none;">Renew License</a>
        <?php else: ?>
            <h2>Register New License</h2>
            <form method="POST" id="licenseForm" style="max-width: 600px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">License Number *</label>
                    <input type="text" 
                           name="license_number" 
                           placeholder="e.g., D123456789" 
                           required 
                           class="form-control <?php echo isset($errors['license_number']) ? 'error-field' : ''; ?>" 
                           style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['license_number']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px; text-transform: uppercase;"
                           minlength="5"
                           maxlength="50"
                           value="<?php echo htmlspecialchars($form_data['license_number']); ?>">
                    <?php if (isset($errors['license_number'])): ?>
                        <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['license_number']); ?></small>
                    <?php else: ?>
                        <small style="color: #666; font-size: 12px;">Your driving license number</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">License Class *</label>
                    <select name="license_class" 
                            required 
                            class="form-control <?php echo isset($errors['license_class']) ? 'error-field' : ''; ?>" 
                            style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['license_class']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;">
                        <option value="">Select license class</option>
                        <option value="B2" <?php echo ($form_data['license_class'] === 'B2') ? 'selected' : ''; ?>>B2 - Motorcycle (≤ 500cc)</option>
                        <option value="B" <?php echo ($form_data['license_class'] === 'B') ? 'selected' : ''; ?>>B - Motorcycle (Full)</option>
                        <option value="D" <?php echo ($form_data['license_class'] === 'D') ? 'selected' : ''; ?>>D - Car (Manual)</option>
                        <option value="DA" <?php echo ($form_data['license_class'] === 'DA') ? 'selected' : ''; ?>>DA - Car (Auto)</option>
                        <option value="D/DA" <?php echo ($form_data['license_class'] === 'D/DA') ? 'selected' : ''; ?>>D/DA - Car (Both)</option>
                        <option value="B2/D" <?php echo ($form_data['license_class'] === 'B2/D') ? 'selected' : ''; ?>>B2/D - Motorcycle & Car</option>
                        <option value="B2/DA" <?php echo ($form_data['license_class'] === 'B2/DA') ? 'selected' : ''; ?>>B2/DA - Motorcycle & Car (Auto)</option>
                        <option value="B2/D/DA" <?php echo ($form_data['license_class'] === 'B2/D/DA') ? 'selected' : ''; ?>>B2/D/DA - All Classes</option>
                    </select>
                    <?php if (isset($errors['license_class'])): ?>
                        <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['license_class']); ?></small>
                    <?php else: ?>
                        <small style="color: #666; font-size: 12px;">Select your license class(es)</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Issue Date *</label>
                        <input type="date" 
                               name="issue_date" 
                               required 
                               class="form-control <?php echo isset($errors['issue_date']) ? 'error-field' : ''; ?>" 
                               style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['issue_date']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;"
                               max="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars($form_data['issue_date']); ?>">
                        <?php if (isset($errors['issue_date'])): ?>
                            <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['issue_date']); ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Expiry Date *</label>
                        <input type="date" 
                               name="expiry_date" 
                               required 
                               class="form-control <?php echo isset($errors['expiry_date']) ? 'error-field' : ''; ?>" 
                               style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['expiry_date']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;"
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars($form_data['expiry_date']); ?>">
                        <?php if (isset($errors['expiry_date'])): ?>
                            <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['expiry_date']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-proceed" onclick="return confirmRegistration(event)">Register License</button>
                <a href="license-renewal.php" class="btn-secondary" style="text-align: center; display: block; text-decoration: none; margin-top: 10px;">Go to License Renewal</a>
            </form>
            
            <!-- Confirmation Modal -->
            <div id="confirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; flex-direction: column;">
                <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; margin: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                    <h3 style="margin-top: 0; color: #dc3545;">⚠️ Important Notice</h3>
                    <p style="line-height: 1.6; margin-bottom: 20px;">
                        Once you register this license, you will <strong>not be allowed to change the details</strong> of it. 
                        Please ensure all information is correct before proceeding.
                    </p>
                    <p style="line-height: 1.6; margin-bottom: 20px; color: #666;">
                        If you encounter any trouble or need to make changes, please call our hotline for assistance.
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="closeConfirmModal()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                        <button onclick="proceedWithRegistration()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm & Register</button>
                    </div>
                </div>
            </div>
            
            <script>
            function confirmRegistration(e) {
                e.preventDefault();
                const modal = document.getElementById('confirmModal');
                modal.style.display = 'flex';
                return false;
            }
            
            function closeConfirmModal() {
                document.getElementById('confirmModal').style.display = 'none';
            }
            
            function proceedWithRegistration() {
                document.getElementById('confirmModal').style.display = 'none';
                document.getElementById('licenseForm').submit();
            }
            </script>
        <?php endif; ?>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.btn-proceed {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: block;
}

.btn-proceed:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.btn-secondary {
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
}

.btn-secondary:hover {
    text-decoration: underline;
}

.vehicle-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.info-value {
    font-weight: 600;
    color: #333;
}
</style>

<?php include 'includes/footer.php'; ?>

