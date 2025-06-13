<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/api_common.php';

session_start();

// Включение логов
file_put_contents('api_requests.log', 
    date('[Y-m-d H:i:s]') . " " . 
    $_SERVER['REQUEST_METHOD'] . " " . 
    $_SERVER['REQUEST_URI'] . "\n" .
    "Headers: " . print_r(getallheaders(), true) . "\n" .
    "POST: " . print_r($_POST, true) . "\n" .
    "GET: " . print_r($_GET, true) . "\n" .
    "INPUT: " . file_get_contents('php://input') . "\n\n",
    FILE_APPEND
);

try {
    $pdo = createDatabaseConnection();
    
    // Получаем входные данные
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' 
        ? (empty($_POST) ? json_decode(file_get_contents('php://input'), true) : $_POST)
        : $_GET;

    $action = $input['action'] ?? '';
    if (empty($action)) {
        jsonResponse(false, 'Параметр action обязателен', [], 400);
    }

    // Проверка авторизации для защищенных действий
    $protectedActions = ['update_user', 'delete_user', 'get_users'];
    if (in_array($action, $protectedActions) && empty($_SESSION['user_id'])) {
        jsonResponse(false, 'Требуется авторизация', [], 401);
    }

    switch ($action) {
        case 'get_users':
            // Только для администраторов
            if (!isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен', [], 403);
            }

            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT id, username, email, first_name, last_name, 
                       phone, avatar_path, role, birth_date,
                       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at
                FROM users 
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, 'Пользователи получены', [
                'users' => $users,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'get_user':
            $userId = $input['id'] ?? $_SESSION['user_id'] ?? 0;
            if (empty($userId)) {
                jsonResponse(false, 'ID пользователя обязателен');
            }

            $stmt = $pdo->prepare("
                SELECT id, username, email, first_name, last_name, 
                       phone, avatar_path, role, birth_date,
                       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at,
                       DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i') as updated_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                jsonResponse(false, 'Пользователь не найден');
            }

            // Обработка аватара
            if (!empty($user['avatar_path']) && !preg_match('/^https?:\/\//', $user['avatar_path'])) {
                $user['avatar_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($user['avatar_path'], '/');
            }

            jsonResponse(true, 'Данные пользователя получены', ['user' => $user]);
            break;

        case 'create_user':
            // Только для администраторов
            if (!isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен', [], 403);
            }

            $required = ['username', 'email', 'role'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }

            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Некорректный email');
            }

            // Проверка уникальности
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$input['username'], $input['email']]);
            if ($stmt->rowCount() > 0) {
                jsonResponse(false, 'Пользователь с таким именем или email уже существует');
            }

            $password = $input['password'] ?? bin2hex(random_bytes(4));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, password, email, first_name, last_name, 
                        phone, birth_date, role, created_at
                    ) VALUES (
                        :username, :password, :email, :first_name, :last_name,
                        :phone, :birth_date, :role, NOW()
                    )
                ");

                $stmt->execute([
                    ':username' => $input['username'],
                    ':password' => $hashedPassword,
                    ':email' => $input['email'],
                    ':first_name' => $input['first_name'] ?? null,
                    ':last_name' => $input['last_name'] ?? null,
                    ':phone' => $input['phone'] ?? null,
                    ':birth_date' => $input['birth_date'] ?? null,
                    ':role' => $input['role']
                ]);

                $userId = $pdo->lastInsertId();

                // Обработка аватара
                if (!empty($_FILES['avatar'])) {
                    $avatarPath = handleAvatarUpload($_FILES['avatar'], $userId);
                    if ($avatarPath) {
                        $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
                        $stmt->execute([$avatarPath, $userId]);
                    }
                }

                $pdo->commit();
                
                jsonResponse(true, 'Пользователь создан', [
                    'user_id' => $userId,
                    'generated_password' => empty($input['password']) ? $password : null
                ]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка создания пользователя: ' . $e->getMessage());
            }
            break;

        case 'update_user':
            $userId = $input['id'] ?? $_SESSION['user_id'] ?? 0;
            if (empty($userId)) {
                jsonResponse(false, 'ID пользователя обязателен', [], 400);
            }

            // Проверка прав (редактировать может только себя или админ)
            $isAdmin = isAdmin($pdo, $_SESSION['user_id']);
            if (!$isAdmin && $_SESSION['user_id'] != $userId) {
                jsonResponse(false, 'Недостаточно прав', [], 403);
            }

            $allowedFields = [
                'username', 'email', 'first_name', 'last_name',
                'phone', 'birth_date', 'avatar_path', 'password'
            ];
            
            $updateData = [];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'password' && !empty($input['password'])) {
                        $updateData['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
                    } else {
                        $updateData[$field] = $input[$field];
                    }
                }
            }

            if (empty($updateData)) {
                jsonResponse(false, 'Нет данных для обновления', [], 400);
            }

            try {
                $pdo->beginTransaction();
                
                $setParts = [];
                $params = [':id' => $userId];
                foreach ($updateData as $field => $value) {
                    $setParts[] = "$field = :$field";
                    $params[":$field"] = $value;
                }

                $query = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);

                // Обработка аватара
                if (!empty($_FILES['avatar'])) {
                    $avatarPath = handleAvatarUpload($_FILES['avatar'], $userId);
                    if ($avatarPath) {
                        $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
                        $stmt->execute([$avatarPath, $userId]);
                    }
                }

                $pdo->commit();
                jsonResponse(true, 'Данные обновлены');
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка обновления: ' . $e->getMessage());
            }
            break;

        case 'delete_user':
            if (!isAdmin($pdo, $_SESSION['user_id'])) {
                jsonResponse(false, 'Доступ запрещен', [], 403);
            }

            $userId = $input['id'] ?? 0;
            if (empty($userId)) {
                jsonResponse(false, 'ID пользователя обязателен');
            }

            // Нельзя удалить себя
            if ($userId == $_SESSION['user_id']) {
                jsonResponse(false, 'Вы не можете удалить самого себя');
            }

            try {
                $pdo->beginTransaction();

                // Проверка активных бронирований
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM room_bookings 
                    WHERE user_id = ? AND status != 'cancelled' AND check_out >= CURDATE()
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();

                if ($result['count'] > 0) {
                    jsonResponse(false, 'Нельзя удалить пользователя с активными бронированиями');
                }

                // Удаление пользователя
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);

                $pdo->commit();
                jsonResponse(true, 'Пользователь удален');
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка удаления: ' . $e->getMessage());
            }
            break;

        default:
            jsonResponse(false, 'Неизвестное действие', [
                'available_actions' => [
                    'get_users', 'get_user', 'create_user', 
                    'update_user', 'delete_user'
                ]
            ], 400);
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера', ['error' => $e->getMessage()]);
}

/**
 * Обработка загрузки аватара
 */
function handleAvatarUpload($file, $userId) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    if ($file['size'] > 2097152) { // 2MB
        return false;
    }

    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/avatars/' . $filename;
    }

    return false;
}