<?php
/**
 * Positions Management Module
 *
 * Handles CRUD operations for position records
 * - List all positions
 * - Add new position
 * - Edit existing position
 * - View position details
 * - Delete position (soft delete)
 *
 * @package HRIS
 * @author HRIS Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

// Get action and position ID
$action = $_GET['action'] ?? 'list';
$position_id = $_GET['id'] ?? null;

// Initialize variables
$errors = [];
$position = null;
$positions = [];

// ==================== POST PROCESSING ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=positions");
        exit;
    }

    $post_action = $_POST['action'] ?? '';

    // Sanitize all input data
    $data = sanitizeInput($_POST);

    // Validate required fields
    if (empty($data['position_title'])) {
        $errors[] = "Position title is required";
    }

    // Validate position code if provided
    if (!empty($data['position_code'])) {
        if (strlen($data['position_code']) > 20) {
            $errors[] = "Position code must not exceed 20 characters";
        }
    }

    // Validate position title length
    if (!empty($data['position_title']) && strlen($data['position_title']) > 100) {
        $errors[] = "Position title must not exceed 100 characters";
    }

    // Process if no validation errors
    if (empty($errors)) {
        $user_id = getCurrentUserId();

        if ($post_action === 'add') {
            // Insert new position
            $stmt = $conn->prepare("
                INSERT INTO positions
                (position_code, position_title, job_description, is_active, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $is_active = isset($data['is_active']) ? 1 : 0;

            $stmt->bind_param(
                "sssiii",
                $data['position_code'],
                $data['position_title'],
                $data['job_description'],
                $is_active,
                $user_id,
                $user_id
            );

            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                logActivity($user_id, 'ADD', 'positions', $new_id, null, json_encode($data));
                setFlashMessage("Position added successfully!", "success");
                header("Location: index.php?page=positions");
                exit;
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }

        } elseif ($post_action === 'edit' && !empty($data['id'])) {
            // Update existing position
            $stmt = $conn->prepare("
                UPDATE positions
                SET position_code = ?,
                    position_title = ?,
                    job_description = ?,
                    is_active = ?,
                    updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND is_deleted = 0
            ");

            $is_active = isset($data['is_active']) ? 1 : 0;

            $stmt->bind_param(
                "sssiii",
                $data['position_code'],
                $data['position_title'],
                $data['job_description'],
                $is_active,
                $user_id,
                $data['id']
            );

            if ($stmt->execute()) {
                logActivity($user_id, 'EDIT', 'positions', $data['id'], null, json_encode($data));
                setFlashMessage("Position updated successfully!", "success");
                header("Location: index.php?page=positions");
                exit;
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
        }
    }

    // If there are errors, store in session for display
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $data;
    }
}

// ==================== DELETE ACTION ====================

if ($action === 'delete' && $position_id) {
    // Verify CSRF token from URL
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlashMessage("Invalid security token.", "error");
        header("Location: index.php?page=positions");
        exit;
    }

    $user_id = getCurrentUserId();

    // Soft delete
    $stmt = $conn->prepare("
        UPDATE positions
        SET is_deleted = 1,
            deleted_at = CURRENT_TIMESTAMP,
            deleted_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $user_id, $position_id);

    if ($stmt->execute()) {
        logActivity($user_id, 'DELETE', 'positions', $position_id);
        setFlashMessage("Position deleted successfully!", "success");
    } else {
        setFlashMessage("Error deleting position: " . $stmt->error, "error");
    }

    header("Location: index.php?page=positions");
    exit;
}

// ==================== DATA FETCHING ====================

if ($action === 'edit' && $position_id) {
    // Fetch position for editing
    $stmt = $conn->prepare("SELECT * FROM positions WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = $result->fetch_assoc();

    if (!$position) {
        setFlashMessage("Position not found", "error");
        header("Location: index.php?page=positions");
        exit;
    }

    // Restore form data if there were validation errors
    if (isset($_SESSION['form_data'])) {
        $position = array_merge($position, $_SESSION['form_data']);
        unset($_SESSION['form_data']);
    }
}

if ($action === 'list') {
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? 'active';

    // Build query
    $query = "SELECT * FROM positions WHERE is_deleted = 0";
    $params = [];
    $types = "";

    // Apply status filter
    if ($status_filter === 'active') {
        $query .= " AND is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND is_active = 0";
    }

    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (position_code LIKE ? OR position_title LIKE ? OR job_description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }

    $query .= " ORDER BY position_title ASC";

    // Execute query
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    $positions = $result->fetch_all(MYSQLI_ASSOC);
}

// Get flash messages and errors from session
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
            <i class="bi bi-briefcase me-2"></i>
            <?php
            if ($action === 'add') {
                echo 'Add New Position';
            } elseif ($action === 'edit') {
                echo 'Edit Position';
            } else {
                echo 'Positions';
            }
            ?>
        </h1>
        <p class="text-muted mb-0">Manage job positions and descriptions</p>
    </div>

    <?php if ($action === 'list'): ?>
    <div>
        <a href="index.php?page=positions&action=add" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Add Position
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
        <form method="GET" action="index.php" class="row g-3">
            <input type="hidden" name="page" value="positions">

            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?= escapeHtml($search) ?>"
                       placeholder="Search by code, title, or description">
            </div>

            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Positions Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($positions)): ?>
        <div class="text-center py-5">
            <i class="bi bi-briefcase" style="font-size: 4rem; color: #ccc;"></i>
            <p class="text-muted mt-3">No positions found</p>
            <a href="index.php?page=positions&action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add First Position
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Position Title</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positions as $pos): ?>
                    <tr>
                        <td><?= escapeHtml($pos['position_code'] ?? 'N/A') ?></td>
                        <td>
                            <strong><?= escapeHtml($pos['position_title']) ?></strong>
                        </td>
                        <td>
                            <?php
                            $desc = $pos['job_description'] ?? '';
                            if (strlen($desc) > 100) {
                                echo escapeHtml(substr($desc, 0, 100)) . '...';
                            } else {
                                echo escapeHtml($desc ?: 'No description');
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($pos['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="index.php?page=positions&action=edit&id=<?= $pos['id'] ?>"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="index.php?page=positions&action=delete&id=<?= $pos['id'] ?>&csrf_token=<?= generateCsrfToken() ?>"
                                   class="btn btn-outline-danger"
                                   title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this position?')">
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
            <p class="text-muted mb-0">
                <i class="bi bi-info-circle me-2"></i>
                Showing <?= count($positions) ?> position(s)
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

<!-- Add/Edit Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="index.php?page=positions">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $position['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <!-- Position Code -->
                        <div class="col-md-4">
                            <label for="position_code" class="form-label">Position Code</label>
                            <input type="text"
                                   class="form-control"
                                   id="position_code"
                                   name="position_code"
                                   maxlength="20"
                                   value="<?= escapeHtml($position['position_code'] ?? '') ?>"
                                   placeholder="e.g., MGR-001">
                            <small class="text-muted">Optional - Max 20 characters</small>
                        </div>

                        <!-- Position Title -->
                        <div class="col-md-8">
                            <label for="position_title" class="form-label">
                                Position Title <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="position_title"
                                   name="position_title"
                                   maxlength="100"
                                   required
                                   value="<?= escapeHtml($position['position_title'] ?? '') ?>"
                                   placeholder="e.g., Senior Software Engineer">
                            <small class="text-muted">Required - Max 100 characters</small>
                        </div>

                        <!-- Job Description -->
                        <div class="col-12">
                            <label for="job_description" class="form-label">Job Description</label>
                            <textarea class="form-control"
                                      id="job_description"
                                      name="job_description"
                                      rows="6"
                                      placeholder="Enter detailed job description, responsibilities, and requirements..."><?= escapeHtml($position['job_description'] ?? '') ?></textarea>
                        </div>

                        <!-- Active Status -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                       <?= ($action === 'add' || ($position['is_active'] ?? 0)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Active Position
                                </label>
                                <small class="text-muted d-block">Inactive positions will not be available for assignment</small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            <?= $action === 'add' ? 'Add Position' : 'Update Position' ?>
                        </button>
                        <a href="index.php?page=positions" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Help Panel -->
    <div class="col-lg-4">
        <div class="card bg-light">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-info-circle me-2"></i>Help
                </h5>
                <h6 class="mt-3">Position Code</h6>
                <p class="small text-muted">
                    Optional unique identifier for the position (e.g., MGR-001, ENG-SR-01)
                </p>

                <h6 class="mt-3">Position Title</h6>
                <p class="small text-muted">
                    The official job title as it appears in employment contracts and organizational charts
                </p>

                <h6 class="mt-3">Job Description</h6>
                <p class="small text-muted">
                    Detailed description including responsibilities, requirements, and qualifications for this position
                </p>

                <h6 class="mt-3">Active Status</h6>
                <p class="small text-muted">
                    Only active positions can be assigned to employees. Inactive positions are hidden from selection lists
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
