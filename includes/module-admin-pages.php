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
		// Simplified: Dashboard, Codes, Assign, Settings (Tools removed, Logs embedded in dashboard)
		add_submenu_page('wc-apc-dashboard', __('Authenticity Codes','wooauthentix'), __('Codes','wooauthentix'), 'manage_woocommerce', 'wc-apc-codes', [__CLASS__,'codes_page']);
		add_submenu_page('wc-apc-dashboard', __('Assign / Override Codes','wooauthentix'), __('Assign/Override','wooauthentix'), 'manage_woocommerce', 'wc-apc-assign', [__CLASS__,'assign_codes_page']);
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
	public static function register_settings(){
		// Base option registration
		register_setting('wc_apc_settings', WC_APC_OPTION_SETTINGS, [__CLASS__,'sanitize_settings']);
		add_settings_section('wc_apc_general', __('General Settings','wooauthentix'), function(){ echo '<p>'.esc_html__('Core behavior, privacy and security options.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_general','rest_api_key',__('REST API Key','wooauthentix'), function(){ $o=wc_apc_get_settings(); $val=isset($o['rest_api_key'])? $o['rest_api_key']:''; echo '<input type="text" id="wooauthentix_rest_api_key" name="'.WC_APC_OPTION_SETTINGS.'[rest_api_key]" value="'.esc_attr($val).'" style="width:280px;" /> <button type="button" class="button" id="wooauthentix_generate_key">'.esc_html__('Generate','wooauthentix').'</button> <button type="button" class="button" id="wooauthentix_clear_key" '.($val?'':'style="display:none;"').'>'.esc_html__('Clear','wooauthentix').'</button>';
			echo '</span><p class="description">'.esc_html__('Provide a secret; clients must send X-WooAuthentix-Key header. Blank = public endpoint.','wooauthentix').'</p>';
			$js="jQuery(function($){var fld=$('#wooauthentix_rest_api_key');$('#wooauthentix_generate_key').on('click',function(e){e.preventDefault();try{var bytes=new Uint8Array(24);window.crypto.getRandomValues(bytes);var hex='';for(var i=0;i<bytes.length;i++){hex+=('0'+bytes[i].toString(16)).slice(-2);}fld.val(hex.toUpperCase());$('#wooauthentix_clear_key').show();}catch(err){var t=Date.now().toString(16)+Math.random().toString(16).slice(2,18);fld.val(t.toUpperCase());$('#wooauthentix_clear_key').show();}});$('#wooauthentix_clear_key').on('click',function(){fld.val('');$(this).hide();});});";
			echo '<script>'.$js.'</script>';
		});
		add_settings_section('wc_apc_notifications', __('Notifications','wooauthentix'), function(){ echo '<p>'.esc_html__('Configure automatic alerts when unassigned code inventory runs low.','wooauthentix').'</p>'; }, 'wc_apc_settings');
		self::add_field('wc_apc_notifications','low_stock_threshold',__('Low Stock Threshold','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<input type="number" min="1" name="'.WC_APC_OPTION_SETTINGS.'[low_stock_threshold]" value="'.esc_attr($o['low_stock_threshold']).'" />'; });
		self::add_field('wc_apc_notifications','low_stock_notify',__('Email Notification','wooauthentix'), function(){ $o=wc_apc_get_settings(); echo '<label><input type="checkbox" name="'.WC_APC_OPTION_SETTINGS.'[low_stock_notify]" value="1" '.checked(1,$o['low_stock_notify'],false).' /> '.__('Send admin email when a product falls below threshold','wooauthentix').'</label>'; });
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
			settings_fields('wc_apc_settings');
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

	public static function codes_page(){ if(!current_user_can('manage_woocommerce')) return; echo '<div class="wrap"><h1>'.esc_html__('Generate Authentic Codes','wooauthentix').'</h1><form method="post">'; wp_nonce_field('wc_apc_generate_codes_action','wc_apc_nonce'); echo '<table class="form-table"><tr><th><label for="code_count">'.esc_html__('Number of Codes','wooauthentix').'</label></th><td><input type="number" name="code_count" id="code_count" value="100" min="1" max="10000" required></td></tr></table><p><input type="submit" name="wc_apc_generate_codes" class="button button-primary" value="'.esc_attr__('Generate Codes','wooauthentix').'" /></p></form>';
		if(isset($_POST['wc_apc_generate_codes'],$_POST['code_count']) && check_admin_referer('wc_apc_generate_codes_action','wc_apc_nonce')){ $code_count=intval($_POST['code_count']); if($code_count>0 && $code_count<=10000){ $codes=wc_apc_generate_batch_codes(null,$code_count); echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('Generated %d generic codes.','wooauthentix'), esc_html(count($codes))).'</p></div>'; } }
		require_once dirname(WC_APC_PLUGIN_FILE).'/admin-codes-table.php'; if(function_exists('wc_apc_all_codes_admin_page')) wc_apc_all_codes_admin_page(); echo '</div>';
	}
	// Removed product/category specific generation UI; all codes are generic now.

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
							$current_codes = function_exists('wc_apc_parse_codes_meta') ? wc_apc_parse_codes_meta($item->get_meta(WC_APC_ITEM_META_KEY)) : [];
						$qty = max(1,intval($item->get_quantity()));
						$slot_mode = isset($_POST['code_slot']);
						$slot_index = $slot_mode ? max(0,min($qty-1,intval($_POST['code_slot']))) : null;
						// Fetch statuses for current codes once
						$verified_codes=[]; $release_codes=[]; $statuses=[];
						if(!empty($current_codes)){
							$placeholders=implode(',',array_fill(0,count($current_codes),'%s'));
							$rows=$wpdb->get_results($wpdb->prepare("SELECT code,status FROM $table WHERE code IN ($placeholders)", ...$current_codes));
							foreach($rows as $r){ $statuses[$r->code]=(int)$r->status; }
						}

						if($slot_mode){
							// Per-slot override / assignment
							$old_code = $current_codes[$slot_index] ?? '';
							$old_status = $old_code && isset($statuses[$old_code]) ? $statuses[$old_code] : null;
							if($old_status === 2){
								$message = sprintf(__('Slot %d contains a verified code and cannot be overridden.','wooauthentix'), $slot_index+1);
							}else{
								if($auto_pick){
									// Allocate one new code
									$product_id=$item->get_product_id();
									$allocated_code=''; $attempts=0; $max_attempts=12;
									while(!$allocated_code && $attempts<$max_attempts){
										$attempts++;
										$row=$wpdb->get_row($wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0 ORDER BY id ASC LIMIT 1", $product_id));
										if($row){
											$aff=$wpdb->update($table,['status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>current_time('mysql')],['id'=>$row->id,'status'=>0]);
											if($aff){ $allocated_code=$row->code; break; }
										}
										$generic=$wpdb->get_row("SELECT id,code FROM $table WHERE product_id IS NULL AND status=0 ORDER BY id ASC LIMIT 1");
										if($generic){
											$aff=$wpdb->update($table,['product_id'=>$product_id,'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>current_time('mysql')],['id'=>$generic->id,'status'=>0]);
											if($aff){ $allocated_code=$generic->code; break; }
										}
										if(function_exists('wc_apc_generate_batch_codes')){
											$new_batch=wc_apc_generate_batch_codes($product_id,1);
											if(!empty($new_batch)){
												$nc=$new_batch[0];
												$wpdb->query($wpdb->prepare("UPDATE $table SET status=1, order_id=%d, order_item_id=%d, assigned_at=%s WHERE code=%s AND status=0", $order_id,$item_id,current_time('mysql'),$nc));
												$allocated_code=$nc; break;
											}
										}
									}
									if($allocated_code){
										// Release old unverified code after successful allocation
										if($old_code && $old_status===1){
											$wpdb->query($wpdb->prepare("UPDATE $table SET status=0, order_id=NULL, order_item_id=NULL, assigned_at=NULL WHERE code=%s AND status=1", $old_code));
										}
										$current_codes[$slot_index]=$allocated_code;
										// Trim to qty
										if(count($current_codes)>$qty){ $current_codes=array_slice($current_codes,0,$qty); }
										$item->update_meta_data(WC_APC_ITEM_META_KEY,$current_codes); $item->save();
										$message = sprintf(__('Assigned new code to slot %1$d. Total codes now: %2$d / %3$d.','wooauthentix'), $slot_index+1, count(array_filter($current_codes)), $qty);
									}else{
										$error = sprintf(__('No available code to assign to slot %d.','wooauthentix'), $slot_index+1);
									}
								}else{ // manual slot override
									if($new_code===''){ $error=__('Manual code empty.','wooauthentix'); }
									elseif($new_code === $old_code){ $message = sprintf(__('Slot %d unchanged (same code).','wooauthentix'), $slot_index+1); }
									else {
										$exists=$wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE code=%s", $new_code));
										if($exists===null){
											$wpdb->insert($table,['code'=>$new_code,'product_id'=>$item->get_product_id(),'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'created_at'=>current_time('mysql'),'assigned_at'=>current_time('mysql')]);
										}else{
											// Mark (or re-mark) code as assigned to this item
											$wpdb->query($wpdb->prepare("UPDATE $table SET product_id=%d,status=1,order_id=%d,order_item_id=%d,assigned_at=NOW() WHERE code=%s", $item->get_product_id(), $order_id, $item_id, $new_code));
										}
										if($old_code && $old_status===1){
											$wpdb->query($wpdb->prepare("UPDATE $table SET status=0, order_id=NULL, order_item_id=NULL, assigned_at=NULL WHERE code=%s AND status=1", $old_code));
										}
										$current_codes[$slot_index]=$new_code;
										if(count($current_codes)>$qty){ $current_codes=array_slice($current_codes,0,$qty); }
										$item->update_meta_data(WC_APC_ITEM_META_KEY,$current_codes); $item->save();
										$message = sprintf(__('Replaced slot %1$d code. Total codes: %2$d / %3$d.','wooauthentix'), $slot_index+1, count(array_filter($current_codes)), $qty);
									}
								}
							}
						} else {
							// Multi-code (all slots) override path
							foreach($current_codes as $c){
								if(isset($statuses[$c]) && $statuses[$c]===2){ $verified_codes[]=$c; }
								else { $release_codes[]=$c; }
							}
							if(!empty($release_codes)){
								$ph=implode(',',array_fill(0,count($release_codes),'%s'));
								$wpdb->query($wpdb->prepare("UPDATE $table SET status=0, order_id=NULL, order_item_id=NULL, assigned_at=NULL WHERE status=1 AND code IN ($ph)", ...$release_codes));
							}
							$current_codes=$verified_codes; // start with retained verified codes
							$verified_count=count($verified_codes);
							$remaining_needed = max(0, $qty - $verified_count);
							if($remaining_needed===0){
								$message = sprintf(__('Nothing to override: %1$d verified code(s) already fill required quantity (%2$d).','wooauthentix'), $verified_count, $qty);
							}else if($auto_pick){
							// Prevent immediate reuse of the just released unverified codes when overriding multiple times.
							$exclude_codes = $release_codes; // codes we just freed; user expects new ones instead of seeing the same values again
							$exclude_clause = '';
							if(!empty($exclude_codes)){
								$exclude_clause = ' AND code NOT IN (' . implode(',', array_fill(0, count($exclude_codes), '%s')) . ')';
							}
							$product_id=$item->get_product_id();
							$needed=$remaining_needed; $allocated=[]; $attempts=0; $max_attempts=$needed*6;
							while(count($allocated)<$needed && $attempts<$max_attempts){
								$attempts++;
								if(!empty($exclude_codes)){
									// Query excluding previously released codes
									$sql = $wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0".$exclude_clause." ORDER BY id ASC LIMIT 1", array_merge([$product_id], $exclude_codes));
									$row = $wpdb->get_row($sql);
								} else {
									$row=$wpdb->get_row($wpdb->prepare("SELECT id,code FROM $table WHERE product_id=%d AND status=0 ORDER BY id ASC LIMIT 1", $product_id));
								}
								if($row){
									$aff=$wpdb->update($table,['status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>current_time('mysql')],['id'=>$row->id,'status'=>0]);
									if($aff){ $allocated[]=$row->code; continue; }
								}
								$generic=$wpdb->get_row("SELECT id,code FROM $table WHERE product_id IS NULL AND status=0 ORDER BY id ASC LIMIT 1");
								if($generic){
									$aff=$wpdb->update($table,['product_id'=>$product_id,'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'assigned_at'=>current_time('mysql')],['id'=>$generic->id,'status'=>0]);
									if($aff){ $allocated[]=$generic->code; continue; }
								}
								if(function_exists('wc_apc_generate_batch_codes')){
									$new_batch=wc_apc_generate_batch_codes($product_id, max(1,$needed-count($allocated)) );
									if(!empty($new_batch)){
										$now=current_time('mysql');
										foreach($new_batch as $nc){
											// Mark newly generated code as assigned to this order/item
											$wpdb->query($wpdb->prepare("UPDATE $table SET status=1, order_id=%d, order_item_id=%d, assigned_at=%s WHERE code=%s AND status=0", $order_id,$item_id,$now,$nc));
											$allocated[]=$nc; if(count($allocated)>=$needed) break; }
									}
								}
								if(empty($row) && empty($generic) && $attempts>($needed+2)) break;
							}
							if(!empty($allocated)){
								$current_codes=array_merge($verified_codes,$allocated);
								if(count($current_codes)>$qty) $current_codes=array_slice($current_codes,0,$qty);
								$item->update_meta_data(WC_APC_ITEM_META_KEY,$current_codes); $item->save();
								$message=sprintf(__('Replaced %1$d unverified code(s) with %2$d new code(s). Verified retained: %3$d. Total: %4$d / %5$d.','wooauthentix'), count($release_codes), count($allocated), $verified_count, count($current_codes), $qty);
							}else{
								$error=__('Override failed: insufficient unassigned codes and generation failed.','wooauthentix');
							}
						}else{ // manual (all slots path)
							if($new_code===''){ $error=__('Manual code empty.','wooauthentix'); }
							else {
								$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE code=%s", $new_code));
								if(!$exists){
									$wpdb->insert($table,['code'=>$new_code,'product_id'=>$item->get_product_id(),'status'=>1,'order_id'=>$order_id,'order_item_id'=>$item_id,'created_at'=>current_time('mysql'),'assigned_at'=>current_time('mysql')]);
								}else{
									$wpdb->query($wpdb->prepare("UPDATE $table SET product_id=%d,status=1,order_id=%d,order_item_id=%d,assigned_at=NOW() WHERE code=%s", $item->get_product_id(), $order_id, $item_id, $new_code));
								}
								if(in_array($new_code,$verified_codes,true)){
									$current_codes=$verified_codes; // no change besides message
									$message=sprintf(__('Manual code matches existing verified code. Retained all verified (%1$d). Total: %2$d / %3$d.','wooauthentix'), $verified_count, count($current_codes), $qty);
								}else{
									$current_codes=array_merge($verified_codes, [$new_code]);
									if(count($current_codes)>$qty) $current_codes=array_slice($current_codes,0,$qty);
									$item->update_meta_data(WC_APC_ITEM_META_KEY,$current_codes); $item->save();
									$message=sprintf(__('Added manual code replacing %1$d unverified; verified kept: %2$d. Total: %3$d / %4$d.','wooauthentix'), count($release_codes), $verified_count, count($current_codes), $qty);
								}
							}
							}
							// end selective override (all slots)
						}
						// If per-slot mode finished earlier, skip rest of multi-slot logic
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
					$per_slot_enabled = apply_filters('wooauthentix_enable_per_slot_assign', true);
					foreach($items as $item_id=>$it){ $prod=$it->get_product(); $pname=$prod? $prod->get_name():__('(deleted product)','wooauthentix'); $qty=intval($it->get_quantity()); $meta_val=$it->get_meta(WC_APC_ITEM_META_KEY); $codes_list = function_exists('wc_apc_parse_codes_meta') ? wc_apc_parse_codes_meta($meta_val) : ( is_array($meta_val)? $meta_val : ( ($meta_val && strpos($meta_val,',')!==false)? array_map('trim',explode(',',$meta_val)) : ( $meta_val? [$meta_val]:[] ) ) ); $codes_list=array_values($codes_list); $code_display=empty($codes_list)? '<span style="color:#999;">'.esc_html__('None','wooauthentix').'</span>' : esc_html(implode(', ',$codes_list));
						echo '<tr><td>'.esc_html(wp_trim_words($pname,8,'…')).' <span style="color:#888;">#'.intval($item_id).'</span></td><td>'.intval($qty).'</td><td>'.$code_display.'</td><td>';
						if($qty>1 && $per_slot_enabled){
							echo '<div style="display:flex;flex-direction:column;gap:6px;">';
							for($slot=0;$slot<$qty;$slot++){
								$slot_code = $codes_list[$slot] ?? '';
								echo '<form method="post" class="wc-apc-inline-assign" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';
								wp_nonce_field('wc_apc_assign_codes','wc_apc_assign_nonce');
								echo '<input type="hidden" name="order_id" value="'.intval($oid).'" />';
								echo '<input type="hidden" name="item_id" value="'.intval($item_id).'" />';
								echo '<input type="hidden" name="code_slot" value="'.intval($slot).'" />';
								echo '<span style="font-size:11px;color:#555;min-width:34px;">'.esc_html__('Slot','wooauthentix').' '.($slot+1).'</span>';
								echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="0" checked /> '.esc_html__('Auto','wooauthentix').'</label>';
								echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="1" '.($slot_code?'':'').' /> '.esc_html__('Manual','wooauthentix').'</label>';
								echo '<input type="text" name="new_code" value="" placeholder="'.esc_attr($slot_code?:__('ABC123','wooauthentix')).'" style="width:120px;" />';
								echo '<button type="submit" name="wc_apc_assign_submit" class="button button-small">'.esc_html__('Assign','wooauthentix').'</button>';
								echo ($slot_code? '<span style="font-size:11px;color:#777;">'.esc_html($slot_code).'</span>':'');
								echo '</form>';
							}
							echo '</div>';
						} else {
							echo '<form method="post" class="wc-apc-inline-assign" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';
							wp_nonce_field('wc_apc_assign_codes','wc_apc_assign_nonce');
							echo '<input type="hidden" name="order_id" value="'.intval($oid).'" />';
							echo '<input type="hidden" name="item_id" value="'.intval($item_id).'" />';
							echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="0" checked /> '.esc_html__('Auto','wooauthentix').'</label>';
							echo '<label style="font-size:11px;margin-right:4px;"><input type="radio" name="manual_code" value="1" /> '.esc_html__('Manual','wooauthentix').'</label>';
							echo '<input type="text" name="new_code" placeholder="'.esc_attr__('ABC123','wooauthentix').'" style="width:120px;" />';
							echo '<button type="submit" name="wc_apc_assign_submit" class="button button-small">'.esc_html__('Assign','wooauthentix').'</button>';
							echo '</form>';
						}
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

	public static function dashboard_page(){
		if(!current_user_can('manage_woocommerce')) return; 
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; 
		$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
		$unassigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=0");
		$assigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=1");
		$verified=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=2");
		// Removed detailed 'Recently Verified' and 'Low Unassigned Stock' sections for a cleaner dashboard.
		// Embedded logs logic (subset, with filters)
		$log_table=$wpdb->prefix.WC_APC_LOG_TABLE; 
		$log_code=isset($_GET['s_code'])? strtoupper(sanitize_text_field($_GET['s_code'])):''; 
		$log_result=isset($_GET['result'])? sanitize_text_field($_GET['result']):'all';
		$log_date_from=isset($_GET['date_from'])? sanitize_text_field($_GET['date_from']):''; 
		$log_date_to=isset($_GET['date_to'])? sanitize_text_field($_GET['date_to']):''; 
		$log_page=max(1, isset($_GET['log_paged'])? intval($_GET['log_paged']):1); // renamed param
		$logs_per_page=25; $log_offset=($log_page-1)*$logs_per_page; 
		$where=[]; $params=[]; 
		if($log_code){ $where[]='code = %s'; $params[]=$log_code; }
		if($log_result && $log_result!=='all'){ $where[]='result = %s'; $params[]=$log_result; }
		if($log_date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$log_date_from)){ $where[]='DATE(created_at) >= %s'; $params[]=$log_date_from; }
		if($log_date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$log_date_to)){ $where[]='DATE(created_at) <= %s'; $params[]=$log_date_to; }
		$where_sql=$where? ('WHERE '.implode(' AND ',$where)) : '';
		$count_sql='SELECT COUNT(*) FROM '.$log_table.' '.$where_sql; 
		$log_total=$params? $wpdb->get_var($wpdb->prepare($count_sql,...$params)):$wpdb->get_var($count_sql);
		$query_sql='SELECT * FROM '.$log_table.' '.$where_sql.' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params_q=$params; $params_q[]=$logs_per_page; $params_q[]=$log_offset; 
		$log_rows=$params_q? $wpdb->get_results($wpdb->prepare($query_sql,...$params_q)):$wpdb->get_results($wpdb->prepare($query_sql,$logs_per_page,$log_offset));

		echo '<div class="wrap"><h1>'.esc_html__('Authenticity Dashboard','wooauthentix').'</h1>';
		echo '<div style="display:flex; gap:16px; flex-wrap:wrap;">';
		$cards=[["label"=>__('Total Codes','wooauthentix'),"value"=>$total,"color"=>'#444'],["label"=>__('Unassigned','wooauthentix'),"value"=>$unassigned,"color"=>'green'],["label"=>__('Assigned','wooauthentix'),"value"=>$assigned,"color"=>'orange'],["label"=>__('Verified','wooauthentix'),"value"=>$verified,"color"=>'blue']];
		foreach($cards as $c){ echo '<div style="flex:1;min-width:180px;border:1px solid #ddd;padding:12px;border-radius:6px;"><div style="font-size:13px;color:#666;">'.esc_html($c['label']).'</div><div style="font-size:24px;font-weight:bold;color:'.esc_attr($c['color']).';">'.esc_html($c['value']).'</div></div>'; }
		echo '</div>';
		// Logs section only
		echo '<h2 id="wc-apc-logs" style="margin-top:2.5em;">'.esc_html__('Verification Logs','wooauthentix').'</h2>';
		echo '<form method="get" style="margin:1em 0; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">';
		echo '<input type="hidden" name="page" value="wc-apc-dashboard" />';
		echo '<input type="text" name="s_code" placeholder="'.esc_attr__('Code','wooauthentix').'" value="'.esc_attr($log_code).'" /> ';
		$results=['all','invalid_format','invalid_code','unassigned','verified','already_verified','rate_limited'];
		echo '<select name="result">'; foreach($results as $res){ echo '<option value="'.esc_attr($res).'" '.selected($res,$log_result,false).'>'.esc_html(ucwords(str_replace('_',' ',$res))).'</option>'; } echo '</select>';
		echo ' <label>'.esc_html__('From','wooauthentix').' <input type="date" name="date_from" value="'.esc_attr($log_date_from).'" /></label>';
		echo '<label>'.esc_html__('To','wooauthentix').' <input type="date" name="date_to" value="'.esc_attr($log_date_to).'" /></label>';
		echo '<button class="button">'.esc_html__('Filter','wooauthentix').'</button>';
		if($log_code||($log_result && $log_result!=='all')||$log_date_from||$log_date_to){ $reset_url=remove_query_arg(['s_code','result','date_from','date_to','log_paged']); echo ' <a class="button" href="'.esc_url($reset_url).'">'.esc_html__('Reset','wooauthentix').'</a>'; }
		echo '</form>';
		echo '<table class="widefat fixed striped"><thead><tr><th>'.esc_html__('ID','wooauthentix').'</th><th>'.esc_html__('Code','wooauthentix').'</th><th>'.esc_html__('Result','wooauthentix').'</th><th>'.esc_html__('IP','wooauthentix').'</th><th>'.esc_html__('User Agent','wooauthentix').'</th><th>'.esc_html__('Created','wooauthentix').'</th></tr></thead><tbody>';
		if(empty($log_rows)){ echo '<tr><td colspan="6">'.esc_html__('No log entries.','wooauthentix').'</td></tr>'; } else { foreach($log_rows as $r){ echo '<tr><td>'.intval($r->id).'</td><td>'.esc_html($r->code).'</td><td>'.esc_html($r->result).'</td><td>'.esc_html($r->ip).'</td><td>'.esc_html(mb_strimwidth($r->user_agent,0,60,'…')).'</td><td>'.esc_html($r->created_at).'</td></tr>'; } }
		echo '</tbody></table>';
		$log_total_pages=ceil($log_total/$logs_per_page); if($log_total_pages>1){ echo '<div class="tablenav"><div class="tablenav-pages">'; for($p=1;$p<=$log_total_pages;$p++){ $u=esc_url(add_query_arg(array_merge($_GET,['log_paged'=>$p]))); $cls=$p==$log_page?' class="page-numbers current"':' class="page-numbers"'; echo '<a'.$cls.' href="'.$u.'">'.$p.'</a> '; } echo '</div></div>'; }
		echo '<p style="margin-top:8px;font-size:11px;color:#555;">'.esc_html__('Metrics and logs only view.','wooauthentix').'</p>';
		echo '</div>';
	}

	// ---------------- Assets & AJAX -----------------
	public static function enqueue_settings_assets(){ if (empty($_GET['page']) || $_GET['page'] !== 'wc-apc-settings') return; if (function_exists('wp_enqueue_media')) { wp_enqueue_media(); } wp_enqueue_script('wooauthentix-qrcode','https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',[],WC_APC_VERSION,true); wp_enqueue_script('wooauthentix-qrious','https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js',[],WC_APC_VERSION,true); wp_enqueue_script('wooauthentix-settings-preview', plugin_dir_url(WC_APC_PLUGIN_FILE).'assets/settings-preview.js', ['jquery','wooauthentix-qrcode','wooauthentix-qrious'], WC_APC_VERSION, true); }
	public static function enqueue_assign_assets(){ if (empty($_GET['page']) || $_GET['page']!=='wc-apc-assign') return; wp_enqueue_script('jquery-ui-autocomplete'); }
	public static function ajax_item_search(){ if (!current_user_can('manage_woocommerce')) wp_send_json_error('forbidden',403); check_ajax_referer('wc_apc_assign_codes','nonce'); $order_id=isset($_GET['order_id'])? intval($_GET['order_id']):0; $term=isset($_GET['term'])? sanitize_text_field(wp_unslash($_GET['term'])):''; if(!$order_id){ wp_send_json_success([]);} $order=wc_get_order($order_id); if(!$order){ wp_send_json_success([]);} $matches=[]; foreach($order->get_items() as $item_id=>$item){ $product=$item->get_product(); $name=$product? $product->get_name():__('(deleted product)','wooauthentix'); $code=$item->get_meta(WC_APC_ITEM_META_KEY); $label=$name.' #'.$item_id; if($code) $label.=' ['.$code.']'; if($term==='' || stripos($name,$term)!==false || ($code && stripos($code,$term)!==false) || stripos((string)$item_id,$term)!==false){ $matches[]=['label'=>$label,'value'=>$item_id]; } } wp_send_json($matches); }
}

WC_APC_Admin_Pages_Module::init();
