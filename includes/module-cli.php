<?php
// CLI commands module.
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined('WP_CLI') && WP_CLI ) {
	if ( ! class_exists( 'WooAuthentix_CLI' ) ) {
		class WooAuthentix_CLI {
			public function generate($args) {
				list($product_raw,$count)=$args; $count=(int)$count; if($count<=0){ WP_CLI::error('Count must be positive.'); }
				$product_id = null;
				if(!in_array(strtolower($product_raw),['generic','none','null','0'],true)){
					$product_id = (int)$product_raw; if($product_id<=0){ WP_CLI::error('Invalid product id (use a positive ID or the keyword generic).'); }
				}
				$codes = wc_apc_generate_batch_codes($product_id,$count);
				if(is_null($product_id)){
					WP_CLI::success(count($codes).' generic codes generated.');
				}else{
					WP_CLI::success(count($codes).' codes generated for product '.$product_id);
				}
			}
			public function export($args,$assoc_args){ list($file)=$args; global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $where=[];$params=[]; if(!empty($assoc_args['status'])){ $map=['unassigned'=>0,'assigned'=>1,'verified'=>2]; if(isset($map[$assoc_args['status']])){$where[]='status=%d';$params[]=$map[$assoc_args['status']];} } if(!empty($assoc_args['product'])){ $where[]='product_id=%d'; $params[]=(int)$assoc_args['product']; } $where_sql=$where?('WHERE '.implode(' AND ',$where)) : ''; $sql='SELECT code,product_id,status,created_at,assigned_at,verified_at FROM '.$table.' '.$where_sql.' ORDER BY created_at DESC'; $rows=$params? $wpdb->get_results($wpdb->prepare($sql,...$params)) : $wpdb->get_results($sql); $fh=fopen($file,'w'); if(!$fh) WP_CLI::error('Unable to open file.'); fputcsv($fh,['code','product_id','status','created_at','assigned_at','verified_at']); foreach($rows as $r){ fputcsv($fh,[(string)$r->code,(int)$r->product_id,(int)$r->status,$r->created_at,$r->assigned_at,$r->verified_at]); } fclose($fh); WP_CLI::success(count($rows).' codes exported to '.$file); }
			public function report(){ global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table"); $unassigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=0"); $assigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=1"); $verified=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status=2"); WP_CLI::log('Total codes: '.$total); WP_CLI::log('Unassigned: '.$unassigned); WP_CLI::log('Assigned: '.$assigned); WP_CLI::log('Verified: '.$verified); $low=$wpdb->get_results("SELECT product_id, COUNT(*) cnt FROM $table WHERE status=0 GROUP BY product_id HAVING cnt < 20 ORDER BY cnt ASC LIMIT 10"); if($low){ WP_CLI::log('Low unassigned stock (<20):'); foreach($low as $row){ WP_CLI::log(' - Product '.$row->product_id.': '.$row->cnt); } } }
		}
	}
	WP_CLI::add_command('wooauthentix','WooAuthentix_CLI');
}
