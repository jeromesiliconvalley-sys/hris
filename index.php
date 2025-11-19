<?php
/**
 * Main Application Index - Router
 * 
 * Handles routing and page loading with proper security and error handling.
 * 
 * This file:
 * - Routes requests to appropriate modules
 * - Validates page access and permissions
 * - Provides error boundaries for modules
 * - Manages layout rendering
 * 
 * @package HRIS
 * @version 2.1
 */

// Start output buffering to allow header redirects from modules
// This is necessary because modules might need to set headers or redirect
ob_start();

// Define access constant before including config
define('HRIS_ACCESS', true);

// Load core configuration files
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/session.php';

/**
 * Whitelist of allowed pages/modules
 * Add new modules here as they are created
 */
$allowed_pages = [
    'dashboard',
    'company',
    'organizational_units',
    'minimum_wage_rates',
    'positions',
    'employees',
    'users',
    'roles',
    'shift_schedules',
    'attendance',
    'leave',
    'overtime',
    'employee_requests',
    'recruitment',
    'announcements',
    'memos',
    'payroll',
    'government_contributions',
    'government_loans',
    'company_deductions',
    'thirteenth_month',
    'performance',
    'assets',
    'supplies',
    'incidents',
    'reports',
    'settings'
];

/**
 * Module-specific permission requirements
 * Define which permission is needed to access each module
 */
$module_permissions = [
    'dashboard' => 'view',
    'company' => 'view',
    'organizational_units' => 'view',
    'minimum_wage_rates' => 'view',
    'positions' => 'view',
    'employees' => 'view',
    'users' => 'view',
    'roles' => 'view',
    'shift_schedules' => 'view',
    'attendance' => 'view',
    'leave' => 'view',
    'overtime' => 'view',
    'employee_requests' => 'view',
    'recruitment' => 'view',
    'announcements' => 'view',
    'memos' => 'view',
    'payroll' => 'view',
    'government_contributions' => 'view',
    'government_loans' => 'view',
    'company_deductions' => 'view',
    'thirteenth_month' => 'view',
    'performance' => 'view',
    'assets' => 'view',
    'supplies' => 'view',
    'incidents' => 'view',
    'reports' => 'view',
    'settings' => 'view'
];

// FIXED: Get and validate page parameter with better sanitization
$page = 'dashboard'; // Default page
$subpage = null; // For sub-pages like attendance/clock

if (isset($_GET['page'])) {
    // Allow lowercase letters, underscores, and forward slashes
    $requested_page = preg_replace('/[^a-z_\/]/', '', strtolower(trim($_GET['page'])));

    // Prevent path traversal attempts
    if (strpos($requested_page, '..') !== false || strpos($requested_page, '//') !== false) {
        error_log('Security Warning: Path traversal attempt detected - ' . $_GET['page']);
        $requested_page = 'dashboard';
    }

    // Check if this is a sub-page request (e.g., attendance/clock)
    if (strpos($requested_page, '/') !== false) {
        $parts = explode('/', $requested_page);
        $main_module = $parts[0];
        $subpage = $parts[1] ?? null;

        // Validate main module against whitelist
        if (in_array($main_module, $allowed_pages)) {
            $page = $main_module;
        } else {
            error_log('Warning: Attempt to access non-whitelisted module: ' . $main_module);
            $page = 'dashboard';
            $subpage = null;
        }
    } else {
        // Validate against whitelist for simple pages
        if (in_array($requested_page, $allowed_pages)) {
            $page = $requested_page;
        } else {
            // Log invalid page access attempt
            error_log('Warning: Attempt to access non-whitelisted page: ' . $requested_page);
            $page = 'dashboard';
        }
    }
}

// FIXED: Check user permissions for the requested module
if (isset($module_permissions[$page])) {
    $required_permission = $module_permissions[$page];

    // Check if user has required permission
    if (!hasPermission($page, $required_permission)) {
        // Log unauthorized access attempt
        $user_id = getCurrentUserId();
        if ($user_id) {
            logActivity($user_id, 'VIEW', $page, null, null, null, 'Unauthorized access attempt');
        }

        // Set error message and redirect to dashboard
        setFlashMessage('Access denied. You do not have permission to access this page.', 'error');
        header("Location: " . BASE_URL . "/index.php?page=dashboard");
        ob_end_flush();
        exit;
    }
}

// Build safe page path
if ($subpage) {
    // For sub-pages: /modules/{module}/{subpage}.php
    $page_path = __DIR__ . '/modules/' . $page . '/' . $subpage . '.php';
} else {
    // For main pages: /modules/{page}/{page}.php
    $page_path = __DIR__ . '/modules/' . $page . '/' . $page . '.php';
}

// Verify file exists and is readable
if (!file_exists($page_path) || !is_readable($page_path)) {
    error_log('Error: Module file not found or not readable - ' . $page_path);
    $page = 'dashboard';
    $page_path = __DIR__ . '/modules/dashboard/dashboard.php';
    
    // Final check - if dashboard also doesn't exist, show error
    if (!file_exists($page_path)) {
        ob_end_clean(); // Clear buffer
        http_response_code(500);
        
        if (DEBUG_MODE) {
            die('Critical Error: Dashboard module not found at: ' . $page_path);
        } else {
            die('System Error: Core module missing. Please contact system administrator.');
        }
    }
    
    setFlashMessage('The requested page could not be found. Redirected to dashboard.', 'warning');
}

// Module initialization - variables that modules can use
$module_name = $page;
$current_user = getCurrentUser();
$page_title = ucwords(str_replace('_', ' ', $page));

// Include header (contains <head> and opening <body>)
try {
    include __DIR__ . '/includes/header.php';
} catch (Exception $e) {
    ob_end_clean();
    error_log('Header Error: ' . $e->getMessage());
    die('Error loading page header. Please contact administrator.');
}
?>

  <!-- Sidebar -->
  <?php 
  try {
      include __DIR__ . '/includes/sidebar.php'; 
  } catch (Exception $e) {
      error_log('Sidebar Error: ' . $e->getMessage());
      echo '<!-- Sidebar failed to load -->';
  }
  ?>

  <!-- Sidebar Overlay for Mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <?php 
    try {
        include __DIR__ . '/includes/topnav.php'; 
    } catch (Exception $e) {
        error_log('Topnav Error: ' . $e->getMessage());
        echo '<!-- Top navigation failed to load -->';
    }
    ?>

    <div class="dashboard-content">
      <?php 
      // FIXED: Error boundary for module loading
      try {
          // Include the module page
          include $page_path;
          
      } catch (Exception $e) {
          // Log the error
          error_log('Module Error [' . $page . ']: ' . $e->getMessage());
          error_log('Stack trace: ' . $e->getTraceAsString());
          
          // Display user-friendly error
          echo '<div class="alert alert-danger" role="alert">';
          echo '<h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Error Loading Module</h4>';
          
          if (DEBUG_MODE) {
              echo '<p><strong>Module:</strong> ' . escapeHtml($page) . '</p>';
              echo '<p><strong>Error:</strong> ' . escapeHtml($e->getMessage()) . '</p>';
              echo '<p><strong>File:</strong> ' . escapeHtml($e->getFile()) . ':' . $e->getLine() . '</p>';
              echo '<details><summary>Stack Trace</summary><pre>' . escapeHtml($e->getTraceAsString()) . '</pre></details>';
          } else {
              echo '<p>An error occurred while loading this page. The system administrator has been notified.</p>';
              echo '<p><a href="' . BASE_URL . '/index.php?page=dashboard" class="btn btn-primary">Return to Dashboard</a></p>';
          }
          
          echo '</div>';
          
          // Log to activity log if user is logged in
          $user_id = getCurrentUserId();
          if ($user_id) {
              logActivity($user_id, 'VIEW', $page, null, null, null, 'Module error: ' . $e->getMessage());
          }
      }
      ?>
    </div>
  </main>

  <?php 
  try {
      include __DIR__ . '/includes/footer.php'; 
  } catch (Exception $e) {
      error_log('Footer Error: ' . $e->getMessage());
      echo '<!-- Footer failed to load -->';
  }
  ?>

</body>
</html>

<?php
// FIXED: Better output buffer handling with error checking
try {
    ob_end_flush();
} catch (Exception $e) {
    // If flush fails, clean the buffer and log error
    ob_end_clean();
    error_log('Output Buffer Error: ' . $e->getMessage());
}