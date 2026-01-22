<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = "My Profile - Wezo Campus Hub";

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Use Auth class methods instead of direct database connections where possible
$currentUser = $auth->getCurrentUser();

// For other database operations, use a separate connection
// This prevents conflicts with Auth class connection
$profile_conn = getDBConnection();

// Check if we got a valid connection
if (!$profile_conn || $profile_conn->connect_error) {
    die("Profile database connection failed. Please try again later.");
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        
        // Check if username/email already exists (excluding current user)
        $checkStmt = $profile_conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkStmt->bind_param("ssi", $username, $email, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = "Username or email already exists!";
        } else {
            // Update profile
            $updateStmt = $profile_conn->prepare("UPDATE users SET username = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->bind_param("ssi", $username, $email, $user_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['username'] = $username;
                $success = "Profile updated successfully!";
            } else {
                $error = "Failed to update profile: " . $profile_conn->error;
            }
        }
    }
    
    if (isset($_POST['update_university_info'])) {
        $university_id = isset($_POST['university_id']) ? (int)$_POST['university_id'] : NULL;
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : NULL;
        $university_text = sanitize_input($_POST['university_text'] ?? '');
        $course_text = sanitize_input($_POST['course_text'] ?? '');
        $registration_year = sanitize_input($_POST['registration_year'] ?? '');
        
        // Update university info
        $updateStmt = $profile_conn->prepare("UPDATE users SET university_id = ?, course_id = ?, university_text = ?, course_text = ?, registration_year = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->bind_param("iissii", $university_id, $course_id, $university_text, $course_text, $registration_year, $user_id);
        
        if ($updateStmt->execute()) {
            $success = "University information updated successfully!";
        } else {
            $error = "Failed to update university information: " . $profile_conn->error;
        }
    }
    
    if (isset($_POST['update_profile_details'])) {
        $bio = sanitize_input($_POST['bio'] ?? '');
        $phone_number = sanitize_input($_POST['phone_number'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? '');
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : NULL;
        $address = sanitize_input($_POST['address'] ?? '');
        $website = sanitize_input($_POST['website'] ?? '');
        $social_media_links = sanitize_input($_POST['social_media_links'] ?? '');
        
        // Check if profile exists
        $checkStmt = $profile_conn->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $profileExists = $checkResult->num_rows > 0;
        
        if ($profileExists) {
            // Update existing profile
            $updateStmt = $profile_conn->prepare("UPDATE user_profiles SET bio = ?, phone_number = ?, gender = ?, date_of_birth = ?, address = ?, website = ?, social_media_links = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $updateStmt->bind_param("sssssssi", $bio, $phone_number, $gender, $date_of_birth, $address, $website, $social_media_links, $user_id);
        } else {
            // Insert new profile
            $updateStmt = $profile_conn->prepare("INSERT INTO user_profiles (user_id, bio, phone_number, gender, date_of_birth, address, website, social_media_links) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $updateStmt->bind_param("isssssss", $user_id, $bio, $phone_number, $gender, $date_of_birth, $address, $website, $social_media_links);
        }
        
        if ($updateStmt->execute()) {
            $success = "Profile details updated successfully!";
        } else {
            $error = "Failed to update profile details: " . $profile_conn->error;
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if ($new_password !== $confirm_password) {
            $error = "New passwords don't match!";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            // Get current password hash
            $stmt = $profile_conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            
            if (password_verify($current_password, $userData['password'])) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $profile_conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($updateStmt->execute()) {
                    $success = "Password changed successfully!";
                } else {
                    $error = "Failed to change password: " . $profile_conn->error;
                }
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
    
    if (isset($_POST['update_profile_photo'])) {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_photo']['type'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array($file_type, $allowed_types)) {
                if ($_FILES['profile_photo']['size'] <= $max_size) {
                    $upload_dir = 'uploads/profile_photos/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                        // Update database with relative path
                        $relative_path = 'uploads/profile_photos/' . $new_filename;
                        $updateStmt = $profile_conn->prepare("UPDATE users SET profile_photo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $updateStmt->bind_param("si", $relative_path, $user_id);
                        
                        if ($updateStmt->execute()) {
                            $success = "Profile photo updated successfully!";
                        } else {
                            $error = "Failed to update profile photo in database: " . $profile_conn->error;
                        }
                    } else {
                        $error = "Failed to upload profile photo!";
                    }
                } else {
                    $error = "File size too large. Maximum size is 2MB.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            }
        } else {
            $error = "Please select a valid profile photo.";
        }
    }
    
    if (isset($_POST['add_university'])) {
        $university_id = isset($_POST['new_university_id']) ? (int)$_POST['new_university_id'] : NULL;
        $course_id = isset($_POST['new_course_id']) ? (int)$_POST['new_course_id'] : NULL;
        $registration_year = sanitize_input($_POST['new_registration_year'] ?? '');
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        
        if ($university_id) {
            // Insert into user_universities
            $insertStmt = $profile_conn->prepare("INSERT INTO user_universities (user_id, university_id, course_id, registration_year, is_current) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("iiisi", $user_id, $university_id, $course_id, $registration_year, $is_current);
            
            if ($insertStmt->execute()) {
                $success = "University added successfully!";
            } else {
                $error = "Failed to add university: " . $profile_conn->error;
            }
        } else {
            $error = "Please select a university.";
        }
    }
}

// Get user data - using profile connection
$stmt = $profile_conn->prepare("
    SELECT u.id, u.username, u.email, u.profile_photo, u.is_verified, u.is_admin, u.is_active, 
           u.login_attempts, u.last_login_attempt, u.created_at, u.updated_at,
           u.university_id, u.course_id, u.university_text, u.course_text, u.registration_year,
           uni.name as university_name, c.name as course_name
    FROM users u
    LEFT JOIN universities uni ON u.university_id = uni.id
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user profile details
$profileStmt = $profile_conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
$profile = $profileResult->fetch_assoc();

// Get user universities (for multiple universities support)
$universitiesStmt = $profile_conn->prepare("
    SELECT uu.*, uni.name as university_name, c.name as course_name
    FROM user_universities uu
    LEFT JOIN universities uni ON uu.university_id = uni.id
    LEFT JOIN courses c ON uu.course_id = c.id
    WHERE uu.user_id = ?
    ORDER BY uu.is_current DESC, uu.created_at DESC
");
$universitiesStmt->bind_param("i", $user_id);
$universitiesStmt->execute();
$user_universities_result = $universitiesStmt->get_result();

// Store universities count
$user_universities_count = $user_universities_result->num_rows;

// Get list of all universities for dropdown
$allUniversities = $profile_conn->query("SELECT id, name, short_name, country, city FROM universities ORDER BY name");
// Reset pointer for second use
$allUniversities2 = $profile_conn->query("SELECT id, name, short_name, country, city FROM universities ORDER BY name");

// Get list of all courses for dropdown
$allCourses = $profile_conn->query("SELECT id, name, code FROM courses ORDER BY name");
// Reset pointer for second use
$allCourses2 = $profile_conn->query("SELECT id, name, code FROM courses ORDER BY name");

// Get user statistics - REAL DATA
$stats = [];

// Total documents uploaded
$docStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM documents WHERE user_id = ?");
$docStmt->bind_param("i", $user_id);
$docStmt->execute();
$docResult = $docStmt->get_result();
$docData = $docResult->fetch_assoc();
$stats['total_documents'] = $docData['total'] ?: 0;

// Total downloads (from download_logs)
$downloadStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM download_logs WHERE user_id = ?");
$downloadStmt->bind_param("i", $user_id);
$downloadStmt->execute();
$downloadResult = $downloadStmt->get_result();
$downloadData = $downloadResult->fetch_assoc();
$stats['total_downloads'] = $downloadData['total'] ?: 0;

// If download_logs is empty, show document downloads as alternative
if ($stats['total_downloads'] == 0) {
    $altDownloadStmt = $profile_conn->prepare("SELECT SUM(download_count) as total FROM documents WHERE user_id = ?");
    $altDownloadStmt->bind_param("i", $user_id);
    $altDownloadStmt->execute();
    $altDownloadResult = $altDownloadStmt->get_result();
    $altDownloadData = $altDownloadResult->fetch_assoc();
    $stats['total_downloads'] = $altDownloadData['total'] ?: 0;
}

// Total document views
$viewStmt = $profile_conn->prepare("SELECT SUM(view_count) as total FROM documents WHERE user_id = ?");
$viewStmt->bind_param("i", $user_id);
$viewStmt->execute();
$viewResult = $viewStmt->get_result();
$viewData = $viewResult->fetch_assoc();
$stats['total_views'] = $viewData['total'] ?: 0;

// Total ratings given
$ratingStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM ratings WHERE user_id = ?");
$ratingStmt->bind_param("i", $user_id);
$ratingStmt->execute();
$ratingResult = $ratingStmt->get_result();
$ratingData = $ratingResult->fetch_assoc();
$stats['total_ratings_given'] = $ratingData['total'] ?: 0;

// Average rating of user's documents
$avgRatingStmt = $profile_conn->prepare("SELECT AVG(average_rating) as avg FROM documents WHERE user_id = ? AND total_ratings > 0");
$avgRatingStmt->bind_param("i", $user_id);
$avgRatingStmt->execute();
$avgRatingResult = $avgRatingStmt->get_result();
$avgRatingData = $avgRatingResult->fetch_assoc();
$stats['avg_document_rating'] = $avgRatingData['avg'] ? number_format($avgRatingData['avg'], 1) : '0.0';

// Total saved documents
$savedStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM saved_documents WHERE user_id = ?");
$savedStmt->bind_param("i", $user_id);
$savedStmt->execute();
$savedResult = $savedStmt->get_result();
$savedData = $savedResult->fetch_assoc();
$stats['total_saved'] = $savedData['total'] ?: 0;

// Total feedback/comments given
$feedbackStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM feedbacks WHERE user_id = ?");
$feedbackStmt->bind_param("i", $user_id);
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result();
$feedbackData = $feedbackResult->fetch_assoc();
$stats['total_feedback'] = $feedbackData['total'] ?: 0;

// Get today's activity
$today = date('Y-m-d');
$todayDownloadsStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM download_logs WHERE user_id = ? AND DATE(downloaded_at) = ?");
$todayDownloadsStmt->bind_param("is", $user_id, $today);
$todayDownloadsStmt->execute();
$todayDownloadsResult = $todayDownloadsStmt->get_result();
$todayDownloadsData = $todayDownloadsResult->fetch_assoc();
$stats['today_downloads'] = $todayDownloadsData['total'] ?: 0;

// This week's activity
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekDownloadsStmt = $profile_conn->prepare("SELECT COUNT(*) as total FROM download_logs WHERE user_id = ? AND DATE(downloaded_at) >= ?");
$weekDownloadsStmt->bind_param("is", $user_id, $weekStart);
$weekDownloadsStmt->execute();
$weekDownloadsResult = $weekDownloadsStmt->get_result();
$weekDownloadsData = $weekDownloadsResult->fetch_assoc();
$stats['week_downloads'] = $weekDownloadsData['total'] ?: 0;

// Get recent documents
$recentDocsStmt = $profile_conn->prepare("
    SELECT id, title, filename, download_count, view_count, average_rating, 
           upload_date, education_level, category 
    FROM documents 
    WHERE user_id = ? 
    ORDER BY upload_date DESC 
    LIMIT 5
");
$recentDocsStmt->bind_param("i", $user_id);
$recentDocsStmt->execute();
$recentDocsResult = $recentDocsStmt->get_result();

// Get recent downloads
$recentDownloadsStmt = $profile_conn->prepare("
    SELECT d.id, d.title, d.filename, dl.downloaded_at, d.education_level 
    FROM download_logs dl 
    JOIN documents d ON dl.document_id = d.id 
    WHERE dl.user_id = ? 
    ORDER BY dl.downloaded_at DESC 
    LIMIT 5
");
$recentDownloadsStmt->bind_param("i", $user_id);
$recentDownloadsStmt->execute();
$recentDownloadsResult = $recentDownloadsStmt->get_result();
$recentDownloadsCount = $recentDownloadsResult->num_rows;

// If no downloads in download_logs, show documents with most downloads as alternative
if ($recentDownloadsCount == 0) {
    $recentDownloadsStmt = $profile_conn->prepare("
        SELECT id, title, filename, upload_date as downloaded_at, education_level 
        FROM documents 
        WHERE user_id = ? 
        ORDER BY download_count DESC 
        LIMIT 5
    ");
    $recentDownloadsStmt->bind_param("i", $user_id);
    $recentDownloadsStmt->execute();
    $recentDownloadsResult = $recentDownloadsStmt->get_result();
    $recentDownloadsCount = $recentDownloadsResult->num_rows;
}

// Helper function for input sanitization
function sanitize_input($data) {
    if (empty($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to get profile photo URL - FIXED VERSION
function getProfilePhoto($user, $default_path = 'assets/images/default-avatar.png') {
    // Always return default first as fallback
    $default_url = SITE_URL . '/' . $default_path;
    
    if (empty($user) || empty($user['profile_photo'])) {
        return $default_url;
    }
    
    $photo = $user['profile_photo'];
    
    // If it's already a full URL, return as-is
    if (filter_var($photo, FILTER_VALIDATE_URL)) {
        return $photo;
    }
    
    // Clean the photo path
    $photo = ltrim($photo, './');
    
    // Check common path patterns
    $possible_paths = [
        $photo, // original path
        'uploads/profile_photos/' . basename($photo),
        'uploads/' . basename($photo),
        'profile_photos/' . basename($photo)
    ];
    
    // Check if file exists in any of these paths
    foreach ($possible_paths as $test_path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($test_path, '/');
        
        // Debug: Check if file exists
        if (file_exists($full_path)) {
            // Return the URL for this path
            return SITE_URL . '/' . $test_path;
        }
    }
    
    // If no file found, check if it's a direct uploads path
    if (strpos($photo, 'uploads/') === 0) {
        // It's already a relative uploads path
        return SITE_URL . '/' . $photo;
    }
    
    // Last attempt: try to construct URL directly
    $direct_url = SITE_URL . '/uploads/profile_photos/' . basename($photo);
    
    // Return default if nothing works
    return $default_url;
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

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Welcome Message with Real Profile Photo -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <img src="<?php echo getProfilePhoto($user); ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle border border-3 border-white"
                                 style="width: 80px; height: 80px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                        </div>
                        <div>
                            <h3 class="mb-1"><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($user['username']); ?>!</h3>
                            <p class="mb-0">
                                Welcome to your dashboard. You've been a member since 
                                <?php echo date('F j, Y', strtotime($user['created_at'])); ?>.
                                <?php if ($stats['total_documents'] > 0): ?>
                                    You've uploaded <strong><?php echo $stats['total_documents']; ?></strong> document<?php echo $stats['total_documents'] > 1 ? 's' : ''; ?>.
                                <?php else: ?>
                                    Start by uploading your first document!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Profile Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <!-- Profile Photo -->
                        <div class="me-4 position-relative">
                            <img src="<?php echo getProfilePhoto($user); ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle border" 
                                 style="width: 120px; height: 120px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                            <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                    style="width: 36px; height: 36px;"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#profilePhotoModal">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        
                        <!-- User Info -->
                        <div class="flex-grow-1">
                            <h2 class="mb-1">
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($user['is_verified']): ?>
                                    <span class="badge bg-success" data-bs-toggle="tooltip" title="Verified Account">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                                <?php if ($user['is_admin']): ?>
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" title="Administrator">
                                        <i class="fas fa-crown"></i> Admin
                                    </span>
                                <?php endif; ?>
                            </h2>
                            
                            <p class="text-muted mb-1">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            
                            <?php if (!empty($user['university_name']) || !empty($user['university_text'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-university me-1"></i> 
                                <?php echo !empty($user['university_name']) ? htmlspecialchars($user['university_name']) : htmlspecialchars($user['university_text']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['course_name']) || !empty($user['course_text'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-graduation-cap me-1"></i> 
                                <?php echo !empty($user['course_name']) ? htmlspecialchars($user['course_name']) : htmlspecialchars($user['course_text']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <p class="text-muted small mb-0">
                                Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="text-end">
                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <i class="fas fa-<?php echo $user['is_active'] ? 'check' : 'times'; ?>-circle me-1"></i>
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards - Enhanced with Real Data -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-upload fa-3x text-primary mb-2"></i>
                    <h3 class="card-title"><?php echo $stats['total_documents']; ?></h3>
                    <p class="card-text">Documents Uploaded</p>
                    <?php if ($stats['total_documents'] == 0): ?>
                    <small class="text-muted">Start sharing knowledge!</small>
                    <?php elseif ($stats['total_documents'] < 3): ?>
                    <small class="text-success">Great start! Keep going!</small>
                    <?php else: ?>
                    <small class="text-success">Excellent contribution!</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-success h-100">
                <div class="card-body text-center">
                    <i class="fas fa-download fa-3x text-success mb-2"></i>
                    <h3 class="card-title"><?php echo number_format($stats['total_downloads']); ?></h3>
                    <p class="card-text">Total Downloads</p>
                    <?php if ($stats['total_downloads'] == 0): ?>
                    <small class="text-muted">Your content awaits discovery</small>
                    <?php elseif ($stats['today_downloads'] > 0): ?>
                    <small class="text-success"><?php echo $stats['today_downloads']; ?> downloads today!</small>
                    <?php else: ?>
                    <small class="text-info"><?php echo $stats['week_downloads']; ?> this week</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body text-center">
                    <i class="fas fa-eye fa-3x text-warning mb-2"></i>
                    <h3 class="card-title"><?php echo number_format($stats['total_views']); ?></h3>
                    <p class="card-text">Total Views</p>
                    <?php if ($stats['total_views'] == 0): ?>
                    <small class="text-muted">No views yet</small>
                    <?php elseif ($stats['total_views'] < 100): ?>
                    <small class="text-info">Growing visibility!</small>
                    <?php else: ?>
                    <small class="text-success">Highly viewed content!</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-info h-100">
                <div class="card-body text-center">
                    <i class="fas fa-star fa-3x text-info mb-2"></i>
                    <h3 class="card-title"><?php echo $stats['avg_document_rating']; ?>/5.0</h3>
                    <p class="card-text">Avg Rating</p>
                    <?php if ($stats['avg_document_rating'] == '0.0'): ?>
                    <small class="text-muted">No ratings yet</small>
                    <?php elseif ($stats['avg_document_rating'] >= 4.0): ?>
                    <small class="text-success">Excellent quality!</small>
                    <?php elseif ($stats['avg_document_rating'] >= 3.0): ?>
                    <small class="text-info">Good ratings!</small>
                    <?php else: ?>
                    <small class="text-warning">Room for improvement</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Additional Stats -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-secondary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-bookmark fa-3x text-secondary mb-2"></i>
                    <h3 class="card-title"><?php echo $stats['total_saved']; ?></h3>
                    <p class="card-text">Saved Documents</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-dark h-100">
                <div class="card-body text-center">
                    <i class="fas fa-comment fa-3x text-dark mb-2"></i>
                    <h3 class="card-title"><?php echo $stats['total_feedback']; ?></h3>
                    <p class="card-text">Comments Given</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-danger h-100">
                <div class="card-body text-center">
                    <i class="fas fa-star-half-alt fa-3x text-danger mb-2"></i>
                    <h3 class="card-title"><?php echo $stats['total_ratings_given']; ?></h3>
                    <p class="card-text">Ratings Given</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-purple h-100" style="border-color: #6f42c1;">
                <div class="card-body text-center">
                    <i class="fas fa-user-graduate fa-3x mb-2" style="color: #6f42c1;"></i>
                    <h3 class="card-title"><?php echo $user_universities_count; ?></h3>
                    <p class="card-text">Universities</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Profile Settings -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                <i class="fas fa-user-edit me-1"></i> Basic Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="university-tab" data-bs-toggle="tab" data-bs-target="#university" type="button" role="tab">
                                <i class="fas fa-university me-1"></i> University
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                <i class="fas fa-key me-1"></i> Password
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="tab-content" id="profileTabsContent">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <h6 class="mb-3"><i class="fas fa-user me-2"></i>Basic Information</h6>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Basic Information
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- University Tab -->
                        <div class="tab-pane fade" id="university" role="tabpanel">
                            <h6 class="mb-3"><i class="fas fa-university me-2"></i>University Information</h6>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="university_id" class="form-label">Select University</label>
                                        <select class="form-control" id="university_id" name="university_id">
                                            <option value="">-- Select University --</option>
                                            <?php if ($allUniversities): ?>
                                                <?php while ($uni = $allUniversities->fetch_assoc()): ?>
                                                    <option value="<?php echo $uni['id']; ?>" 
                                                        <?php echo ($user['university_id'] == $uni['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($uni['name']); ?> 
                                                        (<?php echo htmlspecialchars($uni['short_name']); ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="university_text" class="form-label">Or Enter University Name</label>
                                        <input type="text" class="form-control" id="university_text" name="university_text" 
                                               value="<?php echo htmlspecialchars($user['university_text'] ?? ''); ?>" 
                                               placeholder="Enter if not in list">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="course_id" class="form-label">Select Course</label>
                                        <select class="form-control" id="course_id" name="course_id">
                                            <option value="">-- Select Course --</option>
                                            <?php if ($allCourses): ?>
                                                <?php while ($course = $allCourses->fetch_assoc()): ?>
                                                    <option value="<?php echo $course['id']; ?>" 
                                                        <?php echo ($user['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course['name']); ?> 
                                                        (<?php echo htmlspecialchars($course['code']); ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="course_text" class="form-label">Or Enter Course Name</label>
                                        <input type="text" class="form-control" id="course_text" name="course_text" 
                                               value="<?php echo htmlspecialchars($user['course_text'] ?? ''); ?>" 
                                               placeholder="Enter if not in list">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="registration_year" class="form-label">Registration Year</label>
                                        <input type="number" class="form-control" id="registration_year" name="registration_year" 
                                               min="1900" max="<?php echo date('Y'); ?>" 
                                               value="<?php echo htmlspecialchars($user['registration_year'] ?? ''); ?>"
                                               placeholder="e.g., 2022">
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" name="update_university_info" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update University Information
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- Add Another University Section -->
                            <div class="mt-4 border-top pt-3">
                                <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Add Another University</h6>
                                <form method="POST" action="">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="new_university_id" class="form-label">Select University *</label>
                                            <select class="form-control" id="new_university_id" name="new_university_id" required>
                                                <option value="">-- Select University --</option>
                                                <?php if ($allUniversities2): ?>
                                                    <?php while ($uni = $allUniversities2->fetch_assoc()): ?>
                                                        <option value="<?php echo $uni['id']; ?>">
                                                            <?php echo htmlspecialchars($uni['name']); ?> 
                                                            (<?php echo htmlspecialchars($uni['short_name']); ?>)
                                                        </option>
                                                    <?php endwhile; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_course_id" class="form-label">Select Course</label>
                                            <select class="form-control" id="new_course_id" name="new_course_id">
                                                <option value="">-- Select Course --</option>
                                                <?php if ($allCourses2): ?>
                                                    <?php while ($course = $allCourses2->fetch_assoc()): ?>
                                                        <option value="<?php echo $course['id']; ?>">
                                                            <?php echo htmlspecialchars($course['name']); ?> 
                                                            (<?php echo htmlspecialchars($course['code']); ?>)
                                                        </option>
                                                    <?php endwhile; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_registration_year" class="form-label">Registration Year</label>
                                            <input type="number" class="form-control" id="new_registration_year" name="new_registration_year" 
                                                   min="1900" max="<?php echo date('Y'); ?>" 
                                                   placeholder="e.g., 2022">
                                        </div>
                                        
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_current" name="is_current" value="1" checked>
                                                <label class="form-check-label" for="is_current">
                                                    Set as current university
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" name="add_university" class="btn btn-success">
                                                <i class="fas fa-plus me-1"></i>Add University
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Multiple Universities Section -->
                            <?php if ($user_universities_count > 0): ?>
                            <div class="mt-4 border-top pt-3">
                                <h6 class="mb-3"><i class="fas fa-list me-2"></i>Your Universities</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>University</th>
                                                <th>Course</th>
                                                <th>Year</th>
                                                <th>Status</th>
                                                <th>Added</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user_uni = $user_universities_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user_uni['university_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($user_uni['course_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($user_uni['registration_year'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user_uni['is_current'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $user_uni['is_current'] ? 'Current' : 'Previous'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($user_uni['created_at'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Details Tab -->
                        <div class="tab-pane fade" id="details" role="tabpanel">
                            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Profile Details</h6>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="bio" class="form-label">Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="3" 
                                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               value="<?php echo htmlspecialchars($profile['phone_number'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-control" id="gender" name="gender">
                                            <option value="">-- Select Gender --</option>
                                            <option value="Male" <?php echo (isset($profile['gender']) && $profile['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo (isset($profile['gender']) && $profile['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo (isset($profile['gender']) && $profile['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" 
                                               value="<?php echo htmlspecialchars($profile['website'] ?? ''); ?>"
                                               placeholder="https://example.com">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="social_media_links" class="form-label">Social Media Links</label>
                                        <input type="text" class="form-control" id="social_media_links" name="social_media_links" 
                                               value="<?php echo htmlspecialchars($profile['social_media_links'] ?? ''); ?>"
                                               placeholder="Facebook, Twitter, LinkedIn URLs">
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" name="update_profile_details" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Profile Details
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Password Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <h6 class="mb-3"><i class="fas fa-key me-2"></i>Change Password</h6>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="fas fa-key me-1"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="border-top pt-3 mt-3">
                        <h6><i class="fas fa-info-circle me-2"></i>Account Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Account Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Verification:</strong></td>
                                        <td>
                                            <?php if ($user['is_verified']): ?>
                                                <span class="text-success">Verified</span>
                                            <?php else: ?>
                                                <span class="text-warning">Not Verified</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Member Since:</strong></td>
                                        <td><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Last Updated:</strong></td>
                                        <td><?php echo date('F j, Y H:i', strtotime($user['updated_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Login Attempts:</strong></td>
                                        <td><?php echo $user['login_attempts']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Last Login Attempt:</strong></td>
                                        <td>
                                            <?php echo $user['last_login_attempt'] 
                                                ? date('F j, Y H:i', strtotime($user['last_login_attempt'])) 
                                                : 'Never'; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Activity -->
        <div class="col-lg-4 mb-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header bg-purple text-white" style="background-color: #6f42c1;">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="upload.php" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Upload Document
                        </a>
                        <a href="documents.php" class="btn btn-success">
                            <i class="fas fa-folder me-2"></i>My Documents
                        </a>
                        <a href="search.php" class="btn btn-info text-white">
                            <i class="fas fa-search me-2"></i>Search Documents
                        </a>
                        <a href="timetable.php" class="btn btn-warning">
                            <i class="fas fa-calendar-alt me-2"></i>My Timetable
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Documents -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Documents</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentDocsResult->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($doc = $recentDocsResult->fetch_assoc()): ?>
                        <a href="document-view.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($doc['title']); ?></h6>
                                <small><?php echo date('M j', strtotime($doc['upload_date'])); ?></small>
                            </div>
                            <p class="mb-1 small text-muted">
                                <span class="badge bg-secondary"><?php echo $doc['category'] ?? 'General'; ?></span>
                                <span class="badge bg-light text-dark"><?php echo $doc['education_level']; ?></span>
                            </p>
                            <div class="d-flex justify-content-between">
                                <small><i class="fas fa-download me-1"></i><?php echo $doc['download_count']; ?></small>
                                <small><i class="fas fa-eye me-1"></i><?php echo $doc['view_count']; ?></small>
                                <small><i class="fas fa-star me-1 text-warning"></i><?php echo number_format($doc['average_rating'], 1); ?></small>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file fa-3x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No documents uploaded yet</p>
                        <a href="upload.php" class="btn btn-sm btn-primary mt-2">
                            <i class="fas fa-upload me-1"></i>Upload First Document
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Downloads -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-download me-2"></i>Recent Downloads</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentDownloadsCount > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($download = $recentDownloadsResult->fetch_assoc()): ?>
                        <a href="document-view.php?id=<?php echo $download['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($download['title']); ?></h6>
                                <small><?php echo date('M j', strtotime($download['downloaded_at'])); ?></small>
                            </div>
                            <p class="mb-0 small text-muted">
                                <span class="badge bg-light text-dark"><?php echo $download['education_level']; ?></span>
                            </p>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-download fa-3x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No downloads yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Photo Modal -->
<div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-labelledby="profilePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="profilePhotoModalLabel">Update Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img src="<?php echo getProfilePhoto($user); ?>" 
                             alt="Current Profile Photo" 
                             class="rounded-circle mb-2"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                        <p class="text-muted">Current profile photo</p>
                    </div>
                    <div class="mb-3">
                        <label for="profile_photo" class="form-label">Choose a new profile photo</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*" required>
                        <div class="form-text">Allowed formats: JPG, PNG, GIF. Max size: 2MB</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_profile_photo" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add some interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh statistics every 30 seconds
    setInterval(function() {
        // You can add AJAX call here to refresh stats without page reload
        console.log('Stats refresh available - implement AJAX if needed');
    }, 30000);
    
    // Add animation to stat cards on hover
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
});
</script>

<?php include 'includes/footer.php'; ?>