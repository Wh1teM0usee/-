<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: http://p95364dp.beget.tech");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Настройки логирования
$logDir = __DIR__ . '/api_logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$requestId = uniqid();
$logFile = $logDir . 'events_api_requests.log';

// Запись информации о запросе
file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

session_start();

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'p95364dp_s',
    'username' => 'p95364dp_s',
    'password' => 'p95364dp_ss'
];

function jsonResponse($success, $message, $data = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code($success ? 200 : 400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    switch ($action) {
        case 'get_events':
            // Получение всех активных мероприятий (с датой в будущем)
            $stmt = $pdo->prepare("
                SELECT id, title, description, image_path, event_date, created_at, updated_at 
                FROM events 
                WHERE event_date >= NOW()
                ORDER BY event_date ASC
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Преобразование путей к изображениям
            foreach ($events as &$event) {
                if (!empty($event['image_path']) && !preg_match('/^https?:\/\//', $event['image_path'])) {
                    $event['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($event['image_path'], '/');
                }
            }

            jsonResponse(true, 'Мероприятия успешно получены', $events);
            break;

        case 'get_event_details':
            if (empty($input['event_id'])) {
                jsonResponse(false, 'ID мероприятия не указан');
            }

            $stmt = $pdo->prepare("
                SELECT id, title, description, image_path, event_date, created_at, updated_at 
                FROM events 
                WHERE id = ?
            ");
            $stmt->execute([$input['event_id']]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                jsonResponse(false, 'Мероприятие не найдено');
            }

            if (!empty($event['image_path']) && !preg_match('/^https?:\/\//', $event['image_path'])) {
                $event['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($event['image_path'], '/');
            }

            jsonResponse(true, 'Данные мероприятия получены', $event);
            break;

        case 'add_event':
            // Проверка авторизации администратора
            if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                jsonResponse(false, 'Доступ запрещен');
            }

            $requiredFields = ['title', 'description', 'event_date'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO events 
                (title, description, image_path, event_date, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $input['title'],
                $input['description'],
                $input['image_path'] ?? null,
                $input['event_date']
            ]);

            $eventId = $pdo->lastInsertId();
            jsonResponse(true, 'Мероприятие успешно добавлено', ['event_id' => $eventId]);
            break;

        case 'update_event':
            // Проверка авторизации администратора
            if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                jsonResponse(false, 'Доступ запрещен');
            }

            if (empty($input['event_id'])) {
                jsonResponse(false, 'ID мероприятия не указан');
            }

            $fields = [];
            $params = [];
            $allowedFields = ['title', 'description', 'image_path', 'event_date'];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                }
            }

            if (empty($fields)) {
                jsonResponse(false, 'Нет данных для обновления');
            }

            $params[] = $input['event_id'];

            $sql = "UPDATE events SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            jsonResponse(true, 'Мероприятие успешно обновлено');
            break;

        case 'delete_event':
            // Проверка авторизации администратора
            if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                jsonResponse(false, 'Доступ запрещен');
            }

            if (empty($input['event_id'])) {
                jsonResponse(false, 'ID мероприятия не указан');
            }

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$input['event_id']]);

            jsonResponse(true, 'Мероприятие успешно удалено');
            break;

        default:
            jsonResponse(false, 'Неверное действие', [
                'available_actions' => [
                    'get_events', 
                    'get_event_details', 
                    'add_event', 
                    'update_event', 
                    'delete_event'
                ]
            ]);
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера', ['error' => $e->getMessage()]);
}