<?php
// api/chatbot-api.php - ENHANCED VERSION WITH DOCUMENT SEARCH
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$user_id = $input['user_id'] ?? null;
$session_id = $input['session_id'] ?? 'chat_' . time();

// Include database connection if needed
$hasDatabase = false;
if (file_exists('../includes/db.php')) {
    require_once '../includes/db.php';
    $hasDatabase = true;
}

// Simple response function with document search
function getResponse($message, $hasDatabase = false) {
    $message = strtolower(trim($message));
    
    if (empty($message)) {
        return [
            'response' => "Hello! I'm your Wezo Campus Hub assistant. How can I help you find educational resources today?",
            'questions' => ['Find documents', 'How to upload?', 'Browse resources'],
            'documents' => []
        ];
    }
    
    // Default documents array
    $documents = [];
    $response = "";
    
    // Search for documents based on keywords
    if ($hasDatabase) {
        // Try to search real database
        $documents = searchDatabase($message);
    }
    
    // If no database results, use mock data based on keywords
    if (empty($documents)) {
        $documents = getMockDocuments($message);
    }
    
    // Greeting
    if (strpos($message, 'hello') !== false || 
        strpos($message, 'hi') !== false || 
        strpos($message, 'hey') !== false) {
        $response = "Hello! 👋 Welcome to Wezo Campus Hub. I'm here to help you find educational resources. What would you like to explore today?";
        $questions = ['Find JSS notes', 'CBC resources', 'University materials'];
    }
    
    // Search queries
    else if (strpos($message, 'find') !== false || 
             strpos($message, 'search') !== false || 
             strpos($message, 'looking') !== false ||
             strpos($message, 'get') !== false ||
             strpos($message, 'need') !== false) {
        
        $keyword = extractMainKeyword($message);
        $count = count($documents);
        
        if ($count > 0) {
            $response = "✅ I found {$count} document" . ($count > 1 ? 's' : '') . " matching your search for '{$keyword}':";
            $questions = ['Find more documents', 'Search other subjects', 'Browse all resources'];
        } else {
            $response = "I'm searching for documents related to '{$keyword}'. Try being more specific or browse these popular categories:";
            $questions = ['Mathematics documents', 'Science resources', 'JSS materials', 'University notes'];
            // Get popular documents instead
            $documents = getPopularMockDocuments();
        }
    }
    
    // Subject-specific queries
    else if (strpos($message, 'math') !== false || 
             strpos($message, 'mathematics') !== false) {
        $response = "📐 Here are Mathematics resources I found:";
        $questions = ['Algebra worksheets', 'Calculus notes', 'Geometry formulas', 'More Mathematics'];
    }
    
    else if (strpos($message, 'science') !== false || 
             strpos($message, 'physics') !== false || 
             strpos($message, 'chemistry') !== false || 
             strpos($message, 'biology') !== false) {
        $response = "🔬 Here are Science resources I found:";
        $questions = ['Physics experiments', 'Chemistry labs', 'Biology diagrams', 'More Science'];
    }
    
    else if (strpos($message, 'english') !== false || 
             strpos($message, 'literature') !== false || 
             strpos($message, 'grammar') !== false) {
        $response = "📚 Here are English resources I found:";
        $questions = ['Grammar exercises', 'Literature notes', 'Writing guides', 'More English'];
    }
    
    // Education level queries
    else if (strpos($message, 'jss') !== false) {
        $response = "🏫 Here are JSS (Junior Secondary School) resources:";
        $questions = ['JSS Mathematics', 'JSS Science', 'JSS English', 'JSS Social Studies'];
    }
    
    else if (strpos($message, 'cbc') !== false) {
        $response = "🎓 Here are CBC (Competency Based Curriculum) resources:";
        $questions = ['CBC Grade 4', 'CBC Grade 5', 'CBC Grade 6', 'CBC Assessments'];
    }
    
    else if (strpos($message, 'university') !== false || strpos($message, 'uni') !== false) {
        $response = "🎓 Here are University resources:";
        $questions = ['University notes', 'Research papers', 'Lecture slides', 'Past exams'];
    }
    
    else if (strpos($message, 'college') !== false) {
        $response = "📖 Here are College resources:";
        $questions = ['Diploma notes', 'Technical guides', 'Practical manuals', 'College projects'];
    }
    
    // Help queries
    else if (strpos($message, 'help') !== false || strpos($message, 'how to') !== false) {
        $response = "I'm here to help! Here's what I can assist you with:\n\n📚 **Document Discovery**\n• Search for educational resources\n• Browse by subject or education level\n\n🔄 **Platform Features**\n• Upload your own documents\n• Download resources for study\n• Rate and review documents\n\n🎓 **Education Systems**\n• JSS, CBC, University & College materials";
        $questions = ['How to upload?', 'How to search?', 'What can I download?'];
    }
    
    // Default response
    else {
        $keyword = extractMainKeyword($message);
        $count = count($documents);
        
        if ($count > 0) {
            $response = "I understand you're asking about '{$keyword}'. Here are {$count} related document" . ($count > 1 ? 's' : '') . " I found:";
            $questions = ['Find more documents', 'Search other topics', 'Browse categories'];
        } else {
            $response = "I understand you're asking about: \"{$message}\"\n\nAs your Wezo assistant, I specialize in educational resources. Try asking about:\n\n• Specific subjects (Mathematics, Science, English)\n• Education levels (JSS, CBC, University, College)\n• How to use platform features";
            $questions = ['Find Mathematics', 'Search Science', 'Browse JSS', 'How to upload?'];
        }
    }
    
    return [
        'response' => $response,
        'documents' => $documents,
        'questions' => $questions
    ];
}

// Extract main keyword from message
function extractMainKeyword($message) {
    $keywords = ['math', 'mathematics', 'science', 'english', 'jss', 'cbc', 'university', 'college', 'notes', 'documents', 'papers'];
    
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            return $keyword;
        }
    }
    
    // Return first meaningful word
    $words = explode(' ', $message);
    foreach ($words as $word) {
        if (strlen($word) > 3 && !in_array($word, ['find', 'search', 'looking', 'need', 'want'])) {
            return $word;
        }
    }
    
    return 'documents';
}

// Search database (if available)
function searchDatabase($message) {
    // This would connect to your actual database
    // For now, return empty array
    return [];
}

// Get mock documents based on keywords
function getMockDocuments($message) {
    $documents = [];
    $message = strtolower($message);
    
    // Mathematics related
    if (strpos($message, 'math') !== false || strpos($message, 'mathematics') !== false) {
        $documents = [
            [
                'id' => 101,
                'title' => 'JSS Mathematics Complete Notes - Grade 7-9',
                'education_level' => 'JSS',
                'category' => 'Mathematics',
                'average_rating' => 4.6,
                'download_count' => 421,
                'view_count' => 1250,
                'description' => 'Comprehensive Mathematics notes covering all JSS topics with examples and exercises.'
            ],
            [
                'id' => 102,
                'title' => 'Algebra Worksheets Collection',
                'education_level' => 'CBC',
                'category' => 'Mathematics',
                'average_rating' => 4.2,
                'download_count' => 189,
                'view_count' => 567,
                'description' => 'Practice worksheets for algebra concepts with answer keys.'
            ],
            [
                'id' => 103,
                'title' => 'University Calculus Notes',
                'education_level' => 'University',
                'category' => 'Mathematics',
                'average_rating' => 4.8,
                'download_count' => 312,
                'view_count' => 890,
                'description' => 'Detailed calculus notes covering limits, derivatives, and integrals.'
            ]
        ];
    }
    
    // Science related
    else if (strpos($message, 'science') !== false || 
             strpos($message, 'physics') !== false || 
             strpos($message, 'chemistry') !== false || 
             strpos($message, 'biology') !== false) {
        $documents = [
            [
                'id' => 201,
                'title' => 'Physics Experiments Guide',
                'education_level' => 'University',
                'category' => 'Science',
                'average_rating' => 4.7,
                'download_count' => 312,
                'view_count' => 950,
                'description' => 'Step-by-step guide for physics laboratory experiments.'
            ],
            [
                'id' => 202,
                'title' => 'Chemistry Lab Manual',
                'education_level' => 'College',
                'category' => 'Science',
                'average_rating' => 4.3,
                'download_count' => 201,
                'view_count' => 602,
                'description' => 'Complete chemistry laboratory manual with safety procedures.'
            ],
            [
                'id' => 203,
                'title' => 'Biology Diagrams Collection',
                'education_level' => 'JSS',
                'category' => 'Science',
                'average_rating' => 4.4,
                'download_count' => 278,
                'view_count' => 834,
                'description' => 'Detailed biological diagrams for JSS students.'
            ]
        ];
    }
    
    // JSS related
    else if (strpos($message, 'jss') !== false) {
        $documents = [
            [
                'id' => 301,
                'title' => 'JSS Mathematics Past Papers 2023',
                'education_level' => 'JSS',
                'category' => 'Mathematics',
                'average_rating' => 4.5,
                'download_count' => 345,
                'view_count' => 1035,
                'description' => 'Complete set of JSS Mathematics past papers with marking schemes.'
            ],
            [
                'id' => 302,
                'title' => 'JSS Science Revision Notes',
                'education_level' => 'JSS',
                'category' => 'Science',
                'average_rating' => 4.3,
                'download_count' => 287,
                'view_count' => 861,
                'description' => 'Comprehensive revision notes for JSS Science curriculum.'
            ],
            [
                'id' => 303,
                'title' => 'JSS English Grammar Guide',
                'education_level' => 'JSS',
                'category' => 'English',
                'average_rating' => 4.6,
                'download_count' => 234,
                'view_count' => 702,
                'description' => 'Complete English grammar guide for JSS students.'
            ]
        ];
    }
    
    // CBC related
    else if (strpos($message, 'cbc') !== false) {
        $documents = [
            [
                'id' => 401,
                'title' => 'CBC Grade 4 Mathematics Workbook',
                'education_level' => 'CBC',
                'category' => 'Mathematics',
                'average_rating' => 4.4,
                'download_count' => 456,
                'view_count' => 1368,
                'description' => 'Interactive Mathematics workbook for CBC Grade 4 students.'
            ],
            [
                'id' => 402,
                'title' => 'CBC Grade 5 Science Activities',
                'education_level' => 'CBC',
                'category' => 'Science',
                'average_rating' => 4.2,
                'download_count' => 389,
                'view_count' => 1167,
                'description' => 'Practical science activities for CBC Grade 5 curriculum.'
            ],
            [
                'id' => 403,
                'title' => 'CBC Grade 6 English Stories',
                'education_level' => 'CBC',
                'category' => 'English',
                'average_rating' => 4.7,
                'download_count' => 412,
                'view_count' => 1236,
                'description' => 'Collection of stories for CBC Grade 6 English lessons.'
            ]
        ];
    }
    
    // University related
    else if (strpos($message, 'university') !== false || strpos($message, 'uni') !== false) {
        $documents = [
            [
                'id' => 501,
                'title' => 'Computer Science Lecture Notes',
                'education_level' => 'University',
                'category' => 'Computer Science',
                'average_rating' => 4.8,
                'download_count' => 567,
                'view_count' => 1701,
                'description' => 'Complete lecture notes for Computer Science fundamentals.'
            ],
            [
                'id' => 502,
                'title' => 'Business Studies Research Papers',
                'education_level' => 'University',
                'category' => 'Business',
                'average_rating' => 4.5,
                'download_count' => 423,
                'view_count' => 1269,
                'description' => 'Collection of business studies research papers and case studies.'
            ],
            [
                'id' => 503,
                'title' => 'Engineering Mathematics Tutorials',
                'education_level' => 'University',
                'category' => 'Mathematics',
                'average_rating' => 4.6,
                'download_count' => 489,
                'view_count' => 1467,
                'description' => 'Engineering mathematics tutorials with solved problems.'
            ]
        ];
    }
    
    // Default popular documents
    else if (strpos($message, 'find') !== false || 
             strpos($message, 'search') !== false || 
             strpos($message, 'documents') !== false ||
             strpos($message, 'notes') !== false ||
             strpos($message, 'papers') !== false) {
        $documents = getPopularMockDocuments();
    }
    
    return $documents;
}

// Get popular mock documents
function getPopularMockDocuments() {
    return [
        [
            'id' => 601,
            'title' => 'KCSE Mathematics Past Papers (2015-2023)',
            'education_level' => 'Secondary',
            'category' => 'Mathematics',
            'average_rating' => 4.9,
            'download_count' => 1234,
            'view_count' => 3702,
            'description' => 'Complete collection of KCSE Mathematics past papers with solutions.'
        ],
        [
            'id' => 602,
            'title' => 'Biology Diagrams and Notes',
            'education_level' => 'Secondary',
            'category' => 'Science',
            'average_rating' => 4.7,
            'download_count' => 987,
            'view_count' => 2961,
            'description' => 'Detailed biology diagrams with comprehensive notes.'
        ],
        [
            'id' => 603,
            'title' => 'English Grammar Complete Guide',
            'education_level' => 'General',
            'category' => 'English',
            'average_rating' => 4.8,
            'download_count' => 876,
            'view_count' => 2628,
            'description' => 'Complete English grammar guide for all education levels.'
        ],
        [
            'id' => 604,
            'title' => 'Computer Programming Basics',
            'education_level' => 'College',
            'category' => 'Computer Science',
            'average_rating' => 4.6,
            'download_count' => 765,
            'view_count' => 2295,
            'description' => 'Introduction to computer programming with practical examples.'
        ]
    ];
}

// Get the response
$responseData = getResponse($message, $hasDatabase);

// Return the response
echo json_encode([
    'success' => true,
    'session_id' => $session_id,
    'response' => $responseData['response'],
    'documents' => $responseData['documents'],
    'questions' => $responseData['questions']
]);

// Log the request (for debugging)
file_put_contents('chat_log.txt', 
    date('Y-m-d H:i:s') . " - Message: '$message' - Documents: " . count($responseData['documents']) . "\n", 
    FILE_APPEND
);
?>