<?php
require_once __DIR__ . '/../config/Database.php';

abstract class BaseModel {
    protected PDO $conn;

    public function __construct(?PDO $db = null) {
        $this->conn = $db ?? (new Database())->getConnection();
    }
}
?>