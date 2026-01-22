<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Document.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $level = $_GET['level'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    
    try {
        $document = new Document();
        $results = $document->searchDocuments($query, [
            'category' => $category,
            'education_level' => $level,
            'page' => $page,
            'limit' => $limit
        ]);
        
        // Format results
        $formatted = [];
        foreach ($results as $doc) {
            $formatted[] = [
                'id' => $doc['id'],
                'title' => htmlspecialchars($doc['title']),
                'description' => htmlspecialchars(substr($doc['description'], 0, 100)),
                'category' => $doc['category'],
                'education_level' => $doc['education_level'],
                'uploader' => $doc['username'],
                'views' => $doc['view_count'],
                'downloads' => $doc['download_count'],
                'rating' => number_format($doc['avg_rating'], 1),
                'upload_date' => date('M d, Y', strtotime($doc['upload_date'])),
                'file_type' => $doc['file_type']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'results' => $formatted,
            'count' => count($results),
            'page' => $page
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>