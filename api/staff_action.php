<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../classes/Notification.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager', 'employee'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$notifClass = new Notification();
$action = $_GET['action'] ?? '';

if ($action === 'send_alert') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['status' => false, 'message' => 'Alert message cannot be empty.']);
        exit;
    }

    $success = $notifClass->sendNotification($userId, $message);
    echo json_encode(['status' => $success, 'message' => $success ? 'Payment alert sent successfully!' : 'Failed to send alert.']);
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid staff action endpoint.']);
}
?>