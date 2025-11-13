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
        <h1 class="page-title">Road Tax Renewals Management</h1>
    </div>
    
    <div id="roadtax-table"></div>
</div>

<!-- Roadtax Edit Modal -->
<div id="roadtaxModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="roadtaxModalTitle">Edit Road Tax Renewal</h2>
            <button class="admin-modal-close" onclick="closeRoadtaxModal()">&times;</button>
        </div>
        <form id="roadtaxForm" onsubmit="saveRoadtax(event)">
            <input type="hidden" id="roadtax_id" name="id">
            
            <div class="admin-form-group">
                <label>Vehicle Number *</label>
                <input type="text" id="roadtax_vehicle_number" name="vehicle_number" required style="text-transform: uppercase;">
            </div>
            
            <div class="admin-form-group">
                <label>Renewal Period *</label>
                <select id="roadtax_renewal_period" name="renewal_period" required>
                    <option value="6_months">6 Months</option>
                    <option value="12_months">12 Months</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label>Start Date *</label>
                <input type="date" id="roadtax_start_date" name="start_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Expiry Date *</label>
                <input type="date" id="roadtax_expiry_date" name="expiry_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Amount (RM) *</label>
                <input type="number" id="roadtax_amount" name="amount" step="0.01" min="0" required>
            </div>
            
            <div class="admin-form-group">
                <label>Payment Status *</label>
                <select id="roadtax_payment_status" name="payment_status" required>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label>Status *</label>
                <select id="roadtax_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="admin-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRoadtaxModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let roadtaxTable;

document.addEventListener('DOMContentLoaded', function() {
    roadtaxTable = createAdminTable({
        tableId: 'roadtax-table',
        apiUrl: 'api/roadtax.php',
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
            { field: 'vehicle_number', label: 'Vehicle Number', sortable: true },
            { 
                field: 'vehicle_make', 
                label: 'Vehicle', 
                sortable: true,
                render: function(value, row) {
                    return `${value} ${row.vehicle_model || ''} (${row.vehicle_year || ''})`;
                }
            },
            { field: 'renewal_period', label: 'Period', sortable: true },
            { field: 'start_date', label: 'Start Date', sortable: true, type: 'date' },
            { field: 'expiry_date', label: 'Expiry Date', sortable: true, type: 'date' },
            { field: 'amount', label: 'Amount', sortable: true, type: 'currency' },
            { 
                field: 'payment_status', 
                label: 'Payment', 
                sortable: true,
                type: 'badge',
                render: function(value) {
                    const colors = {
                        'paid': 'success',
                        'pending': 'warning',
                        'failed': 'danger'
                    };
                    return `<span class="badge badge-${colors[value] || 'default'}">${value}</span>`;
                }
            },
            { 
                field: 'status', 
                label: 'Status', 
                sortable: true,
                type: 'badge',
                render: function(value) {
                    const colors = {
                        'active': 'success',
                        'expired': 'warning',
                        'cancelled': 'danger'
                    };
                    return `<span class="badge badge-${colors[value] || 'default'}">${value}</span>`;
                }
            }
        ],
        filters: {
            payment_status: {
                type: 'select',
                label: 'Payment',
                options: [
                    { value: 'paid', label: 'Paid' },
                    { value: 'pending', label: 'Pending' },
                    { value: 'failed', label: 'Failed' }
                ]
            },
            status: {
                type: 'select',
                label: 'Status',
                options: [
                    { value: 'active', label: 'Active' },
                    { value: 'expired', label: 'Expired' },
                    { value: 'cancelled', label: 'Cancelled' }
                ]
            }
        },
        onEdit: function(id) {
            editRoadtax(id);
        },
        onDelete: function(id) {
            deleteRoadtax(id);
        }
    });
});

function editRoadtax(id) {
    fetch(`api/roadtax.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const roadtax = data.data;
                document.getElementById('roadtax_id').value = roadtax.id;
                document.getElementById('roadtax_vehicle_number').value = roadtax.vehicle_number || '';
                document.getElementById('roadtax_renewal_period').value = roadtax.renewal_period || '12_months';
                document.getElementById('roadtax_start_date').value = roadtax.start_date || '';
                document.getElementById('roadtax_expiry_date').value = roadtax.expiry_date || '';
                document.getElementById('roadtax_amount').value = roadtax.amount || '';
                document.getElementById('roadtax_payment_status').value = roadtax.payment_status || 'pending';
                document.getElementById('roadtax_status').value = roadtax.status || 'active';
                
                document.getElementById('roadtaxModal').classList.add('active');
            }
        });
}

function closeRoadtaxModal() {
    document.getElementById('roadtaxModal').classList.remove('active');
}

async function saveRoadtax(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/roadtax.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeRoadtaxModal();
            roadtaxTable.refresh();
        } else {
            alert(result.message || 'Error saving road tax renewal');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving road tax renewal. Please try again.');
    }
}

async function deleteRoadtax(id) {
    if (!confirm('Are you sure you want to delete this road tax renewal?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/roadtax.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            roadtaxTable.refresh();
        } else {
            alert(result.message || 'Error deleting road tax renewal');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting road tax renewal. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>






