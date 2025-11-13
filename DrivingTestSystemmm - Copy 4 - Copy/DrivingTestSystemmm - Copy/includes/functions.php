<?php
// Utility functions

// Log system activity
function logActivity($conn, $user_id, $action, $table_name = null, $record_id = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $query = "INSERT INTO system_logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                  VALUES (:user_id, :action, :table_name, :record_id, :ip_address, :user_agent)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':table_name', $table_name);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        return false;
    }
}

// File upload handler
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $subdirectory = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    // Support legacy signature where second argument is actually subdirectory
    if (!is_array($allowed_types) && is_string($allowed_types) && $subdirectory === '') {
        $subdirectory = $allowed_types;
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
    }

    // Ensure allowed types is an array
    if (!is_array($allowed_types)) {
        $allowed_types = [$allowed_types];
    }

    // Normalize subdirectory
    $subdirectory = trim($subdirectory, '/\\');

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_directory = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR);

    if (!empty($subdirectory)) {
        $target_directory .= DIRECTORY_SEPARATOR . $subdirectory;
    }

    $upload_path = $target_directory . DIRECTORY_SEPARATOR . $new_filename;
    
    // Create upload directory if it doesn't exist
    if (!file_exists($target_directory)) {
        mkdir($target_directory, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $relative_path = ($subdirectory ? $subdirectory . '/' : '') . $new_filename;
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $relative_path
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

// Format date for display
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Calculate percentage
function calculatePercentage($correct, $total) {
    if ($total == 0) return 0;
    return round(($correct / $total) * 100, 2);
}

// Generate certificate number
function generateCertificateNumber() {
    return 'CERT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
}

// Send email (with error handling for development environments)
function sendEmail($to, $subject, $message) {
    // Skip email in development if SMTP is not configured
    if (!defined('ENABLE_EMAIL') || ENABLE_EMAIL === false) {
        error_log("Email would be sent to: $to | Subject: $subject");
        return true; // Return true to not break the flow
    }
    
    $headers = "From: " . SITE_NAME . " <noreply@drivingtest.com>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Suppress warning and handle error gracefully
    $result = @mail($to, $subject, $message, $headers);
    
    if (!$result) {
        error_log("Failed to send email to: $to | Subject: $subject");
        return false;
    }
    
    return true;
}

// Check if exam slot is available
function isSlotAvailable($conn, $session_id) {
    $query = "SELECT available_slots FROM exam_sessions WHERE id = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();
    
    $result = $stmt->fetch();
    return $result && $result['available_slots'] > 0;
}

// Update available slots
function updateAvailableSlots($conn, $session_id, $increment = false) {
    if ($increment) {
        $query = "UPDATE exam_sessions SET available_slots = available_slots + 1 WHERE id = :session_id";
    } else {
        $query = "UPDATE exam_sessions SET available_slots = available_slots - 1 WHERE id = :session_id";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    return $stmt->execute();
}

/**
 * Get column metadata from INFORMATION_SCHEMA.
 */
function getColumnInfo(PDO $conn, $table, $column) {
    $sql = "
        SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':table' => $table,
        ':column' => $column
    ]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    return $info ?: null;
}

/**
 * Ensure a column exists in the given table, adding it if required.
 *
 * The $definition must include the column name, e.g. "`example` VARCHAR(20) NULL".
 */
function ensureColumnExists(PDO $conn, $table, $column, $definition) {
    // Table & column names are controlled internally; minimal sanitisation for safety.
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);

    $stmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($stmt === false || $stmt->fetch() === false) {
        $sql = "ALTER TABLE `$table` ADD COLUMN $definition";
        $conn->exec($sql);
        error_log("[Schema] Added column `$column` to `$table`");
    }
}

/**
 * Ensure a column is nullable, modifying it if it is currently NOT NULL.
 *
 * The $definition must contain the column name and desired type, e.g. "`license_id` INT NULL".
 */
function ensureColumnNullable(PDO $conn, $table, $column, $definition) {
    $info = getColumnInfo($conn, $table, $column);
    if ($info && strtoupper($info['IS_NULLABLE']) === 'NO') {
        $sql = "ALTER TABLE `$table` MODIFY COLUMN $definition";
        $conn->exec($sql);
        error_log("[Schema] Updated `$table`.`$column` to allow NULL values");
    }
}

/**
 * Ensure the license_renewals table contains the columns used by the renewal flow.
 */
function ensureLicenseRenewalSchema(PDO $conn) {
    // Allow legacy license_id column to be NULL when not in use
    ensureColumnNullable($conn, 'license_renewals', 'license_id', '`license_id` INT NULL');

    // Add new columns when missing
    ensureColumnExists($conn, 'license_renewals', 'ic_number', '`ic_number` VARCHAR(20) NULL AFTER `user_id`');
    ensureColumnExists($conn, 'license_renewals', 'license_number', '`license_number` VARCHAR(50) NULL AFTER `ic_number`');
    ensureColumnExists($conn, 'license_renewals', 'new_license_number', '`new_license_number` VARCHAR(50) NULL AFTER `license_number`');
    ensureColumnExists($conn, 'license_renewals', 'license_types', '`license_types` VARCHAR(100) NULL AFTER `new_license_number`');
    ensureColumnExists($conn, 'license_renewals', 'renewal_period', '`renewal_period` VARCHAR(20) NOT NULL DEFAULT \'1_year\' AFTER `license_types`');

    // Ensure amount column exists (legacy schema already has it, but keep for completeness)
    ensureColumnExists($conn, 'license_renewals', 'amount', '`amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `expiry_date`');

    // Ensure payment meta columns exist
    ensureColumnExists($conn, 'license_renewals', 'payment_status', "`payment_status` ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending' AFTER `amount`");
    ensureColumnExists($conn, 'license_renewals', 'payment_method', '`payment_method` VARCHAR(50) NULL AFTER `payment_status`');
    ensureColumnExists($conn, 'license_renewals', 'transaction_id', '`transaction_id` VARCHAR(100) NULL AFTER `payment_method`');
    ensureColumnExists($conn, 'license_renewals', 'receipt_number', '`receipt_number` VARCHAR(50) NULL AFTER `transaction_id`');
    ensureColumnExists($conn, 'license_renewals', 'status', "`status` ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'active' AFTER `receipt_number`");
    ensureColumnExists($conn, 'license_renewals', 'created_at', '`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
}

/**
 * Ensure the roadtax_renewals table contains the columns used by the renewal flow.
 */
function ensureRoadtaxRenewalSchema(PDO $conn) {
    // Allow legacy vehicle_id column to be NULL when not in use
    ensureColumnNullable($conn, 'roadtax_renewals', 'vehicle_id', '`vehicle_id` INT NULL');

    // Add additional descriptive columns if they are missing
    ensureColumnExists($conn, 'roadtax_renewals', 'vehicle_number', '`vehicle_number` VARCHAR(20) NULL AFTER `user_id`');
    ensureColumnExists($conn, 'roadtax_renewals', 'vehicle_make', '`vehicle_make` VARCHAR(100) NULL AFTER `vehicle_number`');
    ensureColumnExists($conn, 'roadtax_renewals', 'vehicle_model', '`vehicle_model` VARCHAR(100) NULL AFTER `vehicle_make`');
    ensureColumnExists($conn, 'roadtax_renewals', 'vehicle_year', '`vehicle_year` INT NULL AFTER `vehicle_model`');
    ensureColumnExists($conn, 'roadtax_renewals', 'engine_capacity', '`engine_capacity` INT NULL AFTER `vehicle_year`');

    // Ensure payment meta columns exist (legacy schema already includes most of these)
    ensureColumnExists($conn, 'roadtax_renewals', 'renewal_period', "`renewal_period` VARCHAR(20) NOT NULL DEFAULT '12_months' AFTER `engine_capacity`");
    ensureColumnExists($conn, 'roadtax_renewals', 'amount', '`amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `expiry_date`');
    ensureColumnExists($conn, 'roadtax_renewals', 'payment_status', "`payment_status` ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending' AFTER `amount`");
    ensureColumnExists($conn, 'roadtax_renewals', 'payment_method', '`payment_method` VARCHAR(50) NULL AFTER `payment_status`');
    ensureColumnExists($conn, 'roadtax_renewals', 'transaction_id', '`transaction_id` VARCHAR(100) NULL AFTER `payment_method`');
    ensureColumnExists($conn, 'roadtax_renewals', 'receipt_number', '`receipt_number` VARCHAR(50) NULL AFTER `transaction_id`');
    ensureColumnExists($conn, 'roadtax_renewals', 'status', "`status` ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'active' AFTER `receipt_number`");
    ensureColumnExists($conn, 'roadtax_renewals', 'created_at', '`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
}
?>
