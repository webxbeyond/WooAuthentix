<?php
// Uninstall cleanup for WooAuthentix (conditional full cleanup based on constant or option)
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

$full = get_option('wooauthentix_full_uninstall');
if ( ! $full ) { return; }

global $wpdb;
$codes_table = $wpdb->prefix . 'wc_authentic_codes';
$logs_table  = $wpdb->prefix . 'wc_authentic_logs';
$wpdb->query("DROP TABLE IF EXISTS $codes_table");
$wpdb->query("DROP TABLE IF EXISTS $logs_table");

delete_option('wooauthentix_db_version');
delete_option('wooauthentix_settings');
// transient cleanup is automatic (expiration) – explicit purge not required
