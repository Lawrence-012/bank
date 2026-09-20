<?php
require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    private string $table = "users";

    public function generateUsername(string $fullName): string {
        $cleanName = strtolower((string)preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
        $prefix = strlen($cleanName) >= 5 ? substr($cleanName, 0, 5) : str_pad($cleanName, 5, 'x');
        return $prefix . rand(1000, 9999);
    }

    public function generatePassword(): string {
        return bin2hex(random_bytes(4));
    }

    public function registerAccountHolder(string $fullName, string $email, string $phone, string $username = '', string $plainPassword = ''): array {
        if (empty($username)) {
            $username = $this->generateUsername($fullName);
        }
        if (empty($plainPassword)) {
            $plainPassword = $this->generatePassword();
        }

        $chkStmt = $this->conn->prepare("SELECT id FROM " . $this->table . " WHERE username = :username");
        $chkStmt->execute([':username' => $username]);
        if ($chkStmt->fetch()) {
            return ['status' => false, 'message' => 'Username is already taken. Please choose another.'];
        }

        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table . " (username, password, role, full_name, email, phone) 
                      VALUES (:username, :password, 'account_holder', :full_name, :email, :phone)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone
            ]);

            $userId = (int)$this->conn->lastInsertId();
            $accNum = "ACC" . rand(10000000, 99999999);

            $accQuery = "INSERT INTO accounts (user_id, account_number, balance) VALUES (:user_id, :account_number, 500.00)";
            $accStmt = $this->conn->prepare($accQuery);
            $accStmt->execute([
                ':user_id' => $userId,
                ':account_number' => $accNum
            ]);

            $this->conn->commit();
            return ['status' => true, 'username' => $username, 'message' => 'Account created successfully! You can now log in.'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function createStaff(string $fullName, string $email, string $phone, string $role): array {
        if (!in_array($role, ['employee', 'manager'])) {
            return ['status' => false, 'message' => 'Invalid staff role provided.'];
        }

        $username = $this->generateUsername($fullName);
        $plainPassword = $this->generatePassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

        $query = "INSERT INTO " . $this->table . " (username, password, role, full_name, email, phone) 
                  VALUES (:username, :password, :role, :full_name, :email, :phone)";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':role' => $role,
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone
        ]);

        return $success 
            ? ['status' => true, 'username' => $username, 'password' => $plainPassword]
            : ['status' => false, 'message' => 'Failed to create staff account.'];
    }

    public function login(string $username, string $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        return false;
    }

    public function getAllUsers(): array {
        $query = "SELECT id, full_name, email, phone, role, created_at FROM " . $this->table . " ORDER BY created_at DESC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAccountHolders(): array {
        $query = "SELECT u.id, u.full_name, u.email, u.phone, a.account_number, a.balance, a.daily_debit_limit, a.daily_credit_limit 
                  FROM " . $this->table . " u 
                  JOIN accounts a ON u.id = a.user_id 
                  WHERE u.role = 'account_holder'";
        return $this->conn->query($query)->fetchAll();
    }
}
?>