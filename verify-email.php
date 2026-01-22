<?php
require_once 'includes/header.php';
require_once 'classes/User.php';

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';

if (empty($token)) {
    $message = "Invalid verification link";
    $message_type = 'danger';
} else {
    try {
        $user = new User();
        $result = $user->verifyEmail($token);
        
        if ($result) {
            $message = "Email verified successfully! You can now login to your account.";
            $message_type = 'success';
        } else {
            $message = "Invalid or expired verification token";
            $message_type = 'warning';
        }
    } catch (Exception $e) {
        $message = "Error verifying email: " . $e->getMessage();
        $message_type = 'danger';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-envelope"></i> Email Verification</h4>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <?php if ($message_type == 'success'): ?>
                        <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <?php elseif ($message_type == 'warning'): ?>
                        <i class="fas fa-exclamation-triangle fa-5x text-warning mb-3"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle fa-5x text-danger mb-3"></i>
                    <?php endif; ?>
                    
                    <h4><?php echo $message; ?></h4>
                </div>
                
                <div class="d-grid gap-2">
                    <?php if ($message_type == 'success'): ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Proceed to Login
                        </a>
                    <?php else: ?>
                        <a href="resend-verification.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Resend Verification Email
                        </a>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>