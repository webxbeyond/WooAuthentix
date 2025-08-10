<?php
// Codes module: generation, assignment, low-stock notifications
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Codes_Module {
	public static function init() {
		add_action('woocommerce_order_status_completed', [__CLASS__, 'assign_on_order']);
		// Optionally also assign when moves to processing
		add_action('woocommerce_order_status_processing', [__CLASS__, 'assign_on_order']);
	}

	/**
	 * Generate a batch of authenticity codes.
	 * If $product_id is null / 0 => generic pool (product_id NULL).
	 */
	public static function generate_batch_codes($product_id = null, $count = 100) {
		global $wpdb; $table = $wpdb->prefix . WC_APC_TABLE;
		$codes = [];
		$desired = max(1, intval($count));
		$now = current_time('mysql');
		$settings = wc_apc_get_settings();
		$code_length = function_exists('wc_apc_get_code_length') ? wc_apc_get_code_length() : ( isset($settings['code_length']) ? (int)$settings['code_length'] : 12 );
		$target_pid = intval($product_id) > 0 ? intval($product_id) : null;
		while (count($codes) < $desired) {
			$remaining = $desired - count($codes);
			$batch_size = min(500, $remaining);
			$new_codes = [];
			for ($i=0; $i<$batch_size; $i++) {
				$bytesNeeded = $code_length / 2;
				$raw = strtoupper(bin2hex(random_bytes($bytesNeeded)));
				$generated = apply_filters('wooauthentix_generate_code', $raw, $target_pid, ['length'=>$code_length,'generic'=>is_null($target_pid)]);
				if (!is_string($generated) || $generated==='') { $generated = $raw; }
				$new_codes[] = strtoupper($generated);
			}
			$placeholders = implode(',', array_fill(0, count($new_codes), '%s'));
			$existing = $wpdb->get_col($wpdb->prepare("SELECT code FROM $table WHERE code IN ($placeholders)", ...$new_codes));
			if ($existing) { $new_codes = array_values(array_diff($new_codes, $existing)); }
			if (empty($new_codes)) continue;
			$insert_rows = [];
			foreach ($new_codes as $c) {
				if (is_null($target_pid)) {
					$insert_rows[] = $wpdb->prepare('(NULL,%s,%d,%d,%s)', $c, 0, 0, $now);
				} else {
					$insert_rows[] = $wpdb->prepare('(%d,%s,%d,%d,%s)', $target_pid, $c, 0, 0, $now);
				}
				do_action('wooauthentix_code_generated', $c, $target_pid);
			}
			$sql = 'INSERT INTO '.$table.' (product_id, code, is_used, status, created_at) VALUES '.implode(',', $insert_rows);
			$wpdb->query($sql);
			$codes = array_merge($codes, $new_codes);
		}
		do_action('wooauthentix_batch_generated', $target_pid, $desired, $codes);
		if(!is_null($target_pid)) self::maybe_notify_low_stock($target_pid);
		return array_slice($codes,0,$desired);
	}

	/** Assign codes (product-specific first, then generic pool fallback; generate if exhausted) */
	public static function assign_on_order($order_id) {
		global $wpdb; $order = wc_get_order($order_id); if(!$order) return; $table=$wpdb->prefix.WC_APC_TABLE;
		$retry_limit = apply_filters('wooauthentix_assignment_retry_attempts', 5);
		$now = current_time('mysql');
		foreach ($order->get_items() as $item_id=>$item) {
			$product_id = (int)$item->get_product_id(); $quantity = (int)$item->get_quantity(); if($quantity<=0) continue;
			$assigned_codes = [];
			for($n=0;$n<$quantity;$n++){
				$code = self::claim_product_code($product_id,$order_id,$item_id,$now,$retry_limit);
				if(!$code){ $code = self::claim_generic_code_and_tag($product_id,$order_id,$item_id,$now,$retry_limit); }
				if(!$code){
					// Generate one generic then retry generic claim
					self::generate_batch_codes(null,1);
					$code = self::claim_generic_code_and_tag($product_id,$order_id,$item_id,$now,$retry_limit);
				}
				if($code){ $assigned_codes[]=$code; } else { break; }
			}
			if(count($assigned_codes) < $quantity){
				// As last resort generate product-specific codes for the remainder
				$remain = $quantity - count($assigned_codes);
				$new_codes = self::generate_batch_codes($product_id,$remain);
				if($new_codes){
					$ph=implode(',',array_fill(0,count($new_codes),'%s'));
					$fresh=$wpdb->get_results($wpdb->prepare("SELECT id,code FROM $table WHERE code IN ($ph)",...$new_codes));
					foreach($fresh as $fr){
						$aff=$wpdb->update($table,[ 'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>$now ],['id'=>$fr->id,'status'=>0]);
						if($aff) $assigned_codes[]=$fr->code;
						if(count($assigned_codes)>=$quantity) break;
					}
				}
			}
			if(!empty($assigned_codes)){
				$existing_new = $item->get_meta(WC_APC_ITEM_META_KEY);
				$merged = [];
				if ($existing_new) { $merged = array_merge($merged, array_map('trim', explode(',', $existing_new))); }
				$merged = array_unique(array_merge($merged, $assigned_codes));
				$item->update_meta_data(WC_APC_ITEM_META_KEY, implode(', ', $merged));
				$item->save();
			}
			self::maybe_notify_low_stock($product_id);
		}
	}

	private static function claim_product_code($product_id,$order_id,$item_id,$now,$retry){
		if($product_id<=0) return false; global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $attempt=0;
		while($attempt++ < $retry){
			$row=$wpdb->get_row($wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0 ORDER BY id ASC LIMIT 1",$product_id));
			if(!$row) return false;
			$aff=$wpdb->update($table,[ 'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>$now ],['id'=>$row->id,'status'=>0]);
			if($aff) return $row->code;
		}
		return false;
	}
	private static function claim_generic_code_and_tag($product_id,$order_id,$item_id,$now,$retry){
		if($product_id<=0) return false; global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $attempt=0;
		while($attempt++ < $retry){
			$row=$wpdb->get_row("SELECT id,code FROM $table WHERE product_id IS NULL AND status=0 ORDER BY id ASC LIMIT 1");
			if(!$row) return false;
			$aff=$wpdb->update($table,[ 'product_id'=>$product_id,'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>$now ],['id'=>$row->id,'status'=>0,'product_id'=>null]);
			if($aff) return $row->code;
		}
		return false;
	}

	/** Notify admin when below threshold */
	public static function maybe_notify_low_stock($product_id) {
		if (!function_exists('wc_apc_get_settings')) return;
		$settings = wc_apc_get_settings();
		if (empty($settings['low_stock_notify'])) return;
		$threshold = max(1, intval($settings['low_stock_threshold']));
		global $wpdb; $table = $wpdb->prefix . WC_APC_TABLE;
		$count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE product_id=%d AND status=0", $product_id));
		if ($count >= $threshold) return;
		$key = 'wooauthentix_low_stock_'.$product_id;
		if (get_transient($key)) return;
		set_transient($key,1,6*HOUR_IN_SECONDS);
		$product = wc_get_product($product_id); $name = $product? $product->get_name() : ('#'.$product_id);
		wp_mail(get_option('admin_email'), sprintf(__('Low authenticity code stock for %s','wooauthentix'), $name), sprintf(__('Product "%1$s" has only %2$d unassigned authenticity codes remaining (threshold %3$d). Generate more codes to avoid order assignment failures.','wooauthentix'), $name, $count, $threshold));
	}
}

WC_APC_Codes_Module::init();

// Backward compatibility function name wrapper
if (!function_exists('wc_apc_generate_batch_codes')) {
	function wc_apc_generate_batch_codes($product_id, $count=100){
		return WC_APC_Codes_Module::generate_batch_codes($product_id, $count);
	}
}

if (!function_exists('wc_apc_maybe_notify_low_stock')) {
	function wc_apc_maybe_notify_low_stock($product_id){
		return WC_APC_Codes_Module::maybe_notify_low_stock($product_id);
	}
}
