<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';
require_once 'classes/Database.php';

$page_title = "Document Details - Wezo Campus Hub";

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: search.php");
    exit();
}

$documentId = intval($_GET['id']);
$document = new Document($documentId);

// Check if document exists
if (!$document->getId()) {
    $_SESSION['message'] = 'Document not found.';
    $_SESSION['message_type'] = 'danger';
    header("Location: search.php");
    exit();
}

// Check if document is approved (unless admin or owner)
$user = $auth->getCurrentUser();
$userId = $user ? $user['id'] : null;
$isOwner = $user && $document->getData('user_id') == $userId;
$isAdmin = $user && $auth->isAdmin();

if (!$document->getData('is_approved') && !$isOwner && !$isAdmin) {
    $_SESSION['message'] = 'This document is under review and not available for viewing.';
    $_SESSION['message_type'] = 'warning';
    header("Location: search.php");
    exit();
}

// Check if user wants to view/read the document
$viewMode = isset($_GET['view']) && $_GET['view'] === 'read';

// Track document reading session
if ($auth->isLoggedIn() && $viewMode) {
    $_SESSION['reading_document'] = $documentId;
    $_SESSION['reading_start_time'] = time();
}

// Increment view count (if user is logged in)
if ($auth->isLoggedIn()) {
    $document->incrementViewCount($userId);
}

// Get document data
$docData = $document->getData();

// Get database connection
$conn = getDBConnection();

// Get uploader information with university details
$uploaderQuery = $conn->prepare("
    SELECT 
        u.id, u.username, u.email, u.profile_photo, u.created_at,
        u.university_id, u.university_text, u.course_id, u.course_text,
        uni.name as university_name, uni.short_name as university_short_name,
        uni.country as university_country, uni.city as university_city,
        c.name as course_name, c.code as course_code,
        up.bio, up.phone_number, up.gender
    FROM users u
    LEFT JOIN universities uni ON u.university_id = uni.id
    LEFT JOIN courses c ON u.course_id = c.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
$uploaderQuery->bind_param("i", $docData['user_id']);
$uploaderQuery->execute();
$uploaderResult = $uploaderQuery->get_result();
$uploader = $uploaderResult->fetch_assoc();

// Get uploader statistics
$uploaderStatsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_documents,
        SUM(download_count) as total_downloads,
        AVG(average_rating) as avg_rating,
        SUM(view_count) as total_views
    FROM documents 
    WHERE user_id = ? AND is_approved = 1
");
$uploaderStatsQuery->bind_param("i", $docData['user_id']);
$uploaderStatsQuery->execute();
$uploaderStatsResult = $uploaderStatsQuery->get_result();
$uploaderStats = $uploaderStatsResult->fetch_assoc();

// Get ratings and feedback
$ratings = $document->getRatings(5);
$feedbacks = $document->getFeedback(5);

// Get similar documents
$similarDocs = $document->getSimilarDocuments(4);

// Get document versions
$versions = $document->getVersions();

// Check if user has rated
$hasRated = false;
$userRating = null;
if ($auth->isLoggedIn()) {
    $checkStmt = $conn->prepare("SELECT * FROM ratings WHERE document_id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $documentId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        $hasRated = true;
        $userRating = $checkResult->fetch_assoc();
    }
}

// Check if user has saved this document
$isSaved = false;
if ($auth->isLoggedIn()) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'saved_documents'");
    if ($tableCheck->num_rows > 0) {
        $saveStmt = $conn->prepare("SELECT id FROM saved_documents WHERE document_id = ? AND user_id = ?");
        if ($saveStmt) {
            $saveStmt->bind_param("ii", $documentId, $userId);
            $saveStmt->execute();
            $saveResult = $saveStmt->get_result();
            $isSaved = $saveResult->num_rows > 0;
        }
    }
}

// Get user download statistics
$userDownloads = 0;
$userUploads = 0;
if ($auth->isLoggedIn()) {
    // Count user's downloads
    $downloadCountQuery = $conn->prepare("
        SELECT COUNT(*) as download_count 
        FROM download_logs 
        WHERE user_id = ? AND DATE(downloaded_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $downloadCountQuery->bind_param("i", $userId);
    $downloadCountQuery->execute();
    $downloadCountResult = $downloadCountQuery->get_result();
    $downloadData = $downloadCountResult->fetch_assoc();
    $userDownloads = $downloadData['download_count'] ?? 0;
    
    // Count user's uploads
    $uploadCountQuery = $conn->prepare("
        SELECT COUNT(*) as upload_count 
        FROM documents 
        WHERE user_id = ? AND is_approved = 1
    ");
    $uploadCountQuery->bind_param("i", $userId);
    $uploadCountQuery->execute();
    $uploadCountResult = $uploadCountQuery->get_result();
    $uploadData = $uploadCountResult->fetch_assoc();
    $userUploads = $uploadData['upload_count'] ?? 0;
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->isLoggedIn()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'rate' && isset($_POST['rating'])) {
        $rating = intval($_POST['rating']);
        $review = sanitizeInput($_POST['review'] ?? '');
        
        if ($rating >= 1 && $rating <= 5) {
            $document->addRating($userId, $rating, $review);
            $hasRated = true;
            $userRating = ['rating' => $rating, 'review' => $review];
            
            // Refresh ratings
            $ratings = $document->getRatings(5);
            
            // Clear reading session if rating was submitted
            if (isset($_SESSION['reading_document']) && $_SESSION['reading_document'] == $documentId) {
                unset($_SESSION['reading_document'], $_SESSION['reading_start_time']);
            }
        }
    } elseif ($action === 'feedback' && isset($_POST['comment'])) {
        $comment = sanitizeInput($_POST['comment']);
        if (!empty($comment)) {
            $document->addFeedback($userId, $comment);
            
            // Refresh feedback
            $feedbacks = $document->getFeedback(5);
        }
    } elseif ($action === 'report') {
        $reportType = sanitizeInput($_POST['report_type'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        if (!empty($reportType)) {
            $document->report($userId, $reportType, $description);
            $_SESSION['message'] = 'Thank you for reporting. Our team will review this document.';
            $_SESSION['message_type'] = 'success';
        }
    } elseif ($action === 'skip_rating') {
        // User chose to skip rating
        if (isset($_SESSION['reading_document']) && $_SESSION['reading_document'] == $documentId) {
            unset($_SESSION['reading_document'], $_SESSION['reading_start_time']);
        }
    } elseif ($action === 'save_document') {
        // Save document for later - create table if it doesn't exist
        $createTable = $conn->query("
            CREATE TABLE IF NOT EXISTS saved_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                document_id INT NOT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_document (user_id, document_id)
            )
        ");
        
        if ($createTable) {
            $saveStmt = $conn->prepare("INSERT INTO saved_documents (user_id, document_id) VALUES (?, ?)");
            if ($saveStmt) {
                $saveStmt->bind_param("ii", $userId, $documentId);
                if ($saveStmt->execute()) {
                    $isSaved = true;
                    $_SESSION['message'] = 'Document saved to your library!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Error saving document: ' . $conn->error;
                    $_SESSION['message_type'] = 'danger';
                }
            }
        }
    } elseif ($action === 'unsave_document') {
        // Remove from saved
        $unsaveStmt = $conn->prepare("DELETE FROM saved_documents WHERE user_id = ? AND document_id = ?");
        if ($unsaveStmt) {
            $unsaveStmt->bind_param("ii", $userId, $documentId);
            if ($unsaveStmt->execute()) {
                $isSaved = false;
                $_SESSION['message'] = 'Document removed from saved list.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } elseif ($action === 'like_feedback' && isset($_POST['feedback_id'])) {
        $feedbackId = intval($_POST['feedback_id']);
        // Check if already liked
        $checkLike = $conn->prepare("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?");
        $checkLike->bind_param("ii", $feedbackId, $userId);
        $checkLike->execute();
        $checkLikeResult = $checkLike->get_result();
        
        if ($checkLikeResult->num_rows === 0) {
            // Add like
            $likeStmt = $conn->prepare("INSERT INTO feedback_likes (feedback_id, user_id) VALUES (?, ?)");
            $likeStmt->bind_param("ii", $feedbackId, $userId);
            $likeStmt->execute();
            
            // Update like count in feedbacks table
            $updateStmt = $conn->prepare("UPDATE feedbacks SET likes = likes + 1 WHERE id = ?");
            $updateStmt->bind_param("i", $feedbackId);
            $updateStmt->execute();
        }
    }
}

// Helper function to get profile photo URL
function getProfilePhoto($user_data, $default_path = 'assets/images/default-avatar.png') {
    if (!empty($user_data['profile_photo']) && $user_data['profile_photo'] != 'default.png') {
        if (filter_var($user_data['profile_photo'], FILTER_VALIDATE_URL)) {
            return $user_data['profile_photo'];
        }
        
        if (strpos($user_data['profile_photo'], 'uploads/') === 0) {
            $photo_path = ltrim($user_data['profile_photo'], '/');
            if (file_exists($photo_path)) {
                return SITE_URL . $photo_path;
            }
        }
        
        $test_path = 'uploads/profile_photos/' . $user_data['profile_photo'];
        if (file_exists($test_path)) {
            return SITE_URL . $test_path;
        }
    }
    
    return SITE_URL . $default_path;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb -->
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="search.php">Browse</a></li>
                    <li class="breadcrumb-item active">Document Details</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Show messages if any -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    ?>
    <?php endif; ?>
    
    <?php if ($viewMode): ?>
    <!-- Document Reader Mode -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book-reader me-2"></i>Reading: <?php echo htmlspecialchars($docData['title']); ?>
                    </h5>
                    <div>
                        <a href="document-view.php?id=<?php echo $documentId; ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-times me-1"></i>Exit Reader
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (canDisplayInline($docData['file_type'])): ?>
                    <!-- Document Viewer -->
                    <div class="document-viewer-container" style="min-height: 600px;">
                        <?php echo getDocumentViewer($docData, true); ?>
                    </div>
                    
                    <!-- Reading Progress -->
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Your Reading Progress</small>
                            <small id="readingTime">Time spent: 0:00</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" id="readingProgress" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Reading Controls -->
                    <div class="d-flex justify-content-between mt-4">
                        <div class="btn-group">
                            <button class="btn btn-outline-secondary btn-sm" onclick="zoomOut()">
                                <i class="fas fa-search-minus"></i> Zoom Out
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="zoomIn()">
                                <i class="fas fa-search-plus"></i> Zoom In
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="resetZoom()">
                                <i class="fas fa-sync-alt"></i> Reset
                            </button>
                        </div>
                        
                        <div class="btn-group">
                            <?php if ($auth->isLoggedIn()): ?>
                                <a href="download.php?id=<?php echo $documentId; ?>" class="btn btn-primary btn-sm" onclick="return checkDownloadLimit(<?php echo $userUploads; ?>)">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-download me-1"></i>Login to Download
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="finishReading()">
                                <i class="fas fa-flag-checkered me-1"></i>Finish Reading
                            </button>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Cannot display inline -->
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h4>Preview Not Available</h4>
                        <p class="text-muted">
                            This file type (<?php echo strtoupper($docData['file_type']); ?>) cannot be displayed inline.
                            Please download the file to view it.
                        </p>
                        <?php if ($auth->isLoggedIn()): ?>
                            <a href="download.php?id=<?php echo $documentId; ?>" class="btn btn-primary" onclick="return checkDownloadLimit(<?php echo $userUploads; ?>)">
                                <i class="fas fa-download me-1"></i>Download Document
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i>Login to Download
                            </a>
                        <?php endif; ?>
                        <a href="document-view.php?id=<?php echo $documentId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Details
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Document Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="mb-2"><?php echo htmlspecialchars($docData['title']); ?></h2>
                            <div class="mb-3">
                                <span class="badge bg-<?php echo getLevelColor($docData['education_level']); ?> me-2">
                                    <?php echo $docData['education_level']; ?>
                                </span>
                                <?php if ($docData['category']): ?>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($docData['category']); ?></span>
                                <?php endif; ?>
                                <?php if ($docData['subcategory']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($docData['subcategory']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="mb-2">
                                <?php if ($docData['average_rating'] > 0): ?>
                                <div class="star-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($docData['average_rating']) ? 'text-warning' : 'text-muted'; ?> fa-lg"></i>
                                    <?php endfor; ?>
                                    <span class="ms-2"><?php echo number_format($docData['average_rating'], 1); ?> (<?php echo $docData['total_ratings']; ?>)</span>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">No ratings yet</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo formatFileSize($docData['file_size']); ?> • <?php echo strtoupper($docData['file_type']); ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <h5><i class="fas fa-align-left me-2"></i>Description</h5>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($docData['description'] ?? 'No description provided.')); ?>
                        </p>
                    </div>
                    
                    <!-- Document Actions -->
                    <div class="mb-4">
                        <h5><i class="fas fa-play-circle me-2"></i>Document Actions</h5>
                        <div class="btn-group" role="group">
                            <?php if (canDisplayInline($docData['file_type'])): ?>
                            <a href="document-view.php?id=<?php echo $documentId; ?>&view=read" class="btn btn-success">
                                <i class="fas fa-book-reader me-1"></i>Read Online
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($auth->isLoggedIn()): ?>
                                <a href="download.php?id=<?php echo $documentId; ?>" class="btn btn-primary" onclick="return checkDownloadLimit(<?php echo $userUploads; ?>)">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login to Download
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($auth->isLoggedIn()): ?>
                            <?php if ($isSaved): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="unsave_document">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-bookmark me-1"></i>Saved
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="save_document">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="far fa-bookmark me-1"></i>Save for Later
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#shareModal">
                                <i class="fas fa-share-alt me-1"></i>Share
                            </button>
                        </div>
                        
                        <!-- Download Status Info -->
                        <?php if ($auth->isLoggedIn()): ?>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Your stats: <?php echo $userUploads; ?> uploads • <?php echo $userDownloads; ?> downloads (30 days)
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tags -->
                    <?php if ($docData['tags']): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-hashtag me-2"></i>Tags</h6>
                        <div>
                            <?php 
                            $tags = explode(',', $docData['tags']);
                            foreach ($tags as $tag):
                                $tag = trim($tag);
                                if (!empty($tag)):
                            ?>
                            <a href="search.php?q=<?php echo urlencode($tag); ?>" class="badge bg-light text-dark border me-1 mb-1">
                                <?php echo htmlspecialchars($tag); ?>
                            </a>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Stats -->
                    <div class="row text-center">
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2">
                                <div class="text-primary fw-bold"><?php echo $docData['download_count']; ?></div>
                                <small class="text-muted">Downloads</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2">
                                <div class="text-success fw-bold"><?php echo $docData['view_count']; ?></div>
                                <small class="text-muted">Views</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2">
                                <div class="text-warning fw-bold"><?php echo $docData['total_ratings']; ?></div>
                                <small class="text-muted">Ratings</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2">
                                <div class="text-info fw-bold">v<?php echo $docData['version']; ?></div>
                                <small class="text-muted">Version</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                Uploaded by: <strong><?php echo htmlspecialchars($uploader['username']); ?></strong>
                                on <?php echo date('M d, Y', strtotime($docData['upload_date'])); ?>
                            </small>
                        </div>
                        <div class="btn-group" role="group">
                            <?php if ($auth->isLoggedIn() && ($isOwner || $isAdmin)): ?>
                            <a href="edit-document.php?id=<?php echo $documentId; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="fas fa-flag me-1"></i>Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ratings Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Ratings & Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (!$hasRated && $auth->isLoggedIn() && !$isOwner): ?>
                    <!-- Rating Form -->
                    <div class="mb-4">
                        <h6>Rate this document</h6>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="rate">
                            <div class="mb-3">
                                <div class="star-rating-input mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning star-btn" data-rating="<?php echo $i; ?>">
                                        <i class="far fa-star"></i>
                                    </button>
                                    <?php endfor; ?>
                                    <input type="hidden" name="rating" id="selectedRating" value="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="review" class="form-label">Review (optional)</label>
                                <textarea class="form-control" id="review" name="review" rows="3" 
                                          placeholder="Share your thoughts about this document..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning">Submit Rating</button>
                        </form>
                    </div>
                    <hr>
                    <?php elseif ($hasRated): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-check-circle me-2"></i>
                        You rated this document <?php echo $userRating['rating']; ?> stars.
                        <?php if ($userRating['review']): ?>
                        Your review: "<?php echo htmlspecialchars($userRating['review']); ?>"
                        <?php endif; ?>
                    </div>
                    <?php elseif (!$auth->isLoggedIn()): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <a href="login.php">Login</a> to rate this document.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Ratings -->
                    <h6 class="mb-3">Recent Ratings</h6>
                    <?php if (empty($ratings)): ?>
                    <p class="text-muted">No ratings yet. Be the first to rate!</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($ratings as $rating): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($rating['username']); ?></h6>
                                <small><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></small>
                            </div>
                            <div class="star-rating mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if ($rating['review']): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($rating['review']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($docData['total_ratings'] > 5): ?>
                    <div class="text-center mt-3">
                        <a href="document-ratings.php?id=<?php echo $documentId; ?>" class="btn btn-sm btn-outline-primary">
                            View All Ratings (<?php echo $docData['total_ratings']; ?>)
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Feedback/Comments Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Comments & Feedback</h5>
                </div>
                <div class="card-body">
                    <!-- Comment Form -->
                    <?php if ($auth->isLoggedIn() && !$isOwner): ?>
                    <div class="mb-4">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="feedback">
                            <div class="mb-3">
                                <label for="comment" class="form-label">Add a comment</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" 
                                          placeholder="Share your feedback about this document..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>
                    <hr>
                    <?php elseif (!$auth->isLoggedIn()): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <a href="login.php">Login</a> to leave a comment.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Comments -->
                    <h6 class="mb-3">Recent Comments</h6>
                    <?php if (empty($feedbacks)): ?>
                    <p class="text-muted">No comments yet. Be the first to comment!</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($feedbacks as $feedback): 
                            // Check if current user liked this feedback
                            $userLiked = false;
                            if ($auth->isLoggedIn()) {
                                $likeCheck = $conn->prepare("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?");
                                $likeCheck->bind_param("ii", $feedback['id'], $userId);
                                $likeCheck->execute();
                                $userLiked = $likeCheck->get_result()->num_rows > 0;
                            }
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($feedback['username']); ?></h6>
                                <small><?php echo timeAgo($feedback['created_at']); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-thumbs-up me-1"></i> <?php echo $feedback['like_count']; ?> likes
                                </small>
                                <?php if ($auth->isLoggedIn()): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="like_feedback">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $userLiked ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        <i class="fas fa-thumbs-up me-1"></i> <?php echo $userLiked ? 'Liked' : 'Like'; ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($docData['total_ratings'] > 5): ?>
                    <div class="text-center mt-3">
                        <a href="document-feedback.php?id=<?php echo $documentId; ?>" class="btn btn-sm btn-outline-primary">
                            View All Comments
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <?php if (canDisplayInline($docData['file_type'])): ?>
                    <a href="document-view.php?id=<?php echo $documentId; ?>&view=read" class="btn btn-success btn-lg w-100 mb-3">
                        <i class="fas fa-book-reader me-2"></i>Read Online
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->isLoggedIn()): ?>
                        <a href="download.php?id=<?php echo $documentId; ?>" class="btn btn-primary btn-lg w-100 mb-3" onclick="return checkDownloadLimit(<?php echo $userUploads; ?>)">
                            <i class="fas fa-download me-2"></i>Download Now
                        </a>
                    <?php else: ?>
                        <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Download
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!$hasRated && $auth->isLoggedIn() && !$isOwner): ?>
                    <button type="button" class="btn btn-warning btn-lg w-100 mb-3" data-bs-toggle="modal" data-bs-target="#quickRateModal">
                        <i class="fas fa-star me-2"></i>Rate This Document
                    </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-info btn-lg w-100" data-bs-toggle="modal" data-bs-target="#shareModal">
                        <i class="fas fa-share-alt me-2"></i>Share With Friends
                    </button>
                </div>
            </div>
            
            <!-- Uploader Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Uploader Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <img src="<?php echo getProfilePhoto($uploader); ?>" 
                                 alt="<?php echo htmlspecialchars($uploader['username']); ?>" 
                                 class="rounded-circle border" 
                                 style="width: 60px; height: 60px; object-fit: cover;">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($uploader['username']); ?></h6>
                            <p class="small text-muted mb-0">
                                Member since <?php echo date('M Y', strtotime($uploader['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- University Info -->
                    <?php if (!empty($uploader['university_name']) || !empty($uploader['university_text'])): ?>
                    <div class="mb-3">
                        <h6><i class="fas fa-university me-2"></i>University</h6>
                        <p class="mb-1">
                            <?php if (!empty($uploader['university_name'])): ?>
                            <strong><?php echo htmlspecialchars($uploader['university_name']); ?></strong>
                            <?php if (!empty($uploader['university_short_name'])): ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($uploader['university_short_name']); ?>)</small>
                            <?php endif; ?>
                            <?php else: ?>
                            <?php echo htmlspecialchars($uploader['university_text']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($uploader['university_city'])): ?>
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars($uploader['university_city']); ?>
                            <?php if (!empty($uploader['university_country'])): ?>
                            , <?php echo htmlspecialchars($uploader['university_country']); ?>
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Course Info -->
                    <?php if (!empty($uploader['course_name']) || !empty($uploader['course_text'])): ?>
                    <div class="mb-3">
                        <h6><i class="fas fa-graduation-cap me-2"></i>Course</h6>
                        <p class="mb-1">
                            <?php if (!empty($uploader['course_name'])): ?>
                            <strong><?php echo htmlspecialchars($uploader['course_name']); ?></strong>
                            <?php if (!empty($uploader['course_code'])): ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($uploader['course_code']); ?>)</small>
                            <?php endif; ?>
                            <?php else: ?>
                            <?php echo htmlspecialchars($uploader['course_text']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Bio -->
                    <?php if (!empty($uploader['bio'])): ?>
                    <div class="mb-3">
                        <h6><i class="fas fa-info-circle me-2"></i>Bio</h6>
                        <p class="small mb-0"><?php echo nl2br(htmlspecialchars(substr($uploader['bio'], 0, 150))); ?>...</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Uploader Stats -->
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fw-bold"><?php echo $uploaderStats['total_documents']; ?></div>
                                <small class="text-muted">Docs</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fw-bold"><?php echo $uploaderStats['total_downloads']; ?></div>
                                <small class="text-muted">Downloads</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fw-bold"><?php echo number_format($uploaderStats['avg_rating'], 1); ?></div>
                                <small class="text-muted">Avg Rating</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="user-profile.php?id=<?php echo $docData['user_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-user-circle me-1"></i>View Full Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Document Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Document Information</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>File Type:</span>
                            <span class="fw-bold"><?php echo strtoupper($docData['file_type']); ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>File Size:</span>
                            <span class="fw-bold"><?php echo formatFileSize($docData['file_size']); ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>Uploaded:</span>
                            <span><?php echo date('M d, Y', strtotime($docData['upload_date'])); ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>Last Updated:</span>
                            <span><?php echo date('M d, Y', strtotime($docData['updated_at'])); ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>Document ID:</span>
                            <span class="text-muted">#<?php echo $documentId; ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($versions) && count($versions) > 1): ?>
                    <hr>
                    <h6><i class="fas fa-code-branch me-2"></i>Document Versions</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($versions as $version): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?php echo $version['is_latest'] ? 'success' : 'secondary'; ?>">
                                        v<?php echo $version['version']; ?>
                                    </span>
                                    <?php if ($version['is_latest']): ?>
                                    <span class="badge bg-success">Latest</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($version['upload_date'])); ?></small>
                                </div>
                            </div>
                            <?php if ($version['id'] != $documentId): ?>
                            <div class="mt-2">
                                <a href="document-view.php?id=<?php echo $version['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Version
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Similar Documents -->
            <?php if (!empty($similarDocs)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Similar Documents</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($similarDocs as $similar): ?>
                        <?php if ($similar['id'] != $documentId): ?>
                        <a href="document-view.php?id=<?php echo $similar['id']; ?>" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars(substr($similar['title'], 0, 50)); ?>...</h6>
                                <small class="text-muted"><?php echo $similar['education_level']; ?></small>
                            </div>
                            <p class="small mb-1 text-muted">
                                <?php echo htmlspecialchars(substr($similar['description'] ?? 'No description', 0, 60)); ?>...
                            </p>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    <i class="fas fa-download me-1"></i> <?php echo $similar['download_count']; ?>
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-star text-warning me-1"></i> <?php echo number_format($similar['average_rating'], 1); ?>
                                </small>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($auth->isLoggedIn()): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="report">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" name="report_type" required>
                            <option value="">Select a reason</option>
                            <option value="copyright">Copyright Violation</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="spam">Spam or Advertisement</option>
                            <option value="incorrect">Incorrect Information</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reportDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="reportDescription" name="description" 
                                  rows="4" placeholder="Please provide details about your report..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        False reports may result in account suspension.
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                    <h5>Please Login to Report</h5>
                    <p>You need to be logged in to report this document.</p>
                    <a href="login.php" class="btn btn-primary">Login</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Share Link</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="shareLink" 
                               value="<?php echo SITE_URL; ?>/document-view.php?id=<?php echo $documentId; ?>" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyShareLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Share on Social Media</label>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/document-view.php?id=' . $documentId); ?>" 
                           target="_blank" class="btn btn-primary btn-sm">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/document-view.php?id=' . $documentId); ?>&text=<?php echo urlencode('Check out this document: ' . $docData['title']); ?>" 
                           target="_blank" class="btn btn-info btn-sm">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Check out this document: ' . $docData['title'] . ' - ' . SITE_URL . '/document-view.php?id=' . $documentId); ?>" 
                           target="_blank" class="btn btn-success btn-sm">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Embed Code</label>
                    <textarea class="form-control" rows="3" readonly>
<iframe src="<?php echo SITE_URL; ?>/embed-document.php?id=<?php echo $documentId; ?>" width="100%" height="500" frameborder="0"></iframe>
                    </textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Rate Modal (popup for users who finish reading) -->
<div class="modal fade" id="quickRateModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate This Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>How would you rate this document?</p>
                
                <form method="POST" action="" id="quickRateForm">
                    <input type="hidden" name="action" value="rate">
                    <div class="mb-4 text-center">
                        <div class="star-rating-input-large mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="btn btn-lg btn-outline-warning star-btn-large" data-rating="<?php echo $i; ?>">
                                <i class="far fa-star"></i>
                            </button>
                            <?php endfor; ?>
                            <input type="hidden" name="rating" id="quickRating" value="0">
                        </div>
                        <div id="ratingText" class="text-muted">Tap to rate</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quickReview" class="form-label">Review (optional)</label>
                        <textarea class="form-control" id="quickReview" name="review" rows="3" 
                                  placeholder="Share your thoughts about this document..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="skipRating()">
                            <i class="fas fa-times me-1"></i>Skip
                        </button>
                        <button type="submit" class="btn btn-warning" id="submitRatingBtn" disabled>
                            <i class="fas fa-star me-1"></i>Submit Rating
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.document-viewer-container {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
}

.star-rating-input .star-btn {
    font-size: 1.5rem;
    padding: 0.25rem 0.5rem;
    margin-right: 0.25rem;
}

.star-rating-input-large .star-btn-large {
    font-size: 2.5rem;
    padding: 0.5rem;
    margin: 0 0.25rem;
    transition: all 0.2s;
}

.star-rating-input .star-btn:hover,
.star-rating-input .star-btn.active,
.star-rating-input-large .star-btn-large:hover,
.star-rating-input-large .star-btn-large.active {
    background-color: #ffc107;
    color: #000;
}

.star-rating-input .star-btn.active i,
.star-rating-input-large .star-btn-large.active i {
    color: #ffc107;
}

#pdfViewer {
    width: 100%;
    height: 600px;
}

.embed-responsive {
    position: relative;
    display: block;
    width: 100%;
    padding: 0;
    overflow: hidden;
}

.embed-responsive::before {
    display: block;
    content: "";
    padding-top: 56.25%;
}

.embed-responsive iframe {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

/* Enhanced document viewer */
.pdf-container {
    position: relative;
    width: 100%;
    height: 600px;
    overflow: auto;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
}

.pdf-page {
    margin: 20px auto;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    background: white;
}
</style>

<script>
let zoomLevel = 1;
let readingTimer;
let readingStartTime = <?php echo isset($_SESSION['reading_start_time']) ? $_SESSION['reading_start_time'] : 'null'; ?>;

// Download limit checker with corrected logic
function checkDownloadLimit(userUploads) {
    <?php if ($auth->isLoggedIn()): ?>
    const userDownloads = <?php echo $userDownloads; ?>;
    
    // First download - motivational message
    if (userDownloads === 0) {
        if (confirm('🎉 Welcome to Wezo Campus Hub!\n\nYou\'re about to download your first document.\n\nHelp others grow by sharing your own documents too!\n\nClick OK to continue with download.')) {
            return true;
        }
        return false;
    }
    
    // After 2 downloads: Require at least 3 uploads
    if (userDownloads >= 2 && userUploads < 3) {
        const message = `⚠️ Download Limit Reached!\n\nYou've downloaded ${userDownloads} documents.\n\nTo continue downloading, please upload at least 3 documents to help the community grow.\n\n"Help others, they also help you."\n\nClick OK to go to upload page.`;
        
        if (confirm(message)) {
            window.location.href = 'upload.php';
        }
        return false;
    }
    
    // Continuous cycle: After every 10 downloads, requires 3 uploads
    // This means: For every block of 10 downloads, you need 3 uploads
    // Example: 
    // - 0-9 downloads: no upload requirement
    // - 10-19 downloads: need 3 uploads
    // - 20-29 downloads: need 6 uploads
    // - 30-39 downloads: need 9 uploads
    // etc.
    
    const downloadBlocks = Math.floor((userDownloads - 2) / 10); // Start counting after first 2
    const requiredUploads = 3 + (downloadBlocks * 3); // Start with 3 uploads, then add 3 per block
    
    if (userUploads < requiredUploads) {
        const neededUploads = requiredUploads - userUploads;
        const message = `📚 Keep Sharing!\n\nYou've downloaded ${userDownloads} documents.\n\nPlease upload ${neededUploads} more document(s) to continue downloading.\n\nThis helps keep our community growing!\n\nClick OK to go to upload page.`;
        
        if (confirm(message)) {
            window.location.href = 'upload.php';
        }
        return false;
    }
    
    // Show encouragement message
    if (userDownloads % 5 === 0 && userDownloads !== 0) {
        const encouragement = `🌟 Great Job!\n\nYou've downloaded ${userDownloads} documents.\n\nThank you for being an active member of our community!\n\nKeep sharing knowledge!`;
        alert(encouragement);
    }
    
    return true;
    <?php else: ?>
    alert('Please login to download documents.');
    return false;
    <?php endif; ?>
}

document.addEventListener('DOMContentLoaded', function() {
    // Star rating input
    const starButtons = document.querySelectorAll('.star-btn, .star-btn-large');
    const selectedRating = document.getElementById('selectedRating');
    const quickRating = document.getElementById('quickRating');
    const submitRatingBtn = document.getElementById('submitRatingBtn');
    const ratingText = document.getElementById('ratingText');
    
    const ratingTexts = {
        0: 'Tap to rate',
        1: 'Poor',
        2: 'Fair',
        3: 'Good',
        4: 'Very Good',
        5: 'Excellent'
    };
    
    starButtons.forEach(button => {
        button.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            const isLarge = this.classList.contains('star-btn-large');
            const targetInput = isLarge ? quickRating : selectedRating;
            
            // Update selected rating
            targetInput.value = rating;
            
            // Update button states
            if (isLarge) {
                // Update all large star buttons
                document.querySelectorAll('.star-btn-large').forEach((btn, index) => {
                    if (index < rating) {
                        btn.classList.add('active');
                        btn.innerHTML = '<i class="fas fa-star"></i>';
                    } else {
                        btn.classList.remove('active');
                        btn.innerHTML = '<i class="far fa-star"></i>';
                    }
                });
                // Update text
                ratingText.textContent = ratingTexts[rating];
                // Enable submit button
                submitRatingBtn.disabled = false;
            } else {
                // Update regular star buttons
                document.querySelectorAll('.star-btn').forEach((btn, index) => {
                    if (index < rating) {
                        btn.classList.add('active');
                        btn.innerHTML = '<i class="fas fa-star"></i>';
                    } else {
                        btn.classList.remove('active');
                        btn.innerHTML = '<i class="far fa-star"></i>';
                    }
                });
            }
        });
    });
    
    // Copy share link
    window.copyShareLink = function() {
        const shareLink = document.getElementById('shareLink');
        shareLink.select();
        shareLink.setSelectionRange(0, 99999);
        document.execCommand('copy');
        
        // Show tooltip or alert
        const originalText = shareLink.value;
        shareLink.value = 'Copied!';
        setTimeout(() => {
            shareLink.value = originalText;
        }, 1500);
    };
    
    // Zoom functions for document viewer
    window.zoomIn = function() {
        zoomLevel += 0.1;
        applyZoom();
    };
    
    window.zoomOut = function() {
        if (zoomLevel > 0.2) {
            zoomLevel -= 0.1;
            applyZoom();
        }
    };
    
    window.resetZoom = function() {
        zoomLevel = 1;
        applyZoom();
    };
    
    function applyZoom() {
        const viewer = document.querySelector('#pdfViewer, .embed-responsive iframe, .pdf-container');
        if (viewer) {
            viewer.style.transform = `scale(${zoomLevel})`;
            viewer.style.transformOrigin = 'top left';
        }
    }
    
    // Reading progress tracker
    if (readingStartTime) {
        updateReadingTime();
        readingTimer = setInterval(updateReadingTime, 1000);
        
        // Simulate progress (in real app, track actual scroll/page progress)
        let progress = 0;
        const progressInterval = setInterval(() => {
            if (progress < 100) {
                progress += 0.1;
                document.getElementById('readingProgress').style.width = progress + '%';
            } else {
                clearInterval(progressInterval);
            }
        }, 100);
    }
    
    // Finish reading - show rating popup
    window.finishReading = function() {
        if (<?php echo $auth->isLoggedIn() && !$hasRated && !$isOwner ? 'true' : 'false'; ?>) {
            // Check if user spent reasonable time reading
            if (readingStartTime && (Date.now()/1000 - readingStartTime) > 60) { // At least 1 minute
                const quickRateModal = new bootstrap.Modal(document.getElementById('quickRateModal'));
                quickRateModal.show();
            } else {
                window.location.href = 'document-view.php?id=<?php echo $documentId; ?>';
            }
        } else {
            window.location.href = 'document-view.php?id=<?php echo $documentId; ?>';
        }
    };
    
    // Skip rating
    window.skipRating = function() {
        fetch('document-view.php?id=<?php echo $documentId; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=skip_rating'
        })
        .then(() => {
            window.location.href = 'document-view.php?id=<?php echo $documentId; ?>';
        });
    };
    
    // Show rating popup when leaving reading mode
    window.addEventListener('beforeunload', function(e) {
        if (readingStartTime && <?php echo $auth->isLoggedIn() && !$hasRated && !$isOwner ? 'true' : 'false'; ?>) {
            const timeSpent = Date.now()/1000 - readingStartTime;
            if (timeSpent > 60) { // At least 1 minute
                // Don't prevent unload, but track that user left without rating
                fetch('track-reading.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        document_id: <?php echo $documentId; ?>,
                        time_spent: Math.floor(timeSpent),
                        action: 'left_without_rating'
                    })
                });
            }
        }
    });
});

function updateReadingTime() {
    if (!readingStartTime) return;
    
    const now = Math.floor(Date.now() / 1000);
    const seconds = now - readingStartTime;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    
    document.getElementById('readingTime').textContent = 
        `Time spent: ${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

// Auto-show rating modal if user finished reading
<?php if (isset($_SESSION['reading_document']) && $_SESSION['reading_document'] == $documentId && 
          $auth->isLoggedIn() && !$hasRated && !$isOwner): ?>
document.addEventListener('DOMContentLoaded', function() {
    const quickRateModal = new bootstrap.Modal(document.getElementById('quickRateModal'));
    quickRateModal.show();
});
<?php endif; ?>
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

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time = time() - $time;
    $time = ($time < 1) ? 1 : $time;
    $tokens = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':' ago');
    }
}

function canDisplayInline($fileType) {
    $inlineTypes = ['pdf', 'txt', 'html', 'htm', 'md', 'rtf'];
    return in_array(strtolower($fileType), $inlineTypes);
}

function getDocumentViewer($docData, $readMode = false) {
    $fileType = strtolower($docData['file_type']);
    $filePath = $docData['file_path'];
    
    // Fix file path - make sure it's an absolute path
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($filePath, '/');
    
    // Ensure file exists
    if (!file_exists($absolutePath)) {
        return '<div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                    <h5>File Not Found</h5>
                    <p class="text-muted">The document file could not be found on the server.</p>
                    <p class="text-muted small">Path: ' . htmlspecialchars($absolutePath) . '</p>
                    <a href="download.php?id=' . $docData['id'] . '" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>Download Document
                    </a>
                </div>';
    }
    
    switch ($fileType) {
        case 'pdf':
            // For PDF files, use Google Docs Viewer or direct PDF display
            $pdfUrl = SITE_URL . '/' . ltrim($filePath, '/');
            
            if ($readMode) {
                // Enhanced PDF viewer for reading mode
                return '<div class="pdf-container">
                            <iframe src="' . $pdfUrl . '" 
                                    style="width: 100%; height: 100%;" 
                                    frameborder="0"></iframe>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Use the zoom buttons to adjust viewing size
                            </small>
                        </div>';
            } else {
                return '<div class="text-center py-5">
                            <i class="fas fa-file-pdf fa-4x text-danger mb-3"></i>
                            <h5>PDF Document</h5>
                            <p class="text-muted mb-4">Click below to read this document online.</p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="document-view.php?id=' . $docData['id'] . '&view=read" class="btn btn-primary btn-lg">
                                    <i class="fas fa-book-reader me-2"></i>Read Online
                                </a>
                                <a href="' . $pdfUrl . '" target="_blank" class="btn btn-success btn-lg">
                                    <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                                </a>
                            </div>
                        </div>';
            }
            
        case 'txt':
        case 'md':
        case 'rtf':
            // For text files, display them directly with character limit
            $content = file_get_contents($absolutePath);
            if (strlen($content) > 100000) {
                $content = substr($content, 0, 100000) . "\n\n[Document truncated - file too large to display fully]";
            }
            
            if ($readMode) {
                return '<div class="p-3 bg-white" style="height: 600px; overflow-y: auto;">
                            <pre style="white-space: pre-wrap; font-family: monospace; font-size: 14px; margin: 0; line-height: 1.6;">' . htmlspecialchars($content) . '</pre>
                        </div>';
            } else {
                $previewContent = substr($content, 0, 1000);
                if (strlen($content) > 1000) {
                    $previewContent .= "\n\n[Preview only - click " . ($readMode ? 'Read Online' : 'Read Online') . " to view full document]";
                }
                return '<div class="p-3 bg-white" style="height: 400px; overflow-y: auto;">
                            <pre style="white-space: pre-wrap; font-family: monospace; font-size: 14px; margin: 0; line-height: 1.6;">' . htmlspecialchars($previewContent) . '</pre>
                        </div>
                        <div class="text-center mt-3">
                            <a href="document-view.php?id=' . $docData['id'] . '&view=read" class="btn btn-primary">
                                <i class="fas fa-book-reader me-2"></i>Read Full Document
                            </a>
                        </div>';
            }
            
        case 'html':
        case 'htm':
            // For HTML files, use iframe with sandbox for security
            $htmlUrl = SITE_URL . '/' . ltrim($filePath, '/');
            return '<div class="embed-responsive" style="height: 600px;">
                        <iframe src="' . $htmlUrl . '" 
                                sandbox="allow-same-origin allow-scripts allow-forms" 
                                style="border: none; width: 100%; height: 100%;"></iframe>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            HTML document displayed inline
                        </small>
                    </div>';
            
        default:
            return '<div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h5>Preview Not Available</h5>
                        <p class="text-muted">This file type cannot be displayed inline.</p>
                        <a href="download.php?id=' . $docData['id'] . '" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i>Download to View
                        </a>
                    </div>';
    }
}

include 'includes/footer.php';
?>