<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';

$page_title = "Feedback - Wezo Campus Hub";

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();

// Get filter parameters
$type = sanitizeInput($_GET['type'] ?? 'given');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Get feedback
$feedbacks = [];
$totalFeedback = 0;

if ($type === 'given') {
    // Feedback given by user
    $sql = "
        SELECT f.*, d.title, d.id as document_id, u.username as document_owner,
               (SELECT COUNT(*) FROM feedback_likes WHERE feedback_id = f.id) as like_count
        FROM feedbacks f 
        JOIN documents d ON f.document_id = d.id 
        JOIN users u ON d.user_id = u.id 
        WHERE f.user_id = ? 
        ORDER BY f.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user['id'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM feedbacks WHERE user_id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $user['id']);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalData = $countResult->fetch_assoc();
    $totalFeedback = $totalData['total'];
} else {
    // Feedback received on user's documents
    $sql = "
        SELECT f.*, d.title, d.id as document_id, u.username as commenter,
               (SELECT COUNT(*) FROM feedback_likes WHERE feedback_id = f.id) as like_count
        FROM feedbacks f 
        JOIN documents d ON f.document_id = d.id 
        JOIN users u ON f.user_id = u.id 
        WHERE d.user_id = ? 
        ORDER BY f.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user['id'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total 
        FROM feedbacks f 
        JOIN documents d ON f.document_id = d.id 
        WHERE d.user_id = ?
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $user['id']);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalData = $countResult->fetch_assoc();
    $totalFeedback = $totalData['total'];
}

// Calculate pagination
$totalPages = ceil($totalFeedback / $limit);

// Get feedback statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM feedbacks WHERE user_id = {$user['id']}) as feedback_given,
        (SELECT COUNT(*) FROM feedback_likes WHERE feedback_id IN (SELECT id FROM feedbacks WHERE user_id = {$user['id']})) as likes_received,
        (SELECT COUNT(*) FROM feedbacks f JOIN documents d ON f.document_id = d.id WHERE d.user_id = {$user['id']}) as feedback_received
")->fetch_assoc();

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb -->
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Feedback</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2"><i class="fas fa-comments me-2"></i>Feedback</h2>
                            <p class="text-muted mb-0">Manage and view feedback on documents</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group" role="group">
                                <a href="feedback.php?type=given" class="btn btn-<?php echo $type === 'given' ? 'primary' : 'outline-primary'; ?>">
                                    Feedback Given
                                </a>
                                <a href="feedback.php?type=received" class="btn btn-<?php echo $type === 'received' ? 'primary' : 'outline-primary'; ?>">
                                    Feedback Received
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Feedback Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $stats['feedback_given']; ?></h3>
                                <p class="mb-0">Feedback Given</p>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $stats['feedback_received']; ?></h3>
                                <p class="mb-0">Feedback Received</p>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning"><?php echo $stats['likes_received']; ?></h3>
                                <p class="mb-0">Likes Received</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feedback List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php echo $type === 'given' ? 'Feedback You\'ve Given' : 'Feedback Received on Your Documents'; ?>
                        </h5>
                        <div>
                            <span class="badge bg-primary">
                                <?php echo number_format($totalFeedback); ?> feedback<?php echo ($totalFeedback !== 1) ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($feedbacks)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                        <h4>No feedback found</h4>
                        <p class="text-muted">
                            <?php if ($type === 'given'): ?>
                                You haven't given any feedback yet.
                            <?php else: ?>
                                No one has given feedback on your documents yet.
                            <?php endif; ?>
                        </p>
                        <a href="search.php" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Browse Documents
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($feedbacks as $feedback): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-1">
                                        <a href="document.php?id=<?php echo $feedback['document_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($feedback['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">
                                        <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php if ($type === 'given'): ?>
                                        To: <?php echo htmlspecialchars($feedback['document_owner']); ?>
                                        <?php else: ?>
                                        By: <?php echo htmlspecialchars($feedback['commenter']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    <button class="btn btn-sm btn-outline-primary like-feedback-btn" 
                                            data-feedback-id="<?php echo $feedback['id']; ?>">
                                        <i class="fas fa-thumbs-up"></i>
                                        <span class="like-count"><?php echo $feedback['like_count']; ?></span>
                                    </button>
                                    <?php if ($type === 'received'): ?>
                                    <button class="btn btn-sm btn-outline-success reply-btn" 
                                            data-feedback-id="<?php echo $feedback['id']; ?>"
                                            data-commenter="<?php echo htmlspecialchars($feedback['commenter']); ?>">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($type === 'given'): ?>
                                    <a href="document.php?id=<?php echo $feedback['document_id']; ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View Document
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Reply form (hidden by default) -->
                            <?php if ($type === 'received'): ?>
                            <div class="reply-form mt-3 d-none" id="reply-form-<?php echo $feedback['id']; ?>">
                                <form class="reply-form-inner">
                                    <div class="mb-2">
                                        <textarea class="form-control form-control-sm" 
                                                  placeholder="Reply to <?php echo htmlspecialchars($feedback['commenter']); ?>..."
                                                  rows="2"></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-sm btn-secondary cancel-reply me-2">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary">Send Reply</button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?type=' . $type . '&page=1">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?type=' . $type . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page + 1; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feedback Tips -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Feedback Best Practices</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h6>Be Constructive</h6>
                                    <p class="small">Provide helpful feedback that improves the document quality</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <i class="fas fa-thumbs-up text-primary fa-2x me-3"></i>
                                <div>
                                    <h6>Be Respectful</h6>
                                    <p class="small">Maintain a professional and respectful tone in all feedback</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <i class="fas fa-star text-warning fa-2x me-3"></i>
                                <div>
                                    <h6>Be Specific</h6>
                                    <p class="small">Point out specific areas that can be improved or were particularly good</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <i class="fas fa-reply text-info fa-2x me-3"></i>
                                <div>
                                    <h6>Engage in Dialogue</h6>
                                    <p class="small">Reply to feedback on your documents to show appreciation</p>
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
document.addEventListener('DOMContentLoaded', function() {
    // Like feedback functionality
    document.querySelectorAll('.like-feedback-btn').forEach(button => {
        button.addEventListener('click', function() {
            const feedbackId = this.getAttribute('data-feedback-id');
            const likeCount = this.querySelector('.like-count');
            
            fetch('like-feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    feedback_id: feedbackId,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    likeCount.textContent = data.like_count;
                    this.innerHTML = '<i class="fas fa-thumbs-up"></i> ' + data.like_count;
                    this.classList.add('btn-primary');
                    this.classList.remove('btn-outline-primary');
                    this.disabled = true;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error liking feedback. Please try again.');
            });
        });
    });
    
    // Reply functionality
    document.querySelectorAll('.reply-btn').forEach(button => {
        button.addEventListener('click', function() {
            const feedbackId = this.getAttribute('data-feedback-id');
            const replyForm = document.getElementById('reply-form-' + feedbackId);
            
            // Hide all other reply forms
            document.querySelectorAll('.reply-form').forEach(form => {
                form.classList.add('d-none');
            });
            
            // Show this reply form
            replyForm.classList.remove('d-none');
            
            // Focus on textarea
            replyForm.querySelector('textarea').focus();
        });
    });
    
    // Cancel reply
    document.querySelectorAll('.cancel-reply').forEach(button => {
        button.addEventListener('click', function() {
            const replyForm = this.closest('.reply-form');
            replyForm.classList.add('d-none');
            replyForm.querySelector('textarea').value = '';
        });
    });
    
    // Submit reply
    document.querySelectorAll('.reply-form-inner').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const textarea = this.querySelector('textarea');
            const reply = textarea.value.trim();
            
            if (!reply) {
                alert('Please enter a reply.');
                return;
            }
            
            const feedbackId = this.closest('.reply-form').id.replace('reply-form-', '');
            
            // In a real implementation, you would send this to the server
            // For now, we'll just show a success message
            alert('Reply sent successfully!');
            textarea.value = '';
            this.closest('.reply-form').classList.add('d-none');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>