<?php
ob_start();
define('HRIS_ACCESS', true);

require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/session.php';

// --- [Keep existing Module Whitelist and Permissions logic here] ---
$allowed_pages = [ 'dashboard', 'company', 'organizational_units', 'minimum_wage_rates', 'positions', 'employees', 'users', 'roles', 'shift_schedules', 'attendance', 'leave', 'overtime', 'employee_requests', 'recruitment', 'announcements', 'memos', 'payroll', 'performance', 'reports', 'settings' ];
$page = isset($_GET['page']) ? preg_replace('/[^a-z_\/]/', '', strtolower(trim($_GET['page']))) : 'dashboard';

if (strpos($page, '/') !== false) {
    $parts = explode('/', $page);
    $main_module = $parts[0];
    if (!in_array($main_module, $allowed_pages)) $page = 'dashboard';
} elseif (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Module Path Logic
$subpage = null;
if (strpos($page, '/') !== false) {
    $parts = explode('/', $page);
    $page_path = __DIR__ . '/modules/' . $parts[0] . '/' . ($parts[1] ?? 'index') . '.php';
} else {
    $page_path = __DIR__ . '/modules/' . $page . '/' . $page . '.php';
}

if (!file_exists($page_path)) {
    $page_path = __DIR__ . '/modules/dashboard/dashboard.php';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">

        <nav class="col-lg-2 d-none d-lg-block bg-white border-end min-vh-100 position-fixed top-0 start-0" style="z-index: 1000;">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
        </nav>

        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarMenuLabel"><?= SYSTEM_NAME ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-0">
                <?php include __DIR__ . '/includes/sidebar.php'; ?>
            </div>
        </div>

        <main class="col-lg-10 ms-auto d-flex flex-column min-vh-100">
            <?php include __DIR__ . '/includes/topnav.php'; ?>

            <div class="flex-grow-1 p-4 bg-light">
                <?php include $page_path; ?>
            </div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
