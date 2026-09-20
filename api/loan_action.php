<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../classes/Loan.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$loanClass = new Loan();
$action = $_GET['action'] ?? '';
$user = $_SESSION['user'];

if ($action === 'apply') {
    $amount = (float)($_POST['amount'] ?? 0);
    $termMonths = (int)($_POST['term_months'] ?? 3);
    
    $res = $loanClass->applyLoan($user['id'], $amount, $termMonths);
    echo json_encode($res);
} elseif ($action === 'disburse') {
    $loanId = (int)($_POST['loan_id'] ?? 0);
    $res = $loanClass->disburseLoan($loanId);
    echo json_encode($res);
} elseif ($action === 'pay') {
    $loanId = (int)($_POST['loan_id'] ?? 0);
    $payMonths = (int)($_POST['pay_months'] ?? 1);
    $res = $loanClass->payLoan($loanId, $user['id'], $payMonths);
    echo json_encode($res);
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid action.']);
}
?>