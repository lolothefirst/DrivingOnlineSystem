<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

error_log("[v0 Debug] Process payment started");
error_log("[v0 Debug] Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("[v0 Debug] Session user_type: " . ($_SESSION['user_type'] ?? 'NOT SET'));
error_log("[v0 Debug] Session roadtax_renewal_data: " . (isset($_SESSION['roadtax_renewal_data']) ? 'SET' : 'NOT SET'));
error_log("[v0 Debug] Session payment_data: " . (isset($_SESSION['payment_data']) ? 'SET' : 'NOT SET'));

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    error_log("[v0 Debug] User not logged in or not a student, redirecting to login");
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_SESSION['roadtax_renewal_data'])) {
    error_log("[v0 Debug] No roadtax_renewal_data in session");
    $_SESSION['error'] = 'No renewal data found. Please start the process again.';
    header('Location: roadtax-renewal.php');
    exit();
}

if (!isset($_SESSION['payment_data'])) {
    error_log("[v0 Debug] No payment_data in session");
    $_SESSION['error'] = 'Payment data not found. Please complete the payment form.';
    header('Location: roadtax-payment.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$renewal_data = $_SESSION['roadtax_renewal_data'];
$payment_data = $_SESSION['payment_data'];

// Ensure database schema supports the fields used in the renewal flow
try {
    ensureRoadtaxRenewalSchema($conn);
} catch (PDOException $schemaException) {
    error_log("Roadtax schema verification failed: " . $schemaException->getMessage());
    $_SESSION['error'] = 'System configuration issue detected. Please contact support to update the database schema.';
    header('Location: roadtax-payment.php');
    exit();
}

error_log("[v0 Debug] User ID: " . $user_id);
error_log("[v0 Debug] Renewal data: " . json_encode($renewal_data));
error_log("[v0 Debug] Payment data: " . json_encode($payment_data));

// Get payment method from session data
$payment_method = $payment_data['payment_method'] ?? 'credit_card';

// Simulate payment processing delay
sleep(1);

// Generate transaction details
$transaction_id = 'RT' . date('YmdHis') . rand(1000, 9999);
$receipt_number = 'JPJ' . date('Ymd') . rand(10000, 99999);

// Calculate dates - check if vehicle already has an active renewal
$vehicle_number = $renewal_data['vehicle_number'];
$months = $renewal_data['months'];

// Check for existing active renewal for this vehicle
$check_stmt = $conn->prepare("
    SELECT expiry_date 
    FROM roadtax_renewals 
    WHERE user_id = ? AND vehicle_number = ? AND status = 'active' 
    ORDER BY expiry_date DESC 
    LIMIT 1
");
$check_stmt->execute([$user_id, $vehicle_number]);
$existing_renewal = $check_stmt->fetch();

if ($existing_renewal && $existing_renewal['expiry_date']) {
    // Extend from existing expiry date
    $start_date = $existing_renewal['expiry_date'];
    $expiry_date = date('Y-m-d', strtotime($existing_renewal['expiry_date'] . " +{$months} months"));
    error_log("[v0 Debug] Extending existing renewal. New expiry: " . $expiry_date);
} else {
    // New renewal - start from today
$start_date = date('Y-m-d');
$expiry_date = date('Y-m-d', strtotime("+{$months} months"));
    error_log("[v0 Debug] New renewal. Expiry: " . $expiry_date);
}

try {
    error_log("[v0 Debug] Starting database transaction");
    $conn->beginTransaction();
    
    error_log("[v0 Debug] Inserting road tax renewal record");
    
    // Insert road tax renewal record
    $stmt = $conn->prepare("
        INSERT INTO roadtax_renewals (
            user_id, vehicle_number, vehicle_make, vehicle_model, vehicle_year, 
            engine_capacity, renewal_period, start_date, expiry_date, amount, 
            payment_status, payment_method, transaction_id, receipt_number, 
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $renewal_data['vehicle_number'],
        $renewal_data['vehicle_make'],
        $renewal_data['vehicle_model'],
        $renewal_data['vehicle_year'],
        $renewal_data['engine_capacity'],
        $renewal_data['renewal_period'],
        $start_date,
        $expiry_date,
        $renewal_data['amount'],
        $payment_method,
        $transaction_id,
        $receipt_number
    ]);
    
    $renewal_id = $conn->lastInsertId();
    error_log("[v0 Debug] Road tax renewal record inserted with ID: " . $renewal_id);
    
    error_log("[v0 Debug] Inserting payment record");
    
    // Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO payments (
            user_id, transaction_id, payment_type, reference_id, amount, 
            payment_method, payment_status, paid_at, created_at
        ) VALUES (?, ?, 'roadtax', ?, ?, ?, 'success', NOW(), NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $transaction_id,
        $renewal_id,
        $renewal_data['amount'],
        $payment_method
    ]);
    
    error_log("[v0 Debug] Payment record inserted");
    
    // Log activity if function exists
    if (function_exists('logActivity')) {
        logActivity($conn, $user_id, 'Road tax renewal completed', 'roadtax_renewals', $renewal_id);
    }
    
    $conn->commit();
    error_log("[v0 Debug] Transaction committed successfully");
    
    // Store success data in session for the success page
    $_SESSION['payment_success'] = [
        'type' => 'roadtax',
        'transaction_id' => $transaction_id,
        'receipt_number' => $receipt_number,
        'amount' => $renewal_data['amount'],
        'vehicle_number' => $renewal_data['vehicle_number'],
        'vehicle_make' => $renewal_data['vehicle_make'],
        'vehicle_model' => $renewal_data['vehicle_model'],
        'vehicle_year' => $renewal_data['vehicle_year'],
        'engine_capacity' => $renewal_data['engine_capacity'],
        'renewal_period' => $renewal_data['renewal_period'],
        'start_date' => $start_date,
        'expiry_date' => $expiry_date,
        'payment_method' => $payment_method,
        'renewal_id' => $renewal_id
    ];
    
    // Clear payment and renewal data
    unset($_SESSION['roadtax_renewal_data']);
    unset($_SESSION['payment_data']);
    
    error_log("[v0 Debug] Redirecting to success page with transaction_id: " . $transaction_id);
    
    // Redirect to success page
    header('Location: roadtax-success.php?transaction_id=' . urlencode($transaction_id));
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("[v0 Debug] EXCEPTION CAUGHT: " . $e->getMessage());
    error_log("[v0 Debug] Exception trace: " . $e->getTraceAsString());
    error_log("Road tax payment processing error: " . $e->getMessage());
    $_SESSION['error'] = 'Payment processing failed. Please try again. If the problem persists, contact support.';
    header('Location: roadtax-payment.php');
    exit();
}
