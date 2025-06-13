<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: http://p95364dp.beget.tech");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Подключение к базе данных
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'p95364dp_s',
    'username' => 'p95364dp_s',
    'password' => 'p95364dp_ss'
];

function jsonResponse($success, $message = '', $data = [], $httpCode = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    http_response_code($success ? ($httpCode ?: 200) : ($httpCode ?: 400));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем входные данные
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    switch ($action) {
        case 'get_services':
            // Получение списка услуг
            $stmt = $pdo->prepare("
                SELECT id, title, description, icon, price, image_path 
                FROM services 
                WHERE is_active = 1
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Обработка изображений
            foreach ($services as &$service) {
                if (!empty($service['image_path']) && !preg_match('/^https?:\/\//', $service['image_path'])) {
                    $service['image_path'] = 'http://p95364dp.beget.tech/' . ltrim($service['image_path'], '/');
                }
            }

            jsonResponse(true, 'Список услуг получен', $services);
            break;

        case 'book_service':
            // Заказ услуги
            session_start();
            
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Требуется авторизация', [], 401);
            }

            if (empty($input['service_id'])) {
                jsonResponse(false, 'Не указана услуга для заказа');
            }

            // Проверяем существование услуги
            $stmt = $pdo->prepare("SELECT id, title, price FROM services WHERE id = ? AND is_active = 1");
            $stmt->execute([$input['service_id']]);
            $service = $stmt->fetch();

            if (!$service) {
                jsonResponse(false, 'Услуга не найдена или недоступна');
            }

            // Создаем заказ
            $stmt = $pdo->prepare("
                INSERT INTO service_orders 
                (service_id, user_id, service_title, price, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $input['service_id'],
                $_SESSION['user_id'],
                $service['title'],
                $service['price']
            ]);

            $orderId = $pdo->lastInsertId();
            
            jsonResponse(true, 'Услуга успешно заказана', [
                'order_id' => $orderId,
                'service_title' => $service['title']
            ]);
            break;

        default:
            jsonResponse(false, 'Неизвестное действие', [
                'available_actions' => ['get_services', 'book_service']
            ]);
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных: ' . $e->getMessage(), [], 500);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера: ' . $e->getMessage(), [], 500);
}