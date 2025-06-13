<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Подключаем общие настройки
require_once __DIR__ . '/api_common.php';

session_start();

try {
    $pdo = createDatabaseConnection();
    $input = getRequestData();
    $action = $_GET['action'] ?? '';

    // Проверка авторизации для всех действий, кроме проверки доступности
    if ($action !== 'check_availability' && $action !== 'get_rooms' && $action !== 'get_room' && empty($_SESSION['user_id'])) {
        jsonResponse(false, 'Для этого действия требуется авторизация');
    }

    switch ($action) {
        case 'book':
            $required = ['room_id', 'date_from', 'date_to', 'guests'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse(false, "Поле {$field} обязательно для заполнения");
                }
            }

            try {
                $pdo->beginTransaction();

                // Проверяем доступность номера
                $stmt = $pdo->prepare("SELECT id, price_per_night FROM rooms WHERE id = ? AND is_available = 1");
                $stmt->execute([$input['room_id']]);
                $room = $stmt->fetch();

                if (!$room) {
                    jsonResponse(false, 'Этот номер недоступен для бронирования');
                }

                // Проверяем, не пересекается ли бронирование с существующими
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
                
                if ($result['count'] > 0) {
                    jsonResponse(false, 'Номер уже забронирован на выбранные даты');
                }

                // Рассчитываем стоимость
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

                // Обновляем статус номера (если требуется)
                if ($nights > 3) { // Пример логики - делаем номер недоступным только для длительных бронирований
                    $stmt = $pdo->prepare("UPDATE rooms SET is_available = 0 WHERE id = ?");
                    $stmt->execute([$input['room_id']]);
                }

                $pdo->commit();
                jsonResponse(true, 'Бронь успешно оформлена', ['booking_id' => $pdo->lastInsertId()]);
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
                    jsonResponse(false, 'Дата выезда должна быть позже дата заезда');
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
                
                // Проверяем общую доступность номера
                $stmt = $pdo->prepare("SELECT is_available FROM rooms WHERE id = ?");
                $stmt->execute([$input['room_id']]);
                $room = $stmt->fetch();
                
                $available = ($result['count'] == 0) && ($room && $room['is_available'] == 1);
                
                jsonResponse(true, 'Проверка завершена', [
                    'is_available' => $available,
                    'dates' => [
                        'from' => $input['date_from'],
                        'to' => $input['date_to']
                    ]
                ]);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка проверки доступности: ' . $e->getMessage());
            }
            break;

        case 'get_rooms':
            try {
                $query = "SELECT * FROM rooms WHERE is_available = 1";
                
                // Если админ - показываем все номера
                if (!empty($_SESSION['user_id']) {
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user && $user['role'] === 'admin') {
                        $query = "SELECT * FROM rooms";
                    }
                }
                
                $stmt = $pdo->prepare($query);
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

                // Проверка прав доступа (админы видят все, остальные - только доступные)
                if ($room['is_available'] == 0) {
                    if (empty($_SESSION['user_id'])) {
                        jsonResponse(false, 'Этот номер недоступен');
                    }
                    
                    $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $userStmt->execute([$_SESSION['user_id']]);
                    $user = $userStmt->fetch();
                    
                    if (!$user || $user['role'] !== 'admin') {
                        jsonResponse(false, 'Этот номер недоступен');
                    }
                }

                if (!empty($room['image_path']) && !preg_match('/^https?:\/\//', $room['image_path'])) {
                    $room['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($room['image_path'], '/');
                }

                jsonResponse(true, 'Номер успешно получен', $room);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;
            
        case 'cancel_booking':
            if (empty($input['id'])) {
                jsonResponse(false, 'ID бронирования обязательно');
            }

            try {
                $pdo->beginTransaction();
                
                // Проверяем, что бронирование принадлежит пользователю
                $stmt = $pdo->prepare("SELECT id, room_id FROM room_bookings WHERE id = ? AND user_id = ?");
                $stmt->execute([$input['id'], $_SESSION['user_id']]);
                
                if ($stmt->rowCount() === 0) {
                    jsonResponse(false, 'Бронирование не найдено или не принадлежит вам');
                }
                
                $booking = $stmt->fetch();
                
                // Обновляем статус бронирования
                $stmt = $pdo->prepare("UPDATE room_bookings SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$input['id']]);
                
                // Освобождаем номер
                $stmt = $pdo->prepare("UPDATE rooms SET is_available = 1 WHERE id = ?");
                $stmt->execute([$booking['room_id']]);
                
                $pdo->commit();
                jsonResponse(true, 'Бронирование отменено');
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;
            
        case 'get_user_bookings':
            try {
                $query = "SELECT b.*, r.title as room_title, r.image_path 
                          FROM room_bookings b
                          JOIN rooms r ON b.room_id = r.id
                          WHERE b.user_id = ?
                          ORDER BY b.check_in DESC";
                          
                $stmt = $pdo->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Обработка путей к изображениям
                foreach ($bookings as &$booking) {
                    if (!empty($booking['image_path']) && !preg_match('/^https?:\/\//', $booking['image_path'])) {
                        $booking['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($booking['image_path'], '/');
                    }
                }
                
                jsonResponse(true, 'Бронирования получены', $bookings);
            } catch (PDOException $e) {
                jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
            }
            break;

        default:
            jsonResponse(false, 'Неверное действие', [
                'available_actions' => [
                    'book',
                    'check_availability',
                    'get_rooms',
                    'get_room',
                    'cancel_booking',
                    'get_user_bookings'
                ]
            ]);
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Ошибка базы данных', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    jsonResponse(false, 'Ошибка сервера', ['error' => $e->getMessage()]);
}