<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php';

$page_title = "Edit Document - Wezo Campus Hub";

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();

$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$document = null;
$errors = [];
$success = false;

// Fetch document details
if ($document_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $document_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found or you don't have permission to edit it.";
        header("Location: mylibrary.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid document ID.";
    header("Location: mylibrary.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $subcategory = sanitizeInput($_POST['subcategory'] ?? '');
    $education_level = sanitizeInput($_POST['education_level'] ?? '');
    $tags = sanitizeInput($_POST['tags'] ?? '');
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Title is required.";
    } elseif (strlen($title) > 255) {
        $errors[] = "Title must be less than 255 characters.";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required.";
    }
    
    if (!in_array($education_level, ['JSS', 'CBC', 'University', 'College', 'General'])) {
        $errors[] = "Invalid education level.";
    }
    
    // Check if new file is being uploaded
    $newFileUploaded = false;
    $newFilename = $document['filename'];
    $newFilePath = $document['file_path'];
    $newFileType = $document['file_type'];
    $newFileSize = $document['file_size'];
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_file'];
        
        // Validate file type
        $allowedTypes = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        
        if (!array_key_exists($file['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG, GIF.";
        }
        
        // Validate file size (max 50MB)
        $maxFileSize = 50 * 1024 * 1024; // 50MB
        if ($file['size'] > $maxFileSize) {
            $errors[] = "File size exceeds maximum limit of 50MB.";
        }
        
        // Check if there are no errors for file upload
        if (empty($errors)) {
            $newFileUploaded = true;
            
            // Generate new filename
            $fileExt = $allowedTypes[$file['type']];
            $newFilename = generateUniqueFilename($file['name'], $fileExt);
            $newFilePath = UPLOAD_DIR . '/' . $newFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $newFilePath)) {
                $errors[] = "Failed to upload file. Please try again.";
            } else {
                // Update file info
                $newFileType = $file['type'];
                $newFileSize = $file['size'];
                
                // Archive old file (optional - you might want to keep old versions)
                $oldFilePath = $document['file_path'];
                if (file_exists($oldFilePath)) {
                    $archiveDir = UPLOAD_DIR . '/archive/' . date('Y/m');
                    if (!file_exists($archiveDir)) {
                        mkdir($archiveDir, 0777, true);
                    }
                    
                    $archiveFilename = $document['filename'] . '_v' . $document['version'] . '_' . date('Ymd_His');
                    $archivePath = $archiveDir . '/' . $archiveFilename;
                    rename($oldFilePath, $archivePath);
                }
            }
        }
    }
    
    // Process tags
    $tagArray = [];
    if (!empty($tags)) {
        $tagArray = array_map('trim', explode(',', $tags));
        $tagArray = array_filter($tagArray);
        $tagArray = array_unique($tagArray);
        $tags = implode(',', array_slice($tagArray, 0, 10)); // Limit to 10 tags
    }
    
    // If no errors, update document
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Create new version
            $newVersion = $document['version'] + 1;
            
            // Update current document to mark as not latest
            $updateStmt = $conn->prepare("
                UPDATE documents 
                SET is_latest = FALSE 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $document_id);
            $updateStmt->execute();
            
            // Insert new version
            $insertStmt = $conn->prepare("
                INSERT INTO documents (
                    user_id, title, original_title, description, 
                    filename, file_path, file_type, file_size,
                    category, subcategory, education_level, tags,
                    version, parent_version_id, is_latest,
                    download_count, view_count, average_rating, total_ratings,
                    report_count, is_approved
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, ?)
            ");
            
            $originalTitle = $document['original_title'] ?: $document['title'];
            
            $insertStmt->bind_param(
                "issssssissssiiisiisi",
                $user['id'],
                $title,
                $originalTitle,
                $description,
                $newFilename,
                $newFilePath,
                $newFileType,
                $newFileSize,
                $category,
                $subcategory,
                $education_level,
                $tags,
                $newVersion,
                $document_id,
                $document['download_count'],
                $document['view_count'],
                $document['average_rating'],
                $document['total_ratings'],
                $document['report_count'],
                $document['is_approved']
            );
            
            if ($insertStmt->execute()) {
                $newDocumentId = $conn->insert_id;
                
                // Log the edit action
                $logStmt = $conn->prepare("
                    INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $action = "Document edited";
                $details = json_encode([
                    'old_document_id' => $document_id,
                    'new_document_id' => $newDocumentId,
                    'changes' => [
                        'title' => $document['title'] !== $title ? [$document['title'], $title] : null,
                        'file_changed' => $newFileUploaded
                    ]
                ]);
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                
                $logStmt->bind_param("isss", $user['id'], $action, $details, $ipAddress);
                $logStmt->execute();
                
                $conn->commit();
                
                // Update session message
                $_SESSION['success'] = "Document updated successfully!";
                
                // Redirect to the new document
                header("Location: document-view.php?id=" . $newDocumentId);
                exit();
            } else {
                $errors[] = "Failed to update document. Please try again.";
                $conn->rollback();
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categoriesResult = $conn->query("
    SELECT DISTINCT category 
    FROM documents 
    WHERE category IS NOT NULL AND category != '' 
    ORDER BY category
");

// Get popular tags for suggestions
$tagsResult = $conn->query("
    SELECT tags 
    FROM documents 
    WHERE tags IS NOT NULL AND tags != ''
    LIMIT 20
");

$popularTags = [];
if ($tagsResult) {
    while ($row = $tagsResult->fetch_assoc()) {
        $docTags = explode(',', $row['tags']);
        foreach ($docTags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                $popularTags[$tag] = isset($popularTags[$tag]) ? $popularTags[$tag] + 1 : 1;
            }
        }
    }
    arsort($popularTags);
    $popularTags = array_slice(array_keys($popularTags), 0, 15);
}

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
                    <li class="breadcrumb-item"><a href="mylibrary.php">My Library</a></li>
                    <li class="breadcrumb-item active">Edit Document</li>
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
                            <h2 class="mb-2"><i class="fas fa-edit me-2"></i>Edit Document</h2>
                            <p class="text-muted mb-0">Update your document information</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="document-view.php?id=<?php echo $document['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View Document
                            </a>
                            <a href="mylibrary.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i>Back to Library
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Current Document Info -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Document Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Current File:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($document['filename']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>File Size:</strong><br>
                            <span class="text-muted"><?php echo formatFileSize($document['file_size']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Uploaded:</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y', strtotime($document['upload_date'])); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Version:</strong><br>
                            <span class="badge bg-secondary">v<?php echo $document['version']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Document Details</h5>
                </div>
                <div class="card-body">
                    <!-- Display Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <!-- Title -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Document Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($document['title']); ?>" 
                                           required maxlength="255">
                                    <div class="form-text">A clear, descriptive title for your document.</div>
                                </div>
                                
                                <!-- Description -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="4" required><?php echo htmlspecialchars($document['description']); ?></textarea>
                                    <div class="form-text">Describe what this document contains and its purpose.</div>
                                </div>
                                
                                <!-- Category -->
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($document['category']); ?>" 
                                           required list="categorySuggestions">
                                    <datalist id="categorySuggestions">
                                        <?php while ($cat = $categoriesResult->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                        <?php endwhile; ?>
                                    </datalist>
                                    <div class="form-text">e.g., Mathematics, Physics, Computer Science, etc.</div>
                                </div>
                                
                                <!-- Subcategory -->
                                <div class="mb-3">
                                    <label for="subcategory" class="form-label">Subcategory</label>
                                    <input type="text" class="form-control" id="subcategory" name="subcategory" 
                                           value="<?php echo htmlspecialchars($document['subcategory'] ?? ''); ?>">
                                    <div class="form-text">Optional: More specific category like "Calculus", "Algorithms", etc.</div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-6">
                                <!-- Education Level -->
                                <div class="mb-3">
                                    <label for="education_level" class="form-label">Education Level *</label>
                                    <select class="form-select" id="education_level" name="education_level" required>
                                        <option value="">Select level</option>
                                        <option value="JSS" <?php echo $document['education_level'] === 'JSS' ? 'selected' : ''; ?>>Junior Secondary School (JSS)</option>
                                        <option value="CBC" <?php echo $document['education_level'] === 'CBC' ? 'selected' : ''; ?>>Competency Based Curriculum (CBC)</option>
                                        <option value="University" <?php echo $document['education_level'] === 'University' ? 'selected' : ''; ?>>University</option>
                                        <option value="College" <?php echo $document['education_level'] === 'College' ? 'selected' : ''; ?>>College</option>
                                        <option value="General" <?php echo $document['education_level'] === 'General' ? 'selected' : ''; ?>>General Education</option>
                                    </select>
                                </div>
                                
                                <!-- Tags -->
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           value="<?php echo htmlspecialchars($document['tags'] ?? ''); ?>"
                                           data-bs-toggle="tooltip" title="Separate tags with commas">
                                    <div class="form-text">
                                        Keywords to help users find your document. 
                                        <span id="tagSuggestions" class="text-muted">
                                            Popular tags: 
                                            <?php echo implode(', ', array_slice($popularTags, 0, 5)); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- File Upload -->
                                <div class="mb-3">
                                    <label for="document_file" class="form-label">Replace File (Optional)</label>
                                    <input type="file" class="form-control" id="document_file" name="document_file" 
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                                    <div class="form-text">
                                        Max file size: 50MB. If you upload a new file, the old one will be archived.
                                        Current: <?php echo htmlspecialchars($document['filename']); ?>
                                    </div>
                                    
                                    <!-- File Preview -->
                                    <div class="mt-2" id="filePreview">
                                        <?php if ($document['file_type'] === 'application/pdf'): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                PDF Document
                                            </div>
                                        <?php elseif (strpos($document['file_type'], 'word') !== false): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-word text-primary me-2"></i>
                                                Word Document
                                            </div>
                                        <?php elseif (strpos($document['file_type'], 'excel') !== false): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-excel text-success me-2"></i>
                                                Excel Spreadsheet
                                            </div>
                                        <?php elseif (strpos($document['file_type'], 'powerpoint') !== false): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-powerpoint text-danger me-2"></i>
                                                PowerPoint Presentation
                                            </div>
                                        <?php elseif (strpos($document['file_type'], 'image') !== false): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-image text-warning me-2"></i>
                                                Image File
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-file-alt me-2"></i>
                                                Document File
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Document Statistics -->
                                <div class="card border-secondary mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Current Statistics</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <h5 class="text-primary"><?php echo $document['download_count']; ?></h5>
                                                <small class="text-muted">Downloads</small>
                                            </div>
                                            <div class="col-4">
                                                <h5 class="text-success"><?php echo $document['view_count']; ?></h5>
                                                <small class="text-muted">Views</small>
                                            </div>
                                            <div class="col-4">
                                                <h5 class="text-warning"><?php echo number_format($document['average_rating'], 1); ?></h5>
                                                <small class="text-muted">Rating</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewChanges()">
                                            <i class="fas fa-eye me-1"></i>Preview Changes
                                        </button>
                                        <button type="button" class="btn btn-outline-danger ms-2" onclick="confirmReset()">
                                            <i class="fas fa-redo me-1"></i>Reset Form
                                        </button>
                                    </div>
                                    <div>
                                        <a href="mylibrary.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary ms-2">
                                            <i class="fas fa-save me-1"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Version History -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Version History</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get version history
                    $versionStmt = $conn->prepare("
                        SELECT d1.* 
                        FROM documents d1 
                        WHERE (d1.id = ? OR d1.parent_version_id = ? OR d1.id IN (
                            SELECT parent_version_id FROM documents WHERE id = ?
                        ))
                        ORDER BY d1.version DESC
                    ");
                    $versionStmt->bind_param("iii", $document_id, $document_id, $document_id);
                    $versionStmt->execute();
                    $versionResult = $versionStmt->get_result();
                    
                    if ($versionResult->num_rows > 0):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Title</th>
                                    <th>File</th>
                                    <th>Upload Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($version = $versionResult->fetch_assoc()): ?>
                                <tr <?php echo $version['id'] == $document['id'] ? 'class="table-info"' : ''; ?>>
                                    <td>
                                        <span class="badge bg-<?php echo $version['is_latest'] ? 'primary' : 'secondary'; ?>">
                                            v<?php echo $version['version']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($version['title']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($version['filename']); ?><br>
                                            <span class="badge bg-light text-dark">
                                                <?php echo formatFileSize($version['file_size']); ?>
                                            </span>
                                        </small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($version['upload_date'])); ?></td>
                                    <td>
                                        <?php if ($version['is_latest']): ?>
                                            <span class="badge bg-success">Latest</span>
                                        <?php elseif ($version['id'] == $document['id']): ?>
                                            <span class="badge bg-info">Current</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Old Version</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$version['is_approved']): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="document-view.php?id=<?php echo $version['id']; ?>" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?id=<?php echo $version['id']; ?>" 
                                               class="btn btn-outline-success" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($version['id'] != $document['id'] && $version['is_latest']): ?>
                                                <a href="edit-document.php?id=<?php echo $version['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No version history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Changes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Current Version</h6>
                        <div class="card">
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($document['title']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($document['description']); ?></p>
                                <div class="mb-2">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($document['category']); ?></span>
                                    <?php if ($document['subcategory']): ?>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($document['subcategory']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Level: <?php echo $document['education_level']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>New Version</h6>
                        <div class="card border-primary">
                            <div class="card-body">
                                <h5 id="previewTitle"></h5>
                                <p class="text-muted" id="previewDescription"></p>
                                <div class="mb-2">
                                    <span class="badge bg-secondary" id="previewCategory"></span>
                                    <span class="badge bg-light text-dark" id="previewSubcategory"></span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Level: <span id="previewLevel"></span></small>
                                </div>
                                <div class="mt-3">
                                    <small class="text-primary">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Changes will create version v<?php echo $document['version'] + 1; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="submitForm()">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* File upload styling */
    .file-upload-container {
        border: 2px dashed #dee2e6;
        border-radius: 5px;
        padding: 20px;
        text-align: center;
        transition: border-color 0.3s;
    }
    
    .file-upload-container:hover {
        border-color: #007bff;
    }
    
    .file-upload-container.dragover {
        border-color: #28a745;
        background-color: rgba(40, 167, 69, 0.1);
    }
    
    /* Tag styling */
    .tag-badge {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .tag-badge:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Preview styling */
    .change-highlight {
        background-color: #fff3cd;
        padding: 2px 4px;
        border-radius: 3px;
    }
</style>

<script>
// File upload drag and drop
const fileInput = document.getElementById('document_file');
const fileUploadContainer = fileInput.parentElement;

fileUploadContainer.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadContainer.classList.add('dragover');
});

fileUploadContainer.addEventListener('dragleave', () => {
    fileUploadContainer.classList.remove('dragover');
});

fileUploadContainer.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadContainer.classList.remove('dragover');
    
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        
        // Show file info
        const file = e.dataTransfer.files[0];
        showFilePreview(file);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length) {
        showFilePreview(e.target.files[0]);
    }
});

function showFilePreview(file) {
    const preview = document.getElementById('filePreview');
    
    let fileTypeIcon = 'fa-file-alt';
    let fileTypeColor = 'text-secondary';
    let fileTypeText = 'Document File';
    
    if (file.type === 'application/pdf') {
        fileTypeIcon = 'fa-file-pdf';
        fileTypeColor = 'text-danger';
        fileTypeText = 'PDF Document';
    } else if (file.type.includes('word')) {
        fileTypeIcon = 'fa-file-word';
        fileTypeColor = 'text-primary';
        fileTypeText = 'Word Document';
    } else if (file.type.includes('excel')) {
        fileTypeIcon = 'fa-file-excel';
        fileTypeColor = 'text-success';
        fileTypeText = 'Excel Spreadsheet';
    } else if (file.type.includes('powerpoint')) {
        fileTypeIcon = 'fa-file-powerpoint';
        fileTypeColor = 'text-danger';
        fileTypeText = 'PowerPoint Presentation';
    } else if (file.type.includes('image')) {
        fileTypeIcon = 'fa-file-image';
        fileTypeColor = 'text-warning';
        fileTypeText = 'Image File';
    }
    
    preview.innerHTML = `
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas ${fileTypeIcon} ${fileTypeColor} fa-2x me-3"></i>
            <div>
                <strong>${file.name}</strong><br>
                <small class="text-muted">
                    ${fileTypeText} • ${formatFileSize(file.size)}
                </small>
            </div>
        </div>
    `;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Tag suggestions
const tagsInput = document.getElementById('tags');
const popularTags = <?php echo json_encode($popularTags); ?>;

// Auto-suggest tags
tagsInput.addEventListener('input', function() {
    // You could add tag autocomplete here
});

// Add popular tags when clicked
const tagSuggestions = document.getElementById('tagSuggestions');
tagSuggestions.addEventListener('click', function(e) {
    if (e.target.classList.contains('tag-suggestion')) {
        const tag = e.target.textContent.trim();
        const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);
        
        if (!currentTags.includes(tag)) {
            currentTags.push(tag);
            tagsInput.value = currentTags.join(', ');
        }
    }
});

// Preview changes
function previewChanges() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    // Get form values
    const title = formData.get('title');
    const description = formData.get('description');
    const category = formData.get('category');
    const subcategory = formData.get('subcategory');
    const education_level = formData.get('education_level');
    
    // Update preview
    document.getElementById('previewTitle').textContent = title;
    document.getElementById('previewDescription').textContent = description;
    document.getElementById('previewCategory').textContent = category;
    document.getElementById('previewSubcategory').textContent = subcategory;
    document.getElementById('previewLevel').textContent = education_level;
    
    // Show modal
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    previewModal.show();
}

// Submit form
function submitForm() {
    document.querySelector('form').submit();
}

// Reset form
function confirmReset() {
    if (confirm('Are you sure you want to reset all changes? All unsaved changes will be lost.')) {
        document.querySelector('form').reset();
        document.getElementById('filePreview').innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-file-alt me-2"></i>
                Current file: <?php echo htmlspecialchars($document['filename']); ?>
            </div>
        `;
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Character counter for title
const titleInput = document.getElementById('title');
const titleCounter = document.createElement('small');
titleCounter.className = 'form-text text-end d-block';
titleCounter.id = 'titleCounter';
titleInput.parentNode.appendChild(titleCounter);

function updateTitleCounter() {
    const currentLength = titleInput.value.length;
    const maxLength = titleInput.maxLength;
    titleCounter.textContent = `${currentLength}/${maxLength} characters`;
    titleCounter.className = `form-text text-end d-block ${currentLength > maxLength * 0.9 ? 'text-danger' : 'text-muted'}`;
}

titleInput.addEventListener('input', updateTitleCounter);
updateTitleCounter(); // Initial update
</script>

<?php
// Helper functions
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function generateUniqueFilename($originalName, $extension) {
    $filename = pathinfo($originalName, PATHINFO_FILENAME);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = substr($filename, 0, 50);
    return $filename . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
}


include 'includes/footer.php';
?>