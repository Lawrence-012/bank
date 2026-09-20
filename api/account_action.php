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

            // Update balance
            $updateQuery = "UPDATE " . $this->table . " SET balance = :balance WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([
                ':balance' => $newBalance,
                ':id' => $accountId
            ]);

            // Log transaction
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

    // Alias wrapper for transaction execution used by account_action.php
    public function executeTransaction(int $accountId, string $type, float $amount, string $description = ''): array {
        $query = "SELECT user_id FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $accountId]);
        $acc = $stmt->fetch();

        if (!$acc) {
            return ['status' => false, 'message' => 'Account not found.'];
        }

        return $this->transact((int)$acc['user_id'], $type, $amount, $description);
    }

    // Calculate interest for all savings/deposit accounts globally
    public function calculateInterestAllAccounts(float $ratePercentage = 2.5): bool {
        try {
            $this->conn->beginTransaction();

            $query = "SELECT id, balance FROM " . $this->table;
            $accounts = $this->conn->query($query)->fetchAll();

            $rate = $ratePercentage / 100;

            foreach ($accounts as $acc) {
                $currentBalance = (float)$acc['balance'];
                if ($currentBalance <= 0) continue;

                $interestAmount = $currentBalance * $rate;
                $newBalance = $currentBalance + $interestAmount;

                $upStmt = $this->conn->prepare("UPDATE " . $this->table . " SET balance = :balance WHERE id = :id");
                $upStmt->execute([':balance' => $newBalance, ':id' => $acc['id']]);

                $logStmt = $this->conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (:account_id, 'interest', :amount, :description)");
                $logStmt->execute([
                    ':account_id' => $acc['id'],
                    ':amount' => $interestAmount,
                    ':description' => 'Global Interest Calculation (' . $ratePercentage . '%)'
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>