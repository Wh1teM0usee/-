<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Request-ID");
header("Access-Control-Expose-Headers: X-Request-ID, X-Response-Time");
header("Access-Control-Allow-Credentials: true");

// Логирование в файл
function logBlogRequest($message) {
    $logFile = __DIR__ . '/blog_api.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    logBlogRequest("OPTIONS запрос");
    exit(0);
}

$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();
header("X-Request-ID: $requestId");

$startTime = microtime(true);
logBlogRequest("Начало обработки запроса [$requestId]");

// Подключение к базе данных
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'p95364dp_s',
    'username' => 'p95364dp_s',
    'password' => 'p95364dp_ss'
];

function jsonResponse($success, $message = '', $data = [], $httpCode = 200) {
    global $startTime, $requestId;
    
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    header("X-Response-Time: {$responseTime}ms");
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'metadata' => [
            'request_id' => $requestId,
            'response_time_ms' => $responseTime,
            'timestamp' => date('c')
        ]
    ];
    
    http_response_code($httpCode);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    global $action;
    logBlogRequest("Ответ [$requestId] для action=$action: " . json_encode([
        'status' => $httpCode,
        'success' => $success,
        'message' => $message,
        'data_size' => is_array($data) ? count($data) : 0,
        'time' => $responseTime . 'ms'
    ], JSON_UNESCAPED_UNICODE));
    
    exit;
}

try {
    logBlogRequest("[$requestId] Подключение к БД");
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    logBlogRequest("[$requestId] Подключение к БД успешно");

    // Получаем параметры запроса
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? 0;
    
    logBlogRequest("[$requestId] Параметры запроса: action=$action, id=$id");

    switch ($action) {
        case 'get_posts':
            logBlogRequest("[$requestId] Получение списка статей");
            
            $stmt = $pdo->query("
                SELECT id, title, content, full_content, image_path, created_at 
                FROM blog 
                ORDER BY created_at DESC
            ");
            $posts = $stmt->fetchAll();
            
            if (empty($posts)) {
                logBlogRequest("[$requestId] Статьи не найдены");
                jsonResponse(true, 'Статьи не найдены', [], 200);
            }
            
            // Обработка путей к изображениям
            $processedPosts = array_map(function($post) {
                if (!empty($post['image_path'])) {
                    $post['image_path'] = str_replace(['!', '?'], ['1', '2'], $post['image_path']);
                } else {
                    $post['image_path'] = '/1337/images/blog-default.jpg';
                }
                return $post;
            }, $posts);
            
            logBlogRequest("[$requestId] Найдено статей: " . count($processedPosts));
            jsonResponse(true, 'Список статей получен', $processedPosts);
            break;

        case 'get_post':
            logBlogRequest("[$requestId] Получение статьи ID: $id");
            
            if (empty($id) || !is_numeric($id)) {
                logBlogRequest("[$requestId] Ошибка: неверный ID статьи");
                jsonResponse(false, 'Неверный ID статьи', [], 400);
            }
            
            $stmt = $pdo->prepare("
                SELECT id, title, content, full_content, image_path, created_at 
                FROM blog 
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch();
            
            if (empty($post)) {
                logBlogRequest("[$requestId] Статья не найдена");
                jsonResponse(false, 'Статья не найдена', [], 404);
            }
            
            // Если есть full_content, используем его вместо content
            if (!empty($post['full_content'])) {
                $post['content'] = $post['full_content'];
            }
            
            // Обработка пути к изображению
            if (!empty($post['image_path'])) {
                $post['image_path'] = str_replace(['!', '?'], ['1', '2'], $post['image_path']);
            } else {
                $post['image_path'] = '/1337/images/blog-default.jpg';
            }
            
            logBlogRequest("[$requestId] Статья найдена: " . $post['title']);
            jsonResponse(true, 'Статья получена', $post);
            break;

        default:
            logBlogRequest("[$requestId] Неизвестный action: $action");
            jsonResponse(false, 'Неизвестное действие', [], 404);
    }

} catch (PDOException $e) {
    logBlogRequest("[$requestId] Ошибка БД: " . $e->getMessage());
    jsonResponse(false, 'Ошибка базы данных', [
        'error_details' => $e->getMessage(),
        'error_code' => $e->getCode()
    ], 500);
} catch (Exception $e) {
    logBlogRequest("[$requestId] Ошибка: " . $e->getMessage());
    jsonResponse(false, 'Ошибка сервера', [
        'error_details' => $e->getMessage(),
        'error_code' => $e->getCode()
    ], 500);
}