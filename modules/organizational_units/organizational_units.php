<?php
/**
 * Organizational Units Management Module
 * 
 * Handles CRUD operations for organizational units (branches, departments, warehouses, etc.)
 * - List all organizational units
 * - Add new organizational unit
 * - Edit existing organizational unit
 * - View organizational unit details
 * - Delete organizational unit (soft delete)
 * 
 * @package HRIS
 * @author HRIS Development Team
 * @version 2.0
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

// Get action and unit ID
$action = $_GET['action'] ?? 'list';
$unit_id = $_GET['id'] ?? null;

// Initialize variables
$errors = [];
$unit = null;
$units = [];

// ==================== POST PROCESSING ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=organizational_units");
        exit;
    }
    
    $post_action = $_POST['action'] ?? '';
    
    // Sanitize all input data
    $data = sanitizeInput($_POST);
    
    // ==================== ADD ORGANIZATIONAL UNIT ====================
    if ($post_action === 'add') {
        // Validate required fields
        if (empty($data['company_id'])) {
            $errors[] = "Company is required";
        }
        if (empty($data['unit_code'])) {
            $errors[] = "Unit code is required";
        }
        if (empty($data['unit_name'])) {
            $errors[] = "Unit name is required";
        }
        if (empty($data['unit_type'])) {
            $errors[] = "Unit type is required";
        }
        if (empty($data['region'])) {
            $errors[] = "Region is required";
        }
        if (empty($data['province'])) {
            $errors[] = "Province is required";
        }
        if (empty($data['city'])) {
            $errors[] = "City is required";
        }
        if (empty($data['barangay'])) {
            $errors[] = "Barangay is required";
        }
        
        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check for duplicate unit code
        if (empty($errors)) {
            $check_query = "SELECT id FROM organizational_units WHERE unit_code = ? AND is_deleted = 0";
            $check_result = executeQuery($check_query, "s", [$data['unit_code']]);
            
            if ($check_result && $check_result->num_rows > 0) {
                $errors[] = "Unit code already exists";
            }
        }
        
        if (empty($errors)) {
            $query = "INSERT INTO organizational_units (
                company_id, unit_code, unit_name, unit_type, description,
                manager_employee_id, building_name, mall_type, unit_number,
                house_number, street_name, barangay, city, province, region,
                postal_code, latitude, longitude, contact_number, email,
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['company_id'],
                $data['unit_code'],
                $data['unit_name'],
                $data['unit_type'],
                $data['description'] ?? null,
                !empty($data['manager_employee_id']) ? $data['manager_employee_id'] : null,
                $data['building_name'] ?? null,
                $data['mall_type'] ?? 'Not Applicable',
                $data['unit_number'] ?? null,
                $data['house_number'] ?? null,
                $data['street_name'] ?? null,
                $data['barangay'],
                $data['city'],
                $data['province'],
                $data['region'],
                $data['postal_code'] ?? null,
                !empty($data['latitude']) ? $data['latitude'] : null,
                !empty($data['longitude']) ? $data['longitude'] : null,
                $data['contact_number'] ?? null,
                $data['email'] ?? null,
                $_SESSION['user_id']
            ];
            
            $types = "issssisssssssssddsssi";
            $result = executeQuery($query, $types, $params);
            
            if ($result) {
                $insert_id = $result;
                
                // Log activity
                logActivity(
                    $_SESSION['user_id'],
                    'CREATE',
                    'organizational_units',
                    $insert_id,
                    null,
                    $data,
                    "Added new organizational unit: {$data['unit_name']} ({$data['unit_code']})"
                );
                
                $_SESSION['success'] = "Organizational unit added successfully!";
                header("Location: index.php?page=organizational_units");
                exit;
            } else {
                $errors[] = "Failed to add organizational unit. Please try again.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            $_SESSION['form_data'] = $data; // Preserve form data
            header("Location: index.php?page=organizational_units&action=add");
            exit;
        }
    }
    
// ==================== EDIT ORGANIZATIONAL UNIT ====================
elseif ($post_action === 'edit') {
    $unit_id = $data['unit_id'] ?? null;
    
    // Validate required fields
    if (empty($unit_id)) {
        $errors[] = "Invalid unit ID";
    }
    if (empty($data['company_id'])) {
        $errors[] = "Company is required";
    }
    if (empty($data['unit_code'])) {
        $errors[] = "Unit code is required";
    }
    if (empty($data['unit_name'])) {
        $errors[] = "Unit name is required";
    }
    if (empty($data['unit_type'])) {
        $errors[] = "Unit type is required";
    }
    if (empty($data['region'])) {
        $errors[] = "Region is required";
    }
    if (empty($data['province'])) {
        $errors[] = "Province is required";
    }
    if (empty($data['city'])) {
        $errors[] = "City is required";
    }
    if (empty($data['barangay'])) {
        $errors[] = "Barangay is required";
    }
    
    // Validate email format if provided
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check for duplicate unit code (excluding current record)
    if (empty($errors)) {
        $check_query = "SELECT id FROM organizational_units WHERE unit_code = ? AND id != ? AND is_deleted = 0";
        $check_result = executeQuery($check_query, "si", [$data['unit_code'], $unit_id]);
        
        if ($check_result && $check_result->num_rows > 0) {
            $errors[] = "Unit code already exists";
        }
    }
    
    // Get old values for activity log
    $old_values = null;
    if (empty($errors)) {
        $old_query = "SELECT * FROM organizational_units WHERE id = ? AND is_deleted = 0";
        $old_result = executeQuery($old_query, "i", [$unit_id]);
        if ($old_result && $old_result->num_rows > 0) {
            $old_values = $old_result->fetch_assoc();
        } else {
            $errors[] = "Organizational unit not found";
        }
    }
    
    if (empty($errors)) {
        $query = "UPDATE organizational_units SET
            company_id = ?, 
            unit_code = ?, 
            unit_name = ?, 
            unit_type = ?,
            description = ?, 
            manager_employee_id = ?, 
            building_name = ?,
            mall_type = ?, 
            unit_number = ?, 
            house_number = ?, 
            street_name = ?,
            barangay = ?, 
            city = ?, 
            province = ?, 
            region = ?, 
            postal_code = ?,
            latitude = ?, 
            longitude = ?, 
            contact_number = ?, 
            email = ?,
            updated_by = ?, 
            updated_at = NOW()
            WHERE id = ?";
        
        $params = [
            $data['company_id'],
            $data['unit_code'],
            $data['unit_name'],
            $data['unit_type'],
            $data['description'] ?? null,
            !empty($data['manager_employee_id']) ? $data['manager_employee_id'] : null,
            $data['building_name'] ?? null,
            $data['mall_type'] ?? 'Not Applicable',
            $data['unit_number'] ?? null,
            $data['house_number'] ?? null,
            $data['street_name'] ?? null,
            $data['barangay'],
            $data['city'],
            $data['province'],
            $data['region'],
            $data['postal_code'] ?? null,
            !empty($data['latitude']) ? $data['latitude'] : null,
            !empty($data['longitude']) ? $data['longitude'] : null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $_SESSION['user_id'],
            $unit_id
        ];
        
        $types = "issssisssssssssddsissi";
        $result = executeQuery($query, $types, $params);
        
        if ($result !== false) {
            // Log activity
            logActivity(
                $_SESSION['user_id'],
                'UPDATE',
                'organizational_units',
                $unit_id,
                $old_values,
                $data,
                "Updated organizational unit: {$data['unit_name']} ({$data['unit_code']})"
            );
            
            $_SESSION['success'] = "Organizational unit updated successfully!";
            header("Location: index.php?page=organizational_units");
            exit;
        } else {
            $errors[] = "Failed to update organizational unit. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $data; // Preserve form data
        header("Location: index.php?page=organizational_units&action=edit&id=" . $unit_id);
        exit;
    }
}
    
    // ==================== DELETE ORGANIZATIONAL UNIT ====================
    elseif ($post_action === 'delete') {
        $unit_id = $data['unit_id'] ?? null;
        
        if (empty($unit_id)) {
            $_SESSION['error'] = "Invalid unit ID";
            header("Location: index.php?page=organizational_units");
            exit;
        }
        
        // Get unit info before deleting for activity log
        $unit_query = "SELECT * FROM organizational_units WHERE id = ? AND is_deleted = 0";
        $unit_result = executeQuery($unit_query, "i", [$unit_id]);
        
        if ($unit_result && $unit_result->num_rows > 0) {
            $unit_data = $unit_result->fetch_assoc();
            
            $delete_query = "UPDATE organizational_units SET 
                is_deleted = 1, 
                deleted_at = NOW(), 
                deleted_by = ? 
                WHERE id = ?";
            
            $result = executeQuery($delete_query, "ii", [$_SESSION['user_id'], $unit_id]);
            
            if ($result !== false) {
                // Log activity
                logActivity(
                    $_SESSION['user_id'],
                    'DELETE',
                    'organizational_units',
                    $unit_id,
                    $unit_data,
                    null,
                    "Deleted organizational unit: {$unit_data['unit_name']} ({$unit_data['unit_code']})"
                );
                
                $_SESSION['success'] = "Organizational unit deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete organizational unit. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Organizational unit not found";
        }
        
        header("Location: index.php?page=organizational_units");
        exit;
    }
}

// ==================== FETCH DATA ====================

// Fetch single unit for edit/view
if (($action === 'edit' || $action === 'view') && $unit_id) {
    $query = "SELECT ou.*, c.name as company_name,
              CONCAT(e.first_name, ' ', e.last_name) as manager_name,
              e.employee_number as manager_employee_number
              FROM organizational_units ou
              LEFT JOIN companies c ON ou.company_id = c.id
              LEFT JOIN employees e ON ou.manager_employee_id = e.id
              WHERE ou.id = ? AND ou.is_deleted = 0";
    
    $result = executeQuery($query, "i", [$unit_id]);
    
    if ($result && $result->num_rows > 0) {
        $unit = $result->fetch_assoc();
        
        // Log view activity
        if ($action === 'view') {
            logActivity(
                $_SESSION['user_id'],
                'VIEW',
                'organizational_units',
                $unit_id,
                null,
                null,
                "Viewed organizational unit: {$unit['unit_name']} ({$unit['unit_code']})"
            );
        }
    } else {
        $_SESSION['error'] = "Organizational unit not found!";
        header("Location: index.php?page=organizational_units");
        exit;
    }
}

// Fetch all units for list
if ($action === 'list') {
    $query = "SELECT ou.*, c.name as company_name,
              CONCAT(e.first_name, ' ', e.last_name) as manager_name
              FROM organizational_units ou
              LEFT JOIN companies c ON ou.company_id = c.id
              LEFT JOIN employees e ON ou.manager_employee_id = e.id
              WHERE ou.is_deleted = 0
              ORDER BY c.name ASC, ou.unit_name ASC";
    
    $result = executeQuery($query);
    
    if ($result) {
        $units = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch active companies for dropdown
$companies = [];
$comp_query = "SELECT id, name FROM companies WHERE is_deleted = 0 AND is_active = 1 ORDER BY name ASC";
$comp_result = executeQuery($comp_query);
if ($comp_result) {
    $companies = $comp_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch active employees for manager dropdown
$employees = [];
$emp_query = "SELECT e.id, e.first_name, e.last_name, e.employee_number, p.position_title
              FROM employees e
              LEFT JOIN positions p ON e.position_id = p.id
              WHERE e.is_deleted = 0 AND e.is_active = 1
              ORDER BY e.last_name ASC, e.first_name ASC";
$emp_result = executeQuery($emp_query);
if ($emp_result) {
    $employees = $emp_result->fetch_all(MYSQLI_ASSOC);
}

// Get preserved form data if validation failed
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

?>

<!-- ==================== LIST VIEW ==================== -->
<?php if ($action === 'list'): ?>
    <div class="page-header">
        <h1 class="page-title">Organizational Units</h1>
        <p class="page-subtitle">Manage branches, departments, warehouses, and regional offices</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0">All Organizational Units</h2>
            <a href="index.php?page=organizational_units&action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add New Unit
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Unit Code</th>
                            <th>Unit Name</th>
                            <th>Type</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($units)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-building" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="mt-2 text-muted">No organizational units found. Add your first unit to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($units as $u): ?>
                                <tr>
                                    <td><?= escapeHtml($u['unit_code']) ?></td>
                                    <td><strong><?= escapeHtml($u['unit_name']) ?></strong></td>
                                    <td>
                                        <?php
                                        $type_colors = [
                                            'Head Office' => 'primary',
                                            'Branch' => 'success',
                                            'Warehouse' => 'warning',
                                            'Regional Office' => 'info',
                                            'Department' => 'secondary'
                                        ];
                                        $color = $type_colors[$u['unit_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= escapeHtml($u['unit_type']) ?></span>
                                    </td>
                                    <td><?= escapeHtml($u['company_name']) ?></td>
                                    <td>
                                        <?= escapeHtml($u['city'] ?? '') ?>
                                        <?= !empty($u['province']) ? ', ' . escapeHtml($u['province']) : '' ?>
                                    </td>
                                    <td><?= escapeHtml($u['manager_name'] ?? 'Not Assigned') ?></td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="index.php?page=organizational_units&action=view&id=<?= $u['id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index.php?page=organizational_units&action=edit&id=<?= $u['id'] ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                title="Delete"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-unit-id="<?= $u['id'] ?>"
                                                data-unit-name="<?= escapeHtml($u['unit_name']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="unitNameToDelete"></strong>?</p>
                    <p class="text-muted mb-0">This action will soft delete the organizational unit. It can be recovered by an administrator.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="index.php?page=organizational_units" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="unit_id" id="unitIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Unit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Delete modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const unitId = button.getAttribute('data-unit-id');
                    const unitName = button.getAttribute('data-unit-name');
                    
                    document.getElementById('unitIdToDelete').value = unitId;
                    document.getElementById('unitNameToDelete').textContent = unitName;
                });
            }
        });
    </script>

<!-- ==================== ADD/EDIT FORM VIEW ==================== -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="page-header">
        <h1 class="page-title"><?= $action === 'add' ? 'Add New Organizational Unit' : 'Edit Organizational Unit' ?></h1>
        <p class="page-subtitle">Fill in the organizational unit information below</p>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="index.php?page=organizational_units" id="unitForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="<?= $action ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="unit_id" value="<?= $unit['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-12">
                        <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select name="company_id" class="form-control" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>" 
                                    <?= ($action === 'edit' && $unit['company_id'] == $company['id']) ? 'selected' : '' ?>
                                    <?= (!empty($form_data['company_id']) && $form_data['company_id'] == $company['id']) ? 'selected' : '' ?>>
                                    <?= escapeHtml($company['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit Type <span class="text-danger">*</span></label>
                        <select name="unit_type" class="form-control" required id="unitType">
                            <option value="">Select Unit Type</option>
                            <option value="Head Office" <?= (($action === 'edit' && $unit['unit_type'] == 'Head Office') || (!empty($form_data['unit_type']) && $form_data['unit_type'] == 'Head Office')) ? 'selected' : '' ?>>Head Office</option>
                            <option value="Branch" <?= (($action === 'edit' && $unit['unit_type'] == 'Branch') || (!empty($form_data['unit_type']) && $form_data['unit_type'] == 'Branch')) ? 'selected' : '' ?>>Branch</option>
                            <option value="Warehouse" <?= (($action === 'edit' && $unit['unit_type'] == 'Warehouse') || (!empty($form_data['unit_type']) && $form_data['unit_type'] == 'Warehouse')) ? 'selected' : '' ?>>Warehouse</option>
                            <option value="Regional Office" <?= (($action === 'edit' && $unit['unit_type'] == 'Regional Office') || (!empty($form_data['unit_type']) && $form_data['unit_type'] == 'Regional Office')) ? 'selected' : '' ?>>Regional Office</option>
                            <option value="Department" <?= (($action === 'edit' && $unit['unit_type'] == 'Department') || (!empty($form_data['unit_type']) && $form_data['unit_type'] == 'Department')) ? 'selected' : '' ?>>Department</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit Code <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="unit_code" 
                               class="form-control" 
                               required 
                               maxlength="20"
                               value="<?= escapeHtml($form_data['unit_code'] ?? $unit['unit_code'] ?? '') ?>"
                               placeholder="e.g., HO-001, BR-QC-001">
                        <small class="form-text text-muted">Unique identifier for the unit</small>
                    </div>

                    <div class="col-md-8 mb-3">
                        <label class="form-label">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="unit_name" 
                               class="form-control" 
                               required 
                               maxlength="100"
                               value="<?= escapeHtml($form_data['unit_name'] ?? $unit['unit_name'] ?? '') ?>"
                               placeholder="e.g., Quezon City Main Branch">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit Manager/Head</label>
                        <select name="manager_employee_id" class="form-control">
                            <option value="">Not Assigned</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['id'] ?>" 
                                    <?= ($action === 'edit' && $unit['manager_employee_id'] == $employee['id']) ? 'selected' : '' ?>
                                    <?= (!empty($form_data['manager_employee_id']) && $form_data['manager_employee_id'] == $employee['id']) ? 'selected' : '' ?>>
                                    <?= escapeHtml($employee['last_name'] . ', ' . $employee['first_name']) ?>
                                    <?= !empty($employee['position_title']) ? ' - ' . escapeHtml($employee['position_title']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" 
                                  class="form-control" 
                                  rows="2"
                                  placeholder="Brief description of this unit"><?= escapeHtml($form_data['description'] ?? $unit['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Address Information -->
                    <div class="col-md-12 mt-3">
                        <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Address Information</h5>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Building/Mall Name</label>
                        <input type="text" 
                               name="building_name" 
                               class="form-control" 
                               maxlength="200"
                               value="<?= escapeHtml($form_data['building_name'] ?? $unit['building_name'] ?? '') ?>"
                               placeholder="e.g., SM City Fairview, Robinsons Galleria">
                    </div>

                    <div class="col-md-3 mb-3" id="mallTypeField" style="display: none;">
                        <label class="form-label">Mall Type</label>
                        <select name="mall_type" class="form-control">
                            <option value="Not Applicable" <?= (($action === 'edit' && ($unit['mall_type'] ?? '') == 'Not Applicable') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Not Applicable')) ? 'selected' : '' ?>>Not Applicable</option>
                            <option value="SM" <?= (($action === 'edit' && $unit['mall_type'] == 'SM') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'SM')) ? 'selected' : '' ?>>SM</option>
                            <option value="Ayala Malls" <?= (($action === 'edit' && $unit['mall_type'] == 'Ayala Malls') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Ayala Malls')) ? 'selected' : '' ?>>Ayala Malls</option>
                            <option value="Robinsons" <?= (($action === 'edit' && $unit['mall_type'] == 'Robinsons') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Robinsons')) ? 'selected' : '' ?>>Robinsons</option>
                            <option value="Puregold" <?= (($action === 'edit' && $unit['mall_type'] == 'Puregold') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Puregold')) ? 'selected' : '' ?>>Puregold</option>
                            <option value="Starmalls" <?= (($action === 'edit' && $unit['mall_type'] == 'Starmalls') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Starmalls')) ? 'selected' : '' ?>>Starmalls</option>
                            <option value="Waltermart" <?= (($action === 'edit' && $unit['mall_type'] == 'Waltermart') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Waltermart')) ? 'selected' : '' ?>>Waltermart</option>
                            <option value="Vista Mall" <?= (($action === 'edit' && $unit['mall_type'] == 'Vista Mall') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Vista Mall')) ? 'selected' : '' ?>>Vista Mall</option>
                            <option value="Other" <?= (($action === 'edit' && $unit['mall_type'] == 'Other') || (!empty($form_data['mall_type']) && $form_data['mall_type'] == 'Other')) ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Unit/Store Number</label>
                        <input type="text" 
                               name="unit_number" 
                               class="form-control" 
                               maxlength="50"
                               value="<?= escapeHtml($form_data['unit_number'] ?? $unit['unit_number'] ?? '') ?>"
                               placeholder="e.g., 2F-123, G/F-45">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">House Number</label>
                        <input type="text" 
                               name="house_number" 
                               class="form-control" 
                               maxlength="50"
                               value="<?= escapeHtml($form_data['house_number'] ?? $unit['house_number'] ?? '') ?>"
                               placeholder="e.g., 123">
                    </div>

                    <div class="col-md-9 mb-3">
                        <label class="form-label">Street Name</label>
                        <input type="text" 
                               name="street_name" 
                               class="form-control" 
                               maxlength="150"
                               value="<?= escapeHtml($form_data['street_name'] ?? $unit['street_name'] ?? '') ?>"
                               placeholder="e.g., Commonwealth Avenue">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Region <span class="text-danger">*</span></label>
                        <select id="region" class="form-control" required>
                            <option value="">Select Region</option>
                        </select>
                        <input type="hidden" name="region" id="region_name" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Province <span class="text-danger">*</span></label>
                        <select id="province" class="form-control" required disabled>
                            <option value="">Select Province</option>
                        </select>
                        <input type="hidden" name="province" id="province_name" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">City/Municipality <span class="text-danger">*</span></label>
                        <select id="city" class="form-control" required disabled>
                            <option value="">Select City/Municipality</option>
                        </select>
                        <input type="hidden" name="city" id="city_name" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Barangay <span class="text-danger">*</span></label>
                        <select id="barangay" class="form-control" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                        <input type="hidden" name="barangay" id="barangay_name" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" 
                               name="postal_code" 
                               class="form-control" 
                               maxlength="10"
                               pattern="[0-9]{4}"
                               value="<?= escapeHtml($form_data['postal_code'] ?? $unit['postal_code'] ?? '') ?>"
                               placeholder="e.g., 1100">
                        <small class="form-text text-muted">4-digit postal code</small>
                    </div>

                    <!-- GPS Coordinates & Contact -->
                    <div class="col-md-12 mt-3">
                        <h5 class="mb-3"><i class="bi bi-map me-2"></i>GPS Coordinates & Contact Information</h5>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" 
                               name="latitude" 
                               id="latitude" 
                               class="form-control" 
                               value="<?= escapeHtml($form_data['latitude'] ?? $unit['latitude'] ?? '') ?>"
                               placeholder="e.g., 14.64627663"
                               step="any">
                        <small class="form-text text-muted">Decimal degrees</small>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" 
                               name="longitude" 
                               id="longitude" 
                               class="form-control" 
                               value="<?= escapeHtml($form_data['longitude'] ?? $unit['longitude'] ?? '') ?>"
                               placeholder="e.g., 121.01638316"
                               step="any">
                        <small class="form-text text-muted">Decimal degrees</small>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" 
                               name="contact_number" 
                               class="form-control" 
                               maxlength="20"
                               value="<?= escapeHtml($form_data['contact_number'] ?? $unit['contact_number'] ?? '') ?>"
                               placeholder="e.g., +63 2 1234 5678">
                        <small class="form-text text-muted">Numbers, spaces, and +()-</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               maxlength="100"
                               value="<?= escapeHtml($form_data['email'] ?? $unit['email'] ?? '') ?>"
                               placeholder="e.g., branch@company.com">
                    </div>

                    <!-- Map Preview -->
                    <div class="col-md-12 mb-3">
                        <div id="mapPreview" style="display: none;">
                            <label class="form-label"><i class="bi bi-map-fill me-2"></i>Location Preview</label>
                            <div class="card">
                                <div class="card-body p-0">
                                    <iframe id="mapFrame" width="100%" height="350" frameborder="0" style="border:0" allowfullscreen></iframe>
                                </div>
                                <div class="card-footer">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Preview updates automatically when you enter coordinates
                                    </small>
                                    <a href="#" id="openInGoogleMaps" target="_blank" class="btn btn-sm btn-outline-primary float-end">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open in Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i><?= $action === 'add' ? 'Add Organizational Unit' : 'Update Organizational Unit' ?>
                    </button>
                    <a href="index.php?page=organizational_units" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ==================== PSGC ADDRESS ====================
        
        console.log('Initializing PSGC Address...');
        
        const psgcAddress = new PSGCAddress({
            prefix: '',
            apiUrl: '<?= BASE_URL ?>/api/psgc-api.php'
        });

        <?php if ($action === 'edit' && $unit): ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Setting values for edit mode...');
            psgcAddress.setValuesByName({
                region: '<?= escapeHtml($unit['region'] ?? '') ?>',
                province: '<?= escapeHtml($unit['province'] ?? '') ?>',
                city: '<?= escapeHtml($unit['city'] ?? '') ?>',
                barangay: '<?= escapeHtml($unit['barangay'] ?? '') ?>'
            });
        });
        <?php endif; ?>

        // ==================== SHOW/HIDE MALL TYPE FIELD ====================
        
        document.addEventListener('DOMContentLoaded', function() {
            const unitTypeSelect = document.getElementById('unitType');
            const mallTypeField = document.getElementById('mallTypeField');
            
            function toggleMallTypeField() {
                if (unitTypeSelect.value === 'Branch') {
                    mallTypeField.style.display = 'block';
                } else {
                    mallTypeField.style.display = 'none';
                }
            }
            
            unitTypeSelect.addEventListener('change', toggleMallTypeField);
            toggleMallTypeField();
        });

        // ==================== MAP PREVIEW FUNCTIONALITY ====================
        
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const mapPreview = document.getElementById('mapPreview');
        const mapFrame = document.getElementById('mapFrame');
        const openInGoogleMaps = document.getElementById('openInGoogleMaps');

        function updateMapPreview() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);

            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                mapPreview.style.display = 'block';
                
                const embedUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=16`;
                mapFrame.src = embedUrl;
                
                openInGoogleMaps.href = `https://www.google.com/maps?q=${lat},${lng}`;
            } else {
                mapPreview.style.display = 'none';
            }
        }

        latInput.addEventListener('input', updateMapPreview);
        lngInput.addEventListener('input', updateMapPreview);

        // Initial map preview on page load (for edit mode)
        updateMapPreview();
    </script>

<!-- ==================== VIEW DETAIL ==================== -->
<?php elseif ($action === 'view'): ?>
    <div class="page-header">
        <h1 class="page-title">Organizational Unit Details</h1>
        <p class="page-subtitle">View complete organizational unit information</p>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0"><?= escapeHtml($unit['unit_name']) ?></h2>
            <div>
                <a href="index.php?page=organizational_units&action=edit&id=<?= $unit['id'] ?>" class="btn btn-warning">
                    <i class="bi bi-pencil me-2"></i>Edit
                </a>
                <a href="index.php?page=organizational_units" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Left Column -->
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Unit Code:</th>
                            <td><?= escapeHtml($unit['unit_code']) ?></td>
                        </tr>
                        <tr>
                            <th>Unit Name:</th>
                            <td><?= escapeHtml($unit['unit_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Unit Type:</th>
                            <td>
                                <?php
                                $type_colors = [
                                    'Head Office' => 'primary',
                                    'Branch' => 'success',
                                    'Warehouse' => 'warning',
                                    'Regional Office' => 'info',
                                    'Department' => 'secondary'
                                ];
                                $color = $type_colors[$unit['unit_type']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>"><?= escapeHtml($unit['unit_type']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Company:</th>
                            <td><?= escapeHtml($unit['company_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($unit['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Unit Manager/Head:</th>
                            <td>
                                <?php if (!empty($unit['manager_name'])): ?>
                                    <i class="bi bi-person-badge me-1"></i><?= escapeHtml($unit['manager_name']) ?>
                                    <?php if (!empty($unit['manager_employee_number'])): ?>
                                        <br><small class="text-muted"><?= escapeHtml($unit['manager_employee_number']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td><?= nl2br(escapeHtml($unit['description'] ?? 'N/A')) ?></td>
                        </tr>
                    </table>

                    <h5 class="mb-3 mt-4"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Contact Number:</th>
                            <td><?= escapeHtml($unit['contact_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?= escapeHtml($unit['email'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Address</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Building/Mall Name:</th>
                            <td><?= escapeHtml($unit['building_name'] ?? 'N/A') ?></td>
                        </tr>
                        <?php if ($unit['unit_type'] === 'Branch' && $unit['mall_type'] !== 'Not Applicable'): ?>
                        <tr>
                            <th>Mall Type:</th>
                            <td><span class="badge bg-info"><?= escapeHtml($unit['mall_type']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Unit/Store Number:</th>
                            <td><?= escapeHtml($unit['unit_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>House Number:</th>
                            <td><?= escapeHtml($unit['house_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Street:</th>
                            <td><?= escapeHtml($unit['street_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Barangay:</th>
                            <td><?= escapeHtml($unit['barangay'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>City:</th>
                            <td><?= escapeHtml($unit['city'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Province:</th>
                            <td><?= escapeHtml($unit['province'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Region:</th>
                            <td><?= escapeHtml($unit['region'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Postal Code:</th>
                            <td><?= escapeHtml($unit['postal_code'] ?? 'N/A') ?></td>
                        </tr>
                    </table>

                    <h5 class="mb-3 mt-4"><i class="bi bi-map me-2"></i>GPS Coordinates</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Latitude:</th>
                            <td><?= escapeHtml($unit['latitude'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Longitude:</th>
                            <td><?= escapeHtml($unit['longitude'] ?? 'N/A') ?></td>
                        </tr>
                        <?php if (!empty($unit['latitude']) && !empty($unit['longitude'])): ?>
                        <tr>
                            <th>Map:</th>
                            <td>
                                <a href="https://www.google.com/maps?q=<?= escapeHtml($unit['latitude']) ?>,<?= escapeHtml($unit['longitude']) ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-geo-alt-fill me-1"></i>View on Google Maps
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Map Display -->
            <?php if (!empty($unit['latitude']) && !empty($unit['longitude'])): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <h5 class="mb-3"><i class="bi bi-map-fill me-2"></i>Location Map</h5>
                    <div class="card">
                        <div class="card-body p-0">
                            <iframe 
                                width="100%" 
                                height="400" 
                                frameborder="0" 
                                style="border:0" 
                                src="https://www.google.com/maps?q=<?= escapeHtml($unit['latitude']) ?>,<?= escapeHtml($unit['longitude']) ?>&output=embed&z=16"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Audit Trail -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i>Audit Trail</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="15%">Created:</th>
                            <td><?= date('F d, Y h:i A', strtotime($unit['created_at'])) ?></td>
                            <th width="15%">Updated:</th>
                            <td><?= date('F d, Y h:i A', strtotime($unit['updated_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>