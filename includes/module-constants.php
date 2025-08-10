<?php
// Separated constants so other modules can rely on them.
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Version & DB constants are primary in main plugin file. These act only as a fallback
// if this module is loaded in a decoupled context (e.g., unit tests including modules directly).
if ( ! defined('WC_APC_VERSION')) {
    define('WC_APC_VERSION','2.2.0'); // keep in sync with plugin header + main file const
    define('WC_APC_DB_VERSION','2.1.0');
}
if ( ! defined('WC_APC_TABLE'))            define('WC_APC_TABLE','wc_authentic_codes');
if ( ! defined('WC_APC_LOG_TABLE'))        define('WC_APC_LOG_TABLE','wc_authentic_logs');
if ( ! defined('WC_APC_OPTION_SETTINGS'))  define('WC_APC_OPTION_SETTINGS','wooauthentix_settings');
if ( ! defined('WC_APC_RATE_LIMIT_MAX'))   define('WC_APC_RATE_LIMIT_MAX',20);
if ( ! defined('WC_APC_RATE_LIMIT_WINDOW'))define('WC_APC_RATE_LIMIT_WINDOW',300);
if ( ! defined('WC_APC_CRON_EVENT'))       define('WC_APC_CRON_EVENT','wooauthentix_prune_logs');
if ( ! defined('WC_APC_ITEM_META_KEY'))    define('WC_APC_ITEM_META_KEY','_wc_apc_code');
