<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Search Documents - Wezo Campus Hub";

// Get search parameters - fixed sanitization function name
$query = sanitize_input($_GET['q'] ?? '');
$education_level = sanitize_input($_GET['level'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$sort = sanitize_input($_GET['sort'] ?? 'recent');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Prepare filters
$filters = [];
if (!empty($education_level) && $education_level !== 'all') {
    $filters['education_level'] = $education_level;
}
if (!empty($category) && $category !== 'all') {
    $filters['category'] = $category;
}

// Get sort order
$sortOrder = 'd.upload_date DESC';
switch ($sort) {
    case 'popular':
        $sortOrder = 'd.download_count DESC, d.average_rating DESC';
        break;
    case 'rating':
        $sortOrder = 'd.average_rating DESC, d.download_count DESC';
        break;
    case 'views':
        $sortOrder = 'd.view_count DESC, d.download_count DESC';
        break;
    case 'recent':
    default:
        $sortOrder = 'd.upload_date DESC';
        break;
}

// Perform search
$conn = getDBConnection();

// GET EDUCATION LEVELS FOR FILTER DROPDOWN
$educationLevelsResult = $conn->query("
    SELECT DISTINCT education_level 
    FROM documents 
    WHERE education_level IS NOT NULL AND education_level != ''
    ORDER BY education_level
");

// GET CATEGORIES FOR FILTER DROPDOWN
$categoriesResult = $conn->query("
    SELECT DISTINCT category 
    FROM documents 
    WHERE category IS NOT NULL AND category != ''
    ORDER BY category
");

$documents = [];
$totalResults = 0;

if (!empty($query) || !empty($filters)) {
    $whereConditions = ['d.is_approved = 1'];
    $params = [];
    $types = '';
    
    // Search query
    if (!empty($query)) {
        $whereConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ? OR u.username LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
    
    // Filters
    if (!empty($filters['education_level'])) {
        $whereConditions[] = "d.education_level = ?";
        $params[] = $filters['education_level'];
        $types .= 's';
    }
    
    if (!empty($filters['category'])) {
        $whereConditions[] = "d.category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM documents d JOIN users u ON d.user_id = u.id $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($params) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalData = $countResult->fetch_assoc();
    $totalResults = $totalData['total'];
    
    // Get documents
    $docSql = "
        SELECT d.*, u.username 
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        $whereClause 
        ORDER BY $sortOrder 
        LIMIT ? OFFSET ?
    ";
    
    $limitParam = $limit;
    $offsetParam = $offset;
    $params[] = &$limitParam;
    $params[] = &$offsetParam;
    $types .= 'ii';
    
    $docStmt = $conn->prepare($docSql);
    $docStmt->bind_param($types, ...$params);
    $docStmt->execute();
    $result = $docStmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
} else {
    // Get all documents if no search
    $sql = "
        SELECT d.*, u.username 
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.is_approved = 1 
        ORDER BY $sortOrder 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    // Get total count
    $countResult = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_approved = 1");
    $totalData = $countResult->fetch_assoc();
    $totalResults = $totalData['total'];
}

// Reset result pointers for filter dropdowns
if ($educationLevelsResult && $educationLevelsResult->num_rows > 0) {
    $educationLevelsResult->data_seek(0);
}
if ($categoriesResult && $categoriesResult->num_rows > 0) {
    $categoriesResult->data_seek(0);
}

// Calculate pagination
$totalPages = ceil($totalResults / $limit);

// Helper function for input sanitization
function sanitize_input($data) {
    if (empty($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Search Documents</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Search Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-3"><i class="fas fa-search me-2"></i>Search Educational Documents</h2>
                    
                    <form method="GET" action="" class="search-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" name="q" 
                                           value="<?php echo htmlspecialchars($query); ?>" 
                                           placeholder="Search documents, subjects, topics...">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-select" name="level">
                                    <option value="all">All Education Levels</option>
                                    <?php 
                                    // Reset pointer for education levels
                                    if ($educationLevelsResult && $educationLevelsResult->num_rows > 0) {
                                        $educationLevelsResult->data_seek(0);
                                        while ($level = $educationLevelsResult->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($level['education_level']); ?>"
                                        <?php echo ($education_level === $level['education_level']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['education_level']); ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="all">All Categories</option>
                                    <?php 
                                    // Reset pointer for categories
                                    if ($categoriesResult && $categoriesResult->num_rows > 0) {
                                        $categoriesResult->data_seek(0);
                                        while ($cat = $categoriesResult->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                        <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mt-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <select class="form-select form-select-sm w-auto" name="sort">
                                            <option value="recent" <?php echo ($sort === 'recent') ? 'selected' : ''; ?>>Sort by: Most Recent</option>
                                            <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Sort by: Most Popular</option>
                                            <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Sort by: Highest Rated</option>
                                            <option value="views" <?php echo ($sort === 'views') ? 'selected' : ''; ?>>Sort by: Most Viewed</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Search
                                        </button>
                                        <a href="search.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo me-1"></i>Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Results -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>
                            <?php if (!empty($query) || !empty($education_level) || !empty($category)): ?>
                                Search Results
                            <?php else: ?>
                                Browse Documents
                            <?php endif; ?>
                        </h5>
                        <div>
                            <span class="badge bg-primary">
                                <?php echo number_format($totalResults); ?> document<?php echo ($totalResults !== 1) ? 's' : ''; ?> found
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-search fa-4x text-muted mb-3"></i>
                        <h4>No documents found</h4>
                        <p class="text-muted">
                            <?php if (!empty($query)): ?>
                                No documents match your search criteria. Try different keywords or filters.
                            <?php else: ?>
                                No documents available yet. Be the first to upload!
                            <?php endif; ?>
                        </p>
                        <?php if ($auth->isLoggedIn()): ?>
                        <a href="upload.php" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </a>
                        <?php endif; ?>
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
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($doc['username']); ?>
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
                                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-info-circle me-1"></i>Details
                                        </a>
                                        <?php if ($auth->isLoggedIn()): ?>
                                        <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                        <?php else: ?>
                                        <a href="login.php" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download me-1"></i>Login to Download
                                        </a>
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
    
    <!-- Search Tips -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Search Tips</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-filter text-info fa-2x me-3"></i>
                                <div>
                                    <h6>Use Filters</h6>
                                    <p class="small">Filter by education level and category for precise results</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-key text-success fa-2x me-3"></i>
                                <div>
                                    <h6>Specific Keywords</h6>
                                    <p class="small">Use specific subject names or document types</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-robot text-warning fa-2x me-3"></i>
                                <div>
                                    <h6>Chatbot Assistance</h6>
                                    <p class="small">Use our chatbot for personalized document recommendations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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