<?php
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Student Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/learning.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?php echo SITE_NAME; ?></a>
                <nav>
                    <ul class="nav-menu">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="learning.php">Learning</a></li>
                        <li><a href="mock-test.php">Mock Tests</a></li>
                        <li><a href="book-exam.php">Book Exam</a></li>
                        <li><a href="bookings.php">My Bookings</a></li>
                        <li><a href="results.php">Results</a></li>
                        <!-- Added JPJ renewal services menu -->
                        <li class="dropdown">
                            <a href="#">JPJ Services</a>
                            <ul class="dropdown-menu">
                                <li><a href="vehicle-registration.php">Register Vehicle</a></li>
                                <li><a href="license-register.php">Register License</a></li>
                                <li><a href="roadtax-renewal.php">Road Tax Renewal</a></li>
                                <li><a href="license-renewal.php">License Renewal</a></li>
                                <li><a href="renewal-status.php">View Renewal Status</a></li>
                            </ul>
                        </li>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="../auth/logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <main class="main-content">
