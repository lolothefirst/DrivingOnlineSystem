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
$form_data = [];

// Fetch user's registered vehicles
$stmt = $conn->prepare("SELECT * FROM vehicle_registrations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

// If no vehicles registered, redirect to registration page
if (empty($vehicles)) {
    $_SESSION['error'] = 'Please register your vehicle first before renewing road tax.';
    header('Location: vehicle-registration.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = filter_var($_POST['vehicle_id'] ?? 0, FILTER_VALIDATE_INT);
    $renewal_period = $_POST['renewal_period'] ?? '';
    
    // Validation
    if (empty($vehicle_id)) {
        $errors[] = "Please select a vehicle";
    } else {
        // Verify vehicle belongs to user
        $stmt = $conn->prepare("SELECT * FROM vehicle_registrations WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicle_id, $user_id]);
        $selected_vehicle = $stmt->fetch();
        
        if (!$selected_vehicle) {
            $errors[] = "Invalid vehicle selected";
        }
    }
    
    if (empty($renewal_period)) {
        $errors[] = "Please select a renewal period";
    } elseif (!in_array($renewal_period, ['6_months', '12_months'])) {
        $errors[] = "Invalid renewal period selected";
    }
    
    // Store form data for repopulation
    $form_data = [
        'vehicle_id' => $vehicle_id,
        'renewal_period' => $renewal_period
    ];
    
    // If no errors, calculate amount and redirect to payment
    if (empty($errors) && isset($selected_vehicle)) {
        $months = ($renewal_period === '6_months') ? 6 : 12;
        $amount = calculateRoadTaxAmount($selected_vehicle['engine_capacity'], $months);
        
        $_SESSION['roadtax_renewal_data'] = [
            'vehicle_id' => $selected_vehicle['id'],
            'vehicle_number' => strtoupper(str_replace(' ', '', $selected_vehicle['registration_number'])),
            'vehicle_make' => $selected_vehicle['vehicle_make'],
            'vehicle_model' => $selected_vehicle['vehicle_model'],
            'vehicle_year' => $selected_vehicle['vehicle_year'],
            'engine_capacity' => $selected_vehicle['engine_capacity'],
            'renewal_period' => $renewal_period,
            'months' => $months,
            'amount' => $amount
        ];
        
        header('Location: roadtax-payment.php');
        exit();
    }
}

// Calculate road tax amount based on engine capacity (Malaysian JPJ rates)
function calculateRoadTaxAmount($engine_cc, $months = 12) {
    $annual_rate = 0;
    
    if ($engine_cc <= 1000) {
        $annual_rate = 20;
    } elseif ($engine_cc <= 1200) {
        $annual_rate = 55;
    } elseif ($engine_cc <= 1400) {
        $annual_rate = 70;
    } elseif ($engine_cc <= 1600) {
        $annual_rate = 90;
    } elseif ($engine_cc <= 1800) {
        $annual_rate = 200;
    } elseif ($engine_cc <= 2000) {
        $annual_rate = 380;
    } elseif ($engine_cc <= 2500) {
        $annual_rate = 550;
    } elseif ($engine_cc <= 3000) {
        $annual_rate = 990;
    } else {
        $annual_rate = 1500;
    }
    
    return $months == 6 ? ($annual_rate / 2) : $annual_rate;
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <div class="jpj-logo">🚗</div>
        <h1>Road Tax Renewal (Cukai Jalan)</h1>
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
        <h2>Select Vehicle to Renew</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="renewalForm" novalidate>
            <div class="form-group">
                <label for="vehicle_id">Select Vehicle <span style="color: red;">*</span></label>
                <select id="vehicle_id" 
                        name="vehicle_id" 
                        class="form-control <?php echo in_array('Please select a vehicle', $errors) || in_array('Invalid vehicle selected', $errors) ? 'error' : ''; ?>" 
                        required
                        onchange="updateVehicleDetails()">
                    <option value="">-- Select Vehicle --</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?php echo $vehicle['id']; ?>" 
                                data-make="<?php echo htmlspecialchars($vehicle['vehicle_make']); ?>"
                                data-model="<?php echo htmlspecialchars($vehicle['vehicle_model']); ?>"
                                data-year="<?php echo $vehicle['vehicle_year']; ?>"
                                data-capacity="<?php echo $vehicle['engine_capacity']; ?>"
                                <?php echo ($form_data['vehicle_id'] ?? '') == $vehicle['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['vehicle_make'] . ' ' . $vehicle['vehicle_model'] . ' (' . $vehicle['vehicle_year'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666;">Select the vehicle you want to renew road tax for</small>
            </div>
            
            <div id="vehicle-details" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h4>Vehicle Details</h4>
                <div class="vehicle-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Registration Number:</strong>
                        <div id="detail-registration">-</div>
                    </div>
                    <div>
                        <strong>Make & Model:</strong>
                        <div id="detail-make-model">-</div>
                    </div>
                    <div>
                        <strong>Year:</strong>
                        <div id="detail-year">-</div>
                    </div>
                    <div>
                        <strong>Engine Capacity:</strong>
                        <div id="detail-capacity">-</div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="vehicle-registration.php" style="color: #007bff; text-decoration: none; font-size: 14px;">+ Register a new vehicle</a>
            </div>
            
            <h3 style="margin-top: 30px;">Select Renewal Period <span style="color: red;">*</span></h3>
            <div class="renewal-options">
                <label class="option-card <?php echo in_array('Please select a renewal period', $errors) || in_array('Invalid renewal period selected', $errors) ? 'error' : ''; ?>">
                    <input type="radio" 
                           name="renewal_period" 
                           value="6_months" 
                           <?php echo (($form_data['renewal_period'] ?? '') === '6_months') ? 'checked' : ''; ?>
                           required>
                    <div class="option-header">
                        <div>
                            <h4>6 Months</h4>
                            <p>Valid for 6 months from today</p>
                        </div>
                        <div class="option-price" id="price-6months">Calculate</div>
                    </div>
                </label>
                
                <label class="option-card <?php echo in_array('Please select a renewal period', $errors) || in_array('Invalid renewal period selected', $errors) ? 'error' : ''; ?>">
                    <input type="radio" 
                           name="renewal_period" 
                           value="12_months"
                           <?php echo (($form_data['renewal_period'] ?? '') === '12_months') ? 'checked' : ''; ?>
                           required>
                    <div class="option-header">
                        <div>
                            <h4>12 Months (Recommended)</h4>
                            <p>Valid for 1 year from today</p>
                        </div>
                        <div class="option-price" id="price-12months">Calculate</div>
                    </div>
                </label>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <strong>💡 Road Tax Rates (Based on Engine Capacity):</strong>
                <ul style="margin: 10px 0 0 20px; font-size: 14px;">
                    <li>Up to 1000cc: RM20/year</li>
                    <li>1001-1200cc: RM55/year</li>
                    <li>1201-1600cc: RM70-90/year</li>
                    <li>1601-2000cc: RM200-380/year</li>
                    <li>Above 2000cc: RM550+/year</li>
                </ul>
            </div>
            
            <button type="submit" class="btn-proceed">Calculate & Proceed to Payment</button>
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
}
</style>

<script>
function updateVehicleDetails() {
    const vehicleSelect = document.getElementById('vehicle_id');
    const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
    const detailsDiv = document.getElementById('vehicle-details');
    
    if (vehicleSelect.value) {
        const registration = selectedOption.textContent.split(' - ')[0];
        const make = selectedOption.getAttribute('data-make');
        const model = selectedOption.getAttribute('data-model');
        const year = selectedOption.getAttribute('data-year');
        const capacity = selectedOption.getAttribute('data-capacity');
        
        document.getElementById('detail-registration').textContent = registration;
        document.getElementById('detail-make-model').textContent = make + ' ' + model;
        document.getElementById('detail-year').textContent = year;
        document.getElementById('detail-capacity').textContent = capacity + ' cc';
        
        detailsDiv.style.display = 'block';
        calculatePrice(parseInt(capacity));
    } else {
        detailsDiv.style.display = 'none';
        document.getElementById('price-6months').textContent = 'Select vehicle';
        document.getElementById('price-12months').textContent = 'Select vehicle';
    }
}

function calculatePrice(engineCapacity) {
    if (!engineCapacity) {
        document.getElementById('price-6months').textContent = 'Select vehicle';
        document.getElementById('price-12months').textContent = 'Select vehicle';
        return;
    }
    
    const cc = engineCapacity;
    let annualRate = 0;
    
    if (cc <= 1000) annualRate = 20;
    else if (cc <= 1200) annualRate = 55;
    else if (cc <= 1400) annualRate = 70;
    else if (cc <= 1600) annualRate = 90;
    else if (cc <= 1800) annualRate = 200;
    else if (cc <= 2000) annualRate = 380;
    else if (cc <= 2500) annualRate = 550;
    else if (cc <= 3000) annualRate = 990;
    else annualRate = 1500;
    
    document.getElementById('price-6months').textContent = 'RM ' + (annualRate / 2).toFixed(2);
    document.getElementById('price-12months').textContent = 'RM ' + annualRate.toFixed(2);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const vehicleSelect = document.getElementById('vehicle_id');
    if (vehicleSelect.value) {
        updateVehicleDetails();
    }
});

// Form validation
document.getElementById('renewalForm').addEventListener('submit', function(e) {
    const vehicleSelect = document.getElementById('vehicle_id');
    let hasErrors = false;
    
    if (!vehicleSelect.value) {
        vehicleSelect.classList.add('error');
        hasErrors = true;
    } else {
        vehicleSelect.classList.remove('error');
    }
    
    const renewalPeriod = document.querySelector('input[name="renewal_period"]:checked');
    if (!renewalPeriod) {
        document.querySelectorAll('.option-card').forEach(card => card.classList.add('error'));
        hasErrors = true;
    } else {
        document.querySelectorAll('.option-card').forEach(card => card.classList.remove('error'));
    }
    
    if (hasErrors) {
        e.preventDefault();
        alert('Please select a vehicle and renewal period.');
    }
});
</script>

<?php include 'includes/footer.php'; ?>