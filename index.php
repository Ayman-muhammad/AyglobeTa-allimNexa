<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Wezo Campus Hub - Educational Resources Platform";

$conn = getDBConnection();

// Get statistics
$stats = [];
$result = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
        (SELECT COUNT(*) FROM documents WHERE is_approved = 1) as total_documents,
        (SELECT SUM(download_count) FROM documents) as total_downloads,
        (SELECT COUNT(*) FROM feedbacks) as total_feedbacks,
        (SELECT COUNT(*) FROM chat_logs) as total_chats
");
if ($result) {
    $stats = $result->fetch_assoc();
}

// Get featured documents
$featured_docs = [];
$result = $conn->query("
    SELECT d.*, u.username 
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_approved = 1 
    AND d.average_rating >= 4.0 
    ORDER BY d.download_count DESC, d.upload_date DESC 
    LIMIT 6
");
while ($row = $result->fetch_assoc()) {
    $featured_docs[] = $row;
}

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section py-5 mb-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Wezo Campus Hub</h1>
                <p class="lead mb-4">Your premier platform for sharing and accessing educational resources across Kenyan education systems. Join thousands of students in the largest educational community.</p>
                <div class="d-flex gap-3">
                    <?php if (!$auth->isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-light btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Join Now
                    </a>
                    <?php endif; ?>
                    <a href="#features" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-rocket me-2"></i>Explore Features
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Education" class="img-fluid" style="max-height: 300px;">
            </div>
        </div>
    </div>
</section>

<!-- Insight Section -->
<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="display-5 fw-bold mb-3">Platform Insights</h2>
                <p class="lead">Join Kenya's Fastest Growing Learning Community</p>
            </div>
        </div>
        
        <!-- Main Statistics Row -->
        <div class="row g-4 mb-5">
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm h-100 insight-stat-card">
                    <div class="card-body p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-users fa-3x text-primary"></i>
                        </div>
                        <h2 class="display-4 fw-bold counter" data-count="2870000" data-speed="50" data-increment="231">0</h2>
                        <p class="text-muted mb-0">Active Students</p>
                        <div class="mt-2">
                            <span class="badge bg-success" id="today-users">
                                <i class="fas fa-arrow-up me-1"></i>+2,341 today
                            </span>
                        </div>
                        <div class="mini-counter mt-2">
                            <small class="text-muted">
                                <i class="fas fa-user-plus text-success me-1"></i>
                                <span id="live-users">+231</span> in last hour
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm h-100 insight-stat-card">
                    <div class="card-body p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-file-alt fa-3x text-success"></i>
                        </div>
                        <h2 class="display-4 fw-bold counter" data-count="7840000" data-speed="80" data-increment="12">0</h2>
                        <p class="text-muted mb-0">Study Resources</p>
                        <div class="mt-2">
                            <span class="badge bg-success" id="today-docs">
                                <i class="fas fa-plus me-1"></i>+856 new today
                            </span>
                        </div>
                        <div class="mini-counter mt-2">
                            <small class="text-muted">
                                <i class="fas fa-file-upload text-info me-1"></i>
                                <span id="live-docs">+12</span> in last hour
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm h-100 insight-stat-card">
                    <div class="card-body p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-download fa-3x text-info"></i>
                        </div>
                        <h2 class="display-4 fw-bold counter" data-count="45600000" data-speed="120" data-increment="723">0</h2>
                        <p class="text-muted mb-0">Total Downloads</p>
                        <div class="mt-2">
                            <span class="badge bg-success" id="today-downloads">
                                <i class="fas fa-fire me-1"></i>52K today
                            </span>
                        </div>
                        <div class="mini-counter mt-2">
                            <small class="text-muted">
                                <i class="fas fa-download text-primary me-1"></i>
                                <span id="live-downloads">+723</span> in last hour
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm h-100 insight-stat-card">
                    <div class="card-body p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-star fa-3x text-warning"></i>
                        </div>
                        <h2 class="display-4 fw-bold counter" data-count="4.7" data-speed="100" data-increment="0.01">0</h2>
                        <p class="text-muted mb-0">Platform Rating</p>
                        <div class="star-rating mt-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= 4 ? 'text-warning' : ($i == 5 ? 'text-warning-half' : 'text-muted'); ?>"></i>
                            <?php endfor; ?>
                            <small class="ms-1 counter" data-count="284753" data-speed="150">(0 reviews)</small>
                        </div>
                        <div class="mini-counter mt-2">
                            <small class="text-muted">
                                <i class="fas fa-star-half-alt text-warning me-1"></i>
                                <span id="live-ratings">+47</span> new ratings today
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Real-Time Activity Section -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-bolt text-warning me-2"></i>
                                Live Activity Feed
                                <span class="badge bg-danger ms-2 pulse">LIVE</span>
                            </h4>
                            <div class="real-time-stats">
                                <small class="text-muted me-3">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Updates every <span id="countdown">8</span>s
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-history me-1"></i>
                                    Last updated: <span id="last-updated">Just now</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="activity-feed" id="activity-feed">
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <img src="https://i.pravatar.cc/40?img=1" alt="User" class="rounded-circle">
                                </div>
                                <div class="activity-content">
                                    <strong>Brian K.</strong> from <span class="text-primary">University of Nairobi</span>
                                    <span class="text-success">downloaded</span> "Computer Science Notes 2024"
                                    <small class="text-muted ms-2">2 minutes ago</small>
                                </div>
                                <div class="activity-action">
                                    <span class="badge bg-primary">CS</span>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <img src="https://i.pravatar.cc/40?img=2" alt="User" class="rounded-circle">
                                </div>
                                <div class="activity-content">
                                    <strong>Sarah M.</strong> from <span class="text-primary">JKUAT</span>
                                    <span class="text-info">uploaded</span> "KCSE Mathematics Past Papers"
                                    <small class="text-muted ms-2">5 minutes ago</small>
                                </div>
                                <div class="activity-action">
                                    <span class="text-success"><i class="fas fa-heart"></i> <span class="like-count">34</span></span>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <img src="https://i.pravatar.cc/40?img=3" alt="User" class="rounded-circle">
                                </div>
                                <div class="activity-content">
                                    <strong>David W.</strong> from <span class="text-primary">Moi University</span>
                                    <span class="text-warning">rated 5 stars</span> to "Business Studies Guide"
                                    <small class="text-muted ms-2">8 minutes ago</small>
                                </div>
                                <div class="activity-action">
                                    <div class="star-rating-sm">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <img src="https://i.pravatar.cc/40?img=4" alt="User" class="rounded-circle">
                                </div>
                                <div class="activity-content">
                                    <strong>Grace N.</strong> from <span class="text-primary">Kenyatta University</span>
                                    <span class="text-danger">commented</span> "This saved my exam!"
                                    <small class="text-muted ms-2">12 minutes ago</small>
                                </div>
                                <div class="activity-action">
                                    <span class="badge bg-info"><i class="fas fa-reply me-1"></i> <span class="reply-count">3</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Social Proof & Testimonials -->
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-comment-dots text-primary me-2"></i>
                            Recent Community Feedback
                        </h4>
                        
                        <div class="testimonial">
                            <div class="testimonial-header d-flex align-items-center mb-3">
                                <img src="https://i.pravatar.cc/50?img=5" alt="User" class="rounded-circle me-3">
                                <div>
                                    <h6 class="mb-0">James O.</h6>
                                    <small class="text-muted">Medicine Student • 2 hours ago</small>
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-sm btn-outline-success like-btn" data-count="142">
                                        <i class="fas fa-thumbs-up me-1"></i> <span class="like-number">142</span>
                                    </button>
                                </div>
                            </div>
                            <p class="mb-3">
                                "Found all the medical resources I needed for my finals. The biochemistry notes are particularly excellent!"
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="star-rating">
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-reply me-1"></i> <span class="reply-number">8</span> replies
                                </small>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="testimonial">
                            <div class="testimonial-header d-flex align-items-center mb-3">
                                <img src="https://i.pravatar.cc/50?img=6" alt="User" class="rounded-circle me-3">
                                <div>
                                    <h6 class="mb-0">Susan A.</h6>
                                    <small class="text-muted">High School Teacher • 3 hours ago</small>
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-sm btn-outline-success like-btn" data-count="89">
                                        <i class="fas fa-thumbs-up me-1"></i> <span class="like-number">89</span>
                                    </button>
                                </div>
                            </div>
                            <p class="mb-3">
                                "As a CBC teacher, this platform has transformed how my students access materials. The structured resources are perfect for our curriculum."
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="star-rating">
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-reply me-1"></i> <span class="reply-number">14</span> replies
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            Real-Time Engagement
                        </h4>
                        
                        <div class="engagement-metric mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Document Downloads Today</span>
                                <div>
                                    <strong class="text-success" id="today-downloads-count">52,847</strong>
                                    <small class="text-success ms-1">
                                        <i class="fas fa-arrow-up"></i>
                                        <span id="downloads-trend">+5.2%</span>
                                    </small>
                                </div>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success download-progress" role="progressbar" style="width: 85%"></div>
                            </div>
                            <div class="text-end mt-1">
                                <small class="text-muted">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    <span id="downloads-rate">12.3/sec</span> average rate
                                </small>
                            </div>
                        </div>
                        
                        <div class="engagement-metric mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Active Discussions</span>
                                <div>
                                    <strong class="text-primary" id="active-discussions">3,284</strong>
                                    <small class="text-success ms-1">
                                        <i class="fas fa-arrow-up"></i>
                                        <span id="discussions-trend">+2.8%</span>
                                    </small>
                                </div>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 70%"></div>
                            </div>
                        </div>
                        
                        <div class="engagement-metric mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>AI Chat Assistance Today</span>
                                <div>
                                    <strong class="text-info" id="ai-assistance">12,591</strong>
                                    <small class="text-success ms-1">
                                        <i class="fas fa-arrow-up"></i>
                                        <span id="ai-trend">+7.5%</span>
                                    </small>
                                </div>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: 90%"></div>
                            </div>
                        </div>
                        
                        <div class="engagement-metric mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Resource Uploads Today</span>
                                <div>
                                    <strong class="text-warning" id="today-uploads">856</strong>
                                    <small class="text-success ms-1">
                                        <i class="fas fa-arrow-up"></i>
                                        <span id="uploads-trend">+3.1%</span>
                                    </small>
                                </div>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: 65%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="real-time-counter">
                                <i class="fas fa-user-clock text-danger me-2"></i>
                                <span class="fw-bold" id="online-users">2,847</span>
                                <small class="text-muted">users online right now</small>
                                <small class="text-success d-block">
                                    <i class="fas fa-chart-line me-1"></i>
                                    Peak: <span id="peak-users">3,124</span> users
                                </small>
                            </div>
                            <div class="real-time-counter mt-3">
                                <i class="fas fa-file-download text-success me-2"></i>
                                <span class="fw-bold" id="live-download-counter">47</span>
                                <small class="text-muted">downloads in last 60 seconds</small>
                                <div class="download-speed mt-1">
                                    <small class="text-muted">
                                        Speed: <span id="download-speed">0.78/sec</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5 bg-light">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="display-5 fw-bold mb-3">Why Choose Wezo Campus Hub?</h2>
                <p class="lead">Designed specifically for Kenyan students across all education levels</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-book-open fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title mb-3">Comprehensive Library</h4>
                        <p class="card-text">Access thousands of educational resources across JSS, CBC, University, and College levels. All content is peer-reviewed and categorized for easy access.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-robot fa-3x text-success"></i>
                        </div>
                        <h4 class="card-title mb-3">Smart Chat Assistant</h4>
                        <p class="card-text">Our intelligent chatbot helps you find exactly what you need by asking specific questions about your document requirements.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-star fa-3x text-warning"></i>
                        </div>
                        <h4 class="card-title mb-3">Rating System</h4>
                        <p class="card-text">Rate and review documents to help others find quality content. See what others think before downloading.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-shield-alt fa-3x text-danger"></i>
                        </div>
                        <h4 class="card-title mb-3">Secure Platform</h4>
                        <p class="card-text">Your data is protected with advanced security measures. We respect copyright and ensure all content is appropriately licensed.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-mobile-alt fa-3x text-info"></i>
                        </div>
                        <h4 class="card-title mb-3">Mobile Friendly</h4>
                        <p class="card-text">Access your documents anytime, anywhere. Our platform is fully responsive and works perfectly on all devices.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-comments fa-3x text-secondary"></i>
                        </div>
                        <h4 class="card-title mb-3">Community Feedback</h4>
                        <p class="card-text">Engage with other students, share feedback, and build a collaborative learning environment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Documents -->
<section class="py-5">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12">
                <h2 class="display-5 fw-bold mb-3">Featured Documents</h2>
                <p class="lead">Top-rated documents from our community</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php if (empty($featured_docs)): ?>
            <div class="col-md-12 text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h4>No featured documents yet</h4>
                <p>Be the first to upload and get featured!</p>
                <?php if ($auth->isLoggedIn()): ?>
                <a href="upload.php" class="btn btn-primary">Upload Document</a>
                <?php else: ?>
                <a href="register.php" class="btn btn-primary">Join to Upload</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <?php foreach ($featured_docs as $doc): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 document-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-<?php echo get_level_color($doc['education_level']); ?>">
                                    <?php echo $doc['education_level']; ?>
                                </span>
                                <?php if ($doc['category']): ?>
                                <span class="badge bg-secondary"><?php echo $doc['category']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <small class="text-muted"><?php echo format_file_size($doc['file_size']); ?></small>
                            </div>
                        </div>
                        
                        <h5 class="card-title"><?php echo htmlspecialchars($doc['title']); ?></h5>
                        <p class="card-text small text-muted">
                            <?php echo htmlspecialchars(substr($doc['description'] ?? 'No description', 0, 100)); ?>...
                        </p>
                        
                        <div class="mb-3">
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($doc['average_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <small class="ms-2"><?php echo number_format($doc['average_rating'], 1); ?> (<?php echo $doc['total_ratings']; ?>)</small>
                            </div>
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
                    </div>
                    <div class="card-footer bg-transparent border-top">
                        <div class="d-flex justify-content-between">
                            <a href="#" class="btn btn-sm btn-outline-primary">
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
            <?php endif; ?>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12 text-center">
                <a href="search.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-search me-2"></i>Browse All Documents
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Education Systems -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="display-5 fw-bold mb-3">Supporting All Kenyan Education Systems</h2>
                <p class="lead">Comprehensive coverage across all levels</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="system-icon mb-3">
                            <i class="fas fa-school fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Junior Secondary (JSS)</h4>
                        <p class="card-text">Grade 7-9 resources, notes, and past papers</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="system-icon mb-3">
                            <i class="fas fa-graduation-cap fa-3x text-success"></i>
                        </div>
                        <h4 class="card-title">CBC System</h4>
                        <p class="card-text">Competency Based Curriculum materials for all grades</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="system-icon mb-3">
                            <i class="fas fa-university fa-3x text-warning"></i>
                        </div>
                        <h4 class="card-title">University</h4>
                        <p class="card-text">Lecture notes, research papers, and academic resources</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="system-icon mb-3">
                            <i class="fas fa-book-reader fa-3x text-info"></i>
                        </div>
                        <h4 class="card-title">General Education</h4>
                        <p class="card-text">General knowledge and skill development resources</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="display-5 fw-bold mb-3">Ready to Join Our Learning Community?</h2>
                <p class="lead mb-4">Start sharing and accessing educational resources today. Join thousands of students already benefiting from our platform.</p>
            </div>
            <div class="col-lg-4 text-center">
                <?php if ($auth->isLoggedIn()): ?>
                <a href="upload.php" class="btn btn-light btn-lg">
                    <i class="fas fa-upload me-2"></i>Upload Document
                </a>
                <?php else: ?>
                <a href="register.php" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-user-plus me-2"></i>Sign Up Free
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* Insight Section Styles */
.insight-stat-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.insight-stat-card:hover {
    transform: translateY(-5px);
    border-color: #667eea;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15) !important;
}

/* Activity Feed */
.activity-feed {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s;
}

.activity-item:hover {
    background-color: #f8f9ff;
}

.activity-avatar {
    margin-right: 15px;
}

.activity-content {
    flex: 1;
}

.activity-action {
    min-width: 60px;
    text-align: right;
}

/* Live Badge Pulse Animation */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

.pulse {
    animation: pulse 2s infinite;
}

/* Testimonials */
.testimonial {
    background: #f8f9ff;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.star-rating-sm i {
    font-size: 0.8rem;
}

.star-rating-sm i.text-warning {
    color: #ffc107 !important;
}

/* Progress Bar Animation */
.progress-bar {
    transition: width 1.5s ease-in-out;
}

/* Real-time Counter Animation */
@keyframes countUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.real-time-counter {
    animation: countUp 0.5s ease-out;
}

/* Half Star for 4.7 Rating */
.text-warning-half {
    background: linear-gradient(90deg, #ffc107 50%, #dee2e6 50%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Scrollbar Styling */
.activity-feed::-webkit-scrollbar {
    width: 5px;
}

.activity-feed::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.activity-feed::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.activity-feed::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Counting Animation */
.counter-animate {
    animation: countPulse 0.5s ease;
}

@keyframes countPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Mini Counter Animation */
.mini-counter {
    font-size: 0.85rem;
}

.mini-counter span {
    font-weight: 600;
    color: #28a745;
}

/* Real-time Stats */
.real-time-stats {
    font-size: 0.9rem;
}

/* Like Button Animation */
.like-btn {
    transition: all 0.3s ease;
}

.like-btn:hover {
    transform: scale(1.1);
}

.like-btn.liked {
    background-color: #28a745;
    color: white;
}

/* Download Progress Animation */
.download-progress {
    position: relative;
    overflow: hidden;
}

.download-progress::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: progressShine 2s infinite;
}

@keyframes progressShine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Floating Replies Animation */
@keyframes floatUp {
    0% {
        opacity: 0;
        transform: translateY(20px) scale(0.9);
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translateY(-20px) scale(1);
    }
}

.floating-reply {
    position: absolute;
    background: white;
    padding: 8px 15px;
    border-radius: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 1000;
    animation: floatUp 3s ease-in-out forwards;
    font-size: 0.85rem;
}

/* Heart Animation */
@keyframes heartBeat {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.heart-animate {
    animation: heartBeat 0.5s ease;
}

/* Download Counter Animation */
@keyframes downloadPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.download-pulse {
    animation: downloadPulse 0.5s ease;
}
</style>

<script>
// Animated counters
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all counters
    initializeCounters();
    
    // Real-time updates
    startRealTimeUpdates();
    
    // Activity feed updates
    setupActivityFeed();
    
    // Engagement metrics updates
    setupEngagementMetrics();
    
    // Like button functionality
    setupLikeButtons();
});

function initializeCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const target = parseFloat(counter.getAttribute('data-count'));
        const speed = parseInt(counter.getAttribute('data-speed') || '100');
        const increment = target / speed;
        let count = 0;
        
        if (counter.textContent.includes('(')) {
            // For review counter (0 reviews)
            const updateCount = () => {
                if (count < target) {
                    count += increment;
                    counter.textContent = `(${Math.ceil(count).toLocaleString()} reviews)`;
                    setTimeout(updateCount, 20);
                } else {
                    counter.textContent = `(${target.toLocaleString()} reviews)`;
                }
            };
            updateCount();
        } else {
            // For main counters
            const updateCount = () => {
                if (count < target) {
                    count += increment;
                    counter.textContent = target >= 1000 ? 
                        Math.ceil(count).toLocaleString() : 
                        count.toFixed(1);
                    counter.classList.add('counter-animate');
                    setTimeout(() => counter.classList.remove('counter-animate'), 500);
                    setTimeout(updateCount, 20);
                } else {
                    counter.textContent = target >= 1000 ? 
                        target.toLocaleString() : 
                        target.toFixed(1);
                }
            };
            updateCount();
        }
    });
}

function startRealTimeUpdates() {
    // Update mini counters every 10 seconds
    setInterval(() => {
        updateMiniCounters();
        updateOnlineUsers();
        updateDownloadCounter();
    }, 10000);
    
    // Start mini counter updates
    updateMiniCounters();
    updateOnlineUsers();
    updateDownloadCounter();
}

function updateMiniCounters() {
    const counters = {
        'live-users': { min: 150, max: 350, current: 231 },
        'live-docs': { min: 8, max: 20, current: 12 },
        'live-downloads': { min: 500, max: 1000, current: 723 },
        'live-ratings': { min: 30, max: 60, current: 47 }
    };
    
    Object.keys(counters).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            const counter = counters[id];
            const change = Math.floor(Math.random() * 41) - 20; // -20 to +20
            counter.current = Math.max(counter.min, Math.min(counter.max, counter.current + change));
            element.textContent = `+${counter.current}`;
            element.classList.add('counter-animate');
            setTimeout(() => element.classList.remove('counter-animate'), 500);
        }
    });
}

function updateOnlineUsers() {
    const onlineUsers = document.getElementById('online-users');
    const peakUsers = document.getElementById('peak-users');
    
    if (onlineUsers) {
        const current = parseInt(onlineUsers.textContent.replace(/,/g, ''));
        const change = Math.floor(Math.random() * 41) - 20; // -20 to +20
        const newValue = Math.max(2800, current + change);
        onlineUsers.textContent = newValue.toLocaleString();
        
        // Update peak users
        if (peakUsers) {
            const peak = parseInt(peakUsers.textContent.replace(/,/g, ''));
            if (newValue > peak) {
                peakUsers.textContent = newValue.toLocaleString();
            }
        }
    }
}

function updateDownloadCounter() {
    const downloadCounter = document.getElementById('live-download-counter');
    const downloadSpeed = document.getElementById('download-speed');
    
    if (downloadCounter) {
        const current = parseInt(downloadCounter.textContent);
        const newDownloads = Math.floor(Math.random() * 8) + 1; // 1-8 downloads
        const newTotal = current + newDownloads;
        downloadCounter.textContent = newTotal;
        downloadCounter.classList.add('download-pulse');
        
        // Update download speed
        if (downloadSpeed) {
            const speed = (Math.random() * 0.5 + 0.5).toFixed(2); // 0.50-1.00/sec
            downloadSpeed.textContent = `${speed}/sec`;
        }
        
        setTimeout(() => {
            downloadCounter.classList.remove('download-pulse');
            
            // Reset counter after 60 seconds
            if (newTotal > 100) {
                downloadCounter.textContent = Math.floor(Math.random() * 30) + 20; // 20-50
            }
        }, 500);
    }
}

function setupActivityFeed() {
    let countdown = 8;
    const countdownElement = document.getElementById('countdown');
    const lastUpdatedElement = document.getElementById('last-updated');
    
    // Countdown timer
    const countdownInterval = setInterval(() => {
        countdown--;
        if (countdownElement) countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            updateActivityFeed();
            countdown = 8;
        }
    }, 1000);
    
    // Update last updated time
    setInterval(() => {
        if (lastUpdatedElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            lastUpdatedElement.textContent = timeString;
        }
    }, 60000);
}

function updateActivityFeed() {
    const activityFeed = document.getElementById('activity-feed');
    if (!activityFeed) return;
    
    const activities = [
        {
            name: ["Brian K.", "Sarah M.", "David W.", "Grace N.", "Michael L.", "Linda J.", "Peter W.", "Susan A."][Math.floor(Math.random() * 8)],
            school: ["University of Nairobi", "JKUAT", "Moi University", "Kenyatta University", "Strathmore", "MOI Girls", "Technical University", "Maseno"][Math.floor(Math.random() * 8)],
            action: ["downloaded", "uploaded", "rated 5 stars", "commented", "shared", "reviewed"][Math.floor(Math.random() * 6)],
            text: ["Computer Science Notes", "Mathematics Past Papers", "Business Studies Guide", "Chemistry Lab Reports", "Physics Formulas", "Biology Diagrams", "History Notes", "Geography Maps"][Math.floor(Math.random() * 8)],
            time: "Just now",
            type: ["download", "upload", "rating", "comment"][Math.floor(Math.random() * 4)],
            avatar: Math.floor(Math.random() * 70) + 1
        }
    ];
    
    const newActivity = activities[0];
    
    let actionBadge = '';
    if (newActivity.type === 'comment') {
        const replies = Math.floor(Math.random() * 10) + 1;
        actionBadge = `<span class="badge bg-info"><i class="fas fa-reply me-1"></i> ${replies}</span>`;
    } else if (newActivity.type === 'rating') {
        actionBadge = `<div class="star-rating-sm">
            <i class="fas fa-star text-warning"></i>
            <i class="fas fa-star text-warning"></i>
            <i class="fas fa-star text-warning"></i>
            <i class="fas fa-star text-warning"></i>
            <i class="fas fa-star text-warning"></i>
        </div>`;
    } else if (newActivity.type === 'upload') {
        const likes = Math.floor(Math.random() * 50) + 10;
        actionBadge = `<span class="text-success"><i class="fas fa-heart"></i> ${likes}</span>`;
    } else {
        actionBadge = '<span class="badge bg-primary">New</span>';
    }
    
    const newItem = document.createElement('div');
    newItem.className = 'activity-item';
    newItem.innerHTML = `
        <div class="activity-avatar">
            <img src="https://i.pravatar.cc/40?img=${newActivity.avatar}" alt="User" class="rounded-circle">
        </div>
        <div class="activity-content">
            <strong>${newActivity.name}</strong> from <span class="text-primary">${newActivity.school}</span>
            <span class="text-info">${newActivity.action}</span> "${newActivity.text}"
            <small class="text-muted ms-2">${newActivity.time}</small>
        </div>
        <div class="activity-action">
            ${actionBadge}
        </div>
    `;
    
    // Insert at top with animation
    newItem.style.opacity = '0';
    newItem.style.transform = 'translateY(-20px)';
    activityFeed.insertBefore(newItem, activityFeed.firstChild);
    
    // Animate in
    setTimeout(() => {
        newItem.style.transition = 'all 0.3s ease';
        newItem.style.opacity = '1';
        newItem.style.transform = 'translateY(0)';
    }, 10);
    
    // Remove oldest if more than 4
    if (activityFeed.children.length > 4) {
        const lastItem = activityFeed.lastElementChild;
        lastItem.style.transition = 'all 0.3s ease';
        lastItem.style.opacity = '0';
        lastItem.style.transform = 'translateY(20px)';
        setTimeout(() => {
            if (activityFeed.contains(lastItem)) {
                activityFeed.removeChild(lastItem);
            }
        }, 300);
    }
    
    // Add floating reply animation randomly
    if (Math.random() > 0.7 && newActivity.type === 'comment') {
        createFloatingReply(newActivity.name);
    }
}

function createFloatingReply(username) {
    const floatingReply = document.createElement('div');
    floatingReply.className = 'floating-reply';
    floatingReply.textContent = `${username}: "Thanks!"`;
    floatingReply.style.left = `${Math.random() * 80 + 10}%`;
    floatingReply.style.top = `${Math.random() * 50 + 25}%`;
    
    document.querySelector('.activity-feed').appendChild(floatingReply);
    
    setTimeout(() => {
        floatingReply.remove();
    }, 3000);
}

function setupEngagementMetrics() {
    // Update engagement metrics every 30 seconds
    setInterval(() => {
        updateTodayMetrics();
        updateTrends();
    }, 30000);
    
    // Initial update
    updateTodayMetrics();
    updateTrends();
}

function updateTodayMetrics() {
    const metrics = {
        'today-downloads-count': { base: 52847, min: 1000, max: 2000 },
        'active-discussions': { base: 3284, min: 100, max: 300 },
        'ai-assistance': { base: 12591, min: 500, max: 1500 },
        'today-uploads': { base: 856, min: 50, max: 150 }
    };
    
    Object.keys(metrics).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            const metric = metrics[id];
            const change = Math.floor(Math.random() * (metric.max - metric.min + 1)) + metric.min;
            const current = parseInt(element.textContent.replace(/,/g, ''));
            const newValue = current + change;
            element.textContent = newValue.toLocaleString();
            
            // Animate progress bar
            const progressBar = element.closest('.engagement-metric').querySelector('.progress-bar');
            if (progressBar) {
                const currentWidth = parseFloat(progressBar.style.width);
                const newWidth = Math.min(100, currentWidth + (Math.random() * 5));
                progressBar.style.width = `${newWidth}%`;
            }
        }
    });
}

function updateTrends() {
    const trends = {
        'downloads-trend': ['+5.2%', '+4.8%', '+5.5%', '+6.1%', '+4.9%'],
        'discussions-trend': ['+2.8%', '+3.1%', '+2.5%', '+3.4%', '+2.9%'],
        'ai-trend': ['+7.5%', '+8.2%', '+7.1%', '+8.5%', '+7.8%'],
        'uploads-trend': ['+3.1%', '+2.8%', '+3.5%', '+2.9%', '+3.2%']
    };
    
    Object.keys(trends).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            const trendOptions = trends[id];
            const randomTrend = trendOptions[Math.floor(Math.random() * trendOptions.length)];
            element.textContent = randomTrend;
        }
    });
}

function setupLikeButtons() {
    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', function() {
            const countElement = this.querySelector('.like-number');
            let count = parseInt(countElement.textContent);
            
            if (this.classList.contains('liked')) {
                count--;
                this.classList.remove('liked');
                this.innerHTML = `<i class="fas fa-thumbs-up me-1"></i> ${count}`;
            } else {
                count++;
                this.classList.add('liked');
                this.innerHTML = `<i class="fas fa-thumbs-up me-1"></i> ${count}`;
            }
            
            // Animate heart
            const heart = this.querySelector('i');
            heart.classList.add('heart-animate');
            setTimeout(() => heart.classList.remove('heart-animate'), 500);
        });
    });
}
</script>

<?php 
// Helper functions
function get_level_color($level) {
    $colors = [
        'JSS' => 'primary',
        'CBC' => 'success',
        'University' => 'warning',
        'College' => 'info',
        'General' => 'secondary'
    ];
    return $colors[$level] ?? 'secondary';
}

function format_file_size($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

include 'includes/footer.php'; 
?>