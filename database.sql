CREATE DATABASE IF NOT EXISTS bank_db;
USE bank_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('account_holder', 'employee', 'manager', 'admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    daily_debit_limit DECIMAL(10,2) DEFAULT 1000.00,
    daily_credit_limit DECIMAL(10,2) DEFAULT 5000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type ENUM('credit', 'debit', 'interest', 'refund') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('success', 'failed', 'refunded') DEFAULT 'success',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrower_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    total_payable DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'approved', 'disbursed', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default Accounts: One Account Per Role
-- Hashed Password for "1234": $2y$10$gN/wFp308I83J3vA2S1X..aGptR15zJ7zS4Pz7jT3eFv4kX6y3mKi

INSERT INTO users (id, username, password, role, full_name, email, phone) VALUES
(1, 'holder_user', '$2y$10$gN/wFp308I83J3vA2S1X..aGptR15zJ7zS4Pz7jT3eFv4kX6y3mKi', 'account_holder', 'John Account Holder', 'holder@bank.com', '1112223333'),
(2, 'employee_user', '$2y$10$gN/wFp308I83J3vA2S1X..aGptR15zJ7zS4Pz7jT3eFv4kX6y3mKi', 'employee', 'Sarah Employee', 'employee@bank.com', '2223334444'),
(3, 'manager_user', '$2y$10$gN/wFp308I83J3vA2S1X..aGptR15zJ7zS4Pz7jT3eFv4kX6y3mKi', 'manager', 'Michael Manager', 'manager@bank.com', '3334445555'),
(4, 'admin_user', '$2y$10$gN/wFp308I83J3vA2S1X..aGptR15zJ7zS4Pz7jT3eFv4kX6y3mKi', 'admin', 'Alex Admin', 'admin@bank.com', '4445556666')
ON DUPLICATE KEY UPDATE password=VALUES(password);

-- Default Bank Account for the Account Holder
INSERT INTO accounts (user_id, account_number, balance, daily_debit_limit, daily_credit_limit) VALUES
(1, 'ACC10002000', 2500.00, 1000.00, 5000.00)
ON DUPLICATE KEY UPDATE id=id;