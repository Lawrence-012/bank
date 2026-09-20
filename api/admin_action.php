<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../classes/User.php';
require_once '../classes/Transaction.php';
require_once '../classes/Notification.php';

// Check if user is logged in AND is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$transClass = new Transaction();
$action = $_GET['action'] ?? '';

if ($action === 'get_user_history') {
    $userId = (int)($_GET['user_id'] ?? 0);
    try {
        $history = $transClass->getTransactionsByUserId($userId);
        echo json_encode(['status' => true, 'data' => $history]);
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>