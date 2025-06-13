<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Настройки логирования
$logDir = __DIR__ . '/api_logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . 'api_requests.log';
file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

session_start();

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'p95364dp_s',
    'username' => 'p95364dp_s',
    'password' => 'p95364dp_ss'
];

function jsonResponse($success, $message, $data = [], $httpCode = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code($httpCode ?: ($success ? 200 : 400));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Если запрос пришел через браузер напрямую к API
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
        echo '<!DOCTYPE html><html><head><title>API Response</title></head><body>';
        echo '<pre>' . htmlspecialchars(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
        echo '</body></html>';
    }
    
    exit;
}

function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return ($user && $user['role'] === 'admin');
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

    // Если запрос пришел напрямую к API без параметров
    if (empty($action) && empty($_POST) && empty($input)) {
        jsonResponse(true, 'API is working', [
            'available_actions' => [
                'login', 'register', 'logout', 'check_auth', 'get_current_user',
                'book', 'check_availability', 'get_rooms', 'get_room',
                'admin_get_users', 'admin_get_bookings', 'admin_update_room',
                'admin_add_room', 'admin_delete_room'
            ]
        ], 200);
    }

    switch ($action) {
        case 'update_profile':
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Для обновления профиля необходимо авторизоваться');
            }

            $required = ['first_name', 'last_name', 'email'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }

            try {
                $stmt = $pdo->prepare("UPDATE users SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    email = :email, 
                    phone = :phone 
                    WHERE id = :id");
                
                $stmt->execute([
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'email' => $input['email'],
                    'phone' => $input['phone'] ?? null,
                    'id' => $_SESSION['user_id']
                ]);

                jsonResponse(true, 'Профиль успешно обновлен');
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка обновления профиля: ' . $e->getMessage());
            }
            break;

        case 'get_user_profile':
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Для просмотра профиля необходимо авторизоваться');
            }

            $requestingId = $_SESSION['user_id'];
            $isAdmin = isAdmin($pdo, $requestingId);

            $targetId = $requestingId;
            if ($isAdmin) {
                if (!empty($_GET['user_id'])) {
                    $targetId = (int)$_GET['user_id'];
                } elseif (!empty($input['user_id'])) {
                    $targetId = (int)$input['user_id'];
                }
            }

            if (!$isAdmin && $targetId !== $requestingId) {
                jsonResponse(false, 'У вас нет доступа к чужому профилю');
            }

            $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, avatar_path, role FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                jsonResponse(false, 'Пользователь не найден');
            }

            if (!empty($user['avatar_path']) && !preg_match('/^https?:\/\//', $user['avatar_path'])) {
                $user['avatar_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($user['avatar_path'], '/');
            }

            if (!$isAdmin) {
                unset($user['role']);
                unset($user['email']);
            }

            jsonResponse(true, 'Профиль пользователя получен', ['user' => $user]);
            break;

        case 'register':
            if (empty($input['username']) || empty($input['password'])) {
                jsonResponse(false, 'Имя пользователя и пароль обязательны');
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$input['username']]);
            
            if ($stmt->rowCount() > 0) {
                jsonResponse(false, 'Это имя пользователя уже занято');
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([
                $input['username'],
                password_hash($input['password'], PASSWORD_DEFAULT)
            ]);

            jsonResponse(true, 'Регистрация успешна', [
                'username' => $input['username']
            ]);
            break;
        
case 'book':
    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, 'Для бронирования необходимо авторизоваться');
    }

    $required = ['room_id', 'date_from', 'date_to', 'guests'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            jsonResponse(false, "Поле {$field} обязательно для заполнения");
        }
    }

    try {
        $pdo->beginTransaction();

        // Получаем информацию о номере
        $stmt = $pdo->prepare("SELECT id, title, price_per_night FROM rooms WHERE id = ? AND is_available = 1");
        $stmt->execute([$input['room_id']]);
        $room = $stmt->fetch();

        if (!$room) {
            jsonResponse(false, 'Этот номер недоступен для бронирования');
        }

        // Рассчитываем общую стоимость
        $checkIn = new DateTime($input['date_from']);
        $checkOut = new DateTime($input['date_to']);
        $nights = $checkOut->diff($checkIn)->days;
        $totalPrice = $nights * $room['price_per_night'];

        // Создаем бронирование
        $stmt = $pdo->prepare("INSERT INTO room_bookings 
            (room_id, user_id, check_in, check_out, guests, total_price, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
        
        $stmt->execute([
            $input['room_id'],
            $_SESSION['user_id'],
            $input['date_from'],
            $input['date_to'],
            $input['guests'],
            $totalPrice
        ]);

        $bookingId = $pdo->lastInsertId();

        // Получаем полные данные о бронировании для ответа
        $stmt = $pdo->prepare("
            SELECT b.*, r.title as room_title 
            FROM room_bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Обновляем статус номера
        $stmt = $pdo->prepare("UPDATE rooms SET is_available = 0 WHERE id = ?");
        $stmt->execute([$input['room_id']]);

        $pdo->commit();
        
        // Возвращаем полные данные о бронировании
        jsonResponse(true, 'Бронь успешно оформлена', [
            'booking' => $bookingData
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Ошибка бронирования: ' . $e->getMessage());
    }
    break;

        case 'check_availability':
            if (empty($input['room_id']) || empty($input['date_from']) || empty($input['date_to'])) {
                jsonResponse(false, 'Не указаны room_id, date_from или date_to');
            }

            try {
                if (strtotime($input['date_from']) >= strtotime($input['date_to'])) {
                    jsonResponse(false, 'Дата выезда должна быть позже даты заезда');
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_bookings 
                    WHERE room_id = ? 
                    AND status != 'cancelled'
                    AND (
                        (check_in <= ? AND check_out >= ?) OR
                        (check_in <= ? AND check_out >= ?) OR
                        (check_in >= ? AND check_out <= ?)
                    )");
                
                $stmt->execute([
                    $input['room_id'],
                    $input['date_from'], $input['date_from'],
                    $input['date_to'], $input['date_to'],
                    $input['date_from'], $input['date_to']
                ]);
                
                $result = $stmt->fetch();
                
                // Проверяем также общую доступность номера
                $stmt = $pdo->prepare("SELECT is_available FROM rooms WHERE id = ?");
                $stmt->execute([$input['room_id']]);
                $room = $stmt->fetch();
                
                $available = ($result['count'] == 0) && ($room && $room['is_available'] == 1);
                
                jsonResponse(true, 'Проверка завершена', [
                    'is_available' => $available
                ]);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка проверки доступности: ' . $e->getMessage());
            }
            break;

        case 'get_rooms':
            try {
                $stmt = $pdo->prepare("SELECT * FROM rooms");
                $stmt->execute();
                $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rooms as &$room) {
                    if (!empty($room['image_path']) && !preg_match('/^https?:\/\//', $room['image_path'])) {
                        $room['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($room['image_path'], '/');
                    }
                }

                jsonResponse(true, 'Номера успешно получены', $rooms);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;
            
        case 'get_room':
            if (empty($input['id']) && empty($_GET['id'])) {
                jsonResponse(false, 'ID номера обязателен');
            }
            
            $roomId = $input['id'] ?? $_GET['id'];
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
                $stmt->execute([$roomId]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$room) {
                    jsonResponse(false, 'Номер не найден');
                }

                if (!empty($room['image_path']) && !preg_match('/^https?:\/\//', $room['image_path'])) {
                    $room['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($room['image_path'], '/');
                }

                jsonResponse(true, 'Номер успешно получен', $room);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;
            
        case 'login':
            if (empty($input['username']) || empty($input['password'])) {
                jsonResponse(false, 'Имя пользователя и пароль обязательны');
            }

            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$input['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($input['password'], $user['password'])) {
                jsonResponse(false, 'Неверные учетные данные');
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            jsonResponse(true, 'Аутентифицирован', [
                'authenticated' => true,
                'username' => $user['username'],
                'role' => $user['role']
            ]);
            break;

        case 'logout':
            // Очищаем данные сессии
            $_SESSION = array();
            
            // Удаляем cookie сессии
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Уничтожаем сессию
            session_destroy();
            
            jsonResponse(true, 'Выход выполнен');
            break;
            
case 'check_auth':
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        jsonResponse(true, 'Аутентифицирован', [
            'authenticated' => true,
            'user' => [ // Добавляем полные данные пользователя
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    }
    jsonResponse(true, 'Не аутентифицирован', ['authenticated' => false]);
    break;

        case 'get_current_user':
            if (empty($_SESSION['user_id'])) {
                jsonResponse(true, 'Пользователь не аутентифицирован', ['authenticated' => false]);
            }
            
            $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                session_destroy();
                jsonResponse(true, 'Пользователь не найден', ['authenticated' => false]);
            }
            
            jsonResponse(true, 'Текущий пользователь', [
                'authenticated' => true,
                'user' => $user
            ]);
            break;

        case 'admin_get_users':
            if (empty($_SESSION['user_id']) || !isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен. Требуются права администратора');
            }

            try {
                $page = $input['page'] ?? 1;
                $perPage = 10;
                $offset = ($page - 1) * $perPage;
                
                $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                
                jsonResponse(true, 'Пользователи получены', [
                    'users' => $users,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage
                ]);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;

        case 'admin_get_bookings':
            if (empty($_SESSION['user_id']) || !isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен. Требуются права администратора');
            }

            try {
                $query = "SELECT b.*, r.title as room_title, u.username 
                          FROM room_bookings b
                          JOIN rooms r ON b.room_id = r.id
                          JOIN users u ON b.user_id = u.id
                          ORDER BY b.created_at DESC";
                          
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                jsonResponse(true, 'Бронирования получены', $bookings);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;

        case 'admin_update_room':
            if (empty($_SESSION['user_id']) || !isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен. Требуются права администратора');
            }

            if (empty($input['id'])) {
                jsonResponse(false, 'ID номера обязателен');
            }
            
            try {
                $allowedFields = ['title', 'description', 'price_per_night', 'capacity', 'image_path', 'is_available'];
                $updateData = [];
                
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateData[$field] = $input[$field];
                    }
                }
                
                if (empty($updateData)) {
                    jsonResponse(false, 'Нет данных для обновления');
                }
                
                $setParts = [];
                foreach ($updateData as $key => $value) {
                    $setParts[] = "$key = :$key";
                }
                
                $query = "UPDATE rooms SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $updateData['id'] = $input['id'];
                $stmt->execute($updateData);
                
                jsonResponse(true, 'Данные номера обновлены');
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;
            
        case 'admin_add_room':
            if (empty($_SESSION['user_id']) || !isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен. Требуются права администратора');
            }

            $required = ['title', 'description', 'price_per_night', 'capacity'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO rooms 
                    (title, description, price_per_night, capacity, image_path, is_available, created_at) 
                    VALUES (?, ?, ?, ?, ?, 1, NOW())");
                
                $stmt->execute([
                    $input['title'],
                    $input['description'],
                    $input['price_per_night'],
                    $input['capacity'],
                    $input['image_path'] ?? ''
                ]);
                
                jsonResponse(true, 'Номер успешно добавлен', ['room_id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка добавления номера: ' . $e->getMessage());
            }
            break;
            
        case 'admin_delete_room':
            if (empty($_SESSION['user_id']) || !isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен. Требуются права администратора');
            }

            if (empty($input['id'])) {
                jsonResponse(false, 'ID номера обязателен');
            }
            
            try {
                $pdo->beginTransaction();
                
                // Проверяем, есть ли активные бронирования для этого номера
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_bookings 
                    WHERE room_id = ? AND status != 'cancelled' AND check_out >= CURDATE()");
                $stmt->execute([$input['id']]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    jsonResponse(false, 'Нельзя удалить номер с активными бронированиями');
                }
                
                // Удаляем номер
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([$input['id']]);
                
                $pdo->commit();
                jsonResponse(true, 'Номер успешно удален');
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка удаления номера: ' . $e->getMessage());
            }
            break;

        default:
            jsonResponse(false, 'Неверное действие', [
                'available_actions' => [
                    'login', 'register', 'logout', 'check_auth', 'get_current_user',
                    'book', 'check_availability', 'get_rooms', 'get_room',
                    'admin_get_users', 'admin_get_bookings', 'admin_update_room',
                    'admin_add_room', 'admin_delete_room'
                ]
            ]);
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера', ['error' => $e->getMessage()]);
}