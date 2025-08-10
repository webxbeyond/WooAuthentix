<?php
// Admin pages (menus, settings UI, dashboard, logs, assign/override, labels, settings registration) module.
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Admin_Pages_Module {
	public static function init() {
		add_action('admin_menu', [__CLASS__,'register_menus']);
		add_action('admin_init', [__CLASS__,'maybe_redirect_legacy']);
		add_action('admin_init', [__CLASS__,'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__,'enqueue_settings_assets']);
		add_action('admin_enqueue_scripts', [__CLASS__,'enqueue_assign_assets']);
		add_action('wp_ajax_wc_apc_item_search', [__CLASS__,'ajax_item_search']);
	}

	// ---------------- Menus -----------------
	public static function register_menus() {
		add_menu_page(__('WooAuthentix','wooauthentix'), __('WooAuthentix','wooauthentix'), 'manage_woocommerce', 'wc-apc-dashboard', [__CLASS__,'dashboard_page'], 'dashicons-shield', 56);
		// New order: Dashboard (top-level), Codes, QR Labels, Assign Codes, Logs, Tools, Settings
		add_submenu_page('wc-apc-dashboard', __('Authenticity Codes','wooauthentix'), __('Codes','wooauthentix'), 'manage_woocommerce', 'wc-apc-codes', [__CLASS__,'codes_page']);
		add_submenu_page('wc-apc-dashboard', __('QR Labels','wooauthentix'), __('QR Labels','wooauthentix'), 'manage_woocommerce', 'wc-apc-labels', [__CLASS__,'labels_page']);
		add_submenu_page('wc-apc-dashboard', __('Assign / Override Codes','wooauthentix'), __('Assign Codes','wooauthentix'), 'manage_woocommerce', 'wc-apc-assign', [__CLASS__,'assign_codes_page']);
		add_submenu_page('wc-apc-dashboard', __('Verification Logs','wooauthentix'), __('Logs','wooauthentix'), 'manage_woocommerce', 'wc-apc-logs', [__CLASS__,'logs_page']);
		add_submenu_page('wc-apc-dashboard', __('Tools','wooauthentix'), __('Tools','wooauthentix'), 'manage_woocommerce', 'wc-apc-tools', [__CLASS__,'tools_page']);
		add_submenu_page('wc-apc-dashboard', __('WooAuthentix Settings','wooauthentix'), __('Settings','wooauthentix'), 'manage_woocommerce', 'wc-apc-settings', [__CLASS__,'settings_page']);
	}

	public static function maybe_redirect_legacy() {
		if (!empty($_GET['page'])) {
			$page = sanitize_text_field(wp_unslash($_GET['page']));
			$legacy = ['wc-apc-codes','wc-apc-settings','wc-apc-logs','wc-apc-dashboard'];
			if (in_array($page,$legacy,true) && (!empty($_GET['post_type']) && $_GET['post_type']==='product')) {
				wp_safe_redirect(remove_query_arg('post_type'));
				exit;
			}
		}
	}

	// ---------------- Settings Registration -----------------
	public static function register_settings() {
		if ( ! is_admin() ) return; static $done=false; if($done) return; $done=true;
		register_setting('wc_apc_settings_group', WC_APC_OPTION_SETTINGS, [__CLASS__,'sanitize_settings']);
		// Flush cached settings after update
		add_action('update_option_' . WC_APC_OPTION_SETTINGS, function(){ if(function_exists('wc_apc_flush_settings_cache')) wc_apc_flush_settings_cache(); }, 10, 0);
		add_settings_section('wc_apc_privacy', __('Privacy Display','wooauthentix'), function(){ echo '<p>'.esc_html__('Control which purchaser details are displayed during code verification.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_privacy','show_buyer_name',__('Show Buyer Name','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[show_buyer_name]" value="1" '.checked(1,$o['show_buyer_name'],false).' /> '.__('Display buyer name (may be personal data)','wooauthentix').'</label>'; });
		self::add_field('wc_apc_privacy','mask_buyer_name',__('Mask Buyer Name','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[mask_buyer_name]" value="1" '.checked(1,$o['mask_buyer_name'],false).' /> '.__('Mask last name to initial (only if name shown)','wooauthentix').'</label>'; });
		self::add_field('wc_apc_privacy','show_purchase_date',__('Show Purchase Date','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[show_purchase_date]" value="1" '.checked(1,$o['show_purchase_date'],false).' /> '.__('Display purchase date','wooauthentix').'</label>'; });
		add_settings_section('wc_apc_security', __('Security & Rate Limiting','wooauthentix'), function(){ echo '<p>'.esc_html__('Configure brute-force protection for verifications.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_security','enable_rate_limit',__('Enable Rate Limiting','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[enable_rate_limit]" value="1" '.checked(1,$o['enable_rate_limit'],false).' /> '.__('Throttle repeated verification attempts per IP','wooauthentix').'</label>'; });
		self::add_field('wc_apc_security','rate_limit_max',__('Max Attempts','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="1" name="'.WC_APC_OPTION_SETTINGS.'[rate_limit_max]" value="'.esc_attr($o['rate_limit_max']).'" />'; });
		self::add_field('wc_apc_security','rate_limit_window',__('Window (seconds)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="30" name="'.WC_APC_OPTION_SETTINGS.'[rate_limit_window]" value="'.esc_attr($o['rate_limit_window']).'" />'; });
		add_settings_section('wc_apc_logging', __('Logging','wooauthentix'), function(){ echo '<p>'.esc_html__('Control recording and retention of verification attempts.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_logging','enable_logging',__('Enable Logging','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[enable_logging]" value="1" '.checked(1,$o['enable_logging'],false).' /> '.__('Record verification attempts (success & failures)','wooauthentix').'</label>'; });
		self::add_field('wc_apc_logging','log_retention_days',__('Log Retention (days)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="1" name="'.WC_APC_OPTION_SETTINGS.'[log_retention_days]" value="'.esc_attr($o['log_retention_days']).'" />'; });
		self::add_field('wc_apc_logging','hash_ip_addresses',__('Hash IP Addresses','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[hash_ip_addresses]" value="1" '.checked(1,$o['hash_ip_addresses'],false).' /> '.esc_html__('Store one-way hashed IP (privacy enhancement)','wooauthentix').'</label>'; });
		add_settings_section('wc_apc_generation', __('Code Generation','wooauthentix'), function(){ echo '<p>'.esc_html__('Tune code characteristics. Longer codes reduce brute-force risk.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_generation','code_length',__('Code Length (hex chars)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="8" max="32" step="2" name="'.WC_APC_OPTION_SETTINGS.'[code_length]" value="'.esc_attr($o['code_length']).'" /> <p class="description">'.esc_html__('Even number between 8 and 32. Default 12.','wooauthentix').'</p>'; });
		add_settings_section('wc_apc_preprinted', __('Preprinted Labels','wooauthentix'), function(){ echo '<p>'.esc_html__('Physically attach codes before orders. Unassigned codes verify on first scan when enabled.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_preprinted','preprinted_mode',__('Enable Preprinted Mode','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[preprinted_mode]" value="1" '.checked(1,$o['preprinted_mode'],false).' /> '.esc_html__('Allow unassigned codes to verify directly (status 0 → verified).','wooauthentix').'</label>'; });
		self::add_field('wc_apc_preprinted','verification_page_url',__('Verification Page URL','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="url" style="width:340px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_page_url]" value="'.esc_attr($o['verification_page_url']).'" placeholder="'.esc_attr(home_url('/verify/')).'" /><p class="description">'.esc_html__('Used for QR labels (?code= appended). Leave blank to use current site home.','wooauthentix').'</p>'; });
		add_settings_section('wc_apc_label_brand', __('Label Branding','wooauthentix'), function(){ echo '<p>'.esc_html__('Customize text/logo printed on QR labels.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_label_brand','label_brand_text',__('Brand Text','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:340px;" name="'.WC_APC_OPTION_SETTINGS.'[label_brand_text]" value="'.esc_attr($o['label_brand_text']).'" placeholder="'.esc_attr(get_bloginfo('name')).'" />'; });
		self::add_field('wc_apc_label_brand','label_logo_id',__('Logo Image','wooauthentix'), [__CLASS__,'field_logo']);
		add_settings_section('wc_apc_label_layout', __('Label Layout','wooauthentix'), function(){ echo '<p>'.esc_html__('Control size, spacing, and structural layout of generated labels.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		add_settings_section('wc_apc_label_visibility', __('Label Visibility','wooauthentix'), function(){ echo '<p>'.esc_html__('Toggle individual elements on each label.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_label_visibility','label_show_brand',__('Show Brand Text','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_show_brand]" value="1" '.checked(1,$o['label_show_brand'],false).' /> '.esc_html__('Display brand text line.','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_visibility','label_show_logo',__('Show Logo Top','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_show_logo]" value="1" '.checked(1,$o['label_show_logo'],false).' /> '.esc_html__('Display logo above QR (if selected).','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_visibility','label_show_code',__('Show Code Text','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_show_code]" value="1" '.checked(1,$o['label_show_code'],false).' /> '.esc_html__('Display raw code under QR.','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_visibility','label_show_site',__('Show Site Host','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_show_site]" value="1" '.checked(1,$o['label_show_site'],false).' /> '.esc_html__('Display site hostname line.','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_layout','label_qr_size',__('QR Size (px)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="60" max="260" name="'.WC_APC_OPTION_SETTINGS.'[label_qr_size]" value="'.esc_attr($o['label_qr_size']).'" />'; });
		self::add_field('wc_apc_label_layout','label_columns',__('Columns','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="0" max="10" name="'.WC_APC_OPTION_SETTINGS.'[label_columns]" value="'.esc_attr($o['label_columns']).'" /> <p class="description">'.esc_html__('0 = auto fill responsive grid','wooauthentix').'</p>'; });
		self::add_field('wc_apc_label_layout','label_margin',__('Label Margin (px)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="2" max="40" name="'.WC_APC_OPTION_SETTINGS.'[label_margin]" value="'.esc_attr($o['label_margin']).'" />'; });
		self::add_field('wc_apc_label_layout','label_enable_border',__('Label Border','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_enable_border]" value="1" '.checked(1,$o['label_enable_border'],false).' /> '.esc_html__('Show border around each label','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_layout','label_border_size',__('Border Thickness (px)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="0" max="10" name="'.WC_APC_OPTION_SETTINGS.'[label_border_size]" value="'.esc_attr($o['label_border_size']).'" /> <p class="description">'.esc_html__('0 removes border even if border toggle is on.','wooauthentix').'</p>'; });
		self::add_field('wc_apc_label_layout','label_logo_overlay',__('Center Logo Overlay','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[label_logo_overlay]" value="1" '.checked(1,$o['label_logo_overlay'],false).' /> '.esc_html__('Embed logo in center of each QR (may reduce readability if logo too large).','wooauthentix').'</label>'; });
		self::add_field('wc_apc_label_layout','label_logo_overlay_scale',__('Logo Overlay Scale %','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="10" max="60" name="'.WC_APC_OPTION_SETTINGS.'[label_logo_overlay_scale]" value="'.esc_attr($o['label_logo_overlay_scale']).'" /> <p class="description">'.esc_html__('Percentage of QR size used for centered logo box (default 28%).','wooauthentix').'</p>'; });
		add_settings_section('wc_apc_advanced', __('Advanced','wooauthentix'), function(){ echo '<p>'.esc_html__('Fallback and experimental features.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_advanced','enable_server_side_qr',__('Server-side QR Fallback','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[enable_server_side_qr]" value="1" '.checked(1,$o['enable_server_side_qr'],false).' /> '.esc_html__('Enable AJAX fallback to server for QR image (requires library or bundled SimpleQR).','wooauthentix').'</label>'; });
		self::add_field('wc_apc_advanced','rest_api_key',__('REST API Key','wooauthentix'), function(){
			$o=wc_apc_get_settings();
			$val=esc_attr($o['rest_api_key']??'');
			echo '<span style="display:flex;gap:6px;align-items:center;max-width:560px;">';
			echo '<input type="text" id="wooauthentix_rest_api_key" style="flex:1;max-width:340px;" name="'.WC_APC_OPTION_SETTINGS.'[rest_api_key]" value="'.$val.'" placeholder="'.esc_attr__('Leave blank for public verify','wooauthentix').'" autocomplete="off" />';
			echo '<button type="button" class="button" id="wooauthentix_generate_key">'.esc_html__('Generate','wooauthentix').'</button>';
			echo '<button type="button" class="button" id="wooauthentix_clear_key" '.($val?'':'style="display:none;"').'>'.esc_html__('Clear','wooauthentix').'</button>';
			echo '</span><p class="description">'.esc_html__('Provide a secret; clients must send X-WooAuthentix-Key header. Blank = public endpoint.','wooauthentix').'</p>';
			$js="jQuery(function($){var fld=$('#wooauthentix_rest_api_key');$('#wooauthentix_generate_key').on('click',function(e){e.preventDefault();try{var bytes=new Uint8Array(24);window.crypto.getRandomValues(bytes);var hex='';for(var i=0;i<bytes.length;i++){hex+=('0'+bytes[i].toString(16)).slice(-2);}fld.val(hex.toUpperCase());$('#wooauthentix_clear_key').show();}catch(err){var t=Date.now().toString(16)+Math.random().toString(16).slice(2,18);fld.val(t.toUpperCase());$('#wooauthentix_clear_key').show();}});$('#wooauthentix_clear_key').on('click',function(){fld.val('');$(this).hide();});});";
			echo '<script>'.$js.'</script>';
		});
		add_settings_section('wc_apc_notifications', __('Notifications','wooauthentix'), function(){ echo '<p>'.esc_html__('Configure automatic alerts when unassigned code inventory runs low.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_notifications','low_stock_threshold',__('Low Stock Threshold','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="1" name="'.WC_APC_OPTION_SETTINGS.'[low_stock_threshold]" value="'.esc_attr($o['low_stock_threshold']).'" />'; });
		self::add_field('wc_apc_notifications','low_stock_notify',__('Email Notification','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[low_stock_notify]" value="1" '.checked(1,$o['low_stock_notify'],false).' /> '.__('Send admin email when a product falls below threshold','wooauthentix').'</label>'; });

		// Verification page design & messages
		add_settings_section('wc_apc_verification_design', __('Verification Page Design','wooauthentix'), function(){ echo '<p>'.esc_html__('Customize the public verification page appearance and outcome messages. Placeholders: {code}, {product}, {buyer_name}, {purchase_date}, {verified_at}, {site_name}.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_verification_design','verification_heading',__('Heading','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:340px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_heading]" value="'.esc_attr($o['verification_heading']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_container_width',__('Container Width (px)','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="320" max="1200" name="'.WC_APC_OPTION_SETTINGS.'[verification_container_width]" value="'.esc_attr($o['verification_container_width']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_bg_color',__('Background Color','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" name="'.WC_APC_OPTION_SETTINGS.'[verification_bg_color]" value="'.esc_attr($o['verification_bg_color']).'" class="regular-text" placeholder="#f5f5f7" />'; });
		self::add_field('wc_apc_verification_design','verification_show_product_image',__('Show Product Image','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[verification_show_product_image]" value="1" '.checked(1,$o['verification_show_product_image'],false).' /> '.__('Display product thumbnail in results','wooauthentix').'</label>'; });
		self::add_field('wc_apc_verification_design','verification_msg_first_time',__('Message: First Time','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_first_time]" value="'.esc_attr($o['verification_msg_first_time']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_msg_already_verified',__('Message: Already Verified','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_already_verified]" value="'.esc_attr($o['verification_msg_already_verified']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_msg_unassigned',__('Message: Unassigned','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_unassigned]" value="'.esc_attr($o['verification_msg_unassigned']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_msg_invalid_code',__('Message: Invalid Code','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_invalid_code]" value="'.esc_attr($o['verification_msg_invalid_code']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_msg_invalid_format',__('Message: Invalid Format','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_invalid_format]" value="'.esc_attr($o['verification_msg_invalid_format']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_msg_rate_limited',__('Message: Rate Limited','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="text" style="width:100%;max-width:600px;" name="'.WC_APC_OPTION_SETTINGS.'[verification_msg_rate_limited]" value="'.esc_attr($o['verification_msg_rate_limited']).'" />'; });
		self::add_field('wc_apc_verification_design','verification_custom_css',__('Custom CSS','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<textarea name="'.WC_APC_OPTION_SETTINGS.'[verification_custom_css]" rows="4" style="width:100%;max-width:700px;font-family:monospace;">'.esc_textarea($o['verification_custom_css']).'</textarea><p class="description">'.esc_html__('Raw CSS appended to verification output. Avoid unsafe @imports.','wooauthentix').'</p>'; });
		self::add_field('wc_apc_verification_design','verification_custom_js',__('Custom JS','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<textarea name="'.WC_APC_OPTION_SETTINGS.'[verification_custom_js]" rows="4" style="width:100%;max-width:700px;font-family:monospace;">'.esc_textarea($o['verification_custom_js']).'</textarea><p class="description">'.esc_html__('Executed after render on verification pages. For trusted admins only.','wooauthentix').'</p>'; });
	}

	public static function sanitize_settings($input){ $out=[]; $out['show_buyer_name']=empty($input['show_buyer_name'])?0:1; $out['mask_buyer_name']=empty($input['mask_buyer_name'])?0:1; $out['show_purchase_date']=empty($input['show_purchase_date'])?0:1; $out['enable_rate_limit']=empty($input['enable_rate_limit'])?0:1; $out['rate_limit_max']=isset($input['rate_limit_max'])? max(1,intval($input['rate_limit_max'])):WC_APC_RATE_LIMIT_MAX; $out['rate_limit_window']=isset($input['rate_limit_window'])? max(30,intval($input['rate_limit_window'])):WC_APC_RATE_LIMIT_WINDOW; $out['enable_logging']=empty($input['enable_logging'])?0:1; $out['log_retention_days']=isset($input['log_retention_days'])? max(1,intval($input['log_retention_days'])):90; $out['hash_ip_addresses']=empty($input['hash_ip_addresses'])?0:1; $len=isset($input['code_length'])? intval($input['code_length']):12; if(function_exists('wc_apc_normalize_code_length')){ $len=wc_apc_normalize_code_length($len);} $out['code_length']=$len; $out['low_stock_threshold']=isset($input['low_stock_threshold'])? max(1,intval($input['low_stock_threshold'])):20; $out['low_stock_notify']=empty($input['low_stock_notify'])?0:1; $out['preprinted_mode']=empty($input['preprinted_mode'])?0:1; $out['verification_page_url']=isset($input['verification_page_url'])? esc_url_raw($input['verification_page_url']):''; $out['label_brand_text']=isset($input['label_brand_text'])? sanitize_text_field($input['label_brand_text']):''; $out['label_logo_id']=isset($input['label_logo_id'])? intval($input['label_logo_id']):0; $out['label_qr_size']=isset($input['label_qr_size'])? max(60,min(260,intval($input['label_qr_size']))):110; $out['label_columns']=isset($input['label_columns'])? max(0,min(10,intval($input['label_columns']))):0; $out['label_margin']=isset($input['label_margin'])? max(2,min(40,intval($input['label_margin']))):6; $out['label_logo_overlay']=empty($input['label_logo_overlay'])?0:1; $out['label_logo_overlay_scale']=isset($input['label_logo_overlay_scale'])? max(10,min(60,intval($input['label_logo_overlay_scale']))):28; $out['enable_server_side_qr']=empty($input['enable_server_side_qr'])?0:1; $out['label_enable_border']=empty($input['label_enable_border'])?0:1; $out['label_border_size']=isset($input['label_border_size'])? max(0,min(10,intval($input['label_border_size']))):1; $out['label_show_brand']=empty($input['label_show_brand'])?0:1; $out['label_show_logo']=empty($input['label_show_logo'])?0:1; $out['label_show_code']=empty($input['label_show_code'])?0:1; $out['label_show_site']=empty($input['label_show_site'])?0:1; $out['rest_api_key']=isset($input['rest_api_key'])? sanitize_text_field($input['rest_api_key']):''; // verification design
		$out['verification_heading']=isset($input['verification_heading'])? sanitize_text_field($input['verification_heading']):''; $out['verification_msg_first_time']=isset($input['verification_msg_first_time'])? sanitize_text_field($input['verification_msg_first_time']):''; $out['verification_msg_already_verified']=isset($input['verification_msg_already_verified'])? sanitize_text_field($input['verification_msg_already_verified']):''; $out['verification_msg_unassigned']=isset($input['verification_msg_unassigned'])? sanitize_text_field($input['verification_msg_unassigned']):''; $out['verification_msg_invalid_code']=isset($input['verification_msg_invalid_code'])? sanitize_text_field($input['verification_msg_invalid_code']):''; $out['verification_msg_invalid_format']=isset($input['verification_msg_invalid_format'])? sanitize_text_field($input['verification_msg_invalid_format']):''; $out['verification_msg_rate_limited']=isset($input['verification_msg_rate_limited'])? sanitize_text_field($input['verification_msg_rate_limited']):''; $out['verification_container_width']=isset($input['verification_container_width'])? max(320,min(1200,intval($input['verification_container_width']))):500; $color=isset($input['verification_bg_color'])? trim($input['verification_bg_color']):'#f5f5f7'; if(!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',$color)) $color='#f5f5f7'; $out['verification_bg_color']=$color; $out['verification_show_product_image']=empty($input['verification_show_product_image'])?0:1; $out['verification_custom_css']=isset($input['verification_custom_css'])? wp_kses_post($input['verification_custom_css']):''; $out['verification_custom_js']=isset($input['verification_custom_js'])? wp_kses_post($input['verification_custom_js']):''; return $out; }
	private static function add_field($section,$id,$title,$callback){ add_settings_field($id,$title,is_callable($callback)?$callback:function(){},'wc_apc_settings',$section); }
	public static function field_logo(){
		$opt = wc_apc_get_settings();
		$id = intval($opt['label_logo_id']);
		$img = $id ? wp_get_attachment_image($id,'thumbnail',false,['style'=>'max-width:80px;height:auto;display:block;margin-bottom:6px;']) : '';
		echo '<div id="wooauthentix-logo-preview">'.($img?$img:'').'</div>';
		echo '<input type="hidden" id="wooauthentix_label_logo_id" name="'.WC_APC_OPTION_SETTINGS.'[label_logo_id]" value="'.esc_attr($id).'" />';
		echo '<button type="button" class="button" id="wooauthentix_pick_logo">'.esc_html__('Select Logo','wooauthentix').'</button> ';
		echo '<button type="button" class="button" id="wooauthentix_remove_logo" '.($id?'':'style="display:none;"').'>'.esc_html__('Remove','wooauthentix').'</button>';
		echo '<p class="description">'.esc_html__('Optional logo above QR code.','wooauthentix').'</p>';
		$i18n = ['select'=>__('Select Logo','wooauthentix'),'use'=>__('Use this','wooauthentix')];
		$js  = 'jQuery(function($){var frame;var i18n='.wp_json_encode($i18n).';\n';
		$js .= '$("#wooauthentix_pick_logo").on("click",function(e){e.preventDefault();if(frame){frame.open();return;}';
		$js .= 'frame=wp.media({title:i18n.select,button:{text:i18n.use},multiple:false});';
		$js .= 'frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();';
		$js .= '$("#wooauthentix_label_logo_id").val(a.id);var url=(a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);';
		$js .= '$("#wooauthentix-logo-preview").html("<img src=\\""+url+"\\" style=\\"max-width:80px;height:auto;display:block;margin-bottom:6px;\\" />");';
		$js .= '$("#wooauthentix_remove_logo").show();});frame.open();});';
		$js .= '$("#wooauthentix_remove_logo").on("click",function(){$("#wooauthentix_label_logo_id").val(\"\");$("#wooauthentix-logo-preview").empty();$(this).hide();});});';
		echo '<script>'.$js.'</script>';
	}

	// ---------------- Page renderers -----------------
	public static function settings_page(){ if(!current_user_can('manage_woocommerce')) return; 
		// Consolidated tab design: fewer groups for simplicity
		$tabs=[
			'guide'=>__('Guide','wooauthentix'),
			'general'=>__('General','wooauthentix'), // privacy, security, logging, notifications
			'codes'=>__('Codes','wooauthentix'), // generation + preprinted
			'labels'=>__('Labels','wooauthentix'), // branding + layout + visibility
			'design'=>__('Verification Design','wooauthentix'), // new design tab
			'advanced'=>__('Advanced','wooauthentix')
		];
		// Map legacy individual tab slugs to new group slugs for backward compatibility
		$legacy_map=[
			'privacy'=>'general','security'=>'general','logging'=>'general','notifications'=>'general',
			'branding'=>'labels','layout'=>'labels','visibility'=>'labels','codes'=>'codes'
		];
		$req_tab=isset($_GET['tab'])? sanitize_key($_GET['tab']):'guide';
		if(!isset($tabs[$req_tab]) && isset($legacy_map[$req_tab])){ $req_tab=$legacy_map[$req_tab]; }
		if(!isset($tabs[$req_tab])) $req_tab='guide';
		$active=$req_tab;
		echo '<div class="wrap"><h1>'.esc_html__('WooAuthentix Settings','wooauthentix').'</h1>';
		// Optional note shown once about consolidation
		if(!get_transient('wc_apc_settings_tabs_consolidated')){ echo '<div class="notice notice-info" style="margin-top:10px;"><p>'.esc_html__('Settings tabs have been consolidated. Former individual tabs are now grouped under General, Codes, Labels, Advanced.','wooauthentix').'</p></div>'; set_transient('wc_apc_settings_tabs_consolidated',1, DAY_IN_SECONDS*7); }
		echo '<h2 class="nav-tab-wrapper">'; foreach($tabs as $slug=>$label){ $class='nav-tab'.($slug===$active?' nav-tab-active':''); echo '<a class="'.esc_attr($class).'" href="'.esc_url(add_query_arg(['tab'=>$slug])).'">'.esc_html($label).'</a>'; } echo '</h2>';
		if($active==='guide'){
			echo '<div id="wooauthentix-guide" style="background:#fff;border:1px solid #c3c4c7;padding:16px;margin:16px 0 24px;max-width:900px;">';
			echo '<h2 style="margin-top:0;">'.esc_html__('Quick Start Guide','wooauthentix').'</h2>';
			echo '<ol style="line-height:1.5;margin-left:20px;">'
				.'<li><strong>'.esc_html__('Generate Codes','wooauthentix').':</strong> '.esc_html__('Go to WooAuthentix > Codes to create a pool.','wooauthentix').'</li>'
				.'<li><strong>'.esc_html__('Create Verification Page','wooauthentix').':</strong> '.esc_html__('Add shortcode','wooauthentix').' <code>[wc_authentic_checker]</code></li>'
				.'<li><strong>'.esc_html__('Automatic Assignment','wooauthentix').':</strong> '.esc_html__('Codes assign when orders complete (or use Assign page).','wooauthentix').'</li>'
				.'<li><strong>'.esc_html__('Customer Verification','wooauthentix').':</strong> '.esc_html__('Users enter code or scan QR to verify.','wooauthentix').'</li>'
				.'<li><strong>'.esc_html__('Monitor & Refill','wooauthentix').':</strong> '.esc_html__('Dashboard & low‑stock emails prompt generation.','wooauthentix').'</li>'
			.'</ol>';
			echo '<p style="margin-top:12px;">'.esc_html__('Use remaining tabs to configure privacy, security, label appearance, and advanced options.','wooauthentix').'</p>';
			echo '</div>';
		} else {
			// Section mapping for consolidated groups
			$group_map=[
				'general'=>['wc_apc_privacy','wc_apc_security','wc_apc_logging','wc_apc_notifications'],
				'codes'=>['wc_apc_generation','wc_apc_preprinted'],
				'labels'=>['wc_apc_label_brand','wc_apc_label_layout','wc_apc_label_visibility'],
				'design'=>['wc_apc_verification_design'],
				'advanced'=>['wc_apc_advanced']
			];
			$sections=isset($group_map[$active])? $group_map[$active]:[];
			echo '<form method="post" action="options.php" style="margin-top:16px;max-width:940px;">';
			settings_fields('wc_apc_settings_group');
			global $wp_settings_sections,$wp_settings_fields;
			// Custom titles for consolidated output
			$title_map=[
				'wc_apc_privacy'=>__('Privacy Display','wooauthentix'),
				'wc_apc_security'=>__('Security & Rate Limiting','wooauthentix'),
				'wc_apc_logging'=>__('Logging','wooauthentix'),
				'wc_apc_notifications'=>__('Notifications','wooauthentix'),
				'wc_apc_generation'=>__('Code Generation','wooauthentix'),
				'wc_apc_preprinted'=>__('Preprinted Labels','wooauthentix'),
				'wc_apc_label_brand'=>__('Label Branding','wooauthentix'),
				'wc_apc_label_layout'=>__('Label Layout','wooauthentix'),
				'wc_apc_label_visibility'=>__('Label Visibility','wooauthentix'),
				'wc_apc_verification_design'=>__('Verification Page Design','wooauthentix')
			];
			foreach($sections as $section_id){
				echo '<h2 style="margin-top:32px;">'.esc_html(isset($title_map[$section_id])? $title_map[$section_id]:$section_id).'</h2>';
				if(isset($wp_settings_sections['wc_apc_settings'][$section_id]['callback'])){
					call_user_func($wp_settings_sections['wc_apc_settings'][$section_id]['callback'],$wp_settings_sections['wc_apc_settings'][$section_id]);
				}
				echo '<table class="form-table" role="presentation">';
				if(isset($wp_settings_fields['wc_apc_settings'][$section_id])){
					foreach($wp_settings_fields['wc_apc_settings'][$section_id] as $field){
						echo '<tr>';
						if(!empty($field['args']['label_for'])){
							echo '<th scope="row"><label for="'.esc_attr($field['args']['label_for']).'">'.esc_html($field['title']).'</label></th>';
						} else {
							echo '<th scope="row">'.esc_html($field['title']).'</th>';
						}
						echo '<td>';
						call_user_func($field['callback'],$field['args']);
						echo '</td></tr>';
					}
				}
				echo '</table>';
			}
			submit_button();
			echo '</form>';
		}
		echo '</div>';
	}

	public static function codes_page(){ if(!current_user_can('manage_woocommerce')) return; $products=get_posts(['post_type'=>'product','posts_per_page'=>1000,'post_status'=>'publish','orderby'=>'title','order'=>'ASC','fields'=>'ids']); $categories=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']); echo '<div class="wrap"><h1>'.esc_html__('Generate Authentic Codes','wooauthentix').'</h1><form method="post">'; wp_nonce_field('wc_apc_generate_codes_action','wc_apc_nonce'); echo '<table class="form-table"><tr><th><label for="product_id">'.esc_html__('Product (optional)','wooauthentix').'</label></th><td>'.self::product_dropdown('product_id','product_id',$products).' <p class="description">'.esc_html__('Leave blank to create generic codes usable for any product (tagged at assignment).','wooauthentix').'</p></td></tr><tr><th><label for="category_id">'.esc_html__('Category (optional batch)','wooauthentix').'</label></th><td><select name="category_id" id="category_id"><option value="">'.esc_html__('-- None --','wooauthentix').'</option>'; foreach($categories as $cat){ echo '<option value="'.esc_attr($cat->term_id).'">'.esc_html($cat->name).'</option>'; } echo '</select> <p class="description">'.esc_html__('If set, generates codes for every product in category (ignores generic option).','wooauthentix').'</p></td></tr><tr><th><label for="code_count">'.esc_html__('Number of Codes','wooauthentix').'</label></th><td><input type="number" name="code_count" id="code_count" value="100" min="1" max="10000" required></td></tr></table><p><input type="submit" name="wc_apc_generate_codes" class="button button-primary" value="'.esc_attr__('Generate Codes','wooauthentix').'" /></p></form>';
		if(isset($_POST['wc_apc_generate_codes'],$_POST['code_count']) && check_admin_referer('wc_apc_generate_codes_action','wc_apc_nonce')){ $code_count=intval($_POST['code_count']); $category_id=isset($_POST['category_id'])? intval($_POST['category_id']):0; $product_id=isset($_POST['product_id'])? intval($_POST['product_id']):0; if($code_count>0 && $code_count<=10000){ if($category_id>0){ $cat_products=get_posts(['post_type'=>'product','posts_per_page'=>1000,'post_status'=>'publish','fields'=>'ids','tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$category_id]]]); $total=0; foreach($cat_products as $pid){ $codes=wc_apc_generate_batch_codes($pid,$code_count); $total+=count($codes);} echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('Generated %d codes (per product) for category. Total new codes ~ %d','wooauthentix'), esc_html($code_count), esc_html($total)).'</p></div>'; } elseif($product_id>0){ $codes=wc_apc_generate_batch_codes($product_id,$code_count); echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('Generated %d product-specific codes (Product ID %d).','wooauthentix'), esc_html(count($codes)), esc_html($product_id)).'</p></div>'; } else { $codes=wc_apc_generate_batch_codes(null,$code_count); echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('Generated %d generic codes (no product).','wooauthentix'), esc_html(count($codes))).'</p></div>'; } } }
		require_once dirname(WC_APC_PLUGIN_FILE).'/admin-codes-table.php'; if(function_exists('wc_apc_all_codes_admin_page')) wc_apc_all_codes_admin_page(); echo '</div>';
	}
	private static function product_dropdown($name,$id,$product_ids){ $html='<select name="'.esc_attr($name).'" id="'.esc_attr($id).'">'; $html.='<option value="">-- Select Product --</option>'; foreach($product_ids as $pid){ $p=wc_get_product($pid); if(!$p) continue; $html.='<option value="'.esc_attr($pid).'">'.esc_html($p->get_name()).' (ID: '.$pid.')</option>'; } $html.='</select>'; return $html; }

	public static function assign_codes_page(){
		if(!current_user_can('manage_woocommerce')) return;
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $message=''; $error='';
		$debug = isset($_GET['wc_apc_debug']);
		if(!function_exists('wc_get_orders')){ echo '<div class="wrap"><h1>'.esc_html__('Assign / Override Codes','wooauthentix').'</h1><div class="notice notice-error"><p>'.esc_html__('WooCommerce not loaded.','wooauthentix').'</p></div></div>'; return; }
		// Process form
		if(isset($_POST['wc_apc_assign_submit']) && check_admin_referer('wc_apc_assign_codes','wc_apc_assign_nonce')){
			$order_id=intval($_POST['order_id']??0); $item_id=intval($_POST['item_id']??0); $new_code=sanitize_text_field($_POST['new_code']??''); $auto_pick=empty($_POST['manual_code']);
			if($order_id && $item_id){
				$order=wc_get_order($order_id);
				if($order){
					$item=$order->get_item($item_id);
					if($item){
						$current_meta=$item->get_meta(WC_APC_ITEM_META_KEY);
						// Normalize current codes to array
						if(is_array($current_meta)) { $current_codes=$current_meta; }
						elseif(is_string($current_meta) && $current_meta!=='') { if(strpos($current_meta,',')!==false){ $current_codes=array_filter(array_map('trim',explode(',',$current_meta))); } else { $current_codes=[$current_meta]; } }
						else { $current_codes=[]; }
						$qty = max(1,intval($item->get_quantity()));
						if($auto_pick){
							$product_id=$item->get_product_id();
							$needed = $qty - count($current_codes);
							if($needed <= 0){ $message = sprintf(__('All %d codes already assigned for this line item.','wooauthentix'), $qty); }
							else {
								$allocated=[]; $attempts=0; $max_attempts=$needed*6; // some slack for races
								while(count($allocated)<$needed && $attempts<$max_attempts){
									$attempts++;
									// Try product-specific first
									$row=$wpdb->get_row($wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0 ORDER BY id ASC LIMIT 1", $product_id));
									if($row){
										$aff=$wpdb->update($table,['status'=>1,'assigned_at'=>current_time('mysql')],['id'=>$row->id,'status'=>0]);
										if($aff){ $allocated[]=$row->code; continue; }
									}
									// Generic fallback
									$generic=$wpdb->get_row("SELECT id,code FROM $table WHERE product_id IS NULL AND status=0 ORDER BY id ASC LIMIT 1");
									if($generic){
										$aff=$wpdb->update($table,['product_id'=>$product_id,'status'=>1,'assigned_at'=>current_time('mysql')],['id'=>$generic->id,'status'=>0]);
										if($aff){ $allocated[]=$generic->code; continue; }
									}
									// Generate on demand if still needed
									if(function_exists('wc_apc_generate_batch_codes')){
										$new_batch=wc_apc_generate_batch_codes($product_id, max(1, $needed - count($allocated)) );
										if(!empty($new_batch)){
											foreach($new_batch as $nc){ $allocated[]=$nc; if(count($allocated)>=$needed) break; }
										}
									}
									// Break if nothing allocated this loop to avoid infinite loop
									if(empty($row) && empty($generic)){
										// no immediate sources; allow generation to have run once; then break next iteration
										if($attempts > $needed+2) break;
									}
								}
								if(!empty($allocated)){
									$current_codes = array_merge($current_codes, $allocated);
									// Trim any accidental over-allocation
									if(count($current_codes) > $qty) $current_codes = array_slice($current_codes,0,$qty);
									$item->update_meta_data(WC_APC_ITEM_META_KEY, $current_codes);
									$item->save();
									$message = sprintf(__('Assigned %1$d new code(s). Total codes for item: %2$d / %3$d.','wooauthentix'), count($allocated), count($current_codes), $qty);
								} else {
									$error = __('Insufficient unassigned codes and generation failed.','wooauthentix');
								}
							}
						}else{
							if($new_code===''){ $error=__('Manual code empty.','wooauthentix'); }
							else {
								if(!in_array($new_code,$current_codes,true)){
									$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE code=%s", $new_code));
									if(!$exists){
										$wpdb->insert($table,['code'=>$new_code,'product_id'=>$item->get_product_id(),'status'=>1,'created_at'=>current_time('mysql'),'assigned_at'=>current_time('mysql')]);
									}else{
										$wpdb->query($wpdb->prepare("UPDATE $table SET product_id=%d,status=1,assigned_at=NOW() WHERE code=%s", $item->get_product_id(), $new_code));
									}
									$current_codes[]=$new_code;
									$current_codes=array_values(array_unique($current_codes));
									$qty = max(1,intval($item->get_quantity()));
									if(count($current_codes)>$qty) $current_codes=array_slice($current_codes,0,$qty); // cap
									$item->update_meta_data(WC_APC_ITEM_META_KEY,$current_codes); $item->save();
									$message = sprintf(__('Added manual code. Total codes: %1$d / %2$d.','wooauthentix'), count($current_codes), $qty);
								} else {
									$message = __('Code already present for this item.','wooauthentix');
								}
							}
						}
						// Single-code legacy cleanup removed; we keep arrays now
					}else{ $error=__('Invalid order item.','wooauthentix'); }
				}else{ $error=__('Invalid order.','wooauthentix'); }
			}else{ $error=__('Order and item required.','wooauthentix'); }
		}
		// Bulk assign for entire order (codes for each quantity unit across items)
		if(isset($_POST['wc_apc_bulk_assign']) && check_admin_referer('wc_apc_bulk_assign_action','wc_apc_bulk_assign_nonce')){
			$bulk_order_id = intval($_POST['order_id']??0);
			if($bulk_order_id){
				$order = wc_get_order($bulk_order_id);
				if($order){
					global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE;
					$added_total=0; $skipped=0; $failed=0;
					$allocator = function($product_id,$need) use ($wpdb,$table){
						$allocated=[]; $attempts=0; $max_attempts=$need*6;
						while(count($allocated)<$need && $attempts<$max_attempts){
							$attempts++;
							$row=$wpdb->get_row($wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0 ORDER BY id ASC LIMIT 1", $product_id));
							if($row){
								$aff=$wpdb->update($table,['status'=>1,'assigned_at'=>current_time('mysql')],['id'=>$row->id,'status'=>0]);
								if($aff){ $allocated[]=$row->code; continue; }
							}
							$generic=$wpdb->get_row("SELECT id,code FROM $table WHERE product_id IS NULL AND status=0 ORDER BY id ASC LIMIT 1");
							if($generic){
								$aff=$wpdb->update($table,['product_id'=>$product_id,'status'=>1,'assigned_at'=>current_time('mysql')],['id'=>$generic->id,'status'=>0]);
								if($aff){ $allocated[]=$generic->code; continue; }
							}
							if(function_exists('wc_apc_generate_batch_codes') && (count($allocated)<$need)){
								$batch=wc_apc_generate_batch_codes($product_id, max(1,$need-count($allocated)) );
								if(!empty($batch)){
									foreach($batch as $c){ $allocated[]=$c; if(count($allocated)>=$need) break; }
								}
							}
							if(empty($row) && empty($generic) && count($allocated)==0 && $attempts>($need+2)) break; // nothing available
						}
						return $allocated;
					};
					foreach($order->get_items() as $oid=>$it){
						$qty=max(1,intval($it->get_quantity()));
						$meta=$it->get_meta(WC_APC_ITEM_META_KEY);
						if(is_array($meta)) $codes=$meta; elseif(is_string($meta) && $meta!==''){ $codes=strpos($meta,',')!==false? array_map('trim',explode(',',$meta)):[$meta]; } else $codes=[];
						$codes=array_filter(array_unique($codes));
						$needed=$qty - count($codes);
						if($needed<=0){ $skipped++; continue; }
						$product_id=$it->get_product_id();
						$new_codes=$allocator($product_id,$needed);
						if(!empty($new_codes)){
							$codes=array_merge($codes,$new_codes);
							if(count($codes)>$qty) $codes=array_slice($codes,0,$qty);
							$it->update_meta_data(WC_APC_ITEM_META_KEY,$codes); $it->save();
							$added_total += count($new_codes);
						}else{ $failed++; }
					}
					if($added_total>0){
						$message = sprintf(__('Bulk assigned %1$d codes. Items fully satisfied: %2$d. Failed: %3$d.','wooauthentix'), $added_total, $skipped, $failed);
					}else{
						$error = $failed? sprintf(__('Bulk assignment failed for %d items (no codes available).','wooauthentix'), $failed):__('Nothing to assign; all items satisfied.','wooauthentix');
					}
				}else{
					$error = __('Invalid order for bulk assign.','wooauthentix');
				}
			}
		}
		echo '<div class="wrap"><h1>'.esc_html__('Assign / Override Codes','wooauthentix').'</h1>';
		if($message) echo '<div class="notice notice-success"><p>'.wp_kses_post($message).'</p></div>';
		if($error) echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
		echo '<p>'.esc_html__('Browse orders (filter by status) then expand an order to assign or override codes per line item.','wooauthentix').'</p>';
		$allowed=[10,20,50,100]; $pp=isset($_GET['orders_per_page'])? intval($_GET['orders_per_page']):20; if(!in_array($pp,$allowed,true)) $pp=20; $page=max(1,isset($_GET['orders_paged'])? intval($_GET['orders_paged']):1); $search=isset($_GET['orders_search'])? sanitize_text_field($_GET['orders_search']):''; $sort_by=isset($_GET['orders_sort_by'])? sanitize_key($_GET['orders_sort_by']):'date'; if(!in_array($sort_by,['date','id'],true)) $sort_by='date'; $sort_dir=isset($_GET['orders_sort_dir'])? strtolower(sanitize_text_field($_GET['orders_sort_dir'])):'desc'; if(!in_array($sort_dir,['asc','desc'],true)) $sort_dir='desc'; $orderby=$sort_by==='id'? 'ID':'date';
		$all_statuses=wc_get_order_statuses(); $filter=isset($_GET['order_status'])? sanitize_key($_GET['order_status']):'all'; if($filter!=='all' && !isset($all_statuses[$filter])) $filter='all';
		$args=['limit'=>$pp,'page'=>$page,'paginate'=>true,'orderby'=>$orderby,'order'=>strtoupper($sort_dir)]; if($filter!=='all') $args['status']=$filter; if($search!==''){ if(ctype_digit($search)){ $o=wc_get_order(intval($search)); if($o){ $args['include']=[intval($search)]; $args['limit']=1; } else { $args['search']='*'.$search.'*'; $args['search_columns']=['billing_first_name','billing_last_name','billing_email','billing_company']; } } else { $args['search']='*'.$search.'*'; $args['search_columns']=['billing_first_name','billing_last_name','billing_email','billing_company']; } }
		$orders=[]; $total=0; $pages=1; $query_error='';
		try{
			$q=wc_get_orders($args);
			if(is_wp_error($q)){
				$query_error=$q->get_error_message();
			}elseif(is_array($q) && isset($q['orders'])){
				$orders=$q['orders']; $total=intval($q['total']); $pages=max(1,intval($q['pages']));
			}elseif(is_array($q)){
				$orders=$q; $total=count($q);
			}
		}catch(Throwable $e){ $query_error=$e->getMessage(); }
		if($debug){
			echo '<div class="notice notice-info"><p><strong>Args:</strong> '.esc_html(wp_json_encode($args)).'</p></div>';
			if($query_error) echo '<div class="notice notice-error"><p><strong>wc_get_orders error:</strong> '.esc_html($query_error).'</p></div>';
			else echo '<div class="notice notice-info"><p>Initial fetch count: '.intval($total).'</p></div>';
		}
		// Fallback simple query if nothing returned but no explicit error
		if(!$query_error && $total===0){
			$fallback = wc_get_orders(['limit'=>$pp,'orderby'=>'date','order'=>'DESC']);
			if(is_array($fallback) && !empty($fallback)){
				$orders=$fallback; $total=count($fallback); $pages=1; if($debug) echo '<div class="notice notice-warning"><p>Fallback query used. Found '.intval($total).' orders.</p></div>';
			}
		}
		echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:1100px;margin-bottom:18px;">';
		echo '<form method="get" style="margin:0 0 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">';
		echo '<input type="hidden" name="page" value="wc-apc-assign" />';
		echo '<label style="display:flex;flex-direction:column;font-size:12px;gap:2px;">'.esc_html__('Status','wooauthentix').'<select name="order_status"><option value="all" '.selected($filter,'all',false).'>'.esc_html__('All','wooauthentix').'</option>'; foreach($all_statuses as $k=>$lab){ echo '<option value="'.esc_attr($k).'" '.selected($filter,$k,false).'>'.esc_html($lab).'</option>'; } echo '</select></label>';
		echo '<label style="display:flex;flex-direction:column;font-size:12px;gap:2px;">'.esc_html__('Search','wooauthentix').'<input type="text" name="orders_search" value="'.esc_attr($search).'" placeholder="'.esc_attr__('Customer / email / order id','wooauthentix').'" /></label>';
		echo '<label style="display:flex;flex-direction:column;font-size:12px;gap:2px;">'.esc_html__('Sort By','wooauthentix').'<select name="orders_sort_by"><option value="date" '.selected($sort_by,'date',false).'>'.esc_html__('Date','wooauthentix').'</option><option value="id" '.selected($sort_by,'id',false).'>'.esc_html__('Order ID','wooauthentix').'</option></select></label>';
		echo '<label style="display:flex;flex-direction:column;font-size:12px;gap:2px;">'.esc_html__('Direction','wooauthentix').'<select name="orders_sort_dir"><option value="desc" '.selected($sort_dir,'desc',false).'>DESC</option><option value="asc" '.selected($sort_dir,'asc',false).'>ASC</option></select></label>';
		echo '<label style="display:flex;flex-direction:column;font-size:12px;gap:2px;">'.esc_html__('Per Page','wooauthentix').'<select name="orders_per_page">'; foreach($allowed as $sz){ echo '<option value="'.esc_attr($sz).'" '.selected($pp,$sz,false).'>'.esc_html($sz).'</option>'; } echo '</select></label>';
		echo '<button class="button button-primary" style="align-self:flex-start;margin-top:18px;">'.esc_html__('Filter','wooauthentix').'</button>'; if($search||$filter!=='all'){ echo '<a class="button" style="align-self:flex-start;margin-top:18px;" href="'.esc_url(remove_query_arg(['orders_search','order_status','orders_paged'])).'">'.esc_html__('Reset','wooauthentix').'</a>'; }
		echo '</form>';
		echo '<div style="font-size:12px;color:#555;margin-bottom:6px;">'.sprintf(esc_html__('%d orders','wooauthentix'), intval($total)).'</div>';
		if(empty($orders)) echo '<p style="margin:0;">'.esc_html__('No orders found for selection.','wooauthentix').'</p>';
		else {
			echo '<div class="wc-apc-accordion-list">';
			foreach($orders as $o){
				$oid=$o->get_id(); $date=$o->get_date_created()? $o->get_date_created()->date_i18n('Y-m-d H:i'):''; $cust=$o->get_formatted_billing_full_name(); if(!$cust) $cust=$o->get_billing_email(); $status=wc_get_order_status_name($o->get_status()); $items=$o->get_items(); $item_count=count($items); $total_html=$o->get_formatted_order_total();
				echo '<div class="wc-apc-acc-item" style="border:1px solid #ddd;margin-bottom:8px;border-radius:4px;">';
				echo '<div class="wc-apc-order-header" style="padding:8px 10px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;background:#f8f8f8;">';
				echo '<div><strong>#'.intval($oid).'</strong> '.esc_html($cust).' <span style="color:#666;">('.esc_html($status).')</span></div>';
				echo '<div style="font-size:12px;color:#555;">'.esc_html($date).' | '.intval($item_count).' '.esc_html__('items','wooauthentix').' | '.wp_kses_post($total_html).'</div>';
				echo '</div>';
					echo '<div class="wc-apc-order-items" style="display:none;padding:10px 12px;background:#fff;">';
					// Bulk assign form per order
					echo '<form method="post" style="margin:0 0 10px;">';
					wp_nonce_field('wc_apc_bulk_assign_action','wc_apc_bulk_assign_nonce');
					echo '<input type="hidden" name="order_id" value="'.intval($oid).'" />';
					echo '<button type="submit" name="wc_apc_bulk_assign" class="button button-secondary">'.esc_html__('Auto Assign Missing Codes For This Order','wooauthentix').'</button>';
					echo '</form>';
				if(empty($items)) echo '<p style="margin:0;">'.esc_html__('No line items.','wooauthentix').'</p>';
				else {
					echo '<table class="widefat striped" style="margin:0 0 10px;">';
					echo '<thead><tr><th>'.esc_html__('Item','wooauthentix').'</th><th style="width:110px;">'.esc_html__('Qty','wooauthentix').'</th><th>'.esc_html__('Current Code(s)','wooauthentix').'</th><th style="width:320px;">'.esc_html__('Assign / Override','wooauthentix').'</th></tr></thead><tbody>';
					foreach($items as $item_id=>$it){ $prod=$it->get_product(); $pname=$prod? $prod->get_name():__('(deleted product)','wooauthentix'); $qty=intval($it->get_quantity()); $meta_val=$it->get_meta(WC_APC_ITEM_META_KEY); $codes_list=[]; if(is_array($meta_val)) $codes_list=$meta_val; elseif(is_string($meta_val) && $meta_val!==''){ $codes_list=strpos($meta_val,',')!==false? array_map('trim',explode(',',$meta_val)):[$meta_val]; } $codes_list=array_filter(array_unique($codes_list)); $code_display=empty($codes_list)? '<span style="color:#999;">'.esc_html__('None','wooauthentix').'</span>' : esc_html(implode(', ',$codes_list));
						echo '<tr><td>'.esc_html(wp_trim_words($pname,8,'…')).' <span style="color:#888;">#'.intval($item_id).'</span></td><td>'.intval($qty).'</td><td>'.$code_display.'</td><td>';
						echo '<form method="post" class="wc-apc-inline-assign" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';
						wp_nonce_field('wc_apc_assign_codes','wc_apc_assign_nonce');
						echo '<input type="hidden" name="order_id" value="'.intval($oid).'" />';
						echo '<input type="hidden" name="item_id" value="'.intval($item_id).'" />';
						echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="0" checked /> '.esc_html__('Auto','wooauthentix').'</label>';
						echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="1" /> '.esc_html__('Manual','wooauthentix').'</label>';
						echo '<input type="text" name="new_code" placeholder="'.esc_attr__('ABC123','wooauthentix').'" style="width:120px;" />';
						echo '<button type="submit" name="wc_apc_assign_submit" class="button button-small">'.esc_html__('Assign','wooauthentix').'</button>';
						echo '</form>';
						echo '</td></tr>';
					}
					echo '</tbody></table>';
				}
				echo '</div></div>';
			}
			echo '</div>';
		}
		if($pages>1 && empty($args['include'])){ echo '<div class="tablenav"><div class="tablenav-pages">'; for($p=1;$p<=$pages;$p++){ $link=add_query_arg(['page'=>'wc-apc-assign','orders_paged'=>$p,'orders_search'=>$search,'order_status'=>$filter,'orders_sort_by'=>$sort_by,'orders_sort_dir'=>$sort_dir,'orders_per_page'=>$pp]); $cls=$p==$page?' class="page-numbers current"':' class="page-numbers"'; echo '<a'.$cls.' href="'.esc_url($link).'">'.$p.'</a> '; } echo '</div></div>'; }
		echo '<p style="margin:8px 0 0;font-size:11px;color:#555;">'.esc_html__('Click an order header to expand items. Use Auto for first unassigned product-specific code.','wooauthentix').'</p>';
		echo '</div>';
		echo '<script>jQuery(function($){$(document).on("click",".wc-apc-order-header",function(){var $it=$(this).next(".wc-apc-order-items");$it.slideToggle(150);});});</script>';
	}


	public static function logs_page(){ if(!current_user_can('manage_woocommerce')) return; global $wpdb; $log_table=$wpdb->prefix.WC_APC_LOG_TABLE; $code=isset($_GET['s_code'])? strtoupper(sanitize_text_field($_GET['s_code'])):''; $result_filter=isset($_GET['result'])? sanitize_text_field($_GET['result']):'all'; $date_from=isset($_GET['date_from'])? sanitize_text_field($_GET['date_from']):''; $date_to=isset($_GET['date_to'])? sanitize_text_field($_GET['date_to']):''; $full_export=isset($_GET['full_export']) && $_GET['full_export']==='1'; $page_num=max(1, isset($_GET['paged'])? intval($_GET['paged']):1); $per_page=50; $offset=($page_num-1)*$per_page; $where=[]; $params=[]; if($code){ $where[]='code = %s'; $params[]=$code; } if($result_filter && $result_filter!=='all'){ $where[]='result = %s'; $params[]=$result_filter; } if($date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from)){ $where[]='DATE(created_at) >= %s'; $params[]=$date_from; } if($date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)){ $where[]='DATE(created_at) <= %s'; $params[]=$date_to; } $where_sql=$where? ('WHERE '.implode(' AND ',$where)) : ''; $count_sql='SELECT COUNT(*) FROM '.$log_table.' '.$where_sql; $total=$params? $wpdb->get_var($wpdb->prepare($count_sql,...$params)):$wpdb->get_var($count_sql); $query_sql='SELECT * FROM '.$log_table.' '.$where_sql.' ORDER BY id DESC LIMIT %d OFFSET %d'; $params_q=$params; $params_q[]=$per_page; $params_q[]=$offset; $rows=$params_q? $wpdb->get_results($wpdb->prepare($query_sql,...$params_q)):$wpdb->get_results($wpdb->prepare($query_sql,$per_page,$offset)); if(isset($_GET['export']) && $_GET['export']==='csv' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'wc_apc_logs_export')){ if(ob_get_length()) ob_end_clean(); header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=wooauthentix-logs'.($full_export?'-full':'-page').'-'.date('Ymd-His').'.csv'); $out=fopen('php://output','w'); fputcsv($out,['ID','Code','Result','IP','User Agent','Created At']); if($full_export){ $chunk=1000; $exp_offset=0; while(true){ $sql='SELECT * FROM '.$log_table.' '.$where_sql.' ORDER BY id DESC LIMIT %d OFFSET %d'; $exp_params=$params; $exp_params[]=$chunk; $exp_params[]=$exp_offset; $batch=$exp_params? $wpdb->get_results($wpdb->prepare($sql,...$exp_params)):$wpdb->get_results($wpdb->prepare($sql,$chunk,$exp_offset)); if(empty($batch)) break; foreach($batch as $r){ fputcsv($out,[$r->id,$r->code,$r->result,$r->ip,$r->user_agent,$r->created_at]); } $exp_offset+=$chunk; if($exp_offset >= $total) break; @ob_flush(); @flush(); } } else { foreach($rows as $r){ fputcsv($out,[$r->id,$r->code,$r->result,$r->ip,$r->user_agent,$r->created_at]); } } fclose($out); exit; } $export_url=add_query_arg(array_merge($_GET,['export'=>'csv','_wpnonce'=>wp_create_nonce('wc_apc_logs_export'),'full_export'=>null])); $full_export_url=add_query_arg(array_merge($_GET,['export'=>'csv','_wpnonce'=>wp_create_nonce('wc_apc_logs_export'),'full_export'=>'1'])); echo '<div class="wrap"><h1>'.esc_html__('Verification Logs','wooauthentix').'</h1><form method="get" style="margin:1em 0; display:flex; flex-wrap:wrap; gap:8px; align-items:center;"><input type="hidden" name="page" value="wc-apc-logs" /><input type="text" name="s_code" placeholder="'.esc_attr__('Code','wooauthentix').'" value="'.esc_attr($code).'" /> <select name="result">'; $results=['all','invalid_format','invalid_code','unassigned','verified','already_verified','rate_limited']; foreach($results as $res){ echo '<option value="'.esc_attr($res).'" '.selected($res,$result_filter,false).'>'.esc_html(ucwords(str_replace('_',' ',$res))).'</option>'; } echo '</select> <label>'.esc_html__('From','wooauthentix').' <input type="date" name="date_from" value="'.esc_attr($date_from).'" /></label><label>'.esc_html__('To','wooauthentix').' <input type="date" name="date_to" value="'.esc_attr($date_to).'" /></label><button class="button">'.esc_html__('Filter','wooauthentix').'</button> <a class="button" href="'.esc_url($export_url).'">'.esc_html__('Export Page CSV','wooauthentix').'</a><a class="button button-secondary" href="'.esc_url($full_export_url).'">'.esc_html__('Full Export CSV','wooauthentix').'</a></form><table class="widefat fixed striped"><thead><tr><th>'.esc_html__('ID','wooauthentix').'</th><th>'.esc_html__('Code','wooauthentix').'</th><th>'.esc_html__('Result','wooauthentix').'</th><th>'.esc_html__('IP','wooauthentix').'</th><th>'.esc_html__('User Agent','wooauthentix').'</th><th>'.esc_html__('Created','wooauthentix').'</th></tr></thead><tbody>'; if(empty($rows)){ echo '<tr><td colspan="6">'.esc_html__('No log entries.','wooauthentix').'</td></tr>'; } else { foreach($rows as $r){ echo '<tr><td>'.intval($r->id).'</td><td>'.esc_html($r->code).'</td><td>'.esc_html($r->result).'</td><td>'.esc_html($r->ip).'</td><td>'.esc_html(mb_strimwidth($r->user_agent,0,60,'…')).'</td><td>'.esc_html($r->created_at).'</td></tr>'; } } echo '</tbody></table>'; $total_pages=ceil($total/$per_page); if($total_pages>1){ echo '<div class="tablenav"><div class="tablenav-pages">'; for($p=1;$p<=$total_pages;$p++){ $u=esc_url(add_query_arg(array_merge($_GET,['paged'=>$p]))); $cls=$p==$page_num?' class="page-numbers current"':' class="page-numbers"'; echo '<a'.$cls.' href="'.$u.'">'.$p.'</a> '; } echo '</div></div>'; } echo '</div>'; }

	public static function dashboard_page(){ if(!current_user_can('manage_woocommerce')) return; global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table"); $unassigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=0"); $assigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=1"); $verified=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=2"); $recent_verified=$wpdb->get_results("SELECT code, product_id, verified_at FROM $table WHERE status=2 AND verified_at IS NOT NULL ORDER BY verified_at DESC LIMIT 10"); $low_stock=$wpdb->get_results("SELECT product_id, COUNT(*) cnt FROM $table WHERE status=0 GROUP BY product_id HAVING cnt < 20 ORDER BY cnt ASC LIMIT 15"); echo '<div class="wrap"><h1>'.esc_html__('Authenticity Dashboard','wooauthentix').'</h1><div style="display:flex; gap:16px; flex-wrap:wrap;">'; $cards=[['label'=>__('Total Codes','wooauthentix'),'value'=>$total,'color'=>'#444'],['label'=>__('Unassigned','wooauthentix'),'value'=>$unassigned,'color'=>'green'],['label'=>__('Assigned','wooauthentix'),'value'=>$assigned,'color'=>'orange'],['label'=>__('Verified','wooauthentix'),'value'=>$verified,'color'=>'blue']]; foreach($cards as $c){ echo '<div style="flex:1;min-width:180px;border:1px solid #ddd;padding:12px;border-radius:6px;"><div style="font-size:13px;color:#666;">'.esc_html($c['label']).'</div><div style="font-size:24px;font-weight:bold;color:'.esc_attr($c['color']).';">'.esc_html($c['value']).'</div></div>'; } echo '</div><h2 style="margin-top:2em;">'.esc_html__('Recently Verified','wooauthentix').'</h2><table class="widefat striped"><thead><tr><th>'.esc_html__('Code','wooauthentix').'</th><th>'.esc_html__('Product','wooauthentix').'</th><th>'.esc_html__('Verified At','wooauthentix').'</th></tr></thead><tbody>'; if(empty($recent_verified)){ echo '<tr><td colspan="3">'.esc_html__('None','wooauthentix').'</td></tr>'; } else { foreach($recent_verified as $rv){ $p=wc_get_product($rv->product_id); $name=$p? $p->get_name():__('Unknown','wooauthentix'); echo '<tr><td>'.esc_html($rv->code).'</td><td>'.esc_html(wp_trim_words($name,6,'…')).'</td><td>'.esc_html($rv->verified_at).'</td></tr>'; } } echo '</tbody></table><h2 style="margin-top:2em;">'.esc_html__('Low Unassigned Stock (<20)','wooauthentix').'</h2><table class="widefat striped"><thead><tr><th>'.esc_html__('Product','wooauthentix').'</th><th>'.esc_html__('Unassigned Codes','wooauthentix').'</th></tr></thead><tbody>'; if(empty($low_stock)){ echo '<tr><td colspan="2">'.esc_html__('None','wooauthentix').'</td></tr>'; } else { foreach($low_stock as $ls){ $p=wc_get_product($ls->product_id); $name=$p? $p->get_name():__('Unknown','wooauthentix'); echo '<tr><td>'.esc_html(wp_trim_words($name,8,'…')).' (#'.intval($ls->product_id).')</td><td>'.intval($ls->cnt).'</td></tr>'; } } echo '</tbody></table></div>'; }

	public static function labels_page(){
		if(!current_user_can('manage_woocommerce')) return;
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $settings=wc_apc_get_settings();
		$qr_filter = isset($_GET['qr_filter'])? sanitize_key($_GET['qr_filter']):'all';
		if(!in_array($qr_filter,['all','generated','not_generated'],true)) $qr_filter='all';
		// Optional: filter by specific order to show that order's assigned/verified codes for label re-printing
		$order_id = isset($_GET['order_id'])? intval($_GET['order_id']):0; if($order_id < 0) $order_id = 0;
		$allowed_page_sizes=[50,100,500,1000];
		$per_page = isset($_GET['per_page'])? intval($_GET['per_page']):50; if(!in_array($per_page,$allowed_page_sizes,true)) $per_page=50;
		$page = max(1, isset($_GET['paged'])? intval($_GET['paged']):1); $offset=($page-1)*$per_page;
		$selected_codes = isset($_POST['label_codes'])? array_map('sanitize_text_field',(array)$_POST['label_codes']):[]; $generate_sheet = isset($_POST['wc_apc_generate_labels']);
		echo '<div class="wrap"><h1>'.esc_html__('QR Labels','wooauthentix').'</h1>';
		if(!class_exists('Endroid\\QrCode\\Builder\\Builder') && !empty($settings['enable_server_side_qr'])){
			echo '<div class="notice notice-warning"><p><strong>'.esc_html__('Server-side QR fallback active (placeholder).','wooauthentix').'</strong> '.esc_html__('Install endroid/qr-code for production-grade server QR rendering or disable fallback in Settings > Advanced.','wooauthentix').'</p></div>';
		}
		$help_msg = $order_id? __('Showing codes for Order ID ','wooauthentix').$order_id : __('Select unassigned codes across all products OR enter an Order ID to re-print assigned codes, then generate a printable / PDF QR label sheet.','wooauthentix');
		echo '<p>'.esc_html($help_msg).'</p>';
		echo '<form method="get" style="margin:1em 0;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">';
		echo '<input type="hidden" name="page" value="wc-apc-labels" />';
		echo '<label>'.esc_html__('Order ID (optional)','wooauthentix').'<br/><input type="number" min="0" name="order_id" value="'.esc_attr($order_id).'" style="width:110px;" placeholder="1234" /></label>';
		echo '<label>'.esc_html__('QR Status','wooauthentix').'<br/><select name="qr_filter">'; $opts=['all'=>__('All','wooauthentix'),'generated'=>__('Generated','wooauthentix'),'not_generated'=>__('Not Generated','wooauthentix')]; foreach($opts as $val=>$lab){ echo '<option value="'.esc_attr($val).'" '.selected($qr_filter,$val,false).'>'.esc_html($lab).'</option>'; } echo '</select></label>';
		echo '<label>'.esc_html__('Per Page','wooauthentix').'<br/><select name="per_page">'; foreach($allowed_page_sizes as $sz){ echo '<option value="'.esc_attr($sz).'" '.selected($per_page,$sz,false).'>'.esc_html($sz).'</option>'; } echo '</select></label>';
		echo '<button class="button button-primary">'.esc_html__('Refresh','wooauthentix').'</button></form>';
		// Base where: unassigned codes OR specific order codes
		if($order_id){
			$where_base = $wpdb->prepare('order_id = %d', $order_id);
		} else {
			$where_base = 'status=0';
		}
		$where_qr=''; if($qr_filter==='generated') $where_qr=' AND qr_label_generated=1'; elseif($qr_filter==='not_generated') $where_qr=' AND (qr_label_generated=0 OR qr_label_generated IS NULL)';
		$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_base $where_qr");
		$rows=$wpdb->get_results($wpdb->prepare("SELECT id,code,product_id,qr_label_generated,qr_generated_at FROM $table WHERE $where_base $where_qr ORDER BY id ASC LIMIT %d OFFSET %d",$per_page,$offset));
		if($generate_sheet && !empty($selected_codes)){
			check_admin_referer('wc_apc_generate_labels','wc_apc_generate_labels_nonce'); $ph=implode(',',array_fill(0,count($selected_codes),'%s')); $now=current_time('mysql');
			$wpdb->query($wpdb->prepare("UPDATE $table SET qr_label_generated=1, qr_generated_at=%s WHERE code IN ($ph)",$now,...$selected_codes));
			$rows=$wpdb->get_results($wpdb->prepare("SELECT id,code,product_id,qr_label_generated,qr_generated_at FROM $table WHERE $where_base $where_qr ORDER BY id ASC LIMIT %d OFFSET %d",$per_page,$offset));
			echo '<div class="notice notice-success"><p>'.esc_html__('QR label sheet generated; statuses updated.','wooauthentix').'</p></div>';
		}
		echo '<form method="post" style="margin-top:12px;">'; wp_nonce_field('wc_apc_generate_labels','wc_apc_generate_labels_nonce'); echo '<input type="hidden" name="qr_filter" value="'.esc_attr($qr_filter).'" />'; echo '<input type="hidden" name="per_page" value="'.esc_attr($per_page).'" />'; echo '<input type="hidden" name="order_id" value="'.esc_attr($order_id).'" />'; $select_all_js="jQuery('.wc-apc-label-code-cb').prop('checked',this.checked);"; echo '<table class="widefat striped"><thead><tr><th style="width:28px;"><input type="checkbox" onclick="'.esc_attr($select_all_js).'" /></th><th>'.esc_html__('Code','wooauthentix').'</th><th>'.esc_html__('Product','wooauthentix').'</th><th>'.esc_html__('QR Status','wooauthentix').'</th><th>'.esc_html__('Generated At','wooauthentix').'</th></tr></thead><tbody>';
		if(empty($rows)){ $empty_msg = $order_id? __('No codes found for this order.','wooauthentix'):__('No unassigned codes found.','wooauthentix'); echo '<tr><td colspan="5">'.esc_html($empty_msg).'</td></tr>'; } else { foreach($rows as $r){ $pname=$r->product_id? (($p=wc_get_product($r->product_id))? wp_trim_words($p->get_name(),6,'…'):__('Unknown','wooauthentix')):__('Generic','wooauthentix'); echo '<tr><td><input type="checkbox" class="wc-apc-label-code-cb" name="label_codes[]" value="'.esc_attr($r->code).'" /></td><td>'.esc_html($r->code).'</td><td>'.esc_html($pname).'</td><td>'.($r->qr_label_generated? '<span style="color:green;font-weight:600;">'.esc_html__('Generated','wooauthentix').'</span>':'<span style="color:#999;">'.esc_html__('Not Generated','wooauthentix').'</span>').'</td><td>'.($r->qr_generated_at? esc_html($r->qr_generated_at):'').'</td></tr>'; } }
		echo '</tbody></table><p style="margin-top:10px;"><button type="submit" class="button button-primary" name="wc_apc_generate_labels" value="1">'.esc_html__('Generate QR Sheet for Selected','wooauthentix').'</button></p>';
		if($generate_sheet && !empty($selected_codes)){
			$brand_text=$settings['label_brand_text']? $settings['label_brand_text']:get_bloginfo('name'); $logo_id=intval($settings['label_logo_id']); $logo_src=$logo_id? wp_get_attachment_image_src($logo_id,'medium'):false; $qr_size=intval($settings['label_qr_size']); $cols=intval($settings['label_columns']); $margin=intval($settings['label_margin']); $grid_cols_css=$cols>0? ('grid-template-columns:repeat('.$cols.',1fr);'):'grid-template-columns:repeat(auto-fill,minmax(180px,1fr));'; $border_css=$settings['label_enable_border']? (intval($settings['label_border_size']).'px solid #111'):'none'; $base=$settings['verification_page_url']? $settings['verification_page_url']:home_url('/'); echo '<style>@media print{body{margin:0;} .wooauthentix-label{page-break-inside:avoid;}} .wooauth-grid{display:grid;'.$grid_cols_css.'gap:'.$margin.'px;margin-top:24px;} .wooauthentix-label{border:'.$border_css.';padding:6px;text-align:center;font:11px/1.3 sans-serif;position:relative;} .wooauthentix-label canvas, .wooauthentix-label img.qr-img{width:'.$qr_size.'px;height:'.$qr_size.'px;image-rendering:pixelated;image-rendering:crisp-edges;} .wooauthentix-label img.logo-top{max-width:70px;height:auto;display:block;margin:0 auto 4px;} .wooauth-controls{margin:12px 0;} .wooauth-note{font-size:11px;color:#555;margin-top:8px;} #wooauthentix_pdf_status{margin-left:10px;font-style:italic;font-size:11px;}</style>'; echo '<div class="wooauth-controls"><button type="button" class="button" id="wooauthentix_print_btn">'.esc_html__('Print','wooauthentix').'</button> <button type="button" class="button" id="wooauthentix_pdf_btn">'.esc_html__('Download PDF','wooauthentix').'</button> <label style="margin-left:8px;">'.esc_html__('Paper','wooauthentix').'<br/><select id="wooauthentix_paper"><option value="a4-p">A4 P</option><option value="a4-l">A4 L</option><option value="letter-p">Letter P</option><option value="letter-l">Letter L</option></select></label> <button type="button" class="button" id="wooauthentix_purge_cache_btn" style="margin-left:8px;">'.esc_html__('Purge Cache','wooauthentix').'</button><span id="wooauthentix_pdf_status"></span></div>'; echo '<div class="wooauth-grid" id="wooauthentix_label_grid" data-product="0" data-brand="'.esc_attr($brand_text).'" data-host="'.esc_attr(parse_url(home_url(),PHP_URL_HOST)).'" data-logo="'.(($logo_src && $settings['label_show_logo'])? esc_url($logo_src[0]):'').'" data-qr-size="'.esc_attr($qr_size).'" data-logo-overlay="'.(($settings['label_logo_overlay'] && $settings['label_show_logo'])?1:0).'" data-logo-scale="'.esc_attr($settings['label_logo_overlay_scale']).'" data-server-fallback="'.esc_attr($settings['enable_server_side_qr']).'" data-ajax-url="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('wooauth_qr')).'" data-show-brand="'.esc_attr($settings['label_show_brand']).'" data-show-code="'.esc_attr($settings['label_show_code']).'" data-show-site="'.esc_attr($settings['label_show_site']).'">'; foreach($selected_codes as $code){ $code_clean=sanitize_text_field($code); $url=add_query_arg('code',$code_clean,$base); echo '<div class="wooauthentix-label" data-code="'.esc_attr($code_clean).'" data-url="'.esc_attr($url).'">'; if($logo_src && $settings['label_show_logo']) echo '<img src="'.esc_url($logo_src[0]).'" class="logo-top" alt="logo" />'; if($settings['label_show_brand']) echo '<div class="brand" style="font-weight:bold;margin-bottom:2px;">'.esc_html($brand_text).'</div>'; echo '<div class="qr" style="width:'.$qr_size.'px;height:'.$qr_size.'px;margin:0 auto 4px;">'.esc_html__('QR','wooauthentix').'</div>'; if($settings['label_show_code']) echo '<div class="code" style="margin-top:2px;font-size:10px;">'.esc_html($code_clean).'</div>'; if($settings['label_show_site']) echo '<div class="site" style="font-size:9px;color:#555;">'.esc_html(parse_url(home_url(),PHP_URL_HOST)).'</div>'; echo '</div>'; } echo '</div><p class="wooauth-note"><em>'.esc_html__('Use Print or Download PDF to output selected labels.','wooauthentix').'</em></p>'; }
		$pages=max(1,ceil($total/$per_page)); if($pages>1){ echo '<div class="tablenav"><div class="tablenav-pages">'; for($p=1;$p<=$pages;$p++){ $u=esc_url(add_query_arg(['paged'=>$p,'qr_filter'=>$qr_filter,'per_page'=>$per_page])); $cls=$p==$page?' class="page-numbers current"':' class="page-numbers"'; echo '<a'.$cls.' href="'.$u.'">'.$p.'</a> '; } echo '</div></div>'; }
		echo '</form></div>';
	}


	public static function tools_page(){
		if(!current_user_can('manage_woocommerce')) return; global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $log_table=$wpdb->prefix.WC_APC_LOG_TABLE; $notices=[]; $errors=[]; $action_performed='';
		if(isset($_POST['wc_apc_tools_action']) && check_admin_referer('wc_apc_tools_action','wc_apc_tools_nonce')){
			$action_performed=sanitize_key($_POST['wc_apc_tools_action']);
			switch($action_performed){
				case 'recount':
					$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
					$unassigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=0");
					$assigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=1");
					$verified=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=2");
					$notices[] = sprintf(esc_html__('Recount complete. Total: %1$d | Unassigned: %2$d | Assigned: %3$d | Verified: %4$d','wooauthentix'), $total,$unassigned,$assigned,$verified);
					break;
				case 'audit_orphans':
					$orphans=$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN (1,2) AND (order_id IS NULL OR order_id=0)");
					$generic_assigned=$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN (1,2) AND product_id IS NULL");
					$sample=$wpdb->get_col("SELECT code FROM $table WHERE status IN (1,2) AND (order_id IS NULL OR order_id=0) LIMIT 10");
					$notices[] = sprintf(esc_html__('Audit: %1$d assigned/verified codes lack order reference; %2$d have no product tag (should be 0 post-assignment). Sample: %3$s','wooauthentix'), intval($orphans), intval($generic_assigned), $sample? esc_html(implode(', ',$sample)) : esc_html__('(none)','wooauthentix'));
					break;
				case 'repair_mismatch':
					// Fix where status=2 but is_used=0
					$fixed1=$wpdb->query("UPDATE $table SET is_used=1, used_at=IF(used_at IS NULL, verified_at, used_at) WHERE status=2 AND is_used=0");
					// Fix where status<2 but is_used=1
					$fixed2=$wpdb->query("UPDATE $table SET is_used=0 WHERE status<2 AND is_used=1");
					$notices[] = sprintf(esc_html__('Repair complete. Set used=1 on %d verified rows; cleared incorrect used flag on %d rows.','wooauthentix'), intval($fixed1), intval($fixed2));
					break;
				case 'prune_logs_now':
					if(method_exists('WC_APC_Verification_Module','prune_logs')){ WC_APC_Verification_Module::prune_logs(); $remaining=(int)$wpdb->get_var("SELECT COUNT(*) FROM $log_table"); $notices[] = sprintf(esc_html__('Log pruning executed. Remaining log rows: %d','wooauthentix'), $remaining); } else { $errors[] = esc_html__('Prune method not available.','wooauthentix'); }
					break;
				case 'flush_rate_limits':
						// Remove transients (and their timeouts) starting with wooauthentix_rl_
						$like1='_transient_wooauthentix_rl_%';
						$like2='_transient_timeout_wooauthentix_rl_%';
						$option_names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like1,$like2));
						$flushed=0; foreach($option_names as $on){
							if(strpos($on,'_transient_timeout_')===0){ $key=str_replace('_transient_timeout_','',$on); delete_transient($key); $flushed++; }
							elseif(strpos($on,'_transient_')===0){ $key=str_replace('_transient_','',$on); delete_transient($key); $flushed++; }
						}
						$notices[] = sprintf(esc_html__('Flushed %d rate limit transients (and timeouts).','wooauthentix'), intval($flushed));
						break;
					case 'repair_generic_mismatch':
						$affected=$wpdb->query("UPDATE $table SET status=0, order_id=NULL, order_item_id=NULL, assigned_at=NULL WHERE product_id IS NULL AND status IN (1,2)");
						$notices[] = sprintf(esc_html__('Reclassified %d generic assigned/verified anomalies back to unassigned.','wooauthentix'), intval($affected));
						break;
				default:
					$errors[] = esc_html__('Unknown action.','wooauthentix');
			}
		}
		echo '<div class="wrap"><h1>'.esc_html__('WooAuthentix Tools','wooauthentix').'</h1>';
		foreach($notices as $m){ echo '<div class="notice notice-success"><p>'.esc_html($m).'</p></div>'; }
		foreach($errors as $m){ echo '<div class="notice notice-error"><p>'.esc_html($m).'</p></div>'; }
		echo '<p>'.esc_html__('Run maintenance utilities below. All actions are immediate and logged only via on-screen notice.','wooauthentix').'</p>';
		echo '<form method="post" style="background:#fff;border:1px solid #c3c4c7;padding:16px;max-width:760px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
		wp_nonce_field('wc_apc_tools_action','wc_apc_tools_nonce');
		$buttons=[
			['recount',__('Recount Status Aggregates','wooauthentix'),__('Compute totals for each status.','wooauthentix')],
			['audit_orphans',__('Audit Orphaned Assigned Codes','wooauthentix'),__('Find status 1/2 codes lacking order id.','wooauthentix')],
			['repair_mismatch',__('Repair Status / is_used Mismatches','wooauthentix'),__('Synchronize status=2 with used flag.','wooauthentix')],
			['prune_logs_now',__('Force Log Pruning Run','wooauthentix'),__('Apply retention policy immediately.','wooauthentix')],
			['flush_rate_limits',__('Flush Rate Limit Transients','wooauthentix'),__('Clear all current rate limit counters.','wooauthentix')],
			['repair_generic_mismatch',__('Repair Generic Assignment Anomalies','wooauthentix'),__('Reclassify any generic (NULL product) rows stuck in assigned/verified.','wooauthentix')],
		];
		foreach($buttons as $b){
			list($val,$label,$desc)=$b;
			echo '<div style="border:1px solid #ddd;padding:12px;border-radius:4px;background:#f9f9f9;">';
			echo '<h3 style="margin:0 0 8px;font-size:14px;">'.esc_html($label).'</h3>';
			echo '<p style="margin:0 0 8px;font-size:12px;color:#555;">'.esc_html($desc).'</p>';
			echo '<button type="submit" name="wc_apc_tools_action" value="'.esc_attr($val).'" class="button button-secondary">'.esc_html__('Run','wooauthentix').'</button>';
			echo '</div>';
		}
		echo '</form>';
		echo '<p style="margin-top:18px;font-size:11px;color:#666;">'.esc_html__('All operations are idempotent and safe to re-run; always take a database backup before large-scale repairs in production.','wooauthentix').'</p>';
		echo '</div>';
	}

	// ---------------- Assets & AJAX -----------------
	public static function enqueue_settings_assets(){ if (empty($_GET['page']) || $_GET['page'] !== 'wc-apc-settings') return; if (function_exists('wp_enqueue_media')) { wp_enqueue_media(); } wp_enqueue_script('wooauthentix-qrcode','https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',[],WC_APC_VERSION,true); wp_enqueue_script('wooauthentix-qrious','https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js',[],WC_APC_VERSION,true); wp_enqueue_script('wooauthentix-settings-preview', plugin_dir_url(WC_APC_PLUGIN_FILE).'assets/settings-preview.js', ['jquery','wooauthentix-qrcode','wooauthentix-qrious'], WC_APC_VERSION, true); }
	public static function enqueue_assign_assets(){ if (empty($_GET['page']) || $_GET['page']!=='wc-apc-assign') return; wp_enqueue_script('jquery-ui-autocomplete'); }
	public static function ajax_item_search(){ if (!current_user_can('manage_woocommerce')) wp_send_json_error('forbidden',403); check_ajax_referer('wc_apc_assign_codes','nonce'); $order_id=isset($_GET['order_id'])? intval($_GET['order_id']):0; $term=isset($_GET['term'])? sanitize_text_field(wp_unslash($_GET['term'])):''; if(!$order_id){ wp_send_json_success([]);} $order=wc_get_order($order_id); if(!$order){ wp_send_json_success([]);} $matches=[]; foreach($order->get_items() as $item_id=>$item){ $product=$item->get_product(); $name=$product? $product->get_name():__('(deleted product)','wooauthentix'); $code=$item->get_meta(WC_APC_ITEM_META_KEY); $label=$name.' #'.$item_id; if($code) $label.=' ['.$code.']'; if($term==='' || stripos($name,$term)!==false || ($code && stripos($code,$term)!==false) || stripos((string)$item_id,$term)!==false){ $matches[]=['label'=>$label,'value'=>$item_id]; } } wp_send_json($matches); }
}

WC_APC_Admin_Pages_Module::init();
