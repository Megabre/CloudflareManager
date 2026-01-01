<?php
/**
 * Admin Domain Details Trait - Domain Details and Zone Settings Management
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!trait_exists('CloudflareManager\AdminDomainDetails')) {
trait AdminDomainDetails {
    /**
     * Domain details page - Modern redesign with tabs
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
            $settings = $domainData['settings'];
            
            // Main container
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">';
            echo '<div class="pull-right">';
            echo '<a href="' . $this->vars['modulelink'] . '&action=domains" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> ' . (isset($this->lang['back_to_domains']) ? $this->lang['back_to_domains'] : 'Back to Domains') . '</a>';
            echo '</div>';
            echo '<h3 class="panel-title"><i class="fa fa-globe"></i> ' . htmlspecialchars($domain->domain) . '</h3>';
            echo '</div>';
            echo '<div class="panel-body">';
            
            // Status badge at top
            $zoneStatus = isset($domain->zone_status) ? strtolower($domain->zone_status) : 'inactive';
            $statusClass = ($zoneStatus == 'active') ? 'success' : 'default';
            echo '<div class="row" style="margin-bottom: 15px;">';
            echo '<div class="col-md-12">';
            echo '<span class="label label-' . $statusClass . '" style="font-size: 13px; padding: 6px 12px;">';
            echo '<i class="fa fa-circle"></i> ' . ucfirst($zoneStatus);
            echo '</span>';
            if (isset($settings['ssl']['status']) && strtolower($settings['ssl']['status']) == 'active') {
                echo ' <span class="label label-success" style="font-size: 13px; padding: 6px 12px; margin-left: 8px;">';
                echo '<i class="fa fa-lock"></i> SSL Active';
                echo '</span>';
            }
            echo '</div>';
            echo '</div>';
            
            // Tab navigation
            echo '<ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">';
            echo '<li role="presentation" class="active"><a href="#overview" aria-controls="overview" role="tab" data-toggle="tab"><i class="fa fa-info-circle"></i> ' . (isset($this->lang['overview']) ? $this->lang['overview'] : 'Overview') . '</a></li>';
            echo '<li role="presentation"><a href="#zone-info" aria-controls="zone-info" role="tab" data-toggle="tab"><i class="fa fa-server"></i> ' . (isset($this->lang['zone_information']) ? $this->lang['zone_information'] : 'Zone Information') . '</a></li>';
            echo '<li role="presentation"><a href="#features" aria-controls="features" role="tab" data-toggle="tab"><i class="fa fa-rocket"></i> ' . (isset($this->lang['cloudflare_features']) ? $this->lang['cloudflare_features'] : 'Cloudflare Features') . '</a></li>';
            echo '<li role="presentation"><a href="#cache" aria-controls="cache" role="tab" data-toggle="tab"><i class="fa fa-database"></i> ' . (isset($this->lang['cache_management']) ? $this->lang['cache_management'] : 'Cache Management') . '</a></li>';
            echo '</ul>';
            
            // Tab content
            echo '<div class="tab-content">';
            
            // Tab 1: Overview
            echo '<div role="tabpanel" class="tab-pane active" id="overview">';
            echo '<div class="row">';
            
            // Left column - Domain Info
            echo '<div class="col-md-6">';
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-info"></i> ' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . ' ' . (isset($this->lang['details']) ? $this->lang['details'] : 'Details') . '</h4></div>';
            echo '<div class="panel-body">';
            echo '<table class="table table-striped table-hover">';
            echo '<tbody>';
            echo '<tr><th style="width:40%">' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th><td><strong>' . htmlspecialchars($domain->domain) . '</strong></td></tr>';
            echo '<tr><th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th><td>';
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
            if (isset($settings['plan'])) {
                echo '<tr><th>' . (isset($this->lang['plan_type']) ? $this->lang['plan_type'] : 'Plan Type') . '</th><td>';
                $planName = htmlspecialchars($settings['plan']['name']);
                $planBadge = 'default';
                if (stripos($planName, 'pro') !== false) $planBadge = 'info';
                elseif (stripos($planName, 'business') !== false) $planBadge = 'primary';
                elseif (stripos($planName, 'enterprise') !== false) $planBadge = 'success';
                echo '<span class="label label-' . $planBadge . '">' . $planName . '</span>';
                echo '</td></tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // Right column - Quick Actions
            echo '<div class="col-md-6">';
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-bolt"></i> ' . (isset($this->lang['quick_actions']) ? $this->lang['quick_actions'] : 'Quick Actions') . '</h4></div>';
            echo '<div class="panel-body">';
            echo '<div class="list-group">';
            echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domainId . '" class="list-group-item">';
            echo '<i class="fa fa-list fa-fw"></i> ' . (isset($this->lang['manage_dns']) ? $this->lang['manage_dns'] : 'Manage DNS Records');
            echo '</a>';
            echo '<a href="' . $this->vars['modulelink'] . '&action=zone-settings&zone_id=' . $domain->zone_id . '" class="list-group-item">';
            echo '<i class="fa fa-cog fa-fw"></i> ' . (isset($this->lang['zone_settings']) ? $this->lang['zone_settings'] : 'Zone Settings');
            echo '</a>';
            if (isset($domain->zone_id)) {
                echo '<a href="https://dash.cloudflare.com/' . htmlspecialchars($domain->zone_id) . '/' . htmlspecialchars($domain->domain) . '" target="_blank" class="list-group-item">';
                echo '<i class="fa fa-external-link fa-fw"></i> ' . (isset($this->lang['open_in_cloudflare']) ? $this->lang['open_in_cloudflare'] : 'Open in Cloudflare Dashboard');
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>'; // End row
            echo '</div>'; // End overview tab
            
            // Tab 2: Zone Information
            echo '<div role="tabpanel" class="tab-pane" id="zone-info">';
            if ($zoneDetails) {
                echo '<div class="row">';
                echo '<div class="col-md-12">';
                echo '<div class="panel panel-default">';
                echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-server"></i> ' . (isset($this->lang['zone_information']) ? $this->lang['zone_information'] : 'Zone Information') . '</h4></div>';
                echo '<div class="panel-body">';
                echo '<div class="row">';
                
                // Zone Details
                echo '<div class="col-md-6">';
                echo '<h5><strong>' . (isset($this->lang['zone_details']) ? $this->lang['zone_details'] : 'Zone Details') . '</strong></h5>';
                echo '<table class="table table-striped table-condensed">';
                if (isset($zoneDetails['id'])) {
                    echo '<tr><th style="width:40%">Zone ID</th><td><code>' . htmlspecialchars($zoneDetails['id']) . '</code></td></tr>';
                }
                if (isset($zoneDetails['status'])) {
                    $statusClass = ($zoneDetails['status'] == 'active') ? 'success' : 'warning';
                    echo '<tr><th>Status</th><td><span class="label label-' . $statusClass . '">' . ucfirst($zoneDetails['status']) . '</span></td></tr>';
                }
                if (isset($zoneDetails['type'])) {
                    echo '<tr><th>Type</th><td>' . ucfirst($zoneDetails['type']) . '</td></tr>';
                }
                if (isset($zoneDetails['plan']['name'])) {
                    $planName = htmlspecialchars($zoneDetails['plan']['name']);
                    $planBadge = 'default';
                    if (stripos($planName, 'pro') !== false) $planBadge = 'info';
                    elseif (stripos($planName, 'business') !== false) $planBadge = 'primary';
                    elseif (stripos($planName, 'enterprise') !== false) $planBadge = 'success';
                    echo '<tr><th>Plan</th><td><span class="label label-' . $planBadge . '">' . $planName . '</span></td></tr>';
                }
                if (isset($zoneDetails['paused'])) {
                    $pausedClass = $zoneDetails['paused'] ? 'warning' : 'success';
                    $pausedText = $zoneDetails['paused'] ? 'Paused' : 'Active';
                    echo '<tr><th>Zone Status</th><td><span class="label label-' . $pausedClass . '">' . $pausedText . '</span></td></tr>';
                }
                if (isset($zoneDetails['development_mode'])) {
                    $devMode = $zoneDetails['development_mode'];
                    $devClass = ($devMode > 0) ? 'warning' : 'success';
                    $devText = ($devMode > 0) ? 'Enabled' : 'Disabled';
                    echo '<tr><th>Development Mode</th><td><span class="label label-' . $devClass . '">' . $devText . '</span></td></tr>';
                }
                echo '</table>';
                echo '</div>';
                
                // Dates & Account
                echo '<div class="col-md-6">';
                echo '<h5><strong>' . (isset($this->lang['dates_account']) ? $this->lang['dates_account'] : 'Dates & Account') . '</strong></h5>';
                echo '<table class="table table-striped table-condensed">';
                if (isset($zoneDetails['created_on'])) {
                    echo '<tr><th style="width:40%">Created On</th><td>' . date('d.m.Y H:i:s', strtotime($zoneDetails['created_on'])) . '</td></tr>';
                }
                if (isset($zoneDetails['activated_on'])) {
                    echo '<tr><th>Activated On</th><td>' . date('d.m.Y H:i:s', strtotime($zoneDetails['activated_on'])) . '</td></tr>';
                }
                if (isset($zoneDetails['modified_on'])) {
                    echo '<tr><th>Last Modified</th><td>' . date('d.m.Y H:i:s', strtotime($zoneDetails['modified_on'])) . '</td></tr>';
                }
                if (isset($zoneDetails['account']['name'])) {
                    echo '<tr><th>Cloudflare Account</th><td>' . htmlspecialchars($zoneDetails['account']['name']) . '</td></tr>';
                }
                if (isset($zoneDetails['original_registrar'])) {
                    echo '<tr><th>Original Registrar</th><td>' . htmlspecialchars($zoneDetails['original_registrar']) . '</td></tr>';
                }
                echo '</table>';
                echo '</div>';
                
                echo '</div>'; // End row
                
                // Name Servers
                if (isset($zoneDetails['name_servers']) && is_array($zoneDetails['name_servers']) && count($zoneDetails['name_servers']) > 0) {
                    echo '<div class="row" style="margin-top: 15px;">';
                    echo '<div class="col-md-12">';
                    echo '<h5><strong>Name Servers</strong></h5>';
                    echo '<div class="well well-sm">';
                    foreach ($zoneDetails['name_servers'] as $ns) {
                        echo '<code style="margin-right: 10px; padding: 4px 8px; background: #f5f5f5; display: inline-block; margin-bottom: 5px;">' . htmlspecialchars($ns) . '</code>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning">Zone details not available.</div>';
            }
            echo '</div>'; // End zone-info tab
            
            // Tab 3: Cloudflare Features
            echo '<div role="tabpanel" class="tab-pane" id="features">';
            echo '<div class="row">';
            
            // Get feature counts
            $featureData = [];
            try {
                // SSL Universal Settings
                try {
                    $sslUniversal = $this->api->getSSLUniversalSettings($domain->zone_id);
                    $featureData['ssl'] = $sslUniversal;
                } catch (Exception $e) {}
                
                // Firewall Rules Count
                try {
                    $firewallRules = $this->api->listFirewallRules($domain->zone_id, 1, 100);
                    $featureData['firewall_count'] = is_array($firewallRules) ? count($firewallRules) : 0;
                } catch (Exception $e) {
                    $featureData['firewall_count'] = 0;
                }
                
                // Page Rules Count
                try {
                    $pageRules = $this->api->listPageRules($domain->zone_id, 1, 100);
                    $featureData['page_rules_count'] = is_array($pageRules) ? count($pageRules) : 0;
                } catch (Exception $e) {
                    $featureData['page_rules_count'] = 0;
                }
                
                // Rate Limits Count
                try {
                    $rateLimits = $this->api->listRateLimits($domain->zone_id, 1, 100);
                    $featureData['rate_limits_count'] = is_array($rateLimits) ? count($rateLimits) : 0;
                } catch (Exception $e) {
                    $featureData['rate_limits_count'] = 0;
                }
                
                // Security & Cache Settings
                try {
                    $securityLevel = $this->api->getSecurityLevel($domain->zone_id);
                    $cacheLevel = $this->api->getCacheLevel($domain->zone_id);
                    $featureData['security_level'] = isset($securityLevel['value']) ? $securityLevel['value'] : null;
                    $featureData['cache_level'] = isset($cacheLevel['value']) ? $cacheLevel['value'] : null;
                } catch (Exception $e) {}
                
            } catch (Exception $e) {}
            
            // Feature cards
            echo '<div class="col-md-4">';
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-lock"></i> SSL/TLS</h4></div>';
            echo '<div class="panel-body">';
            if (isset($featureData['ssl']['enabled'])) {
                echo '<p><strong>Universal SSL:</strong> <span class="label label-' . ($featureData['ssl']['enabled'] ? 'success' : 'default') . '">' . ($featureData['ssl']['enabled'] ? 'Enabled' : 'Disabled') . '</span></p>';
            }
            if (isset($settings['ssl']['status'])) {
                echo '<p><strong>SSL Status:</strong> <span class="label label-success">' . ucfirst($settings['ssl']['status']) . '</span></p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="col-md-4">';
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-shield"></i> Firewall</h4></div>';
            echo '<div class="panel-body">';
            echo '<p><strong>Total Rules:</strong> <span class="badge">' . ($featureData['firewall_count'] ?? 0) . '</span></p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="col-md-4">';
            echo '<div class="panel panel-success">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-file-text"></i> Page Rules</h4></div>';
            echo '<div class="panel-body">';
            echo '<p><strong>Total Rules:</strong> <span class="badge">' . ($featureData['page_rules_count'] ?? 0) . '</span></p>';
            if (isset($zoneDetails['meta']['page_rule_quota'])) {
                echo '<p><strong>Quota:</strong> ' . $zoneDetails['meta']['page_rule_quota'] . '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="col-md-4">';
            echo '<div class="panel panel-primary">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-tachometer"></i> Rate Limiting</h4></div>';
            echo '<div class="panel-body">';
            echo '<p><strong>Total Rules:</strong> <span class="badge">' . ($featureData['rate_limits_count'] ?? 0) . '</span></p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="col-md-4">';
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-cog"></i> Security & Cache</h4></div>';
            echo '<div class="panel-body">';
            if (isset($featureData['security_level'])) {
                echo '<p><strong>Security Level:</strong> <span class="label label-info">' . htmlspecialchars($featureData['security_level']) . '</span></p>';
            }
            if (isset($featureData['cache_level'])) {
                echo '<p><strong>Cache Level:</strong> <span class="label label-info">' . htmlspecialchars($featureData['cache_level']) . '</span></p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>'; // End row
            echo '</div>'; // End features tab
            
            // Tab 4: Cache Management
            echo '<div role="tabpanel" class="tab-pane" id="cache">';
            echo '<div class="row">';
            echo '<div class="col-md-8 col-md-offset-2">';
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-database"></i> ' . (isset($this->lang['cache_management']) ? $this->lang['cache_management'] : 'Cache Management') . '</h4></div>';
            echo '<div class="panel-body">';
            echo '<p class="lead">' . (isset($this->lang['cache_management_desc']) ? $this->lang['cache_management_desc'] : 'If you have updated your website content, you can purge the Cloudflare cache to ensure visitors see the latest version.') . '</p>';
            echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=domain-details&domain_id=' . $domainId . '">';
            echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
            echo '<input type="hidden" name="zone_id" value="' . $domain->zone_id . '">';
            echo '<input type="hidden" name="purge_cache" value="1">';
            echo '<button type="submit" class="btn btn-warning btn-lg" onclick="return confirm(\'' . (isset($this->lang['confirm_purge_cache']) ? $this->lang['confirm_purge_cache'] : 'Are you sure you want to purge the cache?') . '\')">';
            echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['purge_cache']) ? $this->lang['purge_cache'] : 'Purge All Cache');
            echo '</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>'; // End cache tab
            
            echo '</div>'; // End tab-content
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
}
}
