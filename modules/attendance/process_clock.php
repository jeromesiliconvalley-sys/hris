<?php
/**
 * Process Clock In/Out Actions
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

$action = $_POST['action'] ?? '';
$face_descriptor_json = $_POST['face_descriptor'] ?? '';
$selfie_image = $_POST['selfie_image'] ?? '';
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// Validate action
$valid_actions = ['time_in', 'lunch_out', 'lunch_in', 'time_out'];
if (!in_array($action, $valid_actions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Verify face against enrolled data
$face_verified = false;
$face_match_distance = null;

if ($face_descriptor_json) {
    $current_descriptor = json_decode($face_descriptor_json, true);

    // Get enrolled face descriptor
    $result = executeQuery(
        "SELECT face_descriptor FROM face_data WHERE employee_id = ? AND is_active = 1 AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1",
        "i",
        [$employee_id]
    );

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $enrolled_descriptor = json_decode($row['face_descriptor'], true);

        // Calculate Euclidean distance
        $distance = euclideanDistance($current_descriptor, $enrolled_descriptor);
        $face_match_distance = $distance;

        // Threshold for face matching (typically 0.6 or lower is a match)
        if ($distance < 0.6) {
            $face_verified = true;
        }
    }
}

// Save selfie image
$selfie_path = null;
if ($selfie_image) {
    $selfie_path = saveSelfieImage($selfie_image, $employee_id, $action);
}

// Get device info
$device_info = json_encode([
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'platform' => getPlatform(),
    'browser' => getBrowser()
]);

$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
$current_time = date('H:i:s');
$today = date('Y-m-d');

// Check if attendance record exists for today
$check_result = executeQuery(
    "SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ? AND is_deleted = 0",
    "is",
    [$employee_id, $today]
);

$conn = getDbConnection();

if ($check_result->num_rows > 0) {
    // Update existing record
    $attendance_row = $check_result->fetch_assoc();
    $attendance_id = $attendance_row['id'];

    $update_fields = [
        $action => $current_time,
        $action . '_latitude' => $latitude,
        $action . '_longitude' => $longitude,
        $action . '_selfie_path' => $selfie_path,
        $action . '_face_verified' => $face_verified ? 1 : 0,
        $action . '_device_info' => $device_info,
        $action . '_ip_address' => $ip_address
    ];

    $set_clause = [];
    $params = [];
    $types = '';

    foreach ($update_fields as $field => $value) {
        $set_clause[] = "`$field` = ?";
        $params[] = $value;

        if ($field === $action . '_face_verified') {
            $types .= 'i';
        } else if ($field === $action . '_latitude' || $field === $action . '_longitude') {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    $params[] = $user_id;
    $types .= 'i';

    $params[] = $attendance_id;
    $types .= 'i';

    $sql = "UPDATE attendance SET " . implode(', ', $set_clause) . ", updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        logActivity($user_id, 'CLOCK', 'attendance', $attendance_id, null, json_encode(['action' => $action, 'face_verified' => $face_verified]));

        echo json_encode([
            'success' => true,
            'message' => getSuccessMessage($action),
            'face_verified' => $face_verified,
            'match_distance' => $face_match_distance
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

} else {
    // Insert new record (only for time_in)
    if ($action !== 'time_in') {
        echo json_encode(['success' => false, 'message' => 'Please clock in first']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO attendance (
            employee_id, attendance_date,
            time_in, time_in_latitude, time_in_longitude, time_in_selfie_path,
            time_in_face_verified, time_in_device_info, time_in_ip_address,
            created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issddssisii",
        $employee_id, $today, $current_time, $latitude, $longitude, $selfie_path,
        $face_verified, $device_info, $ip_address, $user_id, $user_id
    );

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        logActivity($user_id, 'CLOCK', 'attendance', $new_id, null, json_encode(['action' => $action, 'face_verified' => $face_verified]));

        echo json_encode([
            'success' => true,
            'message' => getSuccessMessage($action),
            'face_verified' => $face_verified,
            'match_distance' => $face_match_distance
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
}

/**
 * Calculate Euclidean distance between two face descriptors
 */
function euclideanDistance($descriptor1, $descriptor2) {
    if (count($descriptor1) !== count($descriptor2)) {
        return PHP_FLOAT_MAX;
    }

    $sum = 0;
    for ($i = 0; $i < count($descriptor1); $i++) {
        $diff = $descriptor1[$i] - $descriptor2[$i];
        $sum += $diff * $diff;
    }

    return sqrt($sum);
}

/**
 * Save selfie image to file system
 */
function saveSelfieImage($base64_image, $employee_id, $action) {
    // Remove data:image/jpeg;base64, prefix
    $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $base64_image);
    $image_data = base64_decode($image_data);

    if (!$image_data) {
        return null;
    }

    $upload_dir = __DIR__ . '/../../uploads/attendance/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = $employee_id . '_' . date('Ymd_His') . '_' . $action . '.jpg';
    $filepath = $upload_dir . $filename;

    if (file_put_contents($filepath, $image_data)) {
        return 'uploads/attendance/' . $filename;
    }

    return null;
}

/**
 * Get platform from user agent
 */
function getPlatform() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (preg_match('/linux/i', $user_agent)) return 'Linux';
    if (preg_match('/macintosh|mac os x/i', $user_agent)) return 'Mac';
    if (preg_match('/windows|win32/i', $user_agent)) return 'Windows';
    if (preg_match('/android/i', $user_agent)) return 'Android';
    if (preg_match('/iphone|ipad|ipod/i', $user_agent)) return 'iOS';

    return 'Unknown';
}

/**
 * Get browser from user agent
 */
function getBrowser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (preg_match('/MSIE|Trident/i', $user_agent)) return 'Internet Explorer';
    if (preg_match('/Firefox/i', $user_agent)) return 'Firefox';
    if (preg_match('/Chrome/i', $user_agent)) return 'Chrome';
    if (preg_match('/Safari/i', $user_agent)) return 'Safari';
    if (preg_match('/Opera|OPR/i', $user_agent)) return 'Opera';
    if (preg_match('/Edge/i', $user_agent)) return 'Edge';

    return 'Unknown';
}

/**
 * Get success message for action
 */
function getSuccessMessage($action) {
    $messages = [
        'time_in' => 'Successfully clocked in! Have a productive day!',
        'lunch_out' => 'Enjoy your lunch break!',
        'lunch_in' => 'Welcome back! Ready to work!',
        'time_out' => 'Successfully clocked out! See you tomorrow!'
    ];

    return $messages[$action] ?? 'Action completed successfully';
}
