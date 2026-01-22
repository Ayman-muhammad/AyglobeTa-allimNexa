<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$userId = getCurrentUserId();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$timetableId = intval($_GET['id']);
$timetable = getTimetableById($timetableId, $userId);

if (!$timetable || $timetable['user_id'] != $userId) {
    header('Location: index.php?error=Permission+denied');
    exit;
}

$db = db()->getConnection();

// Get current privacy settings
$privacyQuery = "SELECT * FROM timetable_privacy WHERE timetable_id = ?";
$privacyStmt = $db->prepare($privacyQuery);
$privacyStmt->bind_param("i", $timetableId);
$privacyStmt->execute();
$privacyResult = $privacyStmt->get_result();
$privacySettings = $privacyResult->fetch_assoc();

if (!$privacySettings) {
    // Create default privacy settings
    $insertQuery = "
        INSERT INTO timetable_privacy 
        (timetable_id, require_login, allow_viewing, allow_comments, allow_duplicate)
        VALUES (?, TRUE, TRUE, TRUE, TRUE)
    ";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bind_param("i", $timetableId);
    $insertStmt->execute();
    
    $privacySettings = [
        'timetable_id' => $timetableId,
        'require_login' => 1,
        'allow_viewing' => 1,
        'allow_comments' => 1,
        'allow_duplicate' => 1,
        'require_password' => 0
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_privacy') {
        $requireLogin = isset($_POST['require_login']) ? 1 : 0;
        $allowViewing = isset($_POST['allow_viewing']) ? 1 : 0;
        $allowComments = isset($_POST['allow_comments']) ? 1 : 0;
        $allowDuplicate = isset($_POST['allow_duplicate']) ? 1 : 0;
        $requirePassword = isset($_POST['require_password']) ? 1 : 0;
        $password = trim($_POST['password'] ?? '');
        
        $updateQuery = "
            UPDATE timetable_privacy 
            SET require_login = ?, 
                allow_viewing = ?, 
                allow_comments = ?, 
                allow_duplicate = ?,
                require_password = ?,
                password_hash = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE timetable_id = ?
        ";
        
        $passwordHash = '';
        if ($requirePassword && !empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        } elseif ($requirePassword && !empty($privacySettings['password_hash'])) {
            $passwordHash = $privacySettings['password_hash'];
        }
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param(
            "iiiiisi",
            $requireLogin,
            $allowViewing,
            $allowComments,
            $allowDuplicate,
            $requirePassword,
            $passwordHash,
            $timetableId
        );
        
        if ($updateStmt->execute()) {
            // Update main timetable table
            $timetableUpdate = "
                UPDATE user_timetables 
                SET is_public = ?, 
                    allow_comments = ?, 
                    allow_duplicate = ?,
                    require_password = ?,
                    password_hash = ?
                WHERE id = ?
            ";
            
            $isPublic = ($allowViewing && !$requireLogin) ? 1 : 0;
            
            $timetableStmt = $db->prepare($timetableUpdate);
            $timetableStmt->bind_param(
                "iiissi",
                $isPublic,
                $allowComments,
                $allowDuplicate,
                $requirePassword,
                $passwordHash,
                $timetableId
            );
            $timetableStmt->execute();
            
            $success = 'Privacy settings updated successfully';
        } else {
            $error = 'Failed to update privacy settings';
        }
    }
}

$pageTitle = 'Privacy Settings: ' . htmlspecialchars($timetable['title']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-lock me-2"></i>Privacy Settings
                            </h4>
                            <p class="mb-0 small opacity-75">"<?php echo htmlspecialchars($timetable['title']); ?>"</p>
                        </div>
                        <a href="view.php?id=<?php echo $timetableId; ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Timetable
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_privacy">
                        
                        <!-- Access Control -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Access Control</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           id="require_login" name="require_login" value="1"
                                           <?php echo $privacySettings['require_login'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="require_login">
                                        Require Login to View
                                    </label>
                                    <div class="form-text">
                                        Only logged-in users can view this timetable
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           id="allow_viewing" name="allow_viewing" value="1"
                                           <?php echo $privacySettings['allow_viewing'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="allow_viewing">
                                        Allow Viewing
                                    </label>
                                    <div class="form-text">
                                        Allow users to view this timetable (disable to make completely private)
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3" id="passwordSection" 
                                     style="display: <?php echo $privacySettings['require_password'] ? 'block' : 'none'; ?>">
                                    <input class="form-check-input" type="checkbox" 
                                           id="require_password" name="require_password" value="1"
                                           <?php echo $privacySettings['require_password'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="require_password">
                                        Require Password
                                    </label>
                                    <div class="form-text">
                                        Users must enter a password to view this timetable
                                    </div>
                                    
                                    <div class="mt-2" id="passwordInput" 
                                         style="display: <?php echo $privacySettings['require_password'] ? 'block' : 'none'; ?>">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" 
                                               name="password" placeholder="Enter password (optional to keep existing)">
                                        <div class="form-text">
                                            Leave blank to keep existing password
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Interaction Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Interaction Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           id="allow_comments" name="allow_comments" value="1"
                                           <?php echo $privacySettings['allow_comments'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="allow_comments">
                                        Allow Comments
                                    </label>
                                    <div class="form-text">
                                        Allow users to comment on this timetable
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           id="allow_duplicate" name="allow_duplicate" value="1"
                                           <?php echo $privacySettings['allow_duplicate'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="allow_duplicate">
                                        Allow Duplication
                                    </label>
                                    <div class="form-text">
                                        Allow users to create a copy of this timetable
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Current Access Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Access Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Current Status:</strong></p>
                                        <div class="alert alert-<?php echo $timetable['is_public'] ? 'success' : 'warning'; ?>">
                                            <?php if ($timetable['is_public']): ?>
                                                <i class="fas fa-globe me-2"></i>
                                                This timetable is <strong>public</strong> and can be viewed by anyone with the link
                                            <?php else: ?>
                                                <i class="fas fa-lock me-2"></i>
                                                This timetable is <strong>private</strong> and has restricted access
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Share Link:</strong></p>
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   value="<?php echo BASE_URL; ?>view.php?id=<?php echo $timetableId; ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-primary" type="button" 
                                                    onclick="copyToClipboard(this.previousElementSibling.value)">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Share this link with others to give access
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $timetableId; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password section
    const requirePassword = document.getElementById('require_password');
    const passwordSection = document.getElementById('passwordSection');
    const passwordInput = document.getElementById('passwordInput');
    
    requirePassword?.addEventListener('change', function() {
        if (this.checked) {
            passwordSection.style.display = 'block';
            passwordInput.style.display = 'block';
        } else {
            passwordSection.style.display = 'none';
            passwordInput.style.display = 'none';
        }
    });
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        toastr.success('Link copied to clipboard!');
    });
}
</script>

<style>
.form-check.form-switch {
    padding-left: 3.5rem;
}

.form-check.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
    margin-left: -3.5rem;
}

.card-header h5 {
    color: #2c3e50;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>