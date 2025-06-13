<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Подключение к базе данных
$host = 'localhost';
$dbname = 'p95364dp_s';
$username = 'p95364dp_s';
$password = 'p95364dp_ss';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_posts':
            $stmt = $db->query("SELECT id, title, SUBSTRING(content, 1, 150) as excerpt, image_path, created_at FROM blog_posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Добавляем полный URL к изображениям
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            foreach ($posts as &$post) {
                if ($post['image_path']) {
                    $post['image_path'] = $baseUrl . '/1337/images/blog/' . basename($post['image_path']);
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $posts,
                'meta' => [
                    'count' => count($posts),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'get_post':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception('Неверный ID статьи');
            }
            
            $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                if ($post['image_path']) {
                    $post['image_path'] = $baseUrl . '/1337/images/blog/' . basename($post['image_path']);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $post,
                    'meta' => [
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Статья не найдена',
                    'error_code' => 404
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Не указано действие',
                'available_actions' => ['get_posts', 'get_post']
            ]);
            break;
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage(),
        'error_code' => 500
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 400
    ]);
}