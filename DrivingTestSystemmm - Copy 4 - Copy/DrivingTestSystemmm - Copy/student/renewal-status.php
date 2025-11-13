<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch road tax renewals - group by vehicle number and get latest expiry
$roadtax_stmt = $conn->prepare("
    SELECT rt.*, p.payment_status, p.paid_at, p.transaction_id as payment_transaction_id
    FROM roadtax_renewals rt 
    LEFT JOIN payments p ON p.reference_id = rt.id AND p.payment_type = 'roadtax'
    WHERE rt.user_id = ? 
    ORDER BY rt.vehicle_number, rt.created_at DESC
");
$roadtax_stmt->execute([$user_id]);
$all_roadtax_renewals = $roadtax_stmt->fetchAll();

// Group by vehicle number and calculate combined expiry dates
$roadtax_renewals_grouped = [];
foreach ($all_roadtax_renewals as $renewal) {
    $vehicle_num = $renewal['vehicle_number'] ?? 'UNKNOWN';
    
    if (!isset($roadtax_renewals_grouped[$vehicle_num])) {
        // First occurrence of this vehicle - initialize
        $roadtax_renewals_grouped[$vehicle_num] = [
            'main' => $renewal,
            'all_renewals' => [$renewal],
            'latest_expiry' => $renewal['expiry_date'] ?? null,
            'total_amount' => floatval($renewal['amount'] ?? 0)
        ];
    } else {
        // Additional renewal for same vehicle
        $roadtax_renewals_grouped[$vehicle_num]['all_renewals'][] = $renewal;
        $roadtax_renewals_grouped[$vehicle_num]['total_amount'] += floatval($renewal['amount'] ?? 0);
        
        // Update latest expiry if this one is later
        $current_expiry = $renewal['expiry_date'] ?? null;
        $existing_expiry = $roadtax_renewals_grouped[$vehicle_num]['latest_expiry'];
        
        if ($current_expiry && (!$existing_expiry || strtotime($current_expiry) > strtotime($existing_expiry))) {
            $roadtax_renewals_grouped[$vehicle_num]['latest_expiry'] = $current_expiry;
            $roadtax_renewals_grouped[$vehicle_num]['main'] = $renewal; // Use latest as main
        }
    }
}

// Convert to array for display (one entry per vehicle)
$roadtax_renewals = array_values($roadtax_renewals_grouped);

// Fetch license renewals - group by license number and get latest expiry
$license_stmt = $conn->prepare("
    SELECT lr.*, p.payment_status, p.paid_at, p.transaction_id as payment_transaction_id
    FROM license_renewals lr 
    LEFT JOIN payments p ON p.reference_id = lr.id AND p.payment_type = 'license'
    WHERE lr.user_id = ? 
    ORDER BY lr.license_number, lr.created_at DESC
");
$license_stmt->execute([$user_id]);
$all_license_renewals = $license_stmt->fetchAll();

// Group by license number and calculate combined expiry dates
$license_renewals_grouped = [];
foreach ($all_license_renewals as $renewal) {
    $license_num = $renewal['license_number'] ?? 'UNKNOWN';
    
    if (!isset($license_renewals_grouped[$license_num])) {
        // First occurrence of this license - initialize
        $license_renewals_grouped[$license_num] = [
            'main' => $renewal,
            'all_renewals' => [$renewal],
            'latest_expiry' => $renewal['expiry_date'] ?? null,
            'total_amount' => floatval($renewal['amount'] ?? 0)
        ];
    } else {
        // Additional renewal for same license
        $license_renewals_grouped[$license_num]['all_renewals'][] = $renewal;
        $license_renewals_grouped[$license_num]['total_amount'] += floatval($renewal['amount'] ?? 0);
        
        // Update latest expiry if this one is later
        $current_expiry = $renewal['expiry_date'] ?? null;
        $existing_expiry = $license_renewals_grouped[$license_num]['latest_expiry'];
        
        if ($current_expiry && (!$existing_expiry || strtotime($current_expiry) > strtotime($existing_expiry))) {
            $license_renewals_grouped[$license_num]['latest_expiry'] = $current_expiry;
            $license_renewals_grouped[$license_num]['main'] = $renewal; // Use latest as main
        }
    }
}

// Calculate cumulative expiry dates for each license group
foreach ($license_renewals_grouped as $license_num => &$group) {
    if (count($group['all_renewals']) > 1) {
        // Sort renewals by created_at (oldest first) to calculate cumulative expiry
        usort($group['all_renewals'], function($a, $b) {
            $dateA = strtotime($a['created_at'] ?? '1970-01-01');
            $dateB = strtotime($b['created_at'] ?? '1970-01-01');
            return $dateA <=> $dateB;
        });
        
        // Calculate cumulative expiry by extending from each previous expiry
        $cumulative_expiry = null;
        foreach ($group['all_renewals'] as $renewal) {
            if ($cumulative_expiry === null) {
                // First renewal - use its expiry date as base
                $cumulative_expiry = $renewal['expiry_date'] ?? null;
            } else {
                // Extend from cumulative expiry by adding the renewal period of current renewal
                if ($cumulative_expiry) {
                    $renewal_period = $renewal['renewal_period'] ?? '1_year';
                    $years = (int)filter_var($renewal_period, FILTER_SANITIZE_NUMBER_INT);
                    // Extend from the cumulative expiry date
                    $cumulative_expiry = date('Y-m-d', strtotime($cumulative_expiry . " +{$years} years"));
                }
            }
        }
        
        // Update latest_expiry with cumulative expiry
        if ($cumulative_expiry) {
            $group['latest_expiry'] = $cumulative_expiry;
        }
        
        // Re-sort by created_at DESC for display (newest first)
        usort($group['all_renewals'], function($a, $b) {
            $dateA = strtotime($a['created_at'] ?? '1970-01-01');
            $dateB = strtotime($b['created_at'] ?? '1970-01-01');
            return $dateB <=> $dateA;
        });
    }
}
unset($group); // Unset reference

// Convert to array for display (one entry per license)
$license_renewals = array_values($license_renewals_grouped);

include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/renewals.css">

<div class="renewal-container">
    <div class="renewal-header">
        <div class="jpj-logo">📋</div>
        <h1>Renewal Status</h1>
        <p>Track your road tax and license renewal applications</p>
    </div>
    
    <!-- Road Tax Renewals -->
    <div class="status-section">
        <h2>🚗 Road Tax Renewals</h2>
        
        <?php if (empty($roadtax_renewals)): ?>
            <div class="no-records">
                <p>No road tax renewal records found.</p>
                <a href="roadtax-renewal.php" class="btn-secondary">Start Road Tax Renewal</a>
            </div>
        <?php else: ?>
            <div class="renewal-records">
                <?php foreach ($roadtax_renewals as $index => $vehicle_group): 
                    $main_renewal = $vehicle_group['main'];
                    $vehicle_num = $main_renewal['vehicle_number'] ?? 'N/A';
                    $has_multiple = count($vehicle_group['all_renewals']) > 1;
                ?>
                    <div class="record-card">
                        <div class="record-header" style="cursor: pointer;" onclick="toggleDetails('roadtax-<?php echo $index; ?>')">
                            <div class="record-info">
                                <h3 style="display: flex; align-items: center; gap: 10px;">
                                    <span>🚗</span>
                                    <span>Vehicle: <?php echo htmlspecialchars($vehicle_num); ?></span>
                                    <?php if ($has_multiple): ?>
                                        <span style="font-size: 12px; color: #667eea; background: #e3f2fd; padding: 2px 8px; border-radius: 12px;">
                                            <?php echo count($vehicle_group['all_renewals']); ?> renewal(s)
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <p class="record-date">
                                    <strong>Valid Until:</strong> 
                                    <span style="color: #28a745; font-weight: bold;">
                                        <?php echo $vehicle_group['latest_expiry'] ? date('d M Y', strtotime($vehicle_group['latest_expiry'])) : 'N/A'; ?>
                                    </span>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="status-badge status-<?php echo strtolower($main_renewal['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($main_renewal['status'] ?? 'Pending'); ?>
                            </div>
                                <button class="toggle-btn" id="toggle-roadtax-<?php echo $index; ?>">
                                    <span class="toggle-icon">▼</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="record-details" id="details-roadtax-<?php echo $index; ?>" style="display: none;">
                            <?php if ($has_multiple): ?>
                                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                    <strong>📋 Renewal History:</strong>
                                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">
                                        This vehicle has been renewed <?php echo count($vehicle_group['all_renewals']); ?> time(s). 
                                        Expiry date has been extended to <?php echo date('d M Y', strtotime($vehicle_group['latest_expiry'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-row">
                                <span>Vehicle Make/Model:</span>
                                <strong><?php echo htmlspecialchars(($main_renewal['vehicle_make'] ?? '') . ' ' . ($main_renewal['vehicle_model'] ?? '')); ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Engine Capacity:</span>
                                <strong><?php echo htmlspecialchars($main_renewal['engine_capacity'] ?? 'N/A'); ?>cc</strong>
                            </div>
                            <div class="detail-row">
                                <span>Total Amount Paid:</span>
                                <strong>RM <?php echo number_format($vehicle_group['total_amount'], 2); ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Latest Valid Until:</span>
                                <strong style="color: #28a745;"><?php echo $vehicle_group['latest_expiry'] ? date('d M Y', strtotime($vehicle_group['latest_expiry'])) : 'N/A'; ?></strong>
                            </div>
                            
                            <?php if ($has_multiple): ?>
                                <hr style="margin: 15px 0;">
                                <div style="margin-bottom: 15px;">
                                    <strong style="display: block; margin-bottom: 10px;">All Renewal Records:</strong>
                                    <?php foreach ($vehicle_group['all_renewals'] as $renewal): ?>
                                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #667eea;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span style="font-size: 13px; color: #666;">Renewed: <?php echo date('d M Y', strtotime($renewal['created_at'])); ?></span>
                                                <span style="font-size: 13px; color: #666;">Amount: RM <?php echo number_format($renewal['amount'] ?? 0, 2); ?></span>
                            </div>
                                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #999;">
                                                <span>Period: <?php echo ucwords(str_replace('_', ' ', $renewal['renewal_period'] ?? '')); ?></span>
                                                <span>Expiry: <?php echo $renewal['expiry_date'] ? date('d M Y', strtotime($renewal['expiry_date'])) : 'N/A'; ?></span>
                            </div>
                            <?php if ($renewal['transaction_id']): ?>
                                                <div style="margin-top: 5px;">
                                                    <button onclick="downloadReceipt('roadtax', <?php echo $renewal['id']; ?>); event.stopPropagation();" 
                                                            class="btn-download" style="font-size: 12px; padding: 4px 12px;">
                                                        📄 Receipt
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <span>Renewal Period:</span>
                                    <strong><?php echo ucwords(str_replace('_', ' ', $main_renewal['renewal_period'] ?? '')); ?></strong>
                                </div>
                                <?php if ($main_renewal['transaction_id']): ?>
                                <div class="detail-row">
                                    <span>Transaction ID:</span>
                                        <strong><?php echo htmlspecialchars($main_renewal['transaction_id']); ?></strong>
                                </div>
                            <?php endif; ?>
                                <?php if ($main_renewal['receipt_number']): ?>
                                <div class="detail-row">
                                    <span>Receipt Number:</span>
                                        <strong><?php echo htmlspecialchars($main_renewal['receipt_number']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        
                            <?php if ($main_renewal['status'] === 'active'): ?>
                                <div class="record-actions" style="margin-top: 15px;">
                                    <?php if (!$has_multiple): ?>
                                        <button onclick="downloadReceipt('roadtax', <?php echo $main_renewal['id']; ?>)" class="btn-download">
                                    📄 Download Receipt
                                </button>
                                    <?php endif; ?>
                                    <div class="info-note">
                                        <small>✅ Road tax renewal is active and valid</small>
                                    </div>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- License Renewals -->
    <div class="status-section">
        <h2>🪪 License Renewals</h2>
        
        <?php if (empty($license_renewals)): ?>
            <div class="no-records">
                <p>No license renewal records found.</p>
                <a href="license-renewal.php" class="btn-secondary">Start License Renewal</a>
            </div>
        <?php else: ?>
            <div class="renewal-records">
                <?php foreach ($license_renewals as $index => $license_group): 
                    $main_renewal = $license_group['main'];
                    $license_num = $main_renewal['license_number'] ?? 'N/A';
                    $has_multiple = count($license_group['all_renewals']) > 1;
                ?>
                    <div class="record-card">
                        <div class="record-header" style="cursor: pointer;" onclick="toggleDetails('license-<?php echo $index; ?>')">
                            <div class="record-info">
                                <h3 style="display: flex; align-items: center; gap: 10px;">
                                    <span>🪪</span>
                                    <span>License: <?php echo htmlspecialchars($license_num); ?></span>
                                    <?php if ($has_multiple): ?>
                                        <span style="font-size: 12px; color: #667eea; background: #e3f2fd; padding: 2px 8px; border-radius: 12px;">
                                            <?php echo count($license_group['all_renewals']); ?> renewal(s)
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <p class="record-date">
                                    <strong>Valid Until:</strong> 
                                    <span style="color: #28a745; font-weight: bold;">
                                        <?php echo $license_group['latest_expiry'] ? date('d M Y', strtotime($license_group['latest_expiry'])) : 'N/A'; ?>
                                    </span>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="status-badge status-<?php echo strtolower($main_renewal['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($main_renewal['status'] ?? 'Pending'); ?>
                                </div>
                                <button class="toggle-btn" id="toggle-license-<?php echo $index; ?>">
                                    <span class="toggle-icon">▼</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="record-details" id="details-license-<?php echo $index; ?>" style="display: none;">
                            <?php if ($has_multiple): ?>
                                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                    <strong>📋 Renewal History:</strong>
                                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">
                                        This license has been renewed <?php echo count($license_group['all_renewals']); ?> time(s). 
                                        Expiry date has been extended to <?php echo date('d M Y', strtotime($license_group['latest_expiry'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-row">
                                <span>License Types:</span>
                                <strong><?php echo htmlspecialchars($main_renewal['license_types'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Total Amount Paid:</span>
                                <strong>RM <?php echo number_format($license_group['total_amount'], 2); ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Latest Valid Until:</span>
                                <strong style="color: #28a745;"><?php echo $license_group['latest_expiry'] ? date('d M Y', strtotime($license_group['latest_expiry'])) : 'N/A'; ?></strong>
                            </div>
                            
                            <?php if ($has_multiple): ?>
                                <hr style="margin: 15px 0;">
                                <div style="margin-bottom: 15px;">
                                    <strong style="display: block; margin-bottom: 10px;">All Renewal Records:</strong>
                                    <?php foreach ($license_group['all_renewals'] as $renewal): ?>
                                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #667eea;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span style="font-size: 13px; color: #666;">Renewed: <?php echo date('d M Y', strtotime($renewal['created_at'])); ?></span>
                                                <span style="font-size: 13px; color: #666;">Amount: RM <?php echo number_format($renewal['amount'] ?? 0, 2); ?></span>
                            </div>
                                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #999;">
                                                <span>Period: <?php echo ucwords(str_replace('_', ' ', $renewal['renewal_period'] ?? '')); ?></span>
                                                <span>Expiry: <?php echo $renewal['expiry_date'] ? date('d M Y', strtotime($renewal['expiry_date'])) : 'N/A'; ?></span>
                            </div>
                            <?php if ($renewal['transaction_id']): ?>
                                                <div style="margin-top: 5px;">
                                                    <button onclick="downloadReceipt('license', <?php echo $renewal['id']; ?>); event.stopPropagation();" 
                                                            class="btn-download" style="font-size: 12px; padding: 4px 12px;">
                                                        📄 Receipt
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <span>Renewal Period:</span>
                                    <strong><?php echo ucwords(str_replace('_', ' ', $main_renewal['renewal_period'] ?? '')); ?></strong>
                                </div>
                                <?php if ($main_renewal['transaction_id']): ?>
                                <div class="detail-row">
                                    <span>Transaction ID:</span>
                                        <strong><?php echo htmlspecialchars($main_renewal['transaction_id']); ?></strong>
                                </div>
                            <?php endif; ?>
                                <?php if ($main_renewal['receipt_number']): ?>
                                <div class="detail-row">
                                    <span>Receipt Number:</span>
                                        <strong><?php echo htmlspecialchars($main_renewal['receipt_number']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        
                            <?php if ($main_renewal['status'] === 'active'): ?>
                                <div class="record-actions" style="margin-top: 15px;">
                                    <?php if (!$has_multiple): ?>
                                        <button onclick="downloadReceipt('license', <?php echo $main_renewal['id']; ?>); event.stopPropagation();" class="btn-download">
                                            📄 Download Receipt
                                        </button>
                                    <?php endif; ?>
                                    <div class="info-note">
                                        <small>📍 Visit any JPJ branch within 30 days to collect your new license</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="roadtax-renewal.php" class="action-btn">
                <div class="action-icon">🚗</div>
                <div>
                    <strong>Renew Road Tax</strong>
                    <small>Start new road tax renewal</small>
                </div>
            </a>
            <a href="license-renewal.php" class="action-btn">
                <div class="action-icon">🪪</div>
                <div>
                    <strong>Renew License</strong>
                    <small>Start new license renewal</small>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
.status-section {
    margin-bottom: 40px;
}

.status-section h2 {
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
}

.no-records {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #dee2e6;
}

.no-records p {
    color: #7f8c8d;
    margin-bottom: 20px;
    font-size: 16px;
}

.btn-secondary {
    display: inline-block;
    padding: 12px 24px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.renewal-records {
    display: grid;
    gap: 20px;
}

.record-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.record-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.record-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.record-info h3 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.record-date {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-expired {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.record-details {
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row span {
    color: #7f8c8d;
    font-size: 14px;
}

.detail-row strong {
    color: #2c3e50;
    font-size: 14px;
}

.record-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-download {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #667eea;
    color: white;
}

.btn-download:hover {
    background: #5a67d8;
}

.info-note {
    flex: 1;
}

.info-note small {
    color: #7f8c8d;
    font-style: italic;
}

.quick-actions {
    background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #667eea;
}

.quick-actions h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
}

.action-btn:hover {
    border-color: #667eea;
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.action-icon {
    font-size: 2rem;
}

.action-btn strong {
    display: block;
    color: #2c3e50;
    margin-bottom: 4px;
}

.action-btn small {
    color: #7f8c8d;
    font-size: 13px;
}

.toggle-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 5px 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}

.toggle-btn:hover {
    background: #f8f9fa;
    border-radius: 4px;
}

.toggle-icon {
    font-size: 16px;
    color: #667eea;
    transition: transform 0.3s ease;
    display: inline-block;
}

.toggle-icon.rotated {
    transform: rotate(-90deg);
}

@media (max-width: 768px) {
    .record-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .record-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function downloadReceipt(type, id) {
    const url = `download-receipt.php?type=${type}&id=${id}`;
    // Open in new window - print dialog will auto-trigger
    const printWindow = window.open(url, '_blank', 'width=800,height=600');
    // Fallback: if popup blocked, redirect current page
    if (!printWindow || printWindow.closed || typeof printWindow.closed == 'undefined') {
        window.location.href = url;
    }
}

function toggleDetails(id) {
    const detailsDiv = document.getElementById('details-' + id);
    const toggleBtn = document.getElementById('toggle-' + id);
    const toggleIcon = toggleBtn.querySelector('.toggle-icon');
    
    if (detailsDiv.style.display === 'none') {
        detailsDiv.style.display = 'block';
        toggleIcon.classList.remove('rotated');
    } else {
        detailsDiv.style.display = 'none';
        toggleIcon.classList.add('rotated');
    }
}

// Auto-refresh status every 30 seconds for pending renewals
document.addEventListener('DOMContentLoaded', function() {
    const pendingElements = document.querySelectorAll('.status-pending');
    if (pendingElements.length > 0) {
        setInterval(function() {
            // Only refresh if there are pending renewals
            location.reload();
        }, 30000); // 30 seconds
    }
    
    // Initialize all details as collapsed
    document.querySelectorAll('[id^="details-roadtax-"]').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('[id^="toggle-roadtax-"]').forEach(function(btn) {
        btn.querySelector('.toggle-icon').classList.add('rotated');
    });
    document.querySelectorAll('[id^="details-license-"]').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('[id^="toggle-license-"]').forEach(function(btn) {
        btn.querySelector('.toggle-icon').classList.add('rotated');
    });
});
</script>

<?php include 'includes/footer.php'; ?>