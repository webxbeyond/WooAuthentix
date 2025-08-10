<?php
// Verification, rate limiting, REST, logging module
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Verification_Module {
	public static function init() {
		add_shortcode('wc_authentic_checker', [__CLASS__,'shortcode']);
		add_action('rest_api_init', [__CLASS__,'register_rest']);
		add_action(WC_APC_CRON_EVENT, [__CLASS__,'prune_logs']);
	}

	public static function shortcode() {
		ob_start();
		$settings = wc_apc_get_settings();
		$len = isset($settings['code_length']) ? (int)$settings['code_length'] : 12; if ($len<8)$len=8; if ($len>32)$len=32; if($len%2!==0)$len++;
		if (empty($_GET['code'])) {
			echo '<form method="get" style="margin:1em 0;" aria-describedby="wooauthentix-result" novalidate>';
			echo '<label style="display:block;margin-bottom:4px;">'.esc_html__('Enter authenticity code','wooauthentix').'</label>';
			echo '<input type="text" name="code" maxlength="'.esc_attr($len).'" pattern="[A-Fa-f0-9]{'.esc_attr($len).'}" required /> ';
			echo '<button type="submit">'.esc_html__('Verify','wooauthentix').'</button>';
			echo '<div id="wooauthentix-result" aria-live="polite" style="margin-top:1em;"></div>';
			echo '</form>';
			return ob_get_clean();
		}
		$code = strtoupper(sanitize_text_field($_GET['code']));
		if (!preg_match('/^[A-F0-9]{'.preg_quote((string)$len,'/').'}$/', $code)) {
			echo '<p style="color:red; font-weight:bold;">'.esc_html__('Invalid code format.','wooauthentix').'</p>';
			return ob_get_clean();
		}
		$result = self::perform_verification($code, 'shortcode');
		echo $result['html'];
		return ob_get_clean();
	}

	public static function perform_verification($code, $context='shortcode') {
		$settings = wc_apc_get_settings();
		$ip = self::get_ip();
		if ($settings['enable_rate_limit'] && self::is_rate_limited($ip)) {
			self::log_attempt($code,'rate_limited');
			return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red;font-weight:bold;">'.esc_html__('Rate limit exceeded. Please wait and try again.','wooauthentix').'</p>'];
		}
		if ($settings['enable_rate_limit']) self::increment_rate($ip);
		global $wpdb; $table = $wpdb->prefix . WC_APC_TABLE;
		$entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE code=%s", $code));
		if(!$entry){ self::log_attempt($code,'invalid_code'); return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html__('Invalid code. This product may not be authentic.','wooauthentix').'</p>']; }
		if (!isset($entry->status) || (int)$entry->status===0) {
			if (!empty($settings['preprinted_mode'])) {
				$now=current_time('mysql'); $wpdb->update($table,[ 'status'=>2,'verified_at'=>$now,'is_used'=>1,'used_at'=>$now,'assigned_at'=>$now ], ['id'=>$entry->id]);
				$entry->status=2; $entry->verified_at=$now; $entry->is_used=1; $entry->used_at=$now; $entry->assigned_at=$now;
				self::log_attempt($code,'verified');
				do_action('wooauthentix_after_verification',$code,['first_time'=>true,'status'=>2,'product_id'=>$entry->product_id,'order_id'=>null,'verified_at'=>$entry->verified_at,'context'=>$context]);
			} else {
				self::log_attempt($code,'unassigned');
				return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html__('Invalid code or not yet assigned to a purchase.','wooauthentix').'</p>'];
			}
		}
		$first_time=false;
		if ((int)$entry->status===1) { $first_time=true; $now=current_time('mysql'); $wpdb->update($table,[ 'status'=>2,'verified_at'=>$now,'is_used'=>1,'used_at'=>$now ], ['id'=>$entry->id]); $entry->status=2; $entry->verified_at=$now; $entry->is_used=1; $entry->used_at=$now; self::log_attempt($code,'verified'); }
		else { self::log_attempt($code,'already_verified'); }
		do_action('wooauthentix_after_verification',$code,['first_time'=>$first_time,'status'=>$entry->status,'product_id'=>$entry->product_id,'order_id'=>$entry->order_id,'verified_at'=>$entry->verified_at,'context'=>$context]);
		$product = wc_get_product($entry->product_id); if(!$product){ return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html__('Product not found.','wooauthentix').'</p>']; }
		$buyer_name=''; $purchase_date='';
		if ($settings['show_buyer_name'] || $settings['show_purchase_date']) { $order = $entry->order_id? wc_get_order($entry->order_id):null; if($order){ if($settings['show_buyer_name']){ $first=$order->get_billing_first_name(); $last=$order->get_billing_last_name(); if($settings['mask_buyer_name'] && $last){ $last=mb_substr($last,0,1).'.'; } $buyer_name=trim($first.' '.$last); if(empty($buyer_name)) $buyer_name=__('Guest','wooauthentix'); } if($settings['show_purchase_date']){ $purchase_date = $order->get_date_created()? $order->get_date_created()->date(get_option('date_format').' '.get_option('time_format')):''; } } }
		$html = $first_time? '<p style="color:green; font-weight:bold;">'.esc_html__('First-time verification: product authenticated.','wooauthentix').'</p>' : '<p style="color:orange; font-weight:bold;">'.esc_html__('This authenticity code has already been verified.','wooauthentix').'</p>';
		$html.='<div style="border:1px solid #ccc; padding:1em; max-width:500px;">';
		$html.='<h2>'.esc_html__('Product Authenticity Result','wooauthentix').'</h2>';
		$html.='<p><strong>'.esc_html__('Product','wooauthentix').':</strong> '.esc_html($product->get_name()).'</p>';
		if ($product->get_image()) { $html.='<p>'.$product->get_image('thumbnail').'</p>'; }
		if ($settings['show_buyer_name']) { $html.='<p><strong>'.esc_html__('Buyer Name','wooauthentix').':</strong> '.esc_html($buyer_name).'</p>'; }
		if ($settings['show_purchase_date']) { $html.='<p><strong>'.esc_html__('Purchase Date','wooauthentix').':</strong> '.esc_html($purchase_date).'</p>'; }
		$html.='<p><strong>'.esc_html__('Authenticity Code','wooauthentix').':</strong> '.esc_html($code).'</p>';
		$html.='<p><em>'.esc_html__('First Verified At','wooauthentix').': '.esc_html($entry->verified_at ? $entry->verified_at : __('Not yet','wooauthentix')).'</em></p>';
		$html.='</div>';
		return ['success'=>true,'first_time'=>$first_time,'html'=>$html,'product'=>$product,'verified_at'=>$entry->verified_at];
	}

	public static function get_ip() {
		foreach(['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k){ if(!empty($_SERVER[$k])){ $ip_list=explode(',', $_SERVER[$k]); return trim($ip_list[0]); } }
		return 'unknown';
	}
	public static function is_rate_limited($ip){ $settings=wc_apc_get_settings(); $key='wooauthentix_rl_'.md5($ip); $data=get_transient($key); if(!$data) return false; return $data['count'] >= $settings['rate_limit_max']; }
	public static function increment_rate($ip){ $settings=wc_apc_get_settings(); $key='wooauthentix_rl_'.md5($ip); $data=get_transient($key); if(!$data){ $data=['count'=>1,'start'=>time()]; } else { $data['count']++; } set_transient($key,$data,$settings['rate_limit_window']); }

	public static function register_rest() {
		$settings = wc_apc_get_settings();
		$len = isset($settings['code_length']) ? (int)$settings['code_length'] : 12; if ($len<8)$len=8; if ($len>32)$len=32; if($len%2!==0)$len++;
		register_rest_route('wooauthentix/v1','/verify',[
			'methods'=>'POST',
			'permission_callback'=>function($request){
				$settings = wc_apc_get_settings();
				// If no API key set, allow public (backward compatible)
				$api_key = isset($settings['rest_api_key']) ? trim($settings['rest_api_key']) : '';
				if($api_key==='') return true;
				$provided = $request->get_header('x-wooauthentix-key');
				return hash_equals($api_key, (string)$provided);
			},
			'args'=>[
				'code'=>[
					'required'=>true,
					'validate_callback'=>function($param) use($len){ return is_string($param) && preg_match('/^[A-Fa-f0-9]{'.preg_quote((string)$len,'/').'}$/',$param); }
				]
			],
			'callback'=>function($request) use ($len){
				$settings = wc_apc_get_settings();
				$code = strtoupper(sanitize_text_field($request->get_param('code')));
				if (!preg_match('/^[A-F0-9]{'.preg_quote((string)$len,'/').'}$/',$code)) { self::log_attempt($code,'invalid_format'); return new WP_REST_Response(['success'=>false,'error'=>__('Invalid code format','wooauthentix')],400); }
				$res = self::perform_verification($code,'rest');
				if(!$res['success']) return new WP_REST_Response(['success'=>false,'error'=>wp_strip_all_tags($res['html'])],400);
				return new WP_REST_Response(['success'=>true,'first_time'=>$res['first_time'],'product'=>$res['product']?$res['product']->get_name():null,'verified_at'=>$res['verified_at'],'html'=>$res['html']],200);
			}
		]);
	}

	public static function log_attempt($code,$result){ $settings=wc_apc_get_settings(); if(!$settings['enable_logging']) return; global $wpdb; $table=$wpdb->prefix.WC_APC_LOG_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) return; $ip=self::get_ip(); if(!empty($settings['hash_ip_addresses']) && $ip!=='unknown'){ $ip = hash('sha256', $ip . wp_salt('auth') ); } $wpdb->insert($table,['code'=>$code,'ip'=>$ip,'result'=>substr($result,0,32),'user_agent'=>isset($_SERVER['HTTP_USER_AGENT'])?substr($_SERVER['HTTP_USER_AGENT'],0,500):'','created_at'=>current_time('mysql')]); }
	public static function prune_logs(){ $settings=wc_apc_get_settings(); if(!$settings['enable_logging']) return; global $wpdb; $table=$wpdb->prefix.WC_APC_LOG_TABLE; $days=intval($settings['log_retention_days']); $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < (NOW() - INTERVAL %d DAY)",$days)); }
}

WC_APC_Verification_Module::init();

// Backward compatibility wrappers
if (!function_exists('wc_apc_perform_verification')) {
	function wc_apc_perform_verification($code,$context='shortcode'){ return WC_APC_Verification_Module::perform_verification($code,$context); }
}
if (!function_exists('wc_apc_get_ip')) {
	function wc_apc_get_ip(){ return WC_APC_Verification_Module::get_ip(); }
}
if (!function_exists('wc_apc_is_rate_limited')) {
	function wc_apc_is_rate_limited($ip){ return WC_APC_Verification_Module::is_rate_limited($ip); }
}
if (!function_exists('wc_apc_increment_rate')) {
	function wc_apc_increment_rate($ip){ return WC_APC_Verification_Module::increment_rate($ip); }
}
if (!function_exists('wc_apc_log_attempt')) {
	function wc_apc_log_attempt($code,$result){ return WC_APC_Verification_Module::log_attempt($code,$result); }
}
