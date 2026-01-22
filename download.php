<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';
require_once 'classes/Database.php';

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'Invalid document ID.';
    $_SESSION['message_type'] = 'danger';
    header("Location: search.php");
    exit();
}

$documentId = intval($_GET['id']);
$document = new Document($documentId);

// Check if document exists
if (!$document->getId()) {
    $_SESSION['message'] = 'Document not found.';
    $_SESSION['message_type'] = 'danger';
    header("Location: search.php");
    exit();
}

// Check if user is logged in (required for downloading)
if (!$auth->isLoggedIn()) {
    $_SESSION['message'] = 'Please login to download documents.';
    $_SESSION['message_type'] = 'warning';
    $_SESSION['redirect_after_login'] = 'download.php?id=' . $documentId;
    header("Location: login.php");
    exit();
}

// Check if document is approved (unless admin or owner)
$user = $auth->getCurrentUser();
$isOwner = $document->getData('user_id') == $user['id'];
$isAdmin = $auth->isAdmin();

if (!$document->getData('is_approved') && !$isOwner && !$isAdmin) {
    $_SESSION['message'] = 'This document is under review and not available for download.';
    $_SESSION['message_type'] = 'warning';
    header("Location: search.php");
    exit();
}

// Get document data
$docData = $document->getData();
$filePath = $docData['file_path'];
$filename = $docData['filename'];
$originalName = $docData['original_title'] ?: $docData['title'];

// Check if file exists
if (!file_exists($filePath)) {
    $_SESSION['message'] = 'File not found on server.';
    $_SESSION['message_type'] = 'danger';
    header("Location: document.php?id=" . $documentId);
    exit();
}

// Increment download count
$document->incrementDownloadCount();

// Log download activity
$conn = getDBConnection();
$conn->query("INSERT INTO download_logs (document_id, user_id, downloaded_at) VALUES ($documentId, {$user['id']}, NOW())");

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($originalName) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clear output buffer
ob_clean();
flush();

// Read file
readfile($filePath);

// Don't output anything else
exit();
?>