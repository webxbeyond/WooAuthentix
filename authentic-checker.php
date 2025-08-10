<?php
/*
Plugin Name: WooAuthentix: Product Code Verification
Description: Manage, assign, and verify unique authenticity codes for WooCommerce products with privacy, logging, security controls, and a generic code pool.
Version: 2.3.0
Author: Anis Afifi
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wooauthentix
Domain Path: /languages
Requires at least: 6.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 8.9
*/

defined('ABSPATH') || exit;
if ( ! defined('WC_APC_PLUGIN_FILE') ) { define('WC_APC_PLUGIN_FILE', __FILE__ ); }

// Plugin constants
const WC_APC_VERSION = '2.3.0'; // keep in sync with header Version & readme.txt Stable tag
const WC_APC_DB_VERSION = '2.3.0';
const WC_APC_TABLE = 'wc_authentic_codes';
const WC_APC_OPTION_SETTINGS = 'wooauthentix_settings';
const WC_APC_RATE_LIMIT_MAX = 20; // attempts
const WC_APC_RATE_LIMIT_WINDOW = 300; // seconds
const WC_APC_LOG_TABLE = 'wc_authentic_logs';
const WC_APC_CRON_EVENT = 'wooauthentix_prune_logs';
const WC_APC_ITEM_META_KEY = '_wc_apc_code';
// Code length bounds (even hex characters) – update here if requirements change.
const WC_APC_CODE_MIN_LENGTH = 8;
const WC_APC_CODE_MAX_LENGTH = 32;

// Declare WooCommerce High-Performance Order Storage compatibility.
add_action('before_woocommerce_init', function(){
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Load translations
add_action('plugins_loaded', function(){
    load_plugin_textdomain('wooauthentix', false, dirname(plugin_basename(__FILE__)).'/languages');
});

// Activation: create/update tables & schedule cron
register_activation_hook(__FILE__, 'wooauthentix_activate');
function wooauthentix_activate(){
    global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $codes_table = $wpdb->prefix.WC_APC_TABLE;
    $logs_table = $wpdb->prefix.WC_APC_LOG_TABLE;
    $sql_codes = "CREATE TABLE $codes_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NULL DEFAULT NULL,
        code VARCHAR(64) NOT NULL,
        status TINYINT UNSIGNED NOT NULL DEFAULT 0,
        is_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
        order_id BIGINT UNSIGNED NULL,
        order_item_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        assigned_at DATETIME NULL,
        verified_at DATETIME NULL,
        used_at DATETIME NULL,
        qr_label_generated TINYINT UNSIGNED NOT NULL DEFAULT 0,
        qr_generated_at DATETIME NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY code (code),
    KEY product_status (product_id,status),
    KEY status_idx (status),
    KEY order_lookup (order_id,order_item_id),
    KEY qr_label_idx (qr_label_generated)
    ) $charset;";
    $sql_logs = "CREATE TABLE $logs_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(64) NOT NULL,
        ip VARCHAR(128) NULL,
        result VARCHAR(32) NOT NULL,
        user_agent VARCHAR(500) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY code_idx (code),
        KEY result_idx (result),
        KEY created_idx (created_at)
    ) $charset;";
    dbDelta($sql_codes);
    dbDelta($sql_logs);
    update_option('wooauthentix_db_version', WC_APC_DB_VERSION); // store DB version
    // Schedule log pruning if not already
    if (!wp_next_scheduled(WC_APC_CRON_EVENT)) {
        wp_schedule_event(time()+3600, 'daily', WC_APC_CRON_EVENT);
    }
    wooauthentix_run_migrations('install');
    // Ensure custom rewrites registered by modules (e.g., pretty verification URL) are flushed.
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
    }
}

// DB migration check (runs on admin_init for performance reasons only when version drift)
add_action('admin_init', function(){
    $stored = get_option('wooauthentix_db_version');
    if ($stored === WC_APC_DB_VERSION) return;
    // Future migrations could go here; currently just ensure schema
    wooauthentix_activate(); // ensure tables
});

/**
 * Run incremental migrations based on stored version.
 * @param string $context 'install' or 'upgrade'
 */
function wooauthentix_run_migrations($context='upgrade'){
    global $wpdb; $from = get_option('wooauthentix_db_version');
    // 2.1.0: allow generic pool by making product_id nullable
    if($from && version_compare($from,'2.1.0','<')){
        $table = $wpdb->prefix.WC_APC_TABLE;
        // Suppress errors if already nullable
        $wpdb->query("ALTER TABLE $table MODIFY product_id BIGINT UNSIGNED NULL DEFAULT NULL");
        update_option('wooauthentix_db_version','2.1.0');
    }
    // 2.2.0: add qr label generation tracking columns
    $current = get_option('wooauthentix_db_version');
    if($current && version_compare($current,'2.2.0','<')){
        $table = $wpdb->prefix.WC_APC_TABLE;
        // Add columns if missing
        $wpdb->query("ALTER TABLE $table ADD COLUMN qr_label_generated TINYINT UNSIGNED NOT NULL DEFAULT 0");
        $wpdb->query("ALTER TABLE $table ADD COLUMN qr_generated_at DATETIME NULL");
        update_option('wooauthentix_db_version','2.2.0');
    }
    // 2.2.1: add index on qr_label_generated for label filtering performance
    $current = get_option('wooauthentix_db_version');
    if($current && version_compare($current,'2.2.1','<')){
        $table = $wpdb->prefix.WC_APC_TABLE;
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
        $has_idx = false; foreach($indexes as $ix){ if( isset($ix['Key_name']) && $ix['Key_name'] === 'qr_label_idx'){ $has_idx=true; break; } }
        if(!$has_idx){
            $wpdb->query("ALTER TABLE $table ADD KEY qr_label_idx (qr_label_generated)");
        }
        update_option('wooauthentix_db_version','2.2.1');
    }
    do_action('wooauthentix_after_migrations', $from, WC_APC_DB_VERSION, $context);
}

function wc_apc_get_settings($refresh = false) {
    // Lightweight in-request cache to avoid repeat option fetch/normalization.
    if ( ! $refresh && isset($GLOBALS['wc_apc_settings_cache']) && is_array($GLOBALS['wc_apc_settings_cache']) ) {
        return $GLOBALS['wc_apc_settings_cache'];
    }
    $defaults = [
        'show_buyer_name'         => 0,
        'mask_buyer_name'         => 1,
        'show_purchase_date'      => 0,
        'enable_rate_limit'       => 1,
        'rate_limit_max'          => WC_APC_RATE_LIMIT_MAX,
        'rate_limit_window'       => WC_APC_RATE_LIMIT_WINDOW,
        'enable_logging'          => 1,
        'log_retention_days'      => 90,
        'code_length'             => 12,
        'low_stock_threshold'     => 20,
        'low_stock_notify'        => 1,
        'preprinted_mode'         => 0,
        'verification_page_url'   => '',
        'label_brand_text'        => '',
        'label_logo_id'           => 0,
        'label_qr_size'           => 110,
        'label_columns'           => 0,
        'label_margin'            => 6,
        'label_logo_overlay'      => 0,
        'label_logo_overlay_scale'=> 28,
        'enable_server_side_qr'   => 0,
        'label_enable_border'     => 1,
        'label_border_size'       => 1,
        'label_show_brand'        => 1,
        'label_show_logo'         => 1,
        'label_show_code'         => 1,
        'label_show_site'         => 1,
    // Verification design defaults
    'verification_heading'              => __('Product Authenticity','wooauthentix'),
    'verification_msg_first_time'       => __('First-time verification: product authenticated.','wooauthentix'),
    'verification_msg_already_verified' => __('This authenticity code has already been verified.','wooauthentix'),
    'verification_msg_unassigned'       => __('Invalid code or not yet assigned to a purchase.','wooauthentix'),
    'verification_msg_invalid_code'     => __('Invalid code. This product may not be authentic.','wooauthentix'),
    'verification_msg_invalid_format'   => __('Invalid code format.','wooauthentix'),
    'verification_msg_rate_limited'     => __('Rate limit exceeded. Please wait and try again.','wooauthentix'),
    'verification_container_width'      => 500,
    'verification_bg_color'             => '#f5f5f7',
    'verification_show_product_image'   => 1,
    'verification_custom_css'           => '',
    'verification_custom_js'            => '',
    ];
    $settings = wp_parse_args( get_option( WC_APC_OPTION_SETTINGS, [] ), $defaults );
    // Normalize length once early; load helper if not yet loaded.
    if ( ! function_exists( 'wc_apc_normalize_code_length' ) ) {
        $util = plugin_dir_path( __FILE__ ) . 'includes/module-utils.php';
        if ( file_exists( $util ) ) { require_once $util; }
    }
    if ( function_exists( 'wc_apc_normalize_code_length' ) ) {
        $settings['code_length'] = wc_apc_normalize_code_length( $settings['code_length'] );
    }
    // Store cache
    $GLOBALS['wc_apc_settings_cache'] = $settings;
    return $settings;
}

/** Clear cached settings (e.g., after update_option). */
function wc_apc_flush_settings_cache(){ unset($GLOBALS['wc_apc_settings_cache']); }

// (Legacy wc_apc_settings_page removed; now in module.)

// Helper: Render product dropdown
// (Legacy product dropdown helper removed; module provides its own.)

// Admin page callback
// (Legacy codes admin page removed; now in module.)

// Manual assign / override codes page
// (Legacy assign codes page removed; now in module.)

// (Code generation function now provided by module-codes)

// (Assignment logic moved to module-codes)

// Removed legacy init GET marking for security (now only via shortcode logic)

// (Shortcode moved to module-verification)

// Shared verification logic
/**
 * Core verification routine.
 * @param string $code Uppercase code.
 * @param string $context 'shortcode'|'rest' for tracking source.
 * @return array Result meta including success flag and rendered HTML snippet.
 */
// (Verification function moved)

// (Rate limit helpers moved)

// REST API endpoint
// (REST route moved)

// (Logging moved)

// (Log pruning cron moved)

// Deactivation cleanup schedule (not uninstall)
register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook(WC_APC_CRON_EVENT);
});

// Logs admin page
// (Legacy logs page removed; now in module.)

// WP-CLI commands
// (CLI class now exclusively lives in module-cli.php)

// Dashboard page
// (Legacy dashboard page removed; now in module.)

/**
 * Notify admin when unassigned code inventory for a product is below threshold.
 * Uses transient to rate-limit alerts per product (6h).
 * @param int $product_id
 */
// (Low stock notify moved)

// -----------------------------------------------------------------------------
// Plugin action links (Settings shortcut on Plugins page)
// -----------------------------------------------------------------------------
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    if (current_user_can('manage_woocommerce')) {
        $settings_url = admin_url('admin.php?page=wc-apc-settings');
        array_unshift($links, '<a href="'.esc_url($settings_url).'">'.esc_html__('Settings','wooauthentix').'</a>');
    }
    return $links;
});

// Uninstall safeguard (if uninstall.php present WordPress will call that) – placeholder flag.

// -----------------------------------------------------------------------------
// QR Labels Page (printable sheet of QR codes for preprinted mode)
// (All admin page asset enqueues & AJAX handlers moved into WC_APC_Admin_Pages_Module and WC_APC_Labels_Module.)

// -----------------------------------------------------------------------------
// Module loader bootstrap
// -----------------------------------------------------------------------------
add_action('plugins_loaded', function(){
    if ( class_exists('WC_APC_Loader') ) {
        WC_APC_Loader::init();
    } else {
        $loader = plugin_dir_path(__FILE__).'includes/class-wc-apc-loader.php';
        if ( file_exists($loader) ) { require_once $loader; if ( class_exists('WC_APC_Loader') ) WC_APC_Loader::init(); }
    }
});
