<?php
// Basic bootstrap placeholder; real setup would use WP test suite or wp-env.
// Abort if not run under PHPUnit.
if ( ! defined('PHPUNIT_RUNNING') ) {
    define('PHPUNIT_RUNNING', true);
}

// Attempt to locate WordPress test library (developer to adjust path / use wp-env in CI).
$tests_dir = getenv('WP_TESTS_DIR');
if ( ! $tests_dir ) {
    $tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}
if ( file_exists($tests_dir . '/includes/functions.php') ) {
    require_once $tests_dir . '/includes/functions.php';
}
// Load WooCommerce + plugin manually in test environment.
function _wooauthentix_manually_load_plugin() {
    // Attempt to load WooCommerce if present in plugins dir for richer factories.
    $wc_plugin = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    if ( file_exists( $wc_plugin ) ) {
        include_once $wc_plugin;
    } else {
        fwrite(STDERR, "WARNING: WooCommerce not found; product/order factories may fail.\n");
    }
    require dirname(__DIR__) . '/authentic-checker.php';
}
if ( function_exists('tests_add_filter') ) {
    tests_add_filter('muplugins_loaded', '_wooauthentix_manually_load_plugin');
}

// Provide minimal factory fallbacks if WooCommerce factories missing
if ( class_exists('WP_UnitTest_Factory') ) {
    tests_add_filter('init', function(){
        if ( ! class_exists('WC_Product') ) return; // WooCommerce absent
        // If product factory not populated in global $factory, create a simple helper wrapper.
        global $factory;
        if ( isset($factory->product) ) return;
        $factory->product = new class {
            public function create($args = []) {
                $defaults = [ 'name' => 'Test Product '.wp_generate_password(6,false), 'regular_price' => '9.99' ];
                $args = wp_parse_args($args, $defaults);
                $p = new WC_Product_Simple();
                $p->set_name($args['name']);
                if(isset($args['regular_price'])) $p->set_regular_price($args['regular_price']);
                $p->save();
                return $p->get_id();
            }
        };
        if ( isset($factory->order) ) return;
        $factory->order = new class {
            public function create($args = []) { $order = wc_create_order(); return $order->get_id(); }
        };
    });
}
if ( file_exists($tests_dir . '/includes/bootstrap.php') ) {
    require $tests_dir . '/includes/bootstrap.php';
} else {
    fwrite(STDERR, "WARNING: WordPress test suite not found.\n");
}
