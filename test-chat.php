<?php
// test-chat.php
echo "<h2>Testing Chat API Connection</h2>";

// Test 1: Check if file exists
$apiFile = 'api/chatbot-api.php';
echo "Test 1: API file exists? ";
echo file_exists($apiFile) ? "✅ Yes" : "❌ No";
echo "<br><br>";

// Test 2: Test direct API call
echo "Test 2: Direct API call<br>";
$url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/chatbot-api.php';
echo "URL: $url<br>";

$data = json_encode(['message' => 'hello']);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data
    ]
]);

$response = @file_get_contents($url, false, $context);
echo "Response: " . htmlspecialchars($response) . "<br>";
echo "Success: " . ($response ? "✅" : "❌");

// Test 3: Check PHP errors
echo "<br><br>Test 3: Checking for PHP errors<br>";
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>