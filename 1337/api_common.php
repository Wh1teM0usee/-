<?php
function jsonResponse($success, $message, $data = [], $httpCode = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code($httpCode ?: ($success ? 200 : 400));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
        echo '<!DOCTYPE html><html><head><title>API Response</title></head><body>';
        echo '<pre>' . htmlspecialchars(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
        echo '</body></html>';
    }
    
    exit;
}

function createDatabaseConnection() {
    $dbConfig = [
        'host' => 'localhost',
        'dbname' => 'p95364dp_s',
        'username' => 'p95364dp_s',
        'password' => 'p95364dp_ss'
    ];

    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    return $pdo;
}

function getRequestData() {
    return json_decode(file_get_contents('php://input'), true) ?: $_POST;
}

function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return ($user && $user['role'] === 'admin');
}