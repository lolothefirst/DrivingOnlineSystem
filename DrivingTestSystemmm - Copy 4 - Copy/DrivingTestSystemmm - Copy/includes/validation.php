<?php

class Validator {
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate Malaysian IC number
     */
    public static function validateIC($ic) {
        // Remove any dashes or spaces
        $ic = preg_replace('/[^0-9]/', '', $ic);
        
        // Check if it's exactly 12 digits
        if (strlen($ic) !== 12) {
            return false;
        }
        
        // Basic format validation (YYMMDD-PB-XXXX)
        $year = substr($ic, 0, 2);
        $month = substr($ic, 2, 2);
        $day = substr($ic, 4, 2);
        
        // Validate month (01-12)
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        // Validate day (01-31)
        if ($day < 1 || $day > 31) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate Malaysian vehicle registration number
     */
    public static function validateVehicleNumber($plateNumber) {
        // Remove spaces and convert to uppercase
        $plateNumber = strtoupper(str_replace(' ', '', $plateNumber));
        
        // Malaysian plate number patterns:
        // ABC1234, WA1234A, B123ABC, etc.
        $patterns = [
            '/^[A-Z]{1,3}[0-9]{1,4}$/',        // ABC1234
            '/^[A-Z]{1,3}[0-9]{1,4}[A-Z]$/',   // WA1234A
            '/^[A-Z][0-9]{1,4}[A-Z]{1,3}$/',   // B123ABC
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plateNumber)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate phone number (Malaysian format)
     */
    public static function validatePhone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Malaysian phone numbers: 10-11 digits starting with 01 or 03-09
        return preg_match('/^(01[0-9]{8,9}|0[3-9][0-9]{7,8})$/', $phone);
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        // At least 8 characters, contains letters and numbers
        return strlen($password) >= 8 && 
               preg_match('/[A-Za-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    /**
     * Check if string length is between min and max
     */
    public static function lengthBetween($string, $min, $max) {
        $length = strlen(trim($string));
        return $length >= $min && $length <= $max;
    }
    
    /**
     * Validate vehicle year
     */
    public static function validateYear($year) {
        $currentYear = date('Y');
        return is_numeric($year) && 
               $year >= 1980 && 
               $year <= $currentYear;
    }
    
    /**
     * Validate engine capacity
     */
    public static function validateEngineCapacity($capacity) {
        return is_numeric($capacity) && 
               $capacity >= 50 && 
               $capacity <= 10000;
    }
    
    /**
     * Validate required field
     */
    public static function required($value) {
        return !empty(trim($value));
    }
    
    /**
     * Validate numeric value within range
     */
    public static function numericRange($value, $min, $max) {
        return is_numeric($value) && 
               $value >= $min && 
               $value <= $max;
    }
    
    /**
     * Validate date format (Y-m-d)
     */
    public static function validateDate($date) {
        if (empty($date)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate that date is not in the past
     */
    public static function dateNotPast($date) {
        return strtotime($date) >= strtotime(date('Y-m-d'));
    }
    
    /**
     * Validate Malaysian license number format
     */
    public static function validateLicenseNumber($license) {
        // Malaysian license format: D123456789 or similar
        return preg_match('/^[A-Z]{1,2}[0-9]{6,12}$/i', $license);
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Check file size (default 5MB)
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // Check file type if specified
        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate URL format
     */
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate that value is in allowed list
     */
    public static function inArray($value, $allowedValues) {
        return in_array($value, $allowedValues);
    }
    
    /**
     * Validate credit card number (16 digits)
     */
    public static function validateCreditCard($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        // Just check if it's exactly 16 digits
        return strlen($number) === 16;
    }
    
    /**
     * Validate Malaysian postcode
     */
    public static function validatePostcode($postcode) {
        return preg_match('/^[0-9]{5}$/', $postcode);
    }
    
    /**
     * Clean and validate text input
     */
    public static function cleanText($text, $allowHtml = false) {
        if (!$allowHtml) {
            return htmlspecialchars(strip_tags(trim($text)));
        }
        
        // Allow basic HTML tags
        $allowedTags = '<p><br><strong><em><u><ol><ul><li>';
        return strip_tags(trim($text), $allowedTags);
    }
    
    /**
     * Validate username format (alphanumeric, underscore, dash, 3-20 chars)
     */
    public static function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
    }
    
    /**
     * Validate card expiry date (MM/YY format)
     */
    public static function validateCardExpiry($expiry) {
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)) {
            return false;
        }
        
        $parts = explode('/', $expiry);
        $month = intval($parts[0]);
        $year = intval('20' . $parts[1]);
        $currentYear = intval(date('Y'));
        $currentMonth = intval(date('m'));
        
        if ($year < $currentYear) {
            return false;
        }
        
        if ($year == $currentYear && $month < $currentMonth) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate CVV (3-4 digits)
     */
    public static function validateCVV($cvv) {
        return preg_match('/^[0-9]{3,4}$/', $cvv);
    }
    
    /**
     * Validate amount (positive number with 2 decimal places)
     */
    public static function validateAmount($amount) {
        return is_numeric($amount) && $amount > 0 && $amount <= 999999.99;
    }
    
    /**
     * Validate renewal period
     */
    public static function validateRenewalPeriod($period, $allowed = ['1_year', '3_years', '5_years', '6_months', '12_months']) {
        return in_array($period, $allowed);
    }
}

/**
 * Helper function to display validation errors
 */
function displayErrors($errors) {
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Please correct the following errors:</strong>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * Helper function to preserve form data
 */
function oldValue($key, $default = '') {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default;
}

/**
 * Helper function to check if field has error
 */
function hasError($field, $errors) {
    foreach ($errors as $error) {
        if (strpos(strtolower($error), strtolower($field)) !== false) {
            return true;
        }
    }
    return false;
}

?>