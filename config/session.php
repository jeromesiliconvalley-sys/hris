<?php
/**
 * Session Management and Authentication
 * 
 * This file handles:
 * - Session initialization and security configuration
 * - User authentication verification
 * - Session timeout and inactivity tracking
 * - Session hijacking prevention (User Agent fingerprinting)
 * - Automatic session regeneration
 * - CSRF token generation
 * - Public page access control
 * 
 * Security Features:
 * - HttpOnly cookies (prevents JavaScript access to session cookies)
 * - Secure cookies for HTTPS connections
 * - SameSite=Lax (mitigates CSRF while allowing top-level navigation)
 * - Session regeneration (prevents session fixation attacks)
 * - User Agent fingerprinting (detects session hijacking)
 * - Inactivity timeout (automatic logout after idle period)
 * 
 * @package HRIS
 * @version 2.1
 * @author HRIS Development Team
 */

// Load database configuration and system constants
require_once __DIR__ . '/db_connect.php';

/**
 * =========================================================================
 * SESSION SECURITY CONFIGURATION
 * =========================================================================
 * Configure session security settings BEFORE starting the session.
 * These settings enhance session security and prevent common attacks.
 */

// HttpOnly: Prevents JavaScript from accessing session cookies (XSS protection)
ini_set('session.cookie_httponly', '1');

// Use only cookies: Prevents session ID from being passed in URLs
ini_set('session.use_only_cookies', '1');

// SameSite: Mitigates CSRF while still permitting top-level navigations (Lax keeps logins working)
// Options: 'Strict', 'Lax', or 'None'
ini_set('session.cookie_samesite', 'Lax');

// Secure cookies: Only send cookies over HTTPS (if site uses HTTPS)
if (defined('BASE_URL') && strpos(BASE_URL, 'https://') === 0) {
    ini_set('session.cookie_secure', '1');
}

// Set custom session name (makes it harder to identify the application)
session_name('HRIS_SESSION');

// Set session lifetime from configuration
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);

// Extract path from BASE_URL for session cookie
$url_path = parse_url(BASE_URL, PHP_URL_PATH);
$cookie_path = $url_path ? rtrim($url_path, '/') . '/' : '/';

// Determine if connection is secure
$is_secure = (defined('BASE_URL') && strpos(BASE_URL, 'https://') === 0);

// Set session cookie parameters for proper cross-page persistence
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => $cookie_path,  // Dynamically set from BASE_URL
    'domain' => '',  // Empty = current domain
    'secure' => $is_secure,  // HTTPS only if BASE_URL uses HTTPS
    'httponly' => true,  // Prevent JavaScript access
    'samesite' => 'Lax'  // CSRF protection while allowing top-level cross-site redirects (Strict would block these)
]);

/**
 * =========================================================================
 * SESSION INITIALIZATION
 * =========================================================================
 * Start the session if not already started.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * =========================================================================
 * CSRF TOKEN GENERATION
 * =========================================================================
 * Generate CSRF token using the function from db_connect.php.
 * This token must be included in all forms to prevent CSRF attacks.
 */

generateCsrfToken();

/**
 * =========================================================================
 * PUBLIC PAGES CONFIGURATION
 * =========================================================================
 * Define pages that don't require authentication.
 * Users can access these pages without being logged in.
 */

$public_pages = [
    'login.php',
    'forgot_password.php',
    'reset_password.php',
    'register.php'
];

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

/**
 * =========================================================================
 * REDIRECT LOOP PROTECTION
 * =========================================================================
 * Prevent infinite redirect loops by tracking redirect count
 */
if (!isset($_SESSION['redirect_count'])) {
    $_SESSION['redirect_count'] = 0;
}

// Reset redirect count if user successfully loads a page
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $_SESSION['redirect_count'] = 0;
}

/**
 * =========================================================================
 * SESSION VALIDATION & SECURITY CHECKS
 * =========================================================================
 * For logged-in users, perform security checks and session management.
 */

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    /**
     * -----------------------------------------------------------------------
     * INACTIVITY TIMEOUT CHECK
     * -----------------------------------------------------------------------
     * Automatically log out users after a period of inactivity.
     * This enhances security by closing abandoned sessions.
     */
    
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // Check if user has been inactive longer than allowed timeout
        if ($inactive_time > SESSION_TIMEOUT) {
            // Store user_id for activity logging before destroying session
            $user_id = $_SESSION['user_id'] ?? null;
            
            // Log the timeout activity BEFORE destroying session
            if ($user_id) {
                logActivity(
                    $user_id, 
                    'LOGOUT', 
                    'users', 
                    $user_id, 
                    null, 
                    null, 
                    'Session timeout due to inactivity (' . round($inactive_time / 60) . ' minutes)'
                );
            }
            
            // Destroy the session
            session_unset();
            session_destroy();
            
            // Redirect to login with timeout flag
            $redirect_url = BASE_URL . "/modules/auth/login.php?timeout=1";
            
            // Check if headers already sent (for debugging)
            if (headers_sent($file, $line)) {
                error_log("Headers already sent in $file on line $line. Cannot redirect.");
                echo "<script>window.location.href='$redirect_url';</script>";
            } else {
                header("Location: $redirect_url");
            }
            exit;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    /**
     * -----------------------------------------------------------------------
     * SESSION REGENERATION (Session Fixation Prevention)
     * -----------------------------------------------------------------------
     * Periodically regenerate session ID to prevent session fixation attacks.
     * Session fixation: Attacker tricks user into using a known session ID.
     */
    
    if (!isset($_SESSION['created'])) {
        // First time - set creation timestamp
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > SESSION_REGENERATE_INTERVAL) {
        // Time to regenerate - create new session ID
        session_regenerate_id(true); // true = delete old session file
        $_SESSION['created'] = time();
        
        // Optional: Log session regeneration for audit trail
        if (ENABLE_QUERY_LOG) {
            error_log('Session regenerated for user_id: ' . ($_SESSION['user_id'] ?? 'unknown'));
        }
    }
    
    /**
     * -----------------------------------------------------------------------
     * USER AGENT FINGERPRINTING (Session Hijacking Prevention)
     * -----------------------------------------------------------------------
     * Detect session hijacking by checking if the User Agent changed.
     * 
     * How it works:
     * - On login: Store the browser's User Agent string
     * - On each request: Verify User Agent matches the stored one
     * - If different: Possible session theft → Force logout
     * 
     * What User Agent contains:
     * - Browser type and version (Chrome, Firefox, Safari, etc.)
     * - Operating system (Windows, Mac, Linux, etc.)
     * - Device type (Desktop, Mobile, Tablet)
     * 
     * Why this is safe:
     * - User Agent doesn't change when switching WiFi/Mobile data
     * - More flexible than IP checking (no mobile user issues)
     * - Catches most session hijacking attempts
     * 
     * Limitations:
     * - Can be bypassed if attacker knows the User Agent
     * - May trigger on browser updates (rare)
     */
    
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (!isset($_SESSION['user_agent'])) {
        // First time - store User Agent fingerprint
        $_SESSION['user_agent'] = $current_user_agent;
    } else {
        // Check if User Agent changed (possible session hijacking)
        if ($_SESSION['user_agent'] !== $current_user_agent) {
            // User Agent mismatch - potential security threat
            $user_id = $_SESSION['user_id'] ?? null;
            
            // Log security event BEFORE destroying session
            if ($user_id) {
                logActivity(
                    $user_id, 
                    'LOGOUT', 
                    'users', 
                    $user_id, 
                    null, 
                    null, 
                    'Session terminated - User Agent mismatch (possible session hijacking)'
                );
            }
            
            // Destroy the compromised session
            session_unset();
            session_destroy();
            
            // Redirect to login with security alert
            $redirect_url = BASE_URL . "/modules/auth/login.php?security=1";
            
            // Check if headers already sent (for debugging)
            if (headers_sent($file, $line)) {
                error_log("Headers already sent in $file on line $line. Cannot redirect.");
                echo "<script>window.location.href='$redirect_url';</script>";
            } else {
                header("Location: $redirect_url");
            }
            exit;
        }
    }
    
    /**
     * -----------------------------------------------------------------------
     * SESSION DATA VALIDATION
     * -----------------------------------------------------------------------
     * Verify that required session data exists and is valid.
     */
    
    // Ensure critical session data exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        // Invalid session state - force logout
        error_log('Invalid session state detected - missing user_id or username');
        
        session_unset();
        session_destroy();
        
        $redirect_url = BASE_URL . "/modules/auth/login.php?error=invalid_session";
        
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line. Cannot redirect.");
            echo "<script>window.location.href='$redirect_url';</script>";
        } else {
            header("Location: $redirect_url");
        }
        exit;
    }
    
} else {
    /**
     * -----------------------------------------------------------------------
     * AUTHENTICATION REQUIRED
     * -----------------------------------------------------------------------
     * User is not logged in - redirect to login page unless on public page.
     */
    
    if (!in_array($current_page, $public_pages)) {
        // Not a public page - require authentication

        // Redirect loop protection
        $_SESSION['redirect_count'] = ($_SESSION['redirect_count'] ?? 0) + 1;
        if ($_SESSION['redirect_count'] > 3) {
            $debug_info = [
                'redirect_count' => $_SESSION['redirect_count'],
                'current_page' => $current_page,
                'logged_in' => $_SESSION['logged_in'] ?? 'NOT SET',
                'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
                'username' => $_SESSION['username'] ?? 'NOT SET',
                'role_id' => $_SESSION['role_id'] ?? 'NOT SET',
                'user_agent_set' => isset($_SESSION['user_agent']) ? 'YES' : 'NO',
                'last_activity' => isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'NOT SET',
                'session_id' => $_SESSION['session_id'] ?? 'NOT SET',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
                'php_self' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
                'public_pages' => $public_pages
            ];

            error_log("REDIRECT LOOP DETECTED: " . json_encode($debug_info));

            // Force complete session reset
            session_unset();
            session_destroy();
            session_start();

            echo "<!DOCTYPE html><html><head><title>Redirect Loop Detected</title><style>
            body{font-family:monospace;padding:20px;background:#f5f5f5}
            .error{background:#fff;border:2px solid #d00;padding:20px;border-radius:5px;max-width:800px;margin:20px auto}
            h1{color:#d00;margin-top:0}
            pre{background:#f9f9f9;padding:10px;overflow:auto;border:1px solid #ddd}
            .solution{background:#ffe;padding:10px;border-left:4px solid #fc0;margin:10px 0}
            </style></head><body><div class='error'>
            <h1>🔄 Redirect Loop Detected</h1>
            <p><strong>Too many redirects occurred (" . $_SESSION['redirect_count'] . " redirects)</strong></p>
            <h2>Debug Information:</h2>
            <pre>" . print_r($debug_info, true) . "</pre>
            <div class='solution'>
            <h3>Solutions:</h3>
            <ol>
            <li><strong>Clear your browser cookies</strong> for this site and try again</li>
            <li>If problem persists, check if session variables are being set correctly during login</li>
            <li>Verify that session.php is not being included multiple times</li>
            <li>Check if the current page ('{$current_page}') should be in the public_pages array</li>
            </ol>
            </div>
            <p><a href='" . BASE_URL . "/modules/auth/login.php' style='color:#00d'>← Back to Login</a></p>
            </div></body></html>";
            exit;
        }

        // Store the requested page to redirect after login (optional feature)
        if (!isset($_SESSION['redirect_after_login'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        }

        $redirect_url = BASE_URL . "/modules/auth/login.php";

        // Check if headers already sent (for debugging)
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line. Cannot redirect.");
            echo "<script>window.location.href='$redirect_url';</script>";
        } else {
            header("Location: $redirect_url");
        }
        exit;
    }
}

/**
 * =========================================================================
 * HELPER FUNCTIONS
 * =========================================================================
 */

/**
 * Check if User is Logged In
 * 
 * Convenience function to check authentication status.
 * 
 * @return bool True if user is logged in, false otherwise
 * 
 * @example
 * if (isLoggedIn()) {
 *     echo "Welcome, " . $_SESSION['username'];
 * }
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get Current User ID
 * 
 * Safely retrieve the current user's ID from session.
 * 
 * @return int|null User ID if logged in, null otherwise
 * 
 * @example
 * $user_id = getCurrentUserId();
 * if ($user_id) {
 *     // User is logged in
 * }
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get Current Username
 * 
 * Safely retrieve the current username from session.
 * 
 * @return string|null Username if logged in, null otherwise
 * 
 * @example
 * $username = getCurrentUsername();
 * echo "Logged in as: " . escapeHtml($username);
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get User Role
 *
 * Retrieve the current user's role from session.
 *
 * @return string|null User role if logged in, null otherwise
 *
 * @example
 * if (getUserRole() === 'admin') {
 *     // Show admin features
 * }
 */
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get Current User
 *
 * Retrieve complete user information from the database for the current logged-in user.
 *
 * @return array|null User data array if logged in, null otherwise
 *
 * @example
 * $user = getCurrentUser();
 * if ($user) {
 *     echo "Welcome, " . escapeHtml($user['first_name']);
 * }
 */
function getCurrentUser() {
    global $conn;

    $user_id = getCurrentUserId();

    if (!$user_id) {
        return null;
    }

    // Check if we have cached user data in session
    if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    // Fetch user data from database
    $stmt = $conn->prepare("
        SELECT u.*, r.role_name, e.first_name, e.last_name, e.email
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN employees e ON u.employee_id = e.id
        WHERE u.id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Cache user data in session for performance
        if ($user) {
            $_SESSION['user_data'] = $user;
        }

        return $user;
    }

    return null;
}

/**
 * Check if User Has Role
 * 
 * Check if current user has a specific role or one of multiple roles.
 * 
 * @param string|array $roles Role name or array of role names to check
 * @return bool True if user has the role, false otherwise
 * 
 * @example
 * if (hasRole('admin')) {
 *     // Admin only feature
 * }
 * 
 * @example
 * if (hasRole(['admin', 'manager'])) {
 *     // Admin or manager feature
 * }
 */
function hasRole($roles) {
    $user_role = getUserRole();
    
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    
    return $user_role === $roles;
}

/**
 * Require Role
 * 
 * Ensure user has required role, otherwise redirect to access denied page.
 * 
 * @param string|array $roles Required role(s)
 * @param string $redirect_url URL to redirect if access denied (optional)
 * @return void
 * 
 * @example
 * requireRole('admin'); // Only admins can proceed
 * requireRole(['admin', 'manager']); // Admins or managers can proceed
 */
function requireRole($roles, $redirect_url = null) {
    if (!hasRole($roles)) {
        // User doesn't have required role
        $user_id = getCurrentUserId();
        
        // Log unauthorized access attempt
        if ($user_id) {
            $required_roles = is_array($roles) ? implode(', ', $roles) : $roles;
            logActivity(
                $user_id,
                'ACCESS_DENIED',
                'system',
                null,
                null,
                null,
                'Attempted to access page requiring role: ' . $required_roles
            );
        }
        
        // Redirect to access denied or custom page
        if ($redirect_url === null) {
            $redirect_url = BASE_URL . "/modules/auth/access_denied.php";
        }
        
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line. Cannot redirect.");
            echo "<script>window.location.href='$redirect_url';</script>";
        } else {
            header("Location: $redirect_url");
        }
        exit;
    }
}

/**
 * Logout User
 * 
 * Properly logout the current user and destroy their session.
 * 
 * @param string $reason Optional reason for logout (for activity logging)
 * @return void
 * 
 * @example
 * logoutUser('User clicked logout button');
 */
function logoutUser($reason = 'User initiated logout') {
    $user_id = getCurrentUserId();
    
    // Log the logout activity
    if ($user_id) {
        logActivity(
            $user_id,
            'LOGOUT',
            'users',
            $user_id,
            null,
            null,
            $reason
        );
    }
    
    // Clear all session variables
    session_unset();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    $redirect_url = BASE_URL . "/modules/auth/login.php?logged_out=1";

    if (headers_sent($file, $line)) {
        error_log("Headers already sent in $file on line $line. Cannot redirect.");
        echo "<script>window.location.href='$redirect_url';</script>";
    } else {
        header("Location: $redirect_url");
    }
    exit;
}

/**
 * Check User Permission
 *
 * Checks if the current user has permission to perform an action on a module.
 *
 * @param string $module The module name (e.g., 'employees', 'users')
 * @param string $action The action to check (e.g., 'view', 'create', 'edit', 'delete')
 * @return bool True if user has permission, false otherwise
 *
 * @example
 * if (hasPermission('employees', 'create')) {
 *     // Show create button
 * }
 */
function hasPermission($module, $action) {
    global $conn;

    // Get current user's role
    $role_id = $_SESSION['role_id'] ?? null;

    if (!$role_id) {
        return false;
    }

    // Super admin (role_id = 1) has all permissions
    if ($role_id == 1) {
        return true;
    }

    // Map action to column name in role_permissions table
    $action_column_map = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'approve' => 'can_approve',
        'export' => 'can_export'
    ];

    // Get the column name for the action
    $column = $action_column_map[$action] ?? null;

    if (!$column) {
        // Unknown action, deny for security
        return false;
    }

    // Check role_permissions table for specific permission
    $stmt = $conn->prepare("
        SELECT $column
        FROM role_permissions
        WHERE role_id = ?
        AND module = ?
        AND is_deleted = 0
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("is", $role_id, $module);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        // Return true if the permission column is set to 1
        return isset($row[$column]) && $row[$column] == 1;
    }

    // If query fails, deny permission for security
    return false;
}

/**
 * Set Flash Message
 *
 * Stores a message in the session to be displayed on the next page load.
 * The message is automatically removed after being displayed once.
 *
 * @param string $message The message to display
 * @param string $type The message type: 'success', 'error', 'warning', 'info'
 *
 * @example
 * setFlashMessage('Employee added successfully!', 'success');
 * header('Location: employees.php');
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get Flash Message
 *
 * Retrieves and removes the flash message from session.
 *
 * @return array|null Array with 'message' and 'type' keys, or null if no message
 *
 * @example
 * $flash = getFlashMessage();
 * if ($flash) {
 *     echo "<div class='alert alert-{$flash['type']}'>{$flash['message']}</div>";
 * }
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}