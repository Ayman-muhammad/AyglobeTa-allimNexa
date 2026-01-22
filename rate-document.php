<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to rate documents.']);
    exit();
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit();
}

// Check rate limiting
if (!checkRateLimit('rate_document', 10, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many rating attempts. Please wait.']);
    exit();
}

// Validate input
$documentId = intval($_POST['document_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review = sanitizeInput($_POST['review'] ?? '');

if ($documentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid document ID.']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit();
}

// Check if document exists
$document = new Document($documentId);
if (!$document->getId()) {
    echo json_encode(['success' => false, 'message' => 'Document not found.']);
    exit();
}

// Check if user is the owner
if ($document->getData('user_id') == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot rate your own document.']);
    exit();
}

// Add rating
try {
    $document->addRating($_SESSION['user_id'], $rating, $review);
    
    // Get updated document data
    $docData = $document->getData();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully.',
        'average_rating' => number_format($docData['average_rating'], 1),
        'total_ratings' => $docData['total_ratings'],
        'rating' => $rating,
        'review' => $review
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>