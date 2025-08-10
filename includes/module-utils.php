<?php
/**
 * Utility helpers for WooAuthentix.
 *
 * Centralizes small shared helpers to reduce duplication across modules.
 *
 * @package WooAuthentix
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'wc_apc_normalize_code_length' ) ) {
    /**
     * Normalize a desired code length to an even integer within [8, 32].
     * Under 8 becomes 8, above 32 becomes 32, and odd numbers are rounded up to next even.
     *
     * @param int $len Requested length.
     * @return int Normalized length.
     */
    function wc_apc_normalize_code_length( $len ) : int {
        $len = (int) $len;
        $min = defined('WC_APC_CODE_MIN_LENGTH') ? WC_APC_CODE_MIN_LENGTH : 8;
        $max = defined('WC_APC_CODE_MAX_LENGTH') ? WC_APC_CODE_MAX_LENGTH : 32;
        if ( $len < $min )  { $len = $min; }
        if ( $len > $max ) { $len = $max; }
        if ( $len % 2 !== 0 ) { $len++; }
        return $len;
    }
}

if ( ! function_exists( 'wc_apc_get_code_length' ) ) {
    /**
     * Convenience accessor for current configured code length (already normalized).
     *
     * @return int
     */
    function wc_apc_get_code_length() : int {
        $settings = wc_apc_get_settings();
        return isset( $settings['code_length'] ) ? (int) $settings['code_length'] : 12; // wc_apc_get_settings already normalizes.
    }
}

if ( ! function_exists( 'wc_apc_code_table' ) ) {
    /**
     * Return fully qualified authenticity codes table name.
     * @global wpdb $wpdb
     * @return string
     */
    function wc_apc_code_table() : string {
        global $wpdb; return $wpdb->prefix . WC_APC_TABLE; }
}

if ( ! function_exists( 'wc_apc_log_table' ) ) {
    /**
     * Return fully qualified logs table name.
     * @global wpdb $wpdb
     * @return string
     */
    function wc_apc_log_table() : string { global $wpdb; return $wpdb->prefix . WC_APC_LOG_TABLE; }
}
