-- Additional tables for Malaysia JPJ Road Tax and License Renewal features

-- Vehicle registrations table
CREATE TABLE IF NOT EXISTS vehicle_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    registration_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_make VARCHAR(100) NOT NULL,
    vehicle_model VARCHAR(100) NOT NULL,
    vehicle_year INT NOT NULL,
    vehicle_type ENUM('car', 'motorcycle', 'van', 'lorry') DEFAULT 'car',
    engine_capacity INT NOT NULL,
    chassis_number VARCHAR(50) NOT NULL,
    engine_number VARCHAR(50) NOT NULL,
    registered_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Road tax renewals table
CREATE TABLE IF NOT EXISTS roadtax_renewals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    renewal_period ENUM('6_months', '12_months') DEFAULT '12_months',
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100) UNIQUE,
    receipt_number VARCHAR(50) UNIQUE,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle_registrations(id) ON DELETE CASCADE
);

-- Driving licenses table
CREATE TABLE IF NOT EXISTS driving_licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    license_class VARCHAR(20) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('active', 'expired', 'suspended', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- License renewals table
CREATE TABLE IF NOT EXISTS license_renewals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_id INT NOT NULL,
    renewal_years INT DEFAULT 5,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100) UNIQUE,
    receipt_number VARCHAR(50) UNIQUE,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (license_id) REFERENCES driving_licenses(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    payment_type ENUM('roadtax', 'license', 'exam', 'other') NOT NULL,
    reference_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('fpx', 'credit_card', 'ewallet') NOT NULL,
    payment_status ENUM('pending', 'processing', 'success', 'failed') DEFAULT 'pending',
    payment_gateway_response TEXT,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample vehicle registration for testing
INSERT INTO vehicle_registrations (user_id, registration_number, vehicle_make, vehicle_model, vehicle_year, vehicle_type, engine_capacity, chassis_number, engine_number, registered_date)
VALUES (1, 'WXY1234', 'Perodua', 'Myvi', 2022, 'car', 1500, 'CH1234567890', 'EN9876543210', '2022-01-15');

-- Insert sample driving license for testing
INSERT INTO driving_licenses (user_id, license_number, license_class, issue_date, expiry_date, status)
VALUES (1, 'D1234567890123', 'D/DA', '2020-01-01', '2025-01-01', 'active');
