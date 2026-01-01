<?php
/**
 * Domain Manager - Professional Domain Management
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     2.0.0
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!class_exists('CloudflareManager\DomainManager')) {
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
     * Get all domains with pagination
     */
    public function getAllDomains($page = 1, $perPage = 10) {
        try {
            $totalDomains = Capsule::table('mod_cloudflaremanager_domains')->count();
            
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
            throw new Exception(
                isset($this->lang['error_fetching_domains']) ? 
                    $this->lang['error_fetching_domains'] . ': ' . $e->getMessage() : 
                    'Error fetching domains: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get domain by zone ID
     */
    public function getDomain($zoneId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $zone = $this->api->getZone($zoneId);
            
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
            throw new Exception(
                isset($this->lang['error_fetching_domain']) ? 
                    $this->lang['error_fetching_domain'] . ': ' . $e->getMessage() : 
                    'Error fetching domain: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get client domains
     */
    public function getClientDomains($clientId) {
        try {
            return Capsule::table('mod_cloudflaremanager_domains')
                ->where('client_id', $clientId)
                ->get();
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getClientDomains): " . $e->getMessage());
            }
            throw new Exception(
                isset($this->lang['error_fetching_client_domains']) ? 
                    $this->lang['error_fetching_client_domains'] . ': ' . $e->getMessage() : 
                    'Error fetching client domains: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Sync all domains from Cloudflare
     */
    public function syncDomains() {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $syncCount = 0;
            $errors = [];
            $page = 1;
            $perPage = 50;
            
            // Fetch all zones with pagination
            $hasMorePages = true;
            while ($hasMorePages) {
                try {
                    $zones = $this->api->listZones($page, $perPage);
                    
                    if ($this->debug) {
                        error_log("Fetched zones page {$page}: " . count($zones) . " zones");
                    }
                    
                    if (!is_array($zones) || empty($zones)) {
                        $hasMorePages = false;
                        break;
                    }
                    
                    foreach ($zones as $zone) {
                        try {
                            if (!isset($zone['id']) || !isset($zone['name'])) {
                                if ($this->debug) {
                                    error_log("Invalid zone data: " . json_encode($zone));
                                }
                                continue;
                            }
                            
                            if ($this->syncSingleDomain($zone)) {
                                $syncCount++;
                            }
                        } catch (Exception $e) {
                            $errors[] = (isset($zone['name']) ? $zone['name'] : 'Unknown') . ': ' . $e->getMessage();
                            if ($this->debug) {
                                error_log("Error syncing domain {$zone['name']}: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Check if there are more pages
                    if (count($zones) < $perPage) {
                        $hasMorePages = false;
                    } else {
                        $page++;
                    }
                } catch (Exception $e) {
                    if ($this->debug) {
                        error_log("Error fetching zones page {$page}: " . $e->getMessage());
                    }
                    $hasMorePages = false;
                    break;
                }
            }
            
            return [
                'success' => true,
                'count' => $syncCount,
                'errors' => $errors,
                'message' => isset($this->lang['sync_completed']) ? 
                    sprintf($this->lang['sync_completed'], $syncCount) : 
                    sprintf('%s domains synchronized successfully.', $syncCount)
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (syncDomains): " . $e->getMessage());
            }
            return [
                'success' => false,
                'count' => 0,
                'message' => (isset($this->lang['sync_error']) ? 
                    $this->lang['sync_error'] : 
                    'Error during domain synchronization') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync single domain
     */
    public function syncSingleDomain($zone) {
        try {
            if (!isset($zone['id']) || !isset($zone['name'])) {
                throw new Exception("Invalid zone data");
            }
            
            $zoneStatus = isset($zone['status']) ? strtolower($zone['status']) : 'active';
            
            // Check if domain exists in database
            $existingDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zone['id'])
                ->first();
            
            // Get WHMCS domain info - try different column structures
            $clientId = 0;
            $registrarName = 'Cloudflare';
            $expiryDate = null;
            
            try {
                // Try with registrar column directly (standard WHMCS structure)
                $whmcsDomainInfo = Capsule::table('tbldomains')
                    ->where('domain', $zone['name'])
                    ->select('userid', 'expirydate', 'registrar')
                    ->first();
                
                if ($whmcsDomainInfo) {
                    $clientId = $whmcsDomainInfo->userid ?? 0;
                    $registrarName = $whmcsDomainInfo->registrar ?? 'Cloudflare';
                    $expiryDate = $whmcsDomainInfo->expirydate ?? null;
                }
            } catch (Exception $e) {
                // If registrar column doesn't exist, try without it
                if ($this->debug) {
                    error_log("Error fetching WHMCS domain info (trying alternative): " . $e->getMessage());
                }
                
                try {
                    $whmcsDomainInfo = Capsule::table('tbldomains')
                        ->where('domain', $zone['name'])
                        ->select('userid', 'expirydate')
                        ->first();
                    
                    if ($whmcsDomainInfo) {
                        $clientId = $whmcsDomainInfo->userid ?? 0;
                        $expiryDate = $whmcsDomainInfo->expirydate ?? null;
                    }
                } catch (Exception $e2) {
                    // If tbldomains table doesn't exist or domain not found, continue with defaults
                    if ($this->debug) {
                        error_log("Domain not found in WHMCS: " . $zone['name']);
                    }
                }
            }
            
            $now = date('Y-m-d H:i:s');
            
            // Prepare settings
            $settings = [
                'name_servers' => isset($zone['name_servers']) ? $zone['name_servers'] : [],
                'original_registrar' => isset($zone['original_registrar']) ? $zone['original_registrar'] : '',
                'original_dnshost' => isset($zone['original_dnshost']) ? $zone['original_dnshost'] : '',
                'ssl' => ['status' => 'active'],
                'plan' => isset($zone['plan']) ? $zone['plan'] : ['name' => 'Free']
            ];
            
            // Get SSL status
            if ($this->api) {
                try {
                    $sslStatus = $this->api->getSSLStatus($zone['id']);
                    if (isset($sslStatus['status'])) {
                        $settings['ssl']['status'] = strtolower($sslStatus['status']);
                    }
                } catch (Exception $e) {
                    if ($this->debug) {
                        error_log("SSL Status Error: " . $e->getMessage());
                    }
                }
            }
            
            // Prepare domain data
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
            
            if (isset($zone['created_on'])) {
                $domainData['created_at'] = date('Y-m-d H:i:s', strtotime($zone['created_on']));
            } else {
                $domainData['created_at'] = $now;
            }
            
            if ($existingDomain) {
                // Preserve analytics
                if ($existingDomain->analytics) {
                    $domainData['analytics'] = $existingDomain->analytics;
                }
                
                Capsule::table('mod_cloudflaremanager_domains')
                    ->where('zone_id', $zone['id'])
                    ->update($domainData);
                
                $domainId = $existingDomain->id;
            } else {
                $domainId = Capsule::table('mod_cloudflaremanager_domains')
                    ->insertGetId($domainData);
            }
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (syncSingleDomain): " . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Purge cache for zone
     */
    public function purgeCache($zoneId, $purgeEverything = true, $files = [], $tags = [], $hosts = []) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $result = $this->api->purgeCache($zoneId, $purgeEverything, $files, $tags, $hosts);
            
            return [
                'success' => true,
                'message' => isset($this->lang['cache_purged']) ? 
                    $this->lang['cache_purged'] : 
                    'Cache purged successfully.'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (purgeCache): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['cache_purge_error']) ? 
                    $this->lang['cache_purge_error'] : 
                    'Error purging cache') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update domain analytics
     */
    public function updateAnalytics($zoneId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->first();
            
            if (!$domain) {
                throw new Exception('Domain not found');
            }
            
            // Check if analytics was updated recently (within 15 minutes)
            $existingAnalytics = $domain->analytics ? json_decode($domain->analytics, true) : null;
            if ($existingAnalytics && isset($existingAnalytics['last_updated'])) {
                $lastUpdateTime = strtotime($existingAnalytics['last_updated']);
                if ($lastUpdateTime > (time() - 900)) { // 15 minutes
                    return true; // Already up to date
                }
            }
            
            // Get analytics data
            $analytics = $this->api->getZoneAnalytics($zoneId);
            
            // Prepare analytics data
            $analyticsData = [
                'analytics' => $analytics ?: ['totals' => []],
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            // Update database
            Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->update([
                    'analytics' => json_encode($analyticsData),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (updateAnalytics): " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get domain details
     */
    public function getDomainDetails($domainId) {
        try {
            if (!$this->api) {
                throw new Exception("API not initialized");
            }
            
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
            
            if (!$domain) {
                throw new Exception(
                    isset($this->lang['domain_not_found']) ? 
                        $this->lang['domain_not_found'] : 
                        'Domain not found'
                );
            }
            
            // Get zone details
            $zoneDetails = $this->api->getZone($domain->zone_id);
            
            // Update analytics
            $this->updateAnalytics($domain->zone_id);
            
            // Get updated domain
            $updatedDomain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
            
            return [
                'domain' => $updatedDomain,
                'zone_details' => $zoneDetails,
                'analytics' => $updatedDomain->analytics ? json_decode($updatedDomain->analytics, true) : null,
                'settings' => $updatedDomain->settings ? json_decode($updatedDomain->settings, true) : null
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DomainManager Error (getDomainDetails): " . $e->getMessage());
            }
            throw new Exception(
                isset($this->lang['error_fetching_details']) ? 
                    $this->lang['error_fetching_details'] : 
                    'Error fetching domain details'
            );
        }
    }
}
}
