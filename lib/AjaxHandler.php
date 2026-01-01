<?php
/**
 * AJAX Handler - Professional Request Handler
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     2.0.0
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!class_exists('CloudflareManager\AjaxHandler')) {
class AjaxHandler {
    protected $api;
    protected $domainManager;
    protected $dnsManager;
    protected $csrfToken;
    protected $lang;
    protected $debug = false;
    
    /**
     * Constructor
     */
    public function __construct($api, $domainManager, $dnsManager, $csrfToken, $lang = []) {
        $this->api = $api;
        $this->domainManager = $domainManager;
        $this->dnsManager = $dnsManager;
        $this->csrfToken = $csrfToken;
        $this->lang = $lang;
    }
    
    /**
     * Enable debug mode
     */
    public function enableDebug() {
        $this->debug = true;
        if ($this->api) {
            $this->api->enableDebug();
        }
        return $this;
    }
    
    /**
     * Process AJAX request
     */
    public function processRequest() {
        if (!isset($_GET['ajax'])) {
            return null;
        }
        
        // CSRF validation
        $token = $this->getCsrfToken();
        if (empty($token) || $token !== $this->csrfToken) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['csrf_error']) ? 
                    $this->lang['csrf_error'] : 
                    'Security validation failed'
            ]);
        }
        
        $action = $_GET['ajax'];
        
        try {
            switch ($action) {
                case 'get_domains':
                    return $this->getDomains();
                    
                case 'get_dns':
                    return $this->getDnsRecords();
                
                case 'get_zone_details':
                    return $this->getZoneDetails();
                
                case 'add_dns_record':
                    return $this->addDnsRecord();
                
                case 'update_dns_record':
                    return $this->updateDnsRecord();
                
                case 'delete_dns_record':
                    return $this->deleteDnsRecord();
                
                case 'purge_cache':
                    return $this->purgeCache();
                
                case 'sync_domains':
                    return $this->syncDomains();
                
                case 'get_domain_analytics':
                    return $this->getDomainAnalytics();
                
                case 'get_ssl_status':
                    return $this->getSSLStatus();
                
                case 'get_zone_settings':
                    return $this->getZoneSettings();
                
                case 'update_zone_setting':
                    return $this->updateZoneSetting();
                
                case 'pause_zone':
                    return $this->pauseZone();
                
                case 'unpause_zone':
                    return $this->unpauseZone();
                
                case 'delete_zone':
                    return $this->deleteZone();
                
                default:
                    return $this->respondWithJSON([
                        'success' => false,
                        'message' => 'Invalid action specified'
                    ]);
            }
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("AjaxHandler Error: " . $e->getMessage());
                error_log("Stack Trace: " . $e->getTraceAsString());
            }
            return $this->respondWithJSON([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $this->debug ? $e->getTraceAsString() : null
            ]);
        }
    }
    
    /**
     * Get CSRF token from request
     */
    protected function getCsrfToken() {
        if (isset($_POST['token'])) {
            return $_POST['token'];
        } elseif (isset($_GET['csrf'])) {
            return $_GET['csrf'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return '';
    }
    
    /**
     * Get domains
     */
    protected function getDomains() {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            
            $result = $this->domainManager->getAllDomains($page, $perPage);
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => (isset($this->lang['error_fetching_domains']) ? 
                    $this->lang['error_fetching_domains'] : 
                    'Error fetching domains') . ': ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get DNS records
     */
    protected function getDnsRecords() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $records = $this->dnsManager->getDnsRecords($zoneId);
            
            return $this->respondWithJSON([
                'success' => true,
                'records' => $records
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => (isset($this->lang['error_fetching_dns']) ? 
                    $this->lang['error_fetching_dns'] : 
                    'Error fetching DNS records') . ': ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get zone details
     */
    protected function getZoneDetails() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $zone = $this->api->getZone($zoneId);
            
            return $this->respondWithJSON([
                'success' => true,
                'zone' => $zone
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error fetching zone details: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Add DNS record
     */
    protected function addDnsRecord() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }
        
        if (empty($_POST['zone_id']) || empty($_POST['type']) || empty($_POST['name'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['missing_required_fields']) ? 
                    $this->lang['missing_required_fields'] : 
                    'Required fields are missing'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $data = [
                'type' => $_POST['type'],
                'name' => $_POST['name'],
                'content' => $_POST['content'] ?? '',
                'ttl' => isset($_POST['ttl']) ? (int)$_POST['ttl'] : 1,
                'proxied' => isset($_POST['proxied']) && $_POST['proxied'] == '1',
            ];
            
            if (isset($_POST['priority'])) {
                $data['priority'] = (int)$_POST['priority'];
            }
            
            $result = $this->dnsManager->createDnsRecord($zoneId, $data);
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error adding DNS record: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update DNS record
     */
    protected function updateDnsRecord() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }
        
        if (empty($_POST['zone_id']) || empty($_POST['record_id']) || empty($_POST['type']) || empty($_POST['name'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['missing_required_fields']) ? 
                    $this->lang['missing_required_fields'] : 
                    'Required fields are missing'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $recordId = $_POST['record_id'];
            $data = [
                'type' => $_POST['type'],
                'name' => $_POST['name'],
                'content' => $_POST['content'] ?? '',
                'ttl' => isset($_POST['ttl']) ? (int)$_POST['ttl'] : 1,
                'proxied' => isset($_POST['proxied']) && $_POST['proxied'] == '1',
            ];
            
            if (isset($_POST['priority'])) {
                $data['priority'] = (int)$_POST['priority'];
            }
            
            $result = $this->dnsManager->updateDnsRecord($zoneId, $recordId, $data);
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error updating DNS record: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Delete DNS record
     */
    protected function deleteDnsRecord() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }
        
        if (empty($_POST['zone_id']) || empty($_POST['record_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['missing_required_fields']) ? 
                    $this->lang['missing_required_fields'] : 
                    'Required fields are missing'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $recordId = $_POST['record_id'];
            
            $result = $this->dnsManager->deleteDnsRecord($zoneId, $recordId);
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error deleting DNS record: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Purge cache
     */
    protected function purgeCache() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }
        
        if (empty($_POST['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['missing_required_fields']) ? 
                    $this->lang['missing_required_fields'] : 
                    'Required fields are missing'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $result = $this->domainManager->purgeCache($zoneId);
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error purging cache: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Sync domains
     */
    protected function syncDomains() {
        try {
            $result = $this->domainManager->syncDomains();
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error syncing domains: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get domain analytics
     */
    protected function getDomainAnalytics() {
        if (!isset($_GET['domain_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Domain ID is required'
            ]);
        }
        
        try {
            $domainId = (int)$_GET['domain_id'];
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
            
            if (!$domain) {
                return $this->respondWithJSON([
                    'success' => false,
                    'message' => 'Domain not found'
                ]);
            }
            
            $this->domainManager->updateAnalytics($domain->zone_id);
            
            $updatedDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
            
            $analytics = $updatedDomain->analytics ? json_decode($updatedDomain->analytics, true) : null;
            
            return $this->respondWithJSON([
                'success' => true,
                'analytics' => $analytics
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error getting analytics: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get SSL Status
     */
    protected function getSSLStatus() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $sslStatus = $this->api->getSSLStatus($zoneId);
            
            return $this->respondWithJSON([
                'success' => true,
                'status' => isset($sslStatus['status']) ? $sslStatus['status'] : 'unknown',
                'details' => $sslStatus
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error getting SSL status: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get Zone Settings
     */
    protected function getZoneSettings() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $zone = $this->api->getZone($zoneId);
            $settings = $this->api->getZoneSettings($zoneId);
            
            // Parse settings array into key-value pairs
            $settingsArray = [];
            if (is_array($settings)) {
                foreach ($settings as $setting) {
                    if (isset($setting['id']) && isset($setting['value'])) {
                        $settingsArray[$setting['id']] = $setting;
                    }
                }
            }
            
            // Get specific settings we need
            $result = [
                'success' => true,
                'zone' => $zone,
                'settings' => $settingsArray
            ];
            
            // Extract specific settings for easier access
            if (isset($settingsArray['development_mode'])) {
                $result['development_mode'] = $settingsArray['development_mode']['value'];
            }
            if (isset($settingsArray['security_level'])) {
                $result['security_level'] = $settingsArray['security_level']['value'];
            }
            
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error getting zone settings: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update Zone Setting
     */
    protected function updateZoneSetting() {
        if (!isset($_POST['zone_id']) || !isset($_POST['setting']) || !isset($_POST['value'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID, setting, and value are required'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $setting = $_POST['setting'];
            $value = $_POST['value'];
            
            // Convert boolean strings to actual booleans
            if ($value === 'true') $value = true;
            if ($value === 'false') $value = false;
            
            $result = $this->api->updateZoneSetting($zoneId, $setting, $value);
            
            return $this->respondWithJSON([
                'success' => true,
                'message' => 'Setting updated successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error updating zone setting: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Pause Zone
     */
    protected function pauseZone() {
        if (!isset($_POST['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $result = $this->api->pauseZone($zoneId);
            
            return $this->respondWithJSON([
                'success' => true,
                'message' => 'Zone paused successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error pausing zone: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Unpause Zone
     */
    protected function unpauseZone() {
        if (!isset($_POST['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $result = $this->api->unpauseZone($zoneId);
            
            return $this->respondWithJSON([
                'success' => true,
                'message' => 'Zone unpaused successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error unpausing zone: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Delete Zone
     */
    protected function deleteZone() {
        if (!isset($_POST['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $result = $this->api->deleteZone($zoneId);
            
            // Also remove from database
            try {
                Capsule::table('mod_cloudflaremanager_domains')
                    ->where('zone_id', $zoneId)
                    ->delete();
            } catch (Exception $e) {
                // Log but don't fail
                error_log("Error removing zone from database: " . $e->getMessage());
            }
            
            return $this->respondWithJSON([
                'success' => true,
                'message' => 'Zone deleted successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => 'Error deleting zone: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send JSON response
     */
    protected function respondWithJSON($data) {
        header('Content-Type: application/json');
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
}
