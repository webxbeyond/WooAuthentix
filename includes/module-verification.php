<?php
// Verification, rate limiting, REST, logging module
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Verification_Module {
	public static function init() {
		add_shortcode('wc_authentic_checker', [__CLASS__,'shortcode']);
		add_action('rest_api_init', [__CLASS__,'register_rest']);
		add_action(WC_APC_CRON_EVENT, [__CLASS__,'prune_logs']);
		// Pretty permalink: /v/CODE -> verification output
		add_action('init', [__CLASS__, 'add_rewrite']);
		add_filter('query_vars', [__CLASS__, 'register_query_vars']);
		add_action('template_redirect', [__CLASS__, 'handle_pretty_verification']);
	}

	/** Register rewrite rule for /v/{CODE} */
	public static function add_rewrite() {
		// Accept 8-32 hex chars (even length will be validated later)
		add_rewrite_rule('^v/([A-Fa-f0-9]{8,32})/?$', 'index.php?wc_apc_verify=1&wc_apc_code=$matches[1]', 'top');
	}

	/** Allow custom query vars */
	public static function register_query_vars($vars) {
		$vars[] = 'wc_apc_verify';
		$vars[] = 'wc_apc_code';
		return $vars;
	}

	/** Handle pretty verification URL rendering */
	public static function handle_pretty_verification() {
		if ( intval(get_query_var('wc_apc_verify')) !== 1 ) return;
		$code = get_query_var('wc_apc_code');
		if ( ! $code ) return; // let normal flow continue
		$code = strtoupper(sanitize_text_field($code));
		$settings = wc_apc_get_settings();
		$len = function_exists('wc_apc_get_code_length') ? wc_apc_get_code_length() : ( isset($settings['code_length']) ? (int)$settings['code_length'] : 12 );
		// Validate format (length + hex) before verification
		if ( ! preg_match('/^[A-F0-9]{'.preg_quote((string)$len,'/').'}$/', $code) ) {
			self::render_minimal_page('<p style="color:red;font-weight:bold;">'.esc_html($settings['verification_msg_invalid_format']).'</p>', $code, $settings);
			exit;
		}
		$result = self::perform_verification($code, 'pretty');
		self::render_minimal_page($result['html'], $code, $settings);
		exit;
	}

	/** Output a minimal standalone HTML page (bypasses theme) with design settings */
	protected static function render_minimal_page($content_html, $code, $settings=null) {
		if(!$settings) $settings = wc_apc_get_settings();
		$site = get_bloginfo('name');
		$heading = isset($settings['verification_heading'])? $settings['verification_heading']:__('Product Authenticity','wooauthentix');
		$container_width = isset($settings['verification_container_width'])? intval($settings['verification_container_width']):760;
		$bg = isset($settings['verification_bg_color'])? $settings['verification_bg_color']:'#f5f5f7';
		$custom_css = !empty($settings['verification_custom_css'])? '<style id="wooauthentix-custom-css">'.$settings['verification_custom_css'].'</style>':'';
		$custom_js = !empty($settings['verification_custom_js'])? '<script id="wooauthentix-custom-js">'.$settings['verification_custom_js'].'</script>':'';
		echo '<!DOCTYPE html><html lang="'.esc_attr(get_locale()).'"><head><meta charset="'.esc_attr(get_bloginfo('charset')).'" />';
		echo '<title>'.esc_html(sprintf(__('Verification for %s','wooauthentix'), $code)).' - '.esc_html($site).'</title>';
		echo '<meta name="robots" content="noindex, nofollow" />';
		echo '<style>body{font:16px/1.4 sans-serif;margin:40px;background:'.esc_attr($bg).';color:#222;} .wa-wrap{max-width:'.esc_attr($container_width).'px;margin:0 auto;background:#fff;padding:28px 32px;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05);} h1{margin-top:0;font-size:24px;} .wa-foot{margin-top:32px;font-size:12px;color:#666;text-align:center;} a{color:#2271b1;text-decoration:none;} a:hover{text-decoration:underline;} .wa-form{margin-top:18px;} input[type=text]{padding:8px;font-size:16px;width:260px;}</style>';
		echo $custom_css;
		echo '</head><body><div class="wa-wrap">';
		echo '<h1>'.esc_html($heading).'</h1>';
		echo wp_kses_post($content_html);
		if ( ! empty($settings['verification_page_url']) ) {
			echo '<p><a href="'.esc_url($settings['verification_page_url']).'">'.esc_html__('Back to verification page','wooauthentix').'</a></p>';
		}
		echo '<div class="wa-foot">'.esc_html($site).' &middot; '.esc_html__('Powered by WooAuthentix','wooauthentix').'</div>';
		echo '</div>'.$custom_js.'</body></html>';
	}

	public static function shortcode() {
		ob_start();
		$settings = wc_apc_get_settings();
		$len = function_exists('wc_apc_get_code_length') ? wc_apc_get_code_length() : ( isset($settings['code_length']) ? (int)$settings['code_length'] : 12 );
		$container_width = isset($settings['verification_container_width'])? intval($settings['verification_container_width']):500;
		$heading = isset($settings['verification_heading'])? $settings['verification_heading']:__('Product Authenticity','wooauthentix');
		$custom_css = !empty($settings['verification_custom_css'])? '<style id="wooauthentix-custom-css">'.$settings['verification_custom_css'].'</style>':'';
		$custom_js_footer = !empty($settings['verification_custom_js'])? '<script id="wooauthentix-custom-js">'.$settings['verification_custom_js'].'</script>':'';
		if (empty($_GET['code'])) {
			echo '<div class="wooauthentix-verify-wrapper" style="max-width:'.esc_attr($container_width).'px;margin:1.5em auto;padding:24px 28px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.04);">';
			echo '<h2 style="margin-top:0;">'.esc_html($heading).'</h2>';
			echo '<form method="get" class="wooauthentix-verify-form" aria-describedby="wooauthentix-result" novalidate>';
			echo '<label style="display:block;margin-bottom:6px;font-weight:600;">'.esc_html__('Enter authenticity code','wooauthentix').'</label>';
			echo '<input style="padding:10px;font-size:16px;width:260px;max-width:100%;" type="text" name="code" maxlength="'.esc_attr($len).'" pattern="[A-Fa-f0-9]{'.esc_attr($len).'}" required /> ';
			echo '<button type="submit" class="button" style="padding:10px 18px;font-size:15px;">'.esc_html__('Verify','wooauthentix').'</button>';
			echo '<div id="wooauthentix-result" aria-live="polite" style="margin-top:1em;"></div>';
			echo '</form>';
			echo '</div>'.$custom_css.$custom_js_footer;
			return ob_get_clean();
		}
		$code = strtoupper(sanitize_text_field($_GET['code']));
		if (!preg_match('/^[A-F0-9]{'.preg_quote((string)$len,'/').'}$/', $code)) {
			echo '<div class="wooauthentix-verify-wrapper" style="max-width:'.esc_attr($container_width).'px;margin:1.5em auto;padding:24px 28px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.04);"><h2 style="margin-top:0;">'.esc_html($heading).'</h2><p style="color:red; font-weight:bold;">'.esc_html($settings['verification_msg_invalid_format']).'</p></div>'.$custom_css.$custom_js_footer; return ob_get_clean();
		}
		$result = self::perform_verification($code, 'shortcode');
		echo '<div class="wooauthentix-verify-wrapper" style="max-width:'.esc_attr($container_width).'px;margin:1.5em auto;padding:24px 28px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.04);"><h2 style="margin-top:0;">'.esc_html($heading).'</h2>'.$result['html'].'</div>'.$custom_css.$custom_js_footer;
		return ob_get_clean();
	}

	public static function perform_verification($code, $context='shortcode') {
		$settings = wc_apc_get_settings();
		$ip = self::get_ip();
		if ($settings['enable_rate_limit'] && self::is_rate_limited($ip)) {
			self::log_attempt($code,'rate_limited');
			return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red;font-weight:bold;">'.esc_html(self::replace_placeholders($settings['verification_msg_rate_limited'],$code,null,'','','')).'</p>'];
		}
		if ($settings['enable_rate_limit']) self::increment_rate($ip);
		global $wpdb; $table = $wpdb->prefix . WC_APC_TABLE;
		$entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE code=%s", $code));
		if(!$entry){ self::log_attempt($code,'invalid_code'); return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html(self::replace_placeholders($settings['verification_msg_invalid_code'],$code,null,'','','')).'</p>']; }
		if (!isset($entry->status) || (int)$entry->status===0) {
			if (!empty($settings['preprinted_mode'])) {
				$now=current_time('mysql'); $wpdb->update($table,[ 'status'=>2,'verified_at'=>$now,'is_used'=>1,'used_at'=>$now,'assigned_at'=>$now ], ['id'=>$entry->id]);
				$entry->status=2; $entry->verified_at=$now; $entry->is_used=1; $entry->used_at=$now; $entry->assigned_at=$now;
				self::log_attempt($code,'verified');
				do_action('wooauthentix_after_verification',$code,['first_time'=>true,'status'=>2,'product_id'=>$entry->product_id,'order_id'=>null,'verified_at'=>$entry->verified_at,'context'=>$context]);
			} else {
				self::log_attempt($code,'unassigned');
				return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html(self::replace_placeholders($settings['verification_msg_unassigned'],$code,null,'','','')).'</p>'];
			}
		}
		$first_time=false;
		if ((int)$entry->status===1) { $first_time=true; $now=current_time('mysql'); $wpdb->update($table,[ 'status'=>2,'verified_at'=>$now,'is_used'=>1,'used_at'=>$now ], ['id'=>$entry->id]); $entry->status=2; $entry->verified_at=$now; $entry->is_used=1; $entry->used_at=$now; self::log_attempt($code,'verified'); }
		else { self::log_attempt($code,'already_verified'); }
		do_action('wooauthentix_after_verification',$code,['first_time'=>$first_time,'status'=>$entry->status,'product_id'=>$entry->product_id,'order_id'=>$entry->order_id,'verified_at'=>$entry->verified_at,'context'=>$context]);
		$product = wc_get_product($entry->product_id); if(!$product){ return ['success'=>false,'first_time'=>false,'html'=>'<p style="color:red; font-weight:bold;">'.esc_html__('Product not found.','wooauthentix').'</p>']; }
		$buyer_name=''; $purchase_date='';
		if ($settings['show_buyer_name'] || $settings['show_purchase_date']) { $order = $entry->order_id? wc_get_order($entry->order_id):null; if($order){ if($settings['show_buyer_name']){ $first=$order->get_billing_first_name(); $last=$order->get_billing_last_name(); if($settings['mask_buyer_name'] && $last){ $last=mb_substr($last,0,1).'.'; } $buyer_name=trim($first.' '.$last); if(empty($buyer_name)) $buyer_name=__('Guest','wooauthentix'); } if($settings['show_purchase_date']){ $purchase_date = $order->get_date_created()? $order->get_date_created()->date(get_option('date_format').' '.get_option('time_format')):''; } } }
		$msg_first = self::replace_placeholders($settings['verification_msg_first_time'],$code,$product,$buyer_name,$purchase_date,$entry->verified_at);
		$msg_already = self::replace_placeholders($settings['verification_msg_already_verified'],$code,$product,$buyer_name,$purchase_date,$entry->verified_at);
		$html = $first_time? '<p style="color:green; font-weight:bold;">'.esc_html($msg_first).'</p>' : '<p style="color:orange; font-weight:bold;">'.esc_html($msg_already).'</p>';
		$html.='<div style="border:1px solid #ccc; padding:1em; max-width:500px;">';
		$html.='<h2>'.esc_html__('Product Authenticity Result','wooauthentix').'</h2>';
		$html.='<p><strong>'.esc_html__('Product','wooauthentix').':</strong> '.esc_html($product->get_name()).'</p>';
		if (!empty($settings['verification_show_product_image']) && $product->get_image()) { $html.='<p>'.$product->get_image('thumbnail').'</p>'; }
		if ($settings['show_buyer_name']) { $html.='<p><strong>'.esc_html__('Buyer Name','wooauthentix').':</strong> '.esc_html($buyer_name).'</p>'; }
		if ($settings['show_purchase_date']) { $html.='<p><strong>'.esc_html__('Purchase Date','wooauthentix').':</strong> '.esc_html($purchase_date).'</p>'; }
		$html.='<p><strong>'.esc_html__('Authenticity Code','wooauthentix').':</strong> '.esc_html($code).'</p>';
		$html.='<p><em>'.esc_html__('First Verified At','wooauthentix').': '.esc_html($entry->verified_at ? $entry->verified_at : __('Not yet','wooauthentix')).'</em></p>';
		$html.='</div>';
		return ['success'=>true,'first_time'=>$first_time,'html'=>$html,'product'=>$product,'verified_at'=>$entry->verified_at];
	}

	/** Replace template placeholders */
	protected static function replace_placeholders($template,$code,$product,$buyer_name,$purchase_date,$verified_at){
		$repl = [
			'{code}' => $code,
			'{product}' => $product? $product->get_name() : '',
			'{buyer_name}' => $buyer_name?:'',
			'{purchase_date}' => $purchase_date?:'',
			'{verified_at}' => $verified_at?:'',
			'{site_name}' => get_bloginfo('name'),
		];
		return strtr($template, $repl);
	}

	public static function get_ip() {
		foreach(['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k){ if(!empty($_SERVER[$k])){ $ip_list=explode(',', $_SERVER[$k]); return trim($ip_list[0]); } }
		return 'unknown';
	}
	public static function is_rate_limited($ip){ $settings=wc_apc_get_settings(); $key='wooauthentix_rl_'.md5($ip); $data=get_transient($key); if(!$data) return false; return $data['count'] >= $settings['rate_limit_max']; }
	public static function increment_rate($ip){ $settings=wc_apc_get_settings(); $key='wooauthentix_rl_'.md5($ip); $data=get_transient($key); if(!$data){ $data=['count'=>1,'start'=>time()]; } else { $data['count']++; } set_transient($key,$data,$settings['rate_limit_window']); }

	public static function register_rest() {
		$settings = wc_apc_get_settings();
		$len = function_exists('wc_apc_get_code_length') ? wc_apc_get_code_length() : ( isset($settings['code_length']) ? (int)$settings['code_length'] : 12 );
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
