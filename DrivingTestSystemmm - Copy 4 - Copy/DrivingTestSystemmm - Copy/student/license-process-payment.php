<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_SESSION['license_renewal_data'])) {
    $_SESSION['error'] = 'No renewal data found. Please start the process again.';
    header('Location: license-renewal.php');
    exit();
}

if (!isset($_SESSION['payment_data'])) {
    $_SESSION['error'] = 'Payment data not found. Please complete the payment form.';
    header('Location: license-payment.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$renewal_data = $_SESSION['license_renewal_data'];
$payment_data = $_SESSION['payment_data'];

// Ensure database schema supports the fields used in the renewal flow
try {
    ensureLicenseRenewalSchema($conn);
} catch (PDOException $schemaException) {
    error_log("License schema verification failed: " . $schemaException->getMessage());
    $_SESSION['error'] = 'System configuration issue detected. Please contact support to update the database schema.';
    header('Location: license-payment.php');
    exit();
}

// Get payment method from session data
$payment_method = $payment_data['payment_method'] ?? 'credit_card';

// Simulate payment processing delay
sleep(1);

// Generate transaction details
$transaction_id = 'DL' . date('YmdHis') . rand(1000, 9999);
$receipt_number = 'JPJ' . date('Ymd') . rand(10000, 99999);
$new_license_number = 'D' . rand(100000000, 999999999);

// Calculate dates
$start_date = date('Y-m-d');
$years = (int)filter_var($renewal_data['renewal_period'], FILTER_SANITIZE_NUMBER_INT);
$expiry_date = date('Y-m-d', strtotime("+{$years} years"));

try {
    $conn->beginTransaction();
    
    // Insert license renewal record
    $stmt = $conn->prepare("
        INSERT INTO license_renewals (
            user_id, ic_number, license_number, new_license_number, license_types, 
            renewal_period, start_date, expiry_date, amount, payment_status, 
            payment_method, transaction_id, receipt_number, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $renewal_data['ic_number'],
        $renewal_data['license_number'],
        $new_license_number,
        $renewal_data['license_types'],
        $renewal_data['renewal_period'],
        $start_date,
        $expiry_date,
        $renewal_data['amount'],
        $payment_method,
        $transaction_id,
        $receipt_number
    ]);
    
    $renewal_id = $conn->lastInsertId();
    
    // Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO payments (
            user_id, transaction_id, payment_type, reference_id, amount, 
            payment_method, payment_status, paid_at, created_at
        ) VALUES (?, ?, 'license', ?, ?, ?, 'success', NOW(), NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $transaction_id,
        $renewal_id,
        $renewal_data['amount'],
        $payment_method
    ]);
    
    // Log activity if function exists
    if (function_exists('logActivity')) {
        logActivity($conn, $user_id, 'License renewal completed', 'license_renewals', $renewal_id);
    }
    
    $conn->commit();
    
    // Store success data in session for the success page
    $_SESSION['payment_success'] = [
        'type' => 'license',
        'transaction_id' => $transaction_id,
        'receipt_number' => $receipt_number,
        'amount' => $renewal_data['amount'],
        'license_number' => $renewal_data['license_number'],
        'new_license_number' => $new_license_number,
        'license_types' => $renewal_data['license_types'],
        'ic_number' => $renewal_data['ic_number'],
        'renewal_period' => $renewal_data['renewal_period'],
        'start_date' => $start_date,
        'expiry_date' => $expiry_date,
        'payment_method' => $payment_method,
        'renewal_id' => $renewal_id
    ];
    
    // Clear payment and renewal data
    unset($_SESSION['license_renewal_data']);
    unset($_SESSION['payment_data']);
    
    // Redirect to success page
    header('Location: license-success.php?transaction_id=' . urlencode($transaction_id));
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("License payment processing error: " . $e->getMessage());
    $_SESSION['error'] = 'Payment processing failed. Please try again. If the problem persists, contact support.';
    header('Location: license-payment.php');
    exit();
}
