<?php
/**
 * Admin Zone Settings Trait - Zone Settings Management
 *
 * @package     CloudflareManager
 * @author      Ali Çömez / Slaweally
 * @copyright   Copyright (c) 2025, Megabre.com
 * @version     1.0.4
 */

namespace CloudflareManager;

use WHMCS\Database\Capsule;
use Exception;

if (!trait_exists('CloudflareManager\AdminZoneSettings')) {
trait AdminZoneSettings {
    /**
     * Display Zone Settings
     */
    protected function displayZoneSettings() {
        if (!isset($_GET['zone_id'])) {
            echo '<div class="alert alert-danger">Zone ID is required</div>';
            return;
        }
        
        $zoneId = $_GET['zone_id'];
        
        try {
            // Get zone details
            $zone = $this->api->getZone($zoneId);
            
            // Get zone settings
            $sslSettings = $this->api->getSSLStatus($zoneId);
            $zoneSettings = $this->api->getZoneSettings($zoneId);
            
            // Parse settings
            $settingsArray = [];
            if (is_array($zoneSettings)) {
                foreach ($zoneSettings as $setting) {
                    if (isset($setting['id']) && isset($setting['value'])) {
                        $settingsArray[$setting['id']] = $setting;
                    }
                }
            }
            
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">';
            echo '<h3 class="panel-title">' . (isset($this->lang['zone_settings']) ? $this->lang['zone_settings'] : 'Zone Settings') . ' - ' . htmlspecialchars($zone['name']) . '</h3>';
            echo '</div>';
            echo '<div class="panel-body">';
            
            // Zone Information
            echo '<div class="row">';
            echo '<div class="col-md-6">';
            echo '<h4>' . (isset($this->lang['zone_info']) ? $this->lang['zone_info'] : 'Zone Information') . '</h4>';
            echo '<table class="table table-striped">';
            echo '<tr><th>' . (isset($this->lang['domain']) ? $this->lang['domain'] : 'Domain') . '</th><td>' . htmlspecialchars($zone['name']) . '</td></tr>';
            echo '<tr><th>' . (isset($this->lang['status']) ? $this->lang['status'] : 'Status') . '</th><td><span class="label label-' . (strtolower($zone['status']) == 'active' ? 'success' : 'default') . '">' . htmlspecialchars($zone['status']) . '</span></td></tr>';
            echo '<tr><th>Zone ID</th><td><code>' . htmlspecialchars($zone['id']) . '</code></td></tr>';
            if (isset($zone['plan']['name'])) {
                echo '<tr><th>' . (isset($this->lang['plan_type']) ? $this->lang['plan_type'] : 'Plan') . '</th><td>' . htmlspecialchars($zone['plan']['name']) . '</td></tr>';
            }
            if (isset($zone['name_servers']) && is_array($zone['name_servers'])) {
                echo '<tr><th>' . (isset($this->lang['nameservers']) ? $this->lang['nameservers'] : 'Nameservers') . '</th><td>' . implode('<br>', array_map('htmlspecialchars', $zone['name_servers'])) . '</td></tr>';
            }
            echo '</table>';
            echo '</div>';
            
            echo '<div class="col-md-6">';
            echo '<h4>' . (isset($this->lang['ssl_tls_settings']) ? $this->lang['ssl_tls_settings'] : 'SSL/TLS Settings') . '</h4>';
            
            // Get SSL/TLS mode from zone settings
            $sslMode = 'off';
            if (isset($settingsArray['ssl']) && isset($settingsArray['ssl']['value'])) {
                $sslMode = $settingsArray['ssl']['value'];
            }
            
            echo '<div class="form-group">';
            echo '<label for="zone_ssl_mode">' . (isset($this->lang['ssl_tls_mode']) ? $this->lang['ssl_tls_mode'] : 'SSL/TLS Mode') . '</label>';
            echo '<select class="form-control" id="zone_ssl_mode" onchange="updateZoneSettingFromPage(\'ssl\', this.value, \'' . $zoneId . '\')">';
            echo '<option value="off" ' . ($sslMode == 'off' ? 'selected' : '') . '>' . (isset($this->lang['ssl_off']) ? $this->lang['ssl_off'] : 'Off (not secure)') . '</option>';
            echo '<option value="flexible" ' . ($sslMode == 'flexible' ? 'selected' : '') . '>' . (isset($this->lang['ssl_flexible']) ? $this->lang['ssl_flexible'] : 'Flexible') . '</option>';
            echo '<option value="full" ' . ($sslMode == 'full' ? 'selected' : '') . '>' . (isset($this->lang['ssl_full']) ? $this->lang['ssl_full'] : 'Full') . '</option>';
            echo '<option value="strict" ' . ($sslMode == 'strict' ? 'selected' : '') . '>' . (isset($this->lang['ssl_strict']) ? $this->lang['ssl_strict'] : 'Full (Strict)') . '</option>';
            echo '</select>';
            echo '<p class="help-block">' . (isset($this->lang['ssl_mode_desc']) ? $this->lang['ssl_mode_desc'] : 'Select SSL/TLS encryption mode for your zone.') . '</p>';
            echo '</div>';
            
            // SSL Verification Status (read-only info)
            if (isset($sslSettings['status']) || isset($sslSettings['verification_status']) || isset($sslSettings['certificate_status'])) {
                echo '<table class="table table-striped">';
                if (isset($sslSettings['status'])) {
                    $sslStatus = strtolower($sslSettings['status']);
                    echo '<tr><th>' . (isset($this->lang['ssl_verification_status']) ? $this->lang['ssl_verification_status'] : 'Verification Status') . '</th><td><span class="label label-' . ($sslStatus == 'active' ? 'success' : 'warning') . '">' . htmlspecialchars($sslSettings['status']) . '</span></td></tr>';
                }
                if (isset($sslSettings['verification_status'])) {
                    echo '<tr><th>Verification Status</th><td>' . htmlspecialchars($sslSettings['verification_status']) . '</td></tr>';
                }
                if (isset($sslSettings['certificate_status'])) {
                    echo '<tr><th>Certificate Status</th><td>' . htmlspecialchars($sslSettings['certificate_status']) . '</td></tr>';
                }
                echo '</table>';
            }
            
            echo '</div>';
            echo '</div>';
            
            echo '<hr>';
            
            // Zone Settings Controls
            echo '<div class="row">';
            echo '<div class="col-md-12">';
            echo '<h4>' . (isset($this->lang['zone_settings_controls']) ? $this->lang['zone_settings_controls'] : 'Zone Settings Controls') . '</h4>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="row">';
            echo '<div class="col-md-6">';
            
            // Developer Mode
            $devModeValue = isset($settingsArray['development_mode']) ? $settingsArray['development_mode']['value'] : 'off';
            echo '<div class="form-group">';
            echo '<label>' . (isset($this->lang['developer_mode']) ? $this->lang['developer_mode'] : 'Developer Mode') . '</label>';
            echo '<div class="checkbox">';
            echo '<label>';
            echo '<input type="checkbox" id="zone_dev_mode" ' . ($devModeValue == 'on' ? 'checked' : '') . ' onchange="updateZoneSettingFromPage(\'development_mode\', this.checked, \'' . $zoneId . '\')">';
            echo (isset($this->lang['enable_developer_mode']) ? $this->lang['enable_developer_mode'] : 'Enable Developer Mode');
            echo '</label>';
            echo '</div>';
            echo '<p class="help-block">' . (isset($this->lang['developer_mode_desc']) ? $this->lang['developer_mode_desc'] : 'Temporarily bypass Cloudflare cache. Automatically expires after 3 hours.') . '</p>';
            echo '</div>';
            
            // Security Level
            $securityLevel = isset($settingsArray['security_level']) ? $settingsArray['security_level']['value'] : 'medium';
            echo '<div class="form-group">';
            echo '<label for="zone_security_level">' . (isset($this->lang['security_level']) ? $this->lang['security_level'] : 'Security Level') . '</label>';
            echo '<select class="form-control" id="zone_security_level" onchange="updateZoneSettingFromPage(\'security_level\', this.value, \'' . $zoneId . '\')">';
            echo '<option value="off" ' . ($securityLevel == 'off' ? 'selected' : '') . '>' . (isset($this->lang['security_off']) ? $this->lang['security_off'] : 'Off') . '</option>';
            echo '<option value="essentially_off" ' . ($securityLevel == 'essentially_off' ? 'selected' : '') . '>' . (isset($this->lang['security_essentially_off']) ? $this->lang['security_essentially_off'] : 'Essentially Off') . '</option>';
            echo '<option value="low" ' . ($securityLevel == 'low' ? 'selected' : '') . '>' . (isset($this->lang['security_low']) ? $this->lang['security_low'] : 'Low') . '</option>';
            echo '<option value="medium" ' . ($securityLevel == 'medium' ? 'selected' : '') . '>' . (isset($this->lang['security_medium']) ? $this->lang['security_medium'] : 'Medium') . '</option>';
            echo '<option value="high" ' . ($securityLevel == 'high' ? 'selected' : '') . '>' . (isset($this->lang['security_high']) ? $this->lang['security_high'] : 'High') . '</option>';
            echo '<option value="under_attack" ' . ($securityLevel == 'under_attack' ? 'selected' : '') . '>' . (isset($this->lang['security_under_attack']) ? $this->lang['security_under_attack'] : 'I\'m Under Attack!') . '</option>';
            echo '</select>';
            echo '<p class="help-block">' . (isset($this->lang['security_level_desc']) ? $this->lang['security_level_desc'] : 'Set the security level for the zone. "I\'m Under Attack!" mode provides maximum protection.') . '</p>';
            echo '</div>';
            
            echo '</div>';
            echo '<div class="col-md-6">';
            
            // Pause Cloudflare
            $isPaused = isset($zone['paused']) ? $zone['paused'] : false;
            echo '<div class="form-group">';
            echo '<label>' . (isset($this->lang['pause_cloudflare']) ? $this->lang['pause_cloudflare'] : 'Pause Cloudflare') . '</label>';
            echo '<div class="checkbox">';
            echo '<label>';
            echo '<input type="checkbox" id="zone_pause" ' . ($isPaused ? 'checked' : '') . ' onchange="togglePauseZoneFromPage(this.checked, \'' . $zoneId . '\')">';
            echo (isset($this->lang['pause_cloudflare_desc']) ? $this->lang['pause_cloudflare_desc'] : 'Pause Cloudflare for this zone');
            echo '</label>';
            echo '</div>';
            echo '<p class="help-block">' . (isset($this->lang['pause_cloudflare_help']) ? $this->lang['pause_cloudflare_help'] : 'When paused, Cloudflare will stop proxying traffic but DNS will continue to work.') . '</p>';
            echo '</div>';
            
            // Remove from Cloudflare
            echo '<div class="form-group">';
            echo '<label>' . (isset($this->lang['remove_from_cloudflare']) ? $this->lang['remove_from_cloudflare'] : 'Remove from Cloudflare') . '</label>';
            echo '<button type="button" class="btn btn-danger" onclick="removeZoneFromPage(\'' . $zoneId . '\', \'' . htmlspecialchars($zone['name']) . '\')">';
            echo '<i class="fa fa-trash"></i> ' . (isset($this->lang['remove_zone']) ? $this->lang['remove_zone'] : 'Remove Zone from Cloudflare');
            echo '</button>';
            echo '<p class="help-block text-danger">' . (isset($this->lang['remove_zone_warning']) ? $this->lang['remove_zone_warning'] : 'WARNING: This will permanently delete the zone from Cloudflare. This action cannot be undone!') . '</p>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            
            echo '<hr>';
            
            echo '<div class="row margin-top-20">';
            echo '<div class="col-md-12">';
            echo '<a href="' . $this->vars['modulelink'] . '&action=domains" class="btn btn-default">';
            echo '<i class="fa fa-arrow-left"></i> ' . (isset($this->lang['back']) ? $this->lang['back'] : 'Back to Domains');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            
            // Add JavaScript for zone settings
            echo '<script>
            function updateZoneSettingFromPage(setting, value, zoneId) {
                var btn = jQuery("#zone_" + setting.replace("_", "_"));
                var originalValue = btn.val ? btn.val() : (btn.prop("checked") ? true : false);
                
                jQuery.ajax({
                    url: "' . $this->vars['modulelink'] . '&ajax=update_zone_setting&zone_id=" + zoneId + "&setting=" + setting + "&value=" + encodeURIComponent(value) + "&csrf=' . $this->csrfToken . '",
                    type: "POST",
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            alert("Setting updated successfully!");
                        } else {
                            alert("Error: " + (response.message || "Unknown error"));
                            // Revert
                            if (btn.prop) {
                                if (btn.prop("checked") !== undefined) {
                                    btn.prop("checked", !originalValue);
                                } else {
                                    btn.val(originalValue);
                                }
                            }
                        }
                    },
                    error: function() {
                        alert("Error updating setting. Please try again.");
                        // Revert
                        if (btn.prop) {
                            if (btn.prop("checked") !== undefined) {
                                btn.prop("checked", !originalValue);
                            } else {
                                btn.val(originalValue);
                            }
                        }
                    }
                });
            }
            
            function togglePauseZoneFromPage(pause, zoneId) {
                if (!confirm(pause ? "Are you sure you want to pause Cloudflare for this zone?" : "Are you sure you want to unpause Cloudflare for this zone?")) {
                    jQuery("#zone_pause").prop("checked", !pause);
                    return;
                }
                
                jQuery.ajax({
                    url: "' . $this->vars['modulelink'] . '&ajax=" + (pause ? "pause_zone" : "unpause_zone") + "&zone_id=" + zoneId + "&csrf=' . $this->csrfToken . '",
                    type: "POST",
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            alert("Zone " + (pause ? "paused" : "unpaused") + " successfully!");
                        } else {
                            alert("Error: " + (response.message || "Unknown error"));
                            jQuery("#zone_pause").prop("checked", !pause);
                        }
                    },
                    error: function() {
                        alert("Error updating zone. Please try again.");
                        jQuery("#zone_pause").prop("checked", !pause);
                    }
                });
            }
            
            function removeZoneFromPage(zoneId, domain) {
                if (!confirm("WARNING: This will permanently delete \\"" + domain + "\\" from Cloudflare. This action cannot be undone!\\n\\nAre you absolutely sure?")) {
                    return;
                }
                
                if (!confirm("Final confirmation: Delete \\"" + domain + "\\" from Cloudflare?")) {
                    return;
                }
                
                jQuery.ajax({
                    url: "' . $this->vars['modulelink'] . '&ajax=delete_zone&zone_id=" + zoneId + "&csrf=' . $this->csrfToken . '",
                    type: "POST",
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            alert("Zone deleted successfully!");
                            window.location.href = "' . $this->vars['modulelink'] . '&action=domains";
                        } else {
                            alert("Error: " + (response.message || "Unknown error"));
                        }
                    },
                    error: function() {
                        alert("Error deleting zone. Please try again.");
                    }
                });
            }
            </script>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error loading zone settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
}
