<?php
/**
 * Employee Management Module
 *
 * Comprehensive employee records management system
 * - List all employees with search and filters
 * - Add new employee with multi-tab form
 * - Edit existing employee
 * - View employee details
 * - Delete employee (soft delete)
 *
 * @package HRIS
 * @author HRIS Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

// Get action and employee ID
$action = $_GET['action'] ?? 'list';
$employee_id = $_GET['id'] ?? null;

// Initialize variables
$errors = [];
$employee = null;
$employees = [];

// ==================== HELPER FUNCTIONS ====================

/**
 * Strip non-numeric characters from government ID
 */
function cleanGovernmentID($id) {
    return preg_replace('/[^0-9]/', '', $id);
}

/**
 * Format SSS for display: 12-3456789-0
 */
function formatSSS($sss) {
    if (empty($sss)) return 'N/A';
    $clean = cleanGovernmentID($sss);
    if (strlen($clean) !== 10) return $sss;
    return substr($clean, 0, 2) . '-' . substr($clean, 2, 7) . '-' . substr($clean, 9, 1);
}

/**
 * Format PhilHealth for display: 12-345678901-2
 */
function formatPhilHealth($philhealth) {
    if (empty($philhealth)) return 'N/A';
    $clean = cleanGovernmentID($philhealth);
    if (strlen($clean) !== 12) return $philhealth;
    return substr($clean, 0, 2) . '-' . substr($clean, 2, 9) . '-' . substr($clean, 11, 1);
}

/**
 * Format Pag-IBIG for display: 1234-5678-9012
 */
function formatPagIBIG($pagibig) {
    if (empty($pagibig)) return 'N/A';
    $clean = cleanGovernmentID($pagibig);
    if (strlen($clean) !== 12) return $pagibig;
    return substr($clean, 0, 4) . '-' . substr($clean, 4, 4) . '-' . substr($clean, 8, 4);
}

/**
 * Format TIN for display: 123-456-789-000
 */
function formatTIN($tin) {
    if (empty($tin)) return 'N/A';
    $clean = cleanGovernmentID($tin);
    if (strlen($clean) !== 12) return $tin;
    return substr($clean, 0, 3) . '-' . substr($clean, 3, 3) . '-' . substr($clean, 6, 3) . '-' . substr($clean, 9, 3);
}

/**
 * Calculate age from birthdate
 */
function calculateAge($birthdate) {
    $dob = new DateTime($birthdate);
    $now = new DateTime();
    return $now->diff($dob)->y;
}

// ==================== POST PROCESSING ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=employees");
        exit;
    }

    $post_action = $_POST['action'] ?? '';
    $data = sanitizeInput($_POST);

    // Clean government IDs
    if (!empty($data['sss_number'])) {
        $data['sss_number'] = cleanGovernmentID($data['sss_number']);
    }
    if (!empty($data['philhealth_number'])) {
        $data['philhealth_number'] = cleanGovernmentID($data['philhealth_number']);
    }
    if (!empty($data['pagibig_number'])) {
        $data['pagibig_number'] = cleanGovernmentID($data['pagibig_number']);
    }
    if (!empty($data['tin_number'])) {
        $data['tin_number'] = cleanGovernmentID($data['tin_number']);
    }

    // Validate required fields
    $required_fields = [
        'employee_number' => 'Employee Number',
        'last_name' => 'Last Name',
        'first_name' => 'First Name',
        'gender' => 'Gender',
        'civil_status' => 'Civil Status',
        'birthdate' => 'Birthdate',
        'contact_number' => 'Contact Number',
        'email' => 'Email',
        'sss_number' => 'SSS Number',
        'philhealth_number' => 'PhilHealth Number',
        'pagibig_number' => 'Pag-IBIG Number',
        'tin_number' => 'TIN Number',
        'company_id' => 'Company',
        'organizational_unit_id' => 'Organizational Unit',
        'position_id' => 'Position',
        'date_hired' => 'Date Hired',
        'employment_status_id' => 'Employment Status',
        'employment_type_id' => 'Employment Type'
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = "$label is required";
        }
    }

    // Validate government IDs length
    if (!empty($data['sss_number']) && strlen($data['sss_number']) !== 10) {
        $errors[] = "SSS Number must be 10 digits";
    }
    if (!empty($data['philhealth_number']) && strlen($data['philhealth_number']) !== 12) {
        $errors[] = "PhilHealth Number must be 12 digits";
    }
    if (!empty($data['pagibig_number']) && strlen($data['pagibig_number']) !== 12) {
        $errors[] = "Pag-IBIG Number must be 12 digits";
    }
    if (!empty($data['tin_number']) && strlen($data['tin_number']) !== 12) {
        $errors[] = "TIN must be 12 digits";
    }

    // Validate email
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Process if no validation errors
    if (empty($errors)) {
        $user_id = getCurrentUserId();

        if ($post_action === 'add') {
            // Check for duplicate employee number
            $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_number = ? AND is_deleted = 0");
            $check_stmt->bind_param("s", $data['employee_number']);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $errors[] = "Employee number already exists";
            } else {
                // Insert new employee
                $stmt = $conn->prepare("
                    INSERT INTO employees (
                        employee_number, last_name, first_name, middle_name, suffix, nickname,
                        gender, civil_status, birthdate, birthplace, nationality, religion, blood_type,
                        contact_number, email,
                        sss_number, philhealth_number, pagibig_number, tin_number,
                        current_building_name, current_unit_number, current_house_number, current_street_name,
                        current_barangay, current_city, current_province, current_region, current_postal_code,
                        permanent_building_name, permanent_unit_number, permanent_house_number, permanent_street_name,
                        permanent_barangay, permanent_city, permanent_province, permanent_region, permanent_postal_code,
                        company_id, organizational_unit_id, position_id, immediate_head_id,
                        date_hired, employment_end_date, regularization_date,
                        employment_status_id, employment_type_id,
                        basic_salary, is_minimum_wage, shift_schedule_id, schedule_type,
                        payroll_group, pay_type, bank_name, bank_account_number,
                        is_active, created_by, updated_by
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?
                    )
                ");

                $is_active = isset($data['is_active']) ? 1 : 0;
                $is_minimum_wage = isset($data['is_minimum_wage']) ? 1 : 0;
                $immediate_head_id = !empty($data['immediate_head_id']) ? $data['immediate_head_id'] : null;
                $employment_end_date = !empty($data['employment_end_date']) ? $data['employment_end_date'] : null;
                $regularization_date = !empty($data['regularization_date']) ? $data['regularization_date'] : null;
                $shift_schedule_id = !empty($data['shift_schedule_id']) ? $data['shift_schedule_id'] : null;
                $basic_salary = !empty($data['basic_salary']) ? $data['basic_salary'] : null;
                $bank_name = !empty($data['bank_name']) ? $data['bank_name'] : null;
                $bank_account_number = !empty($data['bank_account_number']) ? $data['bank_account_number'] : null;

                $stmt->bind_param(
                    "sssssssssssssssssssssssssssssssssssssiiiissiiidississiii",
                    $data['employee_number'], $data['last_name'], $data['first_name'], $data['middle_name'], $data['suffix'], $data['nickname'],
                    $data['gender'], $data['civil_status'], $data['birthdate'], $data['birthplace'], $data['nationality'], $data['religion'], $data['blood_type'],
                    $data['contact_number'], $data['email'],
                    $data['sss_number'], $data['philhealth_number'], $data['pagibig_number'], $data['tin_number'],
                    $data['current_building_name'], $data['current_unit_number'], $data['current_house_number'], $data['current_street_name'],
                    $data['current_barangay'], $data['current_city'], $data['current_province'], $data['current_region'], $data['current_postal_code'],
                    $data['permanent_building_name'], $data['permanent_unit_number'], $data['permanent_house_number'], $data['permanent_street_name'],
                    $data['permanent_barangay'], $data['permanent_city'], $data['permanent_province'], $data['permanent_region'], $data['permanent_postal_code'],
                    $data['company_id'], $data['organizational_unit_id'], $data['position_id'], $immediate_head_id,
                    $data['date_hired'], $employment_end_date, $regularization_date,
                    $data['employment_status_id'], $data['employment_type_id'],
                    $basic_salary, $is_minimum_wage, $shift_schedule_id, $data['schedule_type'],
                    $data['payroll_group'], $data['pay_type'], $bank_name, $bank_account_number,
                    $is_active, $user_id, $user_id
                );

                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    logActivity($user_id, 'ADD', 'employees', $new_id, null, json_encode($data));
                    setFlashMessage("Employee added successfully!", "success");
                    header("Location: index.php?page=employees");
                    exit;
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                }
            }

        } elseif ($post_action === 'edit' && !empty($data['id'])) {
            // Update existing employee
            $stmt = $conn->prepare("
                UPDATE employees SET
                    employee_number = ?, last_name = ?, first_name = ?, middle_name = ?, suffix = ?, nickname = ?,
                    gender = ?, civil_status = ?, birthdate = ?, birthplace = ?, nationality = ?, religion = ?, blood_type = ?,
                    contact_number = ?, email = ?,
                    sss_number = ?, philhealth_number = ?, pagibig_number = ?, tin_number = ?,
                    current_building_name = ?, current_unit_number = ?, current_house_number = ?, current_street_name = ?,
                    current_barangay = ?, current_city = ?, current_province = ?, current_region = ?, current_postal_code = ?,
                    permanent_building_name = ?, permanent_unit_number = ?, permanent_house_number = ?, permanent_street_name = ?,
                    permanent_barangay = ?, permanent_city = ?, permanent_province = ?, permanent_region = ?, permanent_postal_code = ?,
                    company_id = ?, organizational_unit_id = ?, position_id = ?, immediate_head_id = ?,
                    date_hired = ?, employment_end_date = ?, regularization_date = ?,
                    employment_status_id = ?, employment_type_id = ?,
                    basic_salary = ?, is_minimum_wage = ?, shift_schedule_id = ?, schedule_type = ?,
                    payroll_group = ?, pay_type = ?, bank_name = ?, bank_account_number = ?,
                    is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND is_deleted = 0
            ");

            $is_active = isset($data['is_active']) ? 1 : 0;
            $is_minimum_wage = isset($data['is_minimum_wage']) ? 1 : 0;
            $immediate_head_id = !empty($data['immediate_head_id']) ? $data['immediate_head_id'] : null;
            $employment_end_date = !empty($data['employment_end_date']) ? $data['employment_end_date'] : null;
            $regularization_date = !empty($data['regularization_date']) ? $data['regularization_date'] : null;
            $shift_schedule_id = !empty($data['shift_schedule_id']) ? $data['shift_schedule_id'] : null;
            $basic_salary = !empty($data['basic_salary']) ? $data['basic_salary'] : null;
            $bank_name = !empty($data['bank_name']) ? $data['bank_name'] : null;
            $bank_account_number = !empty($data['bank_account_number']) ? $data['bank_account_number'] : null;

            $stmt->bind_param(
                "sssssssssssssssssssssssssssssssssssssiiiissiiidississiiii",
                $data['employee_number'], $data['last_name'], $data['first_name'], $data['middle_name'], $data['suffix'], $data['nickname'],
                $data['gender'], $data['civil_status'], $data['birthdate'], $data['birthplace'], $data['nationality'], $data['religion'], $data['blood_type'],
                $data['contact_number'], $data['email'],
                $data['sss_number'], $data['philhealth_number'], $data['pagibig_number'], $data['tin_number'],
                $data['current_building_name'], $data['current_unit_number'], $data['current_house_number'], $data['current_street_name'],
                $data['current_barangay'], $data['current_city'], $data['current_province'], $data['current_region'], $data['current_postal_code'],
                $data['permanent_building_name'], $data['permanent_unit_number'], $data['permanent_house_number'], $data['permanent_street_name'],
                $data['permanent_barangay'], $data['permanent_city'], $data['permanent_province'], $data['permanent_region'], $data['permanent_postal_code'],
                $data['company_id'], $data['organizational_unit_id'], $data['position_id'], $immediate_head_id,
                $data['date_hired'], $employment_end_date, $regularization_date,
                $data['employment_status_id'], $data['employment_type_id'],
                $basic_salary, $is_minimum_wage, $shift_schedule_id, $data['schedule_type'],
                $data['payroll_group'], $data['pay_type'], $bank_name, $bank_account_number,
                $is_active, $user_id, $data['id']
            );

            if ($stmt->execute()) {
                logActivity($user_id, 'EDIT', 'employees', $data['id'], null, json_encode($data));
                setFlashMessage("Employee updated successfully!", "success");
                header("Location: index.php?page=employees");
                exit;
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
        }
    }

    // Store errors in session
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $data;
    }
}

// ==================== DELETE ACTION ====================

if ($action === 'delete' && $employee_id) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlashMessage("Invalid security token.", "error");
        header("Location: index.php?page=employees");
        exit;
    }

    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("
        UPDATE employees
        SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $user_id, $employee_id);

    if ($stmt->execute()) {
        logActivity($user_id, 'DELETE', 'employees', $employee_id);
        setFlashMessage("Employee deleted successfully!", "success");
    } else {
        setFlashMessage("Error deleting employee: " . $stmt->error, "error");
    }

    header("Location: index.php?page=employees");
    exit;
}

// ==================== DATA FETCHING ====================

// Fetch dropdown data
$companies = $conn->query("SELECT id, name FROM companies WHERE is_deleted = 0 AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$organizational_units = $conn->query("SELECT id, unit_name FROM organizational_units WHERE is_deleted = 0 AND is_active = 1 ORDER BY unit_name")->fetch_all(MYSQLI_ASSOC);
$positions = $conn->query("SELECT id, position_title FROM positions WHERE is_deleted = 0 AND is_active = 1 ORDER BY position_title")->fetch_all(MYSQLI_ASSOC);
$employment_statuses = $conn->query("SELECT id, status_name FROM employment_statuses ORDER BY status_name")->fetch_all(MYSQLI_ASSOC);
$employment_types = $conn->query("SELECT id, type_name FROM employment_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
$all_employees = $conn->query("SELECT id, CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, '')) AS full_name FROM employees WHERE is_deleted = 0 AND is_active = 1 ORDER BY last_name, first_name")->fetch_all(MYSQLI_ASSOC);

if ($action === 'edit' && $employee_id) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();

    if (!$employee) {
        setFlashMessage("Employee not found", "error");
        header("Location: index.php?page=employees");
        exit;
    }

    if (isset($_SESSION['form_data'])) {
        $employee = array_merge($employee, $_SESSION['form_data']);
        unset($_SESSION['form_data']);
    }
}

if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $company_filter = $_GET['company_id'] ?? '';
    $status_filter = $_GET['status'] ?? 'active';

    $query = "
        SELECT e.*,
               c.name as company_name,
               ou.unit_name,
               p.position_title,
               es.status_name,
               et.type_name,
               CONCAT(head.last_name, ', ', head.first_name) as immediate_head_name
        FROM employees e
        LEFT JOIN companies c ON e.company_id = c.id
        LEFT JOIN organizational_units ou ON e.organizational_unit_id = ou.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN employment_statuses es ON e.employment_status_id = es.id
        LEFT JOIN employment_types et ON e.employment_type_id = et.id
        LEFT JOIN employees head ON e.immediate_head_id = head.id AND head.is_deleted = 0
        WHERE e.is_deleted = 0
    ";

    $params = [];
    $types = "";

    if ($status_filter === 'active') {
        $query .= " AND e.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND e.is_active = 0";
    }

    if (!empty($company_filter)) {
        $query .= " AND e.company_id = ?";
        $params[] = $company_filter;
        $types .= "i";
    }

    if (!empty($search)) {
        $query .= " AND (e.employee_number LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }

    $query .= " ORDER BY e.last_name, e.first_name";

    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    $employees = $result->fetch_all(MYSQLI_ASSOC);
}

$flash_message = getFlashMessage();
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-people me-2"></i>
            <?php
            if ($action === 'add') {
                echo 'Add New Employee';
            } elseif ($action === 'edit') {
                echo 'Edit Employee';
            } else {
                echo 'Employees';
            }
            ?>
        </h1>
        <p class="text-muted mb-0">Manage employee records and information</p>
    </div>

    <?php if ($action === 'list'): ?>
    <div>
        <a href="index.php?page=employees&action=add" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Add Employee
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Flash Messages -->
<?php if ($flash_message): ?>
<div class="alert alert-<?= escapeHtml($flash_message['type']) ?> alert-dismissible fade show" role="alert">
    <?= escapeHtml($flash_message['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Validation Errors -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Please correct the following errors:</h5>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= escapeHtml($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label">Search</label>
                <input type="text" class="form-control" id="filterSearch"
                       placeholder="Search by name, number, or email">
            </div>

            <div class="col-md-2">
                <label for="filterCompany" class="form-label">Company</label>
                <select class="form-select" id="filterCompany">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= $company['id'] ?>">
                            <?= escapeHtml($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="filterUnit" class="form-label">Organizational Unit</label>
                <select class="form-select" id="filterUnit">
                    <option value="">All Units</option>
                    <?php foreach ($organizational_units as $unit): ?>
                        <option value="<?= $unit['id'] ?>">
                            <?= escapeHtml($unit['unit_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="filterPosition" class="form-label">Position</label>
                <select class="form-select" id="filterPosition">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= $position['id'] ?>">
                            <?= escapeHtml($position['position_title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="filterEmploymentStatus" class="form-label">Employment Status</label>
                <select class="form-select" id="filterEmploymentStatus">
                    <option value="">All Statuses</option>
                    <?php foreach ($employment_statuses as $status): ?>
                        <option value="<?= $status['id'] ?>">
                            <?= escapeHtml($status['status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1">
                <label for="filterActive" class="form-label">Active</label>
                <select class="form-select" id="filterActive">
                    <option value="">All</option>
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <button type="button" class="btn btn-secondary btn-sm" id="resetFilters">
                    <i class="bi bi-x-circle me-2"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Employees Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($employees)): ?>
        <div class="text-center py-5">
            <i class="bi bi-people" style="font-size: 4rem; color: #ccc;"></i>
            <p class="text-muted mt-3">No employees found</p>
            <a href="index.php?page=employees&action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add First Employee
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee #</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Company</th>
                        <th>Organizational Unit</th>
                        <th>Immediate Head</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody">
                    <?php foreach ($employees as $emp): ?>
                    <tr data-employee-id="<?= $emp['id'] ?>"
                        data-company-id="<?= $emp['company_id'] ?? '' ?>"
                        data-unit-id="<?= $emp['organizational_unit_id'] ?? '' ?>"
                        data-position-id="<?= $emp['position_id'] ?? '' ?>"
                        data-status-id="<?= $emp['employment_status_id'] ?? '' ?>"
                        data-is-active="<?= $emp['is_active'] ?>"
                        data-search-text="<?= strtolower(escapeHtml($emp['employee_number'] . ' ' . $emp['last_name'] . ' ' . $emp['first_name'] . ' ' . ($emp['middle_name'] ?? '') . ' ' . ($emp['email'] ?? ''))) ?>">
                        <td><strong><?= escapeHtml($emp['employee_number']) ?></strong></td>
                        <td>
                            <?= escapeHtml($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . ($emp['middle_name'] ?? '')) ?>
                        </td>
                        <td><?= escapeHtml($emp['position_title'] ?? 'N/A') ?></td>
                        <td><?= escapeHtml($emp['company_name'] ?? 'N/A') ?></td>
                        <td><?= escapeHtml($emp['unit_name'] ?? 'N/A') ?></td>
                        <td><?= escapeHtml($emp['immediate_head_name'] ?? 'N/A') ?></td>
                        <td>
                            <small><?= escapeHtml($emp['contact_number']) ?></small><br>
                            <small class="text-muted"><?= escapeHtml($emp['email']) ?></small>
                        </td>
                        <td>
                            <?php if ($emp['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=employees&action=edit&id=<?= $emp['id'] ?>"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="index.php?page=employees&action=delete&id=<?= $emp['id'] ?>&csrf_token=<?= generateCsrfToken() ?>"
                                   class="btn btn-outline-danger"
                                   title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this employee?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <p class="text-muted mb-0" id="employeeCount">
                <i class="bi bi-info-circle me-2"></i>
                Showing <?= count($employees) ?> employee(s)
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for AJAX Filtering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all filter elements
    const filterSearch = document.getElementById('filterSearch');
    const filterCompany = document.getElementById('filterCompany');
    const filterUnit = document.getElementById('filterUnit');
    const filterPosition = document.getElementById('filterPosition');
    const filterEmploymentStatus = document.getElementById('filterEmploymentStatus');
    const filterActive = document.getElementById('filterActive');
    const resetFiltersBtn = document.getElementById('resetFilters');
    const tableBody = document.getElementById('employeeTableBody');
    const employeeCount = document.getElementById('employeeCount');

    // Function to apply filters
    function applyFilters() {
        const searchTerm = filterSearch ? filterSearch.value.toLowerCase() : '';
        const companyId = filterCompany ? filterCompany.value : '';
        const unitId = filterUnit ? filterUnit.value : '';
        const positionId = filterPosition ? filterPosition.value : '';
        const statusId = filterEmploymentStatus ? filterEmploymentStatus.value : '';
        const activeFilter = filterActive ? filterActive.value : '';

        if (!tableBody) return;

        const rows = tableBody.getElementsByTagName('tr');
        let visibleCount = 0;

        for (let row of rows) {
            let show = true;

            // Search filter
            if (searchTerm && row.dataset.searchText) {
                if (!row.dataset.searchText.includes(searchTerm)) {
                    show = false;
                }
            }

            // Company filter
            if (companyId && row.dataset.companyId !== companyId) {
                show = false;
            }

            // Organizational Unit filter
            if (unitId && row.dataset.unitId !== unitId) {
                show = false;
            }

            // Position filter
            if (positionId && row.dataset.positionId !== positionId) {
                show = false;
            }

            // Employment Status filter
            if (statusId && row.dataset.statusId !== statusId) {
                show = false;
            }

            // Active filter
            if (activeFilter !== '' && row.dataset.isActive !== activeFilter) {
                show = false;
            }

            // Show or hide row
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        }

        // Update count
        if (employeeCount) {
            const totalCount = rows.length;
            const countText = visibleCount === totalCount
                ? `Showing ${totalCount} employee(s)`
                : `Showing ${visibleCount} of ${totalCount} employee(s)`;
            employeeCount.innerHTML = `<i class="bi bi-info-circle me-2"></i>${countText}`;
        }
    }

    // Attach event listeners to all filter fields
    if (filterSearch) {
        filterSearch.addEventListener('input', applyFilters);
    }
    if (filterCompany) {
        filterCompany.addEventListener('change', applyFilters);
    }
    if (filterUnit) {
        filterUnit.addEventListener('change', applyFilters);
    }
    if (filterPosition) {
        filterPosition.addEventListener('change', applyFilters);
    }
    if (filterEmploymentStatus) {
        filterEmploymentStatus.addEventListener('change', applyFilters);
    }
    if (filterActive) {
        filterActive.addEventListener('change', applyFilters);
    }

    // Reset filters functionality
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function() {
            if (filterSearch) filterSearch.value = '';
            if (filterCompany) filterCompany.value = '';
            if (filterUnit) filterUnit.value = '';
            if (filterPosition) filterPosition.value = '';
            if (filterEmploymentStatus) filterEmploymentStatus.value = '';
            if (filterActive) filterActive.value = '1'; // Default to Active

            applyFilters();
        });
    }

    // Apply initial filter (Active only by default)
    applyFilters();
});
</script>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

<!-- Add/Edit Form -->
<style>
/* Tab Styling - Make ALL tabs clearly visible */
.nav-tabs .nav-link {
    font-weight: 500;
    color: #495057 !important;
    background-color: #e9ecef;
    border: 1px solid #dee2e6;
    margin-right: 2px;
    opacity: 1 !important;
}

.nav-tabs .nav-link:hover {
    background-color: #dee2e6;
    color: #000 !important;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
    color: #0d6efd !important;
    background-color: #fff;
    border-bottom-color: #fff;
}

/* Form Styling */
.form-label {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.3rem;
    color: #212529;
}

.form-control, .form-select {
    font-size: 0.95rem;
}

small.text-muted {
    font-size: 0.75rem;
    display: block;
    margin-top: 0.2rem;
}

.tab-content {
    padding: 1.5rem;
    border: 1px solid #dee2e6;
    border-top: none;
    background-color: #fff;
}

h5 {
    color: #0d6efd;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

hr {
    margin: 1.5rem 0;
}

/* Birthplace autocomplete dropdown */
#birthplace-dropdown {
    position: absolute;
    z-index: 1000;
    background: white;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    max-height: 200px;
    overflow-y: auto;
    width: 100%;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: none;
}

#birthplace-dropdown .dropdown-item {
    padding: 0.5rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}

#birthplace-dropdown .dropdown-item:hover {
    background-color: #f8f9fa;
}

#birthplace-dropdown .dropdown-item:last-child {
    border-bottom: none;
}
</style>

<div class="card">
    <div class="card-body">
        <form method="POST" action="index.php?page=employees" id="employeeForm">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            <?php endif; ?>

            <!-- Multi-Tab Navigation -->
            <ul class="nav nav-tabs mb-4" id="employeeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button">
                        <i class="bi bi-person me-2"></i>Personal Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                        <i class="bi bi-geo-alt me-2"></i>Contact & Address
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button">
                        <i class="bi bi-briefcase me-2"></i>Employment Details
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button">
                        <i class="bi bi-cash me-2"></i>Payroll & Banking
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="background-tab" data-bs-toggle="tab" data-bs-target="#background" type="button">
                        <i class="bi bi-file-person me-2"></i>Dependents & Background
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="employeeTabContent">

                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="row g-3">
                        <!-- Row 1: Employee Number, Name Fields -->
                        <div class="col-lg-3 col-md-4">
                            <label for="employee_number" class="form-label">EMPLOYEE NUMBER <span class="text-danger">*</span></label>
                            <?php if ($action === 'add'): ?>
                                <input type="text" class="form-control" id="employee_number" name="employee_number" required
                                       value="<?= escapeHtml($employee['employee_number'] ?? 'AUTO') ?>"
                                       placeholder="AUTO or enter custom">
                                <small class="text-muted">Leave as AUTO for system-generated ID</small>
                            <?php else: ?>
                                <input type="text" class="form-control" id="employee_number" name="employee_number" required
                                       value="<?= escapeHtml($employee['employee_number'] ?? '') ?>" readonly>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-3 col-md-4">
                            <label for="last_name" class="form-label">LAST NAME <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                   value="<?= escapeHtml($employee['last_name'] ?? '') ?>">
                        </div>

                        <div class="col-lg-3 col-md-4">
                            <label for="first_name" class="form-label">FIRST NAME <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required
                                   value="<?= escapeHtml($employee['first_name'] ?? '') ?>">
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="middle_name" class="form-label">MIDDLE NAME</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name"
                                   value="<?= escapeHtml($employee['middle_name'] ?? '') ?>">
                        </div>

                        <div class="col-lg-1 col-md-6">
                            <label for="suffix" class="form-label">SUFFIX</label>
                            <select class="form-select" id="suffix" name="suffix">
                                <option value="">-</option>
                                <option value="Jr." <?= ($employee['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                <option value="Sr." <?= ($employee['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                <option value="II" <?= ($employee['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                                <option value="III" <?= ($employee['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                                <option value="IV" <?= ($employee['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                                <option value="V" <?= ($employee['suffix'] ?? '') === 'V' ? 'selected' : '' ?>>V</option>
                            </select>
                        </div>

                        <!-- Row 2: Personal Info -->
                        <div class="col-lg-3 col-md-4">
                            <label for="nickname" class="form-label">NICKNAME</label>
                            <input type="text" class="form-control" id="nickname" name="nickname"
                                   value="<?= escapeHtml($employee['nickname'] ?? '') ?>">
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label for="gender" class="form-label">GENDER <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select</option>
                                <option value="Male" <?= ($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-4">
                            <label for="civil_status" class="form-label">CIVIL STATUS <span class="text-danger">*</span></label>
                            <select class="form-select" id="civil_status" name="civil_status" required>
                                <option value="">Select</option>
                                <option value="Single" <?= ($employee['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($employee['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Separated" <?= ($employee['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                                <option value="Widowed" <?= ($employee['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                <option value="Annulled" <?= ($employee['civil_status'] ?? '') === 'Annulled' ? 'selected' : '' ?>>Annulled</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="birthdate" class="form-label">BIRTHDATE <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="birthdate" name="birthdate" required
                                   value="<?= escapeHtml($employee['birthdate'] ?? '') ?>">
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="blood_type" class="form-label">BLOOD TYPE</label>
                            <select class="form-select" id="blood_type" name="blood_type">
                                <option value="Unknown">Unknown</option>
                                <?php
                                $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($blood_types as $bt) {
                                    $selected = ($employee['blood_type'] ?? 'Unknown') === $bt ? 'selected' : '';
                                    echo "<option value='$bt' $selected>$bt</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Row 3: Birthplace, Nationality, Religion -->
                        <div class="col-lg-6 col-md-6" style="position: relative;">
                            <label for="birthplace" class="form-label">BIRTHPLACE</label>
                            <input type="text" class="form-control" id="birthplace" name="birthplace"
                                   autocomplete="off"
                                   value="<?= escapeHtml($employee['birthplace'] ?? '') ?>"
                                   placeholder="Start typing city/municipality...">
                            <div id="birthplace-dropdown"></div>
                            <small class="text-muted">Type 3+ characters to search Philippine cities/municipalities</small>
                        </div>

                        <div class="col-lg-3 col-md-3">
                            <label for="nationality" class="form-label">NATIONALITY</label>
                            <input type="text" class="form-control" id="nationality" name="nationality"
                                   value="<?= escapeHtml($employee['nationality'] ?? 'Filipino') ?>">
                        </div>

                        <div class="col-lg-3 col-md-3">
                            <label for="religion" class="form-label">RELIGION</label>
                            <select class="form-select" id="religion" name="religion">
                                <?php
                                $religions = ['Roman Catholic', 'Islam', 'Iglesia ni Cristo', 'Protestant', 'Buddhist', 'Born Again Christian', 'Jehovah\'s Witness', 'Seventh-day Adventist', 'Other', 'None'];
                                foreach ($religions as $rel) {
                                    $selected = ($employee['religion'] ?? 'Roman Catholic') === $rel ? 'selected' : '';
                                    echo "<option value='" . escapeHtml($rel) . "' $selected>" . escapeHtml($rel) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12"><hr></div>

                        <!-- Government IDs -->
                        <div class="col-12"><h5><i class="bi bi-card-list me-2"></i>GOVERNMENT IDs</h5></div>

                        <div class="col-md-3">
                            <label for="sss_number" class="form-label">SSS NUMBER <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sss_number" name="sss_number" required
                                   value="<?= escapeHtml($employee['sss_number'] ?? '') ?>"
                                   placeholder="12-3456789-0" maxlength="12"
                                   pattern="\d{2}-\d{7}-\d{1}" title="Format: 12-3456789-0 (10 digits)">
                            <small class="text-muted">Format: 12-3456789-0</small>
                            <div class="invalid-feedback">SSS format: 12-3456789-0</div>
                        </div>

                        <div class="col-md-3">
                            <label for="philhealth_number" class="form-label">PHILHEALTH NUMBER <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="philhealth_number" name="philhealth_number" required
                                   value="<?= escapeHtml($employee['philhealth_number'] ?? '') ?>"
                                   placeholder="12-345678901-2" maxlength="14"
                                   pattern="\d{2}-\d{9}-\d{1}" title="Format: 12-345678901-2 (12 digits)">
                            <small class="text-muted">Format: 12-345678901-2</small>
                            <div class="invalid-feedback">PhilHealth format: 12-345678901-2</div>
                        </div>

                        <div class="col-md-3">
                            <label for="pagibig_number" class="form-label">PAG-IBIG NUMBER <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pagibig_number" name="pagibig_number" required
                                   value="<?= escapeHtml($employee['pagibig_number'] ?? '') ?>"
                                   placeholder="1234-5678-9012" maxlength="14"
                                   pattern="\d{4}-\d{4}-\d{4}" title="Format: 1234-5678-9012 (12 digits)">
                            <small class="text-muted">Format: 1234-5678-9012</small>
                            <div class="invalid-feedback">Pag-IBIG format: 1234-5678-9012</div>
                        </div>

                        <div class="col-md-3">
                            <label for="tin_number" class="form-label">TIN NUMBER <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tin_number" name="tin_number" required
                                   value="<?= escapeHtml($employee['tin_number'] ?? '') ?>"
                                   placeholder="123-456-789-000" maxlength="15"
                                   pattern="\d{3}-\d{3}-\d{3}-\d{3}" title="Format: 123-456-789-000 (12 digits)">
                            <small class="text-muted">Format: 123-456-789-000</small>
                            <div class="invalid-feedback">TIN format: 123-456-789-000</div>
                        </div>
                    </div>
                </div>

                <!-- Contact & Address Tab -->
                <div class="tab-pane fade" id="contact" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contact_number" class="form-label">CONTACT NUMBER <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" required
                                   value="<?= escapeHtml($employee['contact_number'] ?? '') ?>"
                                   placeholder="09XX XXX XXXX or +63 9XX XXX XXXX"
                                   pattern="^(09|\+639)\d{9}$" title="Philippine mobile number format">
                            <small class="text-muted">Format: 09XXXXXXXXX or +639XXXXXXXXX</small>
                            <div class="invalid-feedback">Enter valid Philippine mobile number</div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">EMAIL ADDRESS <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?= escapeHtml($employee['email'] ?? '') ?>"
                                   pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                   placeholder="email@example.com">
                            <div class="invalid-feedback">Enter a valid email address</div>
                        </div>

                        <div class="col-12"><hr><h5><i class="bi bi-house me-2"></i>CURRENT ADDRESS</h5></div>

                        <div class="col-md-6">
                            <label for="current_building_name" class="form-label">BUILDING NAME</label>
                            <input type="text" class="form-control" id="current_building_name" name="current_building_name"
                                   value="<?= escapeHtml($employee['current_building_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="current_unit_number" class="form-label">UNIT NUMBER</label>
                            <input type="text" class="form-control" id="current_unit_number" name="current_unit_number"
                                   value="<?= escapeHtml($employee['current_unit_number'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="current_house_number" class="form-label">HOUSE NUMBER</label>
                            <input type="text" class="form-control" id="current_house_number" name="current_house_number"
                                   value="<?= escapeHtml($employee['current_house_number'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="current_street_name" class="form-label">STREET NAME</label>
                            <input type="text" class="form-control" id="current_street_name" name="current_street_name"
                                   value="<?= escapeHtml($employee['current_street_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="current_region" class="form-label">REGION</label>
                            <select id="current_region" class="form-control">
                                <option value="">Select Region</option>
                            </select>
                            <input type="hidden" name="current_region" id="current_region_name">
                        </div>

                        <div class="col-md-3">
                            <label for="current_province" class="form-label">PROVINCE</label>
                            <select id="current_province" class="form-control" disabled>
                                <option value="">Select Province</option>
                            </select>
                            <input type="hidden" name="current_province" id="current_province_name">
                        </div>

                        <div class="col-md-3">
                            <label for="current_city" class="form-label">CITY/MUNICIPALITY</label>
                            <select id="current_city" class="form-control" disabled>
                                <option value="">Select City</option>
                            </select>
                            <input type="hidden" name="current_city" id="current_city_name">
                        </div>

                        <div class="col-md-3">
                            <label for="current_barangay" class="form-label">BARANGAY</label>
                            <select id="current_barangay" class="form-control" disabled>
                                <option value="">Select Barangay</option>
                            </select>
                            <input type="hidden" name="current_barangay" id="current_barangay_name">
                        </div>

                        <div class="col-md-12">
                            <label for="current_postal_code" class="form-label">POSTAL CODE</label>
                            <input type="text" class="form-control" id="current_postal_code" name="current_postal_code"
                                   value="<?= escapeHtml($employee['current_postal_code'] ?? '') ?>">
                        </div>

                        <div class="col-12"><hr><h5><i class="bi bi-house-door me-2"></i>Permanent Address</h5></div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sameAsCurrentAddress">
                                <label class="form-check-label" for="sameAsCurrentAddress">
                                    Same as current address
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="permanent_building_name" class="form-label">BUILDING NAME</label>
                            <input type="text" class="form-control" id="permanent_building_name" name="permanent_building_name"
                                   value="<?= escapeHtml($employee['permanent_building_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_unit_number" class="form-label">UNIT NUMBER</label>
                            <input type="text" class="form-control" id="permanent_unit_number" name="permanent_unit_number"
                                   value="<?= escapeHtml($employee['permanent_unit_number'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_house_number" class="form-label">HOUSE NUMBER</label>
                            <input type="text" class="form-control" id="permanent_house_number" name="permanent_house_number"
                                   value="<?= escapeHtml($employee['permanent_house_number'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="permanent_street_name" class="form-label">STREET NAME</label>
                            <input type="text" class="form-control" id="permanent_street_name" name="permanent_street_name"
                                   value="<?= escapeHtml($employee['permanent_street_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_region" class="form-label">REGION</label>
                            <select id="permanent_region" class="form-control">
                                <option value="">Select Region</option>
                            </select>
                            <input type="hidden" name="permanent_region" id="permanent_region_name">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_province" class="form-label">PROVINCE</label>
                            <select id="permanent_province" class="form-control" disabled>
                                <option value="">Select Province</option>
                            </select>
                            <input type="hidden" name="permanent_province" id="permanent_province_name">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_city" class="form-label">CITY/MUNICIPALITY</label>
                            <select id="permanent_city" class="form-control" disabled>
                                <option value="">Select City</option>
                            </select>
                            <input type="hidden" name="permanent_city" id="permanent_city_name">
                        </div>

                        <div class="col-md-3">
                            <label for="permanent_barangay" class="form-label">BARANGAY</label>
                            <select id="permanent_barangay" class="form-control" disabled>
                                <option value="">Select Barangay</option>
                            </select>
                            <input type="hidden" name="permanent_barangay" id="permanent_barangay_name">
                        </div>

                        <div class="col-md-12">
                            <label for="permanent_postal_code" class="form-label">POSTAL CODE</label>
                            <input type="text" class="form-control" id="permanent_postal_code" name="permanent_postal_code"
                                   value="<?= escapeHtml($employee['permanent_postal_code'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Employment Details Tab -->
                <div class="tab-pane fade" id="employment" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="company_id" class="form-label">COMPANY <span class="text-danger">*</span></label>
                            <select class="form-select" id="company_id" name="company_id" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>" <?= ($employee['company_id'] ?? '') == $company['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($company['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="organizational_unit_id" class="form-label">ORGANIZATIONAL UNIT <span class="text-danger">*</span></label>
                            <select class="form-select" id="organizational_unit_id" name="organizational_unit_id" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($organizational_units as $unit): ?>
                                    <option value="<?= $unit['id'] ?>" <?= ($employee['organizational_unit_id'] ?? '') == $unit['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($unit['unit_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="position_id" class="form-label">POSITION <span class="text-danger">*</span></label>
                            <select class="form-select" id="position_id" name="position_id" required>
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?= $position['id'] ?>" <?= ($employee['position_id'] ?? '') == $position['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($position['position_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="immediate_head_id" class="form-label">IMMEDIATE HEAD/SUPERVISOR</label>
                            <select class="form-select" id="immediate_head_id" name="immediate_head_id">
                                <option value="">None</option>
                                <?php foreach ($all_employees as $emp_option): ?>
                                    <?php if ($action === 'edit' && $emp_option['id'] == $employee['id']) continue; ?>
                                    <option value="<?= $emp_option['id'] ?>" <?= ($employee['immediate_head_id'] ?? '') == $emp_option['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($emp_option['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="employment_status_id" class="form-label">EMPLOYMENT STATUS <span class="text-danger">*</span></label>
                            <select class="form-select" id="employment_status_id" name="employment_status_id" required>
                                <option value="">Select Status</option>
                                <?php foreach ($employment_statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= ($employee['employment_status_id'] ?? '') == $status['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($status['status_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="employment_type_id" class="form-label">EMPLOYMENT TYPE <span class="text-danger">*</span></label>
                            <select class="form-select" id="employment_type_id" name="employment_type_id" required>
                                <option value="">Select Type</option>
                                <?php foreach ($employment_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= ($employee['employment_type_id'] ?? '') == $type['id'] ? 'selected' : '' ?>>
                                        <?= escapeHtml($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="date_hired" class="form-label">DATE HIRED <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_hired" name="date_hired" required
                                   value="<?= escapeHtml($employee['date_hired'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="regularization_date" class="form-label">REGULARIZATION DATE</label>
                            <input type="date" class="form-control" id="regularization_date" name="regularization_date"
                                   value="<?= escapeHtml($employee['regularization_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="employment_end_date" class="form-label">EMPLOYMENT END DATE</label>
                            <input type="date" class="form-control" id="employment_end_date" name="employment_end_date"
                                   value="<?= escapeHtml($employee['employment_end_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="schedule_type" class="form-label">SCHEDULE TYPE</label>
                            <select class="form-select" id="schedule_type" name="schedule_type">
                                <option value="Fixed" <?= ($employee['schedule_type'] ?? 'Fixed') === 'Fixed' ? 'selected' : '' ?>>Fixed</option>
                                <option value="Custom" <?= ($employee['schedule_type'] ?? 'Fixed') === 'Custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="shift_schedule_id" class="form-label">DEFAULT SHIFT SCHEDULE</label>
                            <select class="form-select" id="shift_schedule_id" name="shift_schedule_id">
                                <option value="">None</option>
                                <!-- Add shift schedules from database when available -->
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                       <?= ($action === 'add' || ($employee['is_active'] ?? 0)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Active Employee</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll & Banking Tab -->
                <div class="tab-pane fade" id="payroll" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="basic_salary" class="form-label">BASIC SALARY</label>
                            <input type="number" class="form-control" id="basic_salary" name="basic_salary" step="0.01"
                                   value="<?= escapeHtml($employee['basic_salary'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="payroll_group" class="form-label">PAYROLL GROUP</label>
                            <select class="form-select" id="payroll_group" name="payroll_group">
                                <option value="Semi-Monthly" <?= ($employee['payroll_group'] ?? 'Semi-Monthly') === 'Semi-Monthly' ? 'selected' : '' ?>>Semi-Monthly</option>
                                <option value="Monthly" <?= ($employee['payroll_group'] ?? 'Semi-Monthly') === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="is_minimum_wage" name="is_minimum_wage"
                                       <?= ($employee['is_minimum_wage'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_minimum_wage">Minimum Wage Earner</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="pay_type" class="form-label">PAY TYPE</label>
                            <select class="form-select" id="pay_type" name="pay_type">
                                <option value="Cash" <?= ($employee['pay_type'] ?? 'Cash') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="BDO Cash Card" <?= ($employee['pay_type'] ?? 'Cash') === 'BDO Cash Card' ? 'selected' : '' ?>>BDO Cash Card</option>
                                <option value="BDO Debit Card" <?= ($employee['pay_type'] ?? 'Cash') === 'BDO Debit Card' ? 'selected' : '' ?>>BDO Debit Card</option>
                                <option value="Other Bank" <?= ($employee['pay_type'] ?? 'Cash') === 'Other Bank' ? 'selected' : '' ?>>Other Bank</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="bank_name" class="form-label">BANK NAME</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name"
                                   value="<?= escapeHtml($employee['bank_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="bank_account_number" class="form-label">BANK ACCOUNT NUMBER</label>
                            <input type="text" class="form-control" id="bank_account_number" name="bank_account_number"
                                   value="<?= escapeHtml($employee['bank_account_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Dependents & Background Tab -->
                <div class="tab-pane fade" id="background" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Save the employee record first before adding dependents, education, or work history.
                        <?php if ($action === 'edit'): ?>
                            You can now add related information below.
                        <?php endif; ?>
                    </div>

                    <?php if ($action === 'edit' && $employee_id): ?>

                    <!-- Dependents Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Dependents</h5>
                        </div>
                        <div class="card-body">
                            <div id="dependentsList">
                                <?php
                                $dependents_query = $conn->prepare("SELECT * FROM employee_dependents WHERE employee_id = ? AND is_deleted = 0 ORDER BY relationship, birthdate");
                                $dependents_query->bind_param("i", $employee_id);
                                $dependents_query->execute();
                                $dependents = $dependents_query->get_result()->fetch_all(MYSQLI_ASSOC);

                                if (empty($dependents)):
                                ?>
                                <p class="text-muted">No dependents added yet.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Relationship</th>
                                                <th>Birthdate</th>
                                                <th>Age</th>
                                                <th>Beneficiary</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dependents as $dep): ?>
                                            <tr>
                                                <td><?= escapeHtml($dep['first_name'] . ' ' . ($dep['middle_name'] ?? '') . ' ' . $dep['last_name']) ?></td>
                                                <td><?= escapeHtml($dep['relationship']) ?></td>
                                                <td><?= escapeHtml($dep['birthdate']) ?></td>
                                                <td><?= calculateAge($dep['birthdate']) ?> years</td>
                                                <td>
                                                    <?php if ($dep['is_beneficiary']): ?>
                                                        <span class="badge bg-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteDependent(<?= $dep['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="collapse" data-bs-target="#addDependentForm">
                                <i class="bi bi-plus-circle me-2"></i>Add Dependent
                            </button>

                            <div class="collapse mt-3" id="addDependentForm">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Add New Dependent</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="dep_first_name" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Middle Name</label>
                                                <input type="text" class="form-control" id="dep_middle_name">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="dep_last_name" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Relationship <span class="text-danger">*</span></label>
                                                <select class="form-control" id="dep_relationship" required>
                                                    <option value="">Select</option>
                                                    <option value="Spouse">Spouse</option>
                                                    <option value="Child">Child</option>
                                                    <option value="Parent">Parent</option>
                                                    <option value="Sibling">Sibling</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Birthdate <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="dep_birthdate" required>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="dep_is_beneficiary">
                                                    <label class="form-check-label" for="dep_is_beneficiary">
                                                        Beneficiary
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-success" onclick="saveDependent()">Save Dependent</button>
                                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addDependentForm">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Education Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Educational Background</h5>
                        </div>
                        <div class="card-body">
                            <div id="educationList">
                                <?php
                                $education_query = $conn->prepare("SELECT * FROM employee_education WHERE employee_id = ? AND is_deleted = 0 ORDER BY year_ended DESC");
                                $education_query->bind_param("i", $employee_id);
                                $education_query->execute();
                                $education = $education_query->get_result()->fetch_all(MYSQLI_ASSOC);

                                if (empty($education)):
                                ?>
                                <p class="text-muted">No education records added yet.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Level</th>
                                                <th>School Name</th>
                                                <th>Course</th>
                                                <th>Years</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($education as $edu): ?>
                                            <tr>
                                                <td><?= escapeHtml($edu['level']) ?></td>
                                                <td><?= escapeHtml($edu['school_name']) ?></td>
                                                <td><?= escapeHtml($edu['course'] ?? 'N/A') ?></td>
                                                <td><?= escapeHtml($edu['year_started'] ?? '') ?> - <?= escapeHtml($edu['year_ended'] ?? 'Present') ?></td>
                                                <td>
                                                    <?php if ($edu['is_graduated']): ?>
                                                        <span class="badge bg-success">Graduated</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Not Graduated</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEducation(<?= $edu['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-sm btn-success mt-3" data-bs-toggle="collapse" data-bs-target="#addEducationForm">
                                <i class="bi bi-plus-circle me-2"></i>Add Education
                            </button>

                            <div class="collapse mt-3" id="addEducationForm">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Add Education Record</h6>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">Level <span class="text-danger">*</span></label>
                                                <select class="form-control" id="edu_level" required>
                                                    <option value="">Select</option>
                                                    <option value="Elementary">Elementary</option>
                                                    <option value="High School">High School</option>
                                                    <option value="Senior High School">Senior High School</option>
                                                    <option value="Vocational">Vocational</option>
                                                    <option value="College">College</option>
                                                    <option value="Post Graduate">Post Graduate</option>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">School Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="edu_school_name" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Course</label>
                                                <input type="text" class="form-control" id="edu_course">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Year Started</label>
                                                <input type="number" class="form-control" id="edu_year_started" min="1950" max="2100">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Year Ended</label>
                                                <input type="number" class="form-control" id="edu_year_ended" min="1950" max="2100">
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="edu_is_graduated">
                                                    <label class="form-check-label" for="edu_is_graduated">
                                                        Graduated
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-success" onclick="saveEducation()">Save Education</button>
                                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addEducationForm">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Work History Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-briefcase me-2"></i>Work History</h5>
                        </div>
                        <div class="card-body">
                            <div id="workHistoryList">
                                <?php
                                $work_query = $conn->prepare("SELECT * FROM employee_work_history WHERE employee_id = ? AND is_deleted = 0 ORDER BY start_date DESC");
                                $work_query->bind_param("i", $employee_id);
                                $work_query->execute();
                                $work_history = $work_query->get_result()->fetch_all(MYSQLI_ASSOC);

                                if (empty($work_history)):
                                ?>
                                <p class="text-muted">No work history added yet.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Company</th>
                                                <th>Position</th>
                                                <th>Period</th>
                                                <th>Responsibilities</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($work_history as $work): ?>
                                            <tr>
                                                <td><?= escapeHtml($work['company_name']) ?></td>
                                                <td><?= escapeHtml($work['position']) ?></td>
                                                <td><?= escapeHtml($work['start_date']) ?> to <?= escapeHtml($work['end_date'] ?? 'Present') ?></td>
                                                <td><?= escapeHtml(substr($work['responsibilities'] ?? '', 0, 50)) ?><?= strlen($work['responsibilities'] ?? '') > 50 ? '...' : '' ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteWorkHistory(<?= $work['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-sm btn-info mt-3" data-bs-toggle="collapse" data-bs-target="#addWorkHistoryForm">
                                <i class="bi bi-plus-circle me-2"></i>Add Work History
                            </button>

                            <div class="collapse mt-3" id="addWorkHistoryForm">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Add Work History</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="work_company_name" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Position <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="work_position" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="work_start_date" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="work_end_date">
                                                <small class="text-muted">Leave blank if current</small>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Responsibilities</label>
                                                <textarea class="form-control" id="work_responsibilities" rows="3"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-info" onclick="saveWorkHistory()">Save Work History</button>
                                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addWorkHistoryForm">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <p class="text-muted text-center py-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Please save the employee record first to add dependents, education, and work history.
                    </p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>
                    <?= $action === 'add' ? 'Add Employee' : 'Update Employee' ?>
                </button>
                <a href="index.php?page=employees" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
<?php if ($action === 'add' || $action === 'edit'): ?>

// ==================== PSGC ADDRESS - CURRENT ====================

const psgcCurrent = new PSGCAddress({
    prefix: 'current_',
    apiUrl: '<?= BASE_URL ?>/api/psgc-api.php'
});

<?php if ($action === 'edit' && $employee): ?>
document.addEventListener('DOMContentLoaded', function() {
    psgcCurrent.setValuesByName({
        region: '<?= escapeHtml($employee['current_province'] ?? '') ?>',  // Note: We may not have region stored
        province: '<?= escapeHtml($employee['current_province'] ?? '') ?>',
        city: '<?= escapeHtml($employee['current_city'] ?? '') ?>',
        barangay: '<?= escapeHtml($employee['current_barangay'] ?? '') ?>'
    });
});
<?php endif; ?>

// ==================== PSGC ADDRESS - PERMANENT ====================

const psgcPermanent = new PSGCAddress({
    prefix: 'permanent_',
    apiUrl: '<?= BASE_URL ?>/api/psgc-api.php'
});

<?php if ($action === 'edit' && $employee): ?>
document.addEventListener('DOMContentLoaded', function() {
    psgcPermanent.setValuesByName({
        region: '<?= escapeHtml($employee['permanent_province'] ?? '') ?>',  // Note: We may not have region stored
        province: '<?= escapeHtml($employee['permanent_province'] ?? '') ?>',
        city: '<?= escapeHtml($employee['permanent_city'] ?? '') ?>',
        barangay: '<?= escapeHtml($employee['permanent_barangay'] ?? '') ?>'
    });
});
<?php endif; ?>

// ==================== BIRTHPLACE AUTOCOMPLETE ====================

// Birthplace autocomplete using PSGC search with custom dropdown
let birthplaceTimeout;
const birthplaceInput = document.getElementById('birthplace');
const birthplaceDropdown = document.getElementById('birthplace-dropdown');

if (birthplaceInput && birthplaceDropdown) {
    birthplaceInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();

        if (searchTerm.length < 3) {
            birthplaceDropdown.style.display = 'none';
            birthplaceDropdown.innerHTML = '';
            return;
        }

        // Debounce the API call
        clearTimeout(birthplaceTimeout);
        birthplaceTimeout = setTimeout(() => {
            fetch(`<?= BASE_URL ?>/api/psgc-api.php?action=search&query=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        // Filter to only show cities/municipalities
                        const cities = data.data.filter(item =>
                            item.type === 'city' || item.type === 'municipality'
                        ).slice(0, 10);

                        if (cities.length > 0) {
                            birthplaceDropdown.innerHTML = cities
                                .map(city => `<div class="dropdown-item" data-value="${city.name}">${city.name}</div>`)
                                .join('');
                            birthplaceDropdown.style.display = 'block';
                        } else {
                            birthplaceDropdown.innerHTML = '<div class="dropdown-item" style="color: #6c757d;">No cities found</div>';
                            birthplaceDropdown.style.display = 'block';
                        }
                    } else {
                        birthplaceDropdown.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Birthplace search error:', error);
                    birthplaceDropdown.style.display = 'none';
                });
        }, 300); // Wait 300ms after user stops typing
    });

    // Handle dropdown item click
    birthplaceDropdown.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item') && e.target.dataset.value) {
            birthplaceInput.value = e.target.dataset.value;
            birthplaceDropdown.style.display = 'none';
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== birthplaceInput && e.target !== birthplaceDropdown) {
            birthplaceDropdown.style.display = 'none';
        }
    });
}

// ==================== AUTO-FORMAT GOVERNMENT IDs ====================

// SSS Number: XX-XXXXXXX-X
document.getElementById('sss_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substr(0, 10);

    if (value.length >= 2) {
        value = value.substr(0, 2) + '-' + value.substr(2);
    }
    if (value.length >= 11) {
        value = value.substr(0, 10) + '-' + value.substr(10);
    }

    e.target.value = value;
});

// PhilHealth Number: XX-XXXXXXXXX-X
document.getElementById('philhealth_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 12) value = value.substr(0, 12);

    if (value.length >= 2) {
        value = value.substr(0, 2) + '-' + value.substr(2);
    }
    if (value.length >= 13) {
        value = value.substr(0, 12) + '-' + value.substr(12);
    }

    e.target.value = value;
});

// Pag-IBIG Number: XXXX-XXXX-XXXX
document.getElementById('pagibig_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 12) value = value.substr(0, 12);

    if (value.length >= 4) {
        value = value.substr(0, 4) + '-' + value.substr(4);
    }
    if (value.length >= 9) {
        value = value.substr(0, 9) + '-' + value.substr(9);
    }

    e.target.value = value;
});

// TIN Number: XXX-XXX-XXX-XXX
document.getElementById('tin_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 12) value = value.substr(0, 12);

    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 3 === 0) formatted += '-';
        formatted += value[i];
    }

    e.target.value = formatted;
});

// ==================== AUTO-FORMAT CONTACT NUMBER ====================

document.getElementById('contact_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');

    // Handle +63 format
    if (value.startsWith('63')) {
        if (value.length > 12) value = value.substr(0, 12);
        e.target.value = '+' + value;
    }
    // Handle 09 format
    else if (value.startsWith('09')) {
        if (value.length > 11) value = value.substr(0, 11);
        e.target.value = value;
    }
    else {
        e.target.value = value;
    }
});

// ==================== FORM VALIDATION ====================

// Enable Bootstrap validation
(function() {
    'use strict';
    const form = document.getElementById('employeeForm');

    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
})();

// ==================== AUTO-GENERATE EMPLOYEE NUMBER ====================

<?php if ($action === 'add'): ?>
// Generate employee number if AUTO
document.getElementById('employeeForm')?.addEventListener('submit', function(e) {
    const empNumberField = document.getElementById('employee_number');
    if (empNumberField && empNumberField.value.toUpperCase() === 'AUTO') {
        // Generate format: EMP-YYYYMMDD-XXX (where XXX is random)
        const now = new Date();
        const dateStr = now.getFullYear() +
                       String(now.getMonth() + 1).padStart(2, '0') +
                       String(now.getDate()).padStart(2, '0');
        const random = String(Math.floor(Math.random() * 1000)).padStart(3, '0');
        empNumberField.value = `EMP-${dateStr}-${random}`;
    }
});
<?php endif; ?>

// ==================== SAME AS CURRENT ADDRESS ====================

document.getElementById('sameAsCurrentAddress')?.addEventListener('change', function() {
    if (this.checked) {
        // Copy text fields
        document.getElementById('permanent_building_name').value = document.getElementById('current_building_name').value;
        document.getElementById('permanent_unit_number').value = document.getElementById('current_unit_number').value;
        document.getElementById('permanent_house_number').value = document.getElementById('current_house_number').value;
        document.getElementById('permanent_street_name').value = document.getElementById('current_street_name').value;
        document.getElementById('permanent_postal_code').value = document.getElementById('current_postal_code').value;

        // Copy PSGC selections
        const currentRegion = document.getElementById('current_region_name').value;
        const currentProvince = document.getElementById('current_province_name').value;
        const currentCity = document.getElementById('current_city_name').value;
        const currentBarangay = document.getElementById('current_barangay_name').value;

        if (currentProvince && currentCity && currentBarangay) {
            psgcPermanent.setValuesByName({
                region: currentProvince,  // Using province as region fallback
                province: currentProvince,
                city: currentCity,
                barangay: currentBarangay
            });
        }
    }
});

// ==================== BACKGROUND DATA MANAGEMENT ====================

<?php if ($action === 'edit' && $employee_id): ?>

const employeeId = <?= $employee_id ?>;

// Save Dependent
function saveDependent() {
    const data = {
        action: 'add_dependent',
        employee_id: employeeId,
        first_name: document.getElementById('dep_first_name').value,
        middle_name: document.getElementById('dep_middle_name').value,
        last_name: document.getElementById('dep_last_name').value,
        relationship: document.getElementById('dep_relationship').value,
        birthdate: document.getElementById('dep_birthdate').value,
        is_beneficiary: document.getElementById('dep_is_beneficiary').checked ? 1 : 0
    };

    if (!data.first_name || !data.last_name || !data.relationship || !data.birthdate) {
        alert('Please fill in all required fields');
        return;
    }

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Dependent added successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Delete Dependent
function deleteDependent(id) {
    if (!confirm('Are you sure you want to delete this dependent?')) return;

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_dependent', id: id})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Dependent deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Save Education
function saveEducation() {
    const data = {
        action: 'add_education',
        employee_id: employeeId,
        level: document.getElementById('edu_level').value,
        school_name: document.getElementById('edu_school_name').value,
        course: document.getElementById('edu_course').value,
        year_started: document.getElementById('edu_year_started').value,
        year_ended: document.getElementById('edu_year_ended').value,
        is_graduated: document.getElementById('edu_is_graduated').checked ? 1 : 0
    };

    if (!data.level || !data.school_name) {
        alert('Please fill in all required fields');
        return;
    }

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Education record added successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Delete Education
function deleteEducation(id) {
    if (!confirm('Are you sure you want to delete this education record?')) return;

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_education', id: id})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Education record deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Save Work History
function saveWorkHistory() {
    const data = {
        action: 'add_work_history',
        employee_id: employeeId,
        company_name: document.getElementById('work_company_name').value,
        position: document.getElementById('work_position').value,
        start_date: document.getElementById('work_start_date').value,
        end_date: document.getElementById('work_end_date').value,
        responsibilities: document.getElementById('work_responsibilities').value
    };

    if (!data.company_name || !data.position || !data.start_date) {
        alert('Please fill in all required fields');
        return;
    }

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Work history added successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Delete Work History
function deleteWorkHistory(id) {
    if (!confirm('Are you sure you want to delete this work history?')) return;

    fetch('<?= BASE_URL ?>/modules/employees/employee_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_work_history', id: id})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Work history deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

<?php endif; ?>

<?php endif; ?>
</script>

<?php endif; ?>
