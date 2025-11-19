<?php
// API endpoints should NOT display errors (breaks JSON)
// Set these FIRST before anything else
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(0); // Suppress ALL errors for API

// Start output buffering IMMEDIATELY to catch any stray output
ob_start();

// Now load dependencies
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/psgc-helper.php';

// Discard any warnings/errors that were buffered
ob_end_clean();

// Start fresh output buffer for JSON response
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// Use the fully qualified class name with namespace
$psgc = new \HRIS\Helpers\PSGCHelper($conn);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'regions':
            $data = $psgc->getRegions();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'provinces':
            $region_code = $_GET['region_code'] ?? '';
            if (empty($region_code)) {
                throw new Exception('Region code is required');
            }
            $data = $psgc->getProvinces($region_code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'cities':
            $province_code = $_GET['province_code'] ?? '';
            if (empty($province_code)) {
                throw new Exception('Province/Region code is required');
            }
            $data = $psgc->getCitiesMunicipalities($province_code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'barangays':
            $city_code = $_GET['city_code'] ?? '';
            if (empty($city_code)) {
                throw new Exception('City/Municipality code is required');
            }
            $data = $psgc->getBarangays($city_code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'search':
            $query = $_GET['query'] ?? '';
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            $data = $psgc->searchLocation($query);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_region':
            $code = $_GET['code'] ?? '';
            if (empty($code)) {
                throw new Exception('Region code is required');
            }
            $data = $psgc->getRegion($code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_province':
            $code = $_GET['code'] ?? '';
            if (empty($code)) {
                throw new Exception('Province code is required');
            }
            $data = $psgc->getProvince($code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_city':
            $code = $_GET['code'] ?? '';
            if (empty($code)) {
                throw new Exception('City code is required');
            }
            $data = $psgc->getCityMunicipality($code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_barangay':
            $code = $_GET['code'] ?? '';
            if (empty($code)) {
                throw new Exception('Barangay code is required');
            }
            $data = $psgc->getBarangay($code);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'clear_cache':
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Unauthorized');
            }
            $psgc->clearCache();
            echo json_encode(['success' => true, 'message' => 'Cache cleared successfully'], JSON_UNESCAPED_UNICODE);
            break;

        case 'clear_old_cache':
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Unauthorized');
            }
            $psgc->clearOldCache();
            echo json_encode(['success' => true, 'message' => 'Old cache cleared successfully'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// Flush the output buffer
ob_end_flush();