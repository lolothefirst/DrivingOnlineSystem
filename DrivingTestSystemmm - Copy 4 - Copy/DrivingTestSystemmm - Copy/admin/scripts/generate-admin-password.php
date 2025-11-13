<?php
/**
 * Secure admin password hash generator with validations.
 *
 * Usage (CLI):
 *   php generate-admin-password.php --password="StrongP@ssw0rd" --username="admin" --email="admin@drivingtest.com" --full-name="System Administrator" --id-number="ADMIN001"
 *
 * Notes:
 * - If --password is omitted, the script will use "admin123" and print a warning.
 * - This script is intended to be run locally/CLI. It does not modify the database directly; it outputs the SQL you can run.
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Parse CLI options
$options = getopt('', [
    'password::',
    'username::',
    'email::',
    'full-name::',
    'id-number::'
]);

$password = $options['password'] ?? 'admin123';
$username = $options['username'] ?? 'admin';
$email = $options['email'] ?? 'admin@drivingtest.com';
$fullName = $options['full-name'] ?? 'System Administrator';
$idNumber = $options['id-number'] ?? 'ADMIN001';

// Basic sanitization for output safety (not needed for hashing)
function sanitizeOutput($value) {
    return str_replace(["\r", "\n"], [' ', ' '], trim((string)$value));
}

$username = sanitizeOutput($username);
$email = sanitizeOutput($email);
$fullName = sanitizeOutput($fullName);
$idNumber = sanitizeOutput($idNumber);

// Validations
$errors = [];

// Username: 3-100, alnum + _.-
if ($username === '' || strlen($username) < 3 || strlen($username) > 100 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    $errors[] = 'Username must be 3-100 chars and contain only letters, numbers, dot, underscore, or hyphen.';
}

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}

// Full name length
if (strlen($fullName) < 2 || strlen($fullName) > 150) {
    $errors[] = 'Full name must be between 2 and 150 characters.';
}

// ID number length
if ($idNumber === '' || strlen($idNumber) > 50) {
    $errors[] = 'ID number is required and must be at most 50 characters.';
}

// Password strength
$passwordWarnings = [];
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if (!preg_match('/[A-Z]/', $password)) {
    $passwordWarnings[] = 'Consider adding an uppercase letter.';
}
if (!preg_match('/[a-z]/', $password)) {
    $passwordWarnings[] = 'Consider adding a lowercase letter.';
}
if (!preg_match('/[0-9]/', $password)) {
    $passwordWarnings[] = 'Consider adding a number.';
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $passwordWarnings[] = 'Consider adding a special character.';
}

// Common weak defaults
$weakDefaults = ['password','12345678','admin','admin123','qwerty','letmein'];
if (in_array(strtolower($password), $weakDefaults, true)) {
    $passwordWarnings[] = 'This password is commonly used and weak.';
}

if (!empty($errors)) {
    echo "Validation errors:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
    exit(1);
}

if (!empty($passwordWarnings)) {
    echo "Password warnings:\n";
    foreach ($passwordWarnings as $w) {
        echo " - $w\n";
    }
    echo "\n";
}

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Generated credentials and SQL\n";
echo "-----------------------------\n";
echo "Username : $username\n";
echo "Email    : $email\n";
echo "Full Name: $fullName\n";
echo "ID Number: $idNumber\n";
echo "Password : [hidden]\n";
echo "Hash     : $hash\n\n";

echo "Copy this SQL to create/update the admin user:\n\n";
echo "INSERT INTO users (username, email, password, full_name, id_number, user_type, status)\n";
echo "VALUES ('" . addslashes($username) . "', '" . addslashes($email) . "', '" . addslashes($hash) . "', '" . addslashes($fullName) . "', '" . addslashes($idNumber) . "', 'admin', 'active')\n";
echo "ON DUPLICATE KEY UPDATE password = '" . addslashes($hash) . "', full_name = '" . addslashes($fullName) . "', status = 'active';\n";

// Exit code 0 indicates success
exit(0);
