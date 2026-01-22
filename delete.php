<?php
require_once 'includes/header.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';
require_login();

$document_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$document_id) {
    header("Location: mylibrary.php");
    exit();
}

try {
    $db = new Database();
    
    // Check if document belongs to user or user is admin
    $document = $db->fetchOne(
        "SELECT d.*, u.is_admin 
         FROM documents d 
         JOIN users u ON d.user_id = u.id 
         WHERE d.id = ?",
        [$document_id]
    );
    
    if (!$document) {
        $_SESSION['error'] = "Document not found";
        header("Location: mylibrary.php");
        exit();
    }
    
    // Check permissions
    $can_delete = ($document['user_id'] == $user_id) || $document['is_admin'];
    
    if (!$can_delete) {
        $_SESSION['error'] = "You don't have permission to delete this document";
        header("Location: mylibrary.php");
        exit();
    }
    
    // Delete file from server
    if (file_exists($document['file_path'])) {
        unlink($document['file_path']);
    }
    
    // Delete from database (cascade will handle related records)
    $db->preparedQuery(
        "DELETE FROM documents WHERE id = ?",
        [$document_id]
    );
    
    // Log deletion
    $db->insert('admin_logs', [
        'admin_id' => $user_id,
        'action' => 'delete_document',
        'details' => "Deleted document ID: $document_id",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['success'] = "Document deleted successfully";
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error deleting document: " . $e->getMessage();
}

header("Location: mylibrary.php");
exit();
?>