<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get statistics for reports
$stats = [];

// Pass rate
$query = "SELECT 
          COUNT(CASE WHEN result = 'pass' THEN 1 END) as passed,
          COUNT(CASE WHEN result = 'fail' THEN 1 END) as failed,
          COUNT(*) as total
          FROM exam_results";
$stmt = $conn->prepare($query);
$stmt->execute();
$pass_stats = $stmt->fetch();
$stats['pass_rate'] = $pass_stats['total'] > 0 ? round(($pass_stats['passed'] / $pass_stats['total']) * 100, 2) : 0;
$stats['passed'] = $pass_stats['passed'];
$stats['failed'] = $pass_stats['failed'];

// Theory vs Practical
$query = "SELECT exam_type, COUNT(*) as count FROM exam_results GROUP BY exam_type";
$stmt = $conn->prepare($query);
$stmt->execute();
$type_stats = $stmt->fetchAll();

// Monthly bookings
$query = "SELECT DATE_FORMAT(booking_date, '%Y-%m') as month, COUNT(*) as count 
          FROM exam_bookings 
          WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY month 
          ORDER BY month";
$stmt = $conn->prepare($query);
$stmt->execute();
$monthly_bookings = $stmt->fetchAll();

// Top performing students
$query = "SELECT u.full_name, COUNT(*) as exams_taken, 
          SUM(CASE WHEN er.result = 'pass' THEN 1 ELSE 0 END) as exams_passed
          FROM exam_results er
          JOIN users u ON er.user_id = u.id
          GROUP BY er.user_id
          ORDER BY exams_passed DESC, exams_taken DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$top_students = $stmt->fetchAll();

// Test center performance
$query = "SELECT tc.center_name, COUNT(eb.id) as total_bookings,
          COUNT(er.id) as completed_tests,
          SUM(CASE WHEN er.result = 'pass' THEN 1 ELSE 0 END) as passed_tests
          FROM test_centers tc
          LEFT JOIN exam_sessions es ON tc.id = es.center_id
          LEFT JOIN exam_bookings eb ON es.id = eb.session_id
          LEFT JOIN exam_results er ON eb.id = er.booking_id
          GROUP BY tc.id
          ORDER BY total_bookings DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$center_stats = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Reports & Analytics</h1>
        <button onclick="window.print()" class="btn btn-primary">Print Report</button>
    </div>
    
    <div class="grid grid-3">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['pass_rate']; ?>%</div>
            <div class="stat-label">Overall Pass Rate</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="stat-value"><?php echo $stats['passed']; ?></div>
            <div class="stat-label">Total Passed</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <div class="stat-value"><?php echo $stats['failed']; ?></div>
            <div class="stat-label">Total Failed</div>
        </div>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">Exam Type Distribution</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Exam Type</th>
                        <th>Total Tests</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($type_stats as $type): ?>
                        <tr>
                            <td><?php echo ucfirst($type['exam_type']); ?></td>
                            <td><?php echo $type['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <div class="card-header">Monthly Bookings (Last 6 Months)</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Bookings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_bookings as $booking): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($booking['month'] . '-01')); ?></td>
                            <td><?php echo $booking['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">Top Performing Students</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Exams Taken</th>
                    <th>Exams Passed</th>
                    <th>Success Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo $student['exams_taken']; ?></td>
                        <td><?php echo $student['exams_passed']; ?></td>
                        <td>
                            <?php 
                            $rate = $student['exams_taken'] > 0 ? 
                                    round(($student['exams_passed'] / $student['exams_taken']) * 100, 2) : 0;
                            ?>
                            <span class="badge badge-<?php echo $rate >= 70 ? 'success' : 'warning'; ?>">
                                <?php echo $rate; ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <div class="card-header">Test Center Performance</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Test Center</th>
                    <th>Total Bookings</th>
                    <th>Completed Tests</th>
                    <th>Passed Tests</th>
                    <th>Pass Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($center_stats as $center): ?>
                    <?php 
                    $pass_rate = $center['completed_tests'] > 0 ? 
                                round(($center['passed_tests'] / $center['completed_tests']) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($center['center_name']); ?></td>
                        <td><?php echo $center['total_bookings']; ?></td>
                        <td><?php echo $center['completed_tests']; ?></td>
                        <td><?php echo $center['passed_tests']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $pass_rate >= 70 ? 'success' : 'warning'; ?>">
                                <?php echo $pass_rate; ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
