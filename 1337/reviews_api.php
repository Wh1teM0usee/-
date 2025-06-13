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
        case 'check_auth':
            // Проверка авторизации
            session_start();
            jsonResponse(true, '', ['logged_in' => isset($_SESSION['user_id'])]);
            break;

        case 'get_reviews':
            // Получение списка отзывов
            $stmt = $pdo->prepare("
                SELECT r.id, r.text, r.rating, r.created_at, u.username as author_name
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.is_published = 1
                ORDER BY r.created_at DESC
                LIMIT 50
            ");
            $stmt->execute();
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, 'Список отзывов получен', $reviews);
            break;

        case 'add_review':
            // Добавление нового отзыва
            session_start();
            
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Требуется авторизация', [], 401);
            }

            if (empty($input['text']) || empty($input['rating'])) {
                jsonResponse(false, 'Текст отзыва и оценка обязательны');
            }

            $text = trim($input['text']);
            $rating = (int)$input['rating'];
            
            if ($rating < 1 || $rating > 5) {
                jsonResponse(false, 'Оценка должна быть от 1 до 5');
            }
            
            if (mb_strlen($text) < 10) {
                jsonResponse(false, 'Текст отзыва слишком короткий');
            }

            if (mb_strlen($text) > 200) {
                jsonResponse(false, 'Текст отзыва не должен превышать 200 символов');
            }

            // Проверяем, не оставлял ли пользователь отзыв недавно
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM reviews 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                jsonResponse(false, 'Вы можете оставлять только один отзыв в день');
            }

            // Добавляем отзыв
            $stmt = $pdo->prepare("
                INSERT INTO reviews 
                (user_id, text, rating, is_published, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $text,
                $rating
            ]);

            $reviewId = $pdo->lastInsertId();
            
            jsonResponse(true, 'Отзыв успешно добавлен', [
                'review_id' => $reviewId
            ]);
            break;

        default:
            jsonResponse(false, 'Неизвестное действие', [
                'available_actions' => ['check_auth', 'get_reviews', 'add_review']
            ]);
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных: ' . $e->getMessage(), [], 500);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера: ' . $e->getMessage(), [], 500);
}