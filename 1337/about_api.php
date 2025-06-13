<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Request-ID");
header("Access-Control-Expose-Headers: X-Request-ID, X-Response-Time");
header("Access-Control-Allow-Credentials: true");

// Логирование в файл
function logContactRequest($message) {
    $logFile = __DIR__ . '/about_api.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    logContactRequest("OPTIONS запрос");
    exit(0);
}

$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();
header("X-Request-ID: $requestId");

$startTime = microtime(true);
logContactRequest("Начало обработки запроса [$requestId]");

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
            'response_time_ms' => $responseTime
        ]
    ];
    
    http_response_code($httpCode);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
    global $action;
    logContactRequest("Ответ [$requestId] для action=$action: " . json_encode([
        'status' => $httpCode,
        'success' => $success,
        'message' => $message,
        'time' => $responseTime . 'ms'
    ]));
    
    exit;
}

try {
    logContactRequest("[$requestId] Подключение к БД");
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logContactRequest("[$requestId] Подключение к БД успешно");

    // Получаем входные данные
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
    
    logContactRequest("[$requestId] Полученные данные: " . print_r($input, true));
    
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    logContactRequest("[$requestId] Action: $action");

    switch ($action) {
        case 'send_feedback':
            logContactRequest("[$requestId] Обработка send_feedback");
            
            // Валидация данных
            $required = ['name', 'email', 'message'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    logContactRequest("[$requestId] Ошибка: отсутствует поле $field");
                    jsonResponse(false, "Пожалуйста, заполните поле $field", [], 400);
                }
            }

            $name = trim($input['name']);
            $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
            $message = trim($input['message']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                logContactRequest("[$requestId] Ошибка: неверный email: $email");
                jsonResponse(false, 'Укажите корректный email адрес', [], 400);
            }

            logContactRequest("[$requestId] Данные валидны");
            
            // Сохранение в базу данных
            $stmt = $pdo->prepare("
                INSERT INTO feedback_messages 
                (name, email, message, request_id, created_at) 
                VALUES (:name, :email, :message, :request_id, NOW())
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':message' => $message,
                ':request_id' => $requestId
            ]);
            
            $messageId = $pdo->lastInsertId();
            logContactRequest("[$requestId] Сообщение сохранено в БД, ID: $messageId");
            
            jsonResponse(true, 'Сообщение успешно отправлено', [
                'message_id' =>$messageId
]);
break;

    default:
        logContactRequest("[$requestId] Неизвестный action: $action");
        jsonResponse(false, 'Неизвестное действие', [], 404);
}

} catch (PDOException $e) {
    logContactRequest("[requestId] Ошибка БД: " . $e->getMessage());
    jsonResponse(false, 'Ошибка базы данных: ' . $e->getMessage(), [], 500);
} catch (Exception $e) {
    logContactRequest("[requestId] Ошибка: " . $e->getMessage());
    jsonResponse(false, 'Ошибка сервера: ' . $e->getMessage(), [], 500);
}
