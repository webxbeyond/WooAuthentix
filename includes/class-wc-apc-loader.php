<?php
/**
 * Loader class: centralizes includes for modular structure.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Loader {
    public static function init() {
        self::includes();
        do_action('wooauthentix_modules_loaded');
    }
    private static function includes() {
        $base = plugin_dir_path( __FILE__ );
        $root = dirname( $base );
        // Core modules
        // constants reside in main plugin file; module-constants optional if decoupled later
        if ( file_exists( $root . '/includes/module-constants.php' ) ) {
            require_once $root . '/includes/module-constants.php';
        }
    require_once $root . '/includes/module-activation.php';
    require_once $root . '/includes/module-settings.php';
    require_once $root . '/includes/module-codes.php'; // code generation & order assignment
    require_once $root . '/includes/module-verification.php'; // verification + rate limiting + logging
    require_once $root . '/includes/module-rest.php'; // REST endpoints depend on verification
    require_once $root . '/includes/module-admin-pages.php'; // admin UI (menus, pages)
    require_once $root . '/includes/module-labels.php';
    require_once $root . '/includes/module-cli.php';
    require_once $root . '/includes/module-privacy.php';
    }
}
