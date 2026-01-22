<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Document.php';
require_once '../classes/UploadHandler.php';
require_login();

header('Content-Type: application/json');

// Check rate limit
if (!check_rate_limit('upload')) {
    echo json_encode(['error' => 'Upload limit reached. Please try again later.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $uploader = new UploadHandler();
        
        // Validate and process upload
        $result = $uploader->processUpload($_FILES['document'], $_POST);
        
        if ($result['success']) {
            // Create document record
            $document = new Document();
            $document_id = $document->upload(
                $_SESSION['user_id'],
                $result['file_data'],
                $result['document_data']
            );
            
            echo json_encode([
                'success' => true,
                'document_id' => $document_id,
                'message' => 'Document uploaded successfully',
                'file_url' => $result['file_url']
            ]);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>