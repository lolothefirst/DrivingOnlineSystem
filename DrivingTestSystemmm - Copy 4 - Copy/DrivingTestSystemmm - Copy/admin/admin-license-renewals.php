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
        <h1 class="page-title">License Renewals Management</h1>
    </div>
    
    <div id="license-renewals-table"></div>
</div>

<!-- License Renewal Edit Modal -->
<div id="licenseRenewalModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="licenseRenewalModalTitle">Edit License Renewal</h2>
            <button class="admin-modal-close" onclick="closeLicenseRenewalModal()">&times;</button>
        </div>
        <form id="licenseRenewalForm" onsubmit="saveLicenseRenewal(event)">
            <input type="hidden" id="license_renewal_id" name="id">
            
            <div class="admin-form-group">
                <label>License Number *</label>
                <input type="text" id="license_renewal_license_number" name="license_number" required style="text-transform: uppercase;">
            </div>
            
            <div class="admin-form-group">
                <label>Renewal Period *</label>
                <select id="license_renewal_renewal_period" name="renewal_period" required>
                    <option value="1_year">1 Year</option>
                    <option value="3_years">3 Years</option>
                    <option value="5_years">5 Years</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label>Start Date *</label>
                <input type="date" id="license_renewal_start_date" name="start_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Expiry Date *</label>
                <input type="date" id="license_renewal_expiry_date" name="expiry_date" required>
            </div>
            
            <div class="admin-form-group">
                <label>Amount (RM) *</label>
                <input type="number" id="license_renewal_amount" name="amount" step="0.01" min="0" required>
            </div>
            
            <div class="admin-form-group">
                <label>Payment Status *</label>
                <select id="license_renewal_payment_status" name="payment_status" required>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label>Status *</label>
                <select id="license_renewal_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="admin-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeLicenseRenewalModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let licenseRenewalsTable;

document.addEventListener('DOMContentLoaded', function() {
    licenseRenewalsTable = createAdminTable({
        tableId: 'license-renewals-table',
        apiUrl: 'api/license-renewals.php',
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
            { field: 'license_types', label: 'License Types', sortable: false },
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
            editLicenseRenewal(id);
        },
        onDelete: function(id) {
            deleteLicenseRenewal(id);
        }
    });
});

function editLicenseRenewal(id) {
    fetch(`api/license-renewals.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const renewal = data.data;
                document.getElementById('license_renewal_id').value = renewal.id;
                document.getElementById('license_renewal_license_number').value = renewal.license_number || '';
                document.getElementById('license_renewal_renewal_period').value = renewal.renewal_period || '1_year';
                document.getElementById('license_renewal_start_date').value = renewal.start_date || '';
                document.getElementById('license_renewal_expiry_date').value = renewal.expiry_date || '';
                document.getElementById('license_renewal_amount').value = renewal.amount || '';
                document.getElementById('license_renewal_payment_status').value = renewal.payment_status || 'pending';
                document.getElementById('license_renewal_status').value = renewal.status || 'active';
                
                document.getElementById('licenseRenewalModal').classList.add('active');
            }
        });
}

function closeLicenseRenewalModal() {
    document.getElementById('licenseRenewalModal').classList.remove('active');
}

async function saveLicenseRenewal(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/license-renewals.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeLicenseRenewalModal();
            licenseRenewalsTable.refresh();
        } else {
            alert(result.message || 'Error saving license renewal');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving license renewal. Please try again.');
    }
}

async function deleteLicenseRenewal(id) {
    if (!confirm('Are you sure you want to delete this license renewal?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/license-renewals.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            licenseRenewalsTable.refresh();
        } else {
            alert(result.message || 'Error deleting license renewal');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting license renewal. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>






