<?php
header('Content-Type: application/json');

// Подключение к базе данных
$host = 'localhost';
$dbname = 'p95364dp_s';
$username = 'p95364dp_s';
$password = 'p95364dp_ss';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => "Ошибка подключения к базе данных: " . $e->getMessage()]));
}

// Получение мероприятий
if (isset($_GET['action']) && $_GET['action'] == 'get_events') {
    try {
        $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Логирование для отладки
        error_log("Найдено мероприятий: " . count($events));
        
        if (empty($events)) {
            echo json_encode(['debug' => 'Запрос выполнен успешно, но мероприятия не найдены']);
        } else {
            // Преобразование путей к изображениям
            foreach ($events as &$event) {
                if (!empty($event['image_path']) && !preg_match('/^https?:\/\//', $event['image_path'])) {
                    $event['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($event['image_path'], '/');
                }
            }
            echo json_encode($events);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка при получении мероприятий: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Не указано действие']);
}
?>