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

// ---------------- Item Codes Meta Helpers -----------------
if ( ! function_exists( 'wc_apc_parse_codes_meta' ) ) {
    /**
     * Normalize a raw stored meta value (array|string|null) into an array of distinct codes.
     * Accepts either an array (already split) or a comma / space separated string.
     * @param mixed $raw
     * @return string[] Uppercase, unique codes in original order of appearance.
     */
    function wc_apc_parse_codes_meta( $raw ) : array {
        if ( is_array( $raw ) ) {
            $codes = $raw;
        } elseif ( is_string( $raw ) && $raw !== '' ) {
            if ( strpos( $raw, ',' ) !== false ) {
                $codes = array_map( 'trim', explode( ',', $raw ) );
            } else {
                $codes = [ trim( $raw ) ];
            }
        } else {
            $codes = [];
        }
        $codes = array_filter( array_map( function( $c ){ return strtoupper( trim( $c ) ); }, $codes ) );
        return array_values( array_unique( $codes ) );
    }
}

if ( ! function_exists( 'wc_apc_get_item_codes' ) ) {
    /**
     * Get normalized codes array for an order item.
     * @param WC_Order_Item_Product $item
     * @return string[]
     */
    function wc_apc_get_item_codes( $item ) : array {
        if ( ! $item ) return [];
        return wc_apc_parse_codes_meta( $item->get_meta( WC_APC_ITEM_META_KEY ) );
    }
}

if ( ! function_exists( 'wc_apc_set_item_codes' ) ) {
    /**
     * Persist array of codes to an order item. By default stores as comma separated string for backward compatibility.
     * Filter `wooauthentix_store_codes_as_string`=true to keep legacy format; set to false to store raw array.
     * @param WC_Order_Item_Product $item
     * @param string[] $codes
     * @param bool|null $as_string Optional override of storage format.
     */
    function wc_apc_set_item_codes( $item, array $codes, $as_string = null ) : void {
        if ( ! $item ) return; $codes = array_values( array_unique( array_filter( array_map( 'strtoupper', $codes ) ) ) );
        if ( $as_string === null ) {
            $as_string = apply_filters( 'wooauthentix_store_codes_as_string', true );
        }
        $value = $as_string ? implode( ', ', $codes ) : $codes;
        $item->update_meta_data( WC_APC_ITEM_META_KEY, $value );
        $item->save();
    }
}
