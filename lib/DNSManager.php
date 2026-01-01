<?php
/**
 * DNS Manager - Professional DNS Record Management
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     2.0.0
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!class_exists('CloudflareManager\DNSManager')) {
class DNSManager {
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
     * Get supported DNS record types
     */
    public function getSupportedRecordTypes() {
        return [
            'A' => 'A (IPv4 Address)',
            'AAAA' => 'AAAA (IPv6 Address)',
            'CNAME' => 'CNAME (Canonical Name)',
            'TXT' => 'TXT (Text Record)',
            'MX' => 'MX (Mail Exchange)',
            'NS' => 'NS (Name Server)',
            'SRV' => 'SRV (Service Record)',
            'CAA' => 'CAA (Certificate Authority Authorization)',
            'DNSKEY' => 'DNSKEY (DNS Key)',
            'DS' => 'DS (Delegation Signer)',
            'NAPTR' => 'NAPTR (Name Authority Pointer)',
            'SMIMEA' => 'SMIMEA (S/MIME Certificate)',
            'SSHFP' => 'SSHFP (SSH Fingerprint)',
            'TLSA' => 'TLSA (Transport Layer Security)',
            'URI' => 'URI (Uniform Resource Identifier)'
        ];
    }
    
    /**
     * Get supported TTL options
     */
    public function getSupportedTTLOptions() {
        return [
            1 => isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic',
            120 => '2 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            300 => '5 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            600 => '10 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            900 => '15 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            1800 => '30 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            3600 => '1 ' . (isset($this->lang['hour']) ? $this->lang['hour'] : 'Hour'),
            7200 => '2 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours'),
            18000 => '5 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours'),
            43200 => '12 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours'),
            86400 => '1 ' . (isset($this->lang['day']) ? $this->lang['day'] : 'Day')
        ];
    }
    
    /**
     * Check if record type supports proxying
     */
    public function isProxyableType($type) {
        return in_array(strtoupper($type), ['A', 'AAAA', 'CNAME']);
    }
    
    /**
     * Check if record type needs priority field
     */
    public function needsPriority($type) {
        return in_array(strtoupper($type), ['MX', 'SRV', 'URI']);
    }
    
    /**
     * Validate DNS record data
     */
    public function validateRecordData($type, $name, $content, $priority = null) {
        $errors = [];
        
        if (empty($type)) {
            $errors[] = isset($this->lang['type_required']) ? $this->lang['type_required'] : 'Type is required';
        }
        
        if (empty($name)) {
            $errors[] = isset($this->lang['name_required']) ? $this->lang['name_required'] : 'Name is required';
        }
        
        if (empty($content)) {
            $errors[] = isset($this->lang['content_required']) ? $this->lang['content_required'] : 'Content is required';
        }
        
        // Type-specific validation
        switch (strtoupper($type)) {
            case 'A':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $errors[] = isset($this->lang['invalid_ipv4_address']) ? 
                        $this->lang['invalid_ipv4_address'] : 'Invalid IPv4 address';
                }
                break;
                
            case 'AAAA':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $errors[] = isset($this->lang['invalid_ipv6_address']) ? 
                        $this->lang['invalid_ipv6_address'] : 'Invalid IPv6 address';
                }
                break;
                
            case 'MX':
            case 'SRV':
            case 'URI':
                if ($priority === null || $priority === '') {
                    $errors[] = isset($this->lang['priority_required']) ? 
                        $this->lang['priority_required'] : 'Priority is required for this record type';
                } elseif (!is_numeric($priority) || $priority < 0 || $priority > 65535) {
                    $errors[] = isset($this->lang['invalid_priority']) ? 
                        $this->lang['invalid_priority'] : 'Priority must be between 0 and 65535';
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * Get DNS records for a zone
     */
    public function getDnsRecords($zoneId, $page = 1, $perPage = 100, $type = '') {
        try {
            $records = $this->api->listDnsRecords($zoneId, $page, $perPage, $type);
            
            // Get domain info for zone name
            $domainInfo = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->first(['domain']);
            
            if ($domainInfo && is_array($records)) {
                foreach ($records as &$record) {
                    $record['zone_name'] = $domainInfo->domain;
                }
            }
            
            return is_array($records) ? $records : [];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecords): " . $e->getMessage());
            }
            throw new Exception(
                isset($this->lang['error_fetching_dns']) ? 
                    $this->lang['error_fetching_dns'] . ': ' . $e->getMessage() : 
                    'Error fetching DNS records: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get DNS records from database
     */
    public function getDnsRecordsFromDB($domainId) {
        try {
            $records = Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('domain_id', $domainId)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
            
            return $records;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecordsFromDB): " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Get single DNS record
     */
    public function getDnsRecord($zoneId, $recordId) {
        try {
            return $this->api->getDnsRecord($zoneId, $recordId);
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecord): " . $e->getMessage());
            }
            throw new Exception(
                isset($this->lang['error_fetching_dns_record']) ? 
                    $this->lang['error_fetching_dns_record'] . ': ' . $e->getMessage() : 
                    'Error fetching DNS record: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create DNS record
     */
    public function createDnsRecord($zoneId, $data) {
        try {
            // Validate data
            $type = isset($data['type']) ? $data['type'] : '';
            $name = isset($data['name']) ? $data['name'] : '';
            $content = isset($data['content']) ? $data['content'] : '';
            $priority = isset($data['priority']) ? $data['priority'] : null;
            
            $errors = $this->validateRecordData($type, $name, $content, $priority);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }
            
            // Prepare data
            $recordData = [
                'type' => strtoupper($type),
                'name' => $name,
                'content' => $content,
                'ttl' => isset($data['ttl']) ? (int)$data['ttl'] : 1,
            ];
            
            // Add proxied flag (only for proxyable types)
            if ($this->isProxyableType($type)) {
                $recordData['proxied'] = isset($data['proxied']) && $data['proxied'] == '1';
            } else {
                $recordData['proxied'] = false;
            }
            
            // Add priority if needed
            if ($this->needsPriority($type) && $priority !== null) {
                $recordData['priority'] = (int)$priority;
            }
            
            // Create record via API
            $result = $this->api->createDnsRecord($zoneId, $recordData);
            
            // Save to database
            $domainId = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->value('id');
            
            if ($domainId && isset($result['id'])) {
                $now = date('Y-m-d H:i:s');
                Capsule::table('mod_cloudflaremanager_dns_records')->insert([
                    'domain_id' => $domainId,
                    'record_id' => $result['id'],
                    'type' => $result['type'],
                    'name' => $result['name'],
                    'content' => $result['content'],
                    'ttl' => $result['ttl'],
                    'proxied' => $result['proxied'] ? 1 : 0,
                    'priority' => isset($result['priority']) ? $result['priority'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_added']) ? 
                    $this->lang['dns_record_added'] : 
                    'DNS record added successfully',
                'record' => $result
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (createDnsRecord): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_add_error']) ? 
                    $this->lang['dns_record_add_error'] : 
                    'Error adding DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update DNS record
     */
    public function updateDnsRecord($zoneId, $recordId, $data) {
        try {
            // Validate data
            $type = isset($data['type']) ? $data['type'] : '';
            $name = isset($data['name']) ? $data['name'] : '';
            $content = isset($data['content']) ? $data['content'] : '';
            $priority = isset($data['priority']) ? $data['priority'] : null;
            
            $errors = $this->validateRecordData($type, $name, $content, $priority);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }
            
            // Prepare data
            $recordData = [
                'type' => strtoupper($type),
                'name' => $name,
                'content' => $content,
                'ttl' => isset($data['ttl']) ? (int)$data['ttl'] : 1,
            ];
            
            // Add proxied flag (only for proxyable types)
            if ($this->isProxyableType($type)) {
                $recordData['proxied'] = isset($data['proxied']) && $data['proxied'] == '1';
            } else {
                $recordData['proxied'] = false;
            }
            
            // Add priority if needed
            if ($this->needsPriority($type) && $priority !== null) {
                $recordData['priority'] = (int)$priority;
            }
            
            // Update record via API
            $result = $this->api->updateDnsRecord($zoneId, $recordId, $recordData);
            
            // Update database
            $now = date('Y-m-d H:i:s');
            $updateData = [
                'type' => $result['type'],
                'name' => $result['name'],
                'content' => $result['content'],
                'ttl' => $result['ttl'],
                'proxied' => $result['proxied'] ? 1 : 0,
                'updated_at' => $now,
            ];
            
            if (isset($result['priority'])) {
                $updateData['priority'] = $result['priority'];
            }
            
            Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('record_id', $recordId)
                ->update($updateData);
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_updated']) ? 
                    $this->lang['dns_record_updated'] : 
                    'DNS record updated successfully',
                'record' => $result
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (updateDnsRecord): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_update_error']) ? 
                    $this->lang['dns_record_update_error'] : 
                    'Error updating DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete DNS record
     */
    public function deleteDnsRecord($zoneId, $recordId) {
        try {
            // Delete via API
            $this->api->deleteDnsRecord($zoneId, $recordId);
            
            // Delete from database
            Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('record_id', $recordId)
                ->delete();
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_deleted']) ? 
                    $this->lang['dns_record_deleted'] : 
                    'DNS record deleted successfully'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (deleteDnsRecord): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_delete_error']) ? 
                    $this->lang['dns_record_delete_error'] : 
                    'Error deleting DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync DNS records for a domain
     */
    public function syncRecordsByDomain($domainId) {
        try {
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
            
            // Get all DNS records from API
            $allRecords = [];
            $page = 1;
            $perPage = 100;
            
            do {
                $records = $this->api->listDnsRecords($domain->zone_id, $page, $perPage);
                if (is_array($records)) {
                    $allRecords = array_merge($allRecords, $records);
                    $page++;
                } else {
                    break;
                }
            } while (count($records) == $perPage);
            
            $now = date('Y-m-d H:i:s');
            
            // Use transaction for bulk operations
            Capsule::connection()->transaction(function () use ($domainId, $allRecords, $now) {
                // Clear existing records
                Capsule::table('mod_cloudflaremanager_dns_records')
                    ->where('domain_id', $domainId)
                    ->delete();
                
                // Prepare bulk insert
                $bulkInsertData = [];
                foreach ($allRecords as $record) {
                    $bulkInsertData[] = [
                        'domain_id' => $domainId,
                        'record_id' => $record['id'],
                        'type' => $record['type'],
                        'name' => $record['name'],
                        'content' => $record['content'],
                        'ttl' => $record['ttl'],
                        'proxied' => $record['proxied'] ? 1 : 0,
                        'priority' => isset($record['priority']) ? $record['priority'] : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                // Bulk insert
                if (!empty($bulkInsertData)) {
                    foreach (array_chunk($bulkInsertData, 100) as $chunk) {
                        Capsule::table('mod_cloudflaremanager_dns_records')->insert($chunk);
                    }
                }
            });
            
            return [
                'success' => true,
                'count' => count($allRecords),
                'message' => isset($this->lang['dns_records_synced']) ? 
                    $this->lang['dns_records_synced'] : 
                    'DNS records synchronized successfully'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (syncRecordsByDomain): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_sync_error']) ? 
                    $this->lang['dns_sync_error'] : 
                    'Error synchronizing DNS records') . ': ' . $e->getMessage()
            ];
        }
    }
}
}
