<?php
header('Content-Type: text/html; charset=utf-8'); // Устанавливаем кодировку

class Database {
    private $host = "localhost";
    private $db_name = "p95364dp_s";
    private $username = "p95364dp_s";
    private $password = "p95364dp_ss";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8"); // Устанавливаем кодировку соединения
        } catch (PDOException $exception) {
            echo "Ошибка подключения: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

$database = new Database();
$db = $database->getConnection();

?>