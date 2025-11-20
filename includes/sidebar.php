<?php
// Helper function for active state
function isMenuActive($menuPage, $currentPage) {
    return (strpos($currentPage, $menuPage) === 0) ? 'active' : 'text-dark';
}

// Helper to check permissions (simplified wrapper)
function canView($module) {
    return isset($_SESSION['role_id']) && ($_SESSION['role_id'] == 1 || hasPermission($module, 'view'));
}
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white h-100 shadow-sm">
    <a href="<?= BASE_URL ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none text-primary">
        <i class="bi bi-people-fill fs-4 me-2"></i>
        <span class="fs-4 fw-bold text-truncate"><?= htmlspecialchars(SYSTEM_NAME) ?></span>
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
            <a href="<?= BASE_URL ?>/index.php?page=employees" class="nav-link <?= isMenuActive('employees', $page) ?>">
                <i class="bi bi-people me-2"></i>
                Employees
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('attendance')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=attendance/clock" class="nav-link <?= isMenuActive('attendance', $page) ?>">
                <i class="bi bi-clock me-2"></i>
                Attendance
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('leave')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=leave" class="nav-link <?= isMenuActive('leave', $page) ?>">
                <i class="bi bi-calendar-check me-2"></i>
                Leave
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('payroll')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=payroll" class="nav-link <?= isMenuActive('payroll', $page) ?>">
                <i class="bi bi-cash-stack me-2"></i>
                Payroll
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('organizational_units')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=organizational_units" class="nav-link <?= isMenuActive('organizational_units', $page) ?>">
                <i class="bi bi-diagram-3 me-2"></i>
                Org Units
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('positions')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=positions" class="nav-link <?= isMenuActive('positions', $page) ?>">
                <i class="bi bi-briefcase me-2"></i>
                Positions
            </a>
        </li>
        <?php endif; ?>

        <?php if (canView('minimum_wage_rates')): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/index.php?page=minimum_wage_rates" class="nav-link <?= isMenuActive('minimum_wage_rates', $page) ?>">
                <i class="bi bi-cash-coin me-2"></i>
                Wage Rates
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-3">
            <div class="text-uppercase fw-bold text-muted small px-3 mb-1">Settings</div>
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
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>
                Logout
            </a>
        </li>
    </ul>
</div>
