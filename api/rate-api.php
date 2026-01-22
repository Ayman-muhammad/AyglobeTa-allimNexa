<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Document.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $document_id = intval($data['document_id'] ?? 0);
    $rating = intval($data['rating'] ?? 0);
    $review = sanitize_input($data['review'] ?? '');
    
    if (!$document_id || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid rating data']);
        exit;
    }
    
    try {
        $document = new Document();
        $document->rateDocument($document_id, $_SESSION['user_id'], $rating, $review);
        
        // Get updated rating
        $db = new Database();
        $updated = $db->fetchOne(
            "SELECT average_rating, total_ratings FROM documents WHERE id = ?",
            [$document_id]
        );
        
        echo json_encode([
            'success' => true,
            'average_rating' => number_format($updated['average_rating'], 1),
            'total_ratings' => $updated['total_ratings'],
            'message' => 'Rating submitted successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>