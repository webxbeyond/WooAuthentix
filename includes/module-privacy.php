<?php
// Privacy exporter / eraser isolated.
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter('wp_privacy_personal_data_exporters','wc_apc_register_exporter');
function wc_apc_register_exporter($exporters){
    $exporters['wooauthentix-codes'] = [
        'exporter_friendly_name' => __('WooAuthentix Codes','wooauthentix'),
        'callback' => 'wc_apc_personal_data_exporter'
    ];
    return $exporters;
}
add_filter('wp_privacy_personal_data_erasers','wc_apc_register_eraser');
function wc_apc_register_eraser($erasers){
    $erasers['wooauthentix-logs'] = [
        'eraser_friendly_name' => __('WooAuthentix Logs','wooauthentix'),
        'callback' => 'wc_apc_personal_data_eraser'
    ];
    return $erasers;
}
function wc_apc_personal_data_exporter($email,$page=1){
    $user = get_user_by('email',$email);
    if(!$user){ return ['data'=>[], 'done'=>true]; }
    $orders = wc_get_orders([
        'customer_id'=>$user->ID,
        'limit'=>-1,
        'status'=>array_keys(wc_get_order_statuses()),
        'return'=>'objects'
    ]);
    $codes=[]; foreach($orders as $o){ foreach($o->get_items() as $it){ $val=$it->get_meta(WC_APC_ITEM_META_KEY); if(!$val) $val=$it->get_meta('_authentic_code'); if($val){ $codes=array_merge($codes, array_map('trim', explode(',',$val))); } } }
    $codes=array_values(array_unique(array_filter($codes)));
    $data=[]; if($codes){ global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE; $chunks=array_chunk($codes,100); $map=[]; foreach($chunks as $chunk){ $ph=implode(',',array_fill(0,count($chunk),'%s')); $rows=$wpdb->get_results($wpdb->prepare("SELECT code,status,assigned_at,verified_at FROM $table WHERE code IN ($ph)", ...$chunk)); foreach($rows as $r){ $map[$r->code]=$r; } } foreach($codes as $c){ $m=isset($map[$c])?$map[$c]:null; $data[]=[ 'group_id'=>'wooauthentix-codes','group_label'=>__('Authenticity Codes','wooauthentix'),'item_id'=>'wooauthentix-code-'.$c,'data'=>[ ['name'=>__('Code','wooauthentix'),'value'=>$c], ['name'=>__('Status','wooauthentix'),'value'=>$m?(int)$m->status:__('Unknown','wooauthentix')], ['name'=>__('Assigned At','wooauthentix'),'value'=>$m?$m->assigned_at:''], ['name'=>__('Verified At','wooauthentix'),'value'=>$m?$m->verified_at:''], ]]; } }
    return ['data'=>$data,'done'=>true];
}
function wc_apc_personal_data_eraser($email,$page=1){
    $user=get_user_by('email',$email); $removed=0; if($user){ $orders=wc_get_orders(['customer_id'=>$user->ID,'limit'=>-1,'status'=>array_keys(wc_get_order_statuses()),'return'=>'objects']); $codes=[]; foreach($orders as $o){ foreach($o->get_items() as $it){ $val=$it->get_meta(WC_APC_ITEM_META_KEY); if(!$val) $val=$it->get_meta('_authentic_code'); if($val){ $codes=array_merge($codes, array_map('trim', explode(',',$val))); } } } $codes=array_values(array_unique(array_filter($codes))); if($codes){ global $wpdb; $log=$wpdb->prefix.WC_APC_LOG_TABLE; $chunks=array_chunk($codes,100); foreach($chunks as $chunk){ $ph=implode(',',array_fill(0,count($chunk),'%s')); $sql="UPDATE $log SET ip=NULL,user_agent=NULL WHERE code IN ($ph) AND (ip IS NOT NULL OR user_agent IS NOT NULL)"; $wpdb->query($wpdb->prepare($sql,...$chunk)); $removed+=$wpdb->rows_affected; } } }
    return ['items_removed'=>$removed,'items_retained'=>0,'messages'=>[],'done'=>true];
}
