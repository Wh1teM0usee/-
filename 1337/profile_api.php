<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/api_common.php';

session_start();

try {
    $pdo = createDatabaseConnection();
    $input = getRequestData();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_profile':
            // Получение данных профиля текущего пользователя
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Для просмотра профиля необходимо авторизоваться');
            }

            $userId = $_SESSION['user_id'];
            
            // Получаем данные пользователя
            $stmt = $pdo->prepare("
                SELECT 
                    id, username, email, first_name, last_name, 
                    phone, avatar_path, role,
                    DATE_FORMAT(birth_date, '%Y-%m-%d') as birth_date,
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

            // Обработка пути к аватару
            if (!empty($user['avatar_path']) && !preg_match('/^https?:\/\//', $user['avatar_path'])) {
                $user['avatar_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($user['avatar_path'], '/');
            }

            // Получаем бронирования пользователя
            $stmt = $pdo->prepare("
                SELECT 
                    b.id, b.room_id, r.title as room_title, 
                    DATE_FORMAT(b.check_in, '%Y-%m-%d') as check_in,
                    DATE_FORMAT(b.check_out, '%Y-%m-%d') as check_out,
                    b.guests, b.total_price, b.status,
                    DATE_FORMAT(b.created_at, '%Y-%m-%d %H:%i') as created_at
                FROM room_bookings b
                JOIN rooms r ON b.room_id = r.id
                WHERE b.user_id = ?
                ORDER BY b.check_in DESC
            ");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, 'Данные профиля получены', [
                'user' => $user,
                'bookings' => $bookings
            ]);
            break;

        case 'update_profile':
            // Обновление данных профиля
            if (empty($_SESSION['user_id'])) {
                jsonResponse(false, 'Для обновления профиля необходимо авторизоваться');
            }

            $required = ['first_name', 'last_name', 'email'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }

            // Валидация email
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Укажите корректный email адрес');
            }

            try {
                $pdo->beginTransaction();

                // Проверяем уникальность email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$input['email'], $_SESSION['user_id']]);
                if ($stmt->rowCount() > 0) {
                    jsonResponse(false, 'Пользователь с таким email уже существует');
                }

                // Подготавливаем данные для обновления
                $updateFields = [];
                $params = [':id' => $_SESSION['user_id']];

                $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'birth_date'];
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateFields[] = "{$field} = :{$field}";
                        $params[":{$field}"] = $input[$field];
                    }
                }

                // Если есть новый аватар - обрабатываем
                if (!empty($_FILES['avatar'])) {
                    $avatarPath = handleAvatarUpload($_FILES['avatar'], $_SESSION['user_id']);
                    if ($avatarPath) {
                        $updateFields[] = "avatar_path = :avatar_path";
                        $params[':avatar_path'] = $avatarPath;
                    }
                }

                // Если есть что обновлять
                if (!empty($updateFields)) {
                    $updateFields[] = "updated_at = NOW()";
                    
                    $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                }

                $pdo->commit();
                jsonResponse(true, 'Профиль успешно обновлен');
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка обновления профиля: ' . $e->getMessage());
            }
            break;

        default:
            jsonResponse(false, 'Неизвестное действие', [
                'available_actions' => ['get_profile', 'update_profile']
            ]);
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера', ['error' => $e->getMessage()]);
}

/**
 * Обработка загрузки аватара пользователя
 */
function handleAvatarUpload($file, $userId) {
    // Проверка типа файла
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    // Проверка размера файла (максимум 2MB)
    if ($file['size'] > 2097152) {
        return false;
    }

    // Создаем папку для аватаров, если ее нет
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Генерируем уникальное имя файла
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/avatars/' . $filename;
    }

    return false;
}