<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Reset Password - Wezo Campus Hub";

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$validToken = false;
$token = sanitizeInput($_GET['token'] ?? '');

// Validate token
if (!empty($token)) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $validToken = true;
        $user = $result->fetch_assoc();
        $userId = $user['id'];
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    }
} else {
    $error = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters with uppercase, lowercase, and number';
    } else {
        // Hash new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($updateStmt->execute()) {
            $success = 'Password has been reset successfully. You can now login with your new password.';
            $validToken = false; // Invalidate token after use
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Reset Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-1"></i>Go to Login
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($validToken && !$success): ?>
                    <p class="text-muted mb-4">
                        Please enter your new password below.
                    </p>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>New Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">
                                Must be at least 8 characters with uppercase, lowercase, and number
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Confirm New Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>Reset Password
                            </button>
                        </div>
                    </form>
                    <?php elseif (!$validToken && !$success): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Invalid Reset Link</h5>
                        <p class="text-muted">Your password reset link is invalid or has expired.</p>
                        <a href="forgot-password.php" class="btn btn-warning">
                            <i class="fas fa-key me-1"></i>Request New Reset Link
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($validToken): ?>
                    <hr>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-shield-alt me-2"></i>Password Requirements:</h6>
                        <ul class="mb-0 small">
                            <li>Minimum 8 characters</li>
                            <li>At least one uppercase letter</li>
                            <li>At least one lowercase letter</li>
                            <li>At least one number</li>
                            <li>Special characters are allowed but not required</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');
            
            if (strengthBar && strengthText) {
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 25;
                
                // Lowercase check
                if (/[a-z]/.test(password)) strength += 25;
                
                // Uppercase check
                if (/[A-Z]/.test(password)) strength += 25;
                
                // Number check
                if (/[0-9]/.test(password)) strength += 25;
                
                strengthBar.style.width = strength + '%';
                
                // Update color and text
                if (strength < 40) {
                    strengthBar.className = 'progress-bar bg-danger';
                    strengthText.textContent = 'Weak';
                } else if (strength < 70) {
                    strengthBar.className = 'progress-bar bg-warning';
                    strengthText.textContent = 'Moderate';
                } else if (strength < 90) {
                    strengthBar.className = 'progress-bar bg-info';
                    strengthText.textContent = 'Good';
                } else {
                    strengthBar.className = 'progress-bar bg-success';
                    strengthText.textContent = 'Strong';
                }
            }
        });
    }
    
    if (confirmInput && passwordInput) {
        confirmInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (confirmPassword) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>