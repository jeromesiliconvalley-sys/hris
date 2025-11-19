<?php
/**
 * Employee AJAX Handler
 * Handles AJAX requests for employee background data (dependents, education, work history)
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$response = ['success' => false, 'message' => ''];

try {
    $user_id = getCurrentUserId();

    if (!$user_id) {
        throw new Exception('Unauthorized access');
    }

    switch ($action) {

        // ==================== DEPENDENTS ====================

        case 'add_dependent':
            $stmt = $conn->prepare("
                INSERT INTO employee_dependents
                (employee_id, first_name, middle_name, last_name, relationship, birthdate, is_beneficiary, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssssii",
                $input['employee_id'],
                $input['first_name'],
                $input['middle_name'],
                $input['last_name'],
                $input['relationship'],
                $input['birthdate'],
                $input['is_beneficiary'],
                $user_id,
                $user_id
            );

            if ($stmt->execute()) {
                logActivity($user_id, 'ADD', 'employee_dependents', $stmt->insert_id, null, json_encode($input));
                $response['success'] = true;
                $response['message'] = 'Dependent added successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        case 'delete_dependent':
            $stmt = $conn->prepare("
                UPDATE employee_dependents
                SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $user_id, $input['id']);

            if ($stmt->execute()) {
                logActivity($user_id, 'DELETE', 'employee_dependents', $input['id']);
                $response['success'] = true;
                $response['message'] = 'Dependent deleted successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        // ==================== EDUCATION ====================

        case 'add_education':
            $stmt = $conn->prepare("
                INSERT INTO employee_education
                (employee_id, level, school_name, course, year_started, year_ended, is_graduated, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $year_started = !empty($input['year_started']) ? $input['year_started'] : null;
            $year_ended = !empty($input['year_ended']) ? $input['year_ended'] : null;
            $course = !empty($input['course']) ? $input['course'] : null;

            $stmt->bind_param(
                "isssiisii",
                $input['employee_id'],
                $input['level'],
                $input['school_name'],
                $course,
                $year_started,
                $year_ended,
                $input['is_graduated'],
                $user_id,
                $user_id
            );

            if ($stmt->execute()) {
                logActivity($user_id, 'ADD', 'employee_education', $stmt->insert_id, null, json_encode($input));
                $response['success'] = true;
                $response['message'] = 'Education record added successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        case 'delete_education':
            $stmt = $conn->prepare("
                UPDATE employee_education
                SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $user_id, $input['id']);

            if ($stmt->execute()) {
                logActivity($user_id, 'DELETE', 'employee_education', $input['id']);
                $response['success'] = true;
                $response['message'] = 'Education record deleted successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        // ==================== WORK HISTORY ====================

        case 'add_work_history':
            $stmt = $conn->prepare("
                INSERT INTO employee_work_history
                (employee_id, company_name, position, start_date, end_date, responsibilities, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $end_date = !empty($input['end_date']) ? $input['end_date'] : null;
            $responsibilities = !empty($input['responsibilities']) ? $input['responsibilities'] : null;

            $stmt->bind_param(
                "issssiii",
                $input['employee_id'],
                $input['company_name'],
                $input['position'],
                $input['start_date'],
                $end_date,
                $responsibilities,
                $user_id,
                $user_id
            );

            if ($stmt->execute()) {
                logActivity($user_id, 'ADD', 'employee_work_history', $stmt->insert_id, null, json_encode($input));
                $response['success'] = true;
                $response['message'] = 'Work history added successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        case 'delete_work_history':
            $stmt = $conn->prepare("
                UPDATE employee_work_history
                SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $user_id, $input['id']);

            if ($stmt->execute()) {
                logActivity($user_id, 'DELETE', 'employee_work_history', $input['id']);
                $response['success'] = true;
                $response['message'] = 'Work history deleted successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Employee AJAX Error: " . $e->getMessage());
}

echo json_encode($response);
