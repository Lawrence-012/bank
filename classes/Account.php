<?php
require_once __DIR__ . '/BaseModel.php';

class Account extends BaseModel {
    private string $table = "accounts";

    public function getAccountByUserId(int $userId) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    public function getTransactionHistory(int $accountId): array {
        $query = "SELECT * FROM transactions WHERE account_id = :account_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':account_id' => $accountId]);
        return $stmt->fetchAll() ?: [];
    }

    public function transact(int $userId, string $type, float $amount, string $description = ''): array {
        if ($amount <= 0) {
            return ['status' => false, 'message' => 'Invalid amount.'];
        }

        $account = $this->getAccountByUserId($userId);
        if (!$account) {
            return ['status' => false, 'message' => 'Account not found.'];
        }

        $accountId = $account['id'];
        $currentBalance = (float)$account['balance'];

        if ($type === 'debit' && $currentBalance < $amount) {
            return ['status' => false, 'message' => 'Insufficient funds.'];
        }

        $newBalance = ($type === 'credit') ? ($currentBalance + $amount) : ($currentBalance - $amount);

        try {
            $this->conn->beginTransaction();

            $updateQuery = "UPDATE " . $this->table . " SET balance = :balance WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([
                ':balance' => $newBalance,
                ':id' => $accountId
            ]);

            $logQuery = "INSERT INTO transactions (account_id, type, amount, description) VALUES (:account_id, :type, :amount, :description)";
            $logStmt = $this->conn->prepare($logQuery);
            $logStmt->execute([
                ':account_id' => $accountId,
                ':type' => $type,
                ':amount' => $amount,
                ':description' => $description
            ]);

            $this->conn->commit();
            return ['status' => true, 'message' => 'Transaction completed successfully!'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function overrideBalance(int $accountId, float $newBalance, string $reason = 'Administrative Balance Override'): array {
        if ($newBalance < 0) {
            return ['status' => false, 'message' => 'Balance cannot be negative.'];
        }

        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        $account = $stmt->fetch();

        if (!$account) {
            return ['status' => false, 'message' => 'Account not found.'];
        }

        $oldBalance = (float)$account['balance'];
        $diff = $newBalance - $oldBalance;

        if ($diff == 0) {
            return ['status' => true, 'message' => 'Account balance is already at this exact amount.'];
        }

        $type = $diff > 0 ? 'credit' : 'debit';
        $amount = abs($diff);

        try {
            $this->conn->beginTransaction();

            $upStmt = $this->conn->prepare("UPDATE " . $this->table . " SET balance = :balance WHERE id = :id");
            $upStmt->execute([':balance' => $newBalance, ':id' => $accountId]);

            $logStmt = $this->conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (:account_id, :type, :amount, :description)");
            $logStmt->execute([
                ':account_id' => $accountId,
                ':type' => $type,
                ':amount' => $amount,
                ':description' => $reason
            ]);

            $this->conn->commit();
            return ['status' => true, 'message' => 'Account balance successfully overridden and logged!'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
?>