<?php
/**
 * Cloudflare Manager - WHMCS Addon Module - Main Configuration File
 *
 * @package    WHMCS
 * @author     Ali Çömez / Slaweally
 * @copyright  Copyright (c) 2025, Megabre.com
 * @version    1.0.4
 * @link       https://github.com/megabre
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Force forms not to be cached
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * Configuration function
 */
function cloudflaremanager_config() {
    return [
        "name" => "Cloudflare Manager",
        "description" => "Manage your Cloudflare domains and DNS records within WHMCS",
        "version" => "1.0.4",
        "author" => "Ali Çömez / Slaweally",
        "language" => "turkish",
        "fields" => [
            "api_email" => [
                "FriendlyName" => "Cloudflare Email",
                "Type" => "text",
                "Size" => "40",
                "Description" => "Enter your Cloudflare account email (leave empty if using API Token)",
                "Default" => "",
                "Required" => false,
            ],
            "api_key" => [
                "FriendlyName" => "API Key / Token",
                "Type" => "password",
                "Size" => "40",
                "Description" => "Enter your Global API Key or API Token",
                "Default" => "",
                "Required" => true,
            ],
            "client_permissions" => [
                "FriendlyName" => "Client Permissions",
                "Type" => "checkboxes",
                "Options" => [
                    "view_domain_details" => "View Domain Details",
                    "view_dns_records" => "View DNS Records",
                    "view_ssl_status" => "View SSL Status",
                    "view_cache_status" => "View Cache Status",
                ],
                "Description" => "Select which information clients can see",
                "Default" => "view_domain_details,view_dns_records",
            ],
            "use_cache" => [
                "FriendlyName" => "Use API Cache",
                "Type" => "yesno",
                "Description" => "Cache API requests to improve performance",
                "Default" => "yes",
            ],
            "cache_expiry" => [
                "FriendlyName" => "Cache Duration (seconds)",
                "Type" => "text",
                "Size" => "5", 
                "Description" => "How long to keep API cache (minimum 60 seconds)",
                "Default" => "300",
            ],
        ],
    ];
}

/**
 * Activation function
 */
function cloudflaremanager_activate() {
    try {
        // Domains table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_domains')) {
            Capsule::schema()->create('mod_cloudflaremanager_domains', function ($table) {
                $table->increments('id');
                $table->string('domain', 255);
                $table->string('zone_id', 50);
                $table->integer('client_id');
                $table->string('zone_status', 50)->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('expiry_date')->nullable();
                $table->string('registrar', 100)->nullable();
                $table->text('settings')->nullable();
                $table->text('analytics')->nullable();
                $table->index(['zone_id']);
                $table->index(['client_id']);
                $table->index(['domain']);
            });
        } else {
            // Add missing columns to existing table
            $schema = Capsule::schema();
            if (!$schema->hasColumn('mod_cloudflaremanager_domains', 'zone_status')) {
                $schema->table('mod_cloudflaremanager_domains', function ($table) {
                    $table->string('zone_status', 50)->nullable();
                });
            }
            if (!$schema->hasColumn('mod_cloudflaremanager_domains', 'registrar')) {
                $schema->table('mod_cloudflaremanager_domains', function ($table) {
                    $table->string('registrar', 100)->nullable();
                });
            }
            if (!$schema->hasColumn('mod_cloudflaremanager_domains', 'analytics')) {
                $schema->table('mod_cloudflaremanager_domains', function ($table) {
                    $table->text('analytics')->nullable();
                });
            }
        }

        // DNS records table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_dns_records')) {
            Capsule::schema()->create('mod_cloudflaremanager_dns_records', function ($table) {
                $table->increments('id');
                $table->integer('domain_id');
                $table->string('record_id', 50);
                $table->string('type', 10);
                $table->string('name', 255);
                $table->text('content');
                $table->integer('ttl');
                $table->boolean('proxied');
                $table->integer('priority')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['domain_id']);
                $table->index(['record_id']);
            });
        } else {
            // Add missing columns to existing table
            if (!Capsule::schema()->hasColumn('mod_cloudflaremanager_dns_records', 'priority')) {
                Capsule::schema()->table('mod_cloudflaremanager_dns_records', function ($table) {
                    $table->integer('priority')->nullable();
                });
            }
        }

        // Settings table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_settings')) {
            Capsule::schema()->create('mod_cloudflaremanager_settings', function ($table) {
                $table->increments('id');
                $table->string('user_id', 50)->default('0');
                $table->string('setting_key', 100);
                $table->text('setting_value');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['user_id', 'setting_key']);
            });
        }

        // Cache table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_cache')) {
            Capsule::schema()->create('mod_cloudflaremanager_cache', function ($table) {
                $table->increments('id');
                $table->string('cache_key', 255);
                $table->longText('cache_value');
                $table->dateTime('expires_at');
                $table->dateTime('created_at');
                $table->dateTime('updated_at')->nullable();
                $table->index(['cache_key']);
                $table->index(['expires_at']);
            });
        } else {
            // Add updated_at column if needed
            if (!Capsule::schema()->hasColumn('mod_cloudflaremanager_cache', 'updated_at')) {
                Capsule::schema()->table('mod_cloudflaremanager_cache', function ($table) {
                    $table->dateTime('updated_at')->nullable();
                });
            }
        }

        return [
            'status' => 'success',
            'description' => 'Cloudflare Manager module successfully activated.'
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => 'Could not create database tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivation function
 */
function cloudflaremanager_deactivate() {
    return [
        'status' => 'success',
        'description' => 'Cloudflare Manager has been deactivated. Database tables were preserved.'
    ];
}

/**
 * Upgrade function
 */
function cloudflaremanager_upgrade($vars) {
    $currentVersion = $vars['version'];
    
    // Upgrade from version 1.0.2 to 1.0.3
    if (version_compare($currentVersion, '1.0.3', '<')) {
        try {
            // Database optimizations - add indexes
            if (!Capsule::schema()->hasColumn('mod_cloudflaremanager_domains', 'domain_index')) {
                Capsule::schema()->table('mod_cloudflaremanager_domains', function ($table) {
                    $table->index(['domain'], 'domain_index');
                });
            }
            
            // Add updated_at to cache table
            if (!Capsule::schema()->hasColumn('mod_cloudflaremanager_cache', 'updated_at')) {
                Capsule::schema()->table('mod_cloudflaremanager_cache', function ($table) {
                    $table->dateTime('updated_at')->nullable();
                });
            }
            
            // Cleanup operations
            Capsule::table('mod_cloudflaremanager_cache')->truncate();
            
            return [
                'status' => 'success',
                'description' => 'Cloudflare Manager successfully upgraded to version 1.0.3.'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'description' => 'Error during upgrade: ' . $e->getMessage()
            ];
        }
    }
    
    // Upgrade from version 1.0.3 to 1.0.4
    if (version_compare($currentVersion, '1.0.4', '<')) {
        try {
            // Truncate cache to ensure fresh data with new API handling
            Capsule::table('mod_cloudflaremanager_cache')->truncate();
            
            return [
                'status' => 'success',
                'description' => 'Cloudflare Manager successfully upgraded to version 1.0.4.'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'description' => 'Error during upgrade: ' . $e->getMessage()
            ];
        }
    }
    
    return [
        'status' => 'success',
        'description' => 'No upgrade required.'
    ];
}

/**
 * Admin panel output
 */
function cloudflaremanager_output($vars) {
    // Load required classes - modular structure
    require_once __DIR__ . '/lib/CloudflareAPI.php';
    require_once __DIR__ . '/lib/DomainManager.php';
    require_once __DIR__ . '/lib/DNSManager.php';
    require_once __DIR__ . '/lib/AjaxHandler.php';
    require_once __DIR__ . '/lib/Admin.php';
    
    // Initialize admin class and display
    $admin = new CloudflareManager\Admin($vars);
    $admin->display();
}

/**
 * Client area output
 */
function cloudflaremanager_clientarea($vars) {
    // Load required classes
    require_once __DIR__ . '/lib/CloudflareAPI.php';
    require_once __DIR__ . '/lib/DomainManager.php';
    require_once __DIR__ . '/lib/DNSManager.php';
    require_once __DIR__ . '/lib/Client.php';
    
    // Initialize client class
    $client = new CloudflareManager\Client($vars);
    
    // Action check - for cache purging
    if (isset($_POST['action']) && $_POST['action'] === 'purge_cache') {
        $client->purgeCache();
        
        // Handle success/error messages
        if (isset($_SESSION['cloudflaremanager_success'])) {
            $vars['_lang']['success'] = $_SESSION['cloudflaremanager_success'];
            unset($_SESSION['cloudflaremanager_success']);
        }
        
        if (isset($_SESSION['cloudflaremanager_error'])) {
            $vars['_lang']['error'] = $_SESSION['cloudflaremanager_error'];
            unset($_SESSION['cloudflaremanager_error']);
        }
    }
    
    return $client->display();
}