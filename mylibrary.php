<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';

$page_title = "My Library - Wezo Campus Hub";

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();

// Get filter parameters
$category = sanitizeInput($_GET['category'] ?? '');
$education_level = sanitizeInput($_GET['level'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'recent');
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Prepare filters
$filters = ['user_id' => $user['id']];
if (!empty($category) && $category !== 'all') {
    $filters['category'] = $category;
}
if (!empty($education_level) && $education_level !== 'all') {
    $filters['education_level'] = $education_level;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

// Get sort order
$sortOrder = 'upload_date DESC';
switch ($sort) {
    case 'popular':
        $sortOrder = 'download_count DESC, average_rating DESC';
        break;
    case 'rating':
        $sortOrder = 'average_rating DESC, download_count DESC';
        break;
    case 'views':
        $sortOrder = 'view_count DESC, download_count DESC';
        break;
    case 'title':
        $sortOrder = 'title ASC';
        break;
    case 'recent':
    default:
        $sortOrder = 'upload_date DESC';
        break;
}

// Get user's documents
$documents = [];
$totalDocuments = 0;

$whereConditions = ['user_id = ?'];
$params = [$user['id']];
$types = 'i';

if (!empty($category) && $category !== 'all') {
    $whereConditions[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($education_level) && $education_level !== 'all') {
    $whereConditions[] = "education_level = ?";
    $params[] = $education_level;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM documents $whereClause";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalData = $countResult->fetch_assoc();
$totalDocuments = $totalData['total'];

// Get documents
$docSql = "SELECT * FROM documents $whereClause ORDER BY $sortOrder LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$docStmt = $conn->prepare($docSql);
$docStmt->bind_param($types, ...$params);
$docStmt->execute();
$result = $docStmt->get_result();

while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

// Get unique categories and education levels for filters
$categoriesResult = $conn->query("SELECT DISTINCT category FROM documents WHERE user_id = {$user['id']} AND category IS NOT NULL AND category != '' ORDER BY category");
$educationLevelsResult = $conn->query("SELECT DISTINCT education_level FROM documents WHERE user_id = {$user['id']} ORDER BY education_level");

// Calculate pagination
$totalPages = ceil($totalDocuments / $limit);

// Get user statistics
$statsResult = $conn->query("
    SELECT 
        COUNT(*) as total_documents,
        SUM(download_count) as total_downloads,
        SUM(view_count) as total_views,
        AVG(average_rating) as avg_rating,
        SUM(CASE WHEN DATE(upload_date) = CURDATE() THEN 1 ELSE 0 END) as today_uploads
    FROM documents 
    WHERE user_id = {$user['id']}
");
$stats = $statsResult->fetch_assoc();

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb -->
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Library</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2"><i class="fas fa-book me-2"></i>My Library</h2>
                            <p class="text-muted mb-0">Manage and organize your uploaded documents</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="upload.php" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i>Upload New Document
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Your Document Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $stats['total_documents']; ?></h3>
                                <p class="mb-0">Total Documents</p>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $stats['today_uploads']; ?></h3>
                                <p class="mb-0">Today's Uploads</p>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-info"><?php echo $stats['total_downloads']; ?></h3>
                                <p class="mb-0">Total Downloads</p>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning"><?php echo $stats['total_views']; ?></h3>
                                <p class="mb-0">Total Views</p>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-danger"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></h3>
                                <p class="mb-0">Avg. Rating</p>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-dark"><?php echo number_format($totalDocuments); ?></h3>
                                <p class="mb-0">Showing</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Documents</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search in your documents...">
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="category">
                                <option value="all">All Categories</option>
                                <?php while ($cat = $categoriesResult->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                    <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="level">
                                <option value="all">All Education Levels</option>
                                <?php while ($level = $educationLevelsResult->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($level['education_level']); ?>"
                                    <?php echo ($education_level === $level['education_level']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($level['education_level']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </form>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <select class="form-select form-select-sm w-auto" name="sort" onchange="this.form.submit()">
                                        <option value="recent" <?php echo ($sort === 'recent') ? 'selected' : ''; ?>>Sort by: Most Recent</option>
                                        <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Sort by: Most Popular</option>
                                        <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Sort by: Highest Rated</option>
                                        <option value="views" <?php echo ($sort === 'views') ? 'selected' : ''; ?>>Sort by: Most Viewed</option>
                                        <option value="title" <?php echo ($sort === 'title') ? 'selected' : ''; ?>>Sort by: Title A-Z</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <a href="mylibrary.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-redo me-1"></i>Reset Filters
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Documents Grid -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Documents</h5>
                        <div>
                            <span class="badge bg-primary">
                                <?php echo number_format($totalDocuments); ?> document<?php echo ($totalDocuments !== 1) ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-upload fa-4x text-muted mb-3"></i>
                        <h4>No documents found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search) || !empty($category) || !empty($education_level)): ?>
                                No documents match your search criteria. Try different filters.
                            <?php else: ?>
                                You haven't uploaded any documents yet.
                            <?php endif; ?>
                        </p>
                        <a href="upload.php" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Your First Document
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($documents as $doc): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="card document-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-<?php echo getLevelColor($doc['education_level']); ?>">
                                                <?php echo $doc['education_level']; ?>
                                            </span>
                                            <?php if ($doc['category']): ?>
                                            <span class="badge bg-secondary"><?php echo $doc['category']; ?></span>
                                            <?php endif; ?>
                                            <?php if (!$doc['is_approved']): ?>
                                            <span class="badge bg-warning">Pending Review</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo formatFileSize($doc['file_size']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <a href="document.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars(substr($doc['description'] ?? 'No description available', 0, 100)); ?>...
                                    </p>
                                    
                                    <div class="mb-3">
                                        <?php if ($doc['average_rating'] > 0): ?>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= round($doc['average_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                            <small class="ms-2"><?php echo number_format($doc['average_rating'], 1); ?> (<?php echo $doc['total_ratings']; ?>)</small>
                                        </div>
                                        <?php else: ?>
                                        <small class="text-muted">No ratings yet</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted me-2">
                                                <i class="fas fa-download me-1"></i>
                                                <?php echo $doc['download_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-eye me-1"></i>
                                                <?php echo $doc['view_count']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($doc['tags']): ?>
                                    <div class="mt-3">
                                        <?php 
                                        $tags = explode(',', $doc['tags']);
                                        foreach (array_slice($tags, 0, 3) as $tag):
                                            $tag = trim($tag);
                                            if (!empty($tag)):
                                        ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent border-top">
                                    <div class="d-flex justify-content-between">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-success" 
                                               title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="edit-document.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-warning" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['title']); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <?php if ($doc['report_count'] > 0): ?>
                                        <span class="badge bg-danger" title="Reported <?php echo $doc['report_count']; ?> time(s)">
                                            <i class="fas fa-flag"></i> <?php echo $doc['report_count']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildPaginationUrl($page - 1); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . buildPaginationUrl(1) . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildPaginationUrl($i); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . buildPaginationUrl($totalPages) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildPaginationUrl($page + 1); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Bulk Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-download me-1"></i>Export Library
                            </button>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="upload.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-upload me-1"></i>Upload Multiple
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <button type="button" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#organizeModal">
                                <i class="fas fa-folder me-1"></i>Organize
                            </button>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <button type="button" class="btn btn-outline-info w-100" data-bs-toggle="modal" data-bs-target="#statisticsModal">
                                <i class="fas fa-chart-pie me-1"></i>View Statistics
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Library</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="export-library.php">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportType" class="form-label">Export Type</label>
                        <select class="form-select" id="exportType" name="type">
                            <option value="all">All Documents</option>
                            <option value="filtered">Currently Filtered Documents</option>
                            <option value="selected">Selected Documents Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Include</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include[]" value="details" checked>
                            <label class="form-check-label">Document Details</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include[]" value="statistics" checked>
                            <label class="form-check-label">Statistics</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include[]" value="ratings" checked>
                            <label class="form-check-label">Ratings & Reviews</label>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statisticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Library Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>By Education Level</h6>
                        <canvas id="educationLevelChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>By Category</h6>
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
                <hr>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6>Monthly Uploads</h6>
                        <canvas id="monthlyUploadsChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteDocument(id, title) {
    if (confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
        fetch('delete-document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting document. Please try again.');
        });
    }
}

function buildPaginationUrl(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    return params.toString();
}

// Load statistics charts when modal opens
document.getElementById('statisticsModal').addEventListener('show.bs.modal', function() {
    // Fetch statistics data and render charts
    fetch('library-statistics.php')
        .then(response => response.json())
        .then(data => {
            renderCharts(data);
        });
});

function renderCharts(data) {
    // Education Level Chart
    const educationCtx = document.getElementById('educationLevelChart').getContext('2d');
    new Chart(educationCtx, {
        type: 'doughnut',
        data: {
            labels: data.education_levels.labels,
            datasets: [{
                data: data.education_levels.data,
                backgroundColor: ['#007bff', '#28a745', '#ffc107', '#17a2b8', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: data.categories.labels,
            datasets: [{
                label: 'Documents',
                data: data.categories.data,
                backgroundColor: '#28a745'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Monthly Uploads Chart
    const monthlyCtx = document.getElementById('monthlyUploadsChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: data.monthly_uploads.labels,
            datasets: [{
                label: 'Uploads',
                data: data.monthly_uploads.data,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
</script>

<?php
// Helper functions
function getLevelColor($level) {
    $colors = [
        'JSS' => 'primary',
        'CBC' => 'success',
        'University' => 'warning',
        'College' => 'info',
        'General' => 'secondary'
    ];
    return $colors[$level] ?? 'secondary';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return http_build_query($params);
}

include 'includes/footer.php';
?>