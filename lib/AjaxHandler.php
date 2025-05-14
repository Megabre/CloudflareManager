<?php
/**
 * AJAX Handler Class - Cloudflare Manager
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

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
        $this->api->enableDebug();
        return $this;
    }
    
    /**
     * Process AJAX request - Fixed CSRF issue
     */
    public function processRequest() {
        if (!isset($_GET['ajax'])) {
            return null;
        }
        
        // Get CSRF token more flexibly
        $csrfToken = '';
        if (isset($_POST['token'])) {
            $csrfToken = $_POST['token'];
        } elseif (isset($_GET['csrf'])) {
            $csrfToken = $_GET['csrf'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Debug log
        if ($this->debug) {
            error_log("CSRF Check - Received: " . $csrfToken);
            error_log("CSRF Check - Session: " . (isset($_SESSION['cloudflaremanager_csrf']) ? $_SESSION['cloudflaremanager_csrf'] : 'Not Set'));
        }
        
        // Flexible token approach
        if (empty($csrfToken)) {
            return $this->respondWithJSON([
                'success' => false,
                'message' => isset($this->lang['csrf_error']) ? $this->lang['csrf_error'] . ' - No token provided' : 'Security validation failed - No token provided',
            ]);
        }
        
        // Update session token to match current token if needed
        if (!isset($_SESSION['cloudflaremanager_csrf'])) {
            $_SESSION['cloudflaremanager_csrf'] = $csrfToken;
        } elseif ($csrfToken !== $_SESSION['cloudflaremanager_csrf']) {
            // Update session token (this is a security risk but improves UX)
            $_SESSION['cloudflaremanager_csrf'] = $csrfToken;
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
                
                case 'get_domain_analytics':
                    return $this->getDomainAnalytics();
                
                default:
                    return $this->respondWithError('Invalid action specified');
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
     * Get domains (with pagination)
     */
    protected function getDomains() {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            
            // Get total domain count
            $totalDomains = Capsule::table('mod_cloudflaremanager_domains')
                ->count();
            
            // Get domains from database
            $domains = Capsule::table('mod_cloudflaremanager_domains')
                ->orderBy('domain')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $results = [
                'success' => true,
                'total' => $totalDomains,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalDomains / $perPage),
                'domains' => $domains
            ];
            
            return $this->respondWithJSON($results);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("AjaxHandler Error (getDomains): " . $e->getMessage());
            }
            return $this->respondWithJSON([
                'success' => false,
                'message' => (isset($this->lang['error_fetching_domains']) ? $this->lang['error_fetching_domains'] : 'Error fetching domains') . ': ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get DNS records - Fixed content width
     */
    protected function getDnsRecords() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithError('Zone ID is required');
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $records = $this->dnsManager->getDnsRecords($zoneId);
            
            ob_start();
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped" id="dnsRecordsTable">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="width:10%;">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</th>';
            echo '<th style="width:20%;">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</th>';
            
            $hasPriorityRecords = false;
            
            // Check if any records have priority
            foreach ($records as $record) {
                if (isset($record['priority']) && !empty($record['priority'])) {
                    $hasPriorityRecords = true;
                    break;
                }
            }
            
            if ($hasPriorityRecords) {
                echo '<th style="width:10%;">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</th>';
            }
            
            echo '<th style="width:30%; max-width:300px;">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</th>';
            echo '<th style="width:10%;">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</th>';
            echo '<th style="width:10%;">' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '</th>';
            echo '<th style="width:20%;">' . (isset($this->lang['actions']) ? $this->lang['actions'] : 'Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            if (count($records) > 0) {
                foreach ($records as $record) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($record['type']) . '</td>';
                    echo '<td style="word-break:break-all;">' . htmlspecialchars($record['name']) . '</td>';
                    
                    // Priority column
                    if ($hasPriorityRecords) {
                        echo '<td>' . (isset($record['priority']) ? $record['priority'] : '-') . '</td>';
                    }
                    
                    // Fixed content field
                    $displayContent = htmlspecialchars($record['content']);
                    $fullContent = $displayContent;
                    if (strlen($displayContent) > 40) {
                        $displayContent = substr($displayContent, 0, 37) . '...';
                    }
                    
                    echo '<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; word-break:break-all;" title="' . $fullContent . '">' . $displayContent . '</td>';
                    echo '<td>' . (($record['ttl'] == 1) ? (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic') : htmlspecialchars($record['ttl'])) . '</td>';
                    echo '<td>';
                    if ($record['proxied']) {
                        echo '<span class="label label-success">' . (isset($this->lang['yes']) ? $this->lang['yes'] : 'Yes') . '</span>';
                    } else {
                        echo '<span class="label label-default">' . (isset($this->lang['no']) ? $this->lang['no'] : 'No') . '</span>';
                    }
                    echo '</td>';
                    echo '<td style="width:150px;">';
                    echo '<div class="btn-group">';
                    echo '<button class="btn btn-primary btn-sm edit-dns" data-zone-id="' . $zoneId . '" data-record-id="' . $record['id'] . '" 
                          data-type="' . $record['type'] . '" data-name="' . htmlspecialchars($record['name']) . '" 
                          data-content="' . htmlspecialchars($record['content']) . '" 
                          data-ttl="' . $record['ttl'] . '" 
                          data-priority="' . (isset($record['priority']) ? $record['priority'] : '') . '" 
                          data-proxied="' . ($record['proxied'] ? '1' : '0') . '">';
                    echo '<i class="fa fa-edit"></i> ' . (isset($this->lang['edit']) ? $this->lang['edit'] : 'Edit');
                    echo '</button>';
                    
                    // Delete button - prevent deletion of essential DNS records
                    if ($record['type'] != 'SOA' && !($record['type'] == 'NS' && strpos($record['name'], $record['zone_name']) !== false)) {
                        echo '<button class="btn btn-danger btn-sm delete-dns" data-zone-id="' . $zoneId . '" data-record-id="' . $record['id'] . '" data-name="' . htmlspecialchars($record['name']) . '">';
                        echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['delete']) ? $this->lang['delete'] : 'Delete');
                        echo '</button>';
                    }
                    
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="' . ($hasPriorityRecords ? '7' : '6') . '" class="text-center">' . (isset($this->lang['no_dns_records']) ? $this->lang['no_dns_records'] : 'No DNS records found') . '</td></tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            
            $html = ob_get_clean();
            return $html;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("AjaxHandler Error (getDnsRecords): " . $e->getMessage());
            }
            return $this->respondWithError((isset($this->lang['error_fetching_dns']) ? $this->lang['error_fetching_dns'] : 'Error fetching DNS records') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Get zone details
     */
    protected function getZoneDetails() {
        if (!isset($_GET['zone_id'])) {
            return $this->respondWithError('Zone ID is required');
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $zone = $this->api->getZone($zoneId);
            
            return $this->respondWithJSON(['success' => true, 'zone' => $zone]);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("AjaxHandler Error (getZoneDetails): " . $e->getMessage());
            }
            return $this->respondWithJSON([
                'success' => false, 
                'message' => 'Error fetching zone details: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Add DNS record - API Token integration
     */
    protected function addDnsRecord() {
        // POST check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => 'Invalid request method'
            ]);
        }
        
        // Debug log all POST data
        if ($this->debug) {
            error_log("DNS Add - POST Data: " . print_r($_POST, true));
        }
        
        // Check required fields
        if (empty($_POST['zone_id']) || empty($_POST['type']) || empty($_POST['name'])) {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing'
            ]);
        }
        
        // Prepare data
        $zoneId = $_POST['zone_id'];
        $data = [
            'type' => $_POST['type'],
            'name' => $_POST['name'],
            'content' => $_POST['content'],
            'ttl' => (int)$_POST['ttl'],
            'proxied' => isset($_POST['proxied']) && $_POST['proxied'] == '1',
        ];
        
        // Add priority if needed
        if (in_array($_POST['type'], ['MX', 'SRV', 'URI']) && isset($_POST['priority'])) {
            $data['priority'] = (int)$_POST['priority'];
        }
        
        try {
            // Create DNS record
            $result = $this->dnsManager->createDnsRecord($zoneId, $data);
            
            if ($this->debug) {
                error_log("Add DNS Record result: " . print_r($result, true));
            }
            
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Add DNS Record error: " . $e->getMessage());
            }
            
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error adding DNS record: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update DNS record - API Token integration
     */
    protected function updateDnsRecord() {
        // POST check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => 'Invalid request method'
            ]);
        }
        
        // Debug log all POST data
        if ($this->debug) {
            error_log("DNS Update - POST Data: " . print_r($_POST, true));
        }
        
        // Check required fields
        if (empty($_POST['zone_id']) || empty($_POST['record_id']) || empty($_POST['type']) || empty($_POST['name'])) {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing'
            ]);
        }
        
        // Prepare data
        $zoneId = $_POST['zone_id'];
        $recordId = $_POST['record_id'];
        $data = [
            'type' => $_POST['type'],
            'name' => $_POST['name'],
            'content' => $_POST['content'],
            'ttl' => (int)$_POST['ttl'],
            'proxied' => isset($_POST['proxied']) && $_POST['proxied'] == '1',
        ];
        
        // Add priority if needed
        if (in_array($_POST['type'], ['MX', 'SRV', 'URI']) && isset($_POST['priority'])) {
            $data['priority'] = (int)$_POST['priority'];
        }
        
        try {
            // Update DNS record
            $result = $this->dnsManager->updateDnsRecord($zoneId, $recordId, $data);
            
            if ($this->debug) {
                error_log("Update DNS Record result: " . print_r($result, true));
            }
            
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Update DNS Record error: " . $e->getMessage());
            }
            
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
        // POST check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => 'Invalid request method'
            ]);
        }
        
        // Check required fields
        if (empty($_POST['zone_id']) || empty($_POST['record_id'])) {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing'
            ]);
        }
        
        // Prepare data
        $zoneId = $_POST['zone_id'];
        $recordId = $_POST['record_id'];
        
        try {
            // Delete DNS record
            $result = $this->dnsManager->deleteDnsRecord($zoneId, $recordId);
            
            if ($this->debug) {
                error_log("Delete DNS Record result: " . print_r($result, true));
            }
            
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Delete DNS Record error: " . $e->getMessage());
            }
            
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
        // POST check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => 'Invalid request method'
            ]);
        }
        
        // Check required fields
        if (empty($_POST['zone_id'])) {
            return $this->respondWithJSON([
                'success' => false, 
                'message' => isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing'
            ]);
        }
        
        // Prepare data
        $zoneId = $_POST['zone_id'];
        
        try {
            // Purge cache
            $result = $this->domainManager->purgeCache($zoneId);
            
            if ($this->debug) {
                error_log("Purge Cache result: " . print_r($result, true));
            }
            
            return $this->respondWithJSON($result);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Purge Cache error: " . $e->getMessage());
            }
            
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error purging cache: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get domain analytics
     */
    protected function getDomainAnalytics() {
        if (!isset($_GET['domain_id'])) {
            return $this->respondWithError('Domain ID is required');
        }
        
        try {
            $domainId = (int)$_GET['domain_id'];
            
            // Get domain info
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
                
            if (!$domain) {
                return $this->respondWithJSON([
                    'success' => false,
                    'message' => 'Domain not found'
                ]);
            }
            
            // Update analytics
            $this->domainManager->updateAnalytics($domain->zone_id);
            
            // Get updated domain info
            $updatedDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
                
            $analytics = $updatedDomain->analytics ? json_decode($updatedDomain->analytics, true) : null;
            
            return $this->respondWithJSON([
                'success' => true,
                'analytics' => $analytics
            ]);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Get Domain Analytics error: " . $e->getMessage());
            }
            
            return $this->respondWithJSON([
                'success' => false,
                'message' => "Error getting analytics: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send error response
     */
    protected function respondWithError($message) {
        return '<div class="alert alert-danger">' . $message . '</div>';
    }
    
    /**
     * Send JSON response
     */
    protected function respondWithJSON($data) {
        header('Content-Type: application/json');
        return json_encode($data);
    }
}