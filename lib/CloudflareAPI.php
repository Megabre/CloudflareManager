<?php
/**
 * Cloudflare API Client - Professional Implementation
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     2.0.0
 */

namespace CloudflareManager;

use Exception;
use WHMCS\Database\Capsule;

if (!class_exists('CloudflareManager\CloudflareAPI')) {
class CloudflareAPI {
    const API_BASE_URL = "https://api.cloudflare.com/client/v4/";
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_CONNECT_TIMEOUT = 10;
    
    protected $email;
    protected $apiKey;
    protected $useCache = true;
    protected $cacheExpiry = 300;
    protected $debug = false;
    protected $lastError = null;
    
    /**
     * Constructor
     */
    public function __construct($email, $apiKey, $cacheSettings = []) {
        $this->email = trim($email);
        $this->apiKey = trim($apiKey);
        
        if (isset($cacheSettings['use_cache'])) {
            $this->useCache = ($cacheSettings['use_cache'] == 'on' || 
                              $cacheSettings['use_cache'] == 'yes' || 
                              $cacheSettings['use_cache'] == '1');
        }
        
        if (isset($cacheSettings['cache_expiry']) && intval($cacheSettings['cache_expiry']) >= 60) {
            $this->cacheExpiry = intval($cacheSettings['cache_expiry']);
        }
        
        if (empty($this->apiKey)) {
            throw new Exception("API key is required");
        }
    }
    
    /**
     * Enable debug mode
     */
    public function enableDebug() {
        $this->debug = true;
        return $this;
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Make API request
     */
    public function request($endpoint, $method = "GET", $data = []) {
        $cacheEnabled = ($method === "GET" && $this->useCache);
        
        // Check cache for GET requests
        if ($cacheEnabled) {
            $cacheKey = md5($endpoint . json_encode($data));
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                $this->logDebug("Cache HIT: {$endpoint}");
                return $cached;
            }
        }
        
        $url = self::API_BASE_URL . ltrim($endpoint, '/');
        $this->logDebug("API Request: {$method} {$url}");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        // Set authentication headers
        $headers = ["Content-Type: application/json"];
        if (empty($this->email)) {
            // API Token authentication
            $headers[] = "Authorization: Bearer " . $this->apiKey;
            $this->logDebug("Using API Token authentication");
        } else {
            // Global API Key authentication
            $headers[] = "X-Auth-Email: " . $this->email;
            $headers[] = "X-Auth-Key: " . $this->apiKey;
            $this->logDebug("Using Global API Key authentication");
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set request method and data
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
            
            if (!empty($data)) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                $this->logDebug("Request Body: " . $jsonData);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors
        if ($error) {
            $this->lastError = "cURL Error: " . $error;
            throw new Exception($this->lastError);
        }
        
        // Parse response
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = "JSON decode error: " . json_last_error_msg();
            $this->logDebug("Raw response: " . substr($response, 0, 500));
            throw new Exception($this->lastError);
        }
        
        // Log full response in debug mode
        if ($this->debug) {
            $this->logDebug("API Response (HTTP {$httpCode}): " . json_encode($responseData));
        }
        
        // Check API response
        if (!isset($responseData["success"]) || $responseData["success"] !== true) {
            $errorMessages = [];
            if (isset($responseData["errors"]) && is_array($responseData["errors"])) {
                foreach ($responseData["errors"] as $error) {
                    $msg = isset($error["message"]) ? $error["message"] : "Unknown error";
                    $code = isset($error["code"]) ? " (Code: {$error["code"]})" : "";
                    $errorMessages[] = $msg . $code;
                }
            } else {
                $errorMessages[] = "API returned success=false but no error details";
            }
            $this->lastError = "Cloudflare API Error: " . implode(", ", $errorMessages);
            $this->logDebug("API Error Response: " . json_encode($responseData));
            throw new Exception($this->lastError);
        }
        
        // Extract result - Cloudflare API always returns result in "result" field
        $result = isset($responseData["result"]) ? $responseData["result"] : null;
        
        // Log result info if available
        if (isset($responseData["result_info"]) && $this->debug) {
            $this->logDebug("Result Info: " . json_encode($responseData["result_info"]));
        }
        
        // Cache result for GET requests
        if ($cacheEnabled && $result !== null) {
            $this->setCache($cacheKey, $result);
        }
        
        return $result;
    }
    
    /**
     * Get cache
     */
    protected function getCache($key) {
        try {
            $cache = Capsule::table('mod_cloudflaremanager_cache')
                ->where('cache_key', $key)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first(['cache_value']);
            
            if ($cache) {
                return json_decode($cache->cache_value, true);
            }
        } catch (Exception $e) {
            $this->logDebug("Cache Error: " . $e->getMessage());
        }
        return null;
    }
    
    /**
     * Set cache
     */
    protected function setCache($key, $value) {
        try {
            $now = date('Y-m-d H:i:s');
            $expiry = date('Y-m-d H:i:s', time() + $this->cacheExpiry);
            
            $exists = Capsule::table('mod_cloudflaremanager_cache')
                ->where('cache_key', $key)
                ->exists();
            
            if ($exists) {
                Capsule::table('mod_cloudflaremanager_cache')
                    ->where('cache_key', $key)
                    ->update([
                        'cache_value' => json_encode($value),
                        'expires_at' => $expiry,
                        'updated_at' => $now
                    ]);
            } else {
                Capsule::table('mod_cloudflaremanager_cache')->insert([
                    'cache_key' => $key,
                    'cache_value' => json_encode($value),
                    'expires_at' => $expiry,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }
        } catch (Exception $e) {
            $this->logDebug("Cache Save Error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear cache for zone
     */
    public function clearCacheForZone($zoneId) {
        try {
            Capsule::table('mod_cloudflaremanager_cache')
                ->where('cache_key', 'LIKE', '%' . $zoneId . '%')
                ->delete();
            return true;
        } catch (Exception $e) {
            $this->logDebug("Cache Clear Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearAllCache() {
        try {
            Capsule::table('mod_cloudflaremanager_cache')->truncate();
            return true;
        } catch (Exception $e) {
            $this->logDebug("All Cache Clear Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            if (empty($this->email)) {
                // API Token verification
                $this->request("user/tokens/verify");
            } else {
                // Global API Key - get user info
                $this->request("user");
            }
            return true;
        } catch (Exception $e) {
            throw new Exception("Connection test failed: " . $e->getMessage());
        }
    }
    
    /**
     * List all zones (domains)
     * Cloudflare API returns: {"success": true, "result": [...zones...], "result_info": {...}}
     */
    public function listZones($page = 1, $perPage = 50, $direction = 'desc', $order = 'name') {
        try {
            $params = http_build_query([
                'page' => $page,
                'per_page' => $perPage,
                'direction' => $direction,
                'order' => $order
            ]);
            
            $this->logDebug("Fetching zones page {$page} with per_page {$perPage}");
            $result = $this->request("zones?{$params}");
            
            // The request() method already extracts the "result" field from API response
            // So $result should be an array of zones directly
            if (is_array($result)) {
                $this->logDebug("Received " . count($result) . " zones");
                return $result;
            }
            
            // If result is not an array, log and return empty array
            $this->logDebug("listZones returned non-array. Type: " . gettype($result) . ", Value: " . json_encode($result));
            return [];
        } catch (Exception $e) {
            $this->logDebug("listZones error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get zone details
     */
    public function getZone($zoneId) {
        return $this->request("zones/{$zoneId}");
    }
    
    /**
     * List DNS records for a zone
     */
    public function listDnsRecords($zoneId, $page = 1, $perPage = 100, $type = '') {
        $params = ['page' => $page, 'per_page' => $perPage];
        if (!empty($type)) {
            $params['type'] = $type;
        }
        $queryString = http_build_query($params);
        return $this->request("zones/{$zoneId}/dns_records?{$queryString}");
    }
    
    /**
     * Get single DNS record
     */
    public function getDnsRecord($zoneId, $recordId) {
        return $this->request("zones/{$zoneId}/dns_records/{$recordId}");
    }
    
    /**
     * Create DNS record
     */
    public function createDnsRecord($zoneId, $data) {
        try {
            $this->logDebug("Creating DNS record for zone {$zoneId}: " . json_encode($data));
            $result = $this->request("zones/{$zoneId}/dns_records", "POST", $data);
            $this->clearCacheForZone($zoneId);
            $this->logDebug("DNS record created successfully");
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Error creating DNS record: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update DNS record
     */
    public function updateDnsRecord($zoneId, $recordId, $data) {
        try {
            $this->logDebug("Updating DNS record {$recordId} for zone {$zoneId}: " . json_encode($data));
            $result = $this->request("zones/{$zoneId}/dns_records/{$recordId}", "PUT", $data);
            $this->clearCacheForZone($zoneId);
            $this->logDebug("DNS record updated successfully");
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Error updating DNS record: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete DNS record
     */
    public function deleteDnsRecord($zoneId, $recordId) {
        $result = $this->request("zones/{$zoneId}/dns_records/{$recordId}", "DELETE");
        $this->clearCacheForZone($zoneId);
        return $result;
    }
    
    /**
     * Purge zone cache
     */
    public function purgeCache($zoneId, $purgeEverything = true, $files = [], $tags = [], $hosts = []) {
        $data = ['purge_everything' => $purgeEverything];
        if (!$purgeEverything) {
            if (!empty($files)) $data['files'] = $files;
            if (!empty($tags)) $data['tags'] = $tags;
            if (!empty($hosts)) $data['hosts'] = $hosts;
        }
        $result = $this->request("zones/{$zoneId}/purge_cache", "POST", $data);
        $this->clearCacheForZone($zoneId);
        return $result;
    }
    
    /**
     * Get zone analytics
     * Returns both totals and timeseries data for charts
     */
    public function getZoneAnalytics($zoneId, $since = "-1440") {
        try {
            // Get analytics dashboard data (includes totals and timeseries)
            $result = $this->request("zones/{$zoneId}/analytics/dashboard?since={$since}");
            
            // The API returns: { "totals": {...}, "timeseries": [...] }
            // Ensure we return the full structure
            if (is_array($result)) {
                return $result;
            }
            
            // If result is not an array, wrap it
            return ['totals' => $result, 'timeseries' => []];
        } catch (Exception $e) {
            $this->logDebug("Analytics Error: " . $e->getMessage());
            // Return empty structure instead of null to prevent errors
            return ['totals' => [], 'timeseries' => []];
        }
    }
    
    /**
     * Get zone SSL status
     */
    public function getSSLStatus($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/ssl/verification");
        } catch (Exception $e) {
            $this->logDebug("SSL Status Error: " . $e->getMessage());
            return ['status' => 'unknown'];
        }
    }
    
    /**
     * Get zone settings
     */
    public function getZoneSettings($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings");
        } catch (Exception $e) {
            $this->logDebug("Get Zone Settings Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update zone setting
     * @param string $zoneId Zone ID
     * @param string $setting Setting name (e.g., 'development_mode', 'security_level')
     * @param mixed $value Setting value
     */
    public function updateZoneSetting($zoneId, $setting, $value) {
        try {
            // Format value according to Cloudflare API requirements
            // Development mode: "on" or "off"
            if ($setting === 'development_mode') {
                $formattedValue = ($value === true || $value === 'on' || $value === '1') ? 'on' : 'off';
            } else {
                // Other settings use the value as-is
                $formattedValue = $value;
            }
            
            $data = ['value' => $formattedValue];
            $this->logDebug("Updating zone setting: {$setting} = {$formattedValue} for zone {$zoneId}");
            $result = $this->request("zones/{$zoneId}/settings/{$setting}", "PATCH", $data);
            $this->clearCacheForZone($zoneId);
            $this->logDebug("Zone setting updated successfully");
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Update Zone Setting Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Pause Cloudflare for zone
     */
    public function pauseZone($zoneId) {
        try {
            $data = ['paused' => true];
            $result = $this->request("zones/{$zoneId}", "PATCH", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Pause Zone Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Unpause Cloudflare for zone
     */
    public function unpauseZone($zoneId) {
        try {
            $data = ['paused' => false];
            $result = $this->request("zones/{$zoneId}", "PATCH", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Unpause Zone Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete zone (Remove from Cloudflare)
     */
    public function deleteZone($zoneId) {
        try {
            $result = $this->request("zones/{$zoneId}", "DELETE");
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Delete Zone Error: " . $e->getMessage());
            throw $e;
        }
    }
    
        /**
     * Get SSL Universal Settings
     */
    public function getSSLUniversalSettings($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/ssl/universal/settings");
        } catch (Exception $e) {
            $this->logDebug("Get SSL Universal Settings Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update SSL Universal Settings
     */
    public function updateSSLUniversalSettings($zoneId, $data) {
        try {
            $result = $this->request("zones/{$zoneId}/ssl/universal/settings", "PATCH", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Update SSL Universal Settings Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List Firewall Rules
     */
    public function listFirewallRules($zoneId, $page = 1, $perPage = 100) {
        try {
            $params = http_build_query(['page' => $page, 'per_page' => $perPage]);
            return $this->request("zones/{$zoneId}/firewall/rules?{$params}");
        } catch (Exception $e) {
            $this->logDebug("List Firewall Rules Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create Firewall Rule
     */
    public function createFirewallRule($zoneId, $data) {
        try {
            $result = $this->request("zones/{$zoneId}/firewall/rules", "POST", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Create Firewall Rule Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get WAF Packages
     */
    public function getWAFPackages($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/firewall/waf/packages");
        } catch (Exception $e) {
            $this->logDebug("Get WAF Packages Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get WAF Groups
     */
    public function getWAFGroups($zoneId, $packageId) {
        try {
            return $this->request("zones/{$zoneId}/firewall/waf/packages/{$packageId}/groups");
        } catch (Exception $e) {
            $this->logDebug("Get WAF Groups Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List Page Rules
     */
    public function listPageRules($zoneId, $page = 1, $perPage = 100) {
        try {
            $params = http_build_query(['page' => $page, 'per_page' => $perPage]);
            return $this->request("zones/{$zoneId}/pagerules?{$params}");
        } catch (Exception $e) {
            $this->logDebug("List Page Rules Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create Page Rule
     */
    public function createPageRule($zoneId, $data) {
        try {
            $result = $this->request("zones/{$zoneId}/pagerules", "POST", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Create Page Rule Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List Rate Limits
     */
    public function listRateLimits($zoneId, $page = 1, $perPage = 100) {
        try {
            $params = http_build_query(['page' => $page, 'per_page' => $perPage]);
            return $this->request("zones/{$zoneId}/rate_limits?{$params}");
        } catch (Exception $e) {
            $this->logDebug("List Rate Limits Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create Rate Limit
     */
    public function createRateLimit($zoneId, $data) {
        try {
            $result = $this->request("zones/{$zoneId}/rate_limits", "POST", $data);
            $this->clearCacheForZone($zoneId);
            return $result;
        } catch (Exception $e) {
            $this->logDebug("Create Rate Limit Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Cache Level Setting
     */
    public function getCacheLevel($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings/cache_level");
        } catch (Exception $e) {
            $this->logDebug("Get Cache Level Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Browser Cache TTL Setting
     */
    public function getBrowserCacheTTL($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings/browser_cache_ttl");
        } catch (Exception $e) {
            $this->logDebug("Get Browser Cache TTL Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Security Level Setting
     */
    public function getSecurityLevel($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings/security_level");
        } catch (Exception $e) {
            $this->logDebug("Get Security Level Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Challenge Passage Setting
     */
    public function getChallengePassage($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings/challenge_passage");
        } catch (Exception $e) {
            $this->logDebug("Get Challenge Passage Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Privacy Pass Setting
     */
    public function getPrivacyPass($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/settings/privacy_pass");
        } catch (Exception $e) {
            $this->logDebug("Get Privacy Pass Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List Workers Scripts
     */
    public function listWorkers($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/workers/scripts");
        } catch (Exception $e) {
            $this->logDebug("List Workers Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Worker Routes
     */
    public function getWorkerRoutes($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/workers/routes");
        } catch (Exception $e) {
            $this->logDebug("Get Worker Routes Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List Load Balancers
     */
    public function listLoadBalancers($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/load_balancers");
        } catch (Exception $e) {
            $this->logDebug("List Load Balancers Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Stream Info
     */
    public function getStreamInfo($zoneId) {
        try {
            return $this->request("zones/{$zoneId}/stream");
        } catch (Exception $e) {
            $this->logDebug("Get Stream Info Error: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Debug log
     */
    protected function logDebug($message) {
        if ($this->debug) {
            error_log('[CloudflareAPI] ' . $message);
        }
    }
}
}
