<?php
// Activation / uninstall / migrations separated.
if ( ! defined( 'ABSPATH' ) ) { exit; }

register_activation_hook(__FILE__, 'wc_apc_plugin_activate_mod');
function wc_apc_plugin_activate_mod(){ /* placeholder - real activation remains in legacy main until full refactor */ }
