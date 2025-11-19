<?php
/**
 * Sidebar Navigation
 * 
 * Main navigation sidebar with permission-based menu display.
 * Menu items are shown based on user permissions.
 * 
 * @package HRIS
 * @version 2.1
 */

// Get current page for active state
$current_page = $page ?? 'dashboard';
$current_action = $_GET['action'] ?? '';
$subpage = $subpage ?? null; // For sub-pages like attendance/clock

/**
 * Check if menu item should be displayed based on permissions
 */
function canViewMenuItem($module) {
    // Admin sees everything
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Administrator') {
        return true;
    }
    
    // Check specific permission
    return hasPermission($module, 'view');
}

/**
 * Check if menu item is active
 */
function isMenuActive($menuPage, $currentPage, $menuAction = '', $currentAction = '') {
    if ($menuPage === $currentPage) {
        if (empty($menuAction) || $menuAction === $currentAction) {
            return true;
        }
    }
    return false;
}
?>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-header">
    <div class="logo">
      <i class="bi bi-people-fill" aria-hidden="true"></i>
      <span class="logo-text"><?= escapeHtml(SYSTEM_NAME) ?></span>
    </div>
    <button class="toggle-btn hidden lg:flex" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
  </div>

  <ul class="nav-menu" role="menubar">
    
    <!-- Dashboard - Always visible -->
    <li class="nav-item" role="none">
      <a href="<?= BASE_URL ?>/index.php?page=dashboard" 
         class="nav-link <?= isMenuActive('dashboard', $current_page) ? 'active' : '' ?>"
         role="menuitem"
         aria-current="<?= isMenuActive('dashboard', $current_page) ? 'page' : 'false' ?>">
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <!-- Company - FIXED: Check permission -->
    <?php if (canViewMenuItem('company')): ?>
    <li class="nav-item" role="none">
      <a href="<?= BASE_URL ?>/index.php?page=company" 
         class="nav-link <?= isMenuActive('company', $current_page) ? 'active' : '' ?>"
         role="menuitem"
         aria-current="<?= isMenuActive('company', $current_page) ? 'page' : 'false' ?>">
        <i class="bi bi-building" aria-hidden="true"></i>
        <span>Company</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- Organizational Units -->
    <?php if (canViewMenuItem('organizational_units')): ?>
    <li class="nav-item" role="none">
      <a href="<?= BASE_URL ?>/index.php?page=organizational_units" 
         class="nav-link <?= isMenuActive('organizational_units', $current_page) ? 'active' : '' ?>"
         role="menuitem"
         aria-current="<?= isMenuActive('organizational_units', $current_page) ? 'page' : 'false' ?>">
        <i class="bi bi-diagram-3" aria-hidden="true"></i>
        <span>Organizational Units</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- Minimum Wage Rates -->
    <?php if (canViewMenuItem('minimum_wage_rates')): ?>
    <li class="nav-item" role="none">
      <a href="<?= BASE_URL ?>/index.php?page=minimum_wage_rates" 
         class="nav-link <?= isMenuActive('minimum_wage_rates', $current_page) ? 'active' : '' ?>"
         role="menuitem"
         aria-current="<?= isMenuActive('minimum_wage_rates', $current_page) ? 'page' : 'false' ?>">
        <i class="bi bi-cash-coin" aria-hidden="true"></i>
        <span>Minimum Wage Rates</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- Employees Submenu -->
    <?php if (canViewMenuItem('employees')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'employees' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'employees' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-people" aria-hidden="true"></i>
        <span>Employees</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'employees' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=employees&action=list" 
             class="submenu-link <?= isMenuActive('employees', $current_page, 'list', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            <span>All Employees</span>
          </a>
        </li>
        <?php if (hasPermission('employees', 'create')): ?>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=employees&action=add" 
             class="submenu-link <?= isMenuActive('employees', $current_page, 'add', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <span>Add Employee</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=employees&action=chart" 
             class="submenu-link <?= isMenuActive('employees', $current_page, 'chart', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <span>Organization Chart</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Positions -->
    <?php if (canViewMenuItem('positions')): ?>
    <li class="nav-item" role="none">
      <a href="<?= BASE_URL ?>/index.php?page=positions" 
         class="nav-link <?= isMenuActive('positions', $current_page) ? 'active' : '' ?>"
         role="menuitem"
         aria-current="<?= isMenuActive('positions', $current_page) ? 'page' : 'false' ?>">
        <i class="bi bi-briefcase" aria-hidden="true"></i>
        <span>Positions</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- Recruitment Submenu -->
    <?php if (canViewMenuItem('recruitment')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'recruitment' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'recruitment' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-person-badge" aria-hidden="true"></i>
        <span>Recruitment</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'recruitment' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=recruitment&action=jobs" 
             class="submenu-link <?= isMenuActive('recruitment', $current_page, 'jobs', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-briefcase" aria-hidden="true"></i>
            <span>Job Openings</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=recruitment&action=candidates" 
             class="submenu-link <?= isMenuActive('recruitment', $current_page, 'candidates', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-file-earmark-person" aria-hidden="true"></i>
            <span>Candidates</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=recruitment&action=interviews" 
             class="submenu-link <?= isMenuActive('recruitment', $current_page, 'interviews', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
            <span>Interviews</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Attendance Submenu -->
    <?php if (canViewMenuItem('attendance')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'attendance' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'attendance' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-calendar-check" aria-hidden="true"></i>
        <span>Attendance</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'attendance' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance/clock"
             class="submenu-link <?= ($current_page === 'attendance' && $subpage === 'clock') ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-clock-fill" aria-hidden="true"></i>
            <span>Clock In/Out</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance/enroll"
             class="submenu-link <?= ($current_page === 'attendance' && $subpage === 'enroll') ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-person-badge" aria-hidden="true"></i>
            <span>Face Enrollment</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance/history"
             class="submenu-link <?= ($current_page === 'attendance' && $subpage === 'history') ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>Attendance History</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance&action=daily"
             class="submenu-link <?= isMenuActive('attendance', $current_page, 'daily', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar-day" aria-hidden="true"></i>
            <span>Daily Attendance</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance&action=report"
             class="submenu-link <?= isMenuActive('attendance', $current_page, 'report', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            <span>Attendance Report</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=attendance&action=schedule"
             class="submenu-link <?= isMenuActive('attendance', $current_page, 'schedule', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar-week" aria-hidden="true"></i>
            <span>Shift Schedule</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Leave Management Submenu -->
    <?php if (canViewMenuItem('leave')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'leave' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'leave' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-calendar-event" aria-hidden="true"></i>
        <span>Leave Management</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'leave' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=leave&action=requests" 
             class="submenu-link <?= isMenuActive('leave', $current_page, 'requests', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar-x" aria-hidden="true"></i>
            <span>Leave Requests</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=leave&action=balance" 
             class="submenu-link <?= isMenuActive('leave', $current_page, 'balance', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar2-range" aria-hidden="true"></i>
            <span>Leave Balance</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=leave&action=holidays" 
             class="submenu-link <?= isMenuActive('leave', $current_page, 'holidays', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-calendar-day" aria-hidden="true"></i>
            <span>Holidays</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Payroll Submenu -->
    <?php if (canViewMenuItem('payroll')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'payroll' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'payroll' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-cash-stack" aria-hidden="true"></i>
        <span>Payroll</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'payroll' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=payroll&action=run" 
             class="submenu-link <?= isMenuActive('payroll', $current_page, 'run', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-cash-coin" aria-hidden="true"></i>
            <span>Run Payroll</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=payroll&action=payslips" 
             class="submenu-link <?= isMenuActive('payroll', $current_page, 'payslips', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <span>Payslips</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=payroll&action=salary" 
             class="submenu-link <?= isMenuActive('payroll', $current_page, 'salary', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-wallet2" aria-hidden="true"></i>
            <span>Salary Structure</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Performance Submenu -->
    <?php if (canViewMenuItem('performance')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'performance' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'performance' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-graph-up" aria-hidden="true"></i>
        <span>Performance</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'performance' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=performance&action=appraisals" 
             class="submenu-link <?= isMenuActive('performance', $current_page, 'appraisals', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-star" aria-hidden="true"></i>
            <span>Appraisals</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=performance&action=goals" 
             class="submenu-link <?= isMenuActive('performance', $current_page, 'goals', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-bullseye" aria-hidden="true"></i>
            <span>Goals & KPIs</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Reports Submenu -->
    <?php if (canViewMenuItem('reports')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'reports' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'reports' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <span>Reports</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'reports' ? 'show' : '' ?>" role="menu">
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=reports&action=analytics" 
             class="submenu-link <?= isMenuActive('reports', $current_page, 'analytics', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-bar-chart" aria-hidden="true"></i>
            <span>Analytics Dashboard</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=reports&action=employees" 
             class="submenu-link <?= isMenuActive('reports', $current_page, 'employees', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
            <span>Employee Reports</span>
          </a>
        </li>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=reports&action=payroll" 
             class="submenu-link <?= isMenuActive('reports', $current_page, 'payroll', $current_action) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
            <span>Payroll Reports</span>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Settings Submenu -->
    <?php if (canViewMenuItem('settings')): ?>
    <li class="nav-item" role="none">
      <a href="#" 
         class="nav-link has-submenu <?= $current_page === 'settings' ? 'active' : '' ?>" 
         onclick="toggleSubmenu(event, this)"
         role="menuitem"
         aria-expanded="<?= $current_page === 'settings' ? 'true' : 'false' ?>"
         aria-haspopup="true">
        <i class="bi bi-gear" aria-hidden="true"></i>
        <span>Settings</span>
        <i class="bi bi-chevron-down submenu-arrow" aria-hidden="true"></i>
      </a>
      <ul class="submenu <?= $current_page === 'settings' ? 'show' : '' ?>" role="menu">
        <?php if (canViewMenuItem('users')): ?>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=users" 
             class="submenu-link <?= isMenuActive('users', $current_page) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-person-gear" aria-hidden="true"></i>
            <span>User Management</span>
          </a>
        </li>
        <?php endif; ?>
        <?php if (canViewMenuItem('roles')): ?>
        <li class="submenu-item" role="none">
          <a href="<?= BASE_URL ?>/index.php?page=roles" 
             class="submenu-link <?= isMenuActive('roles', $current_page) ? 'active' : '' ?>"
             role="menuitem">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <span>Roles & Permissions</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Logout (Mobile/Tablet Only) -->
    <li class="nav-item lg:hidden" role="none">
      <a href="<?= BASE_URL ?>/modules/auth/logout.php"
         class="nav-link text-red-600 dark:text-red-400"
         role="menuitem">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        <span>Logout</span>
      </a>
    </li>

  </ul>
</aside>