<?php
/**
 * Admin DNS Manager Trait - DNS Management (Modal-free version)
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.5
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!trait_exists('CloudflareManager\AdminDnsManager')) {
trait AdminDnsManager {
    /**
     * DNS Management - Clean design without modals
     */
    protected function displayDnsManagement() {
        // Handle DNS record operations FIRST before displaying
        if (isset($_POST['add_dns_record']) && isset($_POST['zone_id'])) {
            $this->handleAddDnsRecord();
            return; // Exit after redirect
        } elseif (isset($_POST['update_dns_record']) && isset($_POST['record_id'])) {
            $this->handleUpdateDnsRecord();
            return; // Exit after redirect
        } elseif (isset($_GET['delete_dns']) && isset($_GET['zone_id']) && isset($_GET['record_id'])) {
            $this->handleDeleteDnsRecord();
            return; // Exit after redirect
        }
        
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title"><i class="fa fa-list"></i> ' . (isset($this->lang['dns_management']) ? $this->lang['dns_management'] : 'DNS Management') . '</h3>';
        echo '</div>';
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
                    // Show success/error messages
                    if (isset($_GET['success'])) {
                        echo '<div class="alert alert-success alert-dismissible">';
                        echo '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                        echo '<i class="fa fa-check"></i> ' . htmlspecialchars($_GET['success']);
                        echo '</div>';
                    }
                    if (isset($_GET['error'])) {
                        echo '<div class="alert alert-danger alert-dismissible">';
                        echo '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                        echo '<i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($_GET['error']);
                        echo '</div>';
                    }
                    
                    echo '<div class="row">';
                    echo '<div class="col-md-12">';
                    echo '<h4><i class="fa fa-globe"></i> ' . htmlspecialchars($domain->domain) . ' - ' . (isset($this->lang['dns_records']) ? $this->lang['dns_records'] : 'DNS Records') . '</h4>';
                    echo '</div>';
                    echo '</div>';
                    
                    // Sync and show DNS records
                    $this->dnsManager->syncRecordsByDomain($domainId);
                    $dnsRecords = $this->dnsManager->getDnsRecordsFromDB($domainId);
                    
                    // Show add/edit form if requested
                    $showAddForm = isset($_GET['add']) || (isset($_POST['add_dns_record']) && isset($_GET['error']));
                    $showEditForm = isset($_GET['edit']) && isset($_GET['record_id']);
                    $editRecordId = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
                    
                    if ($showAddForm || $showEditForm) {
                        $this->displayDnsForm($domain, $showEditForm ? $editRecordId : 0, $dnsRecords);
                    }
                    
                    // Add new record button
                    if (!$showAddForm && !$showEditForm) {
                        echo '<div class="row" style="margin-bottom: 15px;">';
                        echo '<div class="col-md-12">';
                        echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domainId . '&add=1" class="btn btn-success btn-sm">';
                        echo '<i class="fa fa-plus"></i> ' . (isset($this->lang['add_new_record']) ? $this->lang['add_new_record'] : 'Add New Record');
                        echo '</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    // DNS records table
                    if (count($dnsRecords) > 0) {
                        $hasPriorityRecords = false;
                        foreach ($dnsRecords as $record) {
                            if (isset($record->priority) && !empty($record->priority)) {
                                $hasPriorityRecords = true;
                                break;
                            }
                        }
                        
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered table-striped table-hover">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th style="width:8%;">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . '</th>';
                        echo '<th style="width:20%;">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . '</th>';
                        
                        if ($hasPriorityRecords) {
                            echo '<th style="width:8%;">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</th>';
                        }
                        
                        echo '<th style="width:30%;">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . '</th>';
                        echo '<th style="width:8%;">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</th>';
                        echo '<th style="width:8%;">' . (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied') . '</th>';
                        echo '<th style="width:18%;">' . (isset($this->lang['actions']) ? $this->lang['actions'] : 'Actions') . '</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($dnsRecords as $record) {
                            echo '<tr>';
                            echo '<td><strong>' . htmlspecialchars($record->type) . '</strong></td>';
                            echo '<td style="word-break:break-word;">' . htmlspecialchars($record->name) . '</td>';
                            
                            if ($hasPriorityRecords) {
                                echo '<td>' . (isset($record->priority) ? $record->priority : '-') . '</td>';
                            }
                            
                            $fullContent = $record->content;
                            $displayContent = strlen($fullContent) > 50 ? substr($fullContent, 0, 47) . '...' : $fullContent;
                            echo '<td style="word-break:break-word;" title="' . htmlspecialchars($fullContent) . '">' . htmlspecialchars($displayContent) . '</td>';
                            
                            echo '<td>' . (($record->ttl == 1) ? (isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Auto') : htmlspecialchars($record->ttl)) . '</td>';
                            echo '<td>';
                            if ($record->proxied) {
                                echo '<span class="label label-success">' . (isset($this->lang['yes']) ? $this->lang['yes'] : 'Yes') . '</span>';
                            } else {
                                echo '<span class="label label-default">' . (isset($this->lang['no']) ? $this->lang['no'] : 'No') . '</span>';
                            }
                            echo '</td>';
                            
                            echo '<td>';
                            echo '<div class="btn-group btn-group-sm">';
                            echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domainId . '&edit=1&record_id=' . $record->record_id . '" class="btn btn-primary">';
                            echo '<i class="fa fa-edit"></i> ' . (isset($this->lang['edit']) ? $this->lang['edit'] : 'Edit');
                            echo '</a>';
                            
                            if ($record->type != 'SOA' && !($record->type == 'NS' && strpos($record->name, $domain->domain) !== false)) {
                                echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domainId . '&delete_dns=1&zone_id=' . $domain->zone_id . '&record_id=' . $record->record_id . '&token=' . $this->csrfToken . '" class="btn btn-danger" onclick="return confirm(\'' . (isset($this->lang['confirm_delete_dns']) ? addslashes($this->lang['confirm_delete_dns']) : 'Are you sure you want to delete this DNS record?') . ' ' . addslashes($record->name) . '?\')">';
                                echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['delete']) ? $this->lang['delete'] : 'Delete');
                                echo '</a>';
                            }
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
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
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display DNS form (add/edit)
     */
    protected function displayDnsForm($domain, $recordId = 0, $dnsRecords = []) {
        $isEdit = $recordId > 0;
        $record = null;
        
        if ($isEdit) {
            foreach ($dnsRecords as $r) {
                if ($r->record_id == $recordId) {
                    $record = $r;
                    break;
                }
            }
            if (!$record) {
                echo '<div class="alert alert-danger">Record not found.</div>';
                return;
            }
        }
        
        echo '<div class="panel panel-' . ($isEdit ? 'warning' : 'success') . '">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">';
        echo '<i class="fa fa-' . ($isEdit ? 'edit' : 'plus') . '"></i> ';
        echo $isEdit ? (isset($this->lang['edit_dns_record']) ? $this->lang['edit_dns_record'] : 'Edit DNS Record') : (isset($this->lang['add_dns_record']) ? $this->lang['add_dns_record'] : 'Add DNS Record');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        echo '<form method="post" action="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domain->id . '">';
        echo '<input type="hidden" name="token" value="' . $this->csrfToken . '">';
        echo '<input type="hidden" name="zone_id" value="' . $domain->zone_id . '">';
        echo '<input type="hidden" name="domain_id" value="' . $domain->id . '">';
        if ($isEdit) {
            echo '<input type="hidden" name="update_dns_record" value="1">';
            echo '<input type="hidden" name="record_id" value="' . $recordId . '">';
        } else {
            echo '<input type="hidden" name="add_dns_record" value="1">';
        }
        
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<div class="form-group">';
        echo '<label for="dnsType">' . (isset($this->lang['type']) ? $this->lang['type'] : 'Type') . ' <span class="text-danger">*</span></label>';
        echo '<select class="form-control" id="dnsType" name="type" required onchange="updateDnsFormFields()">';
        $types = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'DNSKEY', 'DS', 'NAPTR', 'SMIMEA', 'SSHFP', 'TLSA', 'URI'];
        foreach ($types as $type) {
            $selected = ($isEdit && $record->type == $type) ? ' selected' : '';
            echo '<option value="' . $type . '"' . $selected . '>' . $type . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-6">';
        echo '<div class="form-group">';
        echo '<label for="dnsName">' . (isset($this->lang['name']) ? $this->lang['name'] : 'Name') . ' <span class="text-danger">*</span></label>';
        echo '<input type="text" class="form-control" id="dnsName" name="name" value="' . ($isEdit ? htmlspecialchars($record->name) : '') . '" required>';
        echo '<p class="help-block">' . (isset($this->lang['dns_name_tip']) ? $this->lang['dns_name_tip'] : 'Use @ for root domain') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<div class="form-group" id="priorityGroup" style="display:none;">';
        echo '<label for="dnsPriority">' . (isset($this->lang['priority']) ? $this->lang['priority'] : 'Priority') . '</label>';
        echo '<input type="number" class="form-control" id="dnsPriority" name="priority" value="' . ($isEdit && isset($record->priority) ? $record->priority : '10') . '" min="0">';
        echo '<p class="help-block">' . (isset($this->lang['priority_tip']) ? $this->lang['priority_tip'] : 'For MX, SRV, URI records') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-6">';
        echo '<div class="form-group">';
        echo '<label for="dnsTtl">' . (isset($this->lang['ttl']) ? $this->lang['ttl'] : 'TTL') . '</label>';
        echo '<select class="form-control" id="dnsTtl" name="ttl">';
        $ttlOptions = [
            1 => isset($this->lang['automatic']) ? $this->lang['automatic'] : 'Automatic',
            120 => '2 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            300 => '5 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            600 => '10 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            1800 => '30 ' . (isset($this->lang['minutes']) ? $this->lang['minutes'] : 'Minutes'),
            3600 => '1 ' . (isset($this->lang['hour']) ? $this->lang['hour'] : 'Hour'),
            7200 => '2 ' . (isset($this->lang['hours']) ? $this->lang['hours'] : 'Hours'),
            86400 => '1 ' . (isset($this->lang['day']) ? $this->lang['day'] : 'Day')
        ];
        foreach ($ttlOptions as $ttl => $label) {
            $selected = ($isEdit && $record->ttl == $ttl) ? ' selected' : '';
            echo '<option value="' . $ttl . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<div class="form-group">';
        echo '<label for="dnsContent">' . (isset($this->lang['content']) ? $this->lang['content'] : 'Content') . ' <span class="text-danger">*</span></label>';
        echo '<input type="text" class="form-control" id="dnsContent" name="content" value="' . ($isEdit ? htmlspecialchars($record->content) : '') . '" required>';
        echo '<p class="help-block" id="contentHelp"></p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<div class="form-group" id="proxyGroup" style="display:none;">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="proxied" id="dnsProxied" value="1"' . ($isEdit && $record->proxied ? ' checked' : '') . '>';
        echo (isset($this->lang['proxied']) ? $this->lang['proxied'] : 'Proxied');
        echo '</label>';
        echo '<p class="help-block">' . (isset($this->lang['proxy_tip']) ? $this->lang['proxy_tip'] : 'Enable Cloudflare proxy') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="panel-footer">';
        echo '<button type="submit" class="btn btn-primary">';
        echo '<i class="fa fa-save"></i> ' . (isset($this->lang['save']) ? $this->lang['save'] : 'Save');
        echo '</button>';
        echo '<a href="' . $this->vars['modulelink'] . '&action=dns&domain_id=' . $domain->id . '" class="btn btn-default">';
        echo '<i class="fa fa-times"></i> ' . (isset($this->lang['cancel']) ? $this->lang['cancel'] : 'Cancel');
        echo '</a>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        // JavaScript for form fields
        echo '<script type="text/javascript">';
        echo 'function updateDnsFormFields() {';
        echo '    var type = jQuery("#dnsType").val();';
        echo '    var priorityTypes = ["MX", "SRV", "URI"];';
        echo '    var proxyTypes = ["A", "AAAA", "CNAME"];';
        echo '    ';
        echo '    if (priorityTypes.indexOf(type) !== -1) {';
        echo '        jQuery("#priorityGroup").show();';
        echo '    } else {';
        echo '        jQuery("#priorityGroup").hide();';
        echo '    }';
        echo '    ';
        echo '    if (proxyTypes.indexOf(type) !== -1) {';
        echo '        jQuery("#proxyGroup").show();';
        echo '    } else {';
        echo '        jQuery("#proxyGroup").hide();';
        echo '        jQuery("#dnsProxied").prop("checked", false);';
        echo '    }';
        echo '    ';
        echo '    var examples = {';
        echo '        "A": "192.168.0.1",';
        echo '        "AAAA": "2001:0db8::1",';
        echo '        "CNAME": "example.com",';
        echo '        "MX": "mail.example.com",';
        echo '        "TXT": "v=spf1 include:_spf.example.com ~all",';
        echo '        "NS": "ns1.example.com"';
        echo '    };';
        echo '    ';
        echo '    if (examples[type]) {';
        echo '        jQuery("#contentHelp").text("Example: " + examples[type]);';
        echo '        jQuery("#dnsContent").attr("placeholder", examples[type]);';
        echo '    } else {';
        echo '        jQuery("#contentHelp").text("");';
        echo '        jQuery("#dnsContent").attr("placeholder", "");';
        echo '    }';
        echo '}';
        echo 'jQuery(document).ready(function() { updateDnsFormFields(); });';
        echo '</script>';
    }
    
    /**
     * Handle add DNS record
     */
    protected function handleAddDnsRecord() {
        if (!isset($_POST['token']) || $_POST['token'] != $this->csrfToken) {
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&error=" . urlencode("Invalid CSRF token"));
            exit;
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            $type = $_POST['type'];
            $name = $_POST['name'];
            $content = $_POST['content'];
            $ttl = isset($_POST['ttl']) ? (int)$_POST['ttl'] : 1;
            $priority = isset($_POST['priority']) && !empty($_POST['priority']) ? (int)$_POST['priority'] : null;
            $proxied = isset($_POST['proxied']) && $_POST['proxied'] == '1';
            
            if (!$this->dnsManager) {
                throw new Exception("DNS Manager not available");
            }
            
            // Prepare data array for createDnsRecord method
            $data = [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'ttl' => $ttl,
                'proxied' => $proxied ? '1' : '0',
            ];
            if ($priority !== null) {
                $data['priority'] = $priority;
            }
            
            $result = $this->dnsManager->createDnsRecord($zoneId, $data);
            
            if ($result['success']) {
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&success=" . urlencode($result['message']));
            } else {
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&add=1&error=" . urlencode($result['message']));
            }
            exit;
        } catch (Exception $e) {
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&add=1&error=" . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /**
     * Handle update DNS record
     */
    protected function handleUpdateDnsRecord() {
        if (!isset($_POST['token']) || $_POST['token'] != $this->csrfToken) {
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&error=" . urlencode("Invalid CSRF token"));
            exit;
        }
        
        try {
            $zoneId = $_POST['zone_id'];
            $recordId = $_POST['record_id'];
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            $type = $_POST['type'];
            $name = $_POST['name'];
            $content = $_POST['content'];
            $ttl = isset($_POST['ttl']) ? (int)$_POST['ttl'] : 1;
            $priority = isset($_POST['priority']) && !empty($_POST['priority']) ? (int)$_POST['priority'] : null;
            $proxied = isset($_POST['proxied']) && $_POST['proxied'] == '1';
            
            if (!$this->dnsManager) {
                throw new Exception("DNS Manager not available");
            }
            
            // Prepare data array for updateDnsRecord method
            $data = [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'ttl' => $ttl,
                'proxied' => $proxied ? '1' : '0',
            ];
            if ($priority !== null) {
                $data['priority'] = $priority;
            }
            
            $result = $this->dnsManager->updateDnsRecord($zoneId, $recordId, $data);
            
            if ($result['success']) {
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&success=" . urlencode($result['message']));
            } else {
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&edit=1&record_id=" . $recordId . "&error=" . urlencode($result['message']));
            }
            exit;
        } catch (Exception $e) {
            $domainId = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : (isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0);
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&edit=1&record_id=" . (isset($_POST['record_id']) ? $_POST['record_id'] : '') . "&error=" . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /**
     * Handle delete DNS record
     */
    protected function handleDeleteDnsRecord() {
        if (!isset($_GET['token']) || $_GET['token'] != $this->csrfToken) {
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . (isset($_GET['domain_id']) ? $_GET['domain_id'] : '') . "&error=" . urlencode("Invalid CSRF token"));
            exit;
        }
        
        try {
            $zoneId = $_GET['zone_id'];
            $recordId = $_GET['record_id'];
            
            if (!$this->dnsManager) {
                throw new Exception("DNS Manager not available");
            }
            
            $result = $this->dnsManager->deleteDnsRecord($zoneId, $recordId);
            
            if ($result['success']) {
                $domainId = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . $domainId . "&success=" . urlencode($result['message']));
            } else {
                header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . (isset($_GET['domain_id']) ? $_GET['domain_id'] : '') . "&error=" . urlencode($result['message']));
            }
            exit;
        } catch (Exception $e) {
            header("Location: " . $this->vars['modulelink'] . "&action=dns&domain_id=" . (isset($_GET['domain_id']) ? $_GET['domain_id'] : '') . "&error=" . urlencode($e->getMessage()));
            exit;
        }
    }
}
}
