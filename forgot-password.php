<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';

$page_title = "Forgot Password - Wezo Campus Hub";

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check rate limiting
        if (!checkRateLimit('password_reset', 3, 3600)) {
            $error = 'Too many password reset attempts. Please try again later.';
        } else {
            // Check if user exists
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetTokenExpiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                
                // Save token to database
                $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->bind_param("ssi", $resetToken, $resetTokenExpiry, $user['id']);
                
                if ($updateStmt->execute()) {
                    // Send reset email (in a real implementation)
                    $resetLink = SITE_URL . "/reset-password.php?token=" . $resetToken;
                    
                    // For demo purposes, we'll show the link
                    $success = 'Password reset link has been sent to your email.';
                    $demoResetLink = "<p class='mt-3'><strong>Demo Reset Link:</strong> <a href='$resetLink'>$resetLink</a></p>";
                    
                    // In production, you would send an actual email
                    // $emailService = new EmailService();
                    // $emailService->sendPasswordResetEmail($email, $user['username'], $resetToken);
                } else {
                    $error = 'Failed to generate reset token. Please try again.';
                }
            } else {
                // For security reasons, don't reveal if email exists
                $success = 'If your email exists in our system, you will receive a password reset link.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Forgot Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <?php if (isset($demoResetLink)) echo $demoResetLink; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                            <div class="form-text">Enter the email address associated with your account.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane me-1"></i>Send Reset Link
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Remember your password? <a href="login.php">Back to Login</a></p>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Security Information:</h6>
                        <ul class="mb-0 small">
                            <li>Reset links expire in 1 hour</li>
                            <li>Only 3 reset attempts allowed per hour</li>
                            <li>Check your spam folder if you don't see the email</li>
                            <li>Contact support if you continue having issues</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>