<?php
header('Content-Type: application/json');

// Настройка директории для логов
$logDir = __DIR__ . '/api_logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$responseFile = $logDir . 'api_response.json';
$debugLogFile = $logDir . 'debug.log';

// Запись в лог для отладки
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    'query' => $_GET ?? [],
    'input' => json_decode(file_get_contents('php://input'), true) ?: $_POST,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
];

// Безопасная запись в лог
try {
    file_put_contents(
        $debugLogFile, 
        json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", 
        FILE_APPEND | LOCK_EX
    );
} catch (Exception $e) {
    // В случае ошибки логирования продолжаем работу
}

// Чтение и вывод файла ответов
try {
    if (file_exists($responseFile) {
        $content = file_get_contents($responseFile);
        if ($content === false) {
            throw new Exception('Failed to read response file');
        }
        
        // Проверка валидности JSON
        json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in response file');
        }
        
        echo $content;
    } else {
        echo json_encode([
            'error' => 'No API responses logged yet',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}