<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Dashboard - Wezo Campus Hub";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize auth object
$auth = new Auth();

// DEBUG: Check if auth is working
if (!$auth) {
    die("Auth object creation failed!");
}

// DEBUG: Check if database connection is alive
if (!$auth->isDBConnected()) {
    die("Database connection failed in Auth class!");
}

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get current user - this should work now
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    // If getCurrentUser fails, redirect to login
    header("Location: login.php?error=session_expired");
    exit();
}

$user_id = $current_user['id'];

// Create a NEW database connection for dashboard queries
// This prevents conflicts with the Auth class connection
$conn = getDBConnection();

if (!$conn || $conn->connect_error) {
    die("Dashboard database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

// Get enhanced user data with university info
$stmt = $conn->prepare("
    SELECT u.*, 
           uni.name as university_name, 
           c.name as course_name,
           up.bio,
           up.phone_number,
           up.gender
    FROM users u
    LEFT JOIN universities uni ON u.university_id = uni.id
    LEFT JOIN courses c ON u.course_id = c.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Merge current_user data with enhanced data
if ($user_data) {
    $user = array_merge($current_user, $user_data);
} else {
    $user = $current_user;
}

// Get user statistics
$stats = [];
$stats_stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM documents WHERE user_id = ?) as total_documents,
        (SELECT COUNT(*) FROM documents WHERE user_id = ? AND DATE(upload_date) = CURDATE()) as today_uploads,
        (SELECT COALESCE(SUM(download_count), 0) FROM documents WHERE user_id = ?) as total_downloads,
        (SELECT COUNT(*) FROM feedbacks WHERE user_id = ?) as total_feedbacks,
        (SELECT COUNT(*) FROM ratings WHERE user_id = ?) as total_ratings,
        (SELECT COALESCE(AVG(average_rating), 0) FROM documents WHERE user_id = ?) as avg_rating,
        (SELECT COUNT(*) FROM saved_documents WHERE user_id = ?) as saved_documents,
        (SELECT COUNT(*) FROM download_logs WHERE user_id = ? AND DATE(downloaded_at) = CURDATE()) as today_downloads,
        (SELECT COUNT(*) FROM user_universities WHERE user_id = ?) as university_count
");
if ($stats_stmt) {
    $stats_stmt->bind_param("iiiiiiiii", 
        $user_id, $user_id, $user_id, $user_id, $user_id, 
        $user_id, $user_id, $user_id, $user_id
    );
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    $stats_stmt->close();
}

// Get recent documents
$recent_docs = [];
$recent_stmt = $conn->prepare("
    SELECT d.*, 
           u.username as uploader_name,
           u.profile_photo as uploader_photo,
           (SELECT COUNT(*) FROM feedbacks WHERE document_id = d.id) as feedback_count,
           (SELECT COUNT(*) FROM ratings WHERE document_id = d.id) as rating_count,
           u.university_text,
           c.name as course_name
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE d.is_approved = 1 
    ORDER BY d.upload_date DESC 
    LIMIT 6
");
if ($recent_stmt) {
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    while ($row = $recent_result->fetch_assoc()) {
        $recent_docs[] = $row;
    }
    $recent_stmt->close();
}

// Get recent feedback
$recent_feedback = [];
$feedback_stmt = $conn->prepare("
    SELECT f.*, d.title, u.username, u.profile_photo
    FROM feedbacks f 
    JOIN documents d ON f.document_id = d.id 
    JOIN users u ON f.user_id = u.id 
    WHERE (d.user_id = ? OR f.user_id = ?)
    ORDER BY f.created_at DESC 
    LIMIT 5
");
if ($feedback_stmt) {
    $feedback_stmt->bind_param("ii", $user_id, $user_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    while ($row = $feedback_result->fetch_assoc()) {
        $recent_feedback[] = $row;
    }
    $feedback_stmt->close();
}

// Get trending documents
$trending_docs = [];
$trending_stmt = $conn->prepare("
    SELECT d.*, 
           u.username as uploader_name,
           u.profile_photo as uploader_photo,
           (d.download_count + d.view_count + (d.average_rating * 10)) as popularity_score
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_approved = 1 
    ORDER BY popularity_score DESC 
    LIMIT 4
");
if ($trending_stmt) {
    $trending_stmt->execute();
    $trending_result = $trending_stmt->get_result();
    while ($row = $trending_result->fetch_assoc()) {
        $trending_docs[] = $row;
    }
    $trending_stmt->close();
}

// Get recommended documents
$recommended_docs = [];
$recommended_stmt = $conn->prepare("
    SELECT DISTINCT d.*, 
           u.username as uploader_name,
           u.profile_photo as uploader_photo
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_approved = 1 
      AND d.user_id != ?
    ORDER BY RAND() 
    LIMIT 4
");
if ($recommended_stmt) {
    $recommended_stmt->bind_param("i", $user_id);
    $recommended_stmt->execute();
    $recommended_result = $recommended_stmt->get_result();
    while ($row = $recommended_result->fetch_assoc()) {
        $recommended_docs[] = $row;
    }
    $recommended_stmt->close();
}

// Get user's documents
$my_recent_docs = [];
$my_docs_stmt = $conn->prepare("
    SELECT id, title, download_count, view_count, average_rating, upload_date
    FROM documents 
    WHERE user_id = ? 
    ORDER BY upload_date DESC 
    LIMIT 4
");
if ($my_docs_stmt) {
    $my_docs_stmt->bind_param("i", $user_id);
    $my_docs_stmt->execute();
    $my_docs_result = $my_docs_stmt->get_result();
    while ($row = $my_docs_result->fetch_assoc()) {
        $my_recent_docs[] = $row;
    }
    $my_docs_stmt->close();
}

// Get platform statistics
$platform_stats_result = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM documents WHERE is_approved = 1) as total_documents,
        (SELECT COUNT(DISTINCT user_id) FROM documents) as total_uploaders,
        (SELECT COALESCE(SUM(download_count), 0) FROM documents) as total_downloads,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
        (SELECT COUNT(*) FROM universities) as total_universities,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM documents WHERE DATE(upload_date) = CURDATE()) as today_uploads
");
if ($platform_stats_result) {
    $platform_stats = $platform_stats_result->fetch_assoc();
} else {
    $platform_stats = [
        'total_documents' => 0,
        'total_users' => 0,
        'total_downloads' => 0,
        'total_universities' => 0,
        'total_courses' => 0,
        'today_uploads' => 0
    ];
}

// Simplified profile photo function
function getProfilePhotoQuick($photo_data) {
    $default = SITE_URL . '/assets/images/default.png';
    
    if (empty($photo_data) || empty($photo_data['profile_photo'])) {
        return $default;
    }
    
    $photo = $photo_data['profile_photo'];
    
    // If it's a URL, return it
    if (strpos($photo, 'http') === 0) {
        return $photo;
    }
    
    // Clean path
    $photo = ltrim($photo, './');
    
    // If it starts with uploads/, return full URL
    if (strpos($photo, 'uploads/') === 0) {
        return SITE_URL . '/' . $photo;
    }
    
    // If it's just a filename, assume it's in uploads/profile_photos/
    if (strpos($photo, '/') === false) {
        return SITE_URL . '/uploads/profile_photos/' . $photo;
    }
    
    return $default;
}

// Get greeting based on time of day
function getGreeting() {
    $hour = date('H');
    if ($hour < 12) {
        return "Welcome";
    } elseif ($hour < 17) {
        return "Welcome";
    } else {
        return "Welcome";
    }
}

// Don't close connection here - let PHP handle it
// $conn->close(); // REMOVE THIS LINE IF IT EXISTS

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Welcome Section with Profile -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php
                            // Get profile photo URL
                            $profile_photo_url = getProfilePhotoQuick($user);
                            ?>
                            <img src="<?php echo $profile_photo_url; ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle border border-3 border-white"
                                 style="width: 80px; height: 80px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default.png'">
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="mb-1"><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($user['username']); ?>!</h3>
                            <p class="mb-0">
                                <?php if (!empty($user['university_name']) || !empty($user['university_text'])): ?>
                                <i class="fas fa-university me-1"></i> 
                                <?php echo !empty($user['university_name']) ? htmlspecialchars($user['university_name']) : htmlspecialchars($user['university_text']); ?>
                                <?php endif; ?>
                                <?php if (!empty($user['course_name'])): ?>
                                | <i class="fas fa-graduation-cap me-1"></i> 
                                <?php echo htmlspecialchars($user['course_name']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="bg-white rounded p-2 d-inline-block">
                                <small class="text-dark d-block">Member Since:</small>
                                <strong class="text-primary"><?php echo date('F Y', strtotime($user['created_at'])); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="upload.php" class="btn btn-success w-100">
                                <i class="fas fa-upload me-1"></i>Upload Document
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="mylibrary.php" class="btn btn-primary w-100">
                                <i class="fas fa-book me-1"></i>My Library
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="search.php" class="btn btn-info w-100 text-white">
                                <i class="fas fa-search me-1"></i>Search Documents
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="chatbot.php" class="btn btn-warning w-100">
                                <i class="fas fa-user me-1"></i>Chat with AI assistant
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
            <h4 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Your Statistics</h4>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-upload fa-3x text-primary mb-2"></i>
                    <h2 class="card-title"><?php echo $stats['total_documents'] ?? 0; ?></h2>
                    <p class="card-text">Your Documents</p>
                    <?php if (($stats['today_uploads'] ?? 0) > 0): ?>
                    <small class="text-success"><?php echo $stats['today_uploads']; ?> uploaded today!</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-success h-100">
                <div class="card-body text-center">
                    <i class="fas fa-download fa-3x text-success mb-2"></i>
                    <h2 class="card-title"><?php echo number_format($stats['total_downloads'] ?? 0); ?></h2>
                    <p class="card-text">Total Downloads</p>
                    <?php if (($stats['today_downloads'] ?? 0) > 0): ?>
                    <small class="text-success"><?php echo $stats['today_downloads']; ?> today</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body text-center">
                    <i class="fas fa-star fa-3x text-warning mb-2"></i>
                    <h2 class="card-title"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?>/5</h2>
                    <p class="card-text">Avg Rating</p>
                    <?php if (($stats['total_ratings'] ?? 0) > 0): ?>
                    <small class="text-info"><?php echo $stats['total_ratings']; ?> ratings given</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-info h-100">
                <div class="card-body text-center">
                    <i class="fas fa-bookmark fa-3x text-info mb-2"></i>
                    <h2 class="card-title"><?php echo $stats['saved_documents'] ?? 0; ?></h2>
                    <p class="card-text">Saved Documents</p>
                    <?php if (($stats['total_feedbacks'] ?? 0) > 0): ?>
                    <small class="text-info"><?php echo $stats['total_feedbacks']; ?> feedbacks given</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- My Recent Documents -->
    <?php if (!empty($my_recent_docs)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Your Recent Documents</h5>
                    <a href="mylibrary.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($my_recent_docs as $doc): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars(substr($doc['title'], 0, 30)); ?>...
                                        </a>
                                    </h6>
                                    <p class="card-text small text-muted mb-2">
                                        <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            <i class="fas fa-download"></i> <?php echo $doc['download_count']; ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-eye"></i> <?php echo $doc['view_count']; ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-star text-warning"></i> <?php echo number_format($doc['average_rating'], 1); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent p-2">
                                    <div class="d-grid">
                                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Browse Public Documents Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Explore Public Documents</h5>
                    <a href="search.php" class="btn btn-sm btn-primary">Browse All</a>
                </div>
                <div class="card-body">
                    <!-- Trending Documents -->
                    <?php if (!empty($trending_docs)): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-fire text-danger me-2"></i>Trending Now</h6>
                        <div class="row">
                            <?php foreach ($trending_docs as $doc): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <?php if (!empty($doc['uploader_photo'])): ?>
                                            <?php $uploader_photo = getProfilePhotoQuick(['profile_photo' => $doc['uploader_photo']]); ?>
                                            <img src="<?php echo $uploader_photo; ?>" 
                                                 class="rounded-circle me-2" 
                                                 style="width: 30px; height: 30px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default.png'">
                                            <?php endif; ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($doc['uploader_name']); ?></small>
                                        </div>
                                        <h6 class="card-title">
                                            <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars(substr($doc['title'], 0, 40)); ?>...
                                            </a>
                                        </h6>
                                        <div class="mb-2">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($doc['education_level']); ?></span>
                                            <?php if (!empty($doc['category'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($doc['category']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="fas fa-download"></i> <?php echo $doc['download_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $doc['view_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-star text-warning"></i> <?php echo number_format($doc['average_rating'], 1); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent p-2">
                                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recommended Documents -->
                    <?php if (!empty($recommended_docs)): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-thumbs-up text-success me-2"></i>Recommended For You</h6>
                        <div class="row">
                            <?php foreach ($recommended_docs as $doc): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <?php if (!empty($doc['uploader_photo'])): ?>
                                            <?php $uploader_photo = getProfilePhotoQuick(['profile_photo' => $doc['uploader_photo']]); ?>
                                            <img src="<?php echo $uploader_photo; ?>" 
                                                 class="rounded-circle me-2" 
                                                 style="width: 30px; height: 30px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default.png'">
                                            <?php endif; ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($doc['uploader_name']); ?></small>
                                        </div>
                                        <h6 class="card-title">
                                            <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars(substr($doc['title'], 0, 40)); ?>...
                                            </a>
                                        </h6>
                                        <div class="mb-2">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($doc['education_level']); ?></span>
                                            <?php if (!empty($doc['category'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($doc['category']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="fas fa-download"></i> <?php echo $doc['download_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $doc['view_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-star text-warning"></i> <?php echo number_format($doc['average_rating'], 1); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent p-2">
                                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-success w-100">
                                            Explore
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Documents from All Users -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recently Uploaded Documents</h5>
                    <a href="search.php?sort=recent" class="btn btn-sm btn-outline-primary">View More</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_docs)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                        <p>No documents have been uploaded yet.</p>
                        <a href="upload.php" class="btn btn-primary">Be the first to upload</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Uploaded By</th>
                                    <th>University</th>
                                    <th>Uploaded</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_docs as $doc): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($doc['title'], 0, 50)); ?></strong><br>
                                        <small class="text-muted">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($doc['education_level']); ?></span>
                                            <?php if (!empty($doc['category'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($doc['category']); ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($doc['uploader_photo'])): ?>
                                            <?php $uploader_photo = getProfilePhotoQuick(['profile_photo' => $doc['uploader_photo']]); ?>
                                            <img src="<?php echo $uploader_photo; ?>" 
                                                 class="rounded-circle me-2" 
                                                 style="width: 30px; height: 30px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default.png'">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($doc['uploader_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($doc['university_text'])): ?>
                                        <small><?php echo htmlspecialchars($doc['university_text']); ?></small>
                                        <?php elseif (!empty($doc['course_name'])): ?>
                                        <small><?php echo htmlspecialchars($doc['course_name']); ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d', strtotime($doc['upload_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($doc['average_rating'] > 0): ?>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= round($doc['average_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                            <small class="ms-1">(<?php echo $doc['rating_count'] ?? 0; ?>)</small>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">No ratings</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-success" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn btn-outline-info" title="Save" onclick="saveDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-bookmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Feedback & Platform Stats -->
        <div class="col-md-4">
            <!-- Recent Feedback -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_feedback)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p>No recent activity.</p>
                        <a href="search.php" class="btn btn-sm btn-primary">Browse Documents</a>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_feedback as $feedback): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex">
                                <?php if (!empty($feedback['profile_photo'])): ?>
                                <?php $feedback_photo = getProfilePhotoQuick(['profile_photo' => $feedback['profile_photo']]); ?>
                                <img src="<?php echo $feedback_photo; ?>" 
                                     class="rounded-circle me-2" 
                                     style="width: 40px; height: 40px; object-fit: cover;"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default.png'">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($feedback['username']); ?></h6>
                                        <small><?php echo time_ago($feedback['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo htmlspecialchars(substr($feedback['comment'], 0, 80)); ?>...</p>
                                    <small class="text-muted">On: <?php echo htmlspecialchars(substr($feedback['title'], 0, 40)); ?>...</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Platform Stats -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>Platform Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-3 border rounded">
                                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                <h4 class="mb-0"><?php echo number_format($platform_stats['total_documents']); ?></h4>
                                <small>Documents</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 border rounded">
                                <i class="fas fa-users fa-2x text-success mb-2"></i>
                                <h4 class="mb-0"><?php echo number_format($platform_stats['total_users']); ?></h4>
                                <small>Users</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 border rounded">
                                <i class="fas fa-download fa-2x text-info mb-2"></i>
                                <h4 class="mb-0"><?php echo number_format($platform_stats['total_downloads']); ?></h4>
                                <small>Downloads</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 border rounded">
                                <i class="fas fa-university fa-2x text-warning mb-2"></i>
                                <h4 class="mb-0"><?php echo number_format($platform_stats['total_universities']); ?></h4>
                                <small>Universities</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-sync-alt me-1"></i>
                            Updated in real-time
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tips Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips for Better Experience</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-search text-primary fa-2x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Browse Documents</h6>
                                    <p class="small mb-0">Explore documents from various universities and courses</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-star text-warning fa-2x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Rate & Review</h6>
                                    <p class="small mb-0">Help others by rating and reviewing documents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-share-alt text-success fa-2x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Share Knowledge</h6>
                                    <p class="small mb-0">Upload your lecture notes and study materials</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-user-graduate text-danger fa-2x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Complete Profile</h6>
                                    <p class="small mb-0">Add your university and course info for better recommendations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Save document function
function saveDocument(documentId) {
    fetch('save_document.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'document_id=' + documentId + '&action=save'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document saved to your library!');
        } else {
            alert('Error: ' + (data.message || 'Could not save document'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
    });
}

// Animate statistics cards on hover
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.card.border-primary, .card.border-success, .card.border-warning, .card.border-info');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php 
// Helper function for time ago
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $time = $now - $time;
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

include 'includes/footer.php'; 
?>