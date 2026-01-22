<?php
require_once 'includes/header.php';
require_once 'classes/User.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $db = new Database();
            $user = $db->fetchOne(
                "SELECT id, is_verified FROM users WHERE email = ?",
                [$email]
            );
            
            if (!$user) {
                // Don't reveal if user exists (security)
                $success = "If an account exists with this email, a verification link has been sent.";
            } elseif ($user['is_verified']) {
                $success = "This email is already verified. You can login.";
            } else {
                // Generate new token
                $token = bin2hex(random_bytes(32));
                
                $db->update('users',
                    ['verification_token' => $token],
                    'id = ?',
                    [$user['id']]
                );
                
                // Send verification email
                $verification_link = SITE_URL . "/verify-email.php?token=" . $token;
                $subject = "Verify Your WCH Account";
                $message = "Click the link to verify your email: $verification_link";
                
                // Use EmailService class here
                mail($email, $subject, $message);
                
                $success = "Verification email sent! Please check your inbox.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-envelope"></i> Resend Verification Email</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <p class="text-muted mb-4">
                    Enter your email address to receive a new verification link.
                </p>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               required autofocus>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Resend Verification
                        </button>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>