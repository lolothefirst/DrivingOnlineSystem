<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_SESSION['roadtax_renewal_data'])) {
    header('Location: roadtax-renewal.php');
    exit();
}

$renewal_data = $_SESSION['roadtax_renewal_data'];
$errors = [];
$form_data = [];
$flash_error = null;

if (isset($_SESSION['error'])) {
    $flash_error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $card_number = trim($_POST['card_number'] ?? '');
    $card_name = trim($_POST['card_name'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    
    // Validate payment method
    if (empty($payment_method)) {
        $errors[] = "Please select a payment method";
    } elseif (!in_array($payment_method, ['credit_card', 'ewallet'])) {
        $errors[] = "Invalid payment method selected";
    }
    
    // Validate based on payment method
    if ($payment_method === 'credit_card') {
        if (empty($card_number)) {
            $errors[] = "Credit card number is required";
        } else {
            $clean_card = preg_replace('/[^0-9]/', '', $card_number);
            if (!Validator::validateCreditCard($clean_card)) {
                $errors[] = "Credit card number must be exactly 16 digits";
            }
        }
        
        if (empty($card_name)) {
            $errors[] = "Cardholder name is required";
        } elseif (!Validator::lengthBetween($card_name, 2, 50) || !preg_match('/^[a-zA-Z\s]+$/', $card_name)) {
            $errors[] = "Invalid cardholder name (2-50 letters only)";
        }
        
        if (empty($card_expiry)) {
            $errors[] = "Card expiry date is required";
        } elseif (!Validator::validateCardExpiry($card_expiry)) {
            $errors[] = "Invalid or expired card expiry date (MM/YY)";
        }
        
        if (empty($card_cvv)) {
            $errors[] = "CVV is required";
        } elseif (!Validator::validateCVV($card_cvv)) {
            $errors[] = "Invalid CVV (must be 3-4 digits)";
        }
    } elseif ($payment_method === 'ewallet') {
        if (empty($account_number)) {
            $errors[] = "E-wallet account number is required";
        } elseif (!preg_match('/^[0-9+]{10,15}$/', str_replace([' ', '-'], '', $account_number))) {
            $errors[] = "Invalid e-wallet account format";
        }
    }
    
    // Store form data
    $form_data = [
        'payment_method' => $payment_method,
        'card_number' => $card_number,
        'card_name' => $card_name,
        'card_expiry' => $card_expiry,
        'card_cvv' => $card_cvv,
        'account_number' => $account_number
    ];
    
    // If no errors, store payment data in session and process payment
    if (empty($errors)) {
        $_SESSION['payment_data'] = $form_data;
        header('Location: roadtax-process-payment.php');
        exit();
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <div class="jpj-logo">💳</div>
        <h1>Road Tax Payment</h1>
        <p>Complete your road tax renewal payment</p>
    </div>
    
    <!-- Payment Summary -->
    <div class="payment-summary">
        <h3>Payment Summary</h3>
        <div class="summary-item">
            <span>Vehicle Number:</span>
            <strong><?php echo htmlspecialchars($renewal_data['vehicle_number']); ?></strong>
        </div>
        <div class="summary-item">
            <span>Vehicle:</span>
            <strong><?php echo htmlspecialchars($renewal_data['vehicle_make'] . ' ' . $renewal_data['vehicle_model'] . ' (' . $renewal_data['vehicle_year'] . ')'); ?></strong>
        </div>
        <div class="summary-item">
            <span>Engine Capacity:</span>
            <strong><?php echo htmlspecialchars($renewal_data['engine_capacity']); ?>cc</strong>
        </div>
        <div class="summary-item">
            <span>Renewal Period:</span>
            <strong><?php echo $renewal_data['months']; ?> months</strong>
        </div>
        <div class="summary-item total">
            <span>Total Amount:</span>
            <strong>RM <?php echo number_format($renewal_data['amount'], 2); ?></strong>
        </div>
    </div>
    
    <!-- Display validation errors -->
    <?php if ($flash_error): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Action required:</strong>
            <p><?php echo htmlspecialchars($flash_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="renewal-card">
        <h2>Select Payment Method</h2>
        
        <form method="POST" action="" id="paymentForm" novalidate>
            <!-- Payment Method Selection -->
            <div class="payment-methods">
                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="credit_card" 
                           <?php echo ($form_data['payment_method'] ?? '') === 'credit_card' ? 'checked' : ''; ?> required>
                    <div class="method-info">
                        <div class="method-icon">💳</div>
                        <div>
                            <strong>Credit/Debit Card</strong>
                            <small>Visa, Mastercard, American Express</small>
                        </div>
                    </div>
                </label>
                
                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="ewallet" 
                           <?php echo ($form_data['payment_method'] ?? '') === 'ewallet' ? 'checked' : ''; ?> required>
                    <div class="method-info">
                        <div class="method-icon">📱</div>
                        <div>
                            <strong>E-Wallet</strong>
                            <small>GrabPay, Touch 'n Go, Boost</small>
                        </div>
                    </div>
                </label>
            </div>
            
            <!-- Credit Card Details -->
            <div id="credit-card-details" class="payment-details" style="display: none;">
                <h3>Credit Card Information</h3>
                <div class="form-group">
                    <label for="card_number">Card Number <span class="required">*</span></label>
                    <input type="text" id="card_number" name="card_number" class="form-control" 
                           placeholder="1234 5678 9012 3456" maxlength="19"
                           value="<?php echo $form_data['card_number'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="card_name">Cardholder Name <span class="required">*</span></label>
                    <input type="text" id="card_name" name="card_name" class="form-control" 
                           placeholder="John Doe" style="text-transform: uppercase;"
                           value="<?php echo $form_data['card_name'] ?? ''; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="card_expiry">Expiry Date <span class="required">*</span></label>
                        <input type="text" id="card_expiry" name="card_expiry" class="form-control" 
                               placeholder="MM/YY" maxlength="5"
                               value="<?php echo $form_data['card_expiry'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="card_cvv">CVV <span class="required">*</span></label>
                        <input type="password" id="card_cvv" name="card_cvv" class="form-control" 
                               placeholder="123" maxlength="4"
                               value="<?php echo $form_data['card_cvv'] ?? ''; ?>">
                    </div>
                </div>
            </div>
            
            <!-- E-Wallet Details -->
            <div id="ewallet-details" class="payment-details" style="display: none;">
                <h3>E-Wallet Information</h3>
                <div class="form-group">
                    <label for="account_number">Phone Number/Account <span class="required">*</span></label>
                    <input type="text" id="account_number" name="account_number" class="form-control" 
                           placeholder="012-3456789"
                           value="<?php echo $form_data['account_number'] ?? ''; ?>">
                    <small class="form-text">Enter your registered phone number or account ID</small>
                </div>
            </div>
            
            <div class="security-info">
                <div class="security-badge">🔒</div>
                <div>
                    <strong>Secure Payment</strong>
                    <p>Your payment information is encrypted and secure. We do not store your payment details.</p>
                </div>
            </div>
            
            <button type="submit" class="btn-proceed">Complete Payment - RM <?php echo number_format($renewal_data['amount'], 2); ?></button>
        </form>
    </div>
</div>

<style>
.payment-summary {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid #dee2e6;
}

.payment-summary h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 1.4rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.total {
    font-size: 1.2rem;
    font-weight: bold;
    color: #27ae60;
    background: #e8f5e8;
    padding: 15px;
    margin: 15px -15px -15px -15px;
    border-radius: 0 0 12px 12px;
}

.payment-methods {
    display: grid;
    gap: 15px;
    margin-bottom: 30px;
}

.payment-method-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
}

.payment-method-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.payment-method-card input[type="radio"] {
    margin-right: 15px;
    transform: scale(1.3);
    accent-color: #667eea;
}

.payment-method-card input[type="radio"]:checked + .method-info {
    color: #667eea;
}

.method-info {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.method-icon {
    font-size: 2rem;
}

.method-info strong {
    display: block;
    font-size: 16px;
    margin-bottom: 4px;
}

.method-info small {
    color: #7f8c8d;
    font-size: 14px;
}

.payment-details {
    background: #f8f9ff;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid #667eea;
}

.payment-details h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
}

.security-info {
    display: flex;
    align-items: center;
    gap: 15px;
    background: #e8f5e8;
    padding: 20px;
    border-radius: 12px;
    margin: 25px 0;
    border: 1px solid #27ae60;
}

.security-badge {
    font-size: 2rem;
}

.security-info strong {
    display: block;
    color: #27ae60;
    margin-bottom: 5px;
}

.security-info p {
    margin: 0;
    color: #2c3e50;
    font-size: 14px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const ewalletDetails = document.getElementById('ewallet-details');
    
    // Handle payment method selection
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            // Hide all details
            creditCardDetails.style.display = 'none';
            ewalletDetails.style.display = 'none';
            
            // Show relevant details
            if (this.value === 'credit_card') {
                creditCardDetails.style.display = 'block';
            } else if (this.value === 'ewallet') {
                ewalletDetails.style.display = 'block';
            }
        });
    });
    
    // Format card number
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            this.value = value;
        });
    }
    
    // Format expiry date
    const expiryInput = document.getElementById('card_expiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });
    }
    
    // Initialize display based on selected method
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    if (selectedMethod) {
        selectedMethod.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include 'includes/footer.php'; ?>