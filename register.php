<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

// Add database connection
require_once 'config/database.php';
$pdo = getDBConnection();

$page_title = "Register - Wezo Campus Hub";

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $university = isset($_POST['university']) ? trim(sanitizeInput($_POST['university'])) : '';
    $course = isset($_POST['course']) ? trim(sanitizeInput($_POST['course'])) : '';
    $registration_year = isset($_POST['registration_year']) ? intval($_POST['registration_year']) : null;
    
    // Validation
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!validateEmail($email)) {
        $error = "Invalid email format";
    } elseif (!validatePassword($password)) {
        $error = "Password must be at least 8 characters with uppercase, lowercase, and number";
    } else {
        // Handle profile photo upload
        $profile_photo = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = $_FILES['profile_photo']['type'];
            $file_size = $_FILES['profile_photo']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, PNG, GIF, and WebP images are allowed";
            } elseif ($file_size > $max_size) {
                $error = "Image size must be less than 2MB";
            } else {
                // Generate unique filename
                $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . uniqid() . '_' . time() . '.' . $extension;
                $upload_path = 'uploads/profile_photos/' . $filename;
                
                // Ensure directory exists
                if (!file_exists('uploads/profile_photos')) {
                    mkdir('uploads/profile_photos', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $profile_photo = $upload_path;
                } else {
                    $error = "Failed to upload profile photo";
                }
            }
        }
        
        if (!$error) {
            // Register user with additional info
            $result = $auth->register($username, $email, $password, [
                'university' => $university,
                'course' => $course,
                'registration_year' => $registration_year,
                'profile_photo' => $profile_photo
            ]);
            
            if ($result['success']) {
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = 'success';
                header("Location: dashboard.php");
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create Your Account</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Profile Photo Column -->
                            <div class="col-md-4 text-center mb-4">
                                <div class="profile-photo-container mb-3">
                                    <img id="profile-photo-preview" src="assets/images/default-avatar.png" 
                                         class="img-thumbnail rounded-circle" 
                                         style="width: 150px; height: 150px; object-fit: cover;"
                                         alt="Profile Photo">
                                </div>
                                <div class="mb-3">
                                    <label for="profile_photo" class="form-label">
                                        <i class="fas fa-camera me-1"></i>Profile Photo
                                    </label>
                                    <input type="file" class="form-control" id="profile_photo" name="profile_photo" 
                                           accept="image/*" onchange="previewProfilePhoto(event)">
                                    <div class="form-text">Optional. Max 2MB (JPG, PNG, GIF, WebP)</div>
                                </div>
                            </div>
                            
                            <!-- Basic Info Column -->
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">
                                                <i class="fas fa-user me-1"></i>Username *
                                            </label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                                   required minlength="3" maxlength="50">
                                            <div class="form-text">3-50 characters, letters and numbers only</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">
                                                <i class="fas fa-envelope me-1"></i>Email Address *
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                                   required>
                                            <div class="form-text">We'll never share your email</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">
                                                <i class="fas fa-lock me-1"></i>Password *
                                            </label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   required minlength="8">
                                            <div class="password-strength mt-1">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" id="password-strength-bar" role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <small id="password-strength-text" class="form-text"></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">
                                                <i class="fas fa-lock me-1"></i>Confirm Password *
                                            </label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <div class="form-text">Re-enter your password</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- University & Course Information -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-graduation-cap me-1"></i>Academic Information (Optional)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="university" class="form-label">
                                                <i class="fas fa-university me-1"></i>University/Institution
                                            </label>
                                            <input type="text" class="form-control" id="university" name="university" 
                                                   value="<?php echo isset($_POST['university']) ? htmlspecialchars($_POST['university']) : ''; ?>"
                                                   placeholder="e.g., University of Nairobi, Kenyatta University">
                                            <div class="form-text">Type your university or institution name</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="course" class="form-label">
                                                <i class="fas fa-book me-1"></i>Course/Program
                                            </label>
                                            <input type="text" class="form-control" id="course" name="course" 
                                                   value="<?php echo isset($_POST['course']) ? htmlspecialchars($_POST['course']) : ''; ?>"
                                                   placeholder="e.g., Computer Science, Business Administration">
                                            <div class="form-text">Type your course or program of study</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="registration_year" class="form-label">
                                                <i class="fas fa-calendar-alt me-1"></i>Registration Year
                                            </label>
                                            <select class="form-select" id="registration_year" name="registration_year">
                                                <option value="">Select Year</option>
                                                <?php for ($year = date('Y'); $year >= 2000; $year--): ?>
                                                <option value="<?php echo $year; ?>" 
                                                    <?php echo (isset($_POST['registration_year']) && $_POST['registration_year'] == $year) ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                                <?php endfor; ?>
                                            </select>
                                            <div class="form-text">Year you started/joined</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="alert alert-info p-2">
                                                <small>
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    You can update this information later in your profile settings
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <div class="alert alert-secondary p-2">
                                            <small>
                                                <i class="fas fa-lightbulb me-1"></i>
                                                <strong>Tips:</strong> 
                                                Entering your university and course helps us provide you with personalized content, 
                                                connect you with classmates, and recommend relevant study materials.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-user-plus me-1"></i>Create Account
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Already have an account? <a href="login.php" class="fw-bold">Login here</a></p>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-graduation-cap me-1"></i>Why Join Wezo Campus Hub?</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Access thousands of educational resources</li>
                                            <li>Share your notes and materials</li>
                                            <li>Connect with fellow students</li>
                                            <li>Get personalized course recommendations</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Rate and review documents</li>
                                            <li>24/7 Chatbot assistance</li>
                                            <li>University-specific content</li>
                                            <li>Connect with alumni network</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By accessing Wezo Campus Hub, you agree to these terms.</p>
                
                <h6>2. User Responsibilities</h6>
                <p>Users are responsible for their content and must respect copyright laws.</p>
                
                <h6>3. Content Guidelines</h6>
                <p>All uploaded content must be educational and appropriate.</p>
                
                <h6>4. Privacy</h6>
                <p>We respect your privacy as outlined in our Privacy Policy.</p>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Information Collection</h6>
                <p>We collect only necessary information for platform functionality.</p>
                
                <h6>2. Data Usage</h6>
                <p>Your data is used solely to improve your experience.</p>
                
                <h6>3. Data Protection</h6>
                <p>We implement security measures to protect your data.</p>
                
                <h6>4. Cookies</h6>
                <p>We use cookies for essential functionality only.</p>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password strength checker
    $('#password').on('keyup', function() {
        var password = $(this).val();
        var strength = 0;
        var text = '';
        
        if (password.length >= 8) strength += 25;
        if (/[a-z]/.test(password)) strength += 25;
        if (/[A-Z]/.test(password)) strength += 25;
        if (/[0-9]/.test(password)) strength += 25;
        
        $('#password-strength-bar').css('width', strength + '%');
        
        if (strength < 50) {
            $('#password-strength-bar').removeClass('bg-warning bg-success').addClass('bg-danger');
            text = 'Weak password';
        } else if (strength < 75) {
            $('#password-strength-bar').removeClass('bg-danger bg-success').addClass('bg-warning');
            text = 'Moderate password';
        } else {
            $('#password-strength-bar').removeClass('bg-danger bg-warning').addClass('bg-success');
            text = 'Strong password';
        }
        
        $('#password-strength-text').text(text);
    });
    
    // Profile photo preview
    window.previewProfilePhoto = function(event) {
        var reader = new FileReader();
        reader.onload = function() {
            var output = document.getElementById('profile-photo-preview');
            output.src = reader.result;
        };
        reader.readAsDataURL(event.target.files[0]);
    };
    
    // Auto-populate current year in registration year
    var currentYear = new Date().getFullYear();
    $('#registration_year').val(currentYear);
    
    // Form validation
    $('form').submit(function() {
        var username = $('#username').val();
        if (username.length < 3) {
            alert('Username must be at least 3 characters long');
            return false;
        }
        
        var email = $('#email').val();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Please enter a valid email address');
            return false;
        }
        
        var password = $('#password').val();
        if (password.length < 8) {
            alert('Password must be at least 8 characters long');
            return false;
        }
        
        var confirmPassword = $('#confirm_password').val();
        if (password !== confirmPassword) {
            alert('Passwords do not match');
            return false;
        }
        
        if (!$('#terms').prop('checked')) {
            alert('You must agree to the Terms of Service and Privacy Policy');
            return false;
        }
        
        return true;
    });
    
    // Auto-suggest for university field (optional enhancement)
    $('#university').on('focus', function() {
        if ($(this).val() === '') {
            $(this).attr('placeholder', 'Start typing (e.g., "University of Nairobi", "Kenyatta University")');
        }
    });
    
    $('#course').on('focus', function() {
        if ($(this).val() === '') {
            $(this).attr('placeholder', 'Start typing (e.g., "Computer Science", "Business Administration")');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>