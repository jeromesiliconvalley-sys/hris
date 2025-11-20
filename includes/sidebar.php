<?php
// includes/sidebar.php

// Ensure variables are available
$page = $page ?? 'dashboard';
$action = $action ?? '';

// Helper functions - only define if not already defined (sidebar is included twice)
if (!function_exists('isMenuActive')) {
    function isMenuActive($menuPage, $currentPage) {
        return (strpos($currentPage, $menuPage) === 0) ? 'active' : 'link-dark';
    }
}

if (!function_exists('isExpanded')) {
    function isExpanded($menuPage, $currentPage) {
        return (strpos($currentPage, $menuPage) === 0) ? 'show' : '';
    }
}

if (!function_exists('getToggleState')) {
    function getToggleState($menuPage, $currentPage) {
        return (strpos($currentPage, $menuPage) === 0) ? 'true' : 'false';
    }
}

if (!function_exists('canViewModule')) {
    // Helper function to check if user can view module (with fallback)
    function canViewModule($module) {
        // Use existing canView if available, otherwise return true for admin
        if (function_exists('canView')) {
            return canView($module);
        }
        // Fallback: allow if user is logged in
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
}
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white h-100 border-end">
    <a href="<?= BASE_URL ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-dark text-decoration-none">
        <i class="bi bi-people-fill fs-4 me-2 text-primary"></i>
        <span class="fs-4 fw-bold"><?= htmlspecialchars(SYSTEM_NAME) ?></span>
    </a>
    <hr>

    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="nav-link <?= isMenuActive('dashboard', $page) ?>" aria-current="page">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>

        <?php if (canView('company')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=company" class="nav-link <?= isMenuActive('company', $page) ?>">
                <i class="bi bi-building me-2"></i>
                Company
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('employees')): ?>
        <li class="nav-item">
            <a href="#" class="nav-link d-flex justify-content-between align-items-center <?= isMenuActive('employees', $page) ?>"
               data-bs-toggle="collapse"
               data-bs-target="#employees-collapse"
               aria-expanded="<?= getToggleState('employees', $page) ?>">
                <span><i class="bi bi-people me-2"></i> Employees</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= isExpanded('employees', $page) ?>" id="employees-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ms-4 mt-1">
                    <li><a href="<?= BASE_URL ?>/index.php?page=employees&action=list" class="nav-link py-1 <?= isMenuActive('employees/list', $page . '/' . $action) ?>">All Employees</a></li>
                    <?php if (hasPermission('employees', 'create')): ?>
                    <li><a href="<?= BASE_URL ?>/index.php?page=employees&action=add" class="nav-link py-1 <?= isMenuActive('employees/add', $page . '/' . $action) ?>">Add Employee</a></li>
                    <?php endif; ?>
                    <li><a href="<?= BASE_URL ?>/index.php?page=employees&action=chart" class="nav-link py-1 <?= isMenuActive('employees/chart', $page . '/' . $action) ?>">Org Chart</a></li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <?php if (canView('attendance')): ?>
        <li class="nav-item">
            <a href="#" class="nav-link d-flex justify-content-between align-items-center <?= isMenuActive('attendance', $page) ?>"
               data-bs-toggle="collapse"
               data-bs-target="#attendance-collapse"
               aria-expanded="<?= getToggleState('attendance', $page) ?>">
                <span><i class="bi bi-clock me-2"></i> Attendance</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= isExpanded('attendance', $page) ?>" id="attendance-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ms-4 mt-1">
                    <li><a href="<?= BASE_URL ?>/index.php?page=attendance/clock" class="nav-link py-1 <?= isMenuActive('attendance/clock', $page) ?>">Clock In/Out</a></li>
                    <li><a href="<?= BASE_URL ?>/index.php?page=attendance/history" class="nav-link py-1 <?= isMenuActive('attendance/history', $page) ?>">My History</a></li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-3">
            <span class="text-uppercase text-muted fw-bold small px-3">System</span>
        </li>

        <?php if (canView('settings') || canView('users')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=users" class="nav-link <?= isMenuActive('users', $page) ?>">
                <i class="bi bi-person-gear me-2"></i>
                Users
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="nav-link link-danger">
                <i class="bi bi-box-arrow-right me-2"></i>
                Logout
            </a>
        </li>
    </ul>
</div>
