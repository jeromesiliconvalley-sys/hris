<?php
/**
 * Company Management Module
 * 
 * Handles CRUD operations for company records
 * - List all companies
 * - Add new company
 * - Edit existing company
 * - View company details
 * - Delete company (soft delete)
 * 
 * Government IDs stored as numbers only, formatted for display
 * 
 * @package HRIS
 * @author HRIS Development Team
 * @version 2.2
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

// Get action and company ID
$action = $_GET['action'] ?? 'list';
$company_id = $_GET['id'] ?? null;

// Initialize variables
$errors = [];
$company = null;
$companies = [];

// ==================== HELPER FUNCTIONS ====================

/**
 * Strip non-numeric characters from government ID
 */
function cleanGovernmentID($id) {
    return preg_replace('/[^0-9]/', '', $id);
}

/**
 * Validate government ID digit count
 */
function validateGovernmentID($id, $type) {
    $clean_id = cleanGovernmentID($id);
    
    if (empty($clean_id)) {
        return true; // Optional field
    }
    
    $required_lengths = [
        'TIN' => 12,
        'SSS' => 10,
        'PhilHealth' => 12,
        'PagIBIG' => 12
    ];
    
    $length = strlen($clean_id);
    $required = $required_lengths[$type] ?? 0;
    
    if ($required > 0 && $length !== $required) {
        return false;
    }
    
    return true;
}

/**
 * Format TIN for display: 123-456-789-000
 */
function formatTIN($tin) {
    if (empty($tin)) return 'N/A';
    
    $clean = cleanGovernmentID($tin);
    if (strlen($clean) !== 12) return $tin;
    
    return substr($clean, 0, 3) . '-' . 
           substr($clean, 3, 3) . '-' . 
           substr($clean, 6, 3) . '-' . 
           substr($clean, 9, 3);
}

/**
 * Format SSS for display: 12-3456789-0
 */
function formatSSS($sss) {
    if (empty($sss)) return 'N/A';
    
    $clean = cleanGovernmentID($sss);
    if (strlen($clean) !== 10) return $sss;
    
    return substr($clean, 0, 2) . '-' . 
           substr($clean, 2, 7) . '-' . 
           substr($clean, 9, 1);
}

/**
 * Format PhilHealth for display: 12-345678901-2
 */
function formatPhilHealth($philhealth) {
    if (empty($philhealth)) return 'N/A';
    
    $clean = cleanGovernmentID($philhealth);
    if (strlen($clean) !== 12) return $philhealth;
    
    return substr($clean, 0, 2) . '-' . 
           substr($clean, 2, 9) . '-' . 
           substr($clean, 11, 1);
}

/**
 * Format Pag-IBIG for display: 1234-5678-9012
 */
function formatPagIBIG($pagibig) {
    if (empty($pagibig)) return 'N/A';
    
    $clean = cleanGovernmentID($pagibig);
    if (strlen($clean) !== 12) return $pagibig;
    
    return substr($clean, 0, 4) . '-' . 
           substr($clean, 4, 4) . '-' . 
           substr($clean, 8, 4);
}

// ==================== POST PROCESSING ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=company");
        exit;
    }
    
    $post_action = $_POST['action'] ?? '';
    
    // Sanitize all input data
    $data = sanitizeInput($_POST);
    
    // Clean government IDs (remove dashes, spaces, etc.)
    if (!empty($data['tin'])) {
        $data['tin'] = cleanGovernmentID($data['tin']);
    }
    if (!empty($data['sss_number'])) {
        $data['sss_number'] = cleanGovernmentID($data['sss_number']);
    }
    if (!empty($data['philhealth_number'])) {
        $data['philhealth_number'] = cleanGovernmentID($data['philhealth_number']);
    }
    if (!empty($data['pagibig_number'])) {
        $data['pagibig_number'] = cleanGovernmentID($data['pagibig_number']);
    }
    
    // ==================== ADD COMPANY ====================
    if ($post_action === 'add') {
        // Validate required fields
        if (empty($data['code'])) {
            $errors[] = "Company code is required";
        }
        if (empty($data['name'])) {
            $errors[] = "Company name is required";
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
        
        // Validate government ID digit counts
        if (!empty($data['tin']) && !validateGovernmentID($data['tin'], 'TIN')) {
            $errors[] = "TIN must be exactly 12 digits (format: 123-456-789-000)";
        }
        if (!empty($data['sss_number']) && !validateGovernmentID($data['sss_number'], 'SSS')) {
            $errors[] = "SSS Number must be exactly 10 digits (format: 12-3456789-0)";
        }
        if (!empty($data['philhealth_number']) && !validateGovernmentID($data['philhealth_number'], 'PhilHealth')) {
            $errors[] = "PhilHealth Number must be exactly 12 digits (format: 12-345678901-2)";
        }
        if (!empty($data['pagibig_number']) && !validateGovernmentID($data['pagibig_number'], 'PagIBIG')) {
            $errors[] = "Pag-IBIG Number must be exactly 12 digits (format: 1234-5678-9012)";
        }
        
        // Validate email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check for duplicate company code
        if (empty($errors)) {
            $check_query = "SELECT id FROM companies WHERE code = ? AND is_deleted = 0";
            $check_result = executeQuery($check_query, "s", [$data['code']]);
            
            if ($check_result && $check_result->num_rows > 0) {
                $errors[] = "Company code already exists";
            }
        }
        
        if (empty($errors)) {
            $query = "INSERT INTO companies (
                code, name, building_name, unit_number, house_number, street_name, 
                barangay, city, province, region, postal_code, contact_number, email,
                tin, sss_number, philhealth_number, pagibig_number, 
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['code'],
                $data['name'],
                $data['building_name'] ?? null,
                $data['unit_number'] ?? null,
                $data['house_number'] ?? null,
                $data['street_name'] ?? null,
                $data['barangay'],
                $data['city'],
                $data['province'],
                $data['region'],
                $data['postal_code'] ?? null,
                $data['contact_number'] ?? null,
                $data['email'] ?? null,
                $data['tin'] ?? null,
                $data['sss_number'] ?? null,
                $data['philhealth_number'] ?? null,
                $data['pagibig_number'] ?? null,
                $_SESSION['user_id']
            ];
            
            $types = "sssssssssssssssssi";
            $result = executeQuery($query, $types, $params);
            
            if ($result) {
                $insert_id = $result;
                
                // Log activity
                logActivity(
                    $_SESSION['user_id'],
                    'CREATE',
                    'companies',
                    $insert_id,
                    null,
                    $data,
                    "Added new company: {$data['name']} ({$data['code']})"
                );
                
                $_SESSION['success'] = "Company added successfully!";
                header("Location: index.php?page=company");
                exit;
            } else {
                $errors[] = "Failed to add company. Please try again.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            $_SESSION['form_data'] = $data; // Preserve form data
            header("Location: index.php?page=company&action=add");
            exit;
        }
    }
    
    // ==================== EDIT COMPANY ====================
    elseif ($post_action === 'edit') {
        $company_id = $data['company_id'] ?? null;
        
        // Validate required fields
        if (empty($company_id)) {
            $errors[] = "Invalid company ID";
        }
        if (empty($data['code'])) {
            $errors[] = "Company code is required";
        }
        if (empty($data['name'])) {
            $errors[] = "Company name is required";
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
        
        // Validate government ID digit counts
        if (!empty($data['tin']) && !validateGovernmentID($data['tin'], 'TIN')) {
            $errors[] = "TIN must be exactly 12 digits (format: 123-456-789-000)";
        }
        if (!empty($data['sss_number']) && !validateGovernmentID($data['sss_number'], 'SSS')) {
            $errors[] = "SSS Number must be exactly 10 digits (format: 12-3456789-0)";
        }
        if (!empty($data['philhealth_number']) && !validateGovernmentID($data['philhealth_number'], 'PhilHealth')) {
            $errors[] = "PhilHealth Number must be exactly 12 digits (format: 12-345678901-2)";
        }
        if (!empty($data['pagibig_number']) && !validateGovernmentID($data['pagibig_number'], 'PagIBIG')) {
            $errors[] = "Pag-IBIG Number must be exactly 12 digits (format: 1234-5678-9012)";
        }
        
        // Validate email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check for duplicate company code (excluding current record)
        if (empty($errors)) {
            $check_query = "SELECT id FROM companies WHERE code = ? AND id != ? AND is_deleted = 0";
            $check_result = executeQuery($check_query, "si", [$data['code'], $company_id]);
            
            if ($check_result && $check_result->num_rows > 0) {
                $errors[] = "Company code already exists";
            }
        }
        
        // Get old values for activity log
        $old_values = null;
        if (empty($errors)) {
            $old_query = "SELECT * FROM companies WHERE id = ? AND is_deleted = 0";
            $old_result = executeQuery($old_query, "i", [$company_id]);
            if ($old_result && $old_result->num_rows > 0) {
                $old_values = $old_result->fetch_assoc();
            } else {
                $errors[] = "Company not found";
            }
        }
        
        if (empty($errors)) {
            $query = "UPDATE companies SET
                code = ?, name = ?, building_name = ?, unit_number = ?, house_number = ?,
                street_name = ?, barangay = ?, city = ?, province = ?, region = ?, postal_code = ?,
                contact_number = ?, email = ?, tin = ?, sss_number = ?,
                philhealth_number = ?, pagibig_number = ?, 
                updated_by = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['code'],
                $data['name'],
                $data['building_name'] ?? null,
                $data['unit_number'] ?? null,
                $data['house_number'] ?? null,
                $data['street_name'] ?? null,
                $data['barangay'],
                $data['city'],
                $data['province'],
                $data['region'],
                $data['postal_code'] ?? null,
                $data['contact_number'] ?? null,
                $data['email'] ?? null,
                $data['tin'] ?? null,
                $data['sss_number'] ?? null,
                $data['philhealth_number'] ?? null,
                $data['pagibig_number'] ?? null,
                $_SESSION['user_id'],
                $company_id
            ];
            
            $types = "sssssssssssssssssii";
            $result = executeQuery($query, $types, $params);
            
            if ($result !== false) {
                // Log activity
                logActivity(
                    $_SESSION['user_id'],
                    'UPDATE',
                    'companies',
                    $company_id,
                    $old_values,
                    $data,
                    "Updated company: {$data['name']} ({$data['code']})"
                );
                
                $_SESSION['success'] = "Company updated successfully!";
                header("Location: index.php?page=company");
                exit;
            } else {
                $errors[] = "Failed to update company. Please try again.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            $_SESSION['form_data'] = $data; // Preserve form data
            header("Location: index.php?page=company&action=edit&id=" . $company_id);
            exit;
        }
    }
    
    // ==================== DELETE COMPANY ====================
    elseif ($post_action === 'delete') {
        $company_id = $data['company_id'] ?? null;
        
        if (empty($company_id)) {
            $_SESSION['error'] = "Invalid company ID";
            header("Location: index.php?page=company");
            exit;
        }
        
        // Get company info before deleting for activity log
        $company_query = "SELECT * FROM companies WHERE id = ? AND is_deleted = 0";
        $company_result = executeQuery($company_query, "i", [$company_id]);
        
        if ($company_result && $company_result->num_rows > 0) {
            $company_data = $company_result->fetch_assoc();
            
            $delete_query = "UPDATE companies SET 
                is_deleted = 1, 
                deleted_at = NOW(), 
                deleted_by = ? 
                WHERE id = ?";
            
            $result = executeQuery($delete_query, "ii", [$_SESSION['user_id'], $company_id]);
            
            if ($result !== false) {
                // Log activity
                logActivity(
                    $_SESSION['user_id'],
                    'DELETE',
                    'companies',
                    $company_id,
                    $company_data,
                    null,
                    "Deleted company: {$company_data['name']} ({$company_data['code']})"
                );
                
                $_SESSION['success'] = "Company deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete company. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Company not found";
        }
        
        header("Location: index.php?page=company");
        exit;
    }
}

// ==================== FETCH DATA ====================

// Fetch single company for edit/view
if (($action === 'edit' || $action === 'view') && $company_id) {
    $query = "SELECT * FROM companies WHERE id = ? AND is_deleted = 0";
    $result = executeQuery($query, "i", [$company_id]);
    
    if ($result && $result->num_rows > 0) {
        $company = $result->fetch_assoc();
        
        // Log view activity
        if ($action === 'view') {
            logActivity(
                $_SESSION['user_id'],
                'VIEW',
                'companies',
                $company_id,
                null,
                null,
                "Viewed company: {$company['name']} ({$company['code']})"
            );
        }
    } else {
        $_SESSION['error'] = "Company not found!";
        header("Location: index.php?page=company");
        exit;
    }
}

// Fetch all companies for list
if ($action === 'list') {
    $query = "SELECT * FROM companies WHERE is_deleted = 0 ORDER BY name ASC";
    $result = executeQuery($query);
    
    if ($result) {
        $companies = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get preserved form data if validation failed
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

?>

<!-- ==================== LIST VIEW ==================== -->
<?php if ($action === 'list'): ?>
    <div class="page-header">
        <h1 class="page-title">Company Management</h1>
        <p class="page-subtitle">Manage company information and details</p>
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
            <h2 class="card-title mb-0">Companies</h2>
            <a href="index.php?page=company&action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add New Company
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Company Name</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="mt-2 text-muted">No companies found. Add your first company to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $comp): ?>
                                <tr>
                                    <td><?= escapeHtml($comp['code']) ?></td>
                                    <td><strong><?= escapeHtml($comp['name']) ?></strong></td>
                                    <td>
                                        <?= escapeHtml($comp['city'] ?? '') ?>
                                        <?= !empty($comp['province']) ? ', ' . escapeHtml($comp['province']) : '' ?>
                                    </td>
                                    <td><?= escapeHtml($comp['contact_number'] ?? 'N/A') ?></td>
                                    <td><?= escapeHtml($comp['email'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($comp['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="index.php?page=company&action=view&id=<?= $comp['id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index.php?page=company&action=edit&id=<?= $comp['id'] ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                title="Delete"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-company-id="<?= $comp['id'] ?>"
                                                data-company-name="<?= escapeHtml($comp['name']) ?>">
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
                    <p>Are you sure you want to delete <strong id="companyNameToDelete"></strong>?</p>
                    <p class="text-muted mb-0">This action will soft delete the company. It can be recovered by an administrator.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="index.php?page=company" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="company_id" id="companyIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Company</button>
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
                    const companyId = button.getAttribute('data-company-id');
                    const companyName = button.getAttribute('data-company-name');
                    
                    document.getElementById('companyIdToDelete').value = companyId;
                    document.getElementById('companyNameToDelete').textContent = companyName;
                });
            }
        });
    </script>

<!-- ==================== ADD/EDIT FORM VIEW ==================== -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="page-header">
        <h1 class="page-title"><?= $action === 'add' ? 'Add New Company' : 'Edit Company' ?></h1>
        <p class="page-subtitle">Fill in the company information below</p>
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
            <form method="POST" action="index.php?page=company" id="companyForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="<?= $action ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-12">
                        <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Code <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="code" 
                               class="form-control" 
                               required 
                               maxlength="10"
                               value="<?= escapeHtml($form_data['code'] ?? $company['code'] ?? '') ?>"
                               placeholder="e.g., COMP001">
                        <small class="form-text text-muted">Unique identifier for the company</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="name" 
                               class="form-control" 
                               required 
                               maxlength="100"
                               value="<?= escapeHtml($form_data['name'] ?? $company['name'] ?? '') ?>"
                               placeholder="Enter company name">
                    </div>

                    <!-- Address Information -->
                    <div class="col-md-12 mt-3">
                        <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Address Information</h5>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Building Name</label>
                        <input type="text" 
                               name="building_name" 
                               class="form-control" 
                               maxlength="200"
                               value="<?= escapeHtml($form_data['building_name'] ?? $company['building_name'] ?? '') ?>"
                               placeholder="e.g., SM Mall of Asia">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Unit Number</label>
                        <input type="text" 
                               name="unit_number" 
                               class="form-control" 
                               maxlength="50"
                               value="<?= escapeHtml($form_data['unit_number'] ?? $company['unit_number'] ?? '') ?>"
                               placeholder="e.g., 2F-123">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">House Number</label>
                        <input type="text" 
                               name="house_number" 
                               class="form-control" 
                               maxlength="50"
                               value="<?= escapeHtml($form_data['house_number'] ?? $company['house_number'] ?? '') ?>"
                               placeholder="e.g., 123">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Street Name</label>
                        <input type="text" 
                               name="street_name" 
                               class="form-control" 
                               maxlength="150"
                               value="<?= escapeHtml($form_data['street_name'] ?? $company['street_name'] ?? '') ?>"
                               placeholder="e.g., Roxas Boulevard">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Region <span class="text-danger">*</span></label>
                        <select id="region" class="form-control" required>
                            <option value="">Select Region</option>
                        </select>
                        <input type="hidden" name="region" id="region_name" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Province <span class="text-danger">*</span></label>
                        <select id="province" class="form-control" required disabled>
                            <option value="">Select Province</option>
                        </select>
                        <input type="hidden" name="province" id="province_name" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">City/Municipality <span class="text-danger">*</span></label>
                        <select id="city" class="form-control" required disabled>
                            <option value="">Select City/Municipality</option>
                        </select>
                        <input type="hidden" name="city" id="city_name" required>
                    </div>

                    <div class="col-md-6 mb-3">
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
                               value="<?= escapeHtml($form_data['postal_code'] ?? $company['postal_code'] ?? '') ?>"
                               placeholder="e.g., 1000">
                        <small class="form-text text-muted">4-digit postal code</small>
                    </div>

                    <!-- Contact Information -->
                    <div class="col-md-12 mt-3">
                        <h5 class="mb-3"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" 
                               name="contact_number" 
                               class="form-control" 
                               maxlength="20"
                               value="<?= escapeHtml($form_data['contact_number'] ?? $company['contact_number'] ?? '') ?>"
                               placeholder="e.g., +63 2 1234 5678">
                        <small class="form-text text-muted">Numbers, spaces, and +()-</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               maxlength="100"
                               value="<?= escapeHtml($form_data['email'] ?? $company['email'] ?? '') ?>"
                               placeholder="e.g., info@company.com">
                    </div>

                    <!-- Government Registration Numbers -->
                    <div class="col-md-12 mt-3">
                        <h5 class="mb-3"><i class="bi bi-card-list me-2"></i>Government Registration Numbers</h5>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Enter numbers only. Formatting will be applied automatically.
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">TIN (12 digits)</label>
                        <input type="text" 
                               name="tin" 
                               id="tin"
                               class="form-control" 
                               maxlength="15"
                               value="<?= escapeHtml($form_data['tin'] ?? $company['tin'] ?? '') ?>"
                               placeholder="123-456-789-000">
                        <small class="form-text text-muted">Tax Identification Number (12 digits: 123-456-789-000)</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">SSS Number (10 digits)</label>
                        <input type="text" 
                               name="sss_number" 
                               id="sss_number"
                               class="form-control" 
                               maxlength="12"
                               value="<?= escapeHtml($form_data['sss_number'] ?? $company['sss_number'] ?? '') ?>"
                               placeholder="12-3456789-0">
                        <small class="form-text text-muted">Social Security System (10 digits: 12-3456789-0)</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">PhilHealth Number (12 digits)</label>
                        <input type="text" 
                               name="philhealth_number" 
                               id="philhealth_number"
                               class="form-control" 
                               maxlength="14"
                               value="<?= escapeHtml($form_data['philhealth_number'] ?? $company['philhealth_number'] ?? '') ?>"
                               placeholder="12-345678901-2">
                        <small class="form-text text-muted">PhilHealth (12 digits: 12-345678901-2)</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pag-IBIG Number (12 digits)</label>
                        <input type="text" 
                               name="pagibig_number" 
                               id="pagibig_number"
                               class="form-control" 
                               maxlength="14"
                               value="<?= escapeHtml($form_data['pagibig_number'] ?? $company['pagibig_number'] ?? '') ?>"
                               placeholder="1234-5678-9012">
                        <small class="form-text text-muted">Home Development Mutual Fund (12 digits: 1234-5678-9012)</small>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i><?= $action === 'add' ? 'Add Company' : 'Update Company' ?>
                    </button>
                    <a href="index.php?page=company" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ==================== GOVERNMENT ID FORMATTING ====================
        
        /**
         * Format TIN: 123-456-789-000 (12 digits)
         */
        function formatTINInput(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 12) value = value.substr(0, 12);
            
            let formatted = '';
            if (value.length > 0) formatted += value.substr(0, 3);
            if (value.length > 3) formatted += '-' + value.substr(3, 3);
            if (value.length > 6) formatted += '-' + value.substr(6, 3);
            if (value.length > 9) formatted += '-' + value.substr(9, 3);
            
            input.value = formatted;
        }

        /**
         * Format SSS: 12-3456789-0 (10 digits)
         */
        function formatSSSInput(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 10) value = value.substr(0, 10);
            
            let formatted = '';
            if (value.length > 0) formatted += value.substr(0, 2);
            if (value.length > 2) formatted += '-' + value.substr(2, 7);
            if (value.length > 9) formatted += '-' + value.substr(9, 1);
            
            input.value = formatted;
        }

        /**
         * Format PhilHealth: 12-345678901-2 (12 digits)
         */
        function formatPhilHealthInput(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 12) value = value.substr(0, 12);
            
            let formatted = '';
            if (value.length > 0) formatted += value.substr(0, 2);
            if (value.length > 2) formatted += '-' + value.substr(2, 9);
            if (value.length > 11) formatted += '-' + value.substr(11, 1);
            
            input.value = formatted;
        }

        /**
         * Format Pag-IBIG: 1234-5678-9012 (12 digits)
         */
        function formatPagIBIGInput(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 12) value = value.substr(0, 12);
            
            let formatted = '';
            if (value.length > 0) formatted += value.substr(0, 4);
            if (value.length > 4) formatted += '-' + value.substr(4, 4);
            if (value.length > 8) formatted += '-' + value.substr(8, 4);
            
            input.value = formatted;
        }

        // ==================== STRIP FORMATTING BEFORE SUBMIT ====================
        
        document.getElementById('companyForm').addEventListener('submit', function(e) {
            // Strip all non-numeric characters before submitting
            const tinInput = document.getElementById('tin');
            const sssInput = document.getElementById('sss_number');
            const philhealthInput = document.getElementById('philhealth_number');
            const pagibigInput = document.getElementById('pagibig_number');
            
            if (tinInput && tinInput.value) {
                tinInput.value = tinInput.value.replace(/\D/g, '');
            }
            if (sssInput && sssInput.value) {
                sssInput.value = sssInput.value.replace(/\D/g, '');
            }
            if (philhealthInput && philhealthInput.value) {
                philhealthInput.value = philhealthInput.value.replace(/\D/g, '');
            }
            if (pagibigInput && pagibigInput.value) {
                pagibigInput.value = pagibigInput.value.replace(/\D/g, '');
            }
        });

        // ==================== ATTACH EVENT LISTENERS ====================
        
        document.addEventListener('DOMContentLoaded', function() {
            const tinInput = document.getElementById('tin');
            const sssInput = document.getElementById('sss_number');
            const philhealthInput = document.getElementById('philhealth_number');
            const pagibigInput = document.getElementById('pagibig_number');
            
            if (tinInput) {
                tinInput.addEventListener('input', function() { formatTINInput(this); });
                // Format on load (for edit mode)
                if (tinInput.value) formatTINInput(tinInput);
            }
            
            if (sssInput) {
                sssInput.addEventListener('input', function() { formatSSSInput(this); });
                if (sssInput.value) formatSSSInput(sssInput);
            }
            
            if (philhealthInput) {
                philhealthInput.addEventListener('input', function() { formatPhilHealthInput(this); });
                if (philhealthInput.value) formatPhilHealthInput(philhealthInput);
            }
            
            if (pagibigInput) {
                pagibigInput.addEventListener('input', function() { formatPagIBIGInput(this); });
                if (pagibigInput.value) formatPagIBIGInput(pagibigInput);
            }
        });

        // ==================== PSGC ADDRESS ====================
        
        console.log('Initializing PSGC Address...');
        
        const psgcAddress = new PSGCAddress({
            prefix: '',
            apiUrl: '<?= BASE_URL ?>/api/psgc-api.php'
        });

        <?php if ($action === 'edit' && $company): ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Setting values for edit mode...');
            psgcAddress.setValuesByName({
                region: '<?= escapeHtml($company['region'] ?? '') ?>',
                province: '<?= escapeHtml($company['province'] ?? '') ?>',
                city: '<?= escapeHtml($company['city'] ?? '') ?>',
                barangay: '<?= escapeHtml($company['barangay'] ?? '') ?>'
            });
        });
        <?php endif; ?>
    </script>

<!-- ==================== VIEW DETAIL ==================== -->
<?php elseif ($action === 'view'): ?>
    <div class="page-header">
        <h1 class="page-title">Company Details</h1>
        <p class="page-subtitle">View complete company information</p>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0"><?= escapeHtml($company['name']) ?></h2>
            <div>
                <a href="index.php?page=company&action=edit&id=<?= $company['id'] ?>" class="btn btn-warning">
                    <i class="bi bi-pencil me-2"></i>Edit
                </a>
                <a href="index.php?page=company" class="btn btn-secondary">
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
                            <th width="40%">Company Code:</th>
                            <td><?= escapeHtml($company['code']) ?></td>
                        </tr>
                        <tr>
                            <th>Company Name:</th>
                            <td><?= escapeHtml($company['name']) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($company['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <h5 class="mb-3 mt-4"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Contact Number:</th>
                            <td><?= escapeHtml($company['contact_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?= escapeHtml($company['email'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Address</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Building Name:</th>
                            <td><?= escapeHtml($company['building_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Unit Number:</th>
                            <td><?= escapeHtml($company['unit_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>House Number:</th>
                            <td><?= escapeHtml($company['house_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Street:</th>
                            <td><?= escapeHtml($company['street_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Barangay:</th>
                            <td><?= escapeHtml($company['barangay'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>City:</th>
                            <td><?= escapeHtml($company['city'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Province:</th>
                            <td><?= escapeHtml($company['province'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Region:</th>
                            <td><?= escapeHtml($company['region'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Postal Code:</th>
                            <td><?= escapeHtml($company['postal_code'] ?? 'N/A') ?></td>
                        </tr>
                    </table>

                    <h5 class="mb-3 mt-4"><i class="bi bi-card-list me-2"></i>Government Numbers</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">TIN:</th>
                            <td><?= formatTIN($company['tin'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>SSS Number:</th>
                            <td><?= formatSSS($company['sss_number'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>PhilHealth Number:</th>
                            <td><?= formatPhilHealth($company['philhealth_number'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Pag-IBIG Number:</th>
                            <td><?= formatPagIBIG($company['pagibig_number'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i>Audit Trail</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="15%">Created:</th>
                            <td><?= date('F d, Y h:i A', strtotime($company['created_at'])) ?></td>
                            <th width="15%">Updated:</th>
                            <td><?= date('F d, Y h:i A', strtotime($company['updated_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>