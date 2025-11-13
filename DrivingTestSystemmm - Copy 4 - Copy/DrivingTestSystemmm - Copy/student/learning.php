<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get all active learning materials
$query = "SELECT * FROM learning_materials WHERE is_active = 1 ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$materials = $stmt->fetchAll();

// Group by type for display sections (video removed)
$byType = [
    'pdf' => [],
    'text' => []
];
foreach ($materials as $m) {
    $type = in_array($m['material_type'], ['pdf','text'], true) ? $m['material_type'] : 'text';
    $byType[$type][] = $m;
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Learning Materials</h1>
        <p class="page-subtitle">Study traffic rules, signs, and driving knowledge</p>
    </div>
    
    <?php
    $sections = [
        'pdf' => 'PDF Documents',
        'text' => 'Text Content'
    ];
    $hasAny = count($materials) > 0;
    ?>

    <!-- Controls -->
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-body" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="text" id="lm_search" class="form-control" placeholder="Search by title or description" style="max-width: 280px;">
            <select id="lm_type" class="form-control" style="max-width: 180px;">
                <option value="all">All types</option>
                <option value="pdf">PDF</option>
                <option value="text">Text</option>
            </select>
            <select id="lm_sort" class="form-control" style="max-width: 200px;">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="title_az">Title A → Z</option>
                <option value="title_za">Title Z → A</option>
            </select>
            <select id="lm_page_size" class="form-control" style="max-width: 140px;">
                <option value="6">6 per page</option>
                <option value="12" selected>12 per page</option>
                <option value="24">24 per page</option>
            </select>
        </div>
    </div>

    <?php if ($hasAny): ?>
        <?php foreach ($sections as $typeKey => $label): ?>
            <?php if (!empty($byType[$typeKey])): ?>
                <div class="card lm-section" data-section-type="<?php echo $typeKey; ?>">
                    <div class="card-header"><?php echo $label; ?></div>
                    <div class="materials-grid">
                        <?php foreach ($byType[$typeKey] as $material): ?>
                            <div class="material-card"
                                 data-type="<?php echo $typeKey; ?>"
                                 data-title="<?php echo htmlspecialchars(mb_strtolower($material['title'])); ?>"
                                 data-desc="<?php echo htmlspecialchars(mb_strtolower($material['description'] ?? '')); ?>"
                                 data-date="<?php echo strtotime($material['created_at']); ?>">
                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                <div class="material-preview">
                                    <?php if ($typeKey === 'pdf'): ?>
                                        <?php if (!empty($material['file_path'])): ?>
                                            <iframe
                                                class="preview-frame"
                                                src="../<?php echo htmlspecialchars($material['file_path']); ?>#page=1&zoom=80"
                                                title="PDF preview"
                                                loading="lazy"
                                            ></iframe>
                                        <?php else: ?>
                                            <div class="preview-fallback">No PDF file</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="preview-text">
                                            <?php
                                                $summary = trim(strip_tags($material['content']));
                                                if (mb_strlen($summary) > 160) {
                                                    $summary = mb_substr($summary, 0, 160) . '...';
                                                }
                                                echo htmlspecialchars($summary ?: ($material['description'] ?? ''));
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo htmlspecialchars($material['description']); ?></p>
                                <small style="color: var(--text-secondary);">Uploaded: <?php echo formatDate($material['created_at'], 'M d, Y h:i A'); ?></small>
                                <a href="learning-view.php?id=<?php echo $material['id']; ?>" class="btn btn-primary btn-sm">
                                    View Material
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div id="lm_pagination" class="card" style="margin-top: 1rem; display: none;">
            <div class="card-body" style="display:flex; gap:0.5rem; flex-wrap: wrap; align-items:center; justify-content:center;">
                <button class="btn" data-page="prev">Previous</button>
                <span id="lm_page_info"></span>
                <button class="btn" data-page="next">Next</button>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <p>No learning materials available at this time.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function() {
    const cards = Array.from(document.querySelectorAll('.material-card'));
    if (cards.length === 0) return;

    const searchInput = document.getElementById('lm_search');
    const typeSelect = document.getElementById('lm_type');
    const sortSelect = document.getElementById('lm_sort');
    const pageSizeSelect = document.getElementById('lm_page_size');
    const pagination = document.getElementById('lm_pagination');
    const pageInfo = document.getElementById('lm_page_info');

    let currentPage = 1;

    function apply() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const type = typeSelect.value;
        const sort = sortSelect.value;
        const pageSize = parseInt(pageSizeSelect.value, 10) || 12;

        // Filter
        let list = cards.filter(card => {
            const matchesType = (type === 'all') || (card.dataset.type === type);
            if (!matchesType) return false;
            if (!q) return true;
            const hay = (card.dataset.title + ' ' + card.dataset.desc).toLowerCase();
            return hay.includes(q);
        });

        // Sort
        if (sort === 'newest' || sort === 'oldest') {
            list.sort((a, b) => parseInt(a.dataset.date, 10) - parseInt(b.dataset.date, 10));
            if (sort === 'newest') list.reverse();
        } else if (sort === 'title_az' || sort === 'title_za') {
            list.sort((a, b) => a.dataset.title.localeCompare(b.dataset.title));
            if (sort === 'title_za') list.reverse();
        }

        // Pagination
        const total = list.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        // Hide all
        cards.forEach(c => c.style.display = 'none');
        // Show page slice
        list.slice(start, end).forEach(c => c.style.display = '');

        // Hide empty sections
        document.querySelectorAll('.lm-section').forEach(section => {
            const visible = section.querySelectorAll('.material-card:not([style*="display: none"])').length;
            section.style.display = visible ? '' : 'none';
        });

        // Pagination UI
        pagination.style.display = total > pageSize ? 'block' : 'none';
        if (pagination.style.display === 'block') {
            pageInfo.textContent = `Page ${currentPage} of ${totalPages} (Total ${total})`;
            const [prevBtn, , nextBtn] = pagination.querySelectorAll('button');
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }
    }

    // Events
    searchInput && searchInput.addEventListener('input', () => { currentPage = 1; apply(); });
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

    apply();
})();
</script>
