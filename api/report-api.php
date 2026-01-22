<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Document.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id'] ?? 0);
    $report_type = $_POST['report_type'] ?? '';
    $description = sanitize_input($_POST['description'] ?? '');
    
    if (!$document_id || empty($report_type) || empty($description)) {
        echo json_encode(['error' => 'Please fill all required fields']);
        exit;
    }
    
    try {
        $document = new Document();
        $report_id = $document->reportDocument(
            $document_id,
            $_SESSION['user_id'],
            $report_type,
            $description
        );
        
        // Increment report count
        $db = new Database();
        $db->preparedQuery(
            "UPDATE documents SET report_count = report_count + 1 WHERE id = ?",
            [$document_id]
        );
        
        echo json_encode([
            'success' => true,
            'report_id' => $report_id,
            'message' => 'Report submitted successfully. Our team will review it.'
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>