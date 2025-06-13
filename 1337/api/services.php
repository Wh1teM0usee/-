<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    if ($action === 'get_services') {
        $stmt = $pdo->query("SELECT id, title, description, icon, price FROM services WHERE is_active = 1");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $services
        ]);
        exit;
    }

    throw new Exception('Неизвестное действие');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getTraceAsString()
    ]);
}