<?php
/**
 * Minimum Wage Rates Module
 * Manages regional minimum wage rates with city-specific variations
 * 
 * Database Schema Note:
 * ALTER TABLE minimum_wage_rates 
 * ADD COLUMN region_code VARCHAR(20) AFTER region,
 * ADD COLUMN province_code VARCHAR(20) AFTER province,
 * ADD COLUMN city_code VARCHAR(20) AFTER city,
 * ADD COLUMN import_batch_id INT AFTER city_code,
 * ADD INDEX idx_location_codes (region_code, province_code, city_code);
 */

// ===== FIX #1: Handle CSV Template Download FIRST (before ANY includes or HTML output) =====
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    // Prevent any output buffering or HTML from being sent
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="minimum_wage_rates_template.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, ['region', 'province', 'city', 'daily_rate', 'effective_date', 'wage_order_number', 'is_current', 'notes']);
    
    // Sample data rows
    fputcsv($output, ['National Capital Region (NCR)', '', '', '695', '07/18/2025', 'WO NCR-26', '1', 'Current NCR rate']);
    fputcsv($output, ['Region IV-A (CALABARZON)', 'Cavite', 'Bacoor', '600', '10/05/2025', 'WO RIVA-22', '1', 'Current rate for Bacoor, Cavite']);
    
    fclose($output);
    exit;
}
// ===== END FIX #1 =====

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';


// Helper function to match region names (used by multiple actions)
function matchRegionName($input_name, $regions_cache) {
    // Strip any content in parentheses for clean matching
    $input_clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $input_name);
    $input_clean = trim($input_clean);
    
    // Try exact match first (case insensitive)
    foreach ($regions_cache as $r) {
        $cache_clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $r['name']);
        $cache_clean = trim($cache_clean);
        
        // Match with and without parentheses content
        if (strcasecmp($cache_clean, $input_clean) === 0 || strcasecmp($r['name'], $input_name) === 0) {
            return $r['code'];
        }
    }
    
    // Extract abbreviation from parentheses if present
    if (preg_match('/\(([^)]+)\)/', $input_name, $matches)) {
        $abbr_in_input = strtoupper(trim($matches[1]));
        // Check if cache has this abbreviation
        foreach ($regions_cache as $r) {
            if (preg_match('/\(([^)]+)\)/', $r['name'], $cache_matches)) {
                if (strcasecmp(trim($cache_matches[1]), $abbr_in_input) === 0) {
                    return $r['code'];
                }
            }
        }
    }
    
    // Try partial match (input name contains cached name or vice versa)
    foreach ($regions_cache as $r) {
        $cache_clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $r['name']);
        $cache_clean = trim($cache_clean);
        
        if (stripos($cache_clean, $input_clean) !== false || stripos($input_clean, $cache_clean) !== false) {
            return $r['code'];
        }
    }
    
    // Try common abbreviations
    $abbreviations = [
        'CAR' => 'Cordillera Administrative Region (CAR)',
        'NCR' => 'National Capital Region (NCR)',
        'ARMM' => 'Autonomous Region in Muslim Mindanao (ARMM)',
        'BARMM' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
        'MIMAROPA' => 'Southwestern Tagalog Region (MIMAROPA)',
        'CALABARZON' => 'Region IV-A (CALABARZON)',
        'SOCCSKSARGEN' => 'Region XII (SOCCSKSARGEN)'
    ];
    
    // Check if input is an abbreviation or contains one
    foreach ($abbreviations as $abbr => $full_name) {
        if (strcasecmp($input_name, $abbr) === 0 || 
            stripos($input_name, $abbr) !== false ||
            stripos($input_name, str_replace(" ($abbr)", "", $full_name)) !== false) {
            // Try to find the full name in cache
            foreach ($regions_cache as $r) {
                $r_clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $r['name']);
                $r_clean = trim($r_clean);
                $full_clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $full_name);
                $full_clean = trim($full_clean);
                
                if (stripos($r_clean, $full_clean) !== false || 
                    stripos($r_clean, $abbr) !== false ||
                    stripos($r['name'], "($abbr)") !== false) {
                    return $r['code'];
                }
            }
        }
    }
    
    // Try "Region" followed by roman numeral with optional suffix (e.g., IV-A, IV-B)
    if (preg_match('/Region\s+(I{1,3}V?|I?V|VI{0,3}|I?X|XI{0,3}|XII{0,3})(-[A-Z])?/i', $input_name, $matches)) {
        $roman = strtoupper($matches[1]);
        $suffix = isset($matches[2]) ? strtoupper($matches[2]) : '';
        $search_pattern = "Region $roman$suffix";
        
        foreach ($regions_cache as $r) {
            if (stripos($r['name'], $search_pattern) !== false) {
                return $r['code'];
            }
        }
    }
    
    return null;
}

// Handle PSGC Code Mapping (run after import to populate codes and standardize region names)
if ($action === 'map_psgc_codes') {
    $mapped = 0;
    $failed = 0;
    $failed_items = [];
    $mapped_items = [];
    $unchanged = 0;
    
    // Get all non-deleted rates to map/update
    $rates_to_map = $conn->query("SELECT id, region, province, city, region_code, province_code, city_code FROM minimum_wage_rates WHERE is_deleted = 0");
    
    // Get regions from cache
    $cache_lookup = $conn->prepare("SELECT cache_data FROM psgc_cache WHERE cache_key = 'regions_all' LIMIT 1");
    $cache_lookup->execute();
    $cache_result = $cache_lookup->get_result();
    
    $regions_cache = [];
    if ($cache_row = $cache_result->fetch_assoc()) {
        $regions_cache = json_decode($cache_row['cache_data'], true);
    }
    $cache_lookup->close();
    
    if (empty($regions_cache)) {
        $_SESSION['error'] = "PSGC regions cache is empty. Please visit the organizational units page first to populate the cache.";
        header("Location: index.php?page=minimum_wage_rates");
        exit;
    }
    
    while ($rate = $rates_to_map->fetch_assoc()) {
        $region_code = matchRegionName($rate['region'], $regions_cache);
        $province_code = null;
        $city_code = null;
        
        // Find standardized region name
        $standardized_region = null;
        foreach ($regions_cache as $r) {
            if ($r['code'] === $region_code) {
                $standardized_region = $r['name'];
                break;
            }
        }
        
        // Map province code if province is specified
        if (!empty($rate['province']) && $region_code) {
            // Get provinces for this region
            $cache_key = "provinces_" . $region_code;
            $prov_lookup = $conn->prepare("SELECT cache_data FROM psgc_cache WHERE cache_key = ? LIMIT 1");
            $prov_lookup->bind_param("s", $cache_key);
            $prov_lookup->execute();
            $prov_result = $prov_lookup->get_result();
            
            $provinces_cache = [];
            if ($prov_row = $prov_result->fetch_assoc()) {
                $provinces_cache = json_decode($prov_row['cache_data'], true);
            } else {
                // Cache not found, try to fetch from API
                $api_url = "http://" . $_SERVER['HTTP_HOST'] . "/hris/api/psgc-api.php?action=provinces&region_code=" . urlencode($region_code);
                $response = @file_get_contents($api_url);
                if ($response) {
                    $api_data = json_decode($response, true);
                    if (isset($api_data['data']) && is_array($api_data['data'])) {
                        $provinces_cache = $api_data['data'];
                        
                        // Store in cache for future use
                        $cache_data = json_encode($provinces_cache);
                        $cache_insert = $conn->prepare("INSERT INTO psgc_cache (cache_key, cache_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), updated_at = CURRENT_TIMESTAMP");
                        $cache_insert->bind_param("ss", $cache_key, $cache_data);
                        $cache_insert->execute();
                        $cache_insert->close();
                    }
                }
            }
            $prov_lookup->close();
            
            if (!empty($provinces_cache)) {
                // Try to match province name
                foreach ($provinces_cache as $p) {
                    if (strcasecmp($p['name'], $rate['province']) === 0) {
                        $province_code = $p['code'];
                        break;
                    }
                }
                
                // If no exact match, try partial match
                if (!$province_code) {
                    foreach ($provinces_cache as $p) {
                        if (stripos($p['name'], $rate['province']) !== false || 
                            stripos($rate['province'], $p['name']) !== false) {
                            $province_code = $p['code'];
                            break;
                        }
                    }
                }
            }
        }
        
        // Map city code if city is specified and province code is found
        if (!empty($rate['city']) && $province_code) {
            // Get cities for this province
            $cache_key = "cities_" . $province_code;
            $city_lookup = $conn->prepare("SELECT cache_data FROM psgc_cache WHERE cache_key = ? LIMIT 1");
            $city_lookup->bind_param("s", $cache_key);
            $city_lookup->execute();
            $city_result = $city_lookup->get_result();
            
            $cities_cache = [];
            if ($city_row = $city_result->fetch_assoc()) {
                $cities_cache = json_decode($city_row['cache_data'], true);
            } else {
                // Cache not found, try to fetch from API
                $api_url = "http://" . $_SERVER['HTTP_HOST'] . "/hris/api/psgc-api.php?action=cities&province_code=" . urlencode($province_code);
                $response = @file_get_contents($api_url);
                if ($response) {
                    $api_data = json_decode($response, true);
                    if (isset($api_data['data']) && is_array($api_data['data'])) {
                        $cities_cache = $api_data['data'];
                        
                        // Store in cache for future use
                        $cache_data = json_encode($cities_cache);
                        $cache_insert = $conn->prepare("INSERT INTO psgc_cache (cache_key, cache_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), updated_at = CURRENT_TIMESTAMP");
                        $cache_insert->bind_param("ss", $cache_key, $cache_data);
                        $cache_insert->execute();
                        $cache_insert->close();
                    }
                }
            }
            $city_lookup->close();
            
            if (!empty($cities_cache)) {
                // Try to match city name (exact match)
                foreach ($cities_cache as $c) {
                    if (strcasecmp($c['name'], $rate['city']) === 0) {
                        $city_code = $c['code'];
                        break;
                    }
                }
                
                // If no exact match, try partial match
                if (!$city_code) {
                    foreach ($cities_cache as $c) {
                        if (stripos($c['name'], $rate['city']) !== false || 
                            stripos($rate['city'], $c['name']) !== false) {
                            $city_code = $c['code'];
                            break;
                        }
                    }
                }
            }
        }
        
        // Check if update is needed
        $needs_update = false;
        $update_parts = [];
        $update_values = [];
        
        if ($region_code && ($rate['region_code'] !== $region_code)) {
            $update_parts[] = "region_code = ?";
            $update_values[] = $region_code;
            $needs_update = true;
        }
        
        if ($standardized_region && ($rate['region'] !== $standardized_region)) {
            $update_parts[] = "region = ?";
            $update_values[] = $standardized_region;
            $needs_update = true;
        }
        
        if ($province_code && ($rate['province_code'] !== $province_code)) {
            $update_parts[] = "province_code = ?";
            $update_values[] = $province_code;
            $needs_update = true;
        }
        
        if ($city_code && ($rate['city_code'] !== $city_code)) {
            $update_parts[] = "city_code = ?";
            $update_values[] = $city_code;
            $needs_update = true;
        }
        
        // Perform update if needed
        if ($needs_update && !empty($update_parts)) {
            $sql = "UPDATE minimum_wage_rates SET " . implode(', ', $update_parts) . " WHERE id = ?";
            $update_stmt = $conn->prepare($sql);

            // Build bind parameters dynamically
            $types = str_repeat('s', count($update_values)) . 'i';
            $update_values[] = $rate['id'];
            $update_stmt->bind_param($types, ...$update_values);
            $update_stmt->execute();
            $update_stmt->close();
            $mapped++;

            // Track what was mapped
            $mapped_items[] = [
                'region' => $standardized_region ?? $rate['region'],
                'province' => $rate['province'] ?? 'All',
                'city' => $rate['city'] ?? 'All',
                'region_code' => $region_code,
                'province_code' => $province_code,
                'city_code' => $city_code
            ];
        } else {
            $unchanged++;
        }
        
        // Track failures
        if (!$region_code) {
            $failed++;
            $failed_items[] = "Region: " . $rate['region'];
        } elseif (!empty($rate['province']) && !$province_code) {
            $failed++;
            $failed_items[] = "Province: " . $rate['province'] . " (Region: " . $rate['region'] . ")";
        } elseif (!empty($rate['city']) && !$city_code) {
            $failed++;
            $failed_items[] = "City: " . $rate['city'] . " (Province: " . $rate['province'] . ")";
        }
    }

    // Store detailed results in session
    $_SESSION['psgc_mapping_results'] = [
        'mapped' => $mapped,
        'failed' => $failed,
        'unchanged' => $unchanged,
        'mapped_items' => $mapped_items,
        'failed_items' => $failed_items
    ];

    header("Location: index.php?page=minimum_wage_rates&action=map_psgc_result");
    exit;
}

// Handle Export Current/Upcoming Rates
if ($action === 'export_current') {
    // Clear any output buffers to prevent HTML from being included
    while (ob_get_level()) {
        ob_end_clean();
    }

    $today = date('Y-m-d');

    // Get current and upcoming rates with PSGC codes - RAW data from database
    $export_stmt = $conn->prepare("
        SELECT region, region_code, province, province_code, city, city_code,
               daily_rate, effective_date, wage_order_number, is_current, notes
        FROM minimum_wage_rates
        WHERE is_deleted = 0
        AND (is_current = 1 OR effective_date > ?)
        ORDER BY region ASC, province ASC, city ASC, effective_date DESC
    ");
    $export_stmt->bind_param("s", $today);
    $export_stmt->execute();
    $result = $export_stmt->get_result();
    $export_data = $result->fetch_all(MYSQLI_ASSOC);
    $export_stmt->close();

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="minimum_wage_rates_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, [
        'region',
        'region_code',
        'province',
        'province_code',
        'city',
        'city_code',
        'daily_rate',
        'effective_date',
        'wage_order_number',
        'is_current',
        'notes'
    ]);

    // Write data rows - strip any HTML tags and decode entities
    // Force PSGC codes as text by prepending tab character (preserves leading zeros in Excel)
    foreach ($export_data as $row) {
        // Clean and prepare code fields as text
        $region_code = $row['region_code'] ?? '';
        $region_code = strip_tags(html_entity_decode($region_code, ENT_QUOTES, 'UTF-8'));
        $region_code = $region_code ? "\t" . $region_code : '';

        $province_code = $row['province_code'] ?? '';
        $province_code = strip_tags(html_entity_decode($province_code, ENT_QUOTES, 'UTF-8'));
        $province_code = $province_code ? "\t" . $province_code : '';

        $city_code = $row['city_code'] ?? '';
        $city_code = strip_tags(html_entity_decode($city_code, ENT_QUOTES, 'UTF-8'));
        $city_code = $city_code ? "\t" . $city_code : '';

        fputcsv($output, [
            strip_tags(html_entity_decode($row['region'] ?? '', ENT_QUOTES, 'UTF-8')),
            $region_code,
            strip_tags(html_entity_decode($row['province'] ?? '', ENT_QUOTES, 'UTF-8')),
            $province_code,
            strip_tags(html_entity_decode($row['city'] ?? '', ENT_QUOTES, 'UTF-8')),
            $city_code,
            $row['daily_rate'],
            $row['effective_date'],
            strip_tags(html_entity_decode($row['wage_order_number'] ?? '', ENT_QUOTES, 'UTF-8')),
            $row['is_current'],
            strip_tags(html_entity_decode($row['notes'] ?? '', ENT_QUOTES, 'UTF-8'))
        ]);
    }

    fclose($output);
    exit;
}

// ===== AUTO-ACTIVATE FUTURE RATES (runs once per day automatically) =====
$today = date('Y-m-d');

// Check if we've already run today (using a simple flag file)
$flag_file = __DIR__ . '/../../temp/wage_rates_last_run.txt';
$flag_dir = dirname($flag_file);

// Create temp directory if it doesn't exist
if (!file_exists($flag_dir)) {
    @mkdir($flag_dir, 0755, true);
}

$should_run = true;
if (file_exists($flag_file)) {
    $last_run = file_get_contents($flag_file);
    if ($last_run === $today) {
        $should_run = false; // Already ran today
    }
}

// Auto-activate pending rates that should be current today
if ($should_run) {
    try {
        $conn->begin_transaction();
        
        // Find pending rates that should now be current
        $pending_stmt = $conn->prepare("
            SELECT id, region, province, city, effective_date, daily_rate
            FROM minimum_wage_rates
            WHERE is_current = 0 
            AND is_deleted = 0
            AND effective_date <= ?
            ORDER BY region, province, city, effective_date DESC
        ");
        
        $pending_stmt->bind_param("s", $today);
        $pending_stmt->execute();
        $pending_rates = $pending_stmt->get_result();
        
        $processed_locations = [];
        $activated_count = 0;
        
        while ($rate = $pending_rates->fetch_assoc()) {
            $location_key = $rate['region'] . '|' . ($rate['province'] ?? '') . '|' . ($rate['city'] ?? '');
            
            if (isset($processed_locations[$location_key])) {
                continue;
            }
            
            $processed_locations[$location_key] = true;
            
            // Activate this rate
            $activate_stmt = $conn->prepare("UPDATE minimum_wage_rates SET is_current = 1 WHERE id = ?");
            $activate_stmt->bind_param("i", $rate['id']);
            $activate_stmt->execute();
            $activate_stmt->close();
            
            // Deactivate older rates
            $deactivate_stmt = $conn->prepare("
                UPDATE minimum_wage_rates
                SET is_current = 0
                WHERE region = ? 
                AND (province = ? OR (province IS NULL AND ? IS NULL))
                AND (city = ? OR (city IS NULL AND ? IS NULL))
                AND effective_date < ?
                AND is_deleted = 0
                AND id != ?
            ");
            
            $province = $rate['province'];
            $city = $rate['city'];
            $effective_date = $rate['effective_date'];
            
            $deactivate_stmt->bind_param("ssssssi", 
                $rate['region'], 
                $province, $province,
                $city, $city,
                $effective_date,
                $rate['id']
            );
            $deactivate_stmt->execute();
            $deactivate_stmt->close();
            
            $activated_count++;
        }
        
        $pending_stmt->close();
        $conn->commit();
        
        // Update flag file
        file_put_contents($flag_file, $today);
        
        // Show notification if rates were activated
        if ($activated_count > 0) {
            $_SESSION['success'] = "Auto-activated $activated_count wage rate(s) with today's effective date!";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Auto-activation error: " . $e->getMessage());
    }
}
// ===== END AUTO-ACTIVATE =====

$action = $_GET['action'] ?? 'list';
$rate_id = $_GET['id'] ?? null;

// Handle CSV Bulk Import
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=minimum_wage_rates&action=import");
        exit;
    }
    
    $file = $_FILES['csv_file'];
    $import_summary = [
        'total' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'errors' => [],
        'skipped_details' => []
    ];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Ensure table is using utf8mb4 charset before importing (critical for special characters like ñ, é, á)
        $charset_result = $conn->query("ALTER TABLE minimum_wage_rates CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$charset_result) {
            error_log("Warning: Could not convert minimum_wage_rates table to utf8mb4: " . $conn->error);
        }

        // Create import batch
        $batch_stmt = $conn->prepare("INSERT INTO import_batches (module, filename, created_by) VALUES ('minimum_wage_rates', ?, ?)");
        $batch_stmt->bind_param("si", $file['name'], $_SESSION['user_id']);
        $batch_stmt->execute();
        $import_batch_id = $conn->insert_id;
        $batch_stmt->close();
        
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // Skip header row
        $row_number = 1;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;
            $import_summary['total']++;

            // Convert each field to UTF-8 if needed (handles special characters like ñ, é, á, etc.)
            $data = array_map(function($field) {
                if (empty($field)) {
                    return $field;
                }

                // Check if already valid UTF-8
                if (mb_check_encoding($field, 'UTF-8')) {
                    return $field;
                }

                // Try to detect and convert from common encodings
                $encodings = ['Windows-1252', 'ISO-8859-1', 'CP1252', 'Latin1'];
                foreach ($encodings as $encoding) {
                    $converted = @mb_convert_encoding($field, 'UTF-8', $encoding);
                    if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                        return $converted;
                    }
                }

                // Fallback to auto-detection
                return mb_convert_encoding($field, 'UTF-8', 'auto');
            }, $data);

            // Skip empty rows
            if (empty(array_filter($data))) {
                $import_summary['skipped']++;
                continue;
            }
            
            // Map CSV columns (expecting: region,province,city,daily_rate,effective_date,wage_order_number,is_current,notes)
            $region = trim($data[0] ?? '');
            $province = trim($data[1] ?? '');
            $city = trim($data[2] ?? '');
            $daily_rate = trim($data[3] ?? '');
            $effective_date = trim($data[4] ?? '');
            $wage_order = trim($data[5] ?? '');
            $is_current = !empty(trim($data[6] ?? '')) ? (int)trim($data[6]) : 0;
            $notes = trim($data[7] ?? '');
            
            // Validate required fields
            if (empty($region) || empty($daily_rate) || empty($effective_date)) {
                $import_summary['errors'][] = "Row $row_number: Missing required fields (region, daily_rate, or effective_date)";
                $import_summary['skipped']++;
                continue;
            }
            
            // Convert date from MM/DD/YYYY to YYYY-MM-DD
            $date_parts = explode('/', $effective_date);
            if (count($date_parts) === 3) {
                $effective_date = sprintf("%04d-%02d-%02d", $date_parts[2], $date_parts[0], $date_parts[1]);
            } else {
                $import_summary['errors'][] = "Row $row_number: Invalid date format (use MM/DD/YYYY)";
                $import_summary['skipped']++;
                continue;
            }
            
            // PSGC codes will be NULL for now
            // They can be populated later using a separate mapping tool
            // This avoids issues with lazy-loaded cache
            $region_code = null;
            $province_code = null;
            $city_code = null;
            
            // Optional: Try to lookup region code if regions_all is cached
            $cache_lookup = $conn->prepare("SELECT cache_data FROM psgc_cache WHERE cache_key = 'regions_all' LIMIT 1");
            if ($cache_lookup) {
                $cache_lookup->execute();
                $cache_result = $cache_lookup->get_result();
                
                if ($cache_row = $cache_result->fetch_assoc()) {
                    $regions = json_decode($cache_row['cache_data'], true);
                    if ($regions) {
                        foreach ($regions as $r) {
                            if (strcasecmp($r['name'], $region) === 0) {
                                $region_code = $r['code'];
                                break;
                            }
                        }
                    }
                }
                $cache_lookup->close();
            }
            
            // Check for duplicate (same location + effective date)
            $empty_province = empty($province) ? null : $province;
            $empty_city = empty($city) ? null : $city;
            
            $check_stmt = $conn->prepare("
                SELECT id FROM minimum_wage_rates 
                WHERE region = ? AND (province <=> ?) AND (city <=> ?) 
                AND effective_date = ? AND is_deleted = 0
            ");
            $check_stmt->bind_param("ssss", $region, $empty_province, $empty_city, $effective_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $import_summary['skipped']++;
                $location = $region;
                if ($province) $location .= ", $province";
                if ($city) $location .= ", $city";
                $import_summary['skipped_details'][] = "Row $row_number: Duplicate - $location on $effective_date already exists";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert new record
            $insert_stmt = $conn->prepare("
                INSERT INTO minimum_wage_rates (
                    region, region_code, province, province_code, city, city_code,
                    import_batch_id, daily_rate, effective_date, wage_order_number, 
                    is_current, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->bind_param("ssssssdsssssi",
                $region, $region_code, $empty_province, $province_code, 
                $empty_city, $city_code, $import_batch_id, $daily_rate, 
                $effective_date, $wage_order, $is_current, $notes, 
                $_SESSION['user_id']
            );
            
            if ($insert_stmt->execute()) {
                $import_summary['inserted']++;
            } else {
                $import_summary['errors'][] = "Row $row_number: Database error - " . $conn->error;
                $import_summary['skipped']++;
            }
            $insert_stmt->close();
        }
        
        fclose($handle);
        
        // Store summary in session
        $_SESSION['import_summary'] = $import_summary;
        header("Location: index.php?page=minimum_wage_rates&action=import_result");
        exit;
    } else {
        $_SESSION['error'] = "File upload error: " . $file['error'];
        header("Location: index.php?page=minimum_wage_rates&action=import");
        exit;
    }
}

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: index.php?page=minimum_wage_rates");
        exit;
    }
    
    if (isset($_POST['action'])) {
        $post_action = $_POST['action'];
        // Sanitize all input data
        $data = sanitizeInput($_POST);
        
        if ($post_action === 'add' || $post_action === 'edit') {
            // Check for duplicate rate
            $check_sql = "SELECT id FROM minimum_wage_rates 
                         WHERE region = ? AND province = ? AND city = ? 
                         AND effective_date = ? AND is_deleted = 0";
            if ($post_action === 'edit') {
                $check_sql .= " AND id != ?";
            }
            
            $check_stmt = $conn->prepare($check_sql);
            if ($post_action === 'edit') {
                $check_stmt->bind_param("ssssi", 
                    $data['region'], $data['province'], $data['city'], 
                    $data['effective_date'], $data['rate_id']
                );
            } else {
                $check_stmt->bind_param("ssss", 
                    $data['region'], $data['province'], $data['city'], 
                    $data['effective_date']
                );
            }
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = "A wage rate for this location and effective date already exists!";
                header("Location: index.php?page=minimum_wage_rates&action=" . $post_action . ($post_action === 'edit' ? "&id=" . $_POST['rate_id'] : ""));
                exit;
            }
            $check_stmt->close();
            
            // If NOT marked as historical (i.e., is current or future), update other rates based on effective date
            if (!isset($_POST['is_historical']) || $_POST['is_historical'] != '1') {
                // Only set other rates to non-current if the new rate's effective date is TODAY or in the PAST
                // Future rates should not affect current rates until their effective date arrives
                $new_rate_date = $_POST['effective_date'];
                $today = date('Y-m-d');
                
                if ($new_rate_date <= $today) {
                    // New rate is current or past - mark all rates with same/earlier dates as non-current
                    $update_sql = "UPDATE minimum_wage_rates 
                                  SET is_current = 0, updated_by = ?, updated_at = NOW()
                                  WHERE region = ? AND province = ? AND city = ? 
                                  AND effective_date <= ?
                                  AND is_deleted = 0";
                    if ($post_action === 'edit') {
                        $update_sql .= " AND id != ?";
                    }
                    
                    $update_stmt = $conn->prepare($update_sql);
                    if ($post_action === 'edit') {
                        $update_stmt->bind_param("issssi", 
                            $_SESSION['user_id'], $_POST['region'], $_POST['province'], 
                            $_POST['city'], $_POST['effective_date'], $_POST['rate_id']
                        );
                    } else {
                        $update_stmt->bind_param("issss", 
                            $_SESSION['user_id'], $_POST['region'], $_POST['province'], 
                            $_POST['city'], $_POST['effective_date']
                        );
                    }
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                // If new_rate_date > today, we don't update any existing rates yet
                // The new rate will be stored but not marked as current until its date arrives
            }
        }
        
        if ($post_action === 'add') {
            $stmt = $conn->prepare("INSERT INTO minimum_wage_rates (
                region, province, city, daily_rate, effective_date, 
                wage_order_number, is_current, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Determine if rate should be marked as current:
            // - If marked as "historical", set is_current = 0
            // - If effective_date is in the FUTURE, set is_current = 0 (pending rate)
            // - If effective_date is TODAY or PAST, set is_current = 1 (active rate)
            $new_rate_date = $_POST['effective_date'];
            $today = date('Y-m-d');
            
            if (isset($_POST['is_historical']) && $_POST['is_historical'] == '1') {
                // Explicitly marked as historical
                $is_current = 0;
            } elseif ($new_rate_date > $today) {
                // Future rate - not current yet
                $is_current = 0;
            } else {
                // Current or past rate
                $is_current = 1;
            }
            
            $city = !empty($_POST['city']) ? $_POST['city'] : NULL;
            $province = !empty($_POST['province']) ? $_POST['province'] : NULL;
            
            $stmt->bind_param("sssdssssi",
                $_POST['region'], $province, $city, $_POST['daily_rate'], 
                $_POST['effective_date'], $_POST['wage_order_number'], 
                $is_current, $_POST['notes'], $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Minimum wage rate added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add wage rate: " . $conn->error;
            }
            $stmt->close();
            header("Location: index.php?page=minimum_wage_rates");
            exit;
            
        } elseif ($post_action === 'edit') {
            $stmt = $conn->prepare("UPDATE minimum_wage_rates SET
                region = ?, province = ?, city = ?, daily_rate = ?, 
                effective_date = ?, wage_order_number = ?, is_current = ?, 
                notes = ?, updated_by = ?
                WHERE id = ?");
            
            // Determine if rate should be marked as current:
            // - If marked as "historical", set is_current = 0
            // - If effective_date is in the FUTURE, set is_current = 0 (pending rate)
            // - If effective_date is TODAY or PAST, set is_current = 1 (active rate)
            $new_rate_date = $_POST['effective_date'];
            $today = date('Y-m-d');
            
            if (isset($_POST['is_historical']) && $_POST['is_historical'] == '1') {
                // Explicitly marked as historical
                $is_current = 0;
            } elseif ($new_rate_date > $today) {
                // Future rate - not current yet
                $is_current = 0;
            } else {
                // Current or past rate
                $is_current = 1;
            }
            
            $city = !empty($_POST['city']) ? $_POST['city'] : NULL;
            $province = !empty($_POST['province']) ? $_POST['province'] : NULL;
            
            $stmt->bind_param("sssdsssiii",
                $_POST['region'], $province, $city, $_POST['daily_rate'],
                $_POST['effective_date'], $_POST['wage_order_number'],
                $is_current, $_POST['notes'], $_SESSION['user_id'], $_POST['rate_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Minimum wage rate updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update wage rate: " . $conn->error;
            }
            $stmt->close();
            header("Location: index.php?page=minimum_wage_rates");
            exit;
        }
    }
}

// Handle delete action
if ($action === 'delete' && $rate_id) {
    $stmt = $conn->prepare("UPDATE minimum_wage_rates SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $rate_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Minimum wage rate deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete wage rate: " . $conn->error;
    }
    $stmt->close();
    header("Location: index.php?page=minimum_wage_rates");
    exit;
}

// Fetch rate data for edit/view
$rate = null;
if (($action === 'edit' || $action === 'view') && $rate_id) {
    $stmt = $conn->prepare("
        SELECT * FROM minimum_wage_rates
        WHERE id = ? AND is_deleted = 0
    ");
    $stmt->bind_param("i", $rate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rate = $result->fetch_assoc();
    $stmt->close();
    
    if (!$rate) {
        $_SESSION['error'] = "Wage rate not found!";
        header("Location: index.php?page=minimum_wage_rates");
        exit;
    }
}

// Fetch all rates for list view with filters
$rates = [];
if ($action === 'list') {
    $where_conditions = ["is_deleted = 0"];
    $params = [];
    $types = "";
    
    // Filter by region
    if (!empty($_GET['filter_region'])) {
        $where_conditions[] = "region = ?";
        $params[] = $_GET['filter_region'];
        $types .= "s";
    }
    
    // Filter by rates - default shows current and upcoming, checkbox shows historical
    if (isset($_GET['filter_historical']) && $_GET['filter_historical'] == '1') {
        // Show all rates including historical
        // No additional filter needed
    } else {
        // Default: show current rates (is_current = 1) AND upcoming rates (effective_date > today)
        $today = date('Y-m-d');
        $where_conditions[] = "(is_current = 1 OR effective_date > '$today')";
    }
    
    // Filter by date range
    if (!empty($_GET['filter_date_from'])) {
        $where_conditions[] = "effective_date >= ?";
        $params[] = $_GET['filter_date_from'];
        $types .= "s";
    }
    if (!empty($_GET['filter_date_to'])) {
        $where_conditions[] = "effective_date <= ?";
        $params[] = $_GET['filter_date_to'];
        $types .= "s";
    }
    
    // Search by wage order number
    if (!empty($_GET['search'])) {
        $where_conditions[] = "(wage_order_number LIKE ? OR region LIKE ? OR province LIKE ? OR city LIKE ?)";
        $search_term = "%" . $_GET['search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ssss";
    }
    
    // Filter by import batch
    if (!empty($_GET['filter_batch'])) {
        $where_conditions[] = "import_batch_id = ?";
        $params[] = $_GET['filter_batch'];
        $types .= "i";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    $sql = "SELECT * FROM minimum_wage_rates WHERE $where_clause ORDER BY region ASC, effective_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rates = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch history for a specific location
$history_rates = [];
if ($action === 'history') {
    $region = $_GET['region'] ?? '';
    $province = $_GET['province'] ?? '';
    $city = $_GET['city'] ?? '';
    
    // Convert empty strings to NULL
    $province = ($province === '') ? null : $province;
    $city = ($city === '') ? null : $city;
    
    // Try to find region_code from the region name
    $region_code = null;
    
    // Get regions from cache for matching
    $cache_lookup = $conn->prepare("SELECT cache_data FROM psgc_cache WHERE cache_key = 'regions_all' LIMIT 1");
    $cache_lookup->execute();
    $cache_result = $cache_lookup->get_result();
    
    if ($cache_row = $cache_result->fetch_assoc()) {
        $regions_cache = json_decode($cache_row['cache_data'], true);
        $region_code = matchRegionName($region, $regions_cache);
    }
    $cache_lookup->close();
    
    // Query using region_code if available, otherwise fall back to region name
    if ($region_code) {
        // Build query with proper NULL handling
        if ($province === null && $city === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region_code = ? AND province IS NULL AND city IS NULL AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("s", $region_code);
        } elseif ($province === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region_code = ? AND province IS NULL AND city = ? AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("ss", $region_code, $city);
        } elseif ($city === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region_code = ? AND province = ? AND city IS NULL AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("ss", $region_code, $province);
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region_code = ? AND province = ? AND city = ? AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("sss", $region_code, $province, $city);
        }
    } else {
        // Fallback to region name with proper NULL handling
        if ($province === null && $city === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region = ? AND province IS NULL AND city IS NULL AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("s", $region);
        } elseif ($province === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region = ? AND province IS NULL AND city = ? AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("ss", $region, $city);
        } elseif ($city === null) {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region = ? AND province = ? AND city IS NULL AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("ss", $region, $province);
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM minimum_wage_rates
                WHERE region = ? AND province = ? AND city = ? AND is_deleted = 0
                ORDER BY effective_date DESC
            ");
            $stmt->bind_param("sss", $region, $province, $city);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $history_rates = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get unique regions for filter dropdown
$regions = [];
$stmt = $conn->prepare("SELECT DISTINCT region FROM minimum_wage_rates WHERE is_deleted = 0 ORDER BY region ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $regions[] = $row['region'];
}
$stmt->close();

// Group rates by region and province for collapsible display
$grouped_rates = [];
foreach ($rates as $rate) {
    $region = $rate['region'];
    $province = $rate['province'] ?? 'Region-wide';
    
    if (!isset($grouped_rates[$region])) {
        $grouped_rates[$region] = [];
    }
    
    if (!isset($grouped_rates[$region][$province])) {
        $grouped_rates[$region][$province] = [];
    }
    
    $grouped_rates[$region][$province][] = $rate;
}
?>

<?php if ($action === 'import'): ?>
    <div class="page-header">
        <h1 class="page-title">Bulk Import Wage Rates</h1>
        <p class="page-subtitle">Upload CSV file to import multiple wage rates at once</p>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-upload me-2"></i>Upload CSV File</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?page=minimum_wage_rates&action=import" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div class="mb-3">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <small class="text-muted">Maximum file size: 5MB</small>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>CSV Format Requirements:</h6>
                            <ul class="mb-0">
                                <li><strong>Columns (in order):</strong> region, province, city, daily_rate, effective_date, wage_order_number, is_current, notes</li>
                                <li><strong>Optional PSGC Codes:</strong> You can include region_code, province_code, city_code columns (exported data includes these)</li>
                                <li><strong>Date Format:</strong> YYYY-MM-DD (e.g., 2025-07-18)</li>
                                <li><strong>is_current:</strong> 1 for current, 0 for historical/upcoming</li>
                                <li><strong>Empty fields:</strong> Leave province/city blank for region-wide rates</li>
                                <li><strong>First row:</strong> Must be header row (will be skipped)</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Duplicate Handling:</strong> Records with the same region/province/city/effective_date will be skipped.
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Upload and Import
                        </button>
                        <a href="index.php?page=minimum_wage_rates&action=export_current" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Export Current/Upcoming Rates
                        </a>
                        <a href="index.php?page=minimum_wage_rates" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-download me-2"></i>CSV Template</h5>
                </div>
                <div class="card-body">
                    <p>Download a sample CSV template to get started:</p>
                    <a href="index.php?page=minimum_wage_rates&action=download_template" class="btn btn-outline-primary w-100">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Download Template
                    </a>

                    <hr>

                    <h6 class="mt-3">Sample Data (NCR):</h6>
                    <pre class="bg-light p-2 small">region,province,city,daily_rate,effective_date,wage_order_number,is_current,notes
National Capital Region,,,695,07/18/2025,WO NCR-26,1,Current NCR rate
National Capital Region,,,645,07/17/2024,WO NCR-25,0,Previous NCR rate</pre>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Import History</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">View past imports and rollback if needed</p>
                    <a href="index.php?page=minimum_wage_rates&action=import_history" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-list-ul me-2"></i>View History
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'import_result'): ?>
    <?php $summary = $_SESSION['import_summary'] ?? null; ?>
    <?php if ($summary): ?>
        <div class="page-header">
            <h1 class="page-title">Import Results</h1>
            <p class="page-subtitle">Review the import summary</p>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <h2 class="text-primary"><?= $summary['total'] ?></h2>
                        <p class="text-muted">Total Rows</p>
                    </div>
                    <div class="col-md-3">
                        <h2 class="text-success"><?= $summary['inserted'] ?></h2>
                        <p class="text-muted">Inserted</p>
                    </div>
                    <div class="col-md-3">
                        <h2 class="text-warning"><?= $summary['skipped'] ?></h2>
                        <p class="text-muted">Skipped</p>
                    </div>
                    <div class="col-md-3">
                        <h2 class="text-danger"><?= count($summary['errors']) ?></h2>
                        <p class="text-muted">Errors</p>
                    </div>
                </div>

                <?php if (!empty($summary['errors'])): ?>
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Errors Encountered:</h6>
                        <ul class="mb-0" id="errorsList">
                            <?php 
                            $error_count = count($summary['errors']);
                            $initial_display = min(10, $error_count);
                            ?>
                            <?php for ($i = 0; $i < $initial_display; $i++): ?>
                                <li><?= htmlspecialchars($summary['errors'][$i]) ?></li>
                            <?php endfor; ?>
                            
                            <?php if ($error_count > 10): ?>
                                <?php for ($i = 10; $i < $error_count; $i++): ?>
                                    <li class="collapse-error-item" style="display: none;"><?= htmlspecialchars($summary['errors'][$i]) ?></li>
                                <?php endfor; ?>
                                <li class="mt-2">
                                    <button class="btn btn-sm btn-outline-danger" id="toggleErrors" onclick="toggleErrorsList()">
                                        <i class="bi bi-chevron-down me-1"></i>
                                        <span id="errorsButtonText">Show <?= $error_count - 10 ?> more errors</span>
                                    </button>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($summary['skipped_details'])): ?>
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-info-circle me-2"></i>Skipped Records (Duplicates):</h6>
                        <ul class="mb-0" id="skippedList">
                            <?php 
                            $skipped_count = count($summary['skipped_details']);
                            $initial_display = min(10, $skipped_count);
                            ?>
                            <?php for ($i = 0; $i < $initial_display; $i++): ?>
                                <li><?= htmlspecialchars($summary['skipped_details'][$i]) ?></li>
                            <?php endfor; ?>
                            
                            <?php if ($skipped_count > 10): ?>
                                <?php for ($i = 10; $i < $skipped_count; $i++): ?>
                                    <li class="collapse-skipped-item" style="display: none;"><?= htmlspecialchars($summary['skipped_details'][$i]) ?></li>
                                <?php endfor; ?>
                                <li class="mt-2">
                                    <button class="btn btn-sm btn-outline-warning" id="toggleSkipped" onclick="toggleSkippedList()">
                                        <i class="bi bi-chevron-down me-1"></i>
                                        <span id="skippedButtonText">Show <?= $skipped_count - 10 ?> more skipped records</span>
                                    </button>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($summary['inserted'] > 0): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Successfully imported <?= $summary['inserted'] ?> wage rate(s)!
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="index.php?page=minimum_wage_rates" class="btn btn-primary">
                        <i class="bi bi-list me-2"></i>View All Rates
                    </a>
                    <a href="index.php?page=minimum_wage_rates&action=import" class="btn btn-secondary">
                        <i class="bi bi-upload me-2"></i>Import More
                    </a>
                </div>
            </div>
        </div>

        <script>
        let errorsExpanded = false;
        let skippedExpanded = false;
        const errorCount = <?= !empty($summary['errors']) ? count($summary['errors']) : 0 ?>;
        const skippedCount = <?= !empty($summary['skipped_details']) ? count($summary['skipped_details']) : 0 ?>;

        function toggleErrorsList() {
            const items = document.querySelectorAll('.collapse-error-item');
            const button = document.getElementById('toggleErrors');
            const buttonText = document.getElementById('errorsButtonText');
            const icon = button.querySelector('i');
            
            errorsExpanded = !errorsExpanded;
            
            items.forEach(item => {
                item.style.display = errorsExpanded ? 'list-item' : 'none';
            });
            
            if (errorsExpanded) {
                icon.className = 'bi bi-chevron-up me-1';
                buttonText.textContent = 'Show less';
            } else {
                icon.className = 'bi bi-chevron-down me-1';
                buttonText.textContent = 'Show ' + (errorCount - 10) + ' more errors';
            }
        }

        function toggleSkippedList() {
            const items = document.querySelectorAll('.collapse-skipped-item');
            const button = document.getElementById('toggleSkipped');
            const buttonText = document.getElementById('skippedButtonText');
            const icon = button.querySelector('i');
            
            skippedExpanded = !skippedExpanded;
            
            items.forEach(item => {
                item.style.display = skippedExpanded ? 'list-item' : 'none';
            });
            
            if (skippedExpanded) {
                icon.className = 'bi bi-chevron-up me-1';
                buttonText.textContent = 'Show less';
            } else {
                icon.className = 'bi bi-chevron-down me-1';
                buttonText.textContent = 'Show ' + (skippedCount - 10) + ' more skipped records';
            }
        }
        </script>

        <?php unset($_SESSION['import_summary']); ?>
    <?php else: ?>
        <div class="alert alert-warning">
            No import summary available.
        </div>
    <?php endif; ?>


<?php elseif ($action === 'import_history'): ?>
    <?php
    // Get import history
    $history_stmt = $conn->prepare("
        SELECT ib.*, u.username,
               COUNT(mwr.id) as records_count
        FROM import_batches ib
        LEFT JOIN users u ON ib.created_by = u.id
        LEFT JOIN minimum_wage_rates mwr ON mwr.import_batch_id = ib.id AND mwr.is_deleted = 0
        WHERE ib.module = 'minimum_wage_rates'
        GROUP BY ib.id
        ORDER BY ib.created_at DESC
        LIMIT 50
    ");
    $history_stmt->execute();
    $history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
    ?>
    
    <div class="page-header">
        <h1 class="page-title">Import History</h1>
        <p class="page-subtitle">View past import batches</p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($history)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-2 text-muted">No import history found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Batch ID</th>
                                <th>Filename</th>
                                <th>Records Imported</th>
                                <th>Imported By</th>
                                <th>Import Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><strong>#<?= $h['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($h['filename']) ?></td>
                                    <td><span class="badge bg-primary"><?= $h['records_count'] ?> records</span></td>
                                    <td><?= htmlspecialchars($h['username'] ?? 'Unknown') ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($h['created_at'])) ?></td>
                                    <td class="text-center">
                                        <a href="index.php?page=minimum_wage_rates&filter_batch=<?= $h['id'] ?>" 
                                           class="btn btn-sm btn-info" title="View Records">
                                            <i class="bi bi-eye"></i> View Records
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-3">
                <a href="index.php?page=minimum_wage_rates&action=import" class="btn btn-primary">
                    <i class="bi bi-upload me-2"></i>New Import
                </a>
                <a href="index.php?page=minimum_wage_rates" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

<?php elseif ($action === 'map_psgc_result'): ?>
    <?php
    $results = $_SESSION['psgc_mapping_results'] ?? null;
    unset($_SESSION['psgc_mapping_results']); // Clear from session after reading

    if (!$results) {
        header("Location: index.php?page=minimum_wage_rates");
        exit;
    }
    ?>

    <div class="page-header">
        <h1 class="page-title">PSGC Mapping Results</h1>
        <p class="page-subtitle">Review which records were successfully mapped and which need manual verification</p>
    </div>

    <!-- Summary Card -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Successfully Mapped</h5>
                    <h2><?= $results['mapped'] ?></h2>
                    <p class="mb-0">Records updated with PSGC codes</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Failed to Map</h5>
                    <h2><?= $results['failed'] ?></h2>
                    <p class="mb-0">Needs manual verification</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title">Unchanged</h5>
                    <h2><?= $results['unchanged'] ?></h2>
                    <p class="mb-0">Already had correct codes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Successfully Mapped Records -->
    <?php if (!empty($results['mapped_items'])): ?>
    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-check-circle me-2"></i>Successfully Mapped Records (<?= count($results['mapped_items']) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Region</th>
                            <th>Province</th>
                            <th>City</th>
                            <th>Region Code</th>
                            <th>Province Code</th>
                            <th>City Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['mapped_items'] as $item): ?>
                        <tr>
                            <td><strong><?= escapeHtml($item['region']) ?></strong></td>
                            <td><?= escapeHtml($item['province']) ?></td>
                            <td><?= escapeHtml($item['city']) ?></td>
                            <td><span class="badge bg-primary"><?= escapeHtml($item['region_code'] ?? 'N/A') ?></span></td>
                            <td><span class="badge bg-info"><?= escapeHtml($item['province_code'] ?? 'N/A') ?></span></td>
                            <td><span class="badge bg-secondary"><?= escapeHtml($item['city_code'] ?? 'N/A') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Failed Records -->
    <?php if (!empty($results['failed_items'])): ?>
    <div class="card mb-3">
        <div class="card-header bg-warning">
            <h5 class="card-title mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>Failed to Map - Manual Verification Required (<?= count($results['failed_items']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <p class="text-muted">The following locations could not be automatically matched with PSGC codes. Please verify the names and update manually if needed.</p>
            <ul class="list-group">
                <?php foreach ($results['failed_items'] as $item): ?>
                <li class="list-group-item">
                    <i class="bi bi-x-circle text-danger me-2"></i><?= escapeHtml($item) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-3">
        <a href="index.php?page=minimum_wage_rates" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>

<?php elseif ($action === 'list'): ?>
    <div class="page-header">
        <h1 class="page-title">Minimum Wage Rates</h1>
        <p class="page-subtitle">Manage regional minimum wage rates with city-specific variations</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['warning'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filters Card -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Filter by Region</label>
                    <select id="filterRegion" class="form-control">
                        <option value="">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= escapeHtml($r) ?>"><?= escapeHtml($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Filter by Province</label>
                    <select id="filterProvince" class="form-control" disabled>
                        <option value="">All Provinces</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Filter by City</label>
                    <select id="filterCity" class="form-control" disabled>
                        <option value="">All Cities</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="button" id="clearFilters" class="btn btn-secondary w-100">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <a href="index.php?page=minimum_wage_rates&action=map_psgc_codes" class="btn btn-warning"
                   onclick="return confirm('This will populate PSGC codes and standardize region names for all records. Continue?')">
                    <i class="bi bi-geo-alt me-2"></i>Map PSGC Codes
                </a>
            </div>
        </div>
    </div>

    <?php
    // Separate rates into upcoming and current/historical
    $today = date('Y-m-d');
    $upcoming_rates = array_filter($rates, function($r) use ($today) {
        return $r['effective_date'] > $today;
    });
    $current_historical_rates = array_filter($rates, function($r) use ($today) {
        return $r['effective_date'] <= $today;
    });
    ?>

    <?php if (!empty($upcoming_rates)): ?>
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
                <i class="bi bi-calendar-event me-2"></i>Upcoming Wage Increases (<?= count($upcoming_rates) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="upcomingRatesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Region</th>
                            <th>Province</th>
                            <th>City</th>
                            <th>Daily Rate</th>
                            <th>Monthly Rate</th>
                            <th>Effective Date</th>
                            <th>Wage Order</th>
                            <th>Days Until Effective</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_rates as $r): ?>
                            <?php
                            $days_until = floor((strtotime($r['effective_date']) - time()) / 86400);
                            ?>
                            <tr class="table-warning">
                                <td><strong><?= escapeHtml($r['region']) ?></strong></td>
                                <td><?= escapeHtml($r['province'] ?? 'All') ?></td>
                                <td><?= escapeHtml($r['city'] ?? 'All') ?></td>
                                <td><strong class="text-success">₱<?= number_format($r['daily_rate'], 2) ?></strong></td>
                                <td>₱<?= number_format($r['daily_rate'] * 26, 2) ?></td>
                                <td><?= date('M d, Y', strtotime($r['effective_date'])) ?></td>
                                <td><small><?= escapeHtml($r['wage_order_number'] ?? 'N/A') ?></small></td>
                                <td><span class="badge bg-warning text-dark"><?= $days_until ?> days</span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?page=minimum_wage_rates&action=view&id=<?= $r['id'] ?>"
                                           class="btn btn-info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index.php?page=minimum_wage_rates&action=edit&id=<?= $r['id'] ?>"
                                           class="btn btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="index.php?page=minimum_wage_rates&action=delete&id=<?= $r['id'] ?>"
                                           class="btn btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this wage rate?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0">All Wage Rates (<?= count($rates) ?>)</h2>
            <div>
                <a href="index.php?page=minimum_wage_rates&action=import" class="btn btn-success me-2">
                    <i class="bi bi-upload me-2"></i>Bulk Import
                </a>
                <a href="index.php?page=minimum_wage_rates&action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add New Rate
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rates)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-cash-coin" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-2 text-muted">No wage rates found. Add your first rate to get started.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="wageRatesTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Region</th>
                                <th>Province</th>
                                <th>City</th>
                                <th>Daily Rate</th>
                                <th>Monthly Rate</th>
                                <th>Effective Date</th>
                                <th>Wage Order</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_historical_rates as $r): ?>
                                <?php
                                $row_class = '';
                                if ($r['is_current']) {
                                    $row_class = 'table-success';
                                }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><strong><?= escapeHtml($r['region']) ?></strong></td>
                                    <td><?= escapeHtml($r['province'] ?? 'All') ?></td>
                                    <td><?= escapeHtml($r['city'] ?? 'All') ?></td>
                                    <td><strong class="text-success">₱<?= number_format($r['daily_rate'], 2) ?></strong></td>
                                    <td>₱<?= number_format($r['daily_rate'] * 26, 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($r['effective_date'])) ?></td>
                                    <td><small><?= escapeHtml($r['wage_order_number'] ?? 'N/A') ?></small></td>
                                    <td>
                                        <?php if ($r['is_current']): ?>
                                            <span class="badge bg-success">Current</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Historical</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="index.php?page=minimum_wage_rates&action=view&id=<?= $r['id'] ?>"
                                               class="btn btn-info" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="index.php?page=minimum_wage_rates&action=edit&id=<?= $r['id'] ?>"
                                               class="btn btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="index.php?page=minimum_wage_rates&action=history&region=<?= urlencode($r['region']) ?>&province=<?= urlencode($r['province'] ?? '') ?>&city=<?= urlencode($r['city'] ?? '') ?>"
                                               class="btn btn-secondary" title="View History">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                            <a href="index.php?page=minimum_wage_rates&action=delete&id=<?= $r['id'] ?>"
                                               class="btn btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this wage rate?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Cascading dropdown and AJAX filtering
    const filterRegion = document.getElementById('filterRegion');
    const filterProvince = document.getElementById('filterProvince');
    const filterCity = document.getElementById('filterCity');
    const clearFiltersBtn = document.getElementById('clearFilters');

    // Store all wage rates data for filtering
    const allRates = <?= json_encode(array_values(array_merge($upcoming_rates, $current_historical_rates))) ?>;

    // Get unique provinces for a region
    function getProvincesForRegion(region) {
        if (!region) return [];
        const provinces = new Set();
        allRates.forEach(rate => {
            if (rate.region === region && rate.province) {
                provinces.add(rate.province);
            }
        });
        return Array.from(provinces).sort();
    }

    // Get unique cities for a province
    function getCitiesForProvince(region, province) {
        if (!province) return [];
        const cities = new Set();
        allRates.forEach(rate => {
            if (rate.region === region && rate.province === province && rate.city) {
                cities.add(rate.city);
            }
        });
        return Array.from(cities).sort();
    }

    // Handle region change
    filterRegion.addEventListener('change', function() {
        const selectedRegion = this.value;

        // Reset and populate province dropdown
        filterProvince.innerHTML = '<option value="">All Provinces</option>';
        filterCity.innerHTML = '<option value="">All Cities</option>';

        if (selectedRegion) {
            const provinces = getProvincesForRegion(selectedRegion);
            provinces.forEach(province => {
                const option = document.createElement('option');
                option.value = province;
                option.textContent = province;
                filterProvince.appendChild(option);
            });
            filterProvince.disabled = false;
        } else {
            filterProvince.disabled = true;
            filterCity.disabled = true;
        }

        filterTable();
    });

    // Handle province change
    filterProvince.addEventListener('change', function() {
        const selectedRegion = filterRegion.value;
        const selectedProvince = this.value;

        // Reset and populate city dropdown
        filterCity.innerHTML = '<option value="">All Cities</option>';

        if (selectedProvince) {
            const cities = getCitiesForProvince(selectedRegion, selectedProvince);
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                filterCity.appendChild(option);
            });
            filterCity.disabled = false;
        } else {
            filterCity.disabled = true;
        }

        filterTable();
    });

    // Handle city change
    filterCity.addEventListener('change', function() {
        filterTable();
    });

    // Clear all filters
    clearFiltersBtn.addEventListener('click', function() {
        filterRegion.value = '';
        filterProvince.value = '';
        filterProvince.disabled = true;
        filterCity.value = '';
        filterCity.disabled = true;
        filterTable();
    });

    // Filter table based on selected criteria
    function filterTable() {
        const selectedRegion = filterRegion.value;
        const selectedProvince = filterProvince.value;
        const selectedCity = filterCity.value;

        // Get all table rows
        const upcomingTable = document.querySelector('#upcomingRatesTable tbody');
        const mainTable = document.querySelector('#wageRatesTable tbody');

        // Filter upcoming rates table
        if (upcomingTable) {
            const rows = upcomingTable.querySelectorAll('tr');
            rows.forEach(row => {
                const region = row.cells[0]?.textContent.trim();
                const province = row.cells[1]?.textContent.trim();
                const city = row.cells[2]?.textContent.trim();

                let show = true;
                if (selectedRegion && region !== selectedRegion) show = false;
                if (selectedProvince && province !== selectedProvince && province !== 'All') show = false;
                if (selectedCity && city !== selectedCity && city !== 'All') show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        // Filter main rates table
        if (mainTable) {
            const rows = mainTable.querySelectorAll('tr');
            rows.forEach(row => {
                const region = row.cells[0]?.textContent.trim();
                const province = row.cells[1]?.textContent.trim();
                const city = row.cells[2]?.textContent.trim();

                let show = true;
                if (selectedRegion && region !== selectedRegion) show = false;
                if (selectedProvince && province !== selectedProvince && province !== 'All') show = false;
                if (selectedCity && city !== selectedCity && city !== 'All') show = false;

                row.style.display = show ? '' : 'none';
            });
        }
    }
    </script>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="page-header">
        <h1 class="page-title"><?= $action === 'add' ? 'Add New Wage Rate' : 'Edit Wage Rate' ?></h1>
        <p class="page-subtitle">Enter the minimum wage rate information below</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="index.php?page=minimum_wage_rates" id="wageRateForm">
                <input type="hidden" name="action" value="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="rate_id" value="<?= $rate['id'] ?>">
                <?php endif; ?>

                <!-- Location Information -->
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Location Information</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Region <span class="text-danger">*</span></label>
                        <select id="region" class="form-control" required>
                            <option value="">Select Region</option>
                        </select>
                        <input type="hidden" name="region" id="region_name">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Province</label>
                        <select id="province" class="form-control" disabled>
                            <option value="">All Provinces</option>
                        </select>
                        <input type="hidden" name="province" id="province_name">
                        <small class="text-muted">Leave blank if rate applies to entire region</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">City/Municipality</label>
                        <select id="city" class="form-control" disabled>
                            <option value="">All Cities</option>
                        </select>
                        <input type="hidden" name="city" id="city_name">
                        <small class="text-muted">Leave blank if rate applies to entire province</small>
                    </div>
                </div>

                <!-- Rate Information -->
                <h5 class="mb-3 mt-4"><i class="bi bi-cash-coin me-2"></i>Rate Information</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Daily Rate <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="daily_rate" id="daily_rate" class="form-control" required
                                   value="<?= htmlspecialchars($rate['daily_rate'] ?? '') ?>"
                                   step="0.01" min="0" placeholder="e.g., 610.00">
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Monthly Equivalent</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="text" id="monthly_rate" class="form-control" readonly 
                                   placeholder="Auto-calculated">
                        </div>
                        <small class="text-muted">Daily rate × 26 days</small>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Effective Date <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control" required
                               value="<?= htmlspecialchars($rate['effective_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Wage Order Number</label>
                        <input type="text" name="wage_order_number" class="form-control" 
                               value="<?= htmlspecialchars($rate['wage_order_number'] ?? '') ?>"
                               placeholder="e.g., NCR-24">
                    </div>

                    <div class="col-md-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_historical" value="1" 
                                   id="isHistorical" <?= ($action === 'edit' && !$rate['is_current']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isHistorical">
                                <strong>Mark as Historical Rate</strong>
                                <small class="text-muted d-block">
                                    Check this for past wage rates that are no longer active.<br>
                                    <strong>Note:</strong> Rates with future effective dates will automatically be marked as "pending" 
                                    until their effective date arrives.
                                </small>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Additional details about this wage order..."><?= htmlspecialchars($rate['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i><?= $action === 'add' ? 'Add Wage Rate' : 'Update Wage Rate' ?>
                    </button>
                    <a href="index.php?page=minimum_wage_rates" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize PSGC Address
        const psgcAddress = new PSGCAddress({
            prefix: '',
            apiUrl: '/hris/api/psgc-api.php'
        });

        <?php if ($action === 'edit' && $rate): ?>
        document.addEventListener('DOMContentLoaded', function() {
            psgcAddress.setValuesByName({
                region: '<?= escapeHtml($rate['region'] ?? '') ?>',
                province: '<?= escapeHtml($rate['province'] ?? '') ?>',
                city: '<?= escapeHtml($rate['city'] ?? '') ?>'
            });
        });
        <?php endif; ?>

        // Calculate monthly rate in real-time
        document.getElementById('daily_rate').addEventListener('input', function() {
            const dailyRate = parseFloat(this.value) || 0;
            const monthlyRate = dailyRate * 26;
            document.getElementById('monthly_rate').value = monthlyRate.toFixed(2);
        });

        // Initial calculation on page load
        <?php if ($action === 'edit' && $rate): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const dailyRate = parseFloat(<?= $rate['daily_rate'] ?>);
            document.getElementById('monthly_rate').value = (dailyRate * 26).toFixed(2);
        });
        <?php endif; ?>
    </script>

<?php elseif ($action === 'view'): ?>
    <div class="page-header">
        <h1 class="page-title">Wage Rate Details</h1>
        <p class="page-subtitle">View complete wage rate information</p>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0"><?= escapeHtml($rate['region']) ?> Wage Rate</h2>
            <div>
                <a href="index.php?page=minimum_wage_rates&action=edit&id=<?= $rate['id'] ?>" 
                   class="btn btn-warning">
                    <i class="bi bi-pencil me-2"></i>Edit
                </a>
                <a href="index.php?page=minimum_wage_rates" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Location Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Region:</th>
                            <td><strong><?= escapeHtml($rate['region']) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Province:</th>
                            <td><?= escapeHtml($rate['province'] ?? 'All Provinces') ?></td>
                        </tr>
                        <tr>
                            <th>City/Municipality:</th>
                            <td><?= escapeHtml($rate['city'] ?? 'All Cities') ?></td>
                        </tr>
                    </table>

                    <h5 class="mb-3 mt-4">Rate Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Daily Minimum Wage:</th>
                            <td><strong class="text-success fs-5">₱<?= number_format($rate['daily_rate'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Monthly Equivalent:</th>
                            <td><strong class="text-primary">₱<?= number_format($rate['daily_rate'] * 26, 2) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Effective Date:</th>
                            <td><?= date('F d, Y', strtotime($rate['effective_date'])) ?></td>
                        </tr>
                        <tr>
                            <th>Wage Order Number:</th>
                            <td><?= htmlspecialchars($rate['wage_order_number'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($rate['is_current']): ?>
                                    <span class="badge bg-success">Current Rate</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Historical Rate</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3">Additional Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Notes:</th>
                            <td><?= nl2br(htmlspecialchars($rate['notes'] ?? 'N/A')) ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?= date('F d, Y', strtotime($rate['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?= date('F d, Y', strtotime($rate['updated_at'])) ?></td>
                        </tr>
                    </table>

                    <div class="alert alert-info mt-4">
                        <h6><i class="bi bi-info-circle me-2"></i>Rate History</h6>
                        <p class="mb-0">
                            <a href="index.php?page=minimum_wage_rates&action=history&region=<?= urlencode($rate['region']) ?>&province=<?= urlencode($rate['province'] ?? '') ?>&city=<?= urlencode($rate['city'] ?? '') ?>" 
                               class="alert-link">
                                View all historical rates for this location →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'history'): ?>
    <?php
    $location_text = escapeHtml($_GET['region']);
    if (!empty($_GET['province'])) {
        $location_text .= ', ' . escapeHtml($_GET['province']);
    }
    if (!empty($_GET['city'])) {
        $location_text .= ', ' . escapeHtml($_GET['city']);
    }
    ?>
    
    <div class="page-header">
        <h1 class="page-title">Wage Rate History</h1>
        <p class="page-subtitle"><?= $location_text ?></p>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0">Historical Rates (<?= count($history_rates) ?>)</h2>
            <a href="index.php?page=minimum_wage_rates" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to List
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($history_rates)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-2 text-muted">No historical rates found for this location.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($history_rates as $index => $hr): ?>
                        <div class="timeline-item mb-4">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="text-end">
                                        <strong><?= date('F d, Y', strtotime($hr['effective_date'])) ?></strong>
                                        <br>
                                        <?php if ($hr['is_current']): ?>
                                            <span class="badge bg-success mt-1">Current</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <div class="card <?= $hr['is_current'] ? 'border-success' : '' ?>">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <h6 class="text-muted mb-1">Daily Rate</h6>
                                                    <h4 class="mb-0 text-success">₱<?= number_format($hr['daily_rate'], 2) ?></h4>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="text-muted mb-1">Monthly Equivalent</h6>
                                                    <h5 class="mb-0 text-primary">₱<?= number_format($hr['daily_rate'] * 26, 2) ?></h5>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="text-muted mb-1">Wage Order</h6>
                                                    <p class="mb-0"><?= htmlspecialchars($hr['wage_order_number'] ?? 'N/A') ?></p>
                                                </div>
                                            </div>
                                            <?php if (!empty($hr['notes'])): ?>
                                                <div class="mt-3">
                                                    <small class="text-muted"><?= nl2br(htmlspecialchars($hr['notes'])) ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($index < count($history_rates) - 1): ?>
                                                <?php 
                                                $next_rate = $history_rates[$index + 1];
                                                $increase = $hr['daily_rate'] - $next_rate['daily_rate'];
                                                $percent_increase = ($increase / $next_rate['daily_rate']) * 100;
                                                ?>
                                                <div class="mt-2">
                                                    <small class="text-success">
                                                        <i class="bi bi-arrow-up me-1"></i>
                                                        Increased by ₱<?= number_format($increase, 2) ?> 
                                                        (<?= number_format($percent_increase, 2) ?>%) from previous rate
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
?>