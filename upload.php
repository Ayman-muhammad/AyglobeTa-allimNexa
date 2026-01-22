<?php
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'classes/Document.php'; // Adjust the path if needed
require_once 'classes/Database.php';
require_once __DIR__ . '/classes/User.php';



$page_title = "Upload Document - Wezo Campus Hub";

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

/*// Check if user is verified
if (!$_SESSION['is_verified']) {
    $_SESSION['message'] = 'Please verify your email before uploading documents.';
    $_SESSION['message_type'] = 'warning';
    header("Location: dashboard.php");
    exit();
}*/

$error = '';
$success = '';
$categories = [];
$subcategories = [];

// Load categories from database
$conn = getDBConnection();
$result = $conn->query("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}

$result = $conn->query("SELECT DISTINCT subcategory FROM documents WHERE subcategory IS NOT NULL AND subcategory != '' ORDER BY subcategory");
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row['subcategory'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Check rate limiting
        if (!checkRateLimit('document_upload', 10, 3600)) {
            $error = 'Too many upload attempts. Please try again later.';
        } else {
            // Validate inputs
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $subcategory = sanitizeInput($_POST['subcategory'] ?? '');
            $education_level = sanitizeInput($_POST['education_level'] ?? '');
            $tags = sanitizeInput($_POST['tags'] ?? '');
            $custom_category = sanitizeInput($_POST['custom_category'] ?? '');
            
            // Use custom category if provided
            if (!empty($custom_category) && empty($category)) {
                $category = $custom_category;
            }
            
            // Validate file
            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a file to upload.';
            } elseif ($_FILES['document']['size'] > MAX_FILE_SIZE) {
                $error = 'File size exceeds maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.';
            } else {
                // Validate file type
                $fileInfo = pathinfo($_FILES['document']['name']);
                $fileExtension = strtolower($fileInfo['extension'] ?? '');
                
                if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
                    $error = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES);
                } else {
                    // Generate unique filename
                    $originalFilename = $_FILES['document']['name'];
                    $uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
                    $uploadPath = UPLOAD_PATH . $uniqueFilename;
                    
                    // Ensure upload directory exists
                    if (!is_dir(UPLOAD_PATH)) {
                        mkdir(UPLOAD_PATH, 0777, true);
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
                        // Get file info
                        $fileSize = $_FILES['document']['size'];
                        $fileType = mime_content_type($uploadPath);
                        
                        // Prepare document data
                        $documentData = [
                            'user_id' => $_SESSION['user_id'],
                            'title' => $title,
                            'original_title' => $originalFilename,
                            'description' => $description,
                            'filename' => $uniqueFilename,
                            'file_path' => $uploadPath,
                            'file_type' => $fileExtension,
                            'file_size' => $fileSize,
                            'category' => $category,
                            'subcategory' => $subcategory,
                            'education_level' => $education_level,
                            'tags' => $tags,
                            'is_approved' => 1 // Auto-approve for now, can be changed
                        ];
                        
                        // Save to database
                        $document = new Document();
                        $documentId = $document->create($documentData);
                        
                        if ($documentId) {
                            $success = 'Document uploaded successfully!';
                            
                            // Log admin action
                            if ($auth->isAdmin()) {
                                $conn->query("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (
                                    {$_SESSION['user_id']}, 
                                    'document_upload',
                                    'Uploaded document: {$title}',
                                    '{$_SERVER['REMOTE_ADDR']}'
                                )");
                            }
                            
                            // Clear form
                            $_POST = [];
                        } else {
                            $error = 'Failed to save document information. Please try again.';
                            // Delete uploaded file
                            unlink($uploadPath);
                        }
                    } else {
                        $error = 'Failed to upload file. Please try again.';
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Upload Document</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-upload me-2"></i>Upload New Document</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Document Details -->
                                <div class="mb-4">
                                    <h5><i class="fas fa-file-alt me-2"></i>Document Details</h5>
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">
                                            <i class="fas fa-heading me-1"></i>Document Title *
                                        </label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                               required maxlength="255">
                                        <div class="form-text">Clear, descriptive title helps others find your document</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">
                                            <i class="fas fa-align-left me-1"></i>Description
                                        </label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="4" maxlength="1000"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="form-text">Brief description of the document content</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">
                                                    <i class="fas fa-tag me-1"></i>Category
                                                </label>
                                                <select class="form-select" id="category" name="category">
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                        <?php echo (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="custom_category" class="form-label">
                                                    <i class="fas fa-plus me-1"></i>New Category (if not listed)
                                                </label>
                                                <input type="text" class="form-control" id="custom_category" name="custom_category" 
                                                       value="<?php echo isset($_POST['custom_category']) ? htmlspecialchars($_POST['custom_category']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="subcategory" class="form-label">
                                                    <i class="fas fa-tags me-1"></i>Subcategory
                                                </label>
                                                <select class="form-select" id="subcategory" name="subcategory">
                                                    <option value="">Select Subcategory</option>
                                                    <?php foreach ($subcategories as $subcat): ?>
                                                    <option value="<?php echo htmlspecialchars($subcat); ?>" 
                                                        <?php echo (isset($_POST['subcategory']) && $_POST['subcategory'] === $subcat) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($subcat); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="education_level" class="form-label">
                                                    <i class="fas fa-graduation-cap me-1"></i>Education Level *
                                                </label>
                                                <select class="form-select" id="education_level" name="education_level" required>
                                                    <option value="">Select Level</option>
                                                    <option value="JSS" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'JSS') ? 'selected' : ''; ?>>Junior Secondary (JSS)</option>
                                                    <option value="CBC" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'CBC') ? 'selected' : ''; ?>>Competency Based Curriculum (CBC)</option>
                                                    <option value="University" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'University') ? 'selected' : ''; ?>>University</option>
                                                    <option value="College" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'College') ? 'selected' : ''; ?>>College</option>
                                                    <option value="General" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'General') ? 'selected' : ''; ?>>General Education</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tags" class="form-label">
                                            <i class="fas fa-hashtag me-1"></i>Tags
                                        </label>
                                        <input type="text" class="form-control" id="tags" name="tags" 
                                               value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>">
                                        <div class="form-text">Separate tags with commas (e.g., mathematics, algebra, grade-7)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- File Upload -->
                                <div class="mb-4">
                                    <h5><i class="fas fa-file-upload me-2"></i>File Upload</h5>
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="document" class="form-label">
                                            <i class="fas fa-paperclip me-1"></i>Select Document *
                                        </label>
                                        <input type="file" class="form-control" id="document" name="document" 
                                               accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.xlsx" required>
                                        <div class="form-text">
                                            Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB<br>
                                            Allowed: <?php echo implode(', ', ALLOWED_FILE_TYPES); ?>
                                        </div>
                                    </div>
                                    
                                    <div id="filePreview" class="d-none">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6>File Preview</h6>
                                                <div id="previewContent"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <h6><i class="fas fa-info-circle me-1"></i>Upload Guidelines:</h6>
                                        <ul class="mb-0 small">
                                            <li>Only upload educational content</li>
                                            <li>Ensure you have rights to share the content</li>
                                            <li>Documents will be reviewed before publishing</li>
                                            <li>Provide accurate descriptions and tags</li>
                                            <li>Respect copyright and intellectual property</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Copyright Notice -->
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="fas fa-copyright me-1"></i>Copyright Notice</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="small mb-0">
                                            By uploading, you confirm that you have the right to share this document.
                                            All content should be original or properly attributed. Wezo Campus Hub
                                            respects intellectual property rights.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Submit Section -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                            <label class="form-check-label" for="agreeTerms">
                                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#uploadTermsModal">upload terms</a>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <button type="reset" class="btn btn-secondary">
                                            <i class="fas fa-redo me-1"></i>Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload me-1"></i>Upload Document
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
    
    <!-- Upload Stats -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Your Upload Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    $user = new User($_SESSION['user_id']);
                    $stats = $user->getStats();
                    ?>
                    <div class="row text-center">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $stats['document_count'] ?? 0; ?></h3>
                                <p class="mb-0">Total Documents</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $stats['today_uploads'] ?? 0; ?></h3>
                                <p class="mb-0">Today's Uploads</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-info"><?php echo $stats['total_downloads'] ?? 0; ?></h3>
                                <p class="mb-0">Total Downloads</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning"><?php echo $stats['rating_count'] ?? 0; ?></h3>
                                <p class="mb-0">Ratings Received</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Terms Modal -->
<div class="modal fade" id="uploadTermsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Terms & Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Content Ownership</h6>
                <p>You must own the rights to any content you upload or have permission to share it.</p>
                
                <h6>2. Educational Purpose</h6>
                <p>All uploaded content must be educational and appropriate for academic use.</p>
                
                <h6>3. Content Guidelines</h6>
                <p>Content must not contain offensive, illegal, or inappropriate material.</p>
                
                <h6>4. Quality Standards</h6>
                <p>Upload clear, readable documents with accurate descriptions.</p>
                
                <h6>5. Review Process</h6>
                <p>All documents are subject to review and may be removed if they violate guidelines.</p>
                
                <h6>6. Copyright Compliance</h6>
                <p>You are responsible for ensuring your uploads comply with copyright laws.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File preview
    const fileInput = document.getElementById('document');
    const filePreview = document.getElementById('filePreview');
    const previewContent = document.getElementById('previewContent');
    
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const fileName = file.name;
            const fileType = file.type;
            const extension = fileName.split('.').pop().toLowerCase();
            
            let icon = 'fas fa-file';
            if (extension === 'pdf') icon = 'fas fa-file-pdf text-danger';
            else if (['doc', 'docx'].includes(extension)) icon = 'fas fa-file-word text-primary';
            else if (['ppt', 'pptx'].includes(extension)) icon = 'fas fa-file-powerpoint text-warning';
            else if (extension === 'txt') icon = 'fas fa-file-alt text-secondary';
            else if (extension === 'xlsx') icon = 'fas fa-file-excel text-success';
            
            previewContent.innerHTML = `
                <div class="d-flex align-items-center mb-3">
                    <i class="${icon} fa-3x me-3"></i>
                    <div>
                        <strong>${fileName}</strong><br>
                        <small class="text-muted">${fileType} | ${fileSize} MB</small>
                    </div>
                </div>
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         style="width: 100%">Ready to upload</div>
                </div>
            `;
            
            filePreview.classList.remove('d-none');
        } else {
            filePreview.classList.add('d-none');
        }
    });
    
    // Form validation
    const form = document.getElementById('uploadForm');
    form.addEventListener('submit', function(e) {
        const fileInput = document.getElementById('document');
        const maxSize = <?php echo MAX_FILE_SIZE; ?>;
        
        if (fileInput.files.length > 0 && fileInput.files[0].size > maxSize) {
            e.preventDefault();
            alert('File size exceeds maximum limit. Please select a smaller file.');
            return false;
        }
        
        const agreeTerms = document.getElementById('agreeTerms');
        if (!agreeTerms.checked) {
            e.preventDefault();
            alert('Please agree to the upload terms.');
            return false;
        }
        
        return true;
    });
    
    // Character counters
    const titleInput = document.getElementById('title');
    const descInput = document.getElementById('description');
    const tagsInput = document.getElementById('tags');
    
    function updateCharCount(input, max) {
        const count = input.value.length;
        const counter = input.nextElementSibling;
        if (counter && counter.classList.contains('form-text')) {
            const text = counter.textContent.split('(')[0];
            counter.textContent = text + ` (${count}/${max} characters)`;
            
            if (count > max * 0.9) {
                counter.classList.add('text-warning');
            } else {
                counter.classList.remove('text-warning');
            }
        }
    }
    
    if (titleInput) {
        titleInput.addEventListener('input', () => updateCharCount(titleInput, 255));
    }
    
    if (descInput) {
        descInput.addEventListener('input', () => updateCharCount(descInput, 1000));
    }
    
    if (tagsInput) {
        tagsInput.addEventListener('input', () => updateCharCount(tagsInput, 255));
    }
});
</script>

<?php include 'includes/footer.php'; ?>