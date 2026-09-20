<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$role = $user['role'];

require_once 'classes/Account.php';
require_once 'classes/Loan.php';
require_once 'classes/User.php';
require_once 'classes/Transaction.php';
require_once 'config/Database.php';

$accountClass = new Account();
$loanClass = new Loan();
$userClass = new User();
$transactionClass = new Transaction();

// Safe Initialization
$account = null;
$transactions = [];
$loans = [];
$accountHolders = [];
$employees = [];
$managers = [];
$allSystemLoans = [];
$allAccountsList = [];
$userNotifications = [];

// Dynamic Financial Counters
$totalBalance = 0.00;
$activeLoanTotal = 0.00;
$availableCreditLimit = 5000.00;

// Fetch Data Based on Role
if ($role === 'account_holder') {
    $account = $accountClass->getAccountByUserId($user['id']);
    
    if ($account) {
        $totalBalance = (float)$account['balance'];
        
        if (method_exists($accountClass, 'getTransactionHistory')) {
            $transactions = $accountClass->getTransactionHistory($account['id']);
        }
    }
    
    if (method_exists($loanClass, 'getUserLoans')) {
        $loans = $loanClass->getUserLoans($user['id']);
        if (method_exists($loanClass, 'getActiveLoanTotal')) {
            $activeLoanTotal = $loanClass->getActiveLoanTotal($user['id']);
            $availableCreditLimit = max(0, $loanClass->maxCreditLimit - $activeLoanTotal);
        }
    }

    $dbConn = (new Database())->getConnection();
    $notifStmt = $dbConn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
    $notifStmt->execute([':user_id' => $user['id']]);
    $userNotifications = $notifStmt->fetchAll() ?: [];

} elseif ($role === 'manager' || $role === 'admin') {
    if (method_exists($loanClass, 'getAllSystemLoans')) {
        $allSystemLoans = $loanClass->getAllSystemLoans();
    }
    if (method_exists($userClass, 'getAllAccountHolders')) {
        $allAccountsList = $userClass->getAllAccountHolders();
    }
} elseif ($role === 'employee') {
    if (method_exists($loanClass, 'getAllSystemLoans')) {
        $allSystemLoans = $loanClass->getAllSystemLoans();
    }
}

if ($role === 'admin') {
    if (method_exists($userClass, 'getAllUsers')) {
        $allUsers = $userClass->getAllUsers();
        $accountHolders = array_filter($allUsers, fn($u) => $u['role'] === 'account_holder');
        $employees = array_filter($allUsers, fn($u) => $u['role'] === 'employee');
        $managers = array_filter($allUsers, fn($u) => $u['role'] === 'admin' || $u['role'] === 'manager');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BankingIna | Dashboard</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="assets/css/style.css?v=17.0" rel="stylesheet">
</head>
<body class="dashboard-body">

    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>

    <div class="dashboard-wrapper d-flex p-2 gap-2">
        
        <!-- SIDEBAR NAVIGATION -->
        <aside class="dashboard-sidebar d-flex flex-column justify-content-between p-3 shadow-sm text-white flex-shrink-0">
            <div>
                <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none mb-3 px-2">
                    <div class="brand-icon-bg d-flex align-items-center justify-content-center rounded-circle">
                        <i class="bi bi-bank text-navy fs-6"></i>
                    </div>
                    <span class="fw-bold fs-6 tracking-tight">BankingIna</span>
                </a>

                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link active d-flex align-items-center gap-2 px-3 py-2 nav-tab-btn" href="#" data-target="dashboard-view">
                            <i class="bi bi-house-door-fill fs-6"></i>
                            <span class="fw-semibold fs-7"><?= ($role === 'admin' || $role === 'manager' || $role === 'employee') ? 'Admin Overview' : 'Dashboard' ?></span>
                        </a>
                    </li>

                    <?php if ($role === 'account_holder'): ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="accounts-view">
                                <i class="bi bi-people-fill fs-6"></i>
                                <span class="fw-medium fs-7">Accounts</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="loans-view">
                                <i class="bi bi-wallet2 fs-6"></i>
                                <span class="fw-medium fs-7">Loan History & Repayment</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'manager' || $role === 'admin'): ?>
                        <div class="text-white-50 fs-7 fw-bold px-3 pt-3 pb-1 text-uppercase tracking-wider">Manager Controls</div>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="manager-approvals-view">
                                <i class="bi bi-check2-square fs-6"></i>
                                <span class="fw-medium fs-7">Loan Approvals</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="manager-loans-view">
                                <i class="bi bi-cash-coin fs-6"></i>
                                <span class="fw-medium fs-7">Loan Portfolio & Alerts</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="manager-accounts-view">
                                <i class="bi bi-wallet2 fs-6"></i>
                                <span class="fw-medium fs-7">Balance Override & Accounts</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="manager-create-user-view">
                                <i class="bi bi-person-plus fs-6"></i>
                                <span class="fw-medium fs-7">Create User Accounts</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'employee'): ?>
                        <div class="text-white-50 fs-7 fw-bold px-3 pt-3 pb-1 text-uppercase tracking-wider">Staff Controls</div>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="employee-disburse-view">
                                <i class="bi bi-cash-stack fs-6"></i>
                                <span class="fw-medium fs-7">Disburse Loans</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="manager-loans-view">
                                <i class="bi bi-cash-coin fs-6"></i>
                                <span class="fw-medium fs-7">Loan Portfolio & Alerts</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'admin'): ?>
                        <div class="text-white-50 fs-7 fw-bold px-3 pt-3 pb-1 text-uppercase tracking-wider">User Management</div>
                        
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="admin-holders-view">
                                <i class="bi bi-person-badge fs-6"></i>
                                <span class="fw-medium fs-7">Account Holders</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="admin-employees-view">
                                <i class="bi bi-person-workspace fs-6"></i>
                                <span class="fw-medium fs-7">Employees</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white-50 nav-tab-btn" href="#" data-target="admin-managers-view">
                                <i class="bi bi-shield-lock fs-6"></i>
                                <span class="fw-medium fs-7">Managers & Admins</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="px-2 pt-2 border-top border-white-10">
                <a href="#" id="logoutBtn" class="d-flex align-items-center gap-2 text-white-50 text-decoration-none fw-medium py-2 hover-white fs-7">
                    <i class="bi bi-box-arrow-left fs-6"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-grow-1 d-flex flex-column h-100 min-w-0">
            
            <header class="d-flex justify-content-between align-items-center mb-2 px-1 flex-shrink-0">
                <h5 class="fw-bold text-navy mb-0" id="mainHeaderTitle">Dashboard</h5>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($role === 'account_holder'): ?>
                        <button class="btn btn-light rounded-circle p-0 d-flex align-items-center justify-content-center shadow-xs position-relative" style="width: 34px; height: 34px;" data-bs-toggle="modal" data-bs-target="#notificationModal">
                            <i class="bi bi-bell-fill text-navy fs-6"></i>
                            <?php if (!empty($userNotifications)): ?>
                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>

                    <div class="d-flex align-items-center gap-2 text-navy fw-semibold fs-7 ms-2">
                        <div class="avatar-circle bg-primary-custom text-white fw-bold d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px;">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($user['full_name']) ?></span>
                    </div>
                </div>
            </header>

            <!-- ================= VIEW 1: DASHBOARD MAIN ================= -->
            <div id="dashboard-view" class="app-view flex-grow-1 d-flex flex-column min-vh-0">
                <div class="mb-2 px-1 flex-shrink-0">
                    <small class="text-muted fw-medium fs-7"><?= date('l, F j, Y') ?></small>
                    <h4 class="fw-bold text-navy mb-0">
                        Good Morning, <span class="text-primary-custom"><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</span>
                    </h4>
                    <p class="text-muted mb-0 fs-7"><?= ($role === 'admin' || $role === 'manager' || $role === 'employee') ? 'System Administration & Control Center' : "Here's your financial overview" ?></p>
                </div>

                <?php if ($role === 'account_holder'): ?>
                    <div class="row g-2 mb-2 flex-shrink-0">
                        <div class="col-6">
                            <div class="card border-0 shadow-sm p-3 bg-white h-100 d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="text-muted fw-semibold text-uppercase tracking-wide fs-7">Total Balance</span>
                                        <h4 class="fw-bold text-navy mb-0 mt-1">$<?= number_format($totalBalance, 2) ?></h4>
                                    </div>
                                    <div class="card-icon-box bg-primary-subtle text-primary"><i class="bi bi-wallet2 fs-6"></i></div>
                                </div>
                                <span class="text-muted fs-7 mt-1 d-block">Available deposit balance</span>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="card border-0 shadow-sm p-3 bg-white h-100 d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="text-muted fw-semibold text-uppercase tracking-wide fs-7">Pending Loans</span>
                                        <h4 class="fw-bold text-navy mb-0 mt-1"><?= count(array_filter($loans, fn($l) => $l['status'] === 'pending')) ?></h4>
                                    </div>
                                    <div class="card-icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock-history fs-6"></i></div>
                                </div>
                                <span class="text-muted fs-7 mt-1 d-block">Available Loan Credit: <strong>$<?= number_format($availableCreditLimit, 2) ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 flex-grow-1 min-vh-0">
                        <div class="col-lg-8 d-flex flex-column h-100 gap-2 min-vh-0">
                            <div class="card border-0 shadow-sm p-3 bg-white flex-shrink-0">
                                <h6 class="fw-bold text-navy mb-2 fs-7">Account Information</h6>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded-3 hover-bg-light">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-indigo-subtle text-indigo p-2 rounded-3"><i class="bi bi-bank fs-6"></i></div>
                                        <div>
                                            <h6 class="fw-bold text-navy mb-0 fs-7">Primary Deposit Account</h6>
                                            <small class="text-muted fs-7">Account No: <?= htmlspecialchars($account['account_number'] ?? 'N/A') ?></small>
                                        </div>
                                    </div>
                                    <span class="fw-bold text-navy fs-7">$<?= number_format($totalBalance, 2) ?></span>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm p-3 bg-white flex-grow-1 d-flex flex-column min-vh-0 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-shrink-0">
                                    <h6 class="fw-bold text-navy mb-0 fs-7">Recent Transactions</h6>
                                    <button class="btn btn-link text-primary-custom text-decoration-none p-0 fs-7 fw-semibold" data-bs-toggle="modal" data-bs-target="#allTransactionsModal">View All</button>
                                </div>
                                
                                <div id="recent-tx-list" class="d-flex flex-column gap-2 overflow-hidden flex-grow-1 justify-content-start">
                                    <?php if (empty($transactions)): ?>
                                        <p class="text-muted text-center py-3 mb-0 fs-7">No transactions found in database.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($transactions, 0, 8) as $tx): ?>
                                            <div class="tx-item d-flex justify-content-between align-items-center p-2.5 rounded-3 hover-bg-light flex-shrink-0">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="p-2 rounded-3 <?= $tx['type'] === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                                        <i class="bi <?= $tx['type'] === 'credit' ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?> fs-6"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-navy mb-0 fs-7"><?= htmlspecialchars($tx['description'] ?: ($tx['type'] === 'credit' ? 'Deposit' : 'Withdrawal')) ?></h6>
                                                        <small class="text-muted fs-7"><?= date('F j, Y, g:i a', strtotime($tx['created_at'])) ?></small>
                                                    </div>
                                                </div>
                                                <span class="fw-bold fs-7 <?= $tx['type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                                                    <?= $tx['type'] === 'credit' ? '+' : '-' ?>$<?= number_format($tx['amount'], 2) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 h-100 min-vh-0">
                            <div class="card border-0 shadow-sm p-3 bg-white h-100 d-flex flex-column justify-content-between">
                                <div class="d-flex flex-column flex-grow-1">
                                    <h6 class="fw-bold text-navy mb-3 fs-7">Quick Actions</h6>
                                    <div class="d-grid gap-2 flex-grow-1 align-content-start">
                                        <button class="btn btn-outline-custom py-2.5 text-start px-3 d-flex align-items-center gap-2 fs-7" data-bs-toggle="modal" data-bs-target="#transactModal">
                                            <i class="bi bi-send text-primary-custom fs-6"></i> Transact Funds
                                        </button>
                                        <button class="btn btn-outline-custom py-2.5 text-start px-3 d-flex align-items-center gap-2 fs-7" data-bs-toggle="modal" data-bs-target="#loanModal">
                                            <i class="bi bi-cash-stack text-primary-custom fs-6"></i> Apply for Loan
                                        </button>
                                    </div>
                                </div>

                                <div class="pt-2 border-top text-center mt-auto flex-shrink-0">
                                    <small class="text-muted fs-7">*BankingIna Account Services*</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($role === 'admin'): ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <span class="text-muted fs-7 fw-bold text-uppercase">Account Holders</span>
                                <h3 class="fw-bold text-navy mt-1"><?= count($accountHolders) ?></h3>
                                <p class="text-muted fs-7 mb-0">Registered clients in the system</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <span class="text-muted fs-7 fw-bold text-uppercase">Employees</span>
                                <h3 class="fw-bold text-navy mt-1"><?= count($employees) ?></h3>
                                <p class="text-muted fs-7 mb-0">Active working staff members</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <span class="text-muted fs-7 fw-bold text-uppercase">Managers & Admins</span>
                                <h3 class="fw-bold text-navy mt-1"><?= count($managers) ?></h3>
                                <p class="text-muted fs-7 mb-0">Administrative controllers</p>
                            </div>
                        </div>
                    </div>
                <?php elseif ($role === 'manager' || $role === 'employee'): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <span class="text-muted fs-7 fw-bold text-uppercase">System Loan Portfolio</span>
                                <h3 class="fw-bold text-navy mt-1"><?= count($allSystemLoans) ?></h3>
                                <p class="text-muted fs-7 mb-0">Total recorded portfolio loans</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <span class="text-muted fs-7 fw-bold text-uppercase">Active Alerts Center</span>
                                <h3 class="fw-bold text-navy mt-1">Ready</h3>
                                <p class="text-muted fs-7 mb-0">Monitor payments and notify users</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($role === 'account_holder'): ?>
                <!-- ================= VIEW 2: ACCOUNT HOLDER PROFILE & SETTINGS ================= -->
                <div id="accounts-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="row g-3 justify-content-center">
                        <div class="col-lg-9">
                            <div class="card border-0 shadow-sm p-4 bg-white mb-3">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="avatar-circle bg-primary-custom text-white fw-bold d-flex align-items-center justify-content-center rounded-circle" style="width: 55px; height: 55px; font-size: 1.25rem;">
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-navy mb-0"><?= htmlspecialchars($user['full_name']) ?></h5>
                                        <span class="text-muted small">Registered Account Holder Profile</span>
                                    </div>
                                </div>
                                
                                <h6 class="fw-bold text-navy mb-3 fs-7 text-uppercase tracking-wide border-bottom pb-2">Full Account Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Full Name</small>
                                        <span class="fw-semibold text-navy"><?= htmlspecialchars($user['full_name']) ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Email Address</small>
                                        <span class="fw-semibold text-navy"><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Account Number</small>
                                        <span class="fw-semibold text-navy font-monospace"><?= htmlspecialchars($account['account_number'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Current Account Balance</small>
                                        <span class="fw-bold text-success">$<?= number_format($totalBalance, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= VIEW 3: ACCOUNT HOLDER LOAN HISTORY ================= -->
                <div id="loans-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-navy mb-3"><i class="bi bi-wallet2 text-primary-custom me-2"></i>My Loan History & Repayment</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Amount</th>
                                        <th>Total Payable</th>
                                        <th>Month to Pay (Tenure)</th>
                                        <th>Amount / Month</th>
                                        <th>Due Date</th>
                                        <th>Payment Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($loans)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-3">No loan history found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($loans as $l): 
                                            $term = max(1, (int)$l['term_months']);
                                            $perMonth = (float)$l['total_payable'] / $term;
                                        ?>
                                            <tr>
                                                <td>$<?= number_format($l['amount'], 2) ?></td>
                                                <td>$<?= number_format($l['total_payable'], 2) ?></td>
                                                <td><?= $l['term_months'] ?> Months</td>
                                                <td>$<?= number_format($perMonth, 2) ?> / mo</td>
                                                <td><?= htmlspecialchars($l['due_date'] ?? 'N/A') ?></td>
                                                <td><span class="badge <?= ($l['payment_status'] ?? 'unpaid') === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>"><?= ucfirst($l['payment_status'] ?? 'unpaid') ?></span></td>
                                                <td class="text-end">
                                                    <?php if (($l['payment_status'] ?? 'unpaid') !== 'paid' && $l['status'] === 'disbursed'): ?>
                                                        <button class="btn btn-sm btn-navy-landbank rounded-pill px-3 py-1 fw-semibold fs-7 open-pay-modal-btn" data-loan-id="<?= $l['id'] ?>" data-total-payable="<?= $l['total_payable'] ?>" data-paid-so-far="<?= $l['paid_amount'] ?? 0 ?>" data-max-term="<?= $l['term_months'] ?>">Pay Installment</button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role === 'manager' || $role === 'admin'): ?>
                <!-- ================= MANAGER VIEW: LOAN APPROVALS ================= -->
                <div id="manager-approvals-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Pending Loan Applications</h5>
                            <small class="text-muted">Review and approve or reject borrower requests</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Amount</th>
                                        <th>Tenure</th>
                                        <th>Total Payable</th>
                                        <th>Applied Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $pendingLoans = array_filter($allSystemLoans, fn($l) => $l['status'] === 'pending');
                                    if (empty($pendingLoans)): 
                                    ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">No pending loan applications found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingLoans as $loan): ?>
                                            <tr>
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($loan['full_name']) ?></td>
                                                <td>$<?= number_format($loan['amount'], 2) ?></td>
                                                <td><?= $loan['term_months'] ?> Months</td>
                                                <td>$<?= number_format($loan['total_payable'], 2) ?></td>
                                                <td><?= htmlspecialchars($loan['applied_at']) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold fs-7 approve-loan-btn" data-loan-id="<?= $loan['id'] ?>">Approve</button>
                                                    <button class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-semibold fs-7 reject-loan-btn" data-loan-id="<?= $loan['id'] ?>">Reject</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= MANAGER VIEW: BALANCE OVERRIDE & ACCOUNTS ================= -->
                <div id="manager-accounts-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Account Balance Override</h5>
                            <small class="text-muted">Click any account row to override balance</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Holder Name</th>
                                        <th>Account Number</th>
                                        <th>Current Balance</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allAccountsList)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No account holders found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($allAccountsList as $acc): ?>
                                            <tr>
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($acc['full_name']) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($acc['account_number']) ?></td>
                                                <td class="fw-bold text-success">$<?= number_format($acc['balance'], 2) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold fs-7 override-balance-btn" data-acc-id="<?= $acc['id'] ?>" data-holder="<?= htmlspecialchars($acc['full_name']) ?>" data-balance="<?= $acc['balance'] ?>" data-bs-toggle="modal" data-bs-target="#overrideBalanceModal">Override Balance</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= MANAGER VIEW: CREATE USER ACCOUNTS ================= -->
                <div id="manager-create-user-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="row g-3 justify-content-center">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm p-4 bg-white mb-3">
                                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-person-plus-fill text-primary-custom me-2"></i>Create New Account Holder</h5>
                                <form id="createAccountHolderForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-navy fs-7">Full Name</label>
                                        <input type="text" name="full_name" class="form-control custom-input" placeholder="e.g. Jane Doe" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Email Address</label>
                                            <input type="email" name="email" class="form-control custom-input" placeholder="jane@bank.com" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Mobile Number</label>
                                            <input type="text" name="phone" class="form-control custom-input" placeholder="09123456789" required>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Username</label>
                                            <input type="text" name="username" class="form-control custom-input" placeholder="Optional (auto-generated if empty)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Password</label>
                                            <input type="password" name="password" class="form-control custom-input" placeholder="Optional (auto-generated if empty)">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-navy-landbank py-2.5 px-4 rounded-pill fs-7 fw-semibold">Create Account Holder</button>
                                </form>
                            </div>

                            <div class="card border-0 shadow-sm p-4 bg-white">
                                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-person-badge-fill text-primary-custom me-2"></i>Create Employee / Staff Account</h5>
                                <form id="createStaffForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-navy fs-7">Staff Full Name</label>
                                        <input type="text" name="full_name" class="form-control custom-input" placeholder="e.g. Mark Staff" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Staff Email</label>
                                            <input type="email" name="email" class="form-control custom-input" placeholder="mark@bank.com" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-navy fs-7">Staff Phone</label>
                                            <input type="text" name="phone" class="form-control custom-input" placeholder="09987654321" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-navy fs-7">Staff Role</label>
                                        <select name="role" class="form-select custom-input" required>
                                            <option value="employee" selected>Employee / Staff</option>
                                            <option value="manager">Manager</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-navy-landbank py-2.5 px-4 rounded-pill fs-7 fw-semibold">Create Staff Account</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role === 'manager' || $role === 'admin' || $role === 'employee'): ?>
                <!-- ================= SHARED VIEW: LOAN PORTFOLIO & PAYMENT ALERTS ================= -->
                <div id="manager-loans-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Loan Portfolio & Payment Alerts Tracker</h5>
                            <small class="text-muted">Send repayment alerts or inspect status</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Account No</th>
                                        <th>Amount</th>
                                        <th>Tenure</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Payment Status</th>
                                        <th class="text-end">Alert Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allSystemLoans)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-3">No loans recorded in the system.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($allSystemLoans as $loan): ?>
                                            <tr>
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($loan['full_name']) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($loan['account_number'] ?? 'N/A') ?></td>
                                                <td>$<?= number_format($loan['amount'], 2) ?></td>
                                                <td><?= $loan['term_months'] ?> Months</td>
                                                <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($loan['status']) ?></span></td>
                                                <td><?= htmlspecialchars($loan['due_date'] ?? 'Pending') ?></td>
                                                <td>
                                                    <span class="badge <?= ($loan['payment_status'] ?? 'unpaid') === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                                        <?= ucfirst($loan['payment_status'] ?? 'unpaid') ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (($loan['payment_status'] ?? 'unpaid') !== 'paid' && $loan['status'] === 'disbursed'): ?>
                                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 fw-semibold fs-7 send-alert-btn" data-user-id="<?= $loan['borrower_id'] ?>" data-borrower="<?= htmlspecialchars($loan['full_name']) ?>">Send Payment Alert</button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role === 'employee'): ?>
                <!-- ================= EMPLOYEE VIEW: DISBURSE LOANS ================= -->
                <div id="employee-disburse-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Approved Loans Ready for Disbursement</h5>
                            <small class="text-muted">Disburse approved funds to borrower accounts</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Account No</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $approvedLoansList = array_filter($allSystemLoans, fn($l) => $l['status'] === 'approved');
                                    if (empty($approvedLoansList)): 
                                    ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No approved loans ready for disbursement.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($approvedLoansList as $loan): ?>
                                            <tr>
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($loan['full_name']) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($loan['account_number'] ?? 'N/A') ?></td>
                                                <td>$<?= number_format($loan['amount'], 2) ?></td>
                                                <td><span class="badge bg-success-subtle text-success text-capitalize"><?= htmlspecialchars($loan['status']) ?></span></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold fs-7 disburse-loan-btn" data-loan-id="<?= $loan['id'] ?>">Disburse Funds</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <!-- ================= ADMIN VIEW: ACCOUNT HOLDERS ================= -->
                <div id="admin-holders-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Account Holders Directory</h5>
                            <small class="text-muted">Click any row to inspect account & transactions</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($accountHolders)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No account holders found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($accountHolders as $holder): ?>
                                            <tr class="user-row" style="cursor: pointer;" data-user-id="<?= $holder['id'] ?>" data-bs-toggle="modal" data-bs-target="#adminUserDetailModal">
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($holder['full_name']) ?></td>
                                                <td><?= htmlspecialchars($holder['email']) ?></td>
                                                <td><?= htmlspecialchars($holder['created_at'] ?? 'N/A') ?></td>
                                                <td><span class="badge bg-success-subtle text-success">Active</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= ADMIN VIEW: EMPLOYEES ================= -->
                <div id="admin-employees-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Employees Directory</h5>
                            <small class="text-muted">Click any row to inspect staff account history and disbursements</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employees)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No employees found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr class="employee-row" style="cursor: pointer;" data-user-id="<?= $emp['id'] ?>" data-bs-toggle="modal" data-bs-target="#adminEmployeeDetailModal">
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($emp['full_name']) ?></td>
                                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                                <td><?= htmlspecialchars($emp['created_at'] ?? 'N/A') ?></td>
                                                <td><span class="badge bg-primary-subtle text-primary">Working Staff</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= ADMIN VIEW: MANAGERS & ADMINS ================= -->
                <div id="admin-managers-view" class="app-view flex-grow-1 d-none flex-column min-vh-0 overflow-auto">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-navy mb-0">Managers & Administrators Directory</h5>
                            <small class="text-muted">Click any row to inspect manager loan approvals and activity</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role Tier</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($managers)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No managers found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($managers as $mgr): ?>
                                            <tr class="manager-row" style="cursor: pointer;" data-user-id="<?= $mgr['id'] ?>" data-bs-toggle="modal" data-bs-target="#adminManagerDetailModal">
                                                <td class="fw-semibold text-navy"><?= htmlspecialchars($mgr['full_name']) ?></td>
                                                <td><?= htmlspecialchars($mgr['email']) ?></td>
                                                <td><span class="badge bg-warning-subtle text-warning-emphasis text-capitalize"><?= htmlspecialchars($mgr['role']) ?></span></td>
                                                <td><span class="badge bg-success-subtle text-success">Controller</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODALS -->
    <!-- PAY LOAN MONTHS MODAL -->
    <div class="modal fade" id="payLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Pay Loan Installment (Advance Payment Supported)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="payLoanForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="loan_id" id="pay_loan_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Select Months to Pay (Max Allowed)</label>
                            <input type="number" min="1" max="12" name="pay_months" id="pay_months_input" class="form-control custom-input fw-bold" value="1" oninput="calculateInstallmentPayment()" required>
                            <small class="text-muted fs-7" id="max_months_hint">You can pay multiple months in advance.</small>
                        </div>

                        <div class="card border border-warning-subtle bg-light p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted fw-semibold fs-7">Total Amount to Pay:</span>
                                <span class="fw-bold text-danger fs-6" id="pay_total_amount_display">$0.00</span>
                            </div>
                            <small class="text-muted mt-1 fs-7" id="pay_breakdown_note">1 month installment breakdown.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="submit" class="btn btn-navy-landbank w-100 py-2 rounded-pill fs-7 fw-semibold">Confirm Payment via Deposit Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SEND PAYMENT ALERT MODAL -->
    <div class="modal fade" id="sendAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Send Payment Due Alert</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="sendAlertForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="user_id" id="alert_user_id">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Borrower</label>
                            <input type="text" id="alert_borrower_name" class="form-control custom-input" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Alert Message</label>
                            <textarea name="message" class="form-control custom-input" rows="3" required>Hello! This is a reminder that your loan payment is due soon. Please settle your account balance.</textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="submit" class="btn btn-navy-landbank w-100 py-2 rounded-pill fs-7 fw-semibold">Send Real-Time Alert</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- OVERRIDE BALANCE MODAL -->
    <div class="modal fade" id="overrideBalanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Override Account Balance</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="overrideBalanceForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="account_id" id="override_acc_id">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Account Holder</label>
                            <input type="text" id="override_holder_name" class="form-control custom-input" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">New Balance ($)</label>
                            <input type="number" step="0.01" name="new_balance" id="override_new_balance" class="form-control custom-input fw-bold" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Reason / Note</label>
                            <input type="text" name="reason" class="form-control custom-input" placeholder="e.g. Administrative adjustment" value="Manager/Admin Balance Override" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="submit" class="btn btn-navy-landbank w-100 py-2 rounded-pill fs-7 fw-semibold">Confirm Balance Override</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADMIN USER DETAIL INSPECTOR MODAL -->
    <div class="modal fade" id="adminUserDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Client Account & Transaction Inspector</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <h6 class="fw-bold text-navy fs-7 text-uppercase">Account Information</h6>
                        <div class="p-3 bg-light rounded-3">
                            <div class="row g-2">
                                <div class="col-md-6"><small class="text-muted d-block">Full Name</small><span id="admin_modal_name" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Email Address</small><span id="admin_modal_email" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Phone Number</small><span id="admin_modal_phone" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Account Number / Link</small><span id="admin_modal_acc" class="fw-semibold text-navy font-monospace">-</span></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold text-navy fs-7 text-uppercase mb-2">Transaction History</h6>
                        <div class="d-flex flex-column gap-2 overflow-auto" id="admin_modal_tx_list" style="max-height: 250px;">
                            <p class="text-muted text-center py-3 mb-0 fs-7">Select an account holder to view logs.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN EMPLOYEE DETAIL INSPECTOR MODAL -->
    <div class="modal fade" id="adminEmployeeDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Staff & Disbursement History Inspector</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <h6 class="fw-bold text-navy fs-7 text-uppercase">Employee Information</h6>
                        <div class="p-3 bg-light rounded-3">
                            <div class="row g-2">
                                <div class="col-md-6"><small class="text-muted d-block">Full Name</small><span id="admin_emp_modal_name" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Email Address</small><span id="admin_emp_modal_email" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Role Tier</small><span class="fw-semibold text-navy text-capitalize">Employee / Staff</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Status</small><span class="badge bg-success-subtle text-success">Active Staff</span></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold text-navy fs-7 text-uppercase mb-2">Processed Transactions & Disbursements</h6>
                        <div class="d-flex flex-column gap-2 overflow-auto" id="admin_emp_modal_tx_list" style="max-height: 250px;">
                            <p class="text-muted text-center py-3 mb-0 fs-7">Select an employee to view history.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN MANAGER DETAIL INSPECTOR MODAL -->
    <div class="modal fade" id="adminManagerDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Manager Approval & Activity History</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <h6 class="fw-bold text-navy fs-7 text-uppercase">Manager Information</h6>
                        <div class="p-3 bg-light rounded-3">
                            <div class="row g-2">
                                <div class="col-md-6"><small class="text-muted d-block">Full Name</small><span id="admin_mgr_modal_name" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Email Address</small><span id="admin_mgr_modal_email" class="fw-semibold text-navy">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Role Tier</small><span id="admin_mgr_modal_role" class="fw-semibold text-navy text-capitalize">-</span></div>
                                <div class="col-md-6"><small class="text-muted d-block">Status</small><span class="badge bg-success-subtle text-success">Active Controller</span></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold text-navy fs-7 text-uppercase mb-2">Loan Approvals & Portfolio Actions</h6>
                        <div class="d-flex flex-column gap-2 overflow-auto" id="admin_mgr_modal_tx_list" style="max-height: 250px;">
                            <p class="text-muted text-center py-3 mb-0 fs-7">Select a manager to view history.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION MODAL -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill text-primary-custom fs-5"></i>
                        <h6 class="modal-title fw-bold text-navy mb-0">Real-Time Notifications</h6>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-column gap-2 overflow-auto" style="max-height: 350px;">
                        <?php if (empty($userNotifications)): ?>
                            <p class="text-muted text-center py-3 mb-0 fs-7">No real-time notifications at this time.</p>
                        <?php else: ?>
                            <?php foreach ($userNotifications as $n): ?>
                                <div class="p-2 bg-light rounded-3 d-flex align-items-start gap-2">
                                    <i class="bi bi-info-circle-fill text-primary fs-6 mt-1"></i>
                                    <div>
                                        <p class="text-navy mb-0 fs-7 fw-semibold">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </p>
                                        <small class="text-muted fs-7"><?= date('M d, Y g:i A', strtotime($n['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LOAN MODAL -->
    <div class="modal fade" id="loanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-wallet-fill text-warning fs-5"></i>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Loan Credit Application</h6>
                            <small class="text-white-50 fs-7">Instant cash loan with monthly installments</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="loanForm">
                    <div class="modal-body p-4">
                        <div class="card border-0 bg-primary-subtle p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted fw-semibold fs-7">Available Credit Limit</span>
                                    <h4 class="fw-bold text-navy mb-0">$<?= number_format($availableCreditLimit, 2) ?></h4>
                                </div>
                                <span class="badge bg-primary rounded-pill px-3 py-1 fs-7">Loan Active</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Loan Amount ($)</label>
                            <input type="number" step="10" name="amount" id="loan_amount" class="form-control custom-input fw-bold" placeholder="e.g. 500" oninput="calculateLoanMonthly()" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy fs-7">Repayment Tenure</label>
                            <select name="term_months" id="loan_term" class="form-select custom-input" onchange="calculateLoanMonthly()" required>
                                <option value="3" selected>3 Months Installment (3% interest)</option>
                                <option value="6">6 Months Installment (5% interest)</option>
                                <option value="12">12 Months Installment (8% interest)</option>
                            </select>
                        </div>

                        <div class="card border border-warning-subtle bg-light p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted fw-semibold fs-7">Estimated Monthly Payment:</span>
                                <span class="fw-bold text-danger fs-6" id="loan_monthly_display">$0.00 / mo</span>
                            </div>
                            <small class="text-muted mt-1 fs-7" id="loan_interest_rate_note">*Interest rate: 3% fixed rate for 3 months.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="submit" class="btn btn-navy-landbank w-100 py-2 rounded-pill fs-7 fw-semibold">Submit Loan Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FUNDS TRANSACT MODAL -->
    <div class="modal fade" id="transactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-navy text-white py-3 px-4">
                    <h6 class="modal-title fw-bold mb-0">Process Funds Transaction</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="transactForm">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-navy fs-7">Transaction Type</label>
                            <select name="type" class="form-select custom-input" required>
                                <option value="debit">Debit (Withdraw / Transfer)</option>
                                <option value="credit">Credit (Deposit)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-navy fs-7">Amount ($)</label>
                            <input type="number" step="0.01" name="amount" class="form-control custom-input" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-navy fs-7">Description</label>
                            <input type="text" name="description" class="form-control custom-input" placeholder="e.g. Utility Deposit">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-navy-landbank w-100 py-2 rounded-pill fs-7 fw-semibold">Confirm Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        const maxCreditLimit = <?= (float)$availableCreditLimit ?>;

        function calculateLoanMonthly() {
            const amountInput = document.getElementById('loan_amount');
            const termInput = document.getElementById('loan_term');
            const display = document.getElementById('loan_monthly_display');
            const note = document.getElementById('loan_interest_rate_note');
            const submitBtn = document.querySelector('#loanForm button[type="submit"]');

            if (!amountInput || !display) return;

            const amount = parseFloat(amountInput.value) || 0;
            const months = parseInt(termInput ? termInput.value : 3) || 3;

            let rate = 0.03;
            if (months === 6) rate = 0.05;
            else if (months === 12) rate = 0.08;

            if (amount > maxCreditLimit) {
                display.innerText = "Exceeds Limit!";
                display.classList.remove('text-danger');
                display.classList.add('text-warning');
                
                if (note) {
                    note.innerText = `*Maximum loanable amount is $${maxCreditLimit.toFixed(2)}.`;
                    note.classList.add('text-danger');
                }
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            if (submitBtn) submitBtn.disabled = false;
            display.classList.remove('text-warning');
            display.classList.add('text-danger');

            if (note) {
                note.classList.remove('text-danger');
                note.innerText = `*Interest rate: ${(rate * 100)}% fixed rate for ${months} months.`;
            }

            if (amount <= 0) {
                display.innerText = '$0.00 / mo';
                return;
            }

            const totalInterest = amount * rate;
            const totalRepayment = amount + totalInterest;
            const monthlyPayment = totalRepayment / months;

            display.innerText = '$' + monthlyPayment.toFixed(2) + ' / mo';
        }

        $('#loanModal').on('shown.bs.modal', function () {
            calculateLoanMonthly();
        });

        let currentLoanTotalPayable = 0;
        let currentLoanTermMonths = 3;
        let currentLoanPaidSoFar = 0;

        // Capture loan details when opening the modal (Supports advance payment / max remaining limit checking)
        $('.open-pay-modal-btn').on('click', function() {
            const loanId = $(this).data('loan-id');
            currentLoanTotalPayable = parseFloat($(this).data('total-payable')) || 0;
            currentLoanTermMonths = parseInt($(this).data('max-term')) || 3;
            currentLoanPaidSoFar = parseFloat($(this).data('paid-so-far')) || 0;

            const monthlyInstallment = currentLoanTotalPayable / Math.max(1, currentLoanTermMonths);
            const remainingBalance = currentLoanTotalPayable - currentLoanPaidSoFar;
            const maxRemainingMonths = Math.ceil(remainingBalance / monthlyInstallment);

            $('#pay_loan_id').val(loanId);
            $('#pay_months_input').val(1);
            $('#pay_months_input').attr('max', maxRemainingMonths);
            $('#max_months_hint').text(`You can pay up to ${maxRemainingMonths} remaining month(s) (supports advance payments).`);
            
            calculateInstallmentPayment();
            $('#payLoanModal').modal('show');
        });

        function calculateInstallmentPayment() {
            const monthsInput = document.getElementById('pay_months_input');
            const display = document.getElementById('pay_total_amount_display');
            const note = document.getElementById('pay_breakdown_note');

            if (!monthsInput || !display) return;

            let monthsToPay = parseInt(monthsInput.value) || 1;
            const monthlyInstallment = currentLoanTotalPayable / Math.max(1, currentLoanTermMonths);
            const remainingBalance = currentLoanTotalPayable - currentLoanPaidSoFar;
            const maxRemainingMonths = Math.ceil(remainingBalance / monthlyInstallment);

            if (monthsToPay < 1) monthsToPay = 1;
            if (monthsToPay > maxRemainingMonths) {
                monthsToPay = maxRemainingMonths;
                monthsInput.value = maxRemainingMonths;
            }

            let calculatedAmount = monthlyInstallment * monthsToPay;
            if (calculatedAmount > remainingBalance) {
                calculatedAmount = remainingBalance;
            }

            display.innerText = '$' + calculatedAmount.toFixed(2);
            note.innerText = `${monthsToPay} month(s) @ $${monthlyInstallment.toFixed(2)} / month.`;
        }

        // Submit Pay Loan Months Form
        $('#payLoanForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/loan_action.php?action=pay',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        $('#payLoanModal').modal('hide');
                        setTimeout(() => location.reload(), 1500);
                    }
                },
                error: function() {
                    showToast('Failed to process loan payment.', 'error');
                }
            });
        });

        // Send Payment Alert Modal Trigger
        $('.send-alert-btn').on('click', function() {
            $('#alert_user_id').val($(this).data('user-id'));
            $('#alert_borrower_name').val($(this).data('borrower'));
            $('#sendAlertModal').modal('show');
        });

        // Submit Payment Alert
        $('#sendAlertForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/staff_action.php?action=send_alert',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        $('#sendAlertModal').modal('hide');
                    }
                },
                error: function() {
                    showToast('Failed to send payment alert.', 'error');
                }
            });
        });

        <?php if ($role === 'admin'): ?>
        // Admin Account Holder Row Click Handler
        $('.user-row').on('click', function() {
            const userId = $(this).data('user-id');
            const name = $(this).find('td:eq(0)').text();
            const email = $(this).find('td:eq(1)').text();

            $('#admin_modal_name').text(name);
            $('#admin_modal_email').text(email);
            $('#admin_modal_phone').text('Verified Client');
            $('#admin_modal_acc').text('Linked Active Account');
            $('#admin_modal_tx_list').html('<p class="text-muted text-center py-3 mb-0 fs-7">Loading transactions...</p>');

            $.ajax({
                url: `api/admin_action.php?action=get_user_history&user_id=${userId}`,
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status) {
                        let txHtml = '';
                        if (res.data.length === 0) {
                            txHtml = '<p class="text-muted text-center py-3 mb-0 fs-7">No transactions recorded.</p>';
                        } else {
                            res.data.forEach(tx => {
                                const isCredit = tx.type === 'credit';
                                txHtml += `
                                    <div class="p-2 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2 rounded-3 ${isCredit ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">
                                                <i class="bi ${isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right'} fs-6"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-navy mb-0 fs-7">${tx.description || (isCredit ? 'Deposit' : 'Withdrawal')}</h6>
                                                <small class="text-muted fs-7">Acc: ${tx.account_number} • ${tx.created_at}</small>
                                            </div>
                                        </div>
                                        <span class="fw-bold fs-7 ${isCredit ? 'text-success' : 'text-danger'}">
                                            ${isCredit ? '+' : '-'}$${parseFloat(tx.amount).toFixed(2)}
                                        </span>
                                    </div>
                                `;
                            });
                        }
                        $('#admin_modal_tx_list').html(txHtml);
                    } else {
                        showToast(res.message, 'error');
                    }
                }
            });
        });

        // Admin Employee Row Click Handler (Disbursements/History)
        $('.employee-row').on('click', function() {
            const userId = $(this).data('user-id');
            const name = $(this).find('td:eq(0)').text();
            const email = $(this).find('td:eq(1)').text();

            $('#admin_emp_modal_name').text(name);
            $('#admin_emp_modal_email').text(email);
            $('#admin_emp_modal_tx_list').html('<p class="text-muted text-center py-3 mb-0 fs-7">Loading staff activity logs...</p>');

            $.ajax({
                url: `api/admin_action.php?action=get_user_history&user_id=${userId}`,
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status) {
                        let html = '';
                        if (res.data.length === 0) {
                            html = '<p class="text-muted text-center py-3 mb-0 fs-7">No disbursements or entries found.</p>';
                        } else {
                            res.data.forEach(tx => {
                                html += `<div class="p-2 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                    <div><h6 class="fw-bold text-navy mb-0 fs-7">${tx.description || 'Disbursement Action'}</h6><small class="text-muted">${tx.created_at}</small></div>
                                    <span class="fw-bold text-success">+$${parseFloat(tx.amount).toFixed(2)}</span>
                                </div>`;
                            });
                        }
                        $('#admin_emp_modal_tx_list').html(html);
                    }
                }
            });
        });

        // Admin Manager Row Click Handler (Loan approvals/activity)
        $('.manager-row').on('click', function() {
            const userId = $(this).data('user-id');
            const name = $(this).find('td:eq(0)').text();
            const email = $(this).find('td:eq(1)').text();
            const roleTier = $(this).find('td:eq(2)').text();

            $('#admin_mgr_modal_name').text(name);
            $('#admin_mgr_modal_email').text(email);
            $('#admin_mgr_modal_role').text(roleTier);
            $('#admin_mgr_modal_tx_list').html('<p class="text-muted text-center py-3 mb-0 fs-7">Loading manager activity history...</p>');

            $.ajax({
                url: `api/admin_action.php?action=get_user_history&user_id=${userId}`,
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status) {
                        let html = '';
                        if (res.data.length === 0) {
                            html = '<p class="text-muted text-center py-3 mb-0 fs-7">No recorded actions under this manager.</p>';
                        } else {
                            res.data.forEach(tx => {
                                html += `<div class="p-2 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                    <div><h6 class="fw-bold text-navy mb-0 fs-7">${tx.description || 'Approval Log'}</h6><small class="text-muted">${tx.created_at}</small></div>
                                    <span class="fw-bold text-navy">$${parseFloat(tx.amount).toFixed(2)}</span>
                                </div>`;
                            });
                        }
                        $('#admin_mgr_modal_tx_list').html(html);
                    }
                }
            });
        });
        <?php endif; ?>

        // Employee Loan Disbursement Handler
        $(document).on('click', '.disburse-loan-btn', function() {
            const loanId = $(this).data('loan-id');
            $.ajax({
                url: 'api/loan_action.php?action=disburse',
                type: 'POST',
                data: { loan_id: loanId },
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        setTimeout(() => location.reload(), 1200);
                    }
                }
            });
        });

        // Manager Loan Approval / Rejection Handlers
        $(document).on('click', '.approve-loan-btn, .reject-loan-btn', function() {
            const loanId = $(this).data('loan-id');
            const status = $(this).hasClass('approve-loan-btn') ? 'approved' : 'rejected';

            $.ajax({
                url: 'api/manager_action.php?action=update_loan_status',
                type: 'POST',
                data: { loan_id: loanId, status: status },
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        setTimeout(() => location.reload(), 1200);
                    }
                }
            });
        });

        // Populate Balance Override Modal
        $('.override-balance-btn').on('click', function() {
            $('#override_acc_id').val($(this).data('acc-id'));
            $('#override_holder_name').val($(this).data('holder'));
            $('#override_new_balance').val($(this).data('balance'));
        });

        // Submit Balance Override
        $('#overrideBalanceForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/manager_action.php?action=override_balance',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        $('#overrideBalanceModal').modal('hide');
                        setTimeout(() => location.reload(), 1200);
                    }
                }
            });
        });

        // Create Account Holder Form Submission
        $('#createAccountHolderForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/manager_action.php?action=create_account_holder',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    showToast(res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        $('#createAccountHolderForm')[0].reset();
                        setTimeout(() => location.reload(), 1500);
                    }
                }
            });
        });

        // Create Staff Form Submission
        $('#createStaffForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/manager_action.php?action=create_staff',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    showToast(res.status ? `Staff created! Username: ${res.username} | Password: ${res.password}` : res.message, res.status ? 'success' : 'error');
                    if (res.status) {
                        $('#createStaffForm')[0].reset();
                    }
                }
            });
        });

        // Sidebar Tab Switcher Logic
        $('.nav-tab-btn').on('click', function(e) {
            e.preventDefault();
            $('.nav-tab-btn').removeClass('active');
            $(this).addClass('active');

            const target = $(this).data('target');
            $('.app-view').addClass('d-none').removeClass('d-flex');
            $('#' + target).removeClass('d-none').addClass('d-flex');

            if (target === 'accounts-view') {
                $('#mainHeaderTitle').text('Account Holder Profile & Settings');
            } else if (target === 'loans-view') {
                $('#mainHeaderTitle').text('Loan History & Repayment');
            } else if (target === 'manager-approvals-view') {
                $('#mainHeaderTitle').text('Loan Approvals');
            } else if (target === 'manager-loans-view') {
                $('#mainHeaderTitle').text('Loan Portfolio & Alerts Tracker');
            } else if (target === 'manager-accounts-view') {
                $('#mainHeaderTitle').text('Balance Override & Accounts');
            } else if (target === 'manager-create-user-view') {
                $('#mainHeaderTitle').text('Create User Accounts');
            } else if (target === 'employee-disburse-view') {
                $('#mainHeaderTitle').text('Disburse Loans');
            } else if (target === 'admin-holders-view') {
                $('#mainHeaderTitle').text('Account Holders Directory');
            } else if (target === 'admin-employees-view') {
                $('#mainHeaderTitle').text('Employees Directory');
            } else if (target === 'admin-managers-view') {
                $('#mainHeaderTitle').text('Managers & Administrators Directory');
            } else {
                $('#mainHeaderTitle').text('<?= ($role === 'admin' || $role === 'manager' || $role === 'employee') ? 'Admin Overview' : 'Dashboard' ?>');
            }
        });

        function fitTransactions() {
            const container = document.getElementById('recent-tx-list');
            if (!container) return;
            
            const items = container.querySelectorAll('.tx-item');
            items.forEach(item => item.style.display = 'flex');
            
            const containerBottom = container.getBoundingClientRect().bottom;
            items.forEach(item => {
                const itemBottom = item.getBoundingClientRect().bottom;
                if (itemBottom > containerBottom) {
                    item.style.display = 'none';
                }
            });
        }

        $(window).on('load resize', fitTransactions);

        $('#logoutBtn').on('click', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api/auth.php?action=logout',
                type: 'GET',
                dataType: 'json',
                success: function() {
                    window.location.href = 'index.php';
                }
            });
        });
    </script>
</body>
</html>