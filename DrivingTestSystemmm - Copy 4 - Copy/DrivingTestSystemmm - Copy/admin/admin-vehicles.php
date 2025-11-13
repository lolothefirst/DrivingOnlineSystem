<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Vehicle Registrations Management</h1>
    </div>
    
    <div id="vehicles-table"></div>
</div>

<!-- Vehicle Edit Modal -->
<div id="vehicleModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="vehicleModalTitle">Edit Vehicle</h2>
            <button class="admin-modal-close" onclick="closeVehicleModal()">&times;</button>
        </div>
        <form id="vehicleForm" onsubmit="saveVehicle(event)">
            <input type="hidden" id="vehicle_id" name="id">
            
            <div class="admin-form-group">
                <label>Registration Number *</label>
                <input type="text" id="vehicle_registration_number" name="registration_number" required style="text-transform: uppercase;">
            </div>
            
            <div class="admin-form-group">
                <label>Vehicle Make *</label>
                <input type="text" id="vehicle_make" name="vehicle_make" required>
            </div>
            
            <div class="admin-form-group">
                <label>Vehicle Model *</label>
                <input type="text" id="vehicle_model" name="vehicle_model" required>
            </div>
            
            <div class="admin-form-group">
                <label>Vehicle Year *</label>
                <input type="number" id="vehicle_year" name="vehicle_year" min="1980" max="<?php echo date('Y'); ?>" required>
            </div>
            
            <div class="admin-form-group">
                <label>Vehicle Type *</label>
                <select id="vehicle_type" name="vehicle_type" required>
                    <option value="car">Car</option>
                    <option value="motorcycle">Motorcycle</option>
                    <option value="van">Van</option>
                    <option value="lorry">Lorry</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label>Engine Capacity (cc) *</label>
                <input type="number" id="vehicle_engine_capacity" name="engine_capacity" min="50" max="10000" required>
            </div>
            
            <div class="admin-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeVehicleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let vehiclesTable;

document.addEventListener('DOMContentLoaded', function() {
    vehiclesTable = createAdminTable({
        tableId: 'vehicles-table',
        apiUrl: 'api/vehicles.php',
        pageSize: 10,
        columns: [
            { field: 'id', label: 'ID', sortable: true },
            { 
                field: 'user_id', 
                label: 'Owner', 
                sortable: true,
                render: function(value, row) {
                    return row.owner_name || `User #${value}`;
                }
            },
            { field: 'registration_number', label: 'Registration', sortable: true },
            { 
                field: 'vehicle_make', 
                label: 'Make & Model', 
                sortable: true,
                render: function(value, row) {
                    return `${value} ${row.vehicle_model}`;
                }
            },
            { field: 'vehicle_year', label: 'Year', sortable: true },
            { field: 'engine_capacity', label: 'Engine (cc)', sortable: true },
            { field: 'created_at', label: 'Registered', sortable: true, type: 'date' }
        ],
        filters: {
            vehicle_type: {
                type: 'select',
                label: 'Type',
                options: [
                    { value: 'car', label: 'Car' },
                    { value: 'motorcycle', label: 'Motorcycle' },
                    { value: 'van', label: 'Van' },
                    { value: 'lorry', label: 'Lorry' }
                ]
            }
        },
        onEdit: function(id) {
            editVehicle(id);
        },
        onDelete: function(id) {
            deleteVehicle(id);
        }
    });
});

function editVehicle(id) {
    fetch(`api/vehicles.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const vehicle = data.data;
                document.getElementById('vehicle_id').value = vehicle.id;
                document.getElementById('vehicle_registration_number').value = vehicle.registration_number;
                document.getElementById('vehicle_make').value = vehicle.vehicle_make;
                document.getElementById('vehicle_model').value = vehicle.vehicle_model;
                document.getElementById('vehicle_year').value = vehicle.vehicle_year;
                document.getElementById('vehicle_type').value = vehicle.vehicle_type;
                document.getElementById('vehicle_engine_capacity').value = vehicle.engine_capacity;
                
                document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
                document.getElementById('vehicleModal').classList.add('active');
            }
        });
}

function closeVehicleModal() {
    document.getElementById('vehicleModal').classList.remove('active');
}

async function saveVehicle(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/vehicles.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeVehicleModal();
            vehiclesTable.refresh();
        } else {
            alert(result.message || 'Error saving vehicle');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving vehicle. Please try again.');
    }
}

async function deleteVehicle(id) {
    if (!confirm('Are you sure you want to delete this vehicle registration?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/vehicles.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            vehiclesTable.refresh();
        } else {
            alert(result.message || 'Error deleting vehicle');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting vehicle. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>





