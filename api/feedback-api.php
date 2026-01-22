<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Document.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $document_id = intval($_POST['document_id'] ?? 0);
            $comment = sanitize_input($_POST['comment'] ?? '');
            
            if (!$document_id || empty($comment)) {
                echo json_encode(['error' => 'Invalid comment data']);
                exit;
            }
            
            try {
                $document = new Document();
                $feedback_id = $document->addFeedback($document_id, $_SESSION['user_id'], $comment);
                
                // Get user info for response
                $db = new Database();
                $user = $db->fetchOne(
                    "SELECT username FROM users WHERE id = ?",
                    [$_SESSION['user_id']]
                );
                
                echo json_encode([
                    'success' => true,
                    'feedback_id' => $feedback_id,
                    'username' => $user['username'],
                    'comment' => htmlspecialchars($comment),
                    'message' => 'Comment added successfully'
                ]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'like':
            $feedback_id = intval($_POST['feedback_id'] ?? 0);
            
            if (!$feedback_id) {
                echo json_encode(['error' => 'Invalid feedback ID']);
                exit;
            }
            
            try {
                $document = new Document();
                $result = $document->likeFeedback($feedback_id, $_SESSION['user_id']);
                
                if ($result) {
                    // Get updated like count
                    $db = new Database();
                    $count = $db->fetchOne(
                        "SELECT likes FROM feedbacks WHERE id = ?",
                        [$feedback_id]
                    )['likes'];
                    
                    echo json_encode([
                        'success' => true,
                        'likes' => $count,
                        'message' => 'Liked successfully'
                    ]);
                } else {
                    echo json_encode(['error' => 'Already liked']);
                }
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>