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
    $query = "DELETE FROM test_centers WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Test center deleted successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Delete test center', 'test_centers', $id);
    }
    redirect('test-centers.php');
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $query = "UPDATE test_centers SET is_active = NOT is_active WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Test center status updated!";
        logActivity($conn, $_SESSION['user_id'], 'Toggle center status', 'test_centers', $id);
    }
    redirect('test-centers.php');
}

// Handle create/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $center_name = trim($_POST['center_name']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    $capacity = intval($_POST['capacity']);
    $facilities = trim($_POST['facilities']);
    
    if ($id) {
        $query = "UPDATE test_centers SET center_name = :center_name, address = :address, city = :city, 
                  phone = :phone, capacity = :capacity, facilities = :facilities WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
    } else {
        $query = "INSERT INTO test_centers (center_name, address, city, phone, capacity, facilities) 
                  VALUES (:center_name, :address, :city, :phone, :capacity, :facilities)";
        $stmt = $conn->prepare($query);
    }
    
    $stmt->bindParam(':center_name', $center_name);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':city', $city);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':facilities', $facilities);
    
    if ($stmt->execute()) {
        $action = $id ? 'Update' : 'Create';
        logActivity($conn, $_SESSION['user_id'], "$action test center", 'test_centers', $id ?: $conn->lastInsertId());
        $_SESSION['success'] = "Test center " . ($id ? "updated" : "created") . " successfully!";
    }
    redirect('test-centers.php');
}

// Get all test centers
$query = "SELECT * FROM test_centers ORDER BY center_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$centers = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Test Centers Management</h1>
            <button class="btn btn-primary" onclick="showModal()">Add New Center</button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (count($centers) > 0): ?>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Center Name</th>
                            <th>City</th>
                            <th>Phone</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($centers as $center): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($center['center_name']); ?></td>
                                <td><?php echo htmlspecialchars($center['city']); ?></td>
                                <td><?php echo htmlspecialchars($center['phone']); ?></td>
                                <td><?php echo $center['capacity']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $center['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $center['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm" onclick='editCenter(<?php echo json_encode($center); ?>)'>Edit</button>
                                    <a href="?toggle=<?php echo $center['id']; ?>" class="btn btn-sm btn-warning">Toggle</a>
                                    <a href="?delete=<?php echo $center['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No test centers found. Add your first center above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="centerModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
        <h2 id="modalTitle">Add New Test Center</h2>
        <form method="POST" class="form">
            <input type="hidden" id="center_id" name="id">
            
            <div class="form-group">
                <label for="center_name">Center Name *</label>
                <input type="text" id="center_name" name="center_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="address">Address *</label>
                <textarea id="address" name="address" class="form-control" rows="2" required></textarea>
            </div>

            <div class="form-group">
                <label for="city">City *</label>
                <input type="text" id="city" name="city" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>

            <div class="form-group">
                <label for="capacity">Capacity *</label>
                <input type="number" id="capacity" name="capacity" class="form-control" value="10" min="1" required>
            </div>

            <div class="form-group">
                <label for="facilities">Facilities</label>
                <textarea id="facilities" name="facilities" class="form-control" rows="3" placeholder="e.g., Parking, Waiting Room, Restrooms"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Center</button>
                <button type="button" class="btn btn-secondary" onclick="hideModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal() {
    document.getElementById('centerModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Add New Test Center';
    document.querySelector('form').reset();
    document.getElementById('center_id').value = '';
}

function hideModal() {
    document.getElementById('centerModal').style.display = 'none';
}

function editCenter(center) {
    document.getElementById('centerModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Test Center';
    document.getElementById('center_id').value = center.id;
    document.getElementById('center_name').value = center.center_name;
    document.getElementById('address').value = center.address;
    document.getElementById('city').value = center.city;
    document.getElementById('phone').value = center.phone;
    document.getElementById('capacity').value = center.capacity;
    document.getElementById('facilities').value = center.facilities;
}
</script>

<?php include 'includes/footer.php'; ?>
