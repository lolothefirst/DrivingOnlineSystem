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

// Popular vehicle makes and models for dropdown
$vehicle_makes = [
    'Perodua' => ['Myvi', 'Axia', 'Bezza', 'Aruz', 'Ativa'],
    'Proton' => ['Saga', 'Persona', 'Iriz', 'Exora', 'X70', 'X50'],
    'Toyota' => ['Vios', 'Camry', 'Corolla', 'Innova', 'Hilux', 'Fortuner'],
    'Honda' => ['City', 'Civic', 'Accord', 'CR-V', 'HR-V', 'Jazz'],
    'Nissan' => ['Almera', 'Teana', 'X-Trail', 'Navara', 'Serena'],
    'Mazda' => ['Mazda2', 'Mazda3', 'CX-3', 'CX-5', 'CX-8'],
    'Mitsubishi' => ['ASX', 'Outlander', 'Triton', 'Pajero'],
    'BMW' => ['320i', '520i', 'X1', 'X3', 'X5'],
    'Mercedes-Benz' => ['A200', 'C200', 'E200', 'GLA', 'GLC'],
    'Audi' => ['A3', 'A4', 'Q3', 'Q5', 'Q7']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = strtoupper(trim(Validator::sanitize($_POST['registration_number'] ?? '')));
    $vehicle_make = Validator::sanitize($_POST['vehicle_make'] ?? '');
    $vehicle_model = Validator::sanitize($_POST['vehicle_model'] ?? '');
    $vehicle_year = filter_var($_POST['vehicle_year'] ?? 0, FILTER_VALIDATE_INT);
    $vehicle_type = Validator::sanitize($_POST['vehicle_type'] ?? '');
    $engine_capacity = filter_var($_POST['engine_capacity'] ?? 0, FILTER_VALIDATE_INT);
    
    // Store form data for repopulation
    $form_data = [
        'registration_number' => htmlspecialchars($registration_number),
        'vehicle_make' => htmlspecialchars($vehicle_make),
        'vehicle_model' => htmlspecialchars($vehicle_model),
        'vehicle_year' => $vehicle_year,
        'vehicle_type' => htmlspecialchars($vehicle_type),
        'engine_capacity' => $engine_capacity
    ];
    
    // Validate all inputs with field-specific errors
    if (empty($registration_number)) {
        $errors['registration_number'] = 'Registration number is required';
    } elseif (!Validator::validateVehicleNumber($registration_number)) {
        $errors['registration_number'] = 'Invalid format. Use Malaysian format (e.g., ABC1234)';
    } else {
        // Check for duplicate registration number across all users
        $stmt = $conn->prepare("SELECT id FROM vehicle_registrations WHERE registration_number = ?");
        $stmt->execute([$registration_number]);
        if ($stmt->fetch()) {
            $errors['registration_number'] = 'This registration number is already registered';
        }
    }
    
    if (empty($vehicle_make)) {
        $errors['vehicle_make'] = 'Please select a vehicle make';
    } elseif (!array_key_exists($vehicle_make, $vehicle_makes)) {
        $errors['vehicle_make'] = 'Invalid vehicle make selected';
    }
    
    if (empty($vehicle_model)) {
        $errors['vehicle_model'] = 'Please select a vehicle model';
    } elseif (!empty($vehicle_make) && !in_array($vehicle_model, $vehicle_makes[$vehicle_make] ?? [])) {
        $errors['vehicle_model'] = 'Invalid vehicle model selected';
    }
    
    if (empty($vehicle_year)) {
        $errors['vehicle_year'] = 'Please select a year';
    } elseif (!Validator::validateYear($vehicle_year)) {
        $errors['vehicle_year'] = 'Invalid year. Must be between 1980 and ' . date('Y');
    }
    
    if (empty($vehicle_type)) {
        $errors['vehicle_type'] = 'Please select a vehicle type';
    } elseif (!in_array($vehicle_type, ['sedan', 'suv', 'mpv', 'hatchback', 'pickup'])) {
        $errors['vehicle_type'] = 'Invalid vehicle type selected';
    }
    
    if (empty($engine_capacity)) {
        $errors['engine_capacity'] = 'Please select engine capacity';
    } elseif (!Validator::validateEngineCapacity($engine_capacity)) {
        $errors['engine_capacity'] = 'Invalid engine capacity';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO vehicle_registrations (user_id, registration_number, vehicle_make, vehicle_model, vehicle_year, vehicle_type, engine_capacity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $registration_number, $vehicle_make, $vehicle_model, $vehicle_year, $vehicle_type, $engine_capacity]);
            
            // Log activity
            logActivity($conn, $user_id, 'Vehicle registration', 'vehicle_registrations', $conn->lastInsertId());
            
            $success = 'Vehicle registered successfully! You can now renew road tax for this vehicle.';
            
            // Clear form by redirecting
            $_SESSION['success'] = $success;
            header('Location: vehicle-registration.php');
            exit();
        } catch (Exception $e) {
            $errors['general'] = 'Failed to register vehicle. Please try again.';
        }
    }
} else {
    $form_data = [
        'registration_number' => '',
        'vehicle_make' => '',
        'vehicle_model' => '',
        'vehicle_year' => '',
        'vehicle_type' => '',
        'engine_capacity' => ''
    ];
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Fetch user's vehicles
$stmt = $conn->prepare("SELECT * FROM vehicle_registrations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <h1>Vehicle Registration</h1>
        <p>Register your vehicles for road tax renewal</p>
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
        
        <h2>Register New Vehicle</h2>
        <form method="POST" id="vehicleForm" style="max-width: 600px;">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Registration Number *</label>
                <input type="text" 
                       name="registration_number" 
                       placeholder="e.g., WXY1234" 
                       required 
                       class="form-control <?php echo isset($errors['registration_number']) ? 'error-field' : ''; ?>" 
                       style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['registration_number']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px; text-transform: uppercase;"
                       value="<?php echo htmlspecialchars($form_data['registration_number']); ?>">
                <?php if (isset($errors['registration_number'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['registration_number']); ?></small>
                <?php else: ?>
                    <small style="color: #666; font-size: 12px;">Malaysian format (e.g., ABC1234, WA1234A)</small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Vehicle Make *</label>
                <select name="vehicle_make" 
                        required 
                        class="form-control <?php echo isset($errors['vehicle_make']) ? 'error-field' : ''; ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['vehicle_make']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;"
                        onchange="updateModels()">
                    <option value="">-- Select Vehicle Make --</option>
                    <?php foreach (array_keys($vehicle_makes) as $make): ?>
                        <option value="<?php echo $make; ?>" <?php echo ($form_data['vehicle_make'] === $make) ? 'selected' : ''; ?>>
                            <?php echo $make; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['vehicle_make'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['vehicle_make']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Vehicle Model *</label>
                <select name="vehicle_model" 
                        required 
                        class="form-control <?php echo isset($errors['vehicle_model']) ? 'error-field' : ''; ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['vehicle_model']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;"
                        id="vehicle_model">
                    <option value="">-- Select Vehicle Model --</option>
                </select>
                <?php if (isset($errors['vehicle_model'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['vehicle_model']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Year *</label>
                <select name="vehicle_year" 
                        required 
                        class="form-control <?php echo isset($errors['vehicle_year']) ? 'error-field' : ''; ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['vehicle_year']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;">
                    <option value="">-- Select Year --</option>
                    <?php for ($year = date('Y'); $year >= 1980; $year--): ?>
                        <option value="<?php echo $year; ?>" <?php echo ($form_data['vehicle_year'] == $year) ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($errors['vehicle_year'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['vehicle_year']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Vehicle Type *</label>
                <select name="vehicle_type" 
                        required 
                        class="form-control <?php echo isset($errors['vehicle_type']) ? 'error-field' : ''; ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['vehicle_type']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;">
                    <option value="">Select vehicle type</option>
                    <option value="sedan" <?php echo ($form_data['vehicle_type'] === 'sedan') ? 'selected' : ''; ?>>Sedan</option>
                    <option value="suv" <?php echo ($form_data['vehicle_type'] === 'suv') ? 'selected' : ''; ?>>SUV</option>
                    <option value="mpv" <?php echo ($form_data['vehicle_type'] === 'mpv') ? 'selected' : ''; ?>>MPV</option>
                    <option value="hatchback" <?php echo ($form_data['vehicle_type'] === 'hatchback') ? 'selected' : ''; ?>>Hatchback</option>
                    <option value="pickup" <?php echo ($form_data['vehicle_type'] === 'pickup') ? 'selected' : ''; ?>>Pickup Truck</option>
                </select>
                <?php if (isset($errors['vehicle_type'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['vehicle_type']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Engine Capacity (cc) *</label>
                <select name="engine_capacity" 
                        required 
                        class="form-control <?php echo isset($errors['engine_capacity']) ? 'error-field' : ''; ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid <?php echo isset($errors['engine_capacity']) ? '#dc3545' : '#ddd'; ?>; border-radius: 6px;">
                    <option value="">-- Select Engine Capacity --</option>
                    <optgroup label="Motorcycle">
                        <option value="110" <?php echo ($form_data['engine_capacity'] == '110') ? 'selected' : ''; ?>>110cc</option>
                        <option value="125" <?php echo ($form_data['engine_capacity'] == '125') ? 'selected' : ''; ?>>125cc</option>
                        <option value="150" <?php echo ($form_data['engine_capacity'] == '150') ? 'selected' : ''; ?>>150cc</option>
                        <option value="250" <?php echo ($form_data['engine_capacity'] == '250') ? 'selected' : ''; ?>>250cc</option>
                    </optgroup>
                    <optgroup label="Car - Small">
                        <option value="660" <?php echo ($form_data['engine_capacity'] == '660') ? 'selected' : ''; ?>>660cc (Kei Car)</option>
                        <option value="1000" <?php echo ($form_data['engine_capacity'] == '1000') ? 'selected' : ''; ?>>1000cc</option>
                        <option value="1200" <?php echo ($form_data['engine_capacity'] == '1200') ? 'selected' : ''; ?>>1200cc</option>
                        <option value="1300" <?php echo ($form_data['engine_capacity'] == '1300') ? 'selected' : ''; ?>>1300cc</option>
                    </optgroup>
                    <optgroup label="Car - Medium">
                        <option value="1400" <?php echo ($form_data['engine_capacity'] == '1400') ? 'selected' : ''; ?>>1400cc</option>
                        <option value="1500" <?php echo ($form_data['engine_capacity'] == '1500') ? 'selected' : ''; ?>>1500cc</option>
                        <option value="1600" <?php echo ($form_data['engine_capacity'] == '1600') ? 'selected' : ''; ?>>1600cc</option>
                        <option value="1800" <?php echo ($form_data['engine_capacity'] == '1800') ? 'selected' : ''; ?>>1800cc</option>
                    </optgroup>
                    <optgroup label="Car - Large">
                        <option value="2000" <?php echo ($form_data['engine_capacity'] == '2000') ? 'selected' : ''; ?>>2000cc</option>
                        <option value="2500" <?php echo ($form_data['engine_capacity'] == '2500') ? 'selected' : ''; ?>>2500cc</option>
                        <option value="3000" <?php echo ($form_data['engine_capacity'] == '3000') ? 'selected' : ''; ?>>3000cc</option>
                        <option value="3500" <?php echo ($form_data['engine_capacity'] == '3500') ? 'selected' : ''; ?>>3500cc</option>
                        <option value="4000" <?php echo ($form_data['engine_capacity'] == '4000') ? 'selected' : ''; ?>>4000cc+</option>
                    </optgroup>
                </select>
                <?php if (isset($errors['engine_capacity'])): ?>
                    <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;"><?php echo htmlspecialchars($errors['engine_capacity']); ?></small>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-proceed" onclick="return confirmRegistration(event)">Register Vehicle</button>
            <a href="roadtax-renewal.php" class="btn-secondary" style="text-align: center; display: block; text-decoration: none; margin-top: 10px;">Go to Road Tax Renewal</a>
        </form>
        
        <!-- Confirmation Modal -->
        <div id="confirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; flex-direction: column;">
            <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; margin: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h3 style="margin-top: 0; color: #dc3545;">⚠️ Important Notice</h3>
                <p style="line-height: 1.6; margin-bottom: 20px;">
                    Once you register this vehicle, you will <strong>not be allowed to change the details</strong> of it. 
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
        // Vehicle makes and models data
        const vehicleData = <?php echo json_encode($vehicle_makes); ?>;
        const selectedMake = '<?php echo htmlspecialchars($form_data['vehicle_make'], ENT_QUOTES); ?>';
        const selectedModel = '<?php echo htmlspecialchars($form_data['vehicle_model'], ENT_QUOTES); ?>';
        
        function updateModels() {
            const makeSelect = document.querySelector('select[name="vehicle_make"]');
            const modelSelect = document.getElementById('vehicle_model');
            const selectedMake = makeSelect.value;
            
            // Clear existing options
            modelSelect.innerHTML = '<option value="">-- Select Vehicle Model --</option>';
            
            if (selectedMake && vehicleData[selectedMake]) {
                vehicleData[selectedMake].forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    if (model === selectedModel) {
                        option.selected = true;
                    }
                    modelSelect.appendChild(option);
                });
            }
        }
        
        // Initialize models on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (selectedMake) {
                updateModels();
            }
        });
        
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
            document.getElementById('vehicleForm').submit();
        }
        </script>
        
        <?php if (!empty($vehicles)): ?>
        <h3 style="margin-top: 40px;">Your Registered Vehicles</h3>
        <?php foreach ($vehicles as $vehicle): ?>
            <div class="vehicle-card" style="margin-bottom: 15px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h4><?php echo htmlspecialchars($vehicle['registration_number']); ?></h4>
                <div class="vehicle-info">
                    <div class="info-item">
                        <span class="info-label">Make & Model</span>
                        <span class="info-value"><?php echo htmlspecialchars($vehicle['vehicle_make'] . ' ' . $vehicle['vehicle_model']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Year</span>
                        <span class="info-value"><?php echo htmlspecialchars($vehicle['vehicle_year']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Engine Capacity</span>
                        <span class="info-value"><?php echo htmlspecialchars($vehicle['engine_capacity']); ?> cc</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
