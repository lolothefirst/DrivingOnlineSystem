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
    $query = "DELETE FROM learning_materials WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Learning material deleted successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Delete learning material', 'learning_materials', $id);
    }
    redirect('learning-materials.php');
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $query = "UPDATE learning_materials SET is_active = NOT is_active WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Material status updated!";
        logActivity($conn, $_SESSION['user_id'], 'Toggle material status', 'learning_materials', $id);
    }
    redirect('learning-materials.php');
}

// Get all learning materials
$query = "SELECT * FROM learning_materials ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$materials = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Learning Materials Management</h1>
            <a href="learning-material-create.php" class="btn btn-primary">Add New Material</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-body" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <input type="text" id="am_search" class="form-control" placeholder="Search title/category">
                <select id="am_type" class="form-control" style="max-width: 160px;">
                    <option value="all">All types</option>
                    <option value="pdf">PDF</option>
                    <option value="text">Text</option>
                </select>
                <select id="am_sort" class="form-control" style="max-width: 200px;">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="title_az">Title A → Z</option>
                    <option value="title_za">Title Z → A</option>
                </select>
                <select id="am_page_size" class="form-control" style="max-width: 140px;">
                    <option value="10">10 per page</option>
                    <option value="25" selected>25 per page</option>
                    <option value="50">50 per page</option>
                </select>
            </div>
        </div>

        <?php if (count($materials) > 0): ?>
            <div class="card">
                <table class="table" id="materialsTable">
                    <thead>
                        <tr>
                            <th data-sort="title">Title <span class="sort-ind"></span></th>
                            <th data-sort="type">Type <span class="sort-ind"></span></th>
                            <th data-sort="category">Category <span class="sort-ind"></span></th>
                            <th data-sort="status">Status <span class="sort-ind"></span></th>
                            <th data-sort="created">Uploaded <span class="sort-ind"></span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $material): ?>
                            <tr
                                data-title="<?php echo htmlspecialchars(mb_strtolower($material['title'])); ?>"
                                data-type="<?php echo htmlspecialchars($material['material_type']); ?>"
                                data-category="<?php echo htmlspecialchars(mb_strtolower($material['category'])); ?>"
                                data-status="<?php echo $material['is_active'] ? 'active' : 'inactive'; ?>"
                                data-created="<?php echo strtotime($material['created_at']); ?>"
                            >
                                <td><?php echo htmlspecialchars($material['title']); ?></td>
                                <td><span class="badge"><?php echo ucfirst($material['material_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($material['category']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $material['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $material['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($material['created_at'], 'M d, Y h:i A'); ?></td>
                                <td>
                                    <a href="learning-material-edit.php?id=<?php echo $material['id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="?toggle=<?php echo $material['id']; ?>" class="btn btn-sm btn-warning">Toggle</a>
                                    <a href="?delete=<?php echo $material['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top:1rem;">
                <div class="card-body" id="am_pagination" style="display:flex;gap:0.5rem;justify-content:center;align-items:center;flex-wrap:wrap;">
                    <button class="btn" data-page="prev">Previous</button>
                    <span id="am_page_info"></span>
                    <button class="btn" data-page="next">Next</button>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No learning materials found. <a href="learning-material-create.php">Add your first material</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function() {
    const table = document.getElementById('materialsTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const head = table.querySelector('thead');
    const searchInput = document.getElementById('am_search');
    const typeSelect = document.getElementById('am_type');
    const sortSelect = document.getElementById('am_sort');
    const pageSizeSelect = document.getElementById('am_page_size');
    const pagination = document.getElementById('am_pagination');
    const pageInfo = document.getElementById('am_page_info');

    let currentPage = 1;
    let sortKey = 'newest'; // created desc by default
    let sortDir = 'desc';

    function apply() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const type = typeSelect.value;
        const sort = sortKey || sortSelect.value;
        const pageSize = parseInt(pageSizeSelect.value, 10) || 25;

        // Filter
        let list = rows.filter(r => {
            const matchesType = (type === 'all') || (r.dataset.type === type);
            if (!matchesType) return false;
            if (!q) return true;
            const hay = (r.dataset.title + ' ' + r.dataset.category).toLowerCase();
            return hay.includes(q);
        });

        // Sort
        const cmp = (a, b, key) => {
            if (key === 'created') return parseInt(a.dataset.created, 10) - parseInt(b.dataset.created, 10);
            if (key === 'title') return a.dataset.title.localeCompare(b.dataset.title);
            if (key === 'type') return a.dataset.type.localeCompare(b.dataset.type);
            if (key === 'category') return a.dataset.category.localeCompare(b.dataset.category);
            if (key === 'status') return a.dataset.status.localeCompare(b.dataset.status);
            return 0;
        };

        if (sort === 'newest' || sort === 'oldest') {
            list.sort((a, b) => cmp(a, b, 'created'));
            if (sort === 'newest') list.reverse();
        } else if (sort === 'title_az' || sort === 'title_za') {
            list.sort((a, b) => cmp(a, b, 'title'));
            if (sort === 'title_za') list.reverse();
        } else if (['created','title','type','category','status'].includes(sort)) {
            list.sort((a, b) => cmp(a, b, sort));
            if (sortDir === 'desc') list.reverse();
        }

        // Pagination
        const total = list.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        // Hide all, then show page slice
        rows.forEach(r => r.style.display = 'none');
        list.slice(start, end).forEach(r => r.style.display = '');

        // Pagination UI
        pagination.style.display = total > pageSize ? 'flex' : 'none';
        if (pagination.style.display !== 'none') {
            pageInfo.textContent = `Page ${currentPage} of ${totalPages} (Total ${total})`;
            const [prevBtn, , nextBtn] = pagination.querySelectorAll('button');
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }
    }

    [searchInput].forEach(el => {
        el && el.addEventListener('input', () => { currentPage = 1; apply(); });
    });
    [typeSelect, sortSelect, pageSizeSelect].forEach(el => {
        el && el.addEventListener('change', () => { currentPage = 1; apply(); });
    });

    if (pagination) {
        pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const pageAction = btn.getAttribute('data-page');
            if (pageAction === 'prev') currentPage = Math.max(1, currentPage - 1);
            if (pageAction === 'next') currentPage = currentPage + 1;
            apply();
        });
    }

    // Clickable header sorting
    if (head) {
        head.addEventListener('click', (e) => {
            const th = e.target.closest('th[data-sort]');
            if (!th) return;
            const key = th.getAttribute('data-sort');
            if (sortKey === key) {
                sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
            } else {
                sortKey = key;
                sortDir = 'asc';
            }
            // Visual indicator
            head.querySelectorAll('th[data-sort] .sort-ind').forEach(span => span.textContent = '');
            const span = th.querySelector('.sort-ind');
            if (span) span.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
            currentPage = 1;
            apply();
        });
    }

    apply();
})();
</script>
