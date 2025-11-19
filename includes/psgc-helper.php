<?php
/**
 * PSGC Helper Class
 * 
 * Philippine Standard Geographic Code (PSGC) Helper
 * Handles address dropdown data from PSGC Cloud API v2
 * 
 * Features:
 * - Region, Province, City/Municipality, Barangay data
 * - 30-day caching in database
 * - Special handling for Manila sub-municipalities
 * - UTF-8 encoding fixes
 * 
 * @package HRIS
 * @version 2.1
 */

// FIXED: Add namespace to prevent conflicts
namespace HRIS\Helpers;

class PSGCHelper {
    private $conn;
    private $base_url = 'https://psgc.cloud/api/v2';
    
    // FIXED: Make cache duration configurable
    private $cache_duration;
    private $api_rate_limit = 100; // Requests per hour
    private $api_requests_made = 0;
    
    /**
     * Constructor
     * 
     * @param mysqli $db_connection Database connection (optional, will use global if not provided)
     * @param int $cache_duration Cache duration in seconds (default 30 days)
     */
    public function __construct($db_connection = null, $cache_duration = 2592000) {
        // FIXED: Use getDbConnection if no connection provided
        if ($db_connection === null) {
            $this->conn = getDbConnection();
        } else {
            $this->conn = $db_connection;
        }
        
        $this->cache_duration = $cache_duration;
        
        // Force UTF-8 on connection
        $this->conn->set_charset("utf8mb4");
    }

    /**
     * Get cached data or fetch from API
     * FIXED: Better error handling and logging
     */
    private function getCachedOrFetch($cache_key, $api_endpoint) {
        // Check cache first
        $stmt = $this->conn->prepare("SELECT cache_data, UNIX_TIMESTAMP(updated_at) as updated_at 
                                       FROM psgc_cache WHERE cache_key = ?");
        
        if (!$stmt) {
            error_log("PSGC Error: Failed to prepare cache query - " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $cache_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $cache_age = time() - $row['updated_at'];
            
            if ($cache_age < $this->cache_duration) {
                $stmt->close();
                $data = json_decode($row['cache_data'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("PSGC Error: Invalid JSON in cache for key: {$cache_key}");
                    return null;
                }
                
                return $data;
            }
        }
        $stmt->close();

        // FIXED: Check rate limiting before API call
        if ($this->api_requests_made >= $this->api_rate_limit) {
            error_log("PSGC Warning: API rate limit reached ({$this->api_rate_limit}/hour)");
            // Return cached data even if old, better than nothing
            if (isset($row['cache_data'])) {
                return json_decode($row['cache_data'], true);
            }
            return null;
        }

        // Fetch from API
        $data = $this->fetchFromAPI($api_endpoint);
        
        if ($data !== null) {
            $this->cacheData($cache_key, $data);
            $this->api_requests_made++;
        } else {
            // FIXED: If API fails, use old cache if available
            if (isset($row['cache_data'])) {
                error_log("PSGC Warning: API failed, using stale cache for {$cache_key}");
                return json_decode($row['cache_data'], true);
            }
        }

        return $data;
    }

    /**
     * Fix double-encoded UTF-8 strings
     * Sometimes server configs cause UTF-8 to be encoded twice
     */
    private function fixDoubleEncoding($str) {
        if (!is_string($str)) {
            return $str;
        }
        
        // Try to detect and fix double encoding
        // If string contains UTF-8 artifacts like "ÃƒÂ±" for "ñ"
        if (preg_match('/ÃƒÂ±|ÃƒÂ©|ÃƒÂ³|ÃƒÂ­|ÃƒÂº|ÃƒÂ¡/u', $str)) {
            // String is likely double-encoded
            // Decode it back to single encoding
            $fixed = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
            return $fixed;
        }
        
        return $str;
    }
    
    /**
     * Recursively fix double-encoding in arrays
     */
    private function fixArrayEncoding($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = $this->fixDoubleEncoding($value);
                } elseif (is_array($value)) {
                    $data[$key] = $this->fixArrayEncoding($value);
                }
            }
        }
        return $data;
    }

    /**
     * Fetch data from PSGC Cloud API v2
     * FIXED: Better error handling and timeout management
     */
    private function fetchFromAPI($endpoint) {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // FIXED: Increased timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Simplified headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: HRIS-PSGC-Client/2.1'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        // FIXED: Better error handling
        if ($curl_errno !== 0) {
            error_log("PSGC CURL Error [{$curl_errno}]: {$curl_error} for URL: {$url}");
            return null;
        }

        if ($http_code !== 200) {
            error_log("PSGC HTTP Error {$http_code} for URL: {$url}");
            return null;
        }

        if (!$response) {
            error_log("PSGC Error: Empty response for URL: {$url}");
            return null;
        }

        // Decode JSON
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("PSGC JSON Decode Error: " . json_last_error_msg() . " for URL: {$url}");
            return null;
        }
        
        // Handle both response formats
        if (isset($decoded['data'])) {
            $decoded = $decoded['data'];
        }
        
        // Fix any double-encoding issues
        $decoded = $this->fixArrayEncoding($decoded);
        
        return $decoded;
    }

    /**
     * Cache data in database with proper UTF-8 encoding
     */
    private function cacheData($cache_key, $data) {
        // Use JSON_UNESCAPED_UNICODE to preserve UTF-8 characters
        $cache_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("PSGC Cache JSON Encode Error: " . json_last_error_msg());
            return false;
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO psgc_cache (cache_key, cache_data, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), updated_at = NOW()
        ");
        
        if (!$stmt) {
            error_log("PSGC Error: Failed to prepare cache insert - " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("ss", $cache_key, $cache_json);
        
        if (!$stmt->execute()) {
            error_log("PSGC Cache Save Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        return true;
    }

    /**
     * Get all regions
     * Endpoint: /regions
     */
    public function getRegions() {
        $data = $this->getCachedOrFetch('regions_all', '/regions');
        
        if (is_array($data)) {
            usort($data, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        
        return $data ?? [];
    }

    /**
     * Get provinces by region code
     * Endpoint: /regions/{regionCode}/provinces
     */
    public function getProvinces($region_code) {
        $data = $this->getCachedOrFetch("provinces_{$region_code}", "/regions/{$region_code}/provinces");
        
        if (is_array($data)) {
            usort($data, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        
        return $data ?? [];
    }

    /**
     * Check if code is NCR region
     */
    private function isNCRRegion($code) {
        return $code === '1300000000' || $code === '13' || $code === 'NCR';
    }

    /**
     * Check if this is Manila city (has sub-municipalities)
     */
    private function isManilaCity($code_or_name) {
        $manila_codes = ['1380600000', '137600000'];
        $manila_names = ['City of Manila', 'Manila'];
        
        return in_array($code_or_name, $manila_codes) || 
               in_array($code_or_name, $manila_names);
    }

    /**
     * Get cities/municipalities by province code OR region code (for NCR)
     * Filters out sub-municipalities (Manila districts)
     * Endpoint: /provinces/{provinceCode}/cities-municipalities
     * For NCR: /regions/{regionCode}/cities-municipalities
     */
    public function getCitiesMunicipalities($code) {
        if ($this->isNCRRegion($code)) {
            $cities = $this->getCachedOrFetch("cities_region_{$code}", "/regions/{$code}/cities-municipalities");
        } else {
            $cities = $this->getCachedOrFetch("cities_{$code}", "/provinces/{$code}/cities-municipalities");
        }
        
        if (is_array($cities)) {
            // Filter out sub-municipalities (type: "SubMun")
            $cities = array_filter($cities, function($city) {
                return !isset($city['type']) || $city['type'] !== 'SubMun';
            });
            
            // Re-index array after filtering
            $cities = array_values($cities);
            
            usort($cities, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        
        return $cities ?? [];
    }

    /**
     * Get sub-municipalities for Manila
     * These are the districts like Quiapo, Sampaloc, Tondo, etc.
     */
    private function getManilaSubMunicipalities() {
        $cached = $this->getCachedOrFetch(
            "submun_ncr", 
            "/regions/1300000000/sub-municipalities"
        );
        
        return $cached ?? [];
    }

    /**
     * Get barangays by city/municipality code
     * 
     * FIXED: Special handling for Manila - fetches from all sub-municipalities automatically
     */
    public function getBarangays($city_municipality_code) {
        $city_municipality_code = trim($city_municipality_code);
        
        // SPECIAL CASE: Manila - fetch barangays from all sub-municipalities
        if ($this->isManilaCity($city_municipality_code)) {
            return $this->getAllManilaBarangays($city_municipality_code);
        }
        
        // Regular cities/municipalities
        $barangays = $this->getCachedOrFetch(
            "barangays_{$city_municipality_code}", 
            "/cities-municipalities/{$city_municipality_code}/barangays"
        );
        
        if (is_array($barangays) && !empty($barangays)) {
            usort($barangays, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        
        return $barangays ?? [];
    }

    /**
     * Get ALL barangays from Manila by fetching from all sub-municipalities
     * FIXED: Simplified logic and better caching
     */
    private function getAllManilaBarangays($city_code) {
        // Check if we have cached aggregated Manila barangays
        $cache_key = "barangays_manila_all";
        
        $stmt = $this->conn->prepare("SELECT cache_data, UNIX_TIMESTAMP(updated_at) as updated_at 
                                       FROM psgc_cache WHERE cache_key = ?");
        $stmt->bind_param("s", $cache_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $cache_age = time() - $row['updated_at'];
            
            if ($cache_age < $this->cache_duration) {
                $cached_data = json_decode($row['cache_data'], true);
                $stmt->close();
                if (!empty($cached_data)) {
                    return $cached_data;
                }
            }
        }
        $stmt->close();
        
        // Fetch fresh data
        error_log("PSGC: Fetching Manila barangays from all sub-municipalities...");
        
        $all_barangays = [];
        
        // Get all sub-municipalities of Manila
        $sub_municipalities = $this->getManilaSubMunicipalities();
        
        if (empty($sub_municipalities)) {
            error_log("PSGC: No sub-municipalities found for Manila");
            return [];
        }
        
        error_log("PSGC: Found " . count($sub_municipalities) . " Manila sub-municipalities");
        
        // Fetch barangays from each sub-municipality
        foreach ($sub_municipalities as $submun) {
            $submun_code = $submun['code'];
            $submun_name = $submun['name'];
            
            // Sub-municipalities use cities-municipalities endpoint
            $barangays = $this->fetchFromAPI("/cities-municipalities/{$submun_code}/barangays");
            
            if (is_array($barangays) && !empty($barangays)) {
                error_log("PSGC: Found " . count($barangays) . " barangays in {$submun_name}");
                $all_barangays = array_merge($all_barangays, $barangays);
            }
        }
        
        // Remove duplicates based on code
        $unique_barangays = [];
        foreach ($all_barangays as $brgy) {
            if (isset($brgy['code'])) {
                $unique_barangays[$brgy['code']] = $brgy;
            }
        }
        $all_barangays = array_values($unique_barangays);
        
        // Sort alphabetically
        usort($all_barangays, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        error_log("PSGC: Total unique Manila barangays: " . count($all_barangays));
        
        // Cache the aggregated result
        if (!empty($all_barangays)) {
            $this->cacheData($cache_key, $all_barangays);
        }
        
        return $all_barangays;
    }

    /**
     * Get region by code
     * Endpoint: /regions/{code}
     */
    public function getRegion($region_code) {
        return $this->getCachedOrFetch("region_{$region_code}", "/regions/{$region_code}");
    }

    /**
     * Get province by code
     * Endpoint: /provinces/{code}
     */
    public function getProvince($province_code) {
        return $this->getCachedOrFetch("province_{$province_code}", "/provinces/{$province_code}");
    }

    /**
     * Get city/municipality by code
     * Endpoint: /cities-municipalities/{code}
     */
    public function getCityMunicipality($city_code) {
        return $this->getCachedOrFetch("city_{$city_code}", "/cities-municipalities/{$city_code}");
    }

    /**
     * Get barangay by code
     * Endpoint: /barangays/{code}
     */
    public function getBarangay($barangay_code) {
        return $this->getCachedOrFetch("barangay_{$barangay_code}", "/barangays/{$barangay_code}");
    }

    /**
     * Search for a location
     * FIXED: Use SQL LIKE instead of loading all into memory
     */
    public function searchLocation($query) {
        $query = trim($query);
        
        if (strlen($query) < 2) {
            return [];
        }
        
        $search_term = '%' . $query . '%';
        
        $stmt = $this->conn->prepare("
            SELECT cache_key, cache_data 
            FROM psgc_cache 
            WHERE cache_data LIKE ?
            LIMIT 50
        ");
        
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $data = json_decode($row['cache_data'], true);
            if (is_array($data)) {
                if (isset($data['name']) && stripos($data['name'], $query) !== false) {
                    $matches[] = $data;
                } else {
                    foreach ($data as $item) {
                        if (isset($item['name']) && stripos($item['name'], $query) !== false) {
                            $matches[] = $item;
                            if (count($matches) >= 20) break 2;
                        }
                    }
                }
            }
        }
        $stmt->close();
        
        // Remove duplicates
        $unique = [];
        foreach ($matches as $match) {
            if (isset($match['code'])) {
                $unique[$match['code']] = $match;
            }
        }
        
        return array_slice(array_values($unique), 0, 20);
    }

    /**
     * Clear all cached data
     */
    public function clearCache() {
        $this->conn->query("TRUNCATE TABLE psgc_cache");
    }

    /**
     * Clear specific cache
     */
    public function clearSpecificCache($cache_key) {
        $stmt = $this->conn->prepare("DELETE FROM psgc_cache WHERE cache_key = ?");
        $stmt->bind_param("s", $cache_key);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clear old cache (older than cache_duration)
     */
    public function clearOldCache() {
        $stmt = $this->conn->prepare("
            DELETE FROM psgc_cache 
            WHERE UNIX_TIMESTAMP(updated_at) < ?
        ");
        $cutoff = time() - $this->cache_duration;
        $stmt->bind_param("i", $cutoff);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clear Manila barangay cache specifically
     * Use this if Manila barangays need to be refreshed
     */
    public function clearManilaBarangayCache() {
        $this->clearSpecificCache("barangays_manila_all");
        $this->clearSpecificCache("submun_ncr");
    }
    
    /**
     * FIXED: Add method to warm up cache
     * Useful for initial setup or after cache clear
     */
    public function warmUpCache() {
        error_log("PSGC: Starting cache warm-up...");
        
        // Get all regions first
        $regions = $this->getRegions();
        error_log("PSGC: Cached " . count($regions) . " regions");
        
        // For each region, get provinces and cities
        foreach ($regions as $region) {
            if ($this->isNCRRegion($region['code'])) {
                // NCR has cities directly
                $cities = $this->getCitiesMunicipalities($region['code']);
                error_log("PSGC: Cached " . count($cities) . " NCR cities");
            } else {
                // Get provinces for this region
                $provinces = $this->getProvinces($region['code']);
                error_log("PSGC: Cached " . count($provinces) . " provinces for " . $region['name']);
                
                // Rate limiting check
                if ($this->api_requests_made >= $this->api_rate_limit) {
                    error_log("PSGC: Rate limit reached, stopping warm-up");
                    break;
                }
            }
        }
        
        error_log("PSGC: Cache warm-up completed");
    }
}