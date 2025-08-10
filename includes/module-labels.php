<?php
// Labels / QR generation module (delegates to existing wc_apc_labels_page function kept in main for now).
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_APC_Labels_Module {
	public static function init() {
		add_action('admin_enqueue_scripts',[__CLASS__,'enqueue_assets']);
		add_action('wp_ajax_wooauthentix_qr',[__CLASS__,'ajax_qr']);
	}

	public static function enqueue_assets($hook){
		if (empty($_GET['page']) || $_GET['page'] !== 'wc-apc-labels') return;
		wp_enqueue_script('wooauthentix-qrcode','https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',[],WC_APC_VERSION,true);
		wp_enqueue_script('wooauthentix-qrious','https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js',[],WC_APC_VERSION,true);
		wp_enqueue_script('wooauthentix-html2canvas','https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',[],WC_APC_VERSION,true);
		wp_enqueue_script('wooauthentix-jspdf','https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',[],WC_APC_VERSION,true);
		wp_enqueue_script('wooauthentix-labels', plugin_dir_url(WC_APC_PLUGIN_FILE).'assets/labels.js', ['jquery','wooauthentix-qrcode','wooauthentix-qrious','wooauthentix-html2canvas','wooauthentix-jspdf'], WC_APC_VERSION, true);
		$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
		wp_add_inline_script('wooauthentix-labels', 'window.wooauthentixLabels = '.wp_json_encode([
			'product_id'=>$product_id,
			'i18n'=>[
				'rendering'=>__('Rendering...','wooauthentix'),
				'done'=>__('Done','wooauthentix'),
				'failed'=>__('Failed','wooauthentix'),
				'purged'=>__('Cache cleared','wooauthentix'),
				'applying'=>__('Applying...','wooauthentix'),
			],
			'paperSizes'=>[
				'a4'=>['w'=>210,'h'=>297],
				'letter'=>['w'=>216,'h'=>279],
			],
		]).';','before');
	}

	public static function ajax_qr() {
		check_ajax_referer('wooauth_qr');
		if (!current_user_can('manage_woocommerce')) wp_send_json_error('forbidden',403);
		$code = isset($_GET['data']) ? sanitize_text_field(wp_unslash($_GET['data'])) : '';
		$size = isset($_GET['s']) ? max(60,min(600,intval($_GET['s']))) : 110;
		if ($code==='') wp_send_json_error('empty');
		if (!class_exists('Endroid\QrCode\Builder\Builder')) {
			$simple_lib = dirname(WC_APC_PLUGIN_FILE) . '/lib/SimpleQR.php';
			if (file_exists($simple_lib)) require_once $simple_lib; if (class_exists('WC_APC_SimpleQR')) { try { $bin = WC_APC_SimpleQR::png($code, $size, 0); if ($bin) { wp_send_json_success(['dataURI'=>'data:image/png;base64,'.base64_encode($bin),'simple'=>true]); } } catch (Exception $e) {} }
		}
		if (class_exists('Endroid\QrCode\Builder\Builder')) {
			try { $builder = Endroid\QrCode\Builder\Builder::create()->data($code)->size($size)->margin(0); $result=$builder->build(); wp_send_json_success(['dataURI'=>'data:image/png;base64,'.base64_encode($result->getString())]); } catch (Exception $e) { wp_send_json_error('build_fail'); }
		}
		wp_send_json_error('no_library');
	}
}

WC_APC_Labels_Module::init();
