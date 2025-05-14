<?php
/**
 * Client Panel Class - Cloudflare Manager
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

class Client {
    protected $vars;
    protected $api;
    protected $clientId;
    protected $lang = [];
    protected $action;
    protected $domainManager;
    protected $dnsManager;
    protected $csrfToken;
    
    /**
     * Constructor
     */
    public function __construct($vars) {
        $this->vars = $vars;
        
        // Load language
        $this->loadLanguage();
        
        // Get client ID
        $this->clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
        
        // Create CSRF Token
        $this->csrfToken = md5(uniqid(rand(), true));
        $_SESSION['cloudflaremanager_csrf'] = $this->csrfToken;
        
        // Determine action type
        $this->action = isset($_GET['action']) ? $_GET['action'] : 'overview';
        
        // Get cache settings for API and managers
        $cacheSettings = [
            'use_cache' => $vars['use_cache'] ?? 'yes',
            'cache_expiry' => $vars['cache_expiry'] ?? 300
        ];
        
        // Initialize API
        try {
            $this->api = new CloudflareAPI($vars['api_email'], $vars['api_key'], $cacheSettings);
            
            // Initialize Domain and DNS Managers
            $this->domainManager = new DomainManager($this->api, $this->lang);
            $this->dnsManager = new DNSManager($this->api, $this->lang);
            
            // Check for post requests
            $this->handlePostRequests();
            
        } catch (Exception $e) {
            // API errors will be handled elsewhere
        }
    }
    
    /**
     * Load language file
     */
    protected function loadLanguage() {
        // Multi-language support
        $langFile = dirname(__DIR__) . '/lang/' . strtolower($this->vars['language']) . '.php';
        if (file_exists($langFile)) {
            require_once $langFile;
        } else {
            require_once dirname(__DIR__) . '/lang/turkish.php'; // Default language
        }
        
        // Create LANG variable
        global $_LANG;
        $this->lang = $_LANG;
    }
    
    /**
     * Handle form submissions
     */
    protected function handlePostRequests() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF check
            if (!isset($_POST['token']) || $_POST['token'] !== $this->csrfToken) {
                return;
            }
            
            // Cache purging action
            if (isset($_POST['action']) && $_POST['action'] === 'purge_cache') {
                $this->purgeCache();
            }
        }
    }
    
    /**
     * Display client panel
     */
    public function display() {
        try {
            switch ($this->action) {
                case 'dns':
                    return $this->displayDnsRecords();
                
                case 'details':
                    return $this->displayDomainDetails();
                
                default:
                    return $this->displayDomainList();
            }
        } catch (Exception $e) {
            return [
                'pagetitle' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
                'breadcrumb' => [
                    'index.php?m=cloudflaremanager' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
                ],
                'templatefile' => '',
                'requirelogin' => true,
                'vars' => [
                    'error' => $e->getMessage(),
                    'LANG' => $this->lang,
                    'token' => $this->csrfToken
                ],
                'outputTemplateFile' => false,
                'outputTemplate' => '
<div class="container-fluid">
    <h2>' . (isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager') . '</h2>
    <div class="alert alert-danger">' . (isset($this->lang['error']) ? $this->lang['error'] : 'Error') . ': {$error}</div>
</div>',
            ];
        }
    }
    
    /**
     * Display domain list
     */
    protected function displayDomainList() {
        // List client's domains
        $domains = Capsule::table('mod_cloudflaremanager_domains')
            ->where('client_id', $this->clientId)
            ->get();
        
        // Get client permissions
        $permissions = explode(',', $this->vars['client_permissions']);
        
        return [
            'pagetitle' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
            'breadcrumb' => [
                'index.php?m=cloudflaremanager' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
            ],
            'templatefile' => '',
            'requirelogin' => true,
            'vars' => [
                'domains' => $domains,
                'permissions' => $permissions,
                'LANG' => $this->lang,
                'token' => $this->csrfToken
            ],
            'outputTemplateFile' => false,
            'outputTemplate' => '
            <div class="container-fluid">
                <h2>' . (isset($this->lang['your_domains']) ? $this->lang['your_domains'] : 'Your Domains') . '</h2>
                
                {if isset($error)}
                    <div class="alert alert-danger">{$error}</div>
                {/if}
                
                {if isset($success)}
                    <div class="alert alert-success">{$success}</div>
                {/if}
                
                {if count($domains) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>' . (isset($this->lang['domain_name']) ? $this->lang['domain_name'] : 'Domain Name') . '</th>
                                    <th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th>
                                    <th>' . (isset($this->lang['created_on']) ? $this->lang['created_on'] : 'Created On') . '</th>
                                    {if in_array("view_ssl_status", $permissions)}
                                        <th>' . (isset($this->lang['ssl_status']) ? $this->lang['ssl_status'] : 'SSL Status') . '</th>
                                    {/if}
                                    <th>' . (isset($this->lang['actions']) ? $this->lang['actions'] : 'Actions') . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$domains item=domain}
                                    {$settings = json_decode($domain->settings, true)}
                                    <tr>
                                        <td>{$domain->domain}</td>
                                        <td>
                                            {if $domain->zone_status eq "active" || strtolower($domain->zone_status) eq "active"}
                                                <span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>
                                            {else}
                                                <span class="label label-default">' . (isset($this->lang['inactive']) ? $this->lang['inactive'] : 'Inactive') . '</span>
                                            {/if}
                                        </td>
                                        <td>{$domain->created_at|date_format:"%d.%m.%Y"}</td>
                                        {if in_array("view_ssl_status", $permissions)}
                                            <td>
                                                {if isset($settings.ssl) && isset($settings.ssl.status) && $settings.ssl.status eq "active"}
                                                    <span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>
                                                {else}
                                                    <span class="label label-default">' . (isset($this->lang['inactive']) ? $this->lang['inactive'] : 'Inactive') . '</span>
                                                {/if}
                                            </td>
                                        {/if}
                                        <td>
                                            <div class="btn-group">
                                                {if in_array("view_domain_details", $permissions)}
                                                    <a href="index.php?m=cloudflaremanager&action=details&domain_id={$domain->id}" class="btn btn-default btn-sm">
                                                        <i class="fa fa-info-circle"></i> ' . (isset($this->lang['details']) ? $this->lang['details'] : 'Details') . '
                                                    </a>
                                                {/if}
                                                
                                                {if in_array("view_dns_records", $permissions)}
                                                    <a href="index.php?m=cloudflaremanager&action=dns&domain_id={$domain->id}" class="btn btn-primary btn-sm">
                                                        <i class="fa fa-list"></i> ' . (isset($this->lang['dns_records']) ? $this->lang['dns_records'] : 'DNS Records') . '
                                                    </a>
                                                {/if}
                                                
                                                {if in_array("view_cache_status", $permissions)}
                                                    <form method="post" action="index.php?m=cloudflaremanager" style="display:inline;">
                                                        <input type="hidden" name="token" value="{$token}">
                                                        <input type="hidden" name="action" value="purge_cache">
                                                        <input type="hidden" name="domain_id" value="{$domain->id}">
                                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm(\'' . (isset($this->lang['confirm_purge_cache']) ? $this->lang['confirm_purge_cache'] : 'Are you sure you want to purge the cache?') . '\')">
                                                            <i class="fa fa-trash"></i> ' . (isset($this->lang['purge_cache']) ? $this->lang['purge_cache'] : 'Purge Cache') . '
                                                        </button>
                                                    </form>
                                                {/if}
                                            </div>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <div class="alert alert-info">' . (isset($this->lang['no_domains_found']) ? $this->lang['no_domains_found'] : 'No domains found.') . '</div>
                {/if}
            </div>',
        ];
    }
    
    /**
     * Display DNS records
     */
    protected function displayDnsRecords() {
        // Check client permissions
        $permissions = explode(',', $this->vars['client_permissions']);
        if (!in_array('view_dns_records', $permissions)) {
            header('Location: index.php?m=cloudflaremanager');
            exit;
        }
        
        // Get domain ID
        $domainId = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;
        
        // Get domain info
        $domain = Capsule::table('mod_cloudflaremanager_domains')
            ->where('id', $domainId)
            ->where('client_id', $this->clientId)
            ->first();
        
        if (!$domain) {
            header('Location: index.php?m=cloudflaremanager');
            exit;
        }
        
        // Sync and get DNS records
        $this->dnsManager->syncRecordsByDomain($domainId);
        $dnsRecords = Capsule::table('mod_cloudflaremanager_dns_records')
            ->where('domain_id', $domainId)
            ->get();
        
        // Check if any records have priority
        $hasPriorityRecords = false;
        foreach ($dnsRecords as $record) {
            if (isset($record->priority) && !empty($record->priority)) {
                $hasPriorityRecords = true;
                break;
            }
        }
        
        return [
            'pagetitle' => (isset($this->lang['dns_records_for']) ? $this->lang['dns_records_for'] : 'DNS Records for') . ' ' . $domain->domain,
            'breadcrumb' => [
                'index.php?m=cloudflaremanager' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
                'index.php?m=cloudflaremanager&action=dns&domain_id=' . $domainId => isset($this->lang['dns_records']) ? $this->lang['dns_records'] : 'DNS Records',
            ],
            'templatefile' => '',
            'requirelogin' => true,
            'vars' => [
                'domain' => $domain,
                'dns_records' => $dnsRecords,
                'has_priority' => $hasPriorityRecords,
                'permissions' => $permissions,
                'LANG' => $this->lang,
                'token' => $this->csrfToken
            ],
            'outputTemplateFile' => false,
            'outputTemplate' => '
<div class="container-fluid">
    <h2>' . (isset($this->lang['dns_records_for']) ? $this->lang['dns_records_for'] : 'DNS Records for') . ' {$domain->domain}</h2>
    
    <p><a href="index.php?m=cloudflaremanager" class="btn btn-default">' . (isset($this->lang['back_to_domains']) ? $this->lang['back_to_domains'] : 'Back to Domains') . '</a></p>
    
    {if count($dns_records) > 0}
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th style="width:10%;">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</th>
                        <th style="width:25%;">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</th>
                        {if $has_priority}
                            <th style="width:10%;">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</th>
                        {/if}
                        <th style="width:45%; max-width:300px;">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</th>
                        <th style="width:10%;">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</th>
                        {if in_array("view_cache_status", $permissions)}
                            <th style="width:10%;">' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '</th>
                        {/if}
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$dns_records item=record}
                        <tr>
                            <td>{$record->type}</td>
                            <td style="word-break:break-all;">{$record->name}</td>
                            {if $has_priority}
                                <td>{if isset($record->priority)}{$record->priority}{else}-{/if}</td>
                            {/if}
                            <td style="word-break:break-all; max-width:300px; overflow:hidden; text-overflow:ellipsis;" title="{$record->content}">
                                {if strlen($record->content) > 40}
                                    {substr($record->content, 0, 37)}...
                                {else}
                                    {$record->content}
                                {/if}
                            </td>
                            <td>
                                {if $record->ttl eq 1}
                                    ' . (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic') . '
                                {else}
                                    {$record->ttl}
                                {/if}
                            </td>
                            {if in_array("view_cache_status", $permissions)}
                                <td>
                                    {if $record->proxied}
                                        <span class="label label-success">' . (isset($this->lang['yes']) ? $this->lang['yes'] : 'Yes') . '</span>
                                    {else}
                                        <span class="label label-default">' . (isset($this->lang['no']) ? $this->lang['no'] : 'No') . '</span>
                                    {/if}
                                </td>
                            {/if}
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {else}
        <div class="alert alert-info">' . (isset($this->lang['no_dns_records']) ? $this->lang['no_dns_records'] : 'No DNS records found.') . '</div>
    {/if}
</div>',
        ];
    }
    
    /**
     * Display domain details
     */
    protected function displayDomainDetails() {
        // Check client permissions
        $permissions = explode(',', $this->vars['client_permissions']);
        if (!in_array('view_domain_details', $permissions)) {
            header('Location: index.php?m=cloudflaremanager');
            exit;
        }
        
        // Get domain ID
        $domainId = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;
        
        // Get domain info
        $domain = Capsule::table('mod_cloudflaremanager_domains')
            ->where('id', $domainId)
            ->where('client_id', $this->clientId)
            ->first();
        
        if (!$domain) {
            header('Location: index.php?m=cloudflaremanager');
            exit;
        }
        
        // Update analytics data
        if ($this->domainManager) {
            $this->domainManager->updateAnalytics($domain->zone_id);
        }
        
        // Get updated domain info
        $domain = Capsule::table('mod_cloudflaremanager_domains')
            ->where('id', $domainId)
            ->first();
        
        // Decode domain settings
        $settings = json_decode($domain->settings, true);
        
        // Decode analytics data (if available)
        $analytics = null;
        if ($domain->analytics) {
            $analytics = json_decode($domain->analytics, true);
        }
        
        // If no analytics data, create default structure
        if (!$analytics || !isset($analytics['analytics']) || !isset($analytics['analytics']['totals'])) {
            $analytics = [
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
        }
        
        return [
            'pagetitle' => (isset($this->lang['details_for']) ? $this->lang['details_for'] : 'Details for') . ' ' . $domain->domain,
            'breadcrumb' => [
                'index.php?m=cloudflaremanager' => isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager',
                'index.php?m=cloudflaremanager&action=details&domain_id=' . $domainId => isset($this->lang['details']) ? $this->lang['details'] : 'Details',
            ],
            'templatefile' => '',
            'requirelogin' => true,
            'vars' => [
                'domain' => $domain,
                'settings' => $settings,
                'analytics' => $analytics,
                'permissions' => $permissions,
                'LANG' => $this->lang,
                'token' => $this->csrfToken
            ],
            'outputTemplateFile' => false,
            'outputTemplate' => '
<div class="container-fluid">
    <h2>' . (isset($this->lang['details_for']) ? $this->lang['details_for'] : 'Details for') . ' {$domain->domain}</h2>
    
    <p><a href="index.php?m=cloudflaremanager" class="btn btn-default">' . (isset($this->lang['back_to_domains']) ? $this->lang['back_to_domains'] : 'Back to Domains') . '</a></p>
    
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . ' ' . (isset($this->lang['details']) ? $this->lang['details'] : 'Details') . '</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <tr>
                            <th>' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th>
                            <td>{$domain->domain}</td>
                        </tr>
                        <tr>
                            <th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th>
                            <td>
                                {if $domain->zone_status eq "active" || strtolower($domain->zone_status) eq "active"}
                                    <span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>
                                {else}
                                    <span class="label label-default">{$domain->zone_status|default:"Inactive"}</span>
                                {/if}
                            </td>
                        </tr>
                        <tr>
                            <th>' . (isset($this->lang['created_on']) ? $this->lang['created_on'] : 'Created On') . '</th>
                            <td>{$domain->created_at|date_format:"%d.%m.%Y"}</td>
                        </tr>
                        {if $domain->expiry_date}
                            <tr>
                                <th>' . (isset($this->lang['expiry_date']) ? $this->lang['expiry_date'] : 'Expiry Date') . '</th>
                                <td>{$domain->expiry_date|date_format:"%d.%m.%Y"}</td>
                            </tr>
                        {/if}
                        <tr>
                            <th>' . (isset($this->lang['registrar']) ? $this->lang['registrar'] : 'Registrar') . '</th>
                            <td>{$domain->registrar}</td>
                        </tr>
                        {if in_array("view_ssl_status", $permissions) && isset($settings.ssl)}
                            <tr>
                                <th>' . (isset($this->lang['ssl_status']) ? $this->lang['ssl_status'] : 'SSL Status') . '</th>
                                <td>
                                    {if isset($settings.ssl.status) && $settings.ssl.status eq "active"}
                                        <span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>
                                    {else}
                                        <span class="label label-default">{if isset($settings.ssl.status)}{$settings.ssl.status|ucfirst}{else}Inactive{/if}</span>
                                    {/if}
                                </td>
                            </tr>
                        {/if}
                        {if isset($settings.name_servers) && is_array($settings.name_servers)}
                            <tr>
                                <th>' . (isset($this->lang['nameservers']) ? $this->lang['nameservers'] : 'Nameservers') . '</th>
                                <td>
                                    {foreach from=$settings.name_servers item=ns}
                                        {$ns}<br>
                                    {/foreach}
                                </td>
                            </tr>
                        {/if}
                    </table>
                </div>
            </div>
        </div>
        
        {if in_array("view_domain_details", $permissions) && $analytics}
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">' . (isset($this->lang['traffic_stats']) ? $this->lang['traffic_stats'] : 'Traffic Statistics') . '</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            {if isset($analytics.analytics.totals.visits)}
                                <tr>
                                    <th>' . (isset($this->lang['unique_visitors']) ? $this->lang['unique_visitors'] : 'Unique Visitors') . ' (24h)</th>
                                    <td>{$analytics.analytics.totals.visits|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.analytics.totals.pageviews)}
                                <tr>
                                    <th>' . (isset($this->lang['page_views']) ? $this->lang['page_views'] : 'Page Views') . ' (24h)</th>
                                    <td>{$analytics.analytics.totals.pageviews|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.analytics.totals.requests)}
                                <tr>
                                    <th>' . (isset($this->lang['total_requests']) ? $this->lang['total_requests'] : 'Total Requests') . ' (24h)</th>
                                    <td>{$analytics.analytics.totals.requests|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.analytics.totals.bandwidth)}
                                <tr>
                                    <th>Bandwidth (24h)</th>
                                    <td>{if $analytics.analytics.totals.bandwidth > 0}{$analytics.analytics.totals.bandwidth|number_format} B{else}0 B{/if}</td>
                                </tr>
                            {/if}
                        </table>
                    </div>
                </div>
                
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">' . (isset($this->lang['security_stats']) ? $this->lang['security_stats'] : 'Security Statistics') . '</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            {if isset($analytics.security.totals.bot_management)}
                                <tr>
                                    <th>Bot Management</th>
                                    <td>{$analytics.security.totals.bot_management|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.security.totals.firewall)}
                                <tr>
                                    <th>Firewall</th>
                                    <td>{$analytics.security.totals.firewall|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.security.totals.rate_limiting)}
                                <tr>
                                    <th>Rate Limiting</th>
                                    <td>{$analytics.security.totals.rate_limiting|number_format}</td>
                                </tr>
                            {/if}
                            {if isset($analytics.security.totals.waf)}
                                <tr>
                                    <th>WAF</th>
                                    <td>{$analytics.security.totals.waf|number_format}</td>
                                </tr>
                            {/if}
                            <tr class="success">
                                <th>' . (isset($this->lang['security_threats']) ? $this->lang['security_threats'] : 'Security Threats') . ' (24h)</th>
                                <td>
                                    {assign var=totalThreats value=0}
                                    {foreach from=$analytics.security.totals key=k item=v}
                                        {assign var=totalThreats value=$totalThreats+$v}
                                    {/foreach}
                                    {$totalThreats|number_format}
                                </td>
                            </tr>
                            {if isset($analytics.last_updated)}
                                <tr>
                                    <th>' . (isset($this->lang['data_last_updated']) ? $this->lang['data_last_updated'] : 'Data Last Updated') . '</th>
                                    <td>{$analytics.last_updated|date_format:"%d.%m.%Y %H:%M:%S"}</td>
                                </tr>
                            {/if}
                        </table>
                    </div>
                </div>
                
                {if in_array("view_cache_status", $permissions)}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">' . (isset($this->lang['cache_management']) ? $this->lang['cache_management'] : 'Cache Management') . '</h3>
                        </div>
                        <div class="panel-body">
                            <p>' . (isset($this->lang['cache_management_desc']) ? $this->lang['cache_management_desc'] : 'If you have updated your website content, you can purge the Cloudflare cache to ensure visitors see the latest version.') . '</p>
                            <form method="post" action="index.php?m=cloudflaremanager&action=details&domain_id={$domain->id}">
                                <input type="hidden" name="token" value="{$token}">
                                <input type="hidden" name="action" value="purge_cache">
                                <input type="hidden" name="domain_id" value="{$domain->id}">
                                <button type="submit" class="btn btn-warning" onclick="return confirm(\'' . (isset($this->lang['confirm_purge_cache']) ? $this->lang['confirm_purge_cache'] : 'Are you sure you want to purge the cache?') . '\')">
                                    <i class="fa fa-refresh"></i> ' . (isset($this->lang['purge_cache']) ? $this->lang['purge_cache'] : 'Purge Cache') . '
                                </button>
                            </form>
                        </div>
                    </div>
                {/if}
            </div>
        {/if}
    </div>
</div>',
        ];
    }
    
    /**
     * Purge cache action
     */
    public function purgeCache() {
        // Check client permissions
        $permissions = explode(',', $this->vars['client_permissions']);
        if (!in_array('view_cache_status', $permissions)) {
            return false;
        }
        
        // Get domain ID
        $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : 0;
        
        // Get domain info
        $domain = Capsule::table('mod_cloudflaremanager_domains')
            ->where('id', $domainId)
            ->where('client_id', $this->clientId)
            ->first();
        
        if (!$domain) {
            return false;
        }
        
        try {
            // Purge cache
            $result = $this->domainManager->purgeCache($domain->zone_id);
            
            // Success message
            $_SESSION['cloudflaremanager_success'] = isset($this->lang['cache_purged']) ? $this->lang['cache_purged'] : 'Cache purged successfully.';
            
            return true;
        } catch (Exception $e) {
            // Error message
            $_SESSION['cloudflaremanager_error'] = (isset($this->lang['cache_purge_error']) ? $this->lang['cache_purge_error'] : 'Error purging cache') . ': ' . $e->getMessage();
            
            return false;
        }
    }
    
    /**
     * Format bytes to human readable
     */
    protected function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}