<?php
/**
 * Save Face Enrollment Data
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$face_descriptor_json = $_POST['face_descriptor'] ?? '';
$face_images_json = $_POST['face_images'] ?? '';

if (!$face_descriptor_json) {
    echo json_encode(['success' => false, 'message' => 'Face descriptor is required']);
    exit;
}

// Save face images
$image_path = null;
if ($face_images_json) {
    $images = json_decode($face_images_json, true);
    if (!empty($images)) {
        // Save the first image as the enrollment photo
        $image_path = saveFaceImage($images[0], $employee_id);
    }
}

$conn = getDbConnection();

// Deactivate previous face data for this employee
$conn->query("UPDATE face_data SET is_active = 0, updated_by = $user_id WHERE employee_id = $employee_id AND is_deleted = 0");

// Insert new face data
$stmt = $conn->prepare("
    INSERT INTO face_data (
        employee_id, face_descriptor, face_image_path,
        is_active, created_by, updated_by
    ) VALUES (?, ?, ?, 1, ?, ?)
");

$stmt->bind_param("issii", $employee_id, $face_descriptor_json, $image_path, $user_id, $user_id);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    logActivity($user_id, 'ENROLL', 'face_data', $new_id, null, json_encode(['employee_id' => $employee_id]));

    echo json_encode([
        'success' => true,
        'message' => 'Face enrollment completed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt->error
    ]);
}

/**
 * Save face image to file system
 */
function saveFaceImage($base64_image, $employee_id) {
    // Remove data:image/jpeg;base64, prefix
    $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $base64_image);
    $image_data = base64_decode($image_data);

    if (!$image_data) {
        return null;
    }

    $upload_dir = __DIR__ . '/../../uploads/face_enrollment/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'emp_' . $employee_id . '_' . date('Ymd_His') . '.jpg';
    $filepath = $upload_dir . $filename;

    if (file_put_contents($filepath, $image_data)) {
        return 'uploads/face_enrollment/' . $filename;
    }

    return null;
}
