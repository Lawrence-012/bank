<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../classes/User.php';

$userClass = new User();
$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $res = $userClass->registerAccountHolder(
        $_POST['full_name'] ?? '', 
        $_POST['email'] ?? '', 
        $_POST['phone'] ?? '',
        $_POST['username'] ?? '',
        $_POST['password'] ?? ''
    );
    echo json_encode($res);
} elseif ($action === 'login') {
    $res = $userClass->login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($res) {
        $_SESSION['user'] = $res;
        echo json_encode(['status' => true, 'role' => $res['role']]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid credentials!']);
    }
} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => true]);
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid authentication endpoint.']);
}
?>