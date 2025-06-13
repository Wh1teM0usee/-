<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Устанавливаем соединение с базой данных
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]));
}

// Получаем действие из запроса
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_posts':
            $stmt = $pdo->query("SELECT * FROM blog ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            
            // Преобразуем даты в читаемый формат
            foreach ($posts as &$post) {
                $post['created_at'] = date('d.m.Y', strtotime($post['created_at']));
            }
            
            echo json_encode([
                'success' => true,
                'data' => $posts
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}