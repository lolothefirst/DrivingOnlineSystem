<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (empty($type) || !in_array($type, ['roadtax', 'license']) || $id <= 0) {
    $_SESSION['error'] = 'Invalid receipt request.';
    header('Location: renewal-status.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($type === 'roadtax') {
    // Fetch road tax renewal details
    $stmt = $conn->prepare("
        SELECT rt.*, u.full_name, u.email, u.id_number
        FROM roadtax_renewals rt
        JOIN users u ON rt.user_id = u.id
        WHERE rt.id = ? AND rt.user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
    $renewal = $stmt->fetch();
    
    if (!$renewal) {
        $_SESSION['error'] = 'Road tax renewal record not found.';
        header('Location: renewal-status.php');
        exit();
    }
    
    $title = 'Road Tax Renewal Receipt';
    $title_malay = 'Resit Pembaharuan Cukai Jalan';
} else {
    // Fetch license renewal details
    $stmt = $conn->prepare("
        SELECT lr.*, u.full_name, u.email, u.id_number
        FROM license_renewals lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.id = ? AND lr.user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
    $renewal = $stmt->fetch();
    
    if (!$renewal) {
        $_SESSION['error'] = 'License renewal record not found.';
        header('Location: renewal-status.php');
        exit();
    }
    
    $title = 'Driving License Renewal Receipt';
    $title_malay = 'Resit Pembaharuan Lesen Memandu';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0066cc;
        }
        
        .receipt-header h1 {
            color: #0066cc;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .receipt-header h2 {
            font-size: 18px;
            margin: 10px 0 5px;
        }
        
        .receipt-header p {
            color: #666;
            font-size: 14px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-row:last-child {
            border-bottom: none;
        }
        
        .receipt-row strong {
            color: #333;
            font-weight: 600;
        }
        
        .receipt-row span {
            color: #666;
            text-align: right;
        }
        
        .receipt-total {
            font-size: 20px;
            color: #0066cc;
            font-weight: bold;
            margin-top: 10px;
        }
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        .print-actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-actions {
                display: none;
            }
            
            .receipt-container {
                box-shadow: none;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>JABATAN PENGANGKUTAN JALAN MALAYSIA</h1>
            <h2><?php echo htmlspecialchars($title); ?></h2>
            <p><?php echo htmlspecialchars($title_malay); ?></p>
        </div>
        
        <div class="receipt-row">
            <strong>Receipt Number:</strong>
            <span><?php echo htmlspecialchars($renewal['receipt_number'] ?? 'N/A'); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Transaction ID:</strong>
            <span><?php echo htmlspecialchars($renewal['transaction_id'] ?? 'N/A'); ?></span>
        </div>
        <div class="receipt-row">
            <strong>Date:</strong>
            <span><?php echo date('d F Y, h:i A', strtotime($renewal['created_at'])); ?></span>
        </div>
        
        <hr>
        
        <?php if ($type === 'roadtax'): ?>
            <div class="receipt-row">
                <strong>Vehicle Number:</strong>
                <span><?php echo htmlspecialchars($renewal['vehicle_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="receipt-row">
                <strong>Vehicle:</strong>
                <span><?php echo htmlspecialchars(($renewal['vehicle_make'] ?? '') . ' ' . ($renewal['vehicle_model'] ?? '') . ' (' . ($renewal['vehicle_year'] ?? '') . ')'); ?></span>
            </div>
            <div class="receipt-row">
                <strong>Engine Capacity:</strong>
                <span><?php echo htmlspecialchars($renewal['engine_capacity'] ?? 'N/A'); ?>cc</span>
            </div>
            <hr>
            <div class="receipt-row">
                <strong>Renewal Period:</strong>
                <span><?php echo ($renewal['renewal_period'] ?? '') === '6_months' ? '6 Months' : '12 Months'; ?></span>
            </div>
        <?php else: ?>
            <div class="receipt-row">
                <strong>License Number:</strong>
                <span><?php echo htmlspecialchars($renewal['license_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="receipt-row">
                <strong>Full Name:</strong>
                <span><?php echo htmlspecialchars($renewal['full_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="receipt-row">
                <strong>IC Number:</strong>
                <span><?php echo htmlspecialchars($renewal['ic_number'] ?? $renewal['id_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="receipt-row">
                <strong>License Classes:</strong>
                <span><?php echo htmlspecialchars($renewal['license_types'] ?? 'N/A'); ?></span>
            </div>
            <hr>
            <div class="receipt-row">
                <strong>Validity Period:</strong>
                <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $renewal['renewal_period'] ?? ''))); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="receipt-row">
            <strong>Valid From:</strong>
            <span><?php echo $renewal['start_date'] ? date('d F Y', strtotime($renewal['start_date'])) : 'N/A'; ?></span>
        </div>
        <div class="receipt-row">
            <strong>Valid Until:</strong>
            <span><?php echo $renewal['expiry_date'] ? date('d F Y', strtotime($renewal['expiry_date'])) : 'N/A'; ?></span>
        </div>
        
        <hr>
        
        <div class="receipt-row">
            <strong>Payment Method:</strong>
            <span><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $renewal['payment_method'] ?? 'N/A'))); ?></span>
        </div>
        <div class="receipt-row receipt-total">
            <strong>Total Paid:</strong>
            <strong>RM <?php echo number_format($renewal['amount'] ?? 0, 2); ?></strong>
        </div>
        
        <div class="print-actions">
            <button onclick="window.print()" class="btn">🖨️ Print Receipt</button>
            <a href="renewal-status.php" class="btn">Back to Status</a>
        </div>
    </div>
    
    <script>
        // Auto-trigger print dialog when page loads
        window.onload = function() {
            // Small delay to ensure page is fully rendered
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>

