<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

error_log("[DEBUG] Roadtax Success Page - User ID: " . $_SESSION['user_id']);
error_log("[DEBUG] GET parameters: " . print_r($_GET, true));
error_log("[DEBUG] Session payment_success: " . (isset($_SESSION['payment_success']) ? 'YES' : 'NO'));

if (!isset($_GET['transaction_id'])) {
    if (isset($_SESSION['payment_success']) && isset($_SESSION['payment_success']['transaction_id'])) {
        $transaction_id = $_SESSION['payment_success']['transaction_id'];
        error_log("[DEBUG] Using transaction_id from session: " . $transaction_id);
    } else {
        $_SESSION['error'] = 'No transaction ID provided.';
        header('Location: roadtax-renewal.php');
        exit();
    }
} else {
    $transaction_id = Validator::sanitize($_GET['transaction_id']);
}

if (empty($transaction_id)) {
    $_SESSION['error'] = 'Invalid transaction ID.';
    header('Location: roadtax-renewal.php');
    exit();
}

if (!preg_match('/^RT[0-9]{10,25}$/i', $transaction_id)) {
    error_log("[DEBUG] Transaction ID format invalid: " . $transaction_id);
    $_SESSION['error'] = 'Invalid transaction ID format.';
    header('Location: roadtax-renewal.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

usleep(100000); // 0.1 second delay

// Fetch renewal details from database
$stmt = $conn->prepare("
    SELECT rt.*, u.full_name, u.email
    FROM roadtax_renewals rt
    JOIN users u ON rt.user_id = u.id
    WHERE rt.transaction_id = ? AND rt.user_id = ?
");
$stmt->execute([$transaction_id, $user_id]);
$renewal = $stmt->fetch();

if (!$renewal) {
    error_log("[DEBUG] Transaction not found in database: " . $transaction_id);
    error_log("[DEBUG] User ID: " . $user_id);
    
    // Check if we have session data as fallback
    if (isset($_SESSION['payment_success'])) {
        error_log("[DEBUG] Using session payment_success data as fallback");
        $renewal = $_SESSION['payment_success'];
        $renewal['full_name'] = $_SESSION['full_name'] ?? 'N/A';
        $renewal['email'] = $_SESSION['email'] ?? 'N/A';
        $renewal['created_at'] = date('Y-m-d H:i:s');
    } else {
        $_SESSION['error'] = 'Transaction not found. Please check your email for confirmation or contact support.';
        header('Location: roadtax-renewal.php');
        exit();
    }
}

// Clear the payment success session data if it exists
if (isset($_SESSION['payment_success'])) {
    unset($_SESSION['payment_success']);
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <!-- Enhanced success message with animation -->
    <div class="success-message non-printable" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 40px; border-radius: 12px; text-align: center; margin-bottom: 30px; box-shadow: 0 8px 16px rgba(40, 167, 69, 0.3);">
        <div style="font-size: 80px; margin-bottom: 20px; animation: scaleIn 0.5s ease;">✅</div>
        <h1 style="margin: 0 0 10px 0; font-size: 32px;">Payment Successful!</h1>
        <p style="margin: 0; font-size: 18px; opacity: 0.95;">Your road tax has been successfully renewed</p>
        <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.2); border-radius: 8px; display: inline-block;">
            <div style="font-size: 14px; opacity: 0.9;">Vehicle Number</div>
            <div style="font-size: 18px; font-weight: bold; margin-top: 5px;"><?php echo htmlspecialchars($renewal['vehicle_number']); ?></div>
        </div>
    </div>
    
    <div class="receipt-box" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div class="receipt-header" style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #0066cc;">
            <h2 style="margin: 0; color: #0066cc; font-size: 24px;">JABATAN PENGANGKUTAN JALAN MALAYSIA</h2>
            <h3 style="margin: 10px 0 5px; font-size: 20px;">Road Tax Renewal Receipt</h3>
            <p style="margin: 0; color: #666; font-size: 14px;">Resit Pembaharuan Cukai Jalan</p>
        </div>
        
        <div class="receipt-row">
            <strong>Receipt Number:</strong>
            <span><?php echo htmlspecialchars($renewal['receipt_number']); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Transaction ID:</strong>
            <span><?php echo htmlspecialchars($renewal['transaction_id']); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Date:</strong>
            <span><?php echo date('d F Y, h:i A'); ?></span>
        </div>
        
        <hr style="margin: 20px 0;">
        
        <div class="receipt-row">
            <strong>Vehicle Number:</strong>
            <span><?php echo htmlspecialchars($renewal['vehicle_number']); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Vehicle:</strong>
            <span><?php echo htmlspecialchars($renewal['vehicle_make'] . ' ' . $renewal['vehicle_model'] . ' (' . $renewal['vehicle_year'] . ')'); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Engine Capacity:</strong>
            <span><?php echo htmlspecialchars($renewal['engine_capacity']); ?>cc</span>
        </div>
        
        <hr style="margin: 20px 0;">
        
        <div class="receipt-row">
            <strong>Renewal Period:</strong>
            <span><?php echo $renewal['renewal_period'] === '6_months' ? '6 Months' : '12 Months'; ?></span>
        </div>
        <div class="receipt-row">
            <strong>Valid From:</strong>
            <span><?php echo date('d F Y', strtotime($renewal['start_date'])); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Valid Until:</strong>
            <span><?php echo date('d F Y', strtotime($renewal['expiry_date'])); ?></span>
        </div>
        
        <hr style="margin: 20px 0;">
        
        <div class="receipt-row">
            <strong>Payment Method:</strong>
            <span><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $renewal['payment_method']))); ?></span>
        </div>
        <div class="receipt-row" style="font-size: 24px; color: #0066cc;">
            <strong>Total Paid:</strong>
            <strong>RM <?php echo number_format($renewal['amount'], 2); ?></strong>
        </div>
    </div>
    
    <div class="non-printable" style="text-align: center; margin: 30px 0;">
        <button onclick="window.print()" class="btn-proceed" style="width: auto; padding: 15px 50px; display: inline-block; margin-right: 10px;">🖨️ Print Receipt</button>
        <a href="roadtax-renewal.php" class="btn-secondary" style="width: auto; padding: 15px 50px; display: inline-block; margin-right: 10px; text-decoration: none;">Renew Another Vehicle</a>
        <a href="renewal-status.php" class="btn-secondary" style="width: auto; padding: 15px 50px; display: inline-block; margin-right: 10px; text-decoration: none;">View Renewal Status</a>
        <a href="dashboard.php" class="btn-secondary" style="width: auto; padding: 15px 50px; display: inline-block; text-decoration: none;">Back to Dashboard</a>
    </div>
    
    <div class="non-printable" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 20px;">
        <strong>⚠️ Important:</strong> Please keep this receipt for your records. You may be required to present it during roadblocks or vehicle inspections.
    </div>
</div>

<style>
.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.receipt-row:last-child {
    border-bottom: none;
}

@keyframes scaleIn {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

@media print {
    body {
        background: #fff !important;
    }
    body * {
        visibility: hidden;
    }
    .receipt-box, .receipt-box * {
        visibility: visible;
    }
    .receipt-box {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: none !important;
        padding: 20px !important;
    }
    .renewal-container {
        margin: 0 !important;
        padding: 0 !important;
    }
    .non-printable {
        display: none !important;
    }
}
</style>

<script>
console.log("[v0] Success page loaded");
console.log("[v0] Transaction ID: <?php echo htmlspecialchars($transaction_id); ?>");
</script>

<?php include 'includes/footer.php'; ?>
