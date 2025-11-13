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

// Fetch user's registered license
$stmt = $conn->prepare("SELECT * FROM driving_licenses WHERE user_id = ?");
$stmt->execute([$user_id]);
$license = $stmt->fetch();

// If no license registered, redirect to registration page
if (!$license) {
    $_SESSION['error'] = 'Please register your license first before renewing.';
    header('Location: license-register.php');
    exit();
}

$errors = [];
$form_data = [];

// JPJ license renewal fees
$renewal_fees = [
    '1_year' => 30.00,
    '3_years' => 80.00,
    '5_years' => 110.00
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $renewal_period = $_POST['renewal_period'] ?? '';
    
    // Validation
    if (empty($renewal_period)) {
        $errors[] = "Please select a renewal period";
    } elseif (!array_key_exists($renewal_period, $renewal_fees)) {
        $errors[] = "Invalid renewal period selected";
    }
    
    // Store form data for repopulation
    $form_data = [
        'renewal_period' => $renewal_period
    ];
    
    // If no errors, redirect to payment
    if (empty($errors)) {
        $amount = $renewal_fees[$renewal_period];
        
        // Get user's IC number from users table
        $stmt = $conn->prepare("SELECT id_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $ic_number = $user['id_number'] ?? '';
        
        // Use the license class from registered license
        $_SESSION['license_renewal_data'] = [
            'license_id' => $license['id'],
            'ic_number' => str_replace('-', '', $ic_number),
            'license_number' => strtoupper($license['license_number']),
            'license_types' => $license['license_class'], // Use registered license class
            'renewal_period' => $renewal_period,
            'amount' => $amount
        ];
        
        header('Location: license-payment.php');
        exit();
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <div class="jpj-logo">🪪</div>
        <h1>Driving License Renewal (Lesen Memandu)</h1>
        <p>Jabatan Pengangkutan Jalan Malaysia (JPJ)</p>
    </div>
    
    <!-- Display validation errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>⚠️ Please correct the following errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="renewal-card">
        <h2>Renew Your License</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Display registered license details -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h4>Your Registered License</h4>
            <div class="vehicle-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div>
                    <strong>License Number:</strong>
                    <div><?php echo htmlspecialchars($license['license_number']); ?></div>
                </div>
                <div>
                    <strong>License Class:</strong>
                    <div><?php echo htmlspecialchars($license['license_class']); ?></div>
                </div>
                <div>
                    <strong>Issue Date:</strong>
                    <div><?php echo date('d M Y', strtotime($license['issue_date'])); ?></div>
                </div>
                <div>
                    <strong>Expiry Date:</strong>
                    <div><?php echo date('d M Y', strtotime($license['expiry_date'])); ?></div>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <a href="license-register.php" style="color: #007bff; text-decoration: none; font-size: 14px;">Edit license details</a>
        </div>
        
        <form method="POST" action="" id="licenseForm" novalidate>
            
            <h3 style="margin-top: 30px;">Select Validity Period <span style="color: red;">*</span></h3>
            <div class="renewal-options">
                <label class="option-card <?php echo in_array('Please select a renewal period', $errors) || in_array('Invalid renewal period selected', $errors) ? 'error' : ''; ?>">
                    <input type="radio" 
                           name="renewal_period" 
                           value="1_year"
                           <?php echo (($form_data['renewal_period'] ?? '') === '1_year') ? 'checked' : ''; ?>
                           required>
                    <div class="option-header">
                        <div>
                            <h4>1 Year</h4>
                            <p>Valid for 1 year from expiry date</p>
                        </div>
                        <div class="option-price">RM <?php echo number_format($renewal_fees['1_year'], 2); ?></div>
                    </div>
                </label>
                
                <label class="option-card <?php echo in_array('Please select a renewal period', $errors) || in_array('Invalid renewal period selected', $errors) ? 'error' : ''; ?>">
                    <input type="radio" 
                           name="renewal_period" 
                           value="3_years"
                           <?php echo (($form_data['renewal_period'] ?? '') === '3_years') ? 'checked' : ''; ?>
                           required>
                    <div class="option-header">
                        <div>
                            <h4>3 Years (Recommended)</h4>
                            <p>Valid for 3 years - Best value!</p>
                            <small style="color: #28a745;">Save RM <?php echo number_format(($renewal_fees['1_year'] * 3) - $renewal_fees['3_years'], 2); ?></small>
                        </div>
                        <div class="option-price">RM <?php echo number_format($renewal_fees['3_years'], 2); ?></div>
                    </div>
                </label>
                
                <label class="option-card <?php echo in_array('Please select a renewal period', $errors) || in_array('Invalid renewal period selected', $errors) ? 'error' : ''; ?>">
                    <input type="radio" 
                           name="renewal_period" 
                           value="5_years"
                           <?php echo (($form_data['renewal_period'] ?? '') === '5_years') ? 'checked' : ''; ?>
                           required>
                    <div class="option-header">
                        <div>
                            <h4>5 Years (Maximum)</h4>
                            <p>Valid for 5 years - Maximum period</p>
                            <small style="color: #28a745;">Save RM <?php echo number_format(($renewal_fees['1_year'] * 5) - $renewal_fees['5_years'], 2); ?></small>
                        </div>
                        <div class="option-price">RM <?php echo number_format($renewal_fees['5_years'], 2); ?></div>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="btn-proceed">Proceed to Payment</button>
        </form>
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

.form-control.error {
    border-color: #dc3545;
    background-color: #fff5f5;
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

.license-type-card:hover {
    border-color: #007bff !important;
    background-color: #f8f9fa;
}

.license-type-card.error {
    border-color: #dc3545 !important;
    background-color: #fff5f5;
}

.license-type-card input[type="checkbox"]:checked + div .license-badge {
    color: #28a745 !important;
}

.option-card {
    cursor: pointer;
    padding: 20px;
    border: 2px solid #ddd;
    border-radius: 12px;
    margin-bottom: 15px;
    transition: all 0.3s;
    display: block;
}

.option-card:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.option-card.error {
    border-color: #dc3545;
    background-color: #fff5f5;
}

.option-card input[type="radio"]:checked + .option-header {
    color: #007bff;
}

.option-card input[type="radio"] {
    margin-right: 10px;
}

.option-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.option-price {
    font-weight: bold;
    color: #28a745;
    font-size: 18px;
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
}

.btn-proceed:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}
</style>

<script>
// Form validation
document.getElementById('licenseForm').addEventListener('submit', function(e) {
    let hasErrors = false;
    
    // Check renewal period
    const renewalPeriod = document.querySelector('input[name="renewal_period"]:checked');
    const optionCards = document.querySelectorAll('.option-card');
    if (!renewalPeriod) {
        optionCards.forEach(card => card.classList.add('error'));
        hasErrors = true;
    } else {
        optionCards.forEach(card => card.classList.remove('error'));
    }
    
    if (hasErrors) {
        e.preventDefault();
        alert('Please select a renewal period.');
        // Scroll to first error
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>