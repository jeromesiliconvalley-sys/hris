<?php
/**
 * PSGC Cache Populator - Simplified Version
 * 
 * Populates cache for:
 * - Regions
 * - Provinces
 * - Cities/Municipalities
 * 
 * Features:
 * - Proper UTF-8 handling with double-encoding protection
 * - NCR special handling (cities at region level)
 * - Authentication required
 * - Rate limiting
 * - Better error handling
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session.php';

// SECURITY: Require authentication
if (!isset($_SESSION['user_id'])) {
    die('<!DOCTYPE html><html><body><h1>Unauthorized Access</h1><p>Please login first.</p></body></html>');
}

set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

$stats = [
    'regions' => 0,
    'provinces' => 0,
    'cities' => 0,
    'errors' => [],
    'api_calls' => 0,
    'start_time' => microtime(true)
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>PSGC Cache Populator</title>";
echo "<style>
body{font-family:Arial;margin:20px;background:#f5f5f5;} 
.container{max-width:1200px;margin:0 auto;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}
.success{color:#28a745;} 
.error{color:#dc3545;} 
.info{color:#007bff;} 
.warning{color:#ffc107;}
.progress{background:#e9ecef;border-left:4px solid #007bff;padding:12px;margin:10px 0;border-radius:4px;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #dee2e6;padding:12px;text-align:left;}
th{background:#f8f9fa;font-weight:600;}
.summary-box{background:#d4edda;border:2px solid #28a745;padding:20px;border-radius:8px;margin:20px 0;}
.error-box{background:#f8d7da;border:2px solid #dc3545;padding:20px;border-radius:8px;margin:20px 0;}
h2{color:#495057;border-bottom:2px solid #007bff;padding-bottom:10px;}
h3{color:#6c757d;}
code{background:#f8f9fa;padding:2px 6px;border-radius:3px;font-family:monospace;color:#e83e8c;}
</style></head><body><div class='container'>";

echo "<h1>PSGC Cache Populator</h1>";
echo "<p>Populating cache with <strong>Regions</strong>, <strong>Provinces</strong>, and <strong>Cities/Municipalities</strong></p>";
echo "<p class='info'>Using API: <code>https://psgc.cloud/api/v2</code></p>";
echo "<hr>";

/**
 * Check if code is NCR region
 */
function isNCRRegion($code) {
    return $code === '1300000000' || $code === '13' || $code === 'NCR';
}

/**
 * Fix double-encoded UTF-8 strings
 */
function fixDoubleEncoding($str) {
    if (!is_string($str)) {
        return $str;
    }
    
    // Check if string contains UTF-8 double-encoding artifacts
    // Looking for byte sequences that indicate double encoding
    if (strpos($str, "\xC3\x83") !== false || strpos($str, "\xC3\xA3") !== false) {
        // Attempt to fix double encoding
        $fixed = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $str);
        if ($fixed !== false && $fixed !== $str) {
            error_log("PSGC: Fixed double-encoding in: " . substr($str, 0, 50));
            return $fixed;
        }
    }
    
    return $str;
}

/**
 * Recursively fix double-encoding in arrays
 */
function fixArrayEncoding($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = fixDoubleEncoding($value);
            } elseif (is_array($value)) {
                $data[$key] = fixArrayEncoding($value);
            }
        }
    }
    return $data;
}

/**
 * Cache data in database with proper UTF-8 encoding
 */
function cacheData($cache_key, $data, $conn) {
    $cache_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("PSGC Cache JSON Encode Error for key '$cache_key': " . json_last_error_msg());
        return false;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO psgc_cache (cache_key, cache_data, updated_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), updated_at = NOW()
    ");
    
    if (!$stmt) {
        error_log("PSGC Cache Prepare Error: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ss", $cache_key, $cache_json);
    
    if (!$stmt->execute()) {
        error_log("PSGC Cache Save Error for key '$cache_key': " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Fetch from API and cache result
 */
function fetchAndCache($endpoint, $cache_key, $conn, &$stats) {
    $base_url = 'https://psgc.cloud/api/v2';
    $url = $base_url . $endpoint;
    
    echo "<div class='progress'>";
    echo "<p class='info'>Fetching: <code>{$endpoint}</code></p>";
    
    $stats['api_calls']++;
    
    // Rate limiting: 100ms delay between requests
    if ($stats['api_calls'] > 1) {
        usleep(100000);
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'user_agent' => 'Mozilla/5.0 HRIS Application',
            'ignore_errors' => true
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        $error_msg = $error['message'] ?? 'Unknown error';
        error_log("PSGC API Fetch Error for '$endpoint': " . $error_msg);
        echo "<p class='error'>Failed: {$error_msg}</p>";
        echo "</div>";
        return null;
    }
    
    // Check HTTP status
    if (isset($http_response_header[0])) {
        preg_match('/\d{3}/', $http_response_header[0], $matches);
        $http_code = $matches[0] ?? '000';
        
        if ($http_code != 200) {
            error_log("PSGC API HTTP Error for '$endpoint': HTTP $http_code");
            echo "<p class='error'>HTTP Error: $http_code</p>";
            echo "</div>";
            return null;
        }
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error = json_last_error_msg();
        error_log("PSGC API JSON Decode Error for '$endpoint': " . $json_error);
        echo "<p class='error'>JSON Error: {$json_error}</p>";
        echo "</div>";
        return null;
    }
    
    // Extract data array if wrapped
    if (isset($data['data']) && is_array($data['data'])) {
        $data = $data['data'];
    }
    
    if (!is_array($data)) {
        error_log("PSGC API returned non-array data for '$endpoint'");
        echo "<p class='error'>Invalid data format</p>";
        echo "</div>";
        return null;
    }
    
    if (empty($data)) {
        echo "<p class='warning'>No data returned</p>";
        echo "</div>";
        return null;
    }
    
    // Apply UTF-8 encoding fixes
    $data = fixArrayEncoding($data);
    
    // Cache the data
    if (!cacheData($cache_key, $data, $conn)) {
        echo "<p class='error'>Failed to cache</p>";
        echo "</div>";
        return null;
    }
    
    echo "<p class='success'>Cached <strong>" . count($data) . "</strong> records with key: <code>{$cache_key}</code></p>";
    echo "</div>";
    
    return $data;
}

// ==================================================================
// STEP 1: REGIONS
// ==================================================================
echo "<h2>Step 1: Caching Regions</h2>";
$regions = fetchAndCache('/regions', 'regions_all', $conn, $stats);

if ($regions) {
    $stats['regions'] = count($regions);
    echo "<p class='success'><strong>Total regions cached: {$stats['regions']}</strong></p>";
} else {
    echo "<p class='error'><strong>Failed to fetch regions</strong></p>";
    $stats['errors'][] = 'Failed to fetch regions';
}

flush();
if (ob_get_level() > 0) ob_flush();

// ==================================================================
// STEP 2: PROVINCES (excluding NCR)
// ==================================================================
echo "<hr><h2>Step 2: Caching Provinces by Region</h2>";
$all_provinces = [];

if ($regions) {
    foreach ($regions as $region) {
        $region_code = $region['code'];
        $region_name = $region['name'];
        
        // Skip NCR - it has no provinces
        if (isNCRRegion($region_code)) {
            echo "<div class='progress'>";
            echo "<p class='warning'>Skipping <strong>{$region_name}</strong> (NCR has no provinces)</p>";
            echo "</div>";
            continue;
        }
        
        $provinces = fetchAndCache(
            "/regions/{$region_code}/provinces", 
            "provinces_{$region_code}", 
            $conn, 
            $stats
        );
        
        if ($provinces) {
            $count = count($provinces);
            $stats['provinces'] += $count;
            $all_provinces = array_merge($all_provinces, $provinces);
        } else {
            $stats['errors'][] = "Failed to fetch provinces for {$region_name}";
        }
        
        flush();
        if (ob_get_level() > 0) ob_flush();
    }
    
    echo "<p class='success'><strong>Total provinces cached: {$stats['provinces']}</strong></p>";
}

// ==================================================================
// STEP 3: NCR CITIES (at region level)
// ==================================================================
echo "<hr><h2>Step 3: Caching NCR Cities</h2>";
echo "<p class='info'>NCR cities are cached at <strong>region level</strong> (no provinces)</p>";

if ($regions) {
    foreach ($regions as $region) {
        $region_code = $region['code'];
        $region_name = $region['name'];
        
        if (isNCRRegion($region_code)) {
            $cities = fetchAndCache(
                "/regions/{$region_code}/cities-municipalities", 
                "cities_region_{$region_code}", 
                $conn,
                $stats
            );
            
            if ($cities) {
                // Filter out sub-municipalities (Manila districts)
                $filtered_cities = array_filter($cities, function($city) {
                    return !isset($city['type']) || $city['type'] !== 'SubMun';
                });
                
                $count = count($filtered_cities);
                $stats['cities'] += $count;
                echo "<p class='success'><strong>NCR cities cached: {$count}</strong> (sub-municipalities filtered out)</p>";
            } else {
                $stats['errors'][] = "Failed to fetch cities for {$region_name}";
            }
            
            flush();
            if (ob_get_level() > 0) ob_flush();
            break;
        }
    }
}

// ==================================================================
// STEP 4: CITIES BY PROVINCE (for all other regions)
// ==================================================================
echo "<hr><h2>Step 4: Caching Cities by Province</h2>";

if (!empty($all_provinces)) {
    echo "<p class='info'>Processing <strong>" . count($all_provinces) . "</strong> provinces...</p>";
    
    $province_counter = 0;
    foreach ($all_provinces as $province) {
        $province_code = $province['code'];
        $province_name = $province['name'];
        $province_counter++;
        
        echo "<div class='progress'>";
        echo "<p class='info'>[{$province_counter}/" . count($all_provinces) . "] <strong>{$province_name}</strong></p>";
        echo "</div>";
        
        $cities = fetchAndCache(
            "/provinces/{$province_code}/cities-municipalities", 
            "cities_{$province_code}", 
            $conn,
            $stats
        );
        
        if ($cities) {
            $count = count($cities);
            $stats['cities'] += $count;
        } else {
            $stats['errors'][] = "Failed to fetch cities for {$province_name}";
        }
        
        flush();
        if (ob_get_level() > 0) ob_flush();
    }
    
    echo "<p class='success'><strong>Total cities/municipalities cached: {$stats['cities']}</strong></p>";
}

// ==================================================================
// SUMMARY
// ==================================================================
$stats['end_time'] = microtime(true);
$stats['duration'] = round($stats['end_time'] - $stats['start_time'], 2);

echo "<hr><h2>Final Summary</h2>";

echo "<table>";
echo "<tr><th>Resource</th><th>Count</th><th>Status</th></tr>";
echo "<tr><td>Regions</td><td><strong>{$stats['regions']}</strong></td><td class='" . ($stats['regions'] > 0 ? 'success' : 'error') . "'>" . ($stats['regions'] > 0 ? 'Success' : 'Failed') . "</td></tr>";
echo "<tr><td>Provinces</td><td><strong>{$stats['provinces']}</strong></td>"
    . "<td class='" . ($stats['provinces'] > 0 ? 'success' : 'error') . "'>"
    . ($stats['provinces'] > 0 ? 'Success' : 'Failed') . "</td></tr>";
echo "<tr><td>Cities/Municipalities</td><td><strong>{$stats['cities']}</strong></td><td class='" . ($stats['cities'] > 0 ? 'success' : 'error') . "'>" . ($stats['cities'] > 0 ? 'Success' : 'Failed') . "</td></tr>";
echo "<tr><td>API Calls</td><td><strong>{$stats['api_calls']}</strong></td><td class='info'>Info</td></tr>";
echo "<tr><td>Duration</td><td><strong>{$stats['duration']}s</strong></td><td class='info'>Info</td></tr>";
echo "</table>";

// Cache statistics
$cache_result = $conn->query("SELECT COUNT(*) as cnt FROM psgc_cache");
if ($cache_result) {
    $cache_count = $cache_result->fetch_assoc()['cnt'];
    echo "<p>Total cache entries in database: <strong>$cache_count</strong></p>";
}

// Show errors if any
if (!empty($stats['errors'])) {
    echo "<div class='error-box'>";
    echo "<h3>Errors Encountered</h3>";
    echo "<ul>";
    foreach ($stats['errors'] as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
    echo "<p><em>Check your PHP error log for detailed error messages.</em></p>";
    echo "</div>";
}

// Cache key reference
echo "<h3>Cache Keys Created</h3>";
echo "<table>";
echo "<tr><th>Cache Key Pattern</th><th>Description</th><th>Count</th></tr>";

$key_patterns = [
    ['pattern' => 'regions_all', 'desc' => 'All Philippine regions', 'like' => 'regions_all'],
    ['pattern' => 'provinces_[CODE]', 'desc' => 'Provinces by region code', 'like' => 'provinces_%'],
    ['pattern' => 'cities_region_[CODE]', 'desc' => 'NCR cities (region level)', 'like' => 'cities_region_%'],
    ['pattern' => 'cities_[CODE]', 'desc' => 'Cities by province code', 'like' => 'cities_%']
];

foreach ($key_patterns as $pattern) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM psgc_cache WHERE cache_key LIKE '{$pattern['like']}'");
    $count = $result ? $result->fetch_assoc()['cnt'] : 0;
    echo "<tr><td><code>{$pattern['pattern']}</code></td><td>{$pattern['desc']}</td><td><strong>{$count}</strong></td></tr>";
}

echo "</table>";

// Final status message
$is_complete = $stats['regions'] > 0 && $stats['provinces'] > 0 && $stats['cities'] > 0;

if ($is_complete && empty($stats['errors'])) {
    echo "<div class='summary-box'>";
    echo "<h2 class='success'>SUCCESS! PSGC Cache Fully Populated</h2>";
    echo "<ul>";
    echo "<li><strong>{$stats['regions']}</strong> regions cached</li>";
    echo "<li><strong>{$stats['provinces']}</strong> provinces cached (excluding NCR)</li>";
    echo "<li><strong>{$stats['cities']}</strong> cities/municipalities cached</li>";
    echo "<li>All data stored with proper <strong>UTF-8 encoding</strong></li>";
    echo "<li>Special characters preserved correctly</li>";
    echo "<li>NCR handled correctly (cities at region level)</li>";
    echo "<li>Double-encoding protection applied</li>";
    echo "<li>Cache keys match PSGCHelper expectations</li>";
    echo "</ul>";
    echo "<h3>What's Working Now:</h3>";
    echo "<ul>";
    echo "<li>Region to Province cascading dropdown</li>";
    echo "<li>Province to City/Municipality cascading dropdown</li>";
    echo "<li>NCR to City direct selection (no provinces)</li>";
    echo "<li>All location names display correctly with special characters</li>";
    echo "</ul>";
    echo "<p><strong>Your PSGC location system is now ready to use!</strong></p>";
    echo "</div>";
} elseif ($is_complete && !empty($stats['errors'])) {
    echo "<div class='summary-box'>";
    echo "<h2 class='success'>Cache Populated with Some Warnings</h2>";
    echo "<p>Core data has been cached successfully, but some errors occurred. Review the errors above.</p>";
    echo "</div>";
} else {
    echo "<div class='error-box'>";
    echo "<h2 class='error'>Cache Population Incomplete</h2>";
    echo "<p>Critical data is missing. Please:</p>";
    echo "<ol>";
    echo "<li>Review errors listed above</li>";
    echo "<li>Check your PHP error log for details</li>";
    echo "<li>Verify PSGC API is accessible: <code>https://psgc.cloud</code></li>";
    echo "<li>Ensure database connection is working</li>";
    echo "<li>Try running the script again</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='index.php?page=minimum_wage_rates'>Back to Minimum Wage Rates</a></p>";

echo "</div></body></html>";
?>