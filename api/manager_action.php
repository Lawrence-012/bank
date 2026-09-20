<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../classes/Loan.php';
require_once '../classes/User.php';
require_once '../classes/Account.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['manager', 'admin'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$loanClass = new Loan();
$userClass = new User();
$accountClass = new Account();
$action = $_GET['action'] ?? '';

if ($action === 'update_loan_status') {
    $loanId = (int)($_POST['loan_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $res = $loanClass->updateLoanStatus($loanId, $status);
    echo json_encode($res);
} elseif ($action === 'create_account_holder') {
    $res = $userClass->registerAccountHolder(
        $_POST['full_name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['username'] ?? '',
        $_POST['password'] ?? ''
    );
    echo json_encode($res);
} elseif ($action === 'create_staff') {
    $res = $userClass->createStaff(
        $_POST['full_name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['role'] ?? 'employee'
    );
    echo json_encode($res);
} elseif ($action === 'override_balance') {
    $accountId = (int)($_POST['account_id'] ?? 0);
    $newBalance = (float)($_POST['new_balance'] ?? 0);
    $reason = $_POST['reason'] ?? 'Manager/Admin Balance Override';
    $res = $accountClass->overrideBalance($accountId, $newBalance, $reason);
    echo json_encode($res);
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid manager action.']);
}
?>