<?php
require_once __DIR__ . '/BaseModel.php';

class Loan extends BaseModel {
    private string $table = "loans";
    public float $maxCreditLimit = 5000.00;

    public function getInterestRate(int $termMonths): float {
        return match ($termMonths) {
            6 => 0.05,
            12 => 0.08,
            default => 0.03,
        };
    }

    public function getUserLoans(int $userId): array {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE borrower_id = :user_id ORDER BY applied_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getActiveLoanTotal(int $userId): float {
        try {
            // Calculate active balance considering remaining unpaid amounts so limits restore automatically upon payment
            $query = "SELECT SUM(amount - paid_amount) as total FROM " . $this->table . " WHERE borrower_id = :user_id AND status = 'disbursed' AND payment_status = 'unpaid'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            $row = $stmt->fetch();
            return (float)($row['total'] ?? 0);
        } catch (PDOException $e) {
            return 0.00;
        }
    }

    public function getAllSystemLoans(): array {
        try {
            $query = "SELECT l.*, u.full_name, u.email, a.account_number 
                      FROM " . $this->table . " l 
                      JOIN users u ON l.borrower_id = u.id 
                      LEFT JOIN accounts a ON u.id = a.user_id 
                      ORDER BY l.applied_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function applyLoan(int $userId, float $amount, int $termMonths = 3): array {
        if ($amount <= 0) {
            return ['status' => false, 'message' => 'Please enter a valid loan amount.'];
        }

        if (!in_array($termMonths, [3, 6, 12])) {
            $termMonths = 3;
        }

        $currentActive = $this->getActiveLoanTotal($userId);
        $availableLimit = $this->maxCreditLimit - $currentActive;

        if ($amount > $availableLimit) {
            return [
                'status' => false, 
                'message' => "Amount exceeds your available loan limit! Max available: $" . number_format($availableLimit, 2)
            ];
        }

        $interestRate = $this->getInterestRate($termMonths);
        $totalInterest = $amount * $interestRate;
        $totalRepayment = $amount + $totalInterest;
        $monthlyPayment = $totalRepayment / $termMonths;

        try {
            $query = "INSERT INTO " . $this->table . " (borrower_id, amount, term_months, interest_rate, total_payable, status) 
                      VALUES (:user_id, :amount, :term_months, :interest_rate, :total_payable, 'pending')";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':term_months' => $termMonths,
                ':interest_rate' => $interestRate,
                ':total_payable' => $totalRepayment
            ]);

            return [
                'status' => true, 
                'message' => 'Loan application submitted! Monthly installment: $' . number_format($monthlyPayment, 2) . ' / month for ' . $termMonths . ' months.'
            ];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateLoanStatus(int $loanId, string $status): array {
        if (!in_array($status, ['approved', 'rejected'])) {
            return ['status' => false, 'message' => 'Invalid status action.'];
        }

        try {
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $query = "UPDATE " . $this->table . " SET status = :status, due_date = :due_date WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':due_date' => $dueDate,
                ':id' => $loanId
            ]);

            return ['status' => true, 'message' => 'Loan application has been ' . $status . ' successfully!'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function disburseLoan(int $loanId): array {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $loanId]);
            $loan = $stmt->fetch();

            if (!$loan || $loan['status'] !== 'approved') {
                $this->conn->rollBack();
                return ['status' => false, 'message' => 'Loan is not eligible for disbursement.'];
            }

            $accStmt = $this->conn->prepare("SELECT * FROM accounts WHERE user_id = :user_id FOR UPDATE");
            $accStmt->execute([':user_id' => $loan['borrower_id']]);
            $account = $accStmt->fetch();

            if (!$account) {
                $this->conn->rollBack();
                return ['status' => false, 'message' => 'Account not found.'];
            }

            $newBalance = (float)$account['balance'] + (float)$loan['amount'];

            $upAcc = $this->conn->prepare("UPDATE accounts SET balance = :balance WHERE id = :id");
            $upAcc->execute([':balance' => $newBalance, ':id' => $account['id']]);

            $txStmt = $this->conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (:account_id, 'credit', :amount, 'Loan Disbursement')");
            $txStmt->execute([':account_id' => $account['id'], ':amount' => $loan['amount']]);

            $upLoan = $this->conn->prepare("UPDATE " . $this->table . " SET status = 'disbursed' WHERE id = :id");
            $upLoan->execute([':id' => $loanId]);

            $this->conn->commit();
            return ['status' => true, 'message' => 'Loan funds disbursed and credited successfully!'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function payLoan(int $loanId, int $userId, int $payMonths): array {
        if ($payMonths <= 0) {
            return ['status' => false, 'message' => 'Please select a valid number of months to pay.'];
        }

        try {
            $this->conn->beginTransaction();

            $loanStmt = $this->conn->prepare("SELECT l.*, a.id as account_id, a.balance FROM " . $this->table . " l JOIN accounts a ON l.borrower_id = a.user_id WHERE l.id = :id AND l.borrower_id = :user_id FOR UPDATE");
            $loanStmt->execute([':id' => $loanId, ':user_id' => $userId]);
            $loan = $loanStmt->fetch();

            if (!$loan) {
                $this->conn->rollBack();
                return ['status' => false, 'message' => 'Loan record not found.'];
            }

            if ($loan['payment_status'] === 'paid') {
                $this->conn->rollBack();
                return ['status' => false, 'message' => 'This loan is already fully paid.'];
            }

            $totalPayable = (float)$loan['total_payable'];
            $termMonths = (int)$loan['term_months'];
            $paidAmountSoFar = (float)$loan['paid_amount'];
            
            $monthlyInstallment = $totalPayable / max(1, $termMonths);
            // Portion of principal/amount being restored
            $principalPortionPerMonth = (float)$loan['amount'] / max(1, $termMonths);
            $amountDue = $monthlyInstallment * $payMonths;
            $principalPaidThisTime = $principalPortionPerMonth * $payMonths;

            $remainingBalanceDue = $totalPayable - $paidAmountSoFar;
            if ($amountDue > $remainingBalanceDue) {
                $amountDue = $remainingBalanceDue;
                $principalPaidThisTime = (float)$loan['amount'] - $paidAmountSoFar; // approximate remainder
            }

            $currentBalance = (float)$loan['balance'];

            if ($currentBalance < $amountDue) {
                $this->conn->rollBack();
                return ['status' => false, 'message' => 'Insufficient funds in deposit account! Required for ' . $payMonths . ' month(s): $' . number_format($amountDue, 2)];
            }

            $newAccountBalance = $currentBalance - $amountDue;
            $newPaidAmount = $paidAmountSoFar + $amountDue;
            $newPaymentStatus = ($newPaidAmount >= $totalPayable - 0.01) ? 'paid' : 'unpaid';

            $upAcc = $this->conn->prepare("UPDATE accounts SET balance = :balance WHERE id = :id");
            $upAcc->execute([':balance' => $newAccountBalance, ':id' => $account['id'] ?? $loan['account_id']]);

            $upLoan = $this->conn->prepare("UPDATE " . $this->table . " SET paid_amount = :paid_amount, payment_status = :payment_status WHERE id = :id");
            $upLoan->execute([
                ':paid_amount' => $newPaidAmount,
                ':payment_status' => $newPaymentStatus,
                ':id' => $loanId
            ]);

            $txStmt = $this->conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (:account_id, 'debit', :amount, :description)");
            $txStmt->execute([
                ':account_id' => $loan['account_id'],
                ':amount' => $amountDue,
                ':description' => 'Loan Repayment (' . $payMonths . ' Month(s) - ID #' . $loanId . ')'
            ]);

            $this->conn->commit();
            return ['status' => true, 'message' => 'Successfully paid ' . $payMonths . ' month(s) installment ($' . number_format($amountDue, 2) . ')! Your loan credit limit has been restored accordingly.'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
?>