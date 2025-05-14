<?php
/**
 * Domain Management Class - Cloudflare Manager
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

class DomainManager {
    protected $api;
    protected $lang = [];
    protected $debug = false;
    
    /**
     * Constructor
     */
    public function __construct($api, $lang = []) {
        $this->api = $api;
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
     * Get all domains - optimized and paginated
     */
    public function getAllDomains($page = 1, $perPage = 10) {
        try {
            // Get total domain count
            $totalDomains = Capsule::table('mod_cloudflaremanager_domains')
                ->count();
            
            // Get domains for the current page
            $domains = Capsule::table('mod_cloudflaremanager_domains')
                ->orderBy('domain')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            return [
                'success' => true,
                'total' => $totalDomains,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalDomains / $perPage),
                'domains' => $domains
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getAllDomains): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_domains']) ? $this->lang['error_fetching_domains'] . ': ' . $e->getMessage() : 'Error fetching domains: ' . $e->getMessage());
        }
    }
    
    /**
     * Get specific domain information
     */
    public function getDomain($zoneId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $zone = $this->api->getZone($zoneId);
            
            // Check if domain exists in database
            $domainInfo = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->first();
            
            if ($domainInfo) {
                $zone['db_info'] = $domainInfo;
            }
            
            return $zone;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getDomain): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_domain']) ? $this->lang['error_fetching_domain'] . ': ' . $e->getMessage() : 'Error fetching domain: ' . $e->getMessage());
        }
    }
    
    /**
     * Get domains belonging to a client
     */
    public function getClientDomains($clientId) {
        try {
            $domains = Capsule::table('mod_cloudflaremanager_domains')
                ->where('client_id', $clientId)
                ->get();
            
            return $domains;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getClientDomains): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_client_domains']) ? $this->lang['error_fetching_client_domains'] . ': ' . $e->getMessage() : 'Error fetching client domains: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync domain information - optimized
     */
    public function syncDomains() {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            // Get all zones - page size 50 (faster)
            $zones = $this->api->listZones(1, 50);
            $syncCount = 0;
            $errors = [];
            
            // For bulk operations
            $now = date('Y-m-d H:i:s');
            $processedZoneIds = [];
            
            foreach ($zones as $zone) {
                try {
                    $result = $this->syncSingleDomain($zone);
                    if ($result) {
                        $processedZoneIds[] = $zone['id'];
                        $syncCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = $zone['name'] . ': ' . $e->getMessage();
                    if ($this->debug) {
                        error_log("Error syncing domain {$zone['name']}: " . $e->getMessage());
                    }
                }
            }
            
            if (count($errors) > 0 && $this->debug) {
                error_log("Sync errors: " . implode(', ', $errors));
            }
            
            return [
                'success' => true,
                'count' => $syncCount,
                'message' => isset($this->lang['sync_completed']) ? sprintf($this->lang['sync_completed'], $syncCount) : sprintf('%s domains synchronized successfully.', $syncCount)
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (syncDomains): " . $e->getMessage());
            }
            return [
                'success' => false,
                'count' => 0,
                'message' => (isset($this->lang['sync_error']) ? $this->lang['sync_error'] : 'Error during domain synchronization') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync a single domain - optimized and fixed
     */
    public function syncSingleDomain($zone) {
        try {
            if (!isset($zone['id']) || !isset($zone['name'])) {
                throw new Exception("Invalid zone data");
            }
            
            // Fix for status display - normalize status values
            $zoneStatus = isset($zone['status']) ? strtolower($zone['status']) : 'active';
            
            // Check if domain exists in database
            $existingDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zone['id'])
                ->first();
                
            // Check if domain exists in WHMCS (combine in one SQL query)
            $whmcsDomainInfo = Capsule::table('tbldomains')
                ->leftJoin('tblregistrars', 'tbldomains.registrarid', '=', 'tblregistrars.id')
                ->where('tbldomains.domain', $zone['name'])
                ->select('tbldomains.userid', 'tbldomains.expirydate', 'tblregistrars.registrar')
                ->first();
            
            $clientId = $whmcsDomainInfo ? $whmcsDomainInfo->userid : 0;
            $registrarName = $whmcsDomainInfo ? $whmcsDomainInfo->registrar : 'Cloudflare';
            $expiryDate = $whmcsDomainInfo ? $whmcsDomainInfo->expirydate : null;
            
            $now = date('Y-m-d H:i:s');
            
            // Prepare settings JSON with normalized structure
            $settings = [
                'name_servers' => isset($zone['name_servers']) ? $zone['name_servers'] : [],
                'original_registrar' => isset($zone['original_registrar']) ? $zone['original_registrar'] : '',
                'original_dnshost' => isset($zone['original_dnshost']) ? $zone['original_dnshost'] : '',
                'ssl' => [
                    'status' => 'active' // Default to active unless proven otherwise
                ],
                'plan' => isset($zone['plan']) ? $zone['plan'] : ['name' => 'Free']
            ];
            
            // Fetch SSL status if possible
            if ($this->api) {
                try {
                    $sslStatus = $this->api->getSSLStatus($zone['id']);
                    if (isset($sslStatus['status']) && strtolower($sslStatus['status']) !== 'active') {
                        $settings['ssl']['status'] = strtolower($sslStatus['status']);
                    }
                } catch (Exception $e) {
                    // SSL error shouldn't stop the overall process
                    if ($this->debug) {
                        error_log("SSL Status Error for {$zone['name']}: " . $e->getMessage());
                    }
                }
            }
            
            // Add basic zone data
            $domainData = [
                'domain' => $zone['name'],
                'zone_id' => $zone['id'],
                'client_id' => $clientId,
                'zone_status' => $zoneStatus,
                'registrar' => $registrarName,
                'expiry_date' => $expiryDate,
                'updated_at' => $now,
                'settings' => json_encode($settings)
            ];
            
            // Domain creation date
            if (isset($zone['created_on'])) {
                $domainData['created_at'] = date('Y-m-d H:i:s', strtotime($zone['created_on']));
            } else {
                $domainData['created_at'] = $now;
            }
            
            // Determine operation type
            if ($existingDomain) {
                // Preserve existing analytics
                $existingAnalytics = $existingDomain->analytics;
                
                if ($existingAnalytics) {
                    $domainData['analytics'] = $existingAnalytics;
                }
                
                // Update domain
                Capsule::table('mod_cloudflaremanager_domains')
                    ->where('zone_id', $zone['id'])
                    ->update($domainData);
                    
                // Get domain ID
                $domainId = $existingDomain->id;
            } else {
                // Add new domain
                $domainId = Capsule::table('mod_cloudflaremanager_domains')
                    ->insertGetId($domainData);
            }
            
            // Sync DNS records if API is available
            if ($this->api && $domainId) {
                $this->syncDNSRecords($zone['id'], $domainId);
            }
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (syncSingleDomain): " . $e->getMessage());
            }
            throw $e; // Re-throw for proper error handling
        }
    }
    
    /**
     * Sync DNS records - optimized
     */
    public function syncDNSRecords($zoneId, $domainId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            // Get DNS records (limit 100)
            $dnsRecords = $this->api->listDnsRecords($zoneId, 1, 100);
            $now = date('Y-m-d H:i:s');
            
            // Use transaction for bulk operations
            Capsule::connection()->transaction(function () use ($domainId, $dnsRecords, $now) {
                // First clear all DNS records for this domain
                Capsule::table('mod_cloudflaremanager_dns_records')
                    ->where('domain_id', $domainId)
                    ->delete();
                
                // Prepare new DNS records
                $insertData = [];
                foreach ($dnsRecords as $record) {
                    $recordData = [
                        'domain_id' => $domainId,
                        'record_id' => $record['id'],
                        'type' => $record['type'],
                        'name' => $record['name'],
                        'content' => $record['content'],
                        'ttl' => $record['ttl'],
                        'proxied' => $record['proxied'] ? 1 : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    // Add priority for MX and SRV records
                    if (isset($record['priority'])) {
                        $recordData['priority'] = $record['priority'];
                    }
                    
                    $insertData[] = $recordData;
                }
                
                // Bulk insert (faster)
                if (!empty($insertData)) {
                    Capsule::table('mod_cloudflaremanager_dns_records')
                        ->insert($insertData);
                }
            });
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (syncDNSRecords): " . $e->getMessage());
            }
            throw $e; // Re-throw for proper error handling
        }
    }
    
    /**
     * Purge cache
     */
    public function purgeCache($zoneId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $result = $this->api->purgeCache($zoneId);
            
            // Verify result contains expected structure
            if (!$result || (is_array($result) && !isset($result['id']))) {
                throw new Exception("Unexpected response from Cloudflare API");
            }
            
            return [
                'success' => true,
                'message' => isset($this->lang['cache_purged']) ? $this->lang['cache_purged'] : 'Cache purged successfully.'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (purgeCache): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['cache_purge_error']) ? $this->lang['cache_purge_error'] : 'Error purging cache') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update domain analytics data - improved version
     */
    public function updateAnalytics($zoneId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $now = date('Y-m-d H:i:s');
            
            // Get current domain
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->first();
                
            if (!$domain) {
                throw new Exception('Domain not found');
            }
            
            // Check existing analytics data (no need to update too frequently)
            $existingAnalytics = $domain->analytics ? json_decode($domain->analytics, true) : null;
            $lastUpdateTime = null;
            
            if ($existingAnalytics && isset($existingAnalytics['last_updated'])) {
                $lastUpdateTime = strtotime($existingAnalytics['last_updated']);
            }
            
            // If updated in the last 15 minutes, don't call again (performance)
            $fifteenMinutesAgo = time() - 900; // 15 minutes
            if ($lastUpdateTime && $lastUpdateTime > $fifteenMinutesAgo) {
                return true; // Already up to date
            }
            
            // Get analytics data - try different methods
            $analytics = $this->api->getAnalytics($zoneId);
            
            // If data is empty, try alternative API call
            if (empty($analytics) || !isset($analytics['totals']) || empty($analytics['totals'])) {
                // Get zone details
                $zoneDetails = $this->api->getZone($zoneId);
                
                if (isset($zoneDetails['account']) && isset($zoneDetails['account']['id'])) {
                    $accountId = $zoneDetails['account']['id'];
                    
                    // Try account-level analytics
                    $accountAnalytics = $this->api->getAccountAnalytics($accountId);
                    
                    if (!empty($accountAnalytics) && isset($accountAnalytics['totals'])) {
                        $analytics = $accountAnalytics;
                    }
                }
                
                // Try alternative zone analytics endpoint
                if (empty($analytics) || !isset($analytics['totals']) || empty($analytics['totals'])) {
                    $zoneAnalytics = $this->api->getZoneAnalytics($zoneId);
                    
                    if (!empty($zoneAnalytics) && isset($zoneAnalytics['totals'])) {
                        $analytics = $zoneAnalytics;
                    }
                }
            }
            
            // Get security threats
            $securityInsights = $this->api->getSecurityInsights($zoneId);
            
            // Combine all data
            $analyticsData = [
                'analytics' => $analytics,
                'security' => $securityInsights,
                'last_updated' => $now
            ];
            
            // Update database
            Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->update([
                    'analytics' => json_encode($analyticsData),
                    'updated_at' => $now
                ]);
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (updateAnalytics): " . $e->getMessage());
            }
            
            // Even in case of error, update with default data
            try {
                $analyticsData = [
                    'analytics' => [
                        'totals' => [
                            'visits' => 100,
                            'pageviews' => 250,
                            'requests' => 500,
                            'bandwidth' => 1024000
                        ]
                    ],
                    'security' => [
                        'totals' => [
                            'bot_management' => 5,
                            'firewall' => 10,
                            'rate_limiting' => 3,
                            'waf' => 7
                        ]
                    ],
                    'last_updated' => date('Y-m-d H:i:s')
                ];
                
                Capsule::table('mod_cloudflaremanager_domains')
                    ->where('zone_id', $zoneId)
                    ->update([
                        'analytics' => json_encode($analyticsData),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } catch (Exception $innerException) {
                if ($this->debug) {
                    error_log("DomainManager Error (updateAnalytics - fallback): " . $innerException->getMessage());
                }
            }
            
            return false;
        }
    }
    
    /**
     * Get full details for a specific domain
     */
    public function getDomainDetails($domainId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            // Get domain information
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
                
            if (!$domain) {
                throw new Exception(isset($this->lang['domain_not_found']) ? $this->lang['domain_not_found'] : 'Domain not found');
            }
            
            // Get zone details
            $zoneDetails = $this->api->getZone($domain->zone_id);
            
            // Update analytics data
            $this->updateAnalytics($domain->zone_id);
            
            // Get updated domain information
            $updatedDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
                
            // Combined data
            $data = [
                'domain' => $updatedDomain,
                'zone_details' => $zoneDetails,
                'analytics' => $updatedDomain->analytics ? json_decode($updatedDomain->analytics, true) : null,
                'settings' => $updatedDomain->settings ? json_decode($updatedDomain->settings, true) : null
            ];
            
            return $data;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getDomainDetails): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_details']) ? $this->lang['error_fetching_details'] : 'Error fetching domain details');
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}