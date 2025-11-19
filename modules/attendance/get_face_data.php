<?php
/**
 * Get Enrolled Face Data for Current Employee
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$employee_id = $_SESSION['employee_id'] ?? null;

if (!$user_id || !$employee_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the most recent active face descriptor
$result = executeQuery(
    "SELECT face_descriptor FROM face_data
     WHERE employee_id = ? AND is_active = 1 AND is_deleted = 0
     ORDER BY created_at DESC LIMIT 1",
    "i",
    [$employee_id]
);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $descriptor = json_decode($row['face_descriptor'], true);

    echo json_encode([
        'success' => true,
        'descriptor' => $descriptor
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No enrolled face data found'
    ]);
}
