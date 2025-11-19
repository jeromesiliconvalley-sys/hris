<?php
session_start();
require_once '../../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$session_id = $_SESSION['session_id'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

if ($user_id) {
    if ($session_id) {
        $stmt = $conn->prepare("UPDATE user_sessions SET logout_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, action, table_name, description, ip_address, user_agent, created_at)
        VALUES (?, 'LOGOUT', 'users', 'User logged out', ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();

header("Location: login.php?logout=1");
exit;
?>