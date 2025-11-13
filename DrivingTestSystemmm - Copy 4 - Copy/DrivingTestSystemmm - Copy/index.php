<?php
require_once 'config/config.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Redirect based on user type
if (isAdmin()) {
    redirect('admin/dashboard.php');
} else {
    redirect('student/dashboard.php');
}
?>
