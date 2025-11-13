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
        <h1 class="page-title">Student Management</h1>
        <button class="btn btn-primary" onclick="openStudentModal()">+ Add Student</button>
    </div>
    
    <div id="students-table"></div>
</div>

<!-- Student Edit Modal -->
<div id="studentModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="studentModalTitle">Add Student</h2>
            <button class="admin-modal-close" onclick="closeStudentModal()">&times;</button>
        </div>
        <form id="studentForm" onsubmit="saveStudent(event)">
            <input type="hidden" id="student_id" name="id">
            
            <div class="admin-form-group">
                <label>Full Name *</label>
                <input type="text" id="student_full_name" name="full_name" required>
            </div>
            
            <div class="admin-form-group">
                <label>Email *</label>
                <input type="email" id="student_email" name="email" required>
            </div>
            
            <div class="admin-form-group">
                <label>ID Number *</label>
                <input type="text" id="student_id_number" name="id_number" required>
            </div>
            
            <div class="admin-form-group">
                <label>Phone</label>
                <input type="text" id="student_phone" name="phone">
            </div>
            
            <div class="admin-form-group">
                <label>Password</label>
                <input type="password" id="student_password" name="password" placeholder="Leave blank to keep current">
                <small>Only required for new students</small>
            </div>
            
            <div class="admin-form-group">
                <label>Status *</label>
                <select id="student_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            
            <div class="admin-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/admin-table.js"></script>
<script>
let studentsTable;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing students table...');
    console.log('Container exists:', document.getElementById('students-table') !== null);
    
    studentsTable = createAdminTable({
        tableId: 'students-table',
        apiUrl: 'api/students.php',
        pageSize: 1,
        defaultSortColumn: 'created_at',
        defaultSortDirection: 'desc',
        columns: [
            { field: 'id', label: 'ID', sortable: true },
            { field: 'full_name', label: 'Name', sortable: true },
            { field: 'email', label: 'Email', sortable: true },
            { field: 'id_number', label: 'ID Number', sortable: true },
            { field: 'phone', label: 'Phone', sortable: false },
            { 
                field: 'status', 
                label: 'Status', 
                sortable: true,
                type: 'badge',
                render: function(value) {
                    const colors = {
                        'active': 'success',
                        'inactive': 'warning',
                        'suspended': 'danger'
                    };
                    return `<span class="badge badge-${colors[value] || 'default'}">${value}</span>`;
                }
            },
            { field: 'created_at', label: 'Registered', sortable: true, type: 'date' }
        ],
        filters: {
            status: {
                type: 'select',
                label: 'Status',
                options: [
                    { value: 'active', label: 'Active' },
                    { value: 'inactive', label: 'Inactive' },
                    { value: 'suspended', label: 'Suspended' }
                ]
            }
        },
        onEdit: function(id) {
            editStudent(id);
        },
        onDelete: function(id) {
            deleteStudent(id);
        }
    });
    
    console.log('Students table initialized:', studentsTable);
});

function openStudentModal(id = null) {
    const modal = document.getElementById('studentModal');
    const title = document.getElementById('studentModalTitle');
    const form = document.getElementById('studentForm');
    
    if (id) {
        title.textContent = 'Edit Student';
        loadStudentData(id);
    } else {
        title.textContent = 'Add Student';
        form.reset();
        document.getElementById('student_id').value = '';
    }
    
    modal.classList.add('active');
}

function closeStudentModal() {
    document.getElementById('studentModal').classList.remove('active');
}

function loadStudentData(id) {
    fetch(`api/students.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.data;
                document.getElementById('student_id').value = student.id;
                document.getElementById('student_full_name').value = student.full_name;
                document.getElementById('student_email').value = student.email;
                document.getElementById('student_id_number').value = student.id_number;
                document.getElementById('student_phone').value = student.phone || '';
                document.getElementById('student_status').value = student.status;
            }
        });
}

function editStudent(id) {
    openStudentModal(id);
}

async function saveStudent(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    const id = data.id;
    
    const method = id ? 'PUT' : 'POST';
    const url = 'api/students.php';
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeStudentModal();
            studentsTable.refresh();
        } else {
            alert(result.message || 'Error saving student');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving student. Please try again.');
    }
}

async function deleteStudent(id) {
    if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/students.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            studentsTable.refresh();
        } else {
            alert(result.message || 'Error deleting student');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting student. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
