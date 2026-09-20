<?php
require_once __DIR__ . '/BaseModel.php';

class Transaction extends BaseModel {

    public function getTransactionsByAccount(int $accountId): array {
        $query = "SELECT * FROM transactions WHERE account_id = :acc_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':acc_id' => $accountId]);
        return $stmt->fetchAll();
    }

    public function getTransactionsByUserId(int $userId): array {
        $query = "SELECT t.*, a.account_number 
                  FROM transactions t
                  JOIN accounts a ON t.account_id = a.id
                  WHERE a.user_id = :user_id 
                  ORDER BY t.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAllTransactions(): array {
        $query = "SELECT t.*, a.account_number, u.full_name 
                  FROM transactions t
                  JOIN accounts a ON t.account_id = a.id
                  JOIN users u ON a.user_id = u.id
                  ORDER BY t.created_at DESC";
        return $this->conn->query($query)->fetchAll();
    }

    public function getDisbursementHistory(): array {
        $query = "SELECT t.*, a.account_number, u.full_name as borrower_name 
                  FROM transactions t
                  JOIN accounts a ON t.account_id = a.id
                  JOIN users u ON a.user_id = u.id
                  WHERE t.description LIKE '%Disbursement%' OR t.type = 'credit'
                  ORDER BY t.created_at DESC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>