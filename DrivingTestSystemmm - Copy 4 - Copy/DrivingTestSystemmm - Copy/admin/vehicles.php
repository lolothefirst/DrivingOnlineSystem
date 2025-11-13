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
    $query = "DELETE FROM test_vehicles WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Vehicle deleted successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Delete vehicle', 'test_vehicles', $id);
    }
    redirect('vehicles.php');
}

// Handle toggle availability
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $query = "UPDATE test_vehicles SET is_available = NOT is_available WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Vehicle availability updated!";
        logActivity($conn, $_SESSION['user_id'], 'Toggle vehicle availability', 'test_vehicles', $id);
    }
    redirect('vehicles.php');
}

// Handle create/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $plate_number = trim($_POST['plate_number']);
    $vehicle_type = trim($_POST['vehicle_type']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $condition_status = $_POST['condition_status'];
    $last_maintenance = $_POST['last_maintenance'];
    $notes = trim($_POST['notes']);
    
    if ($id) {
        $query = "UPDATE test_vehicles SET plate_number = :plate_number, vehicle_type = :vehicle_type, 
                  model = :model, year = :year, condition_status = :condition_status, 
                  last_maintenance = :last_maintenance, notes = :notes WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
    } else {
        $query = "INSERT INTO test_vehicles (plate_number, vehicle_type, model, year, condition_status, last_maintenance, notes) 
                  VALUES (:plate_number, :vehicle_type, :model, :year, :condition_status, :last_maintenance, :notes)";
        $stmt = $conn->prepare($query);
    }
    
    $stmt->bindParam(':plate_number', $plate_number);
    $stmt->bindParam(':vehicle_type', $vehicle_type);
    $stmt->bindParam(':model', $model);
    $stmt->bindParam(':year', $year);
    $stmt->bindParam(':condition_status', $condition_status);
    $stmt->bindParam(':last_maintenance', $last_maintenance);
    $stmt->bindParam(':notes', $notes);
    
    if ($stmt->execute()) {
        $action = $id ? 'Update' : 'Create';
        logActivity($conn, $_SESSION['user_id'], "$action vehicle", 'test_vehicles', $id ?: $conn->lastInsertId());
        $_SESSION['success'] = "Vehicle " . ($id ? "updated" : "created") . " successfully!";
    }
    redirect('vehicles.php');
}

// Get all vehicles
$query = "SELECT * FROM test_vehicles ORDER BY plate_number";
$stmt = $conn->prepare($query);
$stmt->execute();
$vehicles = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Test Vehicles Management</h1>
            <button class="btn btn-primary" onclick="showModal()">Add New Vehicle</button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (count($vehicles) > 0): ?>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plate Number</th>
                            <th>Type</th>
                            <th>Model</th>
                            <th>Year</th>
                            <th>Condition</th>
                            <th>Last Maintenance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($vehicle['plate_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                <td><?php echo $vehicle['year']; ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $vehicle['condition_status'] === 'excellent' ? 'success' : 
                                            ($vehicle['condition_status'] === 'good' ? 'info' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($vehicle['condition_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $vehicle['last_maintenance'] ? formatDate($vehicle['last_maintenance'], 'M d, Y') : 'N/A'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $vehicle['is_available'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $vehicle['is_available'] ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm" onclick='editVehicle(<?php echo json_encode($vehicle); ?>)'>Edit</button>
                                    <a href="?toggle=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-warning">Toggle</a>
                                    <a href="?delete=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No vehicles found. Add your first vehicle above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="vehicleModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
        <h2 id="modalTitle">Add New Vehicle</h2>
        <form method="POST" class="form">
            <input type="hidden" id="vehicle_id" name="id">
            
            <div class="form-group">
                <label for="plate_number">Plate Number *</label>
                <input type="text" id="plate_number" name="plate_number" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="vehicle_type">Vehicle Type *</label>
                <input type="text" id="vehicle_type" name="vehicle_type" class="form-control" placeholder="e.g., Sedan, SUV" required>
            </div>

            <div class="form-group">
                <label for="model">Model *</label>
                <input type="text" id="model" name="model" class="form-control" placeholder="e.g., Toyota Camry" required>
            </div>

            <div class="form-group">
                <label for="year">Year *</label>
                <input type="number" id="year" name="year" class="form-control" min="2000" max="2030" required>
            </div>

            <div class="form-group">
                <label for="condition_status">Condition *</label>
                <select id="condition_status" name="condition_status" class="form-control" required>
                    <option value="excellent">Excellent</option>
                    <option value="good">Good</option>
                    <option value="fair">Fair</option>
                    <option value="poor">Poor</option>
                </select>
            </div>

            <div class="form-group">
                <label for="last_maintenance">Last Maintenance</label>
                <input type="date" id="last_maintenance" name="last_maintenance" class="form-control">
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Vehicle</button>
                <button type="button" class="btn btn-secondary" onclick="hideModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal() {
    document.getElementById('vehicleModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Add New Vehicle';
    document.querySelector('form').reset();
    document.getElementById('vehicle_id').value = '';
}

function hideModal() {
    document.getElementById('vehicleModal').style.display = 'none';
}

function editVehicle(vehicle) {
    document.getElementById('vehicleModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Vehicle';
    document.getElementById('vehicle_id').value = vehicle.id;
    document.getElementById('plate_number').value = vehicle.plate_number;
    document.getElementById('vehicle_type').value = vehicle.vehicle_type;
    document.getElementById('model').value = vehicle.model;
    document.getElementById('year').value = vehicle.year;
    document.getElementById('condition_status').value = vehicle.condition_status;
    document.getElementById('last_maintenance').value = vehicle.last_maintenance;
    document.getElementById('notes').value = vehicle.notes;
}
</script>

<?php include 'includes/footer.php'; ?>
