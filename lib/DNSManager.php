<?php
/**
 * DNS Management Class - Cloudflare Manager
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

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
        $this->api->enableDebug();
        return $this;
    }
    
    /**
     * Get supported DNS record types
     */
    public function getSupportedRecordTypes() {
        return [
            'A' => 'A',
            'AAAA' => 'AAAA',
            'CNAME' => 'CNAME',
            'TXT' => 'TXT',
            'MX' => 'MX',
            'NS' => 'NS',
            'SRV' => 'SRV',
            'CAA' => 'CAA',
            'DNSKEY' => 'DNSKEY',
            'DS' => 'DS',
            'NAPTR' => 'NAPTR',
            'SMIMEA' => 'SMIMEA',
            'SSHFP' => 'SSHFP',
            'TLSA' => 'TLSA',
            'URI' => 'URI'
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
            43200 => '12 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours'),
            86400 => '1 ' . (isset($this->lang['day']) ? $this->lang['day'] : 'Day')
        ];
    }
    
    /**
     * Get example content for record type
     */
    public function getRecordTypeExample($type) {
        $examples = [
            'A' => '192.168.0.1',
            'AAAA' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'CNAME' => 'example.com',
            'MX' => 'mail.example.com',
            'TXT' => 'v=spf1 include:example.com ~all',
            'NS' => 'ns1.example.com',
            'SRV' => 'sip.example.com',
            'CAA' => '0 issue "letsencrypt.org"',
            'DNSKEY' => '257 3 13 mdsswUyr3DPW132mOi8V9xESWE8jTo0dxCjjnopKl+GqJxpVXckHAeF+KkxLbxILfDLUT0rAK9iUzy1L53eKGQ==',
            'DS' => '12345 13 2 1F8188345CD3BE1F533B5CE927F2C2A9B80C0D041F46E9E6210F594F5D5C8BA9',
            'NAPTR' => '10 100 "s" "SIP+D2T" "" _sip._tcp.example.com',
            'SMIMEA' => '3 0 0 308201A2300D06092A864886F70D01010B05003059301D060355045A13...',
            'SSHFP' => '2 1 123456789abcdef67890123456789abcdef67890',
            'TLSA' => '3 0 1 d2abde240d7cd3ee6b4b28c54df034b97983a1d16e8a410e4561cb106618e971',
            'URI' => '10 1 "https://example.com"'
        ];
        
        return isset($examples[$type]) ? $examples[$type] : '';
    }
    
    /**
     * Check if record type supports proxying
     */
    public function isProxyableType($type) {
        $proxyableTypes = ['A', 'AAAA', 'CNAME'];
        return in_array($type, $proxyableTypes);
    }
    
    /**
     * Check if record type needs priority field
     */
    public function needsPriority($type) {
        return in_array($type, ['MX', 'SRV', 'URI']);
    }
    
    /**
     * Get DNS records
     */
    public function getDnsRecords($zoneId) {
        try {
            $records = $this->api->listDnsRecords($zoneId);
            
            // Process records and add zone name
            $domainInfo = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->first();
                
            if ($domainInfo) {
                foreach ($records as &$record) {
                    $record['zone_name'] = $domainInfo->domain;
                }
            }
            
            return $records;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecords): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_dns']) ? $this->lang['error_fetching_dns'] . ': ' . $e->getMessage() : 'Error fetching DNS records: ' . $e->getMessage());
        }
    }
    
    /**
     * Get DNS record
     */
    public function getDnsRecord($zoneId, $recordId) {
        try {
            $record = $this->api->getDnsRecord($zoneId, $recordId);
            return $record;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecord): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['error_fetching_dns_record']) ? $this->lang['error_fetching_dns_record'] . ': ' . $e->getMessage() : 'Error fetching DNS record: ' . $e->getMessage());
        }
    }
    
    /**
     * Get DNS records from database
     */
    public function getDnsRecordsFromDB($domainId) {
        try {
            $records = Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('domain_id', $domainId)
                ->get();
            
            return $records;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (getDnsRecordsFromDB): " . $e->getMessage());
            }
            throw new Exception(isset($this->lang['database_error']) ? $this->lang['database_error'] . ': ' . $e->getMessage() : 'Database error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create DNS record - fixed and improved
     */
    public function createDnsRecord($zoneId, $data) {
        try {
            // Validate data
            if (empty($data['type']) || empty($data['name']) || !isset($data['content'])) {
                throw new Exception(isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing');
            }
            
            // Specific field validations
            switch ($data['type']) {
                case 'A':
                    if (!filter_var($data['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new Exception(isset($this->lang['invalid_ipv4_address']) ? $this->lang['invalid_ipv4_address'] : 'Invalid IPv4 address');
                    }
                    break;
                
                case 'AAAA':
                    if (!filter_var($data['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        throw new Exception(isset($this->lang['invalid_ipv6_address']) ? $this->lang['invalid_ipv6_address'] : 'Invalid IPv6 address');
                    }
                    break;
                
                case 'MX':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 10; // Default value
                    }
                    break;
                
                case 'SRV':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 1; // Default value
                    }
                    break;
                    
                case 'URI':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 1; // Default value
                    }
                    break;
            }
            
            // Turn off proxy for non-proxyable types
            if (!$this->isProxyableType($data['type'])) {
                $data['proxied'] = false;
            }
            
            // Fix numeric values
            if (isset($data['ttl'])) {
                $data['ttl'] = (int)$data['ttl'];
            }
            
            if (isset($data['priority'])) {
                $data['priority'] = (int)$data['priority'];
            }
            
            if ($this->debug) {
                error_log("Creating DNS Record: " . json_encode($data));
            }
            
            // Call API
            $result = $this->api->createDnsRecord($zoneId, $data);
            
            if ($this->debug) {
                error_log("API Response: " . json_encode($result));
            }
            
            // Get domain ID
            $domainId = Capsule::table('mod_cloudflaremanager_domains')
                ->where('zone_id', $zoneId)
                ->value('id');
            
            if ($domainId) {
                // Save to database
                $now = date('Y-m-d H:i:s');
                $insertData = [
                    'domain_id' => $domainId,
                    'record_id' => $result['id'],
                    'type' => $result['type'],
                    'name' => $result['name'],
                    'content' => $result['content'],
                    'ttl' => $result['ttl'],
                    'proxied' => $result['proxied'] ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                
                // Add priority
                if (isset($result['priority'])) {
                    $insertData['priority'] = $result['priority'];
                }
                
                Capsule::table('mod_cloudflaremanager_dns_records')->insert($insertData);
            }
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_added']) ? $this->lang['dns_record_added'] : 'DNS record added successfully',
                'record' => $result
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (createDnsRecord): " . $e->getMessage());
                error_log("Stack Trace: " . $e->getTraceAsString());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_add_error']) ? $this->lang['dns_record_add_error'] : 'Error adding DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update DNS record - fixed and improved
     */
    public function updateDnsRecord($zoneId, $recordId, $data) {
        try {
            // Validate data
            if (empty($data['type']) || empty($data['name']) || !isset($data['content'])) {
                throw new Exception(isset($this->lang['missing_required_fields']) ? $this->lang['missing_required_fields'] : 'Required fields are missing');
            }
            
            // Specific field validations
            switch ($data['type']) {
                case 'A':
                    if (!filter_var($data['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new Exception(isset($this->lang['invalid_ipv4_address']) ? $this->lang['invalid_ipv4_address'] : 'Invalid IPv4 address');
                    }
                    break;
                
                case 'AAAA':
                    if (!filter_var($data['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        throw new Exception(isset($this->lang['invalid_ipv6_address']) ? $this->lang['invalid_ipv6_address'] : 'Invalid IPv6 address');
                    }
                    break;
                
                case 'MX':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 10; // Default value
                    }
                    break;
                
                case 'SRV':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 1; // Default value
                    }
                    break;
                    
                case 'URI':
                    if (!isset($data['priority'])) {
                        $data['priority'] = 1; // Default value
                    }
                    break;
            }
            
            // Turn off proxy for non-proxyable types
            if (!$this->isProxyableType($data['type'])) {
                $data['proxied'] = false;
            }
            
            // Fix numeric values
            if (isset($data['ttl'])) {
                $data['ttl'] = (int)$data['ttl'];
            }
            
            if (isset($data['priority'])) {
                $data['priority'] = (int)$data['priority'];
            }
            
            if ($this->debug) {
                error_log("Updating DNS Record: " . json_encode($data));
            }
            
            // Call API
            $result = $this->api->updateDnsRecord($zoneId, $recordId, $data);
            
            if ($this->debug) {
                error_log("API Response: " . json_encode($result));
            }
            
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
            
            // Add priority
            if (isset($result['priority'])) {
                $updateData['priority'] = $result['priority'];
            }
            
            Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('record_id', $recordId)
                ->update($updateData);
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_updated']) ? $this->lang['dns_record_updated'] : 'DNS record updated successfully',
                'record' => $result
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (updateDnsRecord): " . $e->getMessage());
                error_log("Stack Trace: " . $e->getTraceAsString());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_update_error']) ? $this->lang['dns_record_update_error'] : 'Error updating DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete DNS record
     */
    public function deleteDnsRecord($zoneId, $recordId) {
        try {
            // Call API
            $result = $this->api->deleteDnsRecord($zoneId, $recordId);
            
            // Delete from database
            Capsule::table('mod_cloudflaremanager_dns_records')
                ->where('record_id', $recordId)
                ->delete();
            
            return [
                'success' => true,
                'message' => isset($this->lang['dns_record_deleted']) ? $this->lang['dns_record_deleted'] : 'DNS record deleted successfully'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (deleteDnsRecord): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_record_delete_error']) ? $this->lang['dns_record_delete_error'] : 'Error deleting DNS record') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync DNS records by domain - optimized
     */
    public function syncRecordsByDomain($domainId) {
        try {
            // Get domain info first
            $domain = Capsule::table('mod_cloudflaremanager_domains')
                ->where('id', $domainId)
                ->first();
            
            if (!$domain) {
                throw new Exception(isset($this->lang['domain_not_found']) ? $this->lang['domain_not_found'] : 'Domain not found');
            }
            
            // Get DNS records
            $records = $this->api->listDnsRecords($domain->zone_id);
            $now = date('Y-m-d H:i:s');
            
            // Database operations - use transaction for optimization
            Capsule::connection()->transaction(function () use ($domainId, $records, $now) {
                // Clear existing records
                Capsule::table('mod_cloudflaremanager_dns_records')
                    ->where('domain_id', $domainId)
                    ->delete();
                
                // Prepare new records
                $bulkInsertData = [];
                foreach ($records as $record) {
                    $insertData = [
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
                    
                    // Add priority
                    if (isset($record['priority'])) {
                        $insertData['priority'] = $record['priority'];
                    }
                    
                    $bulkInsertData[] = $insertData;
                }
                
                // Bulk insert (faster)
                if (!empty($bulkInsertData)) {
                    Capsule::table('mod_cloudflaremanager_dns_records')->insert($bulkInsertData);
                }
            });
            
            return [
                'success' => true,
                'count' => count($records),
                'message' => isset($this->lang['dns_records_synced']) ? $this->lang['dns_records_synced'] : 'DNS records synchronized successfully'
            ];
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("DNSManager Error (syncRecordsByDomain): " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => (isset($this->lang['dns_sync_error']) ? $this->lang['dns_sync_error'] : 'Error synchronizing DNS records') . ': ' . $e->getMessage()
            ];
        }
    }
}