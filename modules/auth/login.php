<?php
// Set session name and cookie parameters BEFORE starting session
session_name('HRIS_SESSION');
session_set_cookie_params([
    'lifetime' => 3600,  // 1 hour
    'path' => '/hris/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once '../../config/db_connect.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ../../index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success = 'You have been logged out successfully.';
}

if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Your session has expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $device_data = parseDevice($agent);
    $device_info = json_encode($device_data);

    $geo = getGeoLocation($ip);
    $latitude = $geo['lat'];
    $longitude = $geo['lon'];
    $location_desc = $geo['city'] . ', ' . $geo['country'];

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.password, u.role_id, u.status, u.failed_login_attempts, u.employee_id
            FROM users u
            WHERE u.username = ? AND u.is_deleted = 0 
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user['status'] === 'Locked') {
                $error = "Account is locked. Please contact administrator.";
                
                $stmt2 = $conn->prepare("
                    INSERT INTO security_audit_log 
                    (event_type, severity, user_id, description, ip_address, location_latitude, location_longitude, device_info, user_agent, created_at)
                    VALUES ('Failed Login', 'High', ?, 'Attempted login to locked account', ?, ?, ?, ?, ?, NOW())
                ");
                $stmt2->bind_param("isddss", $user['id'], $ip, $latitude, $longitude, $device_info, $agent);
                $stmt2->execute();
                $stmt2->close();
            }
            elseif ($user['status'] === 'Inactive') {
                $error = "Account is inactive. Please contact administrator.";
                
                $stmt2 = $conn->prepare("
                    INSERT INTO security_audit_log 
                    (event_type, severity, user_id, description, ip_address, location_latitude, location_longitude, device_info, user_agent, created_at)
                    VALUES ('Failed Login', 'Medium', ?, 'Attempted login to inactive account', ?, ?, ?, ?, ?, NOW())
                ");
                $stmt2->bind_param("isddss", $user['id'], $ip, $latitude, $longitude, $device_info, $agent);
                $stmt2->execute();
                $stmt2->close();
            }
            elseif (password_verify($password, $user['password'])) {
                $stmt2 = $conn->prepare("
                    UPDATE users 
                    SET last_login = NOW(), 
                        last_login_ip = ?, 
                        last_login_latitude = ?,
                        last_login_longitude = ?,
                        last_login_device = ?, 
                        failed_login_attempts = 0 
                    WHERE id = ?
                ");
                $stmt2->bind_param("sddsi", $ip, $latitude, $longitude, $device_info, $user['id']);
                $stmt2->execute();
                $stmt2->close();

                $session_uuid = bin2hex(random_bytes(16));
                
                $stmt2 = $conn->prepare("
                    INSERT INTO user_sessions 
                    (id, user_id, ip_address, location_latitude, location_longitude, device_info, user_agent, login_at, last_activity)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt2->bind_param("sisddss", $session_uuid, $user['id'], $ip, $latitude, $longitude, $device_info, $agent);
                $stmt2->execute();
                $stmt2->close();

                $stmt2 = $conn->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, table_name, description, ip_address, location_latitude, location_longitude, device_info, user_agent, created_at)
                    VALUES (?, 'LOGIN', 'users', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $desc = "User logged in from {$location_desc}";
                $stmt2->bind_param("issddss", $user['id'], $desc, $ip, $latitude, $longitude, $device_info, $agent);
                $stmt2->execute();
                $stmt2->close();

                session_regenerate_id(true);
                $_SESSION['session_id'] = $session_uuid;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $_SESSION['redirect_count'] = 0;

                header("Location: ../../index.php");
                exit;
            } 
            else {
                $error = "Incorrect password.";
                
                $stmt2 = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?");
                $stmt2->bind_param("i", $user['id']);
                $stmt2->execute();
                $stmt2->close();

                if ($user['failed_login_attempts'] + 1 >= 5) {
                    $stmt2 = $conn->prepare("UPDATE users SET status = 'Locked' WHERE id = ?");
                    $stmt2->bind_param("i", $user['id']);
                    $stmt2->execute();
                    $stmt2->close();
                    $error = "Account locked due to multiple failed login attempts.";
                }

                $stmt2 = $conn->prepare("
                    INSERT INTO security_audit_log 
                    (event_type, severity, user_id, description, ip_address, location_latitude, location_longitude, device_info, user_agent, created_at)
                    VALUES ('Failed Login', 'Medium', ?, 'Incorrect password attempt', ?, ?, ?, ?, ?, NOW())
                ");
                $stmt2->bind_param("isddss", $user['id'], $ip, $latitude, $longitude, $device_info, $agent);
                $stmt2->execute();
                $stmt2->close();
            }
        } 
        else {
            $stmt->close();
            $error = "Invalid username or password.";
            
            $stmt2 = $conn->prepare("
                INSERT INTO security_audit_log 
                (event_type, severity, user_id, description, ip_address, location_latitude, location_longitude, device_info, user_agent, created_at)
                VALUES ('Failed Login', 'Medium', NULL, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $desc = "Login attempt with unknown username: {$username}";
            $stmt2->bind_param("ssddss", $desc, $ip, $latitude, $longitude, $device_info, $agent);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

function parseDevice($agent) {
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';

    if (preg_match('/Windows/i', $agent)) $os = 'Windows';
    elseif (preg_match('/Mac/i', $agent)) $os = 'MacOS';
    elseif (preg_match('/Linux/i', $agent)) $os = 'Linux';
    elseif (preg_match('/Android/i', $agent)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $agent)) $os = 'iOS';

    if (preg_match('/Chrome/i', $agent)) $browser = 'Chrome';
    elseif (preg_match('/Firefox/i', $agent)) $browser = 'Firefox';
    elseif (preg_match('/Safari/i', $agent) && !preg_match('/Chrome/i', $agent)) $browser = 'Safari';
    elseif (preg_match('/Edge/i', $agent)) $browser = 'Edge';

    return [
        'os' => $os,
        'browser' => $browser,
        'device_type' => (preg_match('/Mobile/i', $agent)) ? 'Mobile' : 'Desktop'
    ];
}

function getGeoLocation($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return ['lat' => null, 'lon' => null, 'city' => 'Local', 'country' => 'Local'];
    }

    $url = "http://ip-api.com/json/{$ip}?fields=status,lat,lon,city,country";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 2
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        return ['lat' => null, 'lon' => null, 'city' => 'Unknown', 'country' => 'Unknown'];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['status']) || $data['status'] !== 'success') {
        return ['lat' => null, 'lon' => null, 'city' => 'Unknown', 'country' => 'Unknown'];
    }
    
    return [
        'lat' => $data['lat'] ?? null,
        'lon' => $data['lon'] ?? null,
        'city' => $data['city'] ?? 'Unknown',
        'country' => $data['country'] ?? 'Unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SYSTEM_NAME ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h1 class="logo-title"><?= SYSTEM_NAME ?></h1>
                <p class="logo-subtitle">Human Resource Information System</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-error show">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success show">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <div class="form-section">
                <h2 class="section-title">Welcome Back</h2>
                <p class="section-subtitle">Please enter your credentials to continue</p>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="error-message" id="usernameError">Please enter your username</div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="error-message" id="passwordError">Password must be at least 6 characters</div>
                    </div>

                    <div class="form-options">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-login">Sign In</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        function togglePassword() {
            const pass = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pass.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value;
            if (!username || username.length < 3) {
                this.classList.add('invalid');
                this.classList.remove('valid');
                document.getElementById('usernameError').classList.add('show');
            } else {
                this.classList.add('valid');
                this.classList.remove('invalid');
                document.getElementById('usernameError').classList.remove('show');
            }
        });

        document.getElementById('password').addEventListener('blur', function() {
            const password = this.value;
            if (password && password.length < 6) {
                this.classList.add('invalid');
                this.classList.remove('valid');
                document.getElementById('passwordError').classList.add('show');
            } else if (password) {
                this.classList.add('valid');
                this.classList.remove('invalid');
                document.getElementById('passwordError').classList.remove('show');
            }
        });

        document.getElementById('username').addEventListener('input', function() {
            this.classList.remove('invalid', 'valid');
            document.getElementById('usernameError').classList.remove('show');
        });

        document.getElementById('password').addEventListener('input', function() {
            this.classList.remove('invalid', 'valid');
            document.getElementById('passwordError').classList.remove('show');
        });
    </script>
</body>
</html>