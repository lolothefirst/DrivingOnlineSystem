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
        <h1 class="page-title">Driving Licenses Management</h1>
    </div>
    
    <div id="licenses-table"></div>
</div>

<!-- License Edit Modal -->
<div id="licenseModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="licenseModalTitle">Edit License</h2>
            <button class="admin-modal-close" onclick="closeLicenseModal()">&times;</button>
        </div>
        <form id="licenseForm" onsubmit="saveLicense(event)">
            <input type="hidden" id="license_id" name="id">
            
            <div class="admin-form-group">
                <label>License Number *</label>
                <input type="text" id="license_license_number" name="license_number" required style="text-transform: uppercase;">
            </div>
            
            <div class="admin-form-group">
                <label>License Class *</label>
                <input type="text" id="license_license_class" name="license_class" required placeholder="e.g., D/DA, B2/D">
            </div>
            
            <div class="admin-form-group">
                <label>Issue Date *</label>
                <input type="date" id="license_issue_date" name="issue_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Expiry Date *</label>
                <input type="date" id="license_expiry_date" name="expiry_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Status *</label>
                <select id="license_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="suspended">Suspended</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            
            <div class="admin-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeLicenseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let licensesTable;

document.addEventListener('DOMContentLoaded', function() {
    licensesTable = createAdminTable({
        tableId: 'licenses-table',
        apiUrl: 'api/licenses.php',
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
            { field: 'license_number', label: 'License Number', sortable: true },
            { field: 'license_class', label: 'Class', sortable: true },
            { field: 'issue_date', label: 'Issue Date', sortable: true, type: 'date' },
            { field: 'expiry_date', label: 'Expiry Date', sortable: true, type: 'date' },
            { 
                field: 'status', 
                label: 'Status', 
                sortable: true,
                type: 'badge',
                render: function(value) {
                    const colors = {
                        'active': 'success',
                        'expired': 'warning',
                        'suspended': 'danger',
                        'revoked': 'danger'
                    };
                    return `<span class="badge badge-${colors[value] || 'default'}">${value}</span>`;
                }
            }
        ],
        filters: {
            status: {
                type: 'select',
                label: 'Status',
                options: [
                    { value: 'active', label: 'Active' },
                    { value: 'expired', label: 'Expired' },
                    { value: 'suspended', label: 'Suspended' },
                    { value: 'revoked', label: 'Revoked' }
                ]
            }
        },
        onEdit: function(id) {
            editLicense(id);
        },
        onDelete: function(id) {
            deleteLicense(id);
        }
    });
});

function editLicense(id) {
    fetch(`api/licenses.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const license = data.data;
                document.getElementById('license_id').value = license.id;
                document.getElementById('license_license_number').value = license.license_number || '';
                document.getElementById('license_license_class').value = license.license_class || '';
                document.getElementById('license_issue_date').value = license.issue_date || '';
                document.getElementById('license_expiry_date').value = license.expiry_date || '';
                document.getElementById('license_status').value = license.status || 'active';
                
                document.getElementById('licenseModal').classList.add('active');
            }
        });
}

function closeLicenseModal() {
    document.getElementById('licenseModal').classList.remove('active');
}

async function saveLicense(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/licenses.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeLicenseModal();
            licensesTable.refresh();
        } else {
            alert(result.message || 'Error saving license');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving license. Please try again.');
    }
}

async function deleteLicense(id) {
    if (!confirm('Are you sure you want to delete this license?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/licenses.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            licensesTable.refresh();
        } else {
            alert(result.message || 'Error deleting license');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting license. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>






