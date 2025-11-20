<?php
/**
 * Header Template
 * 
 * Includes HTML head section with meta tags, CSS, and security headers.
 * This file should be included at the start of every page.
 * 
 * @package HRIS
 * @version 2.1
 */

// Ensure we're being included properly
if (!defined('HRIS_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// FIXED: Add security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy - adjust as needed for your site
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; ";
$csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; ";
$csp .= "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; ";
$csp .= "img-src 'self' data: https:; ";
$csp .= "connect-src 'self' https://cdn.jsdelivr.net; ";  // Allow CDN for source maps
$csp .= "frame-src 'self' https://www.google.com; ";  // Allow Google Maps embeds
header("Content-Security-Policy: " . $csp);

// Dynamic page titles based on current page
$page_titles = [
    'dashboard' => 'Dashboard',
    'company' => 'Company Management',
    'organizational_units' => 'Organizational Units',
    'minimum_wage_rates' => 'Minimum Wage Rates',
    'positions' => 'Positions',
    'employees' => 'Employee Management',
    'users' => 'User Management',
    'roles' => 'Roles & Permissions',
    'shift_schedules' => 'Shift Schedules',
    'recruitment' => 'Recruitment',
    'attendance' => 'Attendance & Time Tracking',
    'leave' => 'Leave Management',
    'overtime' => 'Overtime Management',
    'employee_requests' => 'Employee Requests',
    'announcements' => 'Announcements',
    'memos' => 'Employee Memos',
    'payroll' => 'Payroll Management',
    'government_contributions' => 'Government Contributions',
    'government_loans' => 'Government Loans',
    'company_deductions' => 'Company Deductions',
    'thirteenth_month' => '13th Month Pay',
    'performance' => 'Performance Management',
    'assets' => 'Assets Management',
    'supplies' => 'Office Supplies',
    'incidents' => 'Incident Reports',
    'reports' => 'Reports & Analytics',
    'settings' => 'System Settings'
];

// Get action for more specific titles
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

// FIXED: Set page title with proper sanitization
$page = $page ?? 'dashboard';
$page_title = isset($page_titles[$page]) ? $page_titles[$page] : 'Dashboard';

// Add action to title if exists
if ($action) {
    $action_clean = ucwords(str_replace('_', ' ', $action));
    $page_title .= ' - ' . $action_clean;
}

// FIXED: Properly escape all output
$full_title = escapeHtml(SYSTEM_NAME . ' - ' . $page_title);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=1.0">
    <meta name="description" content="<?= escapeHtml(COMPANY_NAME . ' Human Resource Information System') ?>">
    <meta name="author" content="<?= escapeHtml(COMPANY_NAME) ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- FIXED: Add theme color for mobile browsers - Bootstrap Primary -->
    <meta name="theme-color" content="#0d6efd">
    
    <title><?= $full_title ?></title>
    
    <!-- FIXED: Add favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/apple-touch-icon.png">
    
    <!-- FIXED: DNS prefetch for CDN -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+"
        crossorigin="anonymous"
    >

    <!-- Custom Bootstrap Theme & Components -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-custom.css">

    <!-- PSGC Address Script (if needed on this page) -->
    <?php if (in_array($page, ['company', 'organizational_units', 'employees', 'minimum_wage_rates'])): ?>
    <script src="<?= BASE_URL ?>/assets/js/psgc-address.js"></script>
    <?php endif; ?>
</head>
<body>