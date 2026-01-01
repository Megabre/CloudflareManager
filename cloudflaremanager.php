<?php
/**
 * Cloudflare Manager - WHMCS Addon Module
 * Professional Cloudflare Domain and DNS Management Module
 *
 * @package    WHMCS
 * @author     Ali Çömez / Slaweally
 * @copyright  Copyright (c) 2025, Megabre.com
 * @version    2.0.0
 * @link       https://github.com/megabre
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module Configuration
 */
function cloudflaremanager_config() {
    return [
        "name" => "Cloudflare Manager",
        "description" => "Professional Cloudflare domain and DNS management module with registrar support",
        "version" => "2.0.0",
        "author" => "Ali Çömez / Slaweally",
        "language" => "turkish",
        "fields" => [
            "api_email" => [
                "FriendlyName" => "Cloudflare Email",
                "Type" => "text",
                "Size" => "40",
                "Description" => "Your Cloudflare account email (leave empty if using API Token)",
                "Default" => "",
                "Required" => false,
            ],
            "api_key" => [
                "FriendlyName" => "API Key / Token",
                "Type" => "password",
                "Size" => "40",
                "Description" => "Your Cloudflare Global API Key or API Token",
                "Default" => "",
                "Required" => true,
            ],
            "client_permissions" => [
                "FriendlyName" => "Client Permissions",
                "Type" => "checkboxes",
                "Options" => [
                    "view_domain_details" => "View Domain Details",
                    "view_dns_records" => "View DNS Records",
                    "edit_dns_records" => "Edit DNS Records",
                    "view_ssl_status" => "View SSL Status",
                    "view_cache_status" => "View Cache Status",
                ],
                "Description" => "Select which features clients can access",
                "Default" => "view_domain_details,view_dns_records",
            ],
            "use_cache" => [
                "FriendlyName" => "Enable API Cache",
                "Type" => "yesno",
                "Description" => "Cache API responses to improve performance",
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
 * Module Activation
 */
function cloudflaremanager_activate() {
    try {
        // Domains table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_domains')) {
            Capsule::schema()->create('mod_cloudflaremanager_domains', function ($table) {
                $table->increments('id');
                $table->string('domain', 255);
                $table->string('zone_id', 50)->unique();
                $table->integer('client_id')->default(0);
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
                $table->integer('ttl')->default(1);
                $table->boolean('proxied')->default(false);
                $table->integer('priority')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['domain_id']);
                $table->index(['record_id']);
                $table->index(['type']);
            });
        }

        // Cache table
        if (!Capsule::schema()->hasTable('mod_cloudflaremanager_cache')) {
            Capsule::schema()->create('mod_cloudflaremanager_cache', function ($table) {
                $table->increments('id');
                $table->string('cache_key', 255)->unique();
                $table->longText('cache_value');
                $table->dateTime('expires_at');
                $table->dateTime('created_at');
                $table->dateTime('updated_at')->nullable();
                $table->index(['cache_key']);
                $table->index(['expires_at']);
            });
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
 * Module Deactivation
 */
function cloudflaremanager_deactivate() {
    return [
        'status' => 'success',
        'description' => 'Cloudflare Manager has been deactivated. Database tables were preserved.'
    ];
}

/**
 * Module Upgrade
 */
function cloudflaremanager_upgrade($vars) {
    $currentVersion = $vars['version'] ?? '1.0.0';
    
    // Upgrade to version 2.0.0
    if (version_compare($currentVersion, '2.0.0', '<')) {
        try {
            // Clear cache for fresh start
            Capsule::table('mod_cloudflaremanager_cache')->truncate();
            
            return [
                'status' => 'success',
                'description' => 'Cloudflare Manager successfully upgraded to version 2.0.0.'
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
 * Admin Panel Output
 */
function cloudflaremanager_output($vars) {
    require_once __DIR__ . '/lib/CloudflareAPI.php';
    require_once __DIR__ . '/lib/DomainManager.php';
    require_once __DIR__ . '/lib/DNSManager.php';
    require_once __DIR__ . '/lib/AjaxHandler.php';
    require_once __DIR__ . '/lib/Admin.php';
    
    $admin = new CloudflareManager\Admin($vars);
    $admin->display();
}

/**
 * Client Area Output
 */
function cloudflaremanager_clientarea($vars) {
    require_once __DIR__ . '/lib/CloudflareAPI.php';
    require_once __DIR__ . '/lib/DomainManager.php';
    require_once __DIR__ . '/lib/DNSManager.php';
    require_once __DIR__ . '/lib/Client.php';
    
    $client = new CloudflareManager\Client($vars);
    return $client->display();
}
