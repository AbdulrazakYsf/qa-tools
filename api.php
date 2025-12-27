<?php
/**
 * QA Tools Public API
 * Access tools via JSON API using an API Key.
 */

require_once 'tool_runners.php';

// Define the headers for the API response.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle Options
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Authentication
$apiKey = '';

// Try Header: "Authorization: Bearer <KEY>"
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Fallback for Apache/CGI where $_SERVER['HTTP_AUTHORIZATION'] might be stripped
if (!$authHeader && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }
}

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $apiKey = $matches[1];
}

// Try Query Param: "?api_key=<KEY>"
if (!$apiKey && isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing API Key. Provide "Authorization: Bearer <key>" header or "?api_key=<key>" query param.']);
    exit;
}

try {
    $db = get_db_auth();
    $stmt = $db->prepare("SELECT id, name, role FROM qa_users WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or inactive API Key.']);
        exit;
    }

    // 2. Parse Input
    $inputData = [];
    $toolCode = null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $toolCode = $_GET['tool'] ?? null;
        $inputData = $_GET;
        // Clean up
        unset($inputData['tool']);
        unset($inputData['api_key']);
    } else {
        // POST / PUT
        $rawJson = file_get_contents('php://input');
        $json = json_decode($rawJson, true) ?? [];

        // Determine Tool: Logic = JSON['tool'] -> Query['tool']
        $toolCode = $json['tool'] ?? ($_GET['tool'] ?? null);

        // Determine Data
        // Backward compatibility: if 'input' key exists, use it.
        // Otherwise, use the whole body (minus reserved keys).
        if (isset($json['input']) && is_array($json['input'])) {
            $inputData = $json['input'];
        } else {
            $inputData = $json;
            unset($inputData['tool']);
            unset($inputData['api_key']);
        }
    }

    if (!$toolCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Request. "tool" parameter is required (in URL or JSON body).']);
        exit;
    }

    $data = $inputData;

    // 3. Routing
    // Map tool codes to ToolRunner methods
    $result = null;

    switch ($toolCode) {
        // --- Migrated Tools ---
        case 'add_to_cart':
            $result = ToolRunner::run_add_to_cart($data);
            break;
        case 'brand':
            $result = ToolRunner::run_brand($data);
            break;
        case 'cms':
            $result = ToolRunner::run_cms($data);
            break;
        case 'products':
            $result = ToolRunner::run_products($data);
            break;
        case 'category':
            $result = ToolRunner::run_category($data);
            break;
        case 'sku':
            $result = ToolRunner::run_sku($data);
            break;
        case 'stock':
            $result = ToolRunner::run_stock($data);
            break;
        case 'headers_check':
            $result = ToolRunner::run_headers_check($data);
            break;
        case 'speed_test':
            $result = ToolRunner::run_speed_test($data);
            break;
        case 'json_validator':
            $result = ToolRunner::run_json_validator($data);
            break;
        case 'asset_count':
            $result = ToolRunner::run_asset_count($data);
            break;
        case 'images':
            $result = ToolRunner::run_images($data);
            break;
        case 'link_extractor':
            $result = ToolRunner::run_link_extractor($data);
            break;
        case 'get_categories':
            $result = ToolRunner::run_get_categories($data);
            break;
        case 'sub_category':
            $result = ToolRunner::run_sub_category($data);
            break;
        case 'category_filter':
            $result = ToolRunner::run_category_filter($data);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => "Tool '$toolCode' not supported via API."]);
            exit;
    }

    // 4. Response
    echo json_encode([
        'status' => 'success',
        'tool' => $toolCode,
        'user' => $user['name'],
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error: ' . $e->getMessage()]);
}
