<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Request-ID");
header("Access-Control-Expose-Headers: X-Request-ID, X-Response-Time");
header("Access-Control-Allow-Credentials: true");

// Логирование в файл
function logGroupBookingRequest($message) {
    $logFile = __DIR__ . '/group_bookings_api.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    logGroupBookingRequest("OPTIONS запрос");
    exit(0);
}

$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();
header("X-Request-ID: $requestId");

$startTime = microtime(true);
logGroupBookingRequest("Начало обработки запроса [$requestId]");

// Подключение к базе данных
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'p95364dp_s',
    'username' => 'p95364dp_s',
    'password' => 'p95364dp_ss'
];

try {
    logGroupBookingRequest("[$requestId] Подключение к БД");
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logGroupBookingRequest("[$requestId] Подключение к БД успешно");

    // Получаем входные данные
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
    
    logGroupBookingRequest("[$requestId] Полученные данные: " . print_r($input, true));
    
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    logGroupBookingRequest("[$requestId] Action: $action");

    switch ($action) {
        case 'group_booking_request':
            logGroupBookingRequest("[$requestId] Обработка group_booking_request");
            
            // Проверяем обязательные поля
            $requiredFields = ['name', 'phone', 'email', 'checkin', 'checkout', 'guests', 'group_type'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Пожалуйста, заполните поле $field", [], 400);
                }
            }

            // Валидация данных
            $name = trim($input['name']);
            $phone = trim($input['phone']);
            $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
            $company = !empty($input['company']) ? trim($input['company']) : null;
            $checkin = $input['checkin'];
            $checkout = $input['checkout'];
            $guests = (int)$input['guests'];
            $rooms = !empty($input['rooms']) ? (int)$input['rooms'] : null;
            $groupType = $input['group_type'];
            $notes = !empty($input['notes']) ? trim($input['notes']) : null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Укажите корректный email адрес', [], 400);
            }

            if ($guests < 10) {
                jsonResponse(false, 'Групповое бронирование доступно от 10 человек', [], 400);
            }

            if (strtotime($checkin) >= strtotime($checkout)) {
                jsonResponse(false, 'Дата выезда должна быть позже даты заезда', [], 400);
            }

            logGroupBookingRequest("[$requestId] Данные валидны");
            
            // Проверяем существование таблицы
            $tableExists = $pdo->query("SHOW TABLES LIKE 'group_booking_requests'")->rowCount() > 0;
            if (!$tableExists) {
                // Создаем таблицу, если она не существует
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS group_booking_requests (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        phone VARCHAR(20) NOT NULL,
                        email VARCHAR(100) NOT NULL,
                        company VARCHAR(100) NULL,
                        checkin_date DATE NOT NULL,
                        checkout_date DATE NOT NULL,
                        guests_count INT NOT NULL,
                        rooms_count INT NULL,
                        group_type VARCHAR(50) NOT NULL,
                        notes TEXT NULL,
                        request_id VARCHAR(50) NULL,
                        status ENUM('new','processed') DEFAULT 'new',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }
            
            // Сохранение в базу данных
            $stmt = $pdo->prepare("
                INSERT INTO group_booking_requests 
                (name, phone, email, company, checkin_date, checkout_date, guests_count, rooms_count, group_type, notes, request_id) 
                VALUES (:name, :phone, :email, :company, :checkin, :checkout, :guests, :rooms, :group_type, :notes, :request_id)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':email' => $email,
                ':company' => $company,
                ':checkin' => $checkin,
                ':checkout' => $checkout,
                ':guests' => $guests,
                ':rooms' => $rooms,
                ':group_type' => $groupType,
                ':notes' => $notes,
                ':request_id' => $requestId
            ]);
            
            $bookingId = $pdo->lastInsertId();
            logGroupBookingRequest("[$requestId] Запрос сохранен в БД, ID: $bookingId");
            
            jsonResponse(true, 'Запрос на групповое бронирование успешно отправлен', [
                'request_id' => $bookingId
            ]);
            break;

        default:
            jsonResponse(false, 'Неизвестное действие', [], 404);
    }

} catch (PDOException $e) {
    logGroupBookingRequest("[$requestId] Ошибка БД: " . $e->getMessage());
    jsonResponse(false, 'Ошибка при обработке запроса: ' . $e->getMessage(), [], 500);
} catch (Exception $e) {
    logGroupBookingRequest("[$requestId] Ошибка: " . $e->getMessage());
    jsonResponse(false, 'Внутренняя ошибка сервера: ' . $e->getMessage(), [], 500);
}

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
    exit;
}