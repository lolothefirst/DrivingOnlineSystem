<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM examiners WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Examiner deleted successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Delete examiner', 'examiners', $id);
    }
    redirect('examiners.php');
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $query = "UPDATE examiners SET is_active = NOT is_active WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Examiner status updated!";
        logActivity($conn, $_SESSION['user_id'], 'Toggle examiner status', 'examiners', $id);
    }
    redirect('examiners.php');
}

// Handle create/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $full_name = trim($_POST['full_name']);
    $employee_id = trim($_POST['employee_id']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);
    
    $checkQuery = "SELECT id FROM examiners WHERE employee_id = :employee_id";
    if ($id) {
        $checkQuery .= " AND id != :id";
    }
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':employee_id', $employee_id);
    if ($id) {
        $checkStmt->bindParam(':id', $id);
    }
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        $_SESSION['error'] = "Employee ID already exists! Please use a different ID.";
        redirect('examiners.php');
    }
    
    if ($id) {
        $query = "UPDATE examiners SET full_name = :full_name, employee_id = :employee_id, phone = :phone, 
                  email = :email, specialization = :specialization WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
    } else {
        $query = "INSERT INTO examiners (full_name, employee_id, phone, email, specialization) 
                  VALUES (:full_name, :employee_id, :phone, :email, :specialization)";
        $stmt = $conn->prepare($query);
    }
    
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':specialization', $specialization);
    
    if ($stmt->execute()) {
        $action = $id ? 'Update' : 'Create';
        logActivity($conn, $_SESSION['user_id'], "$action examiner", 'examiners', $id ?: $conn->lastInsertId());
        $_SESSION['success'] = "Examiner " . ($id ? "updated" : "created") . " successfully!";
    }
    redirect('examiners.php');
}

// Get all examiners
$query = "SELECT * FROM examiners ORDER BY full_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$examiners = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Examiners Management</h1>
            <button class="btn btn-primary" onclick="showModal()">Add New Examiner</button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (count($examiners) > 0): ?>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Employee ID</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examiners as $examiner): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($examiner['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($examiner['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($examiner['email']); ?></td>
                                <td><?php echo htmlspecialchars($examiner['phone']); ?></td>
                                <td><?php echo htmlspecialchars($examiner['specialization']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $examiner['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $examiner['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm" onclick='editExaminer(<?php echo json_encode($examiner); ?>)'>Edit</button>
                                    <a href="?toggle=<?php echo $examiner['id']; ?>" class="btn btn-sm btn-warning">Toggle</a>
                                    <a href="?delete=<?php echo $examiner['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No examiners found. Add your first examiner above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="examinerModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
        <h2 id="modalTitle">Add New Examiner</h2>
        <form method="POST" class="form">
            <input type="hidden" id="examiner_id" name="id">
            
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="employee_id">Employee ID *</label>
                <input type="text" id="employee_id" name="employee_id" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>

            <div class="form-group">
                <label for="specialization">Specialization</label>
                <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., Theory, Practical, Both">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Examiner</button>
                <button type="button" class="btn btn-secondary" onclick="hideModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal() {
    document.getElementById('examinerModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Add New Examiner';
    document.querySelector('form').reset();
    document.getElementById('examiner_id').value = '';
}

function hideModal() {
    document.getElementById('examinerModal').style.display = 'none';
}

function editExaminer(examiner) {
    document.getElementById('examinerModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Examiner';
    document.getElementById('examiner_id').value = examiner.id;
    document.getElementById('full_name').value = examiner.full_name;
    document.getElementById('employee_id').value = examiner.employee_id;
    document.getElementById('email').value = examiner.email;
    document.getElementById('phone').value = examiner.phone;
    document.getElementById('specialization').value = examiner.specialization;
}
</script>

<?php include 'includes/footer.php'; ?>
