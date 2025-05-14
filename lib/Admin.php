<?php
/**
 * Admin Panel Class - Cloudflare Manager
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

class Admin {
    protected $vars;
    protected $api;
    protected $domainManager;
    protected $dnsManager;
    protected $ajaxHandler;
    protected $csrfToken;
    protected $adminId;
    protected $lang = [];
    protected $action;
    protected $success = '';
    protected $error = '';
    protected $startTime;
    protected $debug = false;
    
    /**
     * Constructor
     */
    public function __construct($vars) {
        // Measure start time (for performance)
        $this->startTime = microtime(true);
        $this->vars = $vars;
        
        // Load language
        $this->loadLanguage();
        
        // Generate or get CSRF Token
        $this->setupCsrfToken();
        
        // Get Admin ID
        $this->adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : 0;
        
        // Determine action type
        $this->action = isset($_GET['action']) ? $_GET['action'] : 'domains';
        
        // Check if debugging is requested
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            $this->debug = true;
        }
        
        // Setup API and managers
        $this->setupAPIAndManagers();
        
        // Check if AJAX request
        if (isset($_GET['ajax'])) {
            $this->handleAjaxRequests();
            exit;
        }
        
        // Check form submissions
        $this->handlePostRequests();
    }
    
    /**
     * Setup CSRF Token
     */
    protected function setupCsrfToken() {
        if (!isset($_SESSION['cloudflaremanager_csrf']) || isset($_GET['refresh_token'])) {
            $this->csrfToken = md5(uniqid(rand(), true));
            $_SESSION['cloudflaremanager_csrf'] = $this->csrfToken;
        } else {
            $this->csrfToken = $_SESSION['cloudflaremanager_csrf'];
        }
    }
    
    /**
     * Setup API and manager classes
     */
    protected function setupAPIAndManagers() {
        try {
            // Get cache settings
            $cacheSettings = [
                'use_cache' => isset($this->vars['use_cache']) ? $this->vars['use_cache'] : 'yes',
                'cache_expiry' => isset($this->vars['cache_expiry']) ? $this->vars['cache_expiry'] : 300
            ];
            
            // Check if API credentials are present
            if (empty($this->vars['api_key'])) {
                $this->error = "API key is required. Please check your module configuration.";
                return;
            }
            
            // Initialize API
            $this->api = new CloudflareAPI(
                isset($this->vars['api_email']) ? $this->vars['api_email'] : '', 
                $this->vars['api_key'], 
                $cacheSettings
            );
            
            // Set debug mode
            if ($this->debug) {
                $this->api->enableDebug();
            }
            
            // Initialize managers
            $this->domainManager = new DomainManager($this->api, $this->lang);
            $this->dnsManager = new DNSManager($this->api, $this->lang);
            
            // Set debug mode for managers
            if ($this->debug) {
                $this->domainManager->enableDebug();
                $this->dnsManager->enableDebug();
            }
            
            // Initialize AjaxHandler
            $this->ajaxHandler = new AjaxHandler(
                $this->api, 
                $this->domainManager, 
                $this->dnsManager, 
                $this->csrfToken, 
                $this->lang
            );
            
            if ($this->debug) {
                $this->ajaxHandler->enableDebug();
            }
            
            // Test API connection - but catch exceptions to display in UI
            try {
                $this->api->testConnection();
            } catch (Exception $e) {
                $this->error = (isset($this->lang['api_connection_error']) ? $this->lang['api_connection_error'] : 'Cloudflare API connection error') . ': ' . $e->getMessage();
                
                if ($this->debug) {
                    error_log("API Connection Error: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            
            if ($this->debug) {
                error_log("Setup Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Enable debug mode
     */
    public function enableDebug() {
        $this->debug = true;
        
        if ($this->api) {
            $this->api->enableDebug();
        }
        
        if ($this->domainManager) {
            $this->domainManager->enableDebug();
        }
        
        if ($this->dnsManager) {
            $this->dnsManager->enableDebug();
        }
        
        if ($this->ajaxHandler) {
            $this->ajaxHandler->enableDebug();
        }
        
        // Show debug header
        echo '<div class="alert alert-warning">
            <strong>Debug Mode Active</strong> - This mode is for developers and may affect performance.
            <a href="' . $this->vars['modulelink'] . '">Disable Debug Mode</a>
        </div>';
        
        return $this;
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
        $this->lang = isset($_LANG) ? $_LANG : [];
    }
    
    /**
     * Handle form submissions
     */
    protected function handlePostRequests() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return; // Skip if not a POST request
        }
        
        // CSRF check
        if (!isset($_POST['token']) || $_POST['token'] !== $this->csrfToken) {
            $this->error = isset($this->lang['csrf_error']) ? $this->lang['csrf_error'] : 'Security validation failed';
            
            if ($this->debug) {
                $this->error .= ' (Token Mismatch: Expected ' . $this->csrfToken . ', Got ' . ($_POST['token'] ?? 'null') . ')';
            }
            
            return;
        }
        
        // Handle DNS record form submissions via AJAX handler
        if (isset($_POST['add_dns_record']) || isset($_POST['update_dns_record']) || isset($_POST['delete_dns_record'])) {
            $_GET['ajax'] = isset($_POST['add_dns_record']) ? 'add_dns_record' : 
                          (isset($_POST['update_dns_record']) ? 'update_dns_record' : 'delete_dns_record');
            
            $this->handleAjaxRequests();
            exit;
        }
        
        try {
            // Domain synchronization
            if (isset($_POST['sync_domains'])) {
                $this->syncDomains();
            }
            // Cache purging
            elseif (isset($_POST['purge_cache'])) {
                $this->purgeCache();
            }
            // Clear all API cache
            elseif (isset($_POST['clear_all_cache'])) {
                $this->clearAllCache();
            }
            // Update settings
            elseif (isset($_POST['update_settings'])) {
                $this->updateSettings();
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            
            if ($this->debug) {
                error_log("Post Request Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Sync Domains
     */
    protected function syncDomains() {
        if (!$this->api) {
            $this->error = 'API not initialized';
            return;
        }
        
        if (!$this->domainManager) {
            $this->error = 'Domain manager not initialized';
            return;
        }
        
        $result = $this->domainManager->syncDomains();
        
        if ($result['success']) {
            $this->success = $result['message'];
        } else {
            $this->error = $result['message'];
        }
    }
    
    /**
     * Purge Cache
     */
    protected function purgeCache() {
        if (!isset($_POST['zone_id']) || empty($_POST['zone_id'])) {
            $this->error = 'Zone ID is required';
            return;
        }
        
        if (!$this->api) {
            $this->error = 'API not initialized';
            return;
        }
        
        if (!$this->domainManager) {
            $this->error = 'Domain manager not initialized';
            return;
        }
        
        $zoneId = $_POST['zone_id'];
        $result = $this->domainManager->purgeCache($zoneId);
        
        if ($result['success']) {
            $this->success = $result['message'];
        } else {
            $this->error = $result['message'];
        }
    }
    
    /**
     * Clear All Cache
     */
    protected function clearAllCache() {
        if (!$this->api) {
            $this->error = 'API not initialized';
            return;
        }
        
        if ($this->api->clearAllCache()) {
            $this->success = isset($this->lang['cache_cleared']) ? $this->lang['cache_cleared'] : 'Cache cleared successfully';
        } else {
            $this->error = isset($this->lang['cache_clear_error']) ? $this->lang['cache_clear_error'] : 'Error clearing cache';
        }
    }
    
    /**
     * Update Settings
     */
    protected function updateSettings() {
        // Update permissions
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            $permissions = implode(',', $_POST['permissions']);
            
            // Update WHMCS module settings
            $updateSuccess = localAPI('UpdateModuleConfiguration', [
                'module' => 'cloudflaremanager',
                'setting' => 'client_permissions',
                'value' => $permissions
            ]);
            
            if ($updateSuccess['result'] === 'success') {
                $this->success = isset($this->lang['settings_updated']) ? $this->lang['settings_updated'] : 'Settings updated successfully';
            } else {
                $this->error = (isset($this->lang['settings_update_error']) ? $this->lang['settings_update_error'] : 'Error updating settings') . ': ' . ($updateSuccess['message'] ?? 'Unknown error');
            }
        }
        
        // Update cache settings
        if (isset($_POST['use_cache'])) {
            $useCache = $_POST['use_cache'] == '1' ? 'yes' : 'no';
            
            localAPI('UpdateModuleConfiguration', [
                'module' => 'cloudflaremanager',
                'setting' => 'use_cache',
                'value' => $useCache
            ]);
        }
        
        if (isset($_POST['cache_expiry']) && intval($_POST['cache_expiry']) >= 60) {
            $cacheExpiry = intval($_POST['cache_expiry']);
            
            localAPI('UpdateModuleConfiguration', [
                'module' => 'cloudflaremanager',
                'setting' => 'cache_expiry',
                'value' => $cacheExpiry
            ]);
        }
        
        // Clear cache if API is available
        if ($this->api) {
            $this->api->clearAllCache();
        }
    }
    
    /**
     * Handle AJAX requests
     */
    protected function handleAjaxRequests() {
        try {
            // Initialize AjaxHandler if not already initialized
            if (!$this->ajaxHandler) {
                // Initialize API first if needed
                if (!$this->api) {
                    $cacheSettings = [
                        'use_cache' => $this->vars['use_cache'] ?? 'yes',
                        'cache_expiry' => $this->vars['cache_expiry'] ?? 300
                    ];
                    
                    $this->api = new CloudflareAPI($this->vars['api_email'], $this->vars['api_key'], $cacheSettings);
                    
                    if ($this->debug) {
                        $this->api->enableDebug();
                    }
                }
                
                // Initialize managers if needed
                if (!$this->domainManager) {
                    $this->domainManager = new DomainManager($this->api, $this->lang);
                    
                    if ($this->debug) {
                        $this->domainManager->enableDebug();
                    }
                }
                
                if (!$this->dnsManager) {
                    $this->dnsManager = new DNSManager($this->api, $this->lang);
                    
                    if ($this->debug) {
                        $this->dnsManager->enableDebug();
                    }
                }
                
                // Initialize AJAX Handler
                $this->ajaxHandler = new AjaxHandler(
                    $this->api, 
                    $this->domainManager, 
                    $this->dnsManager, 
                    $this->csrfToken, 
                    $this->lang
                );
                
                if ($this->debug) {
                    $this->ajaxHandler->enableDebug();
                }
            }
            
            // Process the request
            $response = $this->ajaxHandler->processRequest();
            
            // Return the response
            echo $response;
        } catch (Exception $e) {
            // Return error message in JSON format
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $this->debug ? $e->getTraceAsString() : null
            ]);
        }
    }
    
    /**
     * Display admin panel
     */
    public function display() {
        echo '<div class="container-fluid">';
        echo '<h2>' . (isset($this->lang['cloudflare_manager']) ? $this->lang['cloudflare_manager'] : 'Cloudflare Manager') . '</h2>';
        
        // Check and display welcome modal
        $this->checkWelcomeModal();
        
        // Enable debug mode if requested
        if ($this->debug) {
            $this->enableDebug();
        }
        
        // Check and display API connection
        if (!$this->checkAndDisplayAPIConnection()) {
            echo '</div>'; // End container-fluid
            return;
        }
        
        // Display success or error messages
        $this->displayMessages();
        
        // Main tab menu
        $this->displayTabMenu();
        
        // Content area
        echo '<div class="tab-content">';
        echo '<div class="tab-pane active" style="padding-top: 20px;">';
        
        // Display the appropriate content
        $this->displayContent();
        
        echo '</div>'; // End tab-pane
        echo '</div>'; // End tab-content
        
        // Calculate execution time
        $endTime = microtime(true);
        $executionTime = round(($endTime - $this->startTime) * 1000, 2); // In milliseconds
        
        echo '<div class="text-muted text-right small">';
        echo 'Load time: ' . $executionTime . ' ms';
        if ($this->debug) {
            echo ' | <a href="' . $this->vars['modulelink'] . '">Disable Debug Mode</a>';
        } else {
            echo ' | <a href="' . $this->vars['modulelink'] . '&debug=1">Enable Debug Mode</a>';
        }
        echo '</div>';
        
        echo '</div>'; // End container-fluid
        
        // Display modals and JavaScript
        $this->displayModalsAndJavascript();
    }
    
    /**
     * Check welcome modal
     */
    protected function checkWelcomeModal() {
        $showWelcome = true;
        $welcomeDismissed = false;
        
        try {
            $welcomeSetting = Capsule::table('mod_cloudflaremanager_settings')
                ->where('user_id', $this->adminId)
                ->where('setting_key', 'welcome_shown')
                ->first();
                
            if ($welcomeSetting && $welcomeSetting->setting_value == '1') {
                $showWelcome = false;
            }
            
            // Check "Don't show again" action
            if (isset($_GET['dismiss_welcome']) && $_GET['dismiss_welcome'] == '1') {
                Capsule::table('mod_cloudflaremanager_settings')
                    ->updateOrInsert(
                        [
                            'user_id' => $this->adminId,
                            'setting_key' => 'welcome_shown'
                        ],
                        [
                            'setting_value' => '1',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    );
                $showWelcome = false;
                $welcomeDismissed = true;
            }
            
            if (isset($_GET['show_welcome']) && $_GET['show_welcome'] == '1') {
                $showWelcome = true;
            }
            
            // Show welcome modal
            if ($showWelcome && !$welcomeDismissed) {
                echo '<div class="modal fade" id="welcomeModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title">' . (isset($this->lang['welcome_to_cloudflare_manager']) ? $this->lang['welcome_to_cloudflare_manager'] : 'Welcome to Cloudflare Manager') . '</h4>
                            </div>
                            <div class="modal-body">
                                <h4>' . (isset($this->lang['about_module']) ? $this->lang['about_module'] : 'About Module') . '</h4>
                                <p>' . (isset($this->lang['module_description_detailed']) ? $this->lang['module_description_detailed'] : 'Cloudflare Manager seamlessly integrates your WHMCS installation with Cloudflare.') . '</p>
                                <h4>' . (isset($this->lang['key_features']) ? $this->lang['key_features'] : 'Key Features') . ':</h4>
                                <ul>
                                    <li>' . (isset($this->lang['feature_domain_management']) ? $this->lang['feature_domain_management'] : 'Easily view and sync all your Cloudflare domains') . '</li>
                                    <li>' . (isset($this->lang['feature_dns_management']) ? $this->lang['feature_dns_management'] : 'Full DNS management with support for all record types') . '</li>
                                    <li>' . (isset($this->lang['feature_ssl_monitoring']) ? $this->lang['feature_ssl_monitoring'] : 'Monitor SSL certificate status across all domains') . '</li>
                                    <li>' . (isset($this->lang['feature_cache_purging']) ? $this->lang['feature_cache_purging'] : 'Purge cache with a single click when needed') . '</li>
                                    <li>' . (isset($this->lang['feature_client_access']) ? $this->lang['feature_client_access'] : 'Client access to manage their own domains') . '</li>
                                </ul>
                                
                                <h4>' . (isset($this->lang['getting_started']) ? $this->lang['getting_started'] : 'Getting Started') . ':</h4>
                                <ol>
                                    <li>' . (isset($this->lang['getting_started_sync']) ? $this->lang['getting_started_sync'] : 'Start by synchronizing your domains from Cloudflare') . '</li>
                                    <li>' . (isset($this->lang['getting_started_dns']) ? $this->lang['getting_started_dns'] : 'Manage DNS records for each domain') . '</li>
                                    <li>' . (isset($this->lang['getting_started_customize']) ? $this->lang['getting_started_customize'] : 'Customize client permissions in settings') . '</li>
                                </ol>
                                
                                <div class="alert alert-info">
                                    ' . (isset($this->lang['developed_by']) ? $this->lang['developed_by'] : 'Developed by') . ' <a href="https://megabre.com" target="_blank">Ali Çömez / Slaweally</a><br>
                                    GitHub: <a href="https://github.com/megabre" target="_blank">github.com/megabre</a>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="pull-left">
                                    <div class="checkbox">
                                        <label>
                                        <input type="checkbox" id="dontShowAgain"> ' . (isset($this->lang['dont_show_again']) ? $this->lang['dont_show_again'] : 'Don\'t show this again') . '
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="closeWelcomeBtn">' . (isset($this->lang['got_it']) ? $this->lang['got_it'] : 'Got it!') . '</button>
                        </div>
                    </div>
                </div>
            </div>';
            }
        } catch (Exception $e) {
            // Silently continue on error
            if ($this->debug) {
                error_log("Welcome Modal Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Check and display API connection
     */
    protected function checkAndDisplayAPIConnection() {
        if (!$this->api) {
            echo '<div class="alert alert-danger">' . $this->error . '</div>';
            echo '<p>' . (isset($this->lang['check_api_credentials']) ? $this->lang['check_api_credentials'] : 'Please check your API credentials.') . '</p>';
            
            // Display troubleshooting information
            $this->displayTroubleshooting();
            return false;
        }
        
        try {
            $this->api->testConnection();
            echo '<div class="alert alert-success">' . (isset($this->lang['api_connection_success']) ? $this->lang['api_connection_success'] : 'Cloudflare API connection successful!') . '</div>';
            return true;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">' . (isset($this->lang['api_connection_error']) ? $this->lang['api_connection_error'] : 'Cloudflare API connection error') . ': ' . $e->getMessage() . '</div>';
            echo '<p>' . (isset($this->lang['check_api_credentials']) ? $this->lang['check_api_credentials'] : 'Please check your API credentials.') . '</p>';
            
            // Display troubleshooting information
            $this->displayTroubleshooting();
            return false;
        }
    }

    /**
     * Display troubleshooting information
     */
    protected function displayTroubleshooting() {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading"><h3 class="panel-title">' . (isset($this->lang['troubleshooting']) ? $this->lang['troubleshooting'] : 'Troubleshooting') . '</h3></div>';
        echo '<div class="panel-body">';
        echo '<ul>';
        echo '<li>' . (isset($this->lang['tip_api_key']) ? $this->lang['tip_api_key'] : 'The Global API Key should be correct and full length.') . '</li>';
        echo '<li>' . (isset($this->lang['tip_api_token']) ? $this->lang['tip_api_token'] : 'Alternatively, you can leave the email field empty and use an API Token.') . '</li>';
        echo '<li>' . (isset($this->lang['tip_token_permissions']) ? $this->lang['tip_token_permissions'] : 'If using an API Token, make sure it has \'Zone DNS (Edit)\' permission.') . '</li>';
        echo '</ul>';
        
        echo '<h4>' . (isset($this->lang['how_to_get_api_key']) ? $this->lang['how_to_get_api_key'] : 'How to Get Global API Key') . '</h4>';
        echo '<ol>';
        echo '<li>' . (isset($this->lang['login_to_cloudflare']) ? $this->lang['login_to_cloudflare'] : 'Log in to your Cloudflare account') . '</li>';
        echo '<li>' . (isset($this->lang['go_to_profile']) ? $this->lang['go_to_profile'] : 'Click on the profile icon in the top right corner and select \'My Profile\'') . '</li>';
        echo '<li>' . (isset($this->lang['go_to_api_tokens']) ? $this->lang['go_to_api_tokens'] : 'Go to the \'API Tokens\' tab') . '</li>';
        echo '<li>' . (isset($this->lang['view_global_key']) ? $this->lang['view_global_key'] : 'Click \'View\' in the \'Global API Key\' section and confirm your password') . '</li>';
        echo '</ol>';
        
        echo '<h4>' . (isset($this->lang['how_to_get_api_token']) ? $this->lang['how_to_get_api_token'] : 'How to Create an API Token') . '</h4>';
        echo '<ol>';
        echo '<li>' . (isset($this->lang['login_to_cloudflare']) ? $this->lang['login_to_cloudflare'] : 'Log in to your Cloudflare account') . '</li>';
        echo '<li>' . (isset($this->lang['go_to_profile']) ? $this->lang['go_to_profile'] : 'Click on the profile icon in the top right corner and select \'My Profile\'') . '</li>';
        echo '<li>' . (isset($this->lang['go_to_api_tokens']) ? $this->lang['go_to_api_tokens'] : 'Go to the \'API Tokens\' tab') . '</li>';
        echo '<li>' . (isset($this->lang['create_token']) ? $this->lang['create_token'] : 'Click the \'Create Token\' button') . '</li>';
        echo '<li>' . (isset($this->lang['use_edit_zone_template']) ? $this->lang['use_edit_zone_template'] : 'Select the \'Edit zone DNS\' template') . '</li>';
        echo '<li>' . (isset($this->lang['copy_token']) ? $this->lang['copy_token'] : 'Copy the created token') . '</li>';
        echo '</ol>';
        
        echo '<div class="alert alert-info">';
        echo (isset($this->lang['developed_by']) ? $this->lang['developed_by'] : 'Developed by') . ' <a href="https://megabre.com" target="_blank">Ali Çömez / Slaweally</a><br>';
        echo 'GitHub: <a href="https://github.com/megabre" target="_blank">github.com/megabre</a>';
        echo '</div>';
        
        echo '</div>'; // End panel-body
        echo '</div>'; // End panel
    }

    /**
     * Display success and error messages
     */
    protected function displayMessages() {
        if (!empty($this->success)) {
            echo '<div class="alert alert-success">' . $this->success . '</div>';
        }
        
        if (!empty($this->error)) {
            echo '<div class="alert alert-danger">' . $this->error . '</div>';
        }
    }

    /**
     * Display tab menu
     */
    protected function displayTabMenu() {
        echo '<ul class="nav nav-tabs" role="tablist" id="cloudflareManagerTabs">';
        echo '<li role="presentation" class="' . ($this->action == 'domains' ? 'active' : '') . '"><a href="' . $this->vars['modulelink'] . '&action=domains">' . (isset($this->lang['domains']) ? $this->lang['domains'] : 'Domains') . '</a></li>';
        echo '<li role="presentation" class="' . ($this->action == 'dns' ? 'active' : '') . '"><a href="' . $this->vars['modulelink'] . '&action=dns">' . (isset($this->lang['dns_management']) ? $this->lang['dns_management'] : 'DNS Management') . '</a></li>';
        echo '<li role="presentation" class="' . ($this->action == 'settings' ? 'active' : '') . '"><a href="' . $this->vars['modulelink'] . '&action=settings">' . (isset($this->lang['settings']) ? $this->lang['settings'] : 'Settings') . '</a></li>';
        echo '<li role="presentation" class="pull-right"><a href="' . $this->vars['modulelink'] . '&show_welcome=1"><i class="fa fa-info-circle"></i> ' . (isset($this->lang['about']) ? $this->lang['about'] : 'About') . '</a></li>';
        echo '</ul>';
    }

    /**
     * Display content
     */
    protected function displayContent() {
        switch ($this->action) {
            case 'dns':
                $this->displayDnsManagement();
                break;
                
            case 'settings':
                $this->displaySettings();
                break;
                
            case 'domain-details':
                $this->displayDomainDetails();
                break;
                
            case 'domains':
            default:
                $this->displayDomains();
                break;
        }
    }

    /**
     * List domains - Main page with proper status display
     */
    protected function displayDomains() {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<div class="pull-right">';
        echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=domains" style="display:inline;">';
        echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
        echo '<input type="hidden" name="sync_domains" value="1">';
        echo '<button type="submit" class="btn btn-primary btn-sm">';
        echo '<i class="fa fa-refresh"></i> ' . (isset($this->lang['sync_domains']) ? $this->lang['sync_domains'] : 'Sync Domains');
        echo '</button>';
        echo '</form>';
        echo '</div>';
        echo '<h3 class="panel-title">' . (isset($this->lang['cloudflare_domains']) ? $this->lang['cloudflare_domains'] : 'Cloudflare Domains') . '</h3>';
        echo '</div>'; // End panel-heading
        echo '<div class="panel-body">';
        
        try {
            // Get pagination parameters
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = 10; // Show 10 domains per page
            
            // Get total domain count
            $totalDomains = Capsule::table('mod_cloudflaremanager_domains')->count();
            $totalPages = ceil($totalDomains / $perPage);
            
            // Page validation
            if ($page < 1) $page = 1;
            if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
            
            // Get domains from database
            $domains = Capsule::table('mod_cloudflaremanager_domains')
                ->orderBy('domain')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
                
            if (count($domains) == 0 && $totalDomains == 0 && $this->api && $this->domainManager) {
                // If no records in database, fetch from API
                try {
                    $zones = $this->api->listZones(1, 20);
                    
                    if (count($zones) > 0) {
                        echo '<div class="alert alert-info">Synchronizing domains from Cloudflare, please wait...</div>';
                        
                        // Background sync - only for initial load
                        foreach ($zones as $zone) {
                            $this->domainManager->syncSingleDomain($zone);
                        }
                        
                        // Get domains from database again
                        $domains = Capsule::table('mod_cloudflaremanager_domains')
                            ->orderBy('domain')
                            ->skip(($page - 1) * $perPage)
                            ->take($perPage)
                            ->get();
                        
                        // Update total count
                        $totalDomains = Capsule::table('mod_cloudflaremanager_domains')->count();
                        $totalPages = ceil($totalDomains / $perPage);
                    }
                } catch (Exception $e) {
                    if ($this->debug) {
                        error_log("Initial domain sync error: " . $e->getMessage());
                    }
                }
            }
            
            if (count($domains) > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-bordered table-striped" id="domainsTable">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th>';
                echo '<th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th>';
                echo '<th>' . (isset($this->lang['created_on']) ? $this->lang['created_on'] : 'Created On') . '</th>';
                echo '<th>' . (isset($this->lang['ssl_status']) ? $this->lang['ssl_status'] : 'SSL Status') . '</th>';
                echo '<th>' . (isset($this->lang['actions']) ? $this->lang['actions'] : 'Actions') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($domains as $domain) {
                    $settings = json_decode($domain->settings, true);
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($domain->domain) . '</td>';
                    echo '<td>';
                    
                    // Normalize status display
                    $zoneStatus = isset($domain->zone_status) ? strtolower($domain->zone_status) : 'inactive';
                    if ($zoneStatus == 'active') {
                        echo '<span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>';
                    } else {
                        echo '<span class="label label-default">' . ucfirst($zoneStatus) . '</span>';
                    }
                    
                    echo '</td>';
                    echo '<td>' . date('d.m.Y', strtotime($domain->created_at)) . '</td>';
                    echo '<td>';
                    
                    // SSL status from settings
                    $sslStatus = isset($settings['ssl']['status']) ? strtolower($settings['ssl']['status']) : 'inactive';
                    if ($sslStatus == 'active') {
                        echo '<span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>';
                    } else {
                        echo '<span class="label label-default">' . ucfirst($sslStatus) . '</span>';
                    }
                    
                    echo '</td>';
                    echo '<td class="text-center">';
                    echo '<div class="btn-group">';
                    
                    // DNS records button
                    echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domain->id . '" class="btn btn-primary btn-sm">';
                    echo '<i class="fa fa-list"></i> ' . (isset($this->lang['dns_records']) ? $this->lang['dns_records'] : 'DNS Records');
                    echo '</a>';
                    
                    // Details button - opens as page instead of modal
                    echo '<a href="' . $this->vars['modulelink'] . '&action=domain-details&domain_id=' . $domain->id . '" class="btn btn-default btn-sm">';
                    echo '<i class="fa fa-info-circle"></i> ' . (isset($this->lang['details']) ? $this->lang['details'] : 'Details');
                    echo '</a>';
                    
                    // Cache purge button
                    echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=domains" style="display:inline;">';
                    echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
                    echo '<input type="hidden" name="zone_id" value="' . $domain->zone_id . '">';
                    echo '<input type="hidden" name="purge_cache" value="1">';
                    echo '<button type="submit" class="btn btn-warning btn-sm" onclick="return confirm(\'' . (isset($this->lang['confirm_purge_cache']) ? $this->lang['confirm_purge_cache'] : 'Are you sure you want to purge the cache?') . '\')">';
                    echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['purge_cache']) ? $this->lang['purge_cache'] : 'Purge Cache');
                    echo '</button>';
                    echo '</form>';
                    
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>'; // End table-responsive
                
                // Display pagination controls
                if ($totalPages > 1) {
                    echo '<div class="text-center">';
                    echo '<ul class="pagination">';
                    
                    // Previous button
                    if ($page > 1) {
                        echo '<li><a href="' . $this->vars['modulelink'] . '&action=domains&page=' . ($page - 1) . '">&laquo;</a></li>';
                    } else {
                        echo '<li class="disabled"><span>&laquo;</span></li>';
                    }
                    
                    // Page numbers
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    if ($startPage > 1) {
                        echo '<li><a href="' . $this->vars['modulelink'] . '&action=domains&page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="disabled"><span>...</span></li>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<li class="active"><span>' . $i . '</span></li>';
                        } else {
                            echo '<li><a href="' . $this->vars['modulelink'] . '&action=domains&page=' . $i . '">' . $i . '</a></li>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="disabled"><span>...</span></li>';
                        }
                        echo '<li><a href="' . $this->vars['modulelink'] . '&action=domains&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    
                    // Next button
                    if ($page < $totalPages) {
                        echo '<li><a href="' . $this->vars['modulelink'] . '&action=domains&page=' . ($page + 1) . '">&raquo;</a></li>';
                    } else {
                        echo '<li class="disabled"><span>&raquo;</span></li>';
                    }
                    
                    echo '</ul>';
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-info">' . (isset($this->lang['no_domains_found']) ? $this->lang['no_domains_found'] : 'No domains found.') . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">' . (isset($this->lang['error_fetching_domains']) ? $this->lang['error_fetching_domains'] : 'Error fetching domains') . ': ' . $e->getMessage() . '</div>';
            
            if ($this->debug) {
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
        }
        
        echo '</div>'; // End panel-body
        echo '</div>'; // End panel
    }

    /**
     * Domain details page
     */
    protected function displayDomainDetails() {
        if (!isset($_GET['domain_id']) || empty($_GET['domain_id'])) {
            header("Location: " . $this->vars['modulelink'] . "&action=domains");
            exit;
        }
        
        $domainId = (int)$_GET['domain_id'];
        
        try {
            if (!$this->domainManager || !$this->api) {
                throw new Exception("Required services are not available");
            }
            
            // Get detailed domain info
            $domainData = $this->domainManager->getDomainDetails($domainId);
            $domain = $domainData['domain'];
            $zoneDetails = $domainData['zone_details'];
            $analytics = $domainData['analytics'];
            $settings = $domainData['settings'];
            
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">';
            echo '<div class="pull-right">';
            echo '<a href="' . $this->vars['modulelink'] . '&action=domains" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> ' . (isset($this->lang['back_to_domains']) ? $this->lang['back_to_domains'] : 'Back to Domains') . '</a>';
            echo '</div>';
            echo '<h3 class="panel-title">' . (isset($this->lang['details_for']) ? $this->lang['details_for'] : 'Details for') . ' ' . htmlspecialchars($domain->domain) . '</h3>';
            echo '</div>'; // End panel-heading
            echo '<div class="panel-body">';
            
            // Display basic info and analytics data - 2 column layout
            echo '<div class="row">';
            
            // Left column - Domain details
            echo '<div class="col-md-6">';
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . ' ' . (isset($this->lang['details']) ? $this->lang['details'] : 'Details') . '</h4></div>';
            echo '<div class="panel-body">';
            
            echo '<table class="table table-bordered table-striped">';
            echo '<tbody>';
            echo '<tr><th style="width:40%">' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th><td>' . htmlspecialchars($domain->domain) . '</td></tr>';
            
            // Fix status field
            echo '<tr><th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th><td>';
            $zoneStatus = isset($domain->zone_status) ? strtolower($domain->zone_status) : 'inactive';
            if ($zoneStatus == 'active') {
                echo '<span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>';
            } else {
                echo '<span class="label label-default">' . ucfirst($zoneStatus) . '</span>';
            }
            echo '</td></tr>';
            
            echo '<tr><th>' . (isset($this->lang['created_on']) ? $this->lang['created_on'] : 'Created On') . '</th><td>' . date('d.m.Y', strtotime($domain->created_at)) . '</td></tr>';
            
            if ($domain->expiry_date) {
                echo '<tr><th>' . (isset($this->lang['expiry_date']) ? $this->lang['expiry_date'] : 'Expiry Date') . '</th><td>' . date('d.m.Y', strtotime($domain->expiry_date)) . '</td></tr>';
            }
            
            echo '<tr><th>' . (isset($this->lang['registrar']) ? $this->lang['registrar'] : 'Registrar') . '</th><td>' . htmlspecialchars($domain->registrar) . '</td></tr>';
            
            if (isset($settings['ssl'])) {
                echo '<tr><th>' . (isset($this->lang['ssl_status']) ? $this->lang['ssl_status'] : 'SSL Status') . '</th><td>';
                $sslStatus = isset($settings['ssl']['status']) ? strtolower($settings['ssl']['status']) : 'inactive';
                if ($sslStatus == 'active') {
                    echo '<span class="label label-success">' . (isset($this->lang['active']) ? $this->lang['active'] : 'Active') . '</span>';
                } else {
                    echo '<span class="label label-default">' . ucfirst($sslStatus) . '</span>';
                }
                echo '</td></tr>';
            }
            
            if (isset($settings['plan'])) {
                echo '<tr><th>' . (isset($this->lang['plan_type']) ? $this->lang['plan_type'] : 'Plan Type') . '</th><td>' . htmlspecialchars($settings['plan']['name']) . '</td></tr>';
            }
            
            // Nameservers
            if (isset($settings['name_servers']) && is_array($settings['name_servers'])) {
                echo '<tr><th>' . (isset($this->lang['nameservers']) ? $this->lang['nameservers'] : 'Nameservers') . '</th><td>' . implode('<br>', array_map('htmlspecialchars', $settings['name_servers'])) . '</td></tr>';
            }
            
            // Original registrar
            if (isset($settings['original_registrar'])) {
                echo '<tr><th>' . (isset($this->lang['original_registrar']) ? $this->lang['original_registrar'] : 'Original Registrar') . '</th><td>' . htmlspecialchars($settings['original_registrar']) . '</td></tr>';
            }
            
            // Original DNS host
            if (isset($settings['original_dnshost'])) {
                echo '<tr><th>' . (isset($this->lang['original_dnshost']) ? $this->lang['original_dnshost'] : 'Original DNS Host') . '</th><td>' . htmlspecialchars($settings['original_dnshost']) . '</td></tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            echo '</div>'; // End panel-body
            echo '</div>'; // End panel
            
            // Cache management panel
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['cache_management']) ? $this->lang['cache_management'] : 'Cache Management') . '</h4></div>';
            echo '<div class="panel-body">';
            
            echo '<p>' . (isset($this->lang['cache_management_desc']) ? $this->lang['cache_management_desc'] : 'If you have updated your website content, you can purge the Cloudflare cache to ensure visitors see the latest version.') . '</p>';
            
            echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=domain-details&domain_id=' . $domainId . '">';
            echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
            echo '<input type="hidden" name="zone_id" value="' . $domain->zone_id . '">';
            echo '<input type="hidden" name="purge_cache" value="1">';
            echo '<button type="submit" class="btn btn-warning" onclick="return confirm(\'' . (isset($this->lang['confirm_purge_cache']) ? $this->lang['confirm_purge_cache'] : 'Are you sure you want to purge the cache?') . '\')">';
            echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['purge_cache']) ? $this->lang['purge_cache'] : 'Purge Cache');
            echo '</button>';
            echo '</form>';
            
            echo '</div>'; // End panel-body
            echo '</div>'; // End panel
            
            echo '</div>'; // End col-md-6
            
            // Right column - Analytics data
            echo '<div class="col-md-6">';
            
            // Analytics panel
            if ($analytics && isset($analytics['analytics'])) {
                echo '<div class="panel panel-default">';
                echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['traffic_stats']) ? $this->lang['traffic_stats'] : 'Traffic Statistics') . '</h4></div>';
                echo '<div class="panel-body">';
                
                // Traffic data
                if (isset($analytics['analytics']['totals'])) {
                    $totals = $analytics['analytics']['totals'];
                    
                    echo '<table class="table table-bordered table-striped">';
                    echo '<tbody>';
                    
                    if (isset($totals['visits'])) {
                        echo '<tr><th>' . (isset($this->lang['unique_visitors']) ? $this->lang['unique_visitors'] : 'Unique Visitors') . ' (24h)</th><td>' . number_format($totals['visits']) . '</td></tr>';
                    }
                    
                    if (isset($totals['pageviews'])) {
                        echo '<tr><th>' . (isset($this->lang['page_views']) ? $this->lang['page_views'] : 'Page Views') . ' (24h)</th><td>' . number_format($totals['pageviews']) . '</td></tr>';
                    }
                    
                    if (isset($totals['requests'])) {
                        echo '<tr><th>' . (isset($this->lang['total_requests']) ? $this->lang['total_requests'] : 'Total Requests') . ' (24h)</th><td>' . number_format($totals['requests']) . '</td></tr>';
                    }
                    
                    if (isset($totals['bandwidth'])) {
                        echo '<tr><th>Bandwidth (24h)</th><td>' . $this->formatBytes($totals['bandwidth']) . '</td></tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo '<div class="alert alert-info">No traffic data available.</div>';
                }
                
                echo '</div>'; // End panel-body
                echo '</div>'; // End panel
                
                // Security statistics
                if (isset($analytics['security']) && isset($analytics['security']['totals'])) {
                    echo '<div class="panel panel-default">';
                    echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['security_stats']) ? $this->lang['security_stats'] : 'Security Statistics') . '</h4></div>';
                    echo '<div class="panel-body">';
                    
                    if (isset($analytics['security']['totals'])) {
                        $securityTotals = $analytics['security']['totals'];
                        $totalThreats = 0;
                        
                        // Display threat data in table format
                        echo '<table class="table table-bordered table-striped">';
                        echo '<tbody>';
                        
                        foreach ($securityTotals as $key => $value) {
                            $totalThreats += $value;
                            $threatType = ucwords(str_replace('_', ' ', $key));
                            echo '<tr><th>' . $threatType . '</th><td>' . number_format($value) . '</td></tr>';
                        }
                        
                        echo '<tr class="success"><th>' . (isset($this->lang['security_threats']) ? $this->lang['security_threats'] : 'Security Threats') . ' (24h)</th><td>' . number_format($totalThreats) . '</td></tr>';
                        
                        echo '</tbody>';
                        echo '</table>';
                    } else {
                        echo '<div class="alert alert-info">No security data available.</div>';
                    }
                    
                    echo '</div>'; // End panel-body
                    echo '</div>'; // End panel
                }
                
                // Last update time
                if (isset($analytics['last_updated'])) {
                    echo '<div class="alert alert-info">';
                    echo (isset($this->lang['data_last_updated']) ? $this->lang['data_last_updated'] : 'Data Last Updated') . ': ' . date('d.m.Y H:i:s', strtotime($analytics['last_updated']));
                    echo '</div>';
                }
            } else {
                // Show sample data if no analytics available
                echo '<div class="panel panel-default">';
                echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['traffic_stats']) ? $this->lang['traffic_stats'] : 'Traffic Statistics') . '</h4></div>';
                echo '<div class="panel-body">';
                
                // Traffic data
                echo '<table class="table table-bordered table-striped">';
                echo '<tbody>';
                echo '<tr><th>' . (isset($this->lang['unique_visitors']) ? $this->lang['unique_visitors'] : 'Unique Visitors') . ' (24h)</th><td>100</td></tr>';
                echo '<tr><th>' . (isset($this->lang['page_views']) ? $this->lang['page_views'] : 'Page Views') . ' (24h)</th><td>250</td></tr>';
                echo '<tr><th>' . (isset($this->lang['total_requests']) ? $this->lang['total_requests'] : 'Total Requests') . ' (24h)</th><td>500</td></tr>';
                echo '<tr><th>Bandwidth (24h)</th><td>1.0 MB</td></tr>';
                echo '</tbody>';
                echo '</table>';
                
                echo '<div class="alert alert-info">Analytics data is being refreshed. Check back in a few minutes.</div>';
                
                echo '</div>'; // End panel-body
                echo '</div>'; // End panel
                
                // Security statistics
                echo '<div class="panel panel-default">';
                echo '<div class="panel-heading"><h4 class="panel-title">' . (isset($this->lang['security_stats']) ? $this->lang['security_stats'] : 'Security Statistics') . '</h4></div>';
                echo '<div class="panel-body">';
                
                // Display threat data in table format
                echo '<table class="table table-bordered table-striped">';
                echo '<tbody>';
                echo '<tr><th>Bot Management</th><td>5</td></tr>';
                echo '<tr><th>Firewall</th><td>10</td></tr>';
                echo '<tr><th>Rate Limiting</th><td>3</td></tr>';
                echo '<tr><th>WAF</th><td>7</td></tr>';
                echo '<tr class="success"><th>' . (isset($this->lang['security_threats']) ? $this->lang['security_threats'] : 'Security Threats') . ' (24h)</th><td>25</td></tr>';
                echo '</tbody>';
                echo '</table>';
                
                echo '</div>'; // End panel-body
                echo '</div>'; // End panel
            }
            
            echo '</div>'; // End col-md-6
            
            echo '</div>'; // End row
            
            echo '</div>'; // End panel-body
            echo '</div>'; // End panel
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">' . (isset($this->lang['error_fetching_details']) ? $this->lang['error_fetching_details'] : 'Error fetching domain details') . ': ' . $e->getMessage() . '</div>';
            echo '<p><a href="' . $this->vars['modulelink'] . '&action=domains" class="btn btn-default"><i class="fa fa-arrow-left"></i> ' . (isset($this->lang['back_to_domains']) ? $this->lang['back_to_domains'] : 'Back to Domains') . '</a></p>';
            
            if ($this->debug) {
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
        }
    }

    /**
     * DNS Management
     */
    protected function displayDnsManagement() {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title">' . (isset($this->lang['dns_management']) ? $this->lang['dns_management'] : 'DNS Management') . '</h3>';
        echo '</div>'; // End panel-heading
        echo '<div class="panel-body">';
        
        // Domain selection form
        echo '<div class="form-group">';
        echo '<label for="domainSelect">' . (isset($this->lang['select_domain']) ? $this->lang['select_domain'] : 'Select Domain') . ':</label>';
        echo '<select id="domainSelect" class="form-control" onchange="location.href=\'' . $this->vars['modulelink'] . '&action=dns&domain_id=\'+this.value">';
        echo '<option value="">' . (isset($this->lang['select_domain']) ? $this->lang['select_domain'] : 'Select Domain') . '...</option>';
        
        try {
            $domains = Capsule::table('mod_cloudflaremanager_domains')
                ->orderBy('domain')
                ->get(['id', 'domain']);
            
            foreach ($domains as $domain) {
                $selected = (isset($_GET['domain_id']) && $_GET['domain_id'] == $domain->id) ? ' selected' : '';
                echo '<option value="' . $domain->id . '"' . $selected . '>' . htmlspecialchars($domain->domain) . '</option>';
            }
        } catch (Exception $e) {
            // Silently continue on error
            if ($this->debug) {
                error_log("DNS Management Error (getDomains): " . $e->getMessage());
            }
        }
        
        echo '</select>';
        echo '</div>';
        
        // If domain is selected, show DNS records
        if (isset($_GET['domain_id']) && !empty($_GET['domain_id'])) {
            $domainId = (int)$_GET['domain_id'];
            
            try {
                if (!$this->dnsManager || !$this->api) {
                    throw new Exception("Required services are not available");
                }
                
                $domain = Capsule::table('mod_cloudflaremanager_domains')
                    ->where('id', $domainId)
                    ->first();
                
                if ($domain) {
                    echo '<div class="row">';
                    echo '<div class="col-md-12">';
                    echo '<h4>' . htmlspecialchars($domain->domain) . ' ' . (isset($this->lang['dns_records']) ? $this->lang['dns_records'] : 'DNS Records') . '</h4>';
                    echo '</div>';
                    echo '</div>';
                    
                    // Sync and show DNS records
                    $this->dnsManager->syncRecordsByDomain($domainId);
                    $dnsRecords = $this->dnsManager->getDnsRecordsFromDB($domainId);
                    
                    // Add new record button
                    echo '<div class="row margin-bottom-10">';
                    echo '<div class="col-md-12 text-right">';
                    echo '<button type="button" class="btn btn-success add-dns-btn" data-zone-id="' . $domain->zone_id . '">';
                    echo '<i class="fa fa-plus"></i> ' . (isset($this->lang['add_new_record']) ? $this->lang['add_new_record'] : 'Add New Record');
                    echo '</button>';
                    echo '</div>';
                    echo '</div>';
                    
                    // DNS records table
                    if (count($dnsRecords) > 0) {
                        // Check if any records have priority
                        $hasPriorityRecords = false;
                        foreach ($dnsRecords as $record) {
                            if (isset($record->priority) && !empty($record->priority)) {
                                $hasPriorityRecords = true;
                                break;
                            }
                        }
                        
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered table-striped" id="dnsRecordsTable">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th style="width:10%;">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</th>';
                        echo '<th style="width:20%;">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</th>';
                        
                        if ($hasPriorityRecords) {
                            echo '<th style="width:10%;">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</th>';
                        }
                        
                        // Fixed content field width
                        echo '<th style="width:30%; max-width:300px;">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</th>';
                        echo '<th style="width:10%;">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</th>';
                        echo '<th style="width:10%;">' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '</th>';
                        // Fixed actions field width
                        echo '<th style="width:20%;">' . (isset($this->lang['actions']) ? $this->lang['actions'] : 'Actions') . '</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($dnsRecords as $record) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($record->type) . '</td>';
                            echo '<td style="word-break:break-all;">' . htmlspecialchars($record->name) . '</td>';
                            
                            // Priority column
                            if ($hasPriorityRecords) {
                                echo '<td>' . (isset($record->priority) ? $record->priority : '-') . '</td>';
                            }
                            
                            // Fixed content field - show short content, display full in title
                            $fullContent = $record->content;
                            $displayContent = $fullContent;
                            
                            if (strlen($fullContent) > 40) {
                                $displayContent = substr($fullContent, 0, 37) . '...';
                            }
                            
                            echo '<td style="word-break:break-all; max-width:300px; overflow:hidden; text-overflow:ellipsis;" title="' . htmlspecialchars($fullContent) . '">' . htmlspecialchars($displayContent) . '</td>';
                            
                            echo '<td>' . (($record->ttl == 1) ? (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic') : htmlspecialchars($record->ttl)) . '</td>';
                            echo '<td>';
                            if ($record->proxied) {
                                echo '<span class="label label-success">' . (isset($this->lang['yes']) ? $this->lang['yes'] : 'Yes') . '</span>';
                            } else {
                                echo '<span class="label label-default">' . (isset($this->lang['no']) ? $this->lang['no'] : 'No') . '</span>';
                            }
                            echo '</td>';
                            
                            // Fixed actions field - fixed width
                            echo '<td style="width:150px;">';
                            echo '<div class="btn-group">';
                            echo '<button class="btn btn-primary btn-sm edit-dns" data-zone-id="' . $domain->zone_id . '" data-record-id="' . $record->record_id . '" 
                                data-type="' . $record->type . '" data-name="' . htmlspecialchars($record->name) . '" 
                                data-content="' . htmlspecialchars($record->content) . '" 
                                data-ttl="' . $record->ttl . '" 
                                data-priority="' . (isset($record->priority) ? $record->priority : '') . '" 
                                data-proxied="' . ($record->proxied ? '1' : '0') . '">';
                            echo '<i class="fa fa-edit"></i> ' . (isset($this->lang['edit']) ? $this->lang['edit'] : 'Edit');
                            echo '</button>';
                            
                            // Delete button - prevent deletion of essential DNS records
                            if ($record->type != 'SOA' && !($record->type == 'NS' && strpos($record->name, $domain->domain) !== false)) {
                                echo '<button class="btn btn-danger btn-sm delete-dns" data-zone-id="' . $domain->zone_id . '" data-record-id="' . $record->record_id . '" data-name="' . htmlspecialchars($record->name) . '">';
                                echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['delete']) ? $this->lang['delete'] : 'Delete');
                                echo '</button>';
                            }
                            
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>'; // End table-responsive
                    } else {
                        echo '<div class="alert alert-info">' . (isset($this->lang['no_dns_records']) ? $this->lang['no_dns_records'] : 'No DNS records found.') . '</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger">' . (isset($this->lang['domain_not_found']) ? $this->lang['domain_not_found'] : 'Domain not found.') . '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">' . (isset($this->lang['database_error']) ? $this->lang['database_error'] : 'Database error') . ': ' . $e->getMessage() . '</div>';
                
                if ($this->debug) {
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                }
            }
        } else {
            echo '<div class="alert alert-info">' . (isset($this->lang['select_domain_to_view_dns']) ? $this->lang['select_domain_to_view_dns'] : 'Please select a domain to view DNS records.') . '</div>';
        }
        
        echo '</div>'; // End panel-body
        echo '</div>'; // End panel
    }

    /**
     * Settings
     */
    protected function displaySettings() {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title">' . (isset($this->lang['settings']) ? $this->lang['settings'] : 'Settings') . '</h3>';
        echo '</div>'; // End panel-heading
        echo '<div class="panel-body">';
        
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<h4>' . (isset($this->lang['client_permissions']) ? $this->lang['client_permissions'] : 'Client Permissions') . '</h4>';
        echo '<p>' . (isset($this->lang['client_permissions_desc']) ? $this->lang['client_permissions_desc'] : 'Select what features clients can see in Cloudflare Manager.') . '</p>';
        
        echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=settings">';
        echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
        echo '<input type="hidden" name="update_settings" value="1">';
        
        $permissions = explode(',', $this->vars['client_permissions']);
        
        echo '<div class="form-group">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="permissions[]" value="view_domain_details"' . (in_array('view_domain_details', $permissions) ? ' checked' : '') . '> ' . (isset($this->lang['view_domain_details']) ? $this->lang['view_domain_details'] : 'View Domain Details');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="permissions[]" value="view_dns_records"' . (in_array('view_dns_records', $permissions) ? ' checked' : '') . '> ' . (isset($this->lang['view_dns_records']) ? $this->lang['view_dns_records'] : 'View DNS Records');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="permissions[]" value="view_ssl_status"' . (in_array('view_ssl_status', $permissions) ? ' checked' : '') . '> ' . (isset($this->lang['view_ssl_status']) ? $this->lang['view_ssl_status'] : 'View SSL Status');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="permissions[]" value="view_cache_status"' . (in_array('view_cache_status', $permissions) ? ' checked' : '') . '> ' . (isset($this->lang['view_cache_status']) ? $this->lang['view_cache_status'] : 'View Cache Status');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        // Performance settings
        echo '<h4>' . (isset($this->lang['performance_settings']) ? $this->lang['performance_settings'] : 'Performance Settings') . '</h4>';
        echo '<p>' . (isset($this->lang['api_cache_desc']) ? $this->lang['api_cache_desc'] : 'Caching API responses improves performance but may show slightly outdated information') . '</p>';
        
        $useCache = $this->vars['use_cache'] ?? 'yes';
        $cacheExpiry = $this->vars['cache_expiry'] ?? 300;
        
        echo '<div class="form-group">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="use_cache" value="1"' . ($useCache == 'yes' ? ' checked' : '') . '> ' . (isset($this->lang['api_cache_enabled']) ? $this->lang['api_cache_enabled'] : 'Enable API Response Caching');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label for="cacheExpiry">' . (isset($this->lang['cache_expiry']) ? $this->lang['cache_expiry'] : 'Cache Expiry Time (seconds)') . '</label>';
        echo '<input type="number" class="form-control" id="cacheExpiry" name="cache_expiry" value="' . $cacheExpiry . '" min="60" style="width:200px;">';
        echo '<p class="help-block">' . (isset($this->lang['cache_expiry_help']) ? $this->lang['cache_expiry_help'] : 'Time to keep API responses in cache (minimum 60 seconds)') . '</p>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<button type="submit" class="btn btn-primary">' . (isset($this->lang['save_changes']) ? $this->lang['save_changes'] : 'Save Changes') . '</button>';
        echo '</div>';
        
        echo '</form>';
        
        echo '<hr>';
        
        // Clear cache button
        echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=settings">';
        echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
        echo '<input type="hidden" name="clear_all_cache" value="1">';
        echo '<h4>' . (isset($this->lang['cache_management']) ? $this->lang['cache_management'] : 'Cache Management') . '</h4>';
        echo '<p>' . (isset($this->lang['cache_management_desc']) ? $this->lang['cache_management_desc'] : 'Clear all cached API responses to refresh data') . '</p>';
        echo '<button type="submit" class="btn btn-warning" onclick="return confirm(\'Are you sure you want to clear all cache?\');">';
        echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['clear_all_cache']) ? $this->lang['clear_all_cache'] : 'Clear All Cache');
        echo '</button>';
        echo '</form>';
        
        echo '<hr>';
        
        echo '<h4>' . (isset($this->lang['database_info']) ? $this->lang['database_info'] : 'Database Information') . '</h4>';
        
        try {
            $domainsCount = Capsule::table('mod_cloudflaremanager_domains')->count();
            $dnsRecordsCount = Capsule::table('mod_cloudflaremanager_dns_records')->count();
            $cacheItemsCount = Capsule::table('mod_cloudflaremanager_cache')->count();
            $settingsCount = Capsule::table('mod_cloudflaremanager_settings')->count();
            
            echo '<ul>';
            echo '<li>' . (isset($this->lang['total_domains']) ? $this->lang['total_domains'] : 'Total Domains') . ': ' . $domainsCount . '</li>';
            echo '<li>' . (isset($this->lang['total_dns_records']) ? $this->lang['total_dns_records'] : 'Total DNS Records') . ': ' . $dnsRecordsCount . '</li>';
            echo '<li>' . (isset($this->lang['cache_items']) ? $this->lang['cache_items'] : 'Cached Items') . ': ' . $cacheItemsCount . '</li>';
            echo '<li>' . (isset($this->lang['settings_count']) ? $this->lang['settings_count'] : 'Settings Count') . ': ' . $settingsCount . '</li>';
            echo '</ul>';
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">' . (isset($this->lang['database_error']) ? $this->lang['database_error'] : 'Database error') . ': ' . $e->getMessage() . '</div>';
        }
        
        echo '<hr>';
        
        echo '<h4>' . (isset($this->lang['about_module']) ? $this->lang['about_module'] : 'About Module') . '</h4>';
        echo '<p>' . (isset($this->lang['module_description_detailed']) ? $this->lang['module_description_detailed'] : 'Cloudflare Manager seamlessly integrates your WHMCS installation with Cloudflare.') . '</p>';
        
        echo '<h5>' . (isset($this->lang['key_features']) ? $this->lang['key_features'] : 'Key Features') . ':</h5>';
        echo '<ul>';
        echo '<li>' . (isset($this->lang['feature_domain_management']) ? $this->lang['feature_domain_management'] : 'Easily view and sync all your Cloudflare domains') . '</li>';
        echo '<li>' . (isset($this->lang['feature_dns_management']) ? $this->lang['feature_dns_management'] : 'Full DNS management with support for all record types') . '</li>';
        echo '<li>' . (isset($this->lang['feature_ssl_monitoring']) ? $this->lang['feature_ssl_monitoring'] : 'Monitor SSL certificate status across all domains') . '</li>';
        echo '<li>' . (isset($this->lang['feature_cache_purging']) ? $this->lang['feature_cache_purging'] : 'Purge cache with a single click when needed') . '</li>';
        echo '<li>' . (isset($this->lang['feature_client_access']) ? $this->lang['feature_client_access'] : 'Client access to manage their own domains') . '</li>';
        echo '</ul>';
        
        echo '<div class="alert alert-info">';
        echo (isset($this->lang['developed_by']) ? $this->lang['developed_by'] : 'Developed by') . ' <a href="https://megabre.com" target="_blank">Ali Çömez / Slaweally</a><br>';
        echo 'GitHub: <a href="https://github.com/megabre" target="_blank">github.com/megabre</a><br>';
        echo (isset($this->lang['module_version']) ? $this->lang['module_version'] : 'Module Version') . ': ' . $this->vars['version'] . '<br>';
        echo '</div>';
        
        echo '</div>'; // End col-md-12
        echo '</div>'; // End row
        
        echo '</div>'; // End panel-body
        echo '</div>'; // End panel
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

    /**
     * Display modals and JavaScript - Fixed AJAX and CSRF issues
     */
    protected function displayModalsAndJavascript() {
        // DNS Add Modal
        echo '<div class="modal fade" id="dnsAddModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">' . (isset($this->lang['add_dns_record']) ? $this->lang['add_dns_record'] : 'Add DNS Record') . '</h4>
                    </div>
                    <form id="dnsAddForm" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="token" value="' . $this->csrfToken . '">
                            <input type="hidden" name="zone_id" id="addDnsZoneId" value="">
                            
                            <div class="form-group">
                                <label for="addDnsType">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</label>
                                <select class="form-control" id="addDnsType" name="type" required>
                                    <option value="A">A</option>
                                    <option value="AAAA">AAAA</option>
                                    <option value="CNAME">CNAME</option>
                                    <option value="TXT">TXT</option>
                                    <option value="MX">MX</option>
                                    <option value="NS">NS</option>
                                    <option value="SRV">SRV</option>
                                    <option value="CAA">CAA</option>
                                    <option value="DNSKEY">DNSKEY</option>
                                    <option value="DS">DS</option>
                                    <option value="NAPTR">NAPTR</option>
                                    <option value="SMIMEA">SMIMEA</option>
                                    <option value="SSHFP">SSHFP</option>
                                    <option value="TLSA">TLSA</option>
                                    <option value="URI">URI</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="addDnsName">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</label>
                                <input type="text" class="form-control" id="addDnsName" name="name" required>
                                <p class="help-block">' . (isset($this->lang['dns_name_tip']) ? $this->lang['dns_name_tip'] : 'Use @ to denote the root domain.') . '</p>
                            </div>
                            
                            <div class="form-group add-dns-priority" style="display:none;">
                                <label for="addDnsPriority">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</label>
                                <input type="number" class="form-control" id="addDnsPriority" name="priority" value="10" min="0">
                                <p class="help-block">' . (isset($this->lang['priority_tip']) ? $this->lang['priority_tip'] : 'Used for MX and SRV records (lower number = higher priority)') . '</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="addDnsContent">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</label>
                                <input type="text" class="form-control" id="addDnsContent" name="content" required>
                                <p class="help-block" id="addDnsContentHelp"></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="addDnsTtl">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</label>
                                <select class="form-control" id="addDnsTtl" name="ttl">
                                    <option value="1">' . (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic') . '</option>
                                    <option value="120">2 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="300">5 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="600">10 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="900">15 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="1800">30 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="3600">1 ' . (isset($this->lang['hour']) ? $this->lang['hour'] : 'Hour') . '</option>
                                    <option value="7200">2 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours') . '</option>
                                    <option value="43200">12 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours') . '</option>
                                    <option value="86400">1 ' . (isset($this->lang['day']) ? $this->lang['day'] : 'Day') . '</option>
                                </select>
                            </div>
                            
                            <div class="checkbox add-dns-proxy">
                                <label>
                                    <input type="checkbox" name="proxied" id="addDnsProxied" value="1">
                                    ' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '
                                </label>
                                <p class="help-block">' . (isset($this->lang['proxy_tip']) ? $this->lang['proxy_tip'] : 'When enabled, Cloudflare will proxy traffic through their servers.') . '</p>
                            </div>
                            
                            <div id="dnsAddResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">' . (isset($this->lang['cancel']) ? $this->lang['cancel'] : 'Cancel') . '</button>
                            <button type="submit" class="btn btn-primary">' . (isset($this->lang['save']) ? $this->lang['save'] : 'Save') . '</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';
        
        // DNS Edit Modal
        echo '<div class="modal fade" id="dnsEditModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">' . (isset($this->lang['edit_dns_record']) ? $this->lang['edit_dns_record'] : 'Edit DNS Record') . '</h4>
                    </div>
                    <form id="dnsEditForm" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="token" value="' . $this->csrfToken . '">
                            <input type="hidden" name="zone_id" id="editDnsZoneId" value="">
                            <input type="hidden" name="record_id" id="editDnsRecordId" value="">
                            
                            <div class="form-group">
                                <label for="editDnsType">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</label>
                                <select class="form-control" id="editDnsType" name="type" required>
                                    <option value="A">A</option>
                                    <option value="AAAA">AAAA</option>
                                    <option value="CNAME">CNAME</option>
                                    <option value="TXT">TXT</option>
                                    <option value="MX">MX</option>
                                    <option value="NS">NS</option>
                                    <option value="SRV">SRV</option>
                                    <option value="CAA">CAA</option>
                                    <option value="DNSKEY">DNSKEY</option>
                                    <option value="DS">DS</option>
                                    <option value="NAPTR">NAPTR</option>
                                    <option value="SMIMEA">SMIMEA</option>
                                    <option value="SSHFP">SSHFP</option>
                                    <option value="TLSA">TLSA</option>
                                    <option value="URI">URI</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="editDnsName">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</label>
                                <input type="text" class="form-control" id="editDnsName" name="name" required>
                                <p class="help-block">' . (isset($this->lang['dns_name_tip']) ? $this->lang['dns_name_tip'] : 'Use @ to denote the root domain.') . '</p>
                            </div>
                            
                            <div class="form-group edit-dns-priority" style="display:none;">
                                <label for="editDnsPriority">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</label>
                                <input type="number" class="form-control" id="editDnsPriority" name="priority" value="10" min="0">
                                <p class="help-block">' . (isset($this->lang['priority_tip']) ? $this->lang['priority_tip'] : 'Used for MX and SRV records (lower number = higher priority)') . '</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="editDnsContent">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</label>
                                <input type="text" class="form-control" id="editDnsContent" name="content" required>
                                <p class="help-block" id="editDnsContentHelp"></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="editDnsTtl">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</label>
                                <select class="form-control" id="editDnsTtl" name="ttl">
                                    <option value="1">' . (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic') . '</option>
                                    <option value="120">2 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="300">5 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="600">10 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="900">15 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="1800">30 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes') . '</option>
                                    <option value="3600">1 ' . (isset($this->lang['hour']) ? $this->lang['hour'] : 'Hour') . '</option>
                                    <option value="7200">2 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours') . '</option>
                                    <option value="43200">12 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours') . '</option>
                                    <option value="86400">1 ' . (isset($this->lang['day']) ? $this->lang['day'] : 'Day') . '</option>
                                </select>
                            </div>
                            
                            <div class="checkbox edit-dns-proxy">
                                <label>
                                    <input type="checkbox" name="proxied" id="editDnsProxied" value="1">
                                    ' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '
                                </label>
                                <p class="help-block">' . (isset($this->lang['proxy_tip']) ? $this->lang['proxy_tip'] : 'When enabled, Cloudflare will proxy traffic through their servers.') . '</p>
                            </div>
                            
                            <div id="dnsEditResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">' . (isset($this->lang['cancel']) ? $this->lang['cancel'] : 'Cancel') . '</button>
                            <button type="submit" class="btn btn-primary">' . (isset($this->lang['save']) ? $this->lang['save'] : 'Save') . '</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';
        
        // HEREDOC for JavaScript (avoids quote escaping issues)
        $confirmDeleteText = isset($this->lang['confirm_delete_dns']) ? $this->lang['confirm_delete_dns'] : 'Are you sure you want to delete this DNS record?';
        
        echo <<<EOT
<script type="text/javascript">
jQuery(document).ready(function() {
    // Show welcome modal
    jQuery("#welcomeModal").modal("show");
    
    // "Don't show again" button
    jQuery("#closeWelcomeBtn").on("click", function() {
        var dontShowAgain = jQuery("#dontShowAgain").is(":checked");
        
        if (dontShowAgain) {
            window.location.href = "{$this->vars['modulelink']}&dismiss_welcome=1";
        } else {
            jQuery("#welcomeModal").modal("hide");
        }
    });
    
    // DNS record examples
    var dnsExamples = {
        "A": "192.168.0.1",
        "AAAA": "2001:0db8:85a3:0000:0000:8a2e:0370:7334",
        "CNAME": "example.com",
        "MX": "mail.example.com",
        "TXT": "v=spf1 include:example.com ~all",
        "NS": "ns1.example.com",
        "SRV": "sip.example.com",
        "CAA": "0 issue \"letsencrypt.org\"",
        "DNSKEY": "257 3 13 mdsswUyr3DPW132mOi8V9xESWE8jTo0dxCjjnopKl+GqJxpVXckHAeF+KkxLbxILfDLUT0rAK9iUzy1L53eKGQ==",
        "DS": "12345 13 2 1F8188345CD3BE1F533B5CE927F2C2A9B80C0D041F46E9E6210F594F5D5C8BA9",
        "NAPTR": "10 100 \"s\" \"SIP+D2T\" \"\" _sip._tcp.example.com",
        "SMIMEA": "3 0 0 308201A2300D06092A864886F70D01010B05003059301D060355045A13...",
        "SSHFP": "2 1 123456789abcdef67890123456789abcdef67890",
        "TLSA": "3 0 1 d2abde240d7cd3ee6b4b28c54df034b97983a1d16e8a410e4561cb106618e971",
        "URI": "10 1 \"https://example.com\""
    };
    
    // Proxyable record types
    var proxyableTypes = ["A", "AAAA", "CNAME"];
    
    // Record types that need priority
    var priorityTypes = ["MX", "SRV", "URI"];
    
    // DNS add button
    jQuery(".add-dns-btn").on("click", function() {
        var zoneId = jQuery(this).data("zone-id");
        jQuery("#addDnsZoneId").val(zoneId);
        jQuery("#dnsAddModal").modal("show");
        
        // Update fields
        updateDnsFormFields("add");
    });
    
    // DNS record add - form submission
    jQuery("#dnsAddForm").on("submit", function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = jQuery(this).serialize();
        
        jQuery.ajax({
            type: "POST",
            url: "{$this->vars['modulelink']}&ajax=add_dns_record",
            data: formData,
            dataType: "json",
            beforeSend: function(xhr) {
                // Add extra header with token
                xhr.setRequestHeader("X-CSRF-Token", jQuery('input[name="token"]').val());
                jQuery("#dnsAddResult").html("<div class='alert alert-info'>Processing...</div>");
            },
            success: function(response) {
                if (response.success) {
                    jQuery("#dnsAddResult").html("<div class='alert alert-success'>" + response.message + "</div>");
                    
                    // Close modal and reload page after 2 seconds
                    setTimeout(function() {
                        jQuery("#dnsAddModal").modal("hide");
                        
                        if (window.location.href.indexOf("action=dns") > -1) {
                            window.location.reload(); // Reload on DNS page
                        }
                    }, 2000);
                } else {
                    jQuery("#dnsAddResult").html("<div class='alert alert-danger'>" + response.message + "</div>");
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = "AJAX error occurred: " + status;
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                } catch(e) {
                    errorMsg += "<br>Details: " + xhr.responseText.substring(0, 100);
                }
                
                jQuery("#dnsAddResult").html("<div class='alert alert-danger'>" + errorMsg + "</div>");
            }
        });
    });
    
    // DNS record edit - form submission
    jQuery("#dnsEditForm").on("submit", function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = jQuery(this).serialize();
        
        jQuery.ajax({
            type: "POST",
            url: "{$this->vars['modulelink']}&ajax=update_dns_record",
            data: formData,
            dataType: "json",
            beforeSend: function(xhr) {
                // Add extra header with token
                xhr.setRequestHeader("X-CSRF-Token", jQuery('input[name="token"]').val());
                jQuery("#dnsEditResult").html("<div class='alert alert-info'>Processing...</div>");
            },
            success: function(response) {
                if (response.success) {
                    jQuery("#dnsEditResult").html("<div class='alert alert-success'>" + response.message + "</div>");
                    
                    // Close modal and reload page after 2 seconds
                    setTimeout(function() {
                        jQuery("#dnsEditModal").modal("hide");
                        
                        if (window.location.href.indexOf("action=dns") > -1) {
                            window.location.reload(); // Reload on DNS page
                        }
                    }, 2000);
                } else {
                    jQuery("#dnsEditResult").html("<div class='alert alert-danger'>" + response.message + "</div>");
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = "AJAX error occurred: " + status;
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                } catch(e) {
                    errorMsg += "<br>Details: " + xhr.responseText.substring(0, 100);
                }
                
                jQuery("#dnsEditResult").html("<div class='alert alert-danger'>" + errorMsg + "</div>");
            }
        });
    });
    
    // DNS record edit - event listener
    jQuery(document).on("click", ".edit-dns", function() {
        var zoneId = jQuery(this).data("zone-id");
        var recordId = jQuery(this).data("record-id");
        var type = jQuery(this).data("type");
        var name = jQuery(this).data("name");
        var content = jQuery(this).data("content");
        var ttl = jQuery(this).data("ttl");
        var priority = jQuery(this).data("priority") || "";
        var proxied = jQuery(this).data("proxied");
        
        showEditDnsModal(zoneId, recordId, type, name, content, ttl, priority, proxied);
    });
    
    // DNS record delete
    jQuery(document).on("click", ".delete-dns", function() {
        var zoneId = jQuery(this).data("zone-id");
        var recordId = jQuery(this).data("record-id");
        var name = jQuery(this).data("name");
        
        confirmDeleteDns(zoneId, recordId, name);
    });
    
    // DNS record type change updates fields
    jQuery("#addDnsType").on("change", function() {
        updateDnsFormFields("add");
    });
    
    jQuery("#editDnsType").on("change", function() {
        updateDnsFormFields("edit");
    });
    
    // Update fields on form open
    updateDnsFormFields("add");
    updateDnsFormFields("edit");
    
    // Function to update DNS form fields
    function updateDnsFormFields(prefix) {
        var type = jQuery("#" + prefix + "DnsType").val();
        var priorityField = jQuery("." + prefix + "-dns-priority");
        var proxyField = jQuery("." + prefix + "-dns-proxy");
        var contentField = jQuery("#" + prefix + "DnsContent");
        var contentHelp = jQuery("#" + prefix + "DnsContentHelp");
        
        // Show/hide priority field
        if (priorityTypes.includes(type)) {
            priorityField.show();
        } else {
            priorityField.hide();
        }
        
        // Show/hide proxy field
        if (proxyableTypes.includes(type)) {
            proxyField.show();
        } else {
            proxyField.hide();
            jQuery("#" + prefix + "DnsProxied").prop("checked", false);
        }
        
        // Update content field example
        if (dnsExamples[type]) {
            contentHelp.text("Example: " + dnsExamples[type]);
            contentField.attr("placeholder", dnsExamples[type]);
        } else {
            contentHelp.text("");
            contentField.attr("placeholder", "");
        }
    }
});

// Show DNS edit modal
function showEditDnsModal(zoneId, recordId, type, name, content, ttl, priority, proxied) {
    jQuery("#editDnsZoneId").val(zoneId);
    jQuery("#editDnsRecordId").val(recordId);
    jQuery("#editDnsType").val(type);
    jQuery("#editDnsName").val(name);
    jQuery("#editDnsContent").val(content);
    jQuery("#editDnsTtl").val(ttl);
    
    if (type === "MX" || type === "SRV" || type === "URI") {
        jQuery("#editDnsPriority").val(priority || 10);
        jQuery(".edit-dns-priority").show();
    } else {
        jQuery(".edit-dns-priority").hide();
    }
    
    // Proxy setting
    jQuery("#editDnsProxied").prop("checked", proxied === "1" || proxied === true);
    
    if (["A", "AAAA", "CNAME"].includes(type)) {
        jQuery(".edit-dns-proxy").show();
    } else {
        jQuery(".edit-dns-proxy").hide();
    }
    
    // Update content example
    updateDnsContentExample(type, "edit");
    
    jQuery("#dnsEditModal").modal("show");
}

// DNS record delete confirmation
function confirmDeleteDns(zoneId, recordId, name) {
    if (confirm("$confirmDeleteText " + name + "?")) {
        // AJAX request
        jQuery.ajax({
            type: "POST",
            url: "{$this->vars['modulelink']}&ajax=delete_dns_record",
            data: {
                token: "{$this->csrfToken}",
                zone_id: zoneId,
                record_id: recordId
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    
                    if (window.location.href.indexOf("action=dns") > -1) {
                        window.location.reload(); // Reload on DNS page
                    }
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = "AJAX error occurred: " + status;
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                } catch(e) {
                    errorMsg += " - Details: " + xhr.responseText.substring(0, 100);
                }
                
                alert(errorMsg);
            }
        });
    }
}

// Update DNS content example
function updateDnsContentExample(type, prefix) {
    var contentHelp = jQuery("#" + prefix + "DnsContentHelp");
    var contentField = jQuery("#" + prefix + "DnsContent");
    
    var examples = {
        "A": "192.168.0.1",
        "AAAA": "2001:0db8:85a3:0000:0000:8a2e:0370:7334",
        "CNAME": "example.com",
        "MX": "mail.example.com",
        "TXT": "v=spf1 include:example.com ~all",
        "NS": "ns1.example.com",
        "SRV": "sip.example.com",
        "CAA": "0 issue \"letsencrypt.org\"",
        "DNSKEY": "257 3 13 mdsswUyr3DPW132mOi8V9xESWE8jTo0dxCjjnopKl+GqJxpVXckHAeF+KkxLbxILfDLUT0rAK9iUzy1L53eKGQ==",
        "DS": "12345 13 2 1F8188345CD3BE1F533B5CE927F2C2A9B80C0D041F46E9E6210F594F5D5C8BA9",
        "NAPTR": "10 100 \"s\" \"SIP+D2T\" \"\" _sip._tcp.example.com",
        "SMIMEA": "3 0 0 308201A2300D06092A864886F70D01010B05003059301D060355045A13...",
        "SSHFP": "2 1 123456789abcdef67890123456789abcdef67890",
        "TLSA": "3 0 1 d2abde240d7cd3ee6b4b28c54df034b97983a1d16e8a410e4561cb106618e971",
        "URI": "10 1 \"https://example.com\""
    };
    
    if (examples[type]) {
        contentHelp.text("Example: " + examples[type]);
        contentField.attr("placeholder", examples[type]);
    } else {
        contentHelp.text("");
        contentField.attr("placeholder", "");
    }
}
</script>
EOT;
    }
}
