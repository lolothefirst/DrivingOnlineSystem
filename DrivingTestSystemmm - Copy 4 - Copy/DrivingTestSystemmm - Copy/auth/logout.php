<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    $database = new Database();
    $conn = $database->getConnection();
    
    logActivity($conn, $_SESSION['user_id'], 'User logged out');
    
    session_destroy();
}

redirect('login.php');
?>
