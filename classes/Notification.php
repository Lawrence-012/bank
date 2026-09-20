<?php
require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel {

    public function sendNotification(int $userId, string $message): bool {
        $query = "INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':user_id' => $userId, ':message' => $message]);
    }

    public function getUserNotifications(int $userId): array {
        $query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }
}
?>