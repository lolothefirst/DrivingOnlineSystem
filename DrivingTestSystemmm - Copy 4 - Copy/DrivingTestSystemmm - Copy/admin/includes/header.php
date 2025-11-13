<?php
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <!-- Added sidebar navigation with hamburger menu toggle -->
    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><?php echo SITE_NAME; ?></h2>
                <button class="sidebar-close" id="sidebarClose">&times;</button>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <div class="nav-section">Management</div>
                
                <a href="students.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Students</span>
                </a>
                
                <a href="exams.php" class="nav-item">
                    <span class="nav-icon">📝</span>
                    <span class="nav-text">Exams</span>
                </a>
                
                <a href="questions.php" class="nav-item">
                    <span class="nav-icon">❓</span>
                    <span class="nav-text">Questions</span>
                </a>
                
                <a href="results-manage.php" class="nav-item">
                    <span class="nav-icon">🏆</span>
                    <span class="nav-text">Results</span>
                </a>
                
                <div class="nav-section">Resources</div>
                
                <a href="learning-materials.php" class="nav-item">
                    <span class="nav-icon">📚</span>
                    <span class="nav-text">Learning Materials</span>
                </a>
                
                <a href="test-centers.php" class="nav-item">
                    <span class="nav-icon">🏢</span>
                    <span class="nav-text">Test Centers</span>
                </a>
                
                <a href="examiners.php" class="nav-item">
                    <span class="nav-icon">👔</span>
                    <span class="nav-text">Examiners</span>
                </a>
                
                <a href="vehicles.php" class="nav-item">
                    <span class="nav-icon">🚗</span>
                    <span class="nav-text">Vehicles</span>
                </a>
                
                <div class="nav-section">JPJ Services</div>
                
                <a href="admin-vehicles.php" class="nav-item">
                    <span class="nav-icon">🚙</span>
                    <span class="nav-text">Vehicle Registrations</span>
                </a>
                
                <a href="admin-roadtax.php" class="nav-item">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Road Tax Renewals</span>
                </a>
                
                <a href="admin-licenses.php" class="nav-item">
                    <span class="nav-icon">🪪</span>
                    <span class="nav-text">Licenses</span>
                </a>
                
                <a href="admin-license-renewals.php" class="nav-item">
                    <span class="nav-icon">🔄</span>
                    <span class="nav-text">License Renewals</span>
                </a>
                
                <div class="nav-section">System</div>
                
                <a href="announcements.php" class="nav-item">
                    <span class="nav-icon">📢</span>
                    <span class="nav-text">Announcements</span>
                </a>
                
                <a href="reports.php" class="nav-item">
                    <span class="nav-icon">📈</span>
                    <span class="nav-text">Reports</span>
                </a>
                
                <a href="activity-logs.php" class="nav-item">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Activity Logs</span>
                </a>
                
                <a href="settings.php" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Settings</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-menu" id="hamburgerMenu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1 class="header-title">Admin Panel</h1>
                </div>
                
                <div class="header-right">
                    <span class="admin-name">Admin</span>
                    <a href="../auth/logout.php" class="logout-btn">Logout</a>
                </div>
            </header>
            
            <main class="main-content">
