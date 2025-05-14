<?php
/**
 * Cloudflare Manager - English Language File
 */

// General
$_LANG['cloudflare_manager'] = "Cloudflare Manager";
$_LANG['error'] = "Error";
$_LANG['success'] = "Success";
$_LANG['save'] = "Save";
$_LANG['cancel'] = "Cancel";
$_LANG['close'] = "Close";
$_LANG['edit'] = "Edit";
$_LANG['delete'] = "Delete";
$_LANG['yes'] = "Yes";
$_LANG['no'] = "No";
$_LANG['automatic'] = "Automatic";

// Welcome Modal
$_LANG['welcome_to_cloudflare_manager'] = "Welcome to Cloudflare Manager";
$_LANG['about'] = "About";
$_LANG['got_it'] = "Got it!";
$_LANG['dont_show_again'] = "Don't show this again";

// Main Menu
$_LANG['overview'] = "Overview";
$_LANG['domains'] = "Domains";
$_LANG['dns_management'] = "DNS Management";
$_LANG['settings'] = "Settings";

// Domain List
$_LANG['cloudflare_domains'] = "Cloudflare Domains";
$_LANG['domain'] = "Domain";
$_LANG['domain_name'] = "Domain Name";
$_LANG['status'] = "Status";
$_LANG['active'] = "Active";
$_LANG['inactive'] = "Inactive";
$_LANG['created_on'] = "Created On";
$_LANG['modified_on'] = "Modified On";
$_LANG['expiry_date'] = "Expiry Date";
$_LANG['ssl_status'] = "SSL Status";
$_LANG['plan_type'] = "Plan Type";
$_LANG['no_domains_found'] = "No domains found.";
$_LANG['select_domain'] = "Select Domain";
$_LANG['select_domain_to_view_dns'] = "Please select a domain to view DNS records.";
$_LANG['your_domains'] = "Your Domains";
$_LANG['registration_date'] = "Registration Date";
$_LANG['domain_not_found'] = "Domain not found.";
$_LANG['registrar'] = "Registrar";
$_LANG['nameservers'] = "Nameservers";
$_LANG['original_registrar'] = "Original Registrar";
$_LANG['original_dnshost'] = "Original DNS Host";

// DNS Management
$_LANG['dns_records'] = "DNS Records";
$_LANG['dns_records_for'] = "DNS Records for";
$_LANG['type'] = "Type";
$_LANG['name'] = "Name";
$_LANG['content'] = "Content";
$_LANG['ttl'] = "TTL";
$_LANG['proxied'] = "Proxied";
$_LANG['priority'] = "Priority";
$_LANG['priority_tip'] = "Used for MX and SRV records (lower number = higher priority)";
$_LANG['add_new_record'] = "Add New Record";
$_LANG['add_dns_record'] = "Add DNS Record";
$_LANG['edit_dns_record'] = "Edit DNS Record";
$_LANG['dns_name_tip'] = "Use @ to denote the root domain.";
$_LANG['proxy_tip'] = "When enabled, Cloudflare will proxy traffic through their servers.";
$_LANG['dns_record_added'] = "DNS record added successfully.";
$_LANG['dns_record_updated'] = "DNS record updated successfully.";
$_LANG['dns_record_deleted'] = "DNS record deleted successfully.";
$_LANG['dns_record_add_error'] = "Error adding DNS record";
$_LANG['dns_record_update_error'] = "Error updating DNS record";
$_LANG['dns_record_delete_error'] = "Error deleting DNS record";
$_LANG['confirm_delete_dns'] = "Are you sure you want to delete this DNS record?";
$_LANG['no_dns_records'] = "No DNS records found.";

// Actions
$_LANG['actions'] = "Actions";
$_LANG['details'] = "Details";
$_LANG['details_for'] = "Details for";
$_LANG['purge_cache'] = "Purge Cache";
$_LANG['cache_purged'] = "Cache purged successfully.";
$_LANG['cache_purge_error'] = "Error purging cache";
$_LANG['cache_cleared'] = "Cache cleared successfully.";
$_LANG['cache_clear_error'] = "Error clearing cache";
$_LANG['confirm_purge_cache'] = "Are you sure you want to purge the entire cache? This action cannot be undone!";
$_LANG['sync_domains'] = "Sync Domains";
$_LANG['sync_completed'] = "%s domains synchronized successfully.";
$_LANG['sync_error'] = "Error during domain synchronization";

// Settings
$_LANG['client_permissions'] = "Client Permissions";
$_LANG['client_permissions_desc'] = "Select what features clients can see in Cloudflare Manager.";
$_LANG['view_domain_details'] = "View Domain Details";
$_LANG['view_dns_records'] = "View DNS Records";
$_LANG['view_ssl_status'] = "View SSL Status";
$_LANG['view_cache_status'] = "View Cache Status";
$_LANG['save_changes'] = "Save Changes";
$_LANG['settings_updated'] = "Settings updated successfully.";
$_LANG['settings_update_error'] = "Error updating settings";
$_LANG['database_info'] = "Database Information";
$_LANG['total_domains'] = "Total Domains";
$_LANG['total_dns_records'] = "Total DNS Records";
$_LANG['cache_items'] = "Cached Items";
$_LANG['settings_count'] = "Settings Count";
$_LANG['database_error'] = "Database error";
$_LANG['about_module'] = "About Module";
$_LANG['module_description'] = "This module allows you to manage your Cloudflare domains and DNS records.";
$_LANG['module_description_detailed'] = "Cloudflare Manager seamlessly integrates your WHMCS installation with Cloudflare, allowing you to manage your domains, DNS records, SSL settings, and cache from a single dashboard. Suitable for hosting companies and domain resellers who want to provide their clients with efficient Cloudflare management.";
$_LANG['module_version'] = "Module Version";
$_LANG['module_author'] = "Author";
$_LANG['developed_by'] = "Developed by";

// Module Features
$_LANG['key_features'] = "Key Features";
$_LANG['feature_domain_management'] = "Easily view and sync all your Cloudflare domains";
$_LANG['feature_dns_management'] = "Full DNS management with support for all record types";
$_LANG['feature_ssl_monitoring'] = "Monitor SSL certificate status across all domains";
$_LANG['feature_cache_purging'] = "Purge cache with a single click when needed";
$_LANG['feature_client_access'] = "Client access to manage their own domains";

// Getting Started
$_LANG['getting_started'] = "Getting Started";
$_LANG['getting_started_sync'] = "Start by synchronizing your domains from Cloudflare";
$_LANG['getting_started_dns'] = "Manage DNS records for each domain";
$_LANG['getting_started_customize'] = "Customize client permissions in settings";

// Time Units
$_LANG['minutes'] = "Minutes";
$_LANG['hours'] = "Hours";
$_LANG['hour'] = "Hour";
$_LANG['day'] = "Day";

// API and Connection
$_LANG['api_connection_success'] = "Cloudflare API connection successful!";
$_LANG['api_connection_error'] = "Cloudflare API connection error";
$_LANG['check_api_credentials'] = "Please check your API credentials.";
$_LANG['error_fetching_domains'] = "Error fetching domains";
$_LANG['error_fetching_dns'] = "Error fetching DNS records";
$_LANG['error_fetching_details'] = "Error fetching domain details";
$_LANG['csrf_error'] = "Security validation failed. Please refresh the page and try again.";

// Performance
$_LANG['performance_settings'] = "Performance Settings";
$_LANG['api_cache_enabled'] = "Enable API Response Caching";
$_LANG['api_cache_desc'] = "Caching API responses improves performance but may show slightly outdated information";
$_LANG['cache_expiry'] = "Cache Expiry Time (seconds)";
$_LANG['cache_expiry_help'] = "Time to keep API responses in cache (minimum 60 seconds)";
$_LANG['clear_all_cache'] = "Clear All Cache";

// Analytics & Statistics
$_LANG['traffic_stats'] = "Traffic Statistics";
$_LANG['security_stats'] = "Security Statistics";
$_LANG['analytics_data'] = "Analytics Data";
$_LANG['total_requests'] = "Total Requests";
$_LANG['unique_visitors'] = "Unique Visitors";
$_LANG['page_views'] = "Page Views";
$_LANG['security_threats'] = "Security Threats";
$_LANG['data_last_updated'] = "Data Last Updated";
$_LANG['analytics_period'] = "Analytics Period";
$_LANG['last_24_hours'] = "Last 24 Hours";
$_LANG['last_7_days'] = "Last 7 Days";
$_LANG['last_30_days'] = "Last 30 Days";
$_LANG['by_country'] = "By Country";
$_LANG['browser_stats'] = "Browser Statistics";

// Client Side
$_LANG['back_to_domains'] = "Back to Domains";
$_LANG['cache_management'] = "Cache Management";
$_LANG['cache_management_desc'] = "If you have updated your website content, you can purge the Cloudflare cache to ensure visitors see the latest version.";

// Troubleshooting
$_LANG['troubleshooting'] = "Troubleshooting";
$_LANG['tip_api_key'] = "The Global API Key should be correct and full length.";
$_LANG['tip_api_token'] = "Alternatively, you can leave the email field empty and use an API Token.";
$_LANG['tip_token_permissions'] = "If using an API Token, make sure it has 'Zone DNS (Edit)' permission.";
$_LANG['how_to_get_api_key'] = "How to Get Global API Key";
$_LANG['login_to_cloudflare'] = "Log in to your Cloudflare account";
$_LANG['go_to_profile'] = "Click on the profile icon in the top right corner and select 'My Profile'";
$_LANG['go_to_api_tokens'] = "Go to the 'API Tokens' tab";
$_LANG['view_global_key'] = "Click 'View' in the 'Global API Key' section and confirm your password";
$_LANG['how_to_get_api_token'] = "How to Create an API Token";
$_LANG['create_token'] = "Click the 'Create Token' button";
$_LANG['use_edit_zone_template'] = "Select the 'Edit zone DNS' template";
$_LANG['copy_token'] = "Copy the created token";

// Form Validation
$_LANG['missing_required_fields'] = "Please fill in all required fields";
$_LANG['invalid_ipv4_address'] = "Invalid IPv4 address format";
$_LANG['invalid_ipv6_address'] = "Invalid IPv6 address format";
$_LANG['invalid_hostname'] = "Invalid hostname format";
$_LANG['dns_records_synced'] = "DNS records synchronized successfully";
$_LANG['dns_sync_error'] = "Error synchronizing DNS records";
