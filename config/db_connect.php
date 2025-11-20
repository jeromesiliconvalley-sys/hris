<?php
/**
 * Database Connection Configuration
 * 
 * This file handles:
 * - Environment variable loading from .env file
 * - Database connection management (singleton pattern)
 * - System-wide constants and configurations
 * - Timezone and error reporting configuration
 * - Security utilities (CSRF, sanitization, activity logging)
 * 
 * @package HRIS
 * @version 2.1
 * @author HRIS Development Team
 */

// Prevent direct access to this configuration file
defined('HRIS_ACCESS') or define('HRIS_ACCESS', true);

/**
 * =========================================================================
 * ENVIRONMENT CONFIGURATION LOADING
 * =========================================================================
 * Load environment variables from .env file in the root directory.
 * The .env file should be located one level up from the config directory.
 * Path structure: hris/config/db_connect.php -> hris/.env
 */

$env_file = __DIR__ . '/../.env';

// Check if .env file exists
if (!file_exists($env_file)) {
    die('Error: .env file not found at ' . $env_file . '. Please create it from .env.example');
}

// Custom .env parser (more robust than parse_ini_file)
// This avoids errors with special characters and BOM issues
$env = [];
$lines = @file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines !== false) {
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Remove BOM if present
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes from value
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }
    }
}

// Validate .env file was parsed successfully
if (empty($env)) {
    error_log('Error: Unable to parse .env file at ' . $env_file);
    die('Error: Unable to load .env file. Please check the file syntax.');
}

/**
 * =========================================================================
 * DATABASE CONFIGURATION CONSTANTS
 * =========================================================================
 * These constants define the database connection parameters.
 * Values are loaded from .env file with fallback defaults.
 */

define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_PORT', $env['DB_PORT'] ?? '3306');
define('DB_USERNAME', $env['DB_USERNAME'] ?? '');
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? '');
define('DB_CHARSET', $env['DB_CHARSET'] ?? 'utf8mb4');
define('DB_COLLATION', $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci');

/**
 * =========================================================================
 * SYSTEM CONFIGURATION CONSTANTS
 * =========================================================================
 * General system settings including branding and URL configuration.
 */

// Detect BASE_URL from the current request as a resilient fallback for misconfigured environments
$detected_base_url = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $detected_base_url = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . ($path === '.' ? '' : $path), '/');
}

$env_base_url = rtrim($env['BASE_URL'] ?? '', '/');

// Prefer the .env BASE_URL when it matches the current host; otherwise fall back to the detected URL
if ($env_base_url && !empty($_SERVER['HTTP_HOST'])) {
    $env_host = parse_url($env_base_url, PHP_URL_HOST);
    if ($env_host && strcasecmp($env_host, $_SERVER['HTTP_HOST']) !== 0 && $detected_base_url) {
        $env_base_url = $detected_base_url;
    }
} elseif (!$env_base_url && $detected_base_url) {
    $env_base_url = $detected_base_url;
}

define('SYSTEM_NAME', $env['SYSTEM_NAME'] ?? 'HRIS Pro');
define('COMPANY_NAME', $env['COMPANY_NAME'] ?? 'Your Company');
define('BASE_URL', $env_base_url);
define('TIMEZONE', $env['TIMEZONE'] ?? 'Asia/Manila');

/**
 * =========================================================================
 * SESSION CONFIGURATION CONSTANTS
 * =========================================================================
 * Session lifetime and timeout settings for user authentication.
 * 
 * - SESSION_LIFETIME: Total session lifetime in seconds (default: 1 hour)
 * - SESSION_TIMEOUT: Inactivity timeout in seconds (default: 15 minutes)
 * - SESSION_REGENERATE_INTERVAL: Session ID regeneration interval (default: 30 minutes)
 */

define('SESSION_LIFETIME', (int)($env['SESSION_LIFETIME'] ?? 3600));
define('SESSION_TIMEOUT', (int)($env['SESSION_TIMEOUT'] ?? 900));
define('SESSION_REGENERATE_INTERVAL', (int)($env['SESSION_REGENERATE_INTERVAL'] ?? 1800));

/**
 * =========================================================================
 * SECURITY CONFIGURATION CONSTANTS
 * =========================================================================
 * Security-related settings for debugging and login attempt protection.
 * 
 * - DEBUG_MODE: Enable detailed error messages (disable in production)
 * - ENABLE_QUERY_LOG: Log all SQL queries for debugging
 * - MAX_LOGIN_ATTEMPTS: Maximum failed login attempts before lockout
 * - LOGIN_LOCKOUT_TIME: Lockout duration in seconds (default: 15 minutes)
 */

define('DEBUG_MODE', filter_var($env['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('ENABLE_QUERY_LOG', filter_var($env['ENABLE_QUERY_LOG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('MAX_LOGIN_ATTEMPTS', (int)($env['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOGIN_LOCKOUT_TIME', (int)($env['LOGIN_LOCKOUT_TIME'] ?? 900));

/**
 * =========================================================================
 * FILE UPLOAD CONFIGURATION
 * =========================================================================
 * Settings for file upload functionality including size limits and allowed types.
 * 
 * - MAX_UPLOAD_SIZE: Maximum file size in bytes (default: 5MB)
 * - ALLOWED_EXTENSIONS: Comma-separated list of allowed file extensions
 */

define('MAX_UPLOAD_SIZE', (int)($env['MAX_UPLOAD_SIZE'] ?? 5242880)); // 5MB default
define('UPLOAD_MAX_SIZE', MAX_UPLOAD_SIZE); // Alias for compatibility
define('ALLOWED_EXTENSIONS', $env['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');
define('ALLOWED_FILE_TYPES', ALLOWED_EXTENSIONS); // Alias for compatibility

/**
 * =========================================================================
 * EMAIL CONFIGURATION (SMTP)
 * =========================================================================
 * SMTP settings for sending system emails (notifications, password resets, etc.)
 * 
 * - SMTP_SECURE: Use 'tls' (port 587) or 'ssl' (port 465)
 */

define('SMTP_HOST', $env['SMTP_HOST'] ?? 'localhost');
define('SMTP_PORT', (int)($env['SMTP_PORT'] ?? 587));
define('SMTP_SECURE', $env['SMTP_SECURE'] ?? 'tls');
define('SMTP_USERNAME', $env['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $env['SMTP_PASSWORD'] ?? '');
define('SMTP_FROM_EMAIL', $env['SMTP_FROM_EMAIL'] ?? 'noreply@example.com');
define('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? SYSTEM_NAME);

/**
 * =========================================================================
 * FACE RECOGNITION CONFIGURATION
 * =========================================================================
 * Settings for biometric face recognition feature.
 * 
 * - FACE_CONFIDENCE_THRESHOLD: Minimum confidence score (0.0-1.0) for face match
 */

define('FACE_RECOGNITION_ENABLED', filter_var($env['FACE_RECOGNITION_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('FACE_CONFIDENCE_THRESHOLD', (float)($env['FACE_CONFIDENCE_THRESHOLD'] ?? 0.6));

/**
 * =========================================================================
 * PAGINATION CONFIGURATION
 * =========================================================================
 * Default number of records to display per page in data tables.
 */

define('RECORDS_PER_PAGE', (int)($env['RECORDS_PER_PAGE'] ?? 25));

/**
 * =========================================================================
 * TIMEZONE CONFIGURATION
 * =========================================================================
 * Set PHP timezone to match the system timezone defined in .env
 */

date_default_timezone_set(TIMEZONE);

/**
 * =========================================================================
 * ERROR REPORTING CONFIGURATION
 * =========================================================================
 * Configure PHP error reporting based on DEBUG_MODE setting.
 * 
 * DEBUG_MODE = true:  Show all errors on screen (development)
 * DEBUG_MODE = false: Hide errors from screen, log to file (production)
 */

if (DEBUG_MODE) {
    // Development mode: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    // Production mode: Hide errors, log only
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

/**
 * =========================================================================
 * DATABASE CONNECTION FUNCTIONS
 * =========================================================================
 */

/**
 * Get Database Connection (Singleton Pattern)
 * 
 * Returns a single mysqli connection instance throughout the application lifecycle.
 * The connection is created on first call and reused on subsequent calls.
 * This pattern prevents multiple database connections and improves performance.
 * 
 * Features:
 * - Connection pooling (reuses existing connection if alive)
 * - Automatic charset configuration
 * - SQL mode configuration for strict data validation
 * - Timezone synchronization between PHP and MySQL
 * - Comprehensive error handling
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 * 
 * @example
 * $conn = getDbConnection();
 * $result = $conn->query("SELECT * FROM users");
 */
function getDbConnection() {
    static $conn = null;
    
    // Return existing connection if available and alive
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }
    
    try {
        // Create new mysqli connection
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
        
        // Check for connection errors
        if ($conn->connect_error) {
            throw new Exception('Connection failed: ' . $conn->connect_error);
        }
        
        // Set character set to ensure proper encoding
        if (!$conn->set_charset(DB_CHARSET)) {
            throw new Exception('Error setting charset: ' . $conn->error);
        }
        
        // Set SQL mode for strict data validation and consistency
        // Prevents invalid date values, division by zero, etc.
        $sql_mode = "SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";
        if (!$conn->query($sql_mode)) {
            // Non-fatal error, log but continue
            error_log('Warning: Could not set SQL mode: ' . $conn->error);
        }
        
        // Synchronize MySQL timezone with PHP timezone
        // Ensures consistent datetime handling across application
        $timezone_query = "SET time_zone = '" . date('P') . "'";
        if (!$conn->query($timezone_query)) {
            error_log('Warning: Could not set MySQL timezone: ' . $conn->error);
        }
        
        // Store connection in global scope for backward compatibility
        $GLOBALS['conn'] = $conn;
        
        return $conn;
        
    } catch (Exception $e) {
        // Log the error to file
        error_log('Database Connection Error: ' . $e->getMessage());
        
        // Display appropriate error message based on debug mode
        if (DEBUG_MODE) {
            die('Database Connection Failed: ' . $e->getMessage());
        } else {
            die('Database Connection Failed. Please contact the system administrator.');
        }
    }
}

/**
 * Close Database Connection
 * 
 * Explicitly closes the database connection and cleans up resources.
 * This function works with both the singleton pattern (static variable)
 * and the global connection variable (for backward compatibility).
 * 
 * When to use:
 * - At the end of long-running scripts
 * - Before forking processes
 * - When you need to force a fresh connection
 * 
 * Note: The connection will be automatically recreated on the next 
 * call to getDbConnection() if needed.
 * 
 * @return void
 * 
 * @example
 * closeDbConnection();
 * // Connection is now closed
 * $conn = getDbConnection(); // Creates a new connection
 */
function closeDbConnection() {
    // Close the global connection (backward compatibility)
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
        $GLOBALS['conn'] = null;
    }
    
    // Note: The static $conn in getDbConnection() will be reset to null
    // on the next script execution. For manual reset within the same script,
    // you would need to call getDbConnection() which will check ping()
    // and recreate if needed.
}

/**
 * =========================================================================
 * QUERY EXECUTION FUNCTIONS
 * =========================================================================
 */

/**
 * Execute a Prepared Statement Safely
 * 
 * Helper function for executing secure parameterized SQL queries.
 * Uses prepared statements to prevent SQL injection attacks.
 * 
 * Parameter Types:
 * - i: integer
 * - d: double/float
 * - s: string
 * - b: blob (binary data)
 * 
 * @param string $query SQL query with placeholders (?)
 * @param string $types Parameter types string (e.g., "ssi" for string, string, integer)
 * @param array $params Array of parameter values
 * @return mysqli_result|int|bool Query result for SELECT, affected rows/insert_id for INSERT/UPDATE/DELETE, false on error
 * 
 * @example
 * // SELECT query
 * $result = executeQuery(
 *     "SELECT * FROM users WHERE email = ? AND status = ?", 
 *     "ss", 
 *     ['user@example.com', 'active']
 * );
 * 
 * @example
 * // INSERT query
 * $insert_id = executeQuery(
 *     "INSERT INTO users (name, email, age) VALUES (?, ?, ?)", 
 *     "ssi", 
 *     ['John Doe', 'john@example.com', 30]
 * );
 * 
 * @example
 * // UPDATE query
 * $affected = executeQuery(
 *     "UPDATE users SET status = ? WHERE id = ?", 
 *     "si", 
 *     ['inactive', 123]
 * );
 */
function executeQuery($query, $types = '', $params = []) {
    $conn = getDbConnection();
    
    // Log query in debug mode if query logging is enabled
    if (ENABLE_QUERY_LOG) {
        error_log('SQL Query: ' . $query);
        error_log('Params: ' . json_encode($params));
    }
    
    // Prepare the statement
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error . ' | Query: ' . $query);
        return false;
    }
    
    // Bind parameters if provided
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute the statement
    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error . ' | Query: ' . $query);
        $stmt->close();
        return false;
    }
    
    // Handle SELECT queries - return result set
    if (stripos($query, 'SELECT') === 0) {
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
    
    // Handle INSERT/UPDATE/DELETE queries
    $affected_rows = $stmt->affected_rows;
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    // Return insert_id for INSERT queries, otherwise return affected_rows
    return (stripos($query, 'INSERT') === 0 && $insert_id > 0) ? $insert_id : $affected_rows;
}

/**
 * =========================================================================
 * SECURITY & SANITIZATION FUNCTIONS
 * =========================================================================
 */

/**
 * Sanitize Input Data
 * 
 * Performs basic input sanitization to prevent XSS attacks.
 * This function should be used on all user input before processing.
 * 
 * Note: This does NOT prevent SQL injection - use prepared statements for that.
 * 
 * Processing steps:
 * 1. Trim whitespace from beginning and end
 * 2. Remove backslashes (stripslashes)
 * 3. Convert special characters to HTML entities
 * 
 * @param mixed $data Input data to sanitize (can be string or array)
 * @return mixed Sanitized data in the same format as input
 * 
 * @example
 * $clean_name = sanitizeInput($_POST['name']);
 * $clean_array = sanitizeInput($_POST); // Works with arrays too
 */
function sanitizeInput($data) {
    // Handle arrays recursively
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    // Sanitize string data
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Escape Output for HTML Display
 * 
 * Prevents XSS (Cross-Site Scripting) attacks when displaying user-generated content.
 * Use this function whenever outputting data that came from user input or database.
 * 
 * @param string $string String to escape
 * @return string Escaped string safe for HTML output
 * 
 * @example
 * echo "<p>" . escapeHtml($user_comment) . "</p>";
 */
function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * =========================================================================
 * CSRF PROTECTION FUNCTIONS
 * =========================================================================
 */

/**
 * Generate CSRF Token
 * 
 * Creates a secure Cross-Site Request Forgery (CSRF) token for form submissions.
 * The token is stored in the session and should be included in all forms.
 * 
 * How to use:
 * 1. Call this function to generate/get the token
 * 2. Include the token in your form as a hidden field
 * 3. Verify the token on form submission using verifyCsrfToken()
 * 
 * @return string CSRF token (64 character hexadecimal string)
 * 
 * @example
 * <form method="POST">
 *     <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
 *     <!-- other form fields -->
 * </form>
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 * 
 * Validates the CSRF token from a form submission.
 * This prevents Cross-Site Request Forgery attacks.
 * 
 * @param string $token Token from the form submission to verify
 * @return bool True if token is valid, false otherwise
 * 
 * @example
 * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *     if (!verifyCsrfToken($_POST['csrf_token'])) {
 *         die('Invalid CSRF token');
 *     }
 *     // Process form...
 * }
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * =========================================================================
 * ACTIVITY LOGGING FUNCTIONS
 * =========================================================================
 */

/**
 * Log User Activity
 * 
 * Records user actions to the activity_logs table for audit trail purposes.
 * Captures who did what, when, where (IP), and with what device.
 * 
 * Activity Types:
 * - CREATE: New record created
 * - UPDATE: Existing record modified
 * - DELETE: Record deleted
 * - VIEW: Record viewed (optional logging)
 * - LOGIN: User logged in
 * - LOGOUT: User logged out
 * 
 * @param int $user_id ID of the user performing the action
 * @param string $action Action type (CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT)
 * @param string $table_name Database table affected by the action
 * @param int|null $record_id ID of the specific record affected (optional)
 * @param array|null $old_values Previous values before update (optional, for UPDATE actions)
 * @param array|null $new_values New values after create/update (optional)
 * @param string|null $description Additional description or notes (optional)
 * @return bool True if logged successfully, false on error
 * 
 * @example
 * // Log a new user creation
 * logActivity(
 *     $_SESSION['user_id'], 
 *     'CREATE', 
 *     'users', 
 *     $new_user_id, 
 *     null, 
 *     ['name' => 'John Doe', 'email' => 'john@example.com'],
 *     'New employee account created'
 * );
 * 
 * @example
 * // Log an update with before/after values
 * logActivity(
 *     $_SESSION['user_id'], 
 *     'UPDATE', 
 *     'employees', 
 *     $employee_id, 
 *     ['salary' => 50000], 
 *     ['salary' => 55000],
 *     'Salary increase approved'
 * );
 */
function logActivity($user_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null, $description = null) {
    try {
        // Capture request information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Parse user agent to extract device information
        $device_info = json_encode([
            'browser' => getBrowser($user_agent),
            'os' => getOS($user_agent),
            'device' => getDevice($user_agent)
        ]);
        
        // Insert activity log record
        $query = "INSERT INTO activity_logs 
                  (user_id, action, table_name, record_id, old_values, new_values, description, ip_address, device_info, user_agent, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $types = 'isssisssss';
        $params = [
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $description,
            $ip_address,
            $device_info,
            $user_agent
        ];
        
        return executeQuery($query, $types, $params) !== false;
        
    } catch (Exception $e) {
        error_log('Activity Log Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * =========================================================================
 * USER AGENT PARSING FUNCTIONS
 * =========================================================================
 * These functions parse the HTTP User Agent string to extract browser,
 * operating system, and device type information.
 */

/**
 * Get Browser from User Agent
 * 
 * Extracts the browser name from the User Agent string.
 * 
 * @param string $user_agent User agent string from $_SERVER['HTTP_USER_AGENT']
 * @return string Browser name (Chrome, Firefox, Safari, Edge, Opera, or Unknown)
 */
function getBrowser($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    return 'Unknown';
}

/**
 * Get Operating System from User Agent
 * 
 * Extracts the operating system name from the User Agent string.
 * 
 * @param string $user_agent User agent string from $_SERVER['HTTP_USER_AGENT']
 * @return string OS name (Windows, MacOS, Linux, Android, iOS, or Unknown)
 */
function getOS($user_agent) {
    if (strpos($user_agent, 'Windows') !== false) return 'Windows';
    if (strpos($user_agent, 'Mac') !== false) return 'MacOS';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'iOS') !== false) return 'iOS';
    return 'Unknown';
}

/**
 * Get Device Type from User Agent
 * 
 * Determines the device type from the User Agent string.
 * 
 * @param string $user_agent User agent string from $_SERVER['HTTP_USER_AGENT']
 * @return string Device type (Mobile, Tablet, or Desktop)
 */
function getDevice($user_agent) {
    if (strpos($user_agent, 'Mobile') !== false) return 'Mobile';
    if (strpos($user_agent, 'Tablet') !== false) return 'Tablet';
    return 'Desktop';
}

/**
 * =========================================================================
 * INITIALIZATION & SHUTDOWN
 * =========================================================================
 */

// Initialize global connection variable (for backward compatibility)
// New code should use getDbConnection() function instead of accessing $conn directly
$conn = getDbConnection();

// Register shutdown function to automatically close connection when script ends
register_shutdown_function('closeDbConnection');