<?php
/**
 * Admin Domains Trait - Domain List Management
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!trait_exists('CloudflareManager\AdminDomains')) {
trait AdminDomains {
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
            $perPage = 20; // Show 20 domains per page
            
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
                
            // Always show sync button and message if no domains
            if (count($domains) == 0 && $totalDomains == 0) {
                echo '<div class="alert alert-info">';
                echo '<i class="fa fa-info-circle"></i> ';
                echo isset($this->lang['no_domains_found']) ? $this->lang['no_domains_found'] : 'No domains found.';
                echo ' <strong>' . (isset($this->lang['sync_domains']) ? $this->lang['sync_domains'] : 'Please click "Sync Domains" button above to fetch domains from Cloudflare.') . '</strong>';
                echo '</div>';
            }
            
            if (count($domains) > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-bordered table-striped" id="domainsTable">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th>';
                echo '<th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th>';
                echo '<th>' . (isset($this->lang['created_on']) ? $this->lang['created_on'] : 'Created On') . '</th>';
                echo '<th>' . (isset($this->lang['expiry_date']) ? $this->lang['expiry_date'] : 'Expiry Date') . '</th>';
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
                    if ($domain->expiry_date) {
                        echo date('d.m.Y', strtotime($domain->expiry_date));
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    echo '</td>';
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
                    
                    // Zone Settings button - quick access to zone settings
                    echo '<a href="' . $this->vars['modulelink'] . '&action=zone-settings&zone_id=' . $domain->zone_id . '" class="btn btn-info btn-sm" title="' . (isset($this->lang['zone_settings']) ? $this->lang['zone_settings'] : 'Zone Settings') . '">';
                    echo '<i class="fa fa-cog"></i> ' . (isset($this->lang['settings']) ? $this->lang['settings'] : 'Settings');
                    echo '</a>';
                    
                    // SSL Status button - quick SSL check
                    echo '<button type="button" class="btn btn-success btn-sm check-ssl-btn" data-zone-id="' . $domain->zone_id . '" data-domain="' . htmlspecialchars($domain->domain) . '" title="' . (isset($this->lang['check_ssl']) ? $this->lang['check_ssl'] : 'Check SSL Status') . '">';
                    echo '<i class="fa fa-lock"></i> SSL';
                    echo '</button>';
                    
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
}
}
