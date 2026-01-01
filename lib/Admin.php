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

// Load trait files
require_once __DIR__ . '/admin-domains.php';
require_once __DIR__ . '/admin-domain-details.php';
require_once __DIR__ . '/admin-zone-settings.php';
require_once __DIR__ . '/admin-dns-manager.php';

if (!class_exists('CloudflareManager\Admin')) {
class Admin {
    use AdminDomains, AdminDomainDetails, AdminZoneSettings, AdminDnsManager;
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
        
        // DNS record operations are now handled directly in displayDnsManagement() method
        // No need to redirect to AJAX handler anymore
        
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
            // Update zone settings
            elseif (isset($_POST['update_zone_settings'])) {
                $this->updateZoneSettings();
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
        
        try {
            // Enable debug temporarily
            $wasDebug = $this->debug;
            $this->debug = true;
            if ($this->api) {
                $this->api->enableDebug();
            }
            if ($this->domainManager) {
                $this->domainManager->enableDebug();
            }
            
            $result = $this->domainManager->syncDomains();
            
            // Restore debug state
            $this->debug = $wasDebug;
            
            if ($result['success']) {
                $this->success = $result['message'];
                if (!empty($result['errors'])) {
                    $errorMsg = 'Some domains failed: ' . implode(', ', array_slice($result['errors'], 0, 3));
                    if (count($result['errors']) > 3) {
                        $errorMsg .= ' and ' . (count($result['errors']) - 3) . ' more';
                    }
                    $this->error = $errorMsg;
                }
            } else {
                $this->error = $result['message'];
            }
        } catch (Exception $e) {
            $this->error = 'Error syncing domains: ' . $e->getMessage();
            error_log("Sync Domains Exception: " . $e->getMessage());
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
        try {
            // Update permissions
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                $permissions = implode(',', $_POST['permissions']);
                
                // Update WHMCS addon module configuration
                $result = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudflaremanager')
                    ->where('setting', 'client_permissions')
                    ->update(['value' => $permissions]);
                
                if ($result === false) {
                    // Insert if doesn't exist
                    Capsule::table('tbladdonmodules')->insert([
                        'module' => 'cloudflaremanager',
                        'setting' => 'client_permissions',
                        'value' => $permissions
                    ]);
                }
            }
            
            // Update cache settings
            if (isset($_POST['use_cache'])) {
                $useCache = $_POST['use_cache'] == '1' ? 'yes' : 'no';
                
                $result = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudflaremanager')
                    ->where('setting', 'use_cache')
                    ->update(['value' => $useCache]);
                
                if ($result === false) {
                    Capsule::table('tbladdonmodules')->insert([
                        'module' => 'cloudflaremanager',
                        'setting' => 'use_cache',
                        'value' => $useCache
                    ]);
                }
            } else {
                // Uncheck means 'no'
                Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudflaremanager')
                    ->where('setting', 'use_cache')
                    ->update(['value' => 'no']);
            }
            
            if (isset($_POST['cache_expiry']) && intval($_POST['cache_expiry']) >= 60) {
                $cacheExpiry = intval($_POST['cache_expiry']);
                
                $result = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudflaremanager')
                    ->where('setting', 'cache_expiry')
                    ->update(['value' => $cacheExpiry]);
                
                if ($result === false) {
                    Capsule::table('tbladdonmodules')->insert([
                        'module' => 'cloudflaremanager',
                        'setting' => 'cache_expiry',
                        'value' => $cacheExpiry
                    ]);
                }
            }
            
            // Clear cache if API is available
            if ($this->api) {
                $this->api->clearAllCache();
            }
            
            $this->success = isset($this->lang['settings_updated']) ? $this->lang['settings_updated'] : 'Settings updated successfully';
        } catch (Exception $e) {
            $this->error = (isset($this->lang['settings_update_error']) ? $this->lang['settings_update_error'] : 'Error updating settings') . ': ' . $e->getMessage();
            
            if ($this->debug) {
                error_log("UpdateSettings Error: " . $e->getMessage());
            }
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
        try {
            $showWelcome = true;
            $welcomeDismissed = false;
            
            // Use session instead of database table
            if (!isset($_SESSION)) {
                session_start();
            }
            
            $welcomeKey = 'cloudflaremanager_welcome_' . $this->adminId;
            
            if (isset($_SESSION[$welcomeKey]) && $_SESSION[$welcomeKey] == '1') {
                $showWelcome = false;
            }
            
            // Check "Don't show again" action
            if (isset($_GET['dismiss_welcome']) && $_GET['dismiss_welcome'] == '1') {
                $_SESSION[$welcomeKey] = '1';
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
        echo '<li role="presentation" class="' . ($this->action == 'domains' || $this->action == '' ? 'active' : '') . '"><a href="' . $this->vars['modulelink'] . '&action=domains">' . (isset($this->lang['domains']) ? $this->lang['domains'] : 'Domains') . '</a></li>';
        echo '<li role="presentation" class="' . ($this->action == 'dns' ? 'active' : '') . '"><a href="' . $this->vars['modulelink'] . '&action=dns">' . (isset($this->lang['dns_management']) ? $this->lang['dns_management'] : 'DNS Management') . '</a></li>';
        echo '<li role="presentation" class="pull-right">';
        echo '<form method="post" action="' . $this->vars['modulelink'] . '" style="display:inline; margin:0;">';
        echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
        echo '<input type="hidden" name="clear_all_cache" value="1">';
        echo '<button type="submit" class="btn btn-warning btn-sm" onclick="return confirm(\'' . (isset($this->lang['confirm_clear_cache']) ? $this->lang['confirm_clear_cache'] : 'Are you sure you want to clear all cache?') . '\');">';
        echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['clear_all_cache']) ? $this->lang['clear_all_cache'] : 'Clear Cache');
        echo '</button>';
        echo '</form>';
        echo '</li>';
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
                
            case 'domain-details':
                $this->displayDomainDetails();
                break;
                
            case 'zone-settings':
                $this->displayZoneSettings();
                break;
                
            case 'domains':
            default:
                $this->displayDomains();
                break;
        }
    }

    // displayDomains() method moved to AdminDomains trait

    // displayDomainDetails() method moved to AdminDomainDetails trait

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
        // Prepare JavaScript variables
        $moduleLinkJs = json_encode($this->vars['modulelink'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $csrfTokenJs = json_encode($this->csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function() {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    ';
        echo '    // Show welcome modal (only if exists)';
        echo '    if (jQuery("#welcomeModal").length > 0) {';
        echo '        jQuery("#welcomeModal").modal("show");';
        echo '    }';
        echo '    ';
        echo '    // "Don\'t show again" button';
        echo '    jQuery("#closeWelcomeBtn").on("click", function() {';
        echo '        var dontShowAgain = jQuery("#dontShowAgain").is(":checked");';
        echo '        if (dontShowAgain) {';
        echo '            window.location.href = moduleLink + "&dismiss_welcome=1";';
        echo '        } else {';
        echo '            jQuery("#welcomeModal").modal("hide");';
        echo '        }';
        echo '    });';
        echo '});';
        echo ' ';
        echo '// Check SSL status for a zone';
        echo 'function checkSSLStatus(zoneId, domain) {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    var btn = jQuery(\'.check-ssl-btn[data-zone-id="\' + zoneId + \'"]\');';
        echo '    var originalHtml = btn.html();';
        echo '    btn.prop(\'disabled\', true).html(\'<i class="fa fa-spinner fa-spin"></i> Checking...\');';
        echo '    ';
        echo '    jQuery.ajax({';
        echo '        url: moduleLink + "&ajax=get_ssl_status&zone_id=" + zoneId + "&csrf=" + csrfToken,';
        echo '        type: "GET",';
        echo '        dataType: "json",';
        echo '        success: function(response) {';
        echo '            btn.prop(\'disabled\', false).html(originalHtml);';
        echo '            if (response.success) {';
        echo '                var status = response.status || "unknown";';
        echo '                var message = "SSL Status for " + domain + ":\\n\\nStatus: " + status;';
        echo '                if (response.details && response.details.verification_status) {';
        echo '                    message += "\\nVerification: " + response.details.verification_status;';
        echo '                }';
        echo '                alert(message);';
        echo '            } else {';
        echo '                alert("Error checking SSL status: " + (response.message || "Unknown error"));';
        echo '            }';
        echo '        },';
        echo '        error: function() {';
        echo '            btn.prop(\'disabled\', false).html(originalHtml);';
        echo '            alert("Error checking SSL status. Please try again.");';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo '// SSL check button click handler';
        echo 'jQuery(document).on("click", ".check-ssl-btn", function() {';
        echo '    var zoneId = jQuery(this).data("zone-id");';
        echo '    var domain = jQuery(this).data("domain");';
        echo '    checkSSLStatus(zoneId, domain);';
        echo '});';
        echo ' ';
        echo '// Zone Settings Functions';
        echo 'function loadZoneSettings() {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    var zoneId = jQuery("#zone_settings_domain").val();';
        echo '    var domain = jQuery("#zone_settings_domain option:selected").data("domain");';
        echo '    ';
        echo '    if (!zoneId) {';
        echo '        jQuery("#zoneSettingsContent").hide();';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    jQuery("#zoneSettingsTitle").text("Zone Settings - " + domain);';
        echo '    jQuery("#zoneSettingsContent").show();';
        echo '    ';
        echo '    jQuery.ajax({';
        echo '        url: moduleLink + "&ajax=get_zone_settings&zone_id=" + zoneId + "&csrf=" + csrfToken,';
        echo '        type: "GET",';
        echo '        dataType: "json",';
        echo '        success: function(response) {';
        echo '            if (response.success) {';
        echo '                if (response.development_mode == "on" || (response.settings && response.settings.development_mode && response.settings.development_mode.value == "on")) {';
        echo '                    jQuery("#developer_mode").prop("checked", true);';
        echo '                } else {';
        echo '                    jQuery("#developer_mode").prop("checked", false);';
        echo '                }';
        echo '                ';
        echo '                if (response.security_level) {';
        echo '                    jQuery("#security_level").val(response.security_level);';
        echo '                } else if (response.settings && response.settings.security_level) {';
        echo '                    jQuery("#security_level").val(response.settings.security_level.value);';
        echo '                }';
        echo '                ';
        echo '                if (response.zone && response.zone.paused) {';
        echo '                    jQuery("#pause_zone").prop("checked", response.zone.paused);';
        echo '                }';
        echo '            }';
        echo '        },';
        echo '        error: function() {';
        echo '            alert("Error loading zone settings. Please try again.");';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo 'function updateZoneSetting(setting, value) {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    var zoneId = jQuery("#zone_settings_domain").val();';
        echo '    ';
        echo '    if (!zoneId) {';
        echo '        alert("Please select a domain first.");';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    jQuery.ajax({';
        echo '        url: moduleLink + "&ajax=update_zone_setting&zone_id=" + zoneId + "&setting=" + setting + "&value=" + encodeURIComponent(value) + "&csrf=" + csrfToken,';
        echo '        type: "POST",';
        echo '        dataType: "json",';
        echo '        success: function(response) {';
        echo '            if (response.success) {';
        echo '                alert("Setting updated successfully!");';
        echo '            } else {';
        echo '                alert("Error: " + (response.message || "Unknown error"));';
        echo '            }';
        echo '        },';
        echo '        error: function() {';
        echo '            alert("Error updating setting. Please try again.");';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo 'function togglePauseZone(pause) {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    var zoneId = jQuery("#zone_settings_domain").val();';
        echo '    ';
        echo '    if (!zoneId) {';
        echo '        alert("Please select a domain first.");';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    if (!confirm(pause ? "Are you sure you want to pause Cloudflare for this zone?" : "Are you sure you want to unpause Cloudflare for this zone?")) {';
        echo '        jQuery("#pause_zone").prop("checked", !pause);';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    jQuery.ajax({';
        echo '        url: moduleLink + "&ajax=" + (pause ? "pause_zone" : "unpause_zone") + "&zone_id=" + zoneId + "&csrf=" + csrfToken,';
        echo '        type: "POST",';
        echo '        dataType: "json",';
        echo '        success: function(response) {';
        echo '            if (response.success) {';
        echo '                alert("Zone " + (pause ? "paused" : "unpaused") + " successfully!");';
        echo '            } else {';
        echo '                alert("Error: " + (response.message || "Unknown error"));';
        echo '                jQuery("#pause_zone").prop("checked", !pause);';
        echo '            }';
        echo '        },';
        echo '        error: function() {';
        echo '            alert("Error updating zone. Please try again.");';
        echo '            jQuery("#pause_zone").prop("checked", !pause);';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo 'function removeZone() {';
        echo '    var moduleLink = ' . $moduleLinkJs . ';';
        echo '    var csrfToken = ' . $csrfTokenJs . ';';
        echo '    var zoneId = jQuery("#zone_settings_domain").val();';
        echo '    var domain = jQuery("#zone_settings_domain option:selected").data("domain");';
        echo '    ';
        echo '    if (!zoneId) {';
        echo '        alert("Please select a domain first.");';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    if (!confirm("WARNING: This will permanently delete \\"" + domain + "\\" from Cloudflare. This action cannot be undone!\\n\\nAre you absolutely sure?")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    if (!confirm("Final confirmation: Delete \\"" + domain + "\\" from Cloudflare?")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    jQuery.ajax({';
        echo '        url: moduleLink + "&ajax=delete_zone&zone_id=" + zoneId + "&csrf=" + csrfToken,';
        echo '        type: "POST",';
        echo '        dataType: "json",';
        echo '        success: function(response) {';
        echo '            if (response.success) {';
        echo '                alert("Zone deleted successfully!");';
        echo '                location.reload();';
        echo '            } else {';
        echo '                alert("Error: " + (response.message || "Unknown error"));';
        echo '            }';
        echo '        },';
        echo '        error: function() {';
        echo '            alert("Error deleting zone. Please try again.");';
        echo '        }';
        echo '    });';
        echo '}';
        echo '</script>';
    }
}
}