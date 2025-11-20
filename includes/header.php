<?php
// Ensure we're being included properly
if (!defined('HRIS_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Basic page titles
$page = $page ?? 'dashboard';
$action = isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '';
$page_title = ucwords(str_replace(['_', '/'], ' ', $page));
if ($action) {
    $page_title .= ' - ' . ucwords(str_replace('_', ' ', $action));
}
$full_title = htmlspecialchars(SYSTEM_NAME . ' - ' . $page_title);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(COMPANY_NAME . ' HRIS') ?>">
    <meta name="theme-color" content="#0d6efd">

    <title><?= $full_title ?></title>

    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Minimal utility to ensure full height layout */
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar-nav .nav-link.active { background-color: #0d6efd; color: white; }
        /* Ensure chart canvas responsiveness */
        canvas { max-width: 100%; }
    </style>

    <?php if (in_array($page, ['company', 'organizational_units', 'employees', 'minimum_wage_rates'])): ?>
    <script src="<?= BASE_URL ?>/assets/js/psgc-address.js"></script>
    <?php endif; ?>
</head>
<body class="bg-light">
