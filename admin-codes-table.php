<?php
// admin-codes-table.php
if (!defined('ABSPATH')) exit;

function wc_apc_all_codes_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $table = $wpdb->prefix . (defined('WC_APC_TABLE') ? WC_APC_TABLE : 'wc_authentic_codes');
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    // Sorting params
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
    $order = isset($_GET['order']) ? strtolower(sanitize_text_field($_GET['order'])) : 'desc';
    $allowed_orderby = [ 'id','code','product','status','created_at','assigned_at','verified_at','qr_label_generated' ];
    if(!in_array($orderby,$allowed_orderby,true)) { $orderby = 'created_at'; }
    $order = $order==='asc' ? 'asc' : 'desc';
    // Removed code prefix and date range filters
    $search_code = '';
    $product_filter = 0; // legacy no-op
    $date_from = '';
    $date_to = '';
    $full_export = isset($_GET['full_export']) && $_GET['full_export'] === '1';
    $page_num = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
    $allowed_page_sizes = [50,100,500,1000];
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
    if (!in_array($per_page, $allowed_page_sizes, true)) { $per_page = 50; }
    $offset = ($page_num - 1) * $per_page;

    $where_parts = [];
    $params = [];
    if ($filter === 'verified') {
        $where_parts[] = 'status = 2';
    } elseif ($filter === 'assigned') {
        $where_parts[] = 'status = 1';
    } elseif ($filter === 'unassigned') {
        $where_parts[] = 'status = 0';
    }
    // Removed code prefix & date filter logic
    $where_sql = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
    $qr_preview_html = ''; // will hold preview from manual or bulk generation
    $auto_open_preview = false; // flag to auto-open new window when bulk generation completes
    // Bulk actions: generate selected QR labels, delete selected, delete all filtered variants
    if (isset($_POST['wc_apc_bulk_delete'])) {
        $bulk_action = '';
        $selected_ids = [];
        $nonce = isset($_POST['wc_apc_bulk_codes_nonce']) ? sanitize_text_field($_POST['wc_apc_bulk_codes_nonce']) : '';
        $nonce_ok = wp_verify_nonce($nonce,'wc_apc_bulk_codes');
        if(!$nonce_ok){
            echo '<div class="notice notice-error"><p>'.esc_html__('Security check failed (nonce). Refresh and try again.','wooauthentix').'</p></div>';
        } else {
            if(isset($_POST['bulk_action']) && $_POST['bulk_action']!==''){ $bulk_action = sanitize_text_field($_POST['bulk_action']); }
            $selected_ids = !empty($_POST['delete_ids']) ? array_slice(array_unique(array_filter(array_map('intval',(array)$_POST['delete_ids']),function($v){return $v>0;})),0,5000) : [];
            switch($bulk_action){
                case 'generate_qr_labels':
                    if(empty($selected_ids)){
                        echo '<div class="notice notice-warning"><p>'.esc_html__('Select at least one unassigned code to generate QR labels.','wooauthentix').'</p></div>';
                        break;
                    }
                    $ph = implode(',', array_fill(0,count($selected_ids),'%d'));
                    $codes_sel = $wpdb->get_col($wpdb->prepare("SELECT code FROM $table WHERE id IN ($ph) AND status=0", ...$selected_ids));
                    if(empty($codes_sel)){
                        echo '<div class="notice notice-warning"><p>'.esc_html__('Selected codes are not unassigned or unavailable.','wooauthentix').'</p></div>';
                        break;
                    }
                    $now = current_time('mysql');
                    $phu = implode(',', array_fill(0,count($codes_sel),'%s'));
                    $wpdb->query($wpdb->prepare("UPDATE $table SET qr_label_generated=1, qr_generated_at=%s WHERE code IN ($phu)", $now, ...$codes_sel));
                    $site = parse_url(home_url(),PHP_URL_HOST);
                    $verify_base = trailingslashit(home_url()).'v/';
                    $opt = wc_apc_get_settings();
                    $show_brand = !empty($opt['label_show_brand']);
                    $brand_text = !empty($opt['label_brand_text']) ? esc_html($opt['label_brand_text']) : get_bloginfo('name');
                    $show_site = !empty($opt['label_show_site']);
                    $show_code = !empty($opt['label_show_code']);
                    $qr_size = !empty($opt['label_qr_size']) ? max(60,min(260,intval($opt['label_qr_size']))) : 110;
                    $margin = isset($opt['label_margin']) ? max(0,min(40,intval($opt['label_margin']))) : 6;
                    $border = !empty($opt['label_enable_border']);
                    $border_size = isset($opt['label_border_size']) ? max(0,min(10,intval($opt['label_border_size']))) : 1;
                    $logo_id = !empty($opt['label_logo_id']) ? intval($opt['label_logo_id']) : 0;
                    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id,'full') : '';
                    ob_start();
                    echo '<div id="wc-apc-label-sheet" style="background:#fff;padding:10px;">';
                    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax('.intval($qr_size).'px,1fr));gap:10px;">';
                    foreach($codes_sel as $c){
                        $verify_url = esc_url($verify_base.rawurlencode($c));
                        echo '<div class="wc-apc-label" style="text-align:center;'.($border?'border:'.$border_size.'px solid #333;':'').'padding:'.intval($margin).'px;">';
                        if($show_brand){ echo '<div class="wc-apc-label-brand" style="font-size:11px;font-weight:600;margin-bottom:4px;">'.$brand_text.'</div>'; }
                        echo '<div class="wc-apc-label-qr" data-code="'.esc_attr($c).'" data-url="'.$verify_url.'" style="width:'.$qr_size.'px;height:'.$qr_size.'px;margin:0 auto;"></div>';
                        if($logo_url){ echo '<div class="wc-apc-label-logo" style="margin-top:3px;"><img src="'.esc_url($logo_url).'" alt="logo" style="max-width:60px;height:auto;" /></div>'; }
                        if($show_code){ echo '<div class="wc-apc-label-code" style="font-size:10px;letter-spacing:1px;margin-top:4px;">'.esc_html($c).'</div>'; }
                        if($show_site){ echo '<div class="wc-apc-label-site" style="font-size:9px;color:#555;margin-top:2px;">'.esc_html($site).'</div>'; }
                        echo '</div>';
                    }
                    echo '</div></div>';
                    $qr_preview_html = ob_get_clean();
                    $preview_json = wp_json_encode($qr_preview_html);
                    echo '<div class="notice notice-success"><p>'.esc_html__('Generated QR label preview for selected codes.','wooauthentix').' <a href="#" id="wc-apc-open-preview-fallback">'.esc_html__('(Open QR-only view)','wooauthentix').'</a></p></div>';
                    echo '<script>window.wcApcLabelPreviewHTML='.$preview_json.';</script>';
                    echo '<script>document.addEventListener("DOMContentLoaded",function(){function openQrOnly(){var temp=document.createElement("div");temp.innerHTML=window.wcApcLabelPreviewHTML||"";var qrs=temp.querySelectorAll(".wc-apc-label-qr");if(!qrs.length){var live=document.getElementById("wc-apc-label-sheet");if(live)qrs=live.querySelectorAll(".wc-apc-label-qr");}if(!qrs.length){alert("Preview not ready yet.");return;}var size=(qrs[0].clientWidth||parseInt(qrs[0].style.width)||110);var w=window.open("","_blank");if(!w){alert("Popup blocked. Allow popups.");return;}var htmlStart="<html><head><title>QR Codes</title><meta charset=\"utf-8\"><style>body{margin:0;padding:10px;background:#fff;}#qr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax("+size+"px,1fr));gap:10px;}#qr-grid .qr-box{width:"+size+"px;height:"+size+"px;}@media print{.no-print{display:none}}</style></head><body>";var grid="<div id=\"qr-grid\">";qrs.forEach(function(el){grid+="<div class=\"qr-box\" data-url=\""+el.getAttribute("data-url")+"\"></div>";});grid+="</div>";var htmlEnd="<script src=\"https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js\"><\\/script><script>(function(){function gen(){if(!window.QRCode)return;var els=document.querySelectorAll(\"#qr-grid .qr-box\");els.forEach(function(el){if(el.dataset.done)return;el.dataset.done=1;new QRCode(el,{text:el.getAttribute(\"data-url\"),width:el.clientWidth,height:el.clientHeight,correctLevel:QRCode.CorrectLevel.M});});}if(typeof QRCode===\"undefined\"){var i=setInterval(function(){if(window.QRCode){clearInterval(i);gen();}},120);}gen();})();<\\/script></body></html>";w.document.write(htmlStart+grid+htmlEnd);w.document.close();setTimeout(function(){w.focus();},150);}var lk=document.getElementById("wc-apc-open-preview-fallback");if(lk){lk.addEventListener("click",function(e){e.preventDefault();openQrOnly();});}window.wcApcOpenQrOnly=openQrOnly;});</script>';
                    if($auto_open_preview){ echo '<script>document.addEventListener("DOMContentLoaded",function(){setTimeout(function(){if(window.wcApcOpenQrOnly)window.wcApcOpenQrOnly();},60);});</script>'; }
                    break;
                case 'delete_unassigned':
                    if(empty($selected_ids)){
                        echo '<div class="notice notice-warning"><p>'.esc_html__('Select at least one unassigned code to delete.','wooauthentix').'</p></div>';
                        break;
                    }
                    $ph = implode(',', array_fill(0,count($selected_ids),'%d'));
                    $affected = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE status=0 AND id IN ($ph)", ...$selected_ids));
                    echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%d unassigned codes deleted.','wooauthentix'), intval($affected)).'</p></div>';
                    break;
                case 'delete_all_unassigned_filtered':
                    $del_where = $where_parts; // copy filter-derived parts
                    // Ensure status=0 condition present
                    $has_status_zero = false; foreach($del_where as $p){ if(trim($p)==='status = 0'){ $has_status_zero=true; break; } }
                    if(!$has_status_zero){ $del_where[] = 'status = 0'; }
                    $del_where_sql = $del_where ? 'WHERE '.implode(' AND ',$del_where) : '';
                    $cnt = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table $del_where_sql");
                    if($cnt>0){
                        $wpdb->query("DELETE FROM $table $del_where_sql");
                        echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%d unassigned filtered codes deleted.','wooauthentix'), $cnt).'</p></div>';
                    } else {
                        echo '<div class="notice notice-info"><p>'.esc_html__('No unassigned codes matched filter.','wooauthentix').'</p></div>';
                    }
                    break;
                case 'delete_all_filtered':
                    $del_where_sql = $where_sql ?: '';
                    $cnt = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table $del_where_sql");
                    if($cnt>0){
                        $wpdb->query("DELETE FROM $table $del_where_sql");
                        echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%d filtered codes (any status) deleted.','wooauthentix'), $cnt).'</p></div>';
                    } else {
                        echo '<div class="notice notice-info"><p>'.esc_html__('No codes matched filter for deletion.','wooauthentix').'</p></div>';
                    }
                    break;
            }
        }
    }
    $count_sql = 'SELECT COUNT(*) FROM '.$table.' '.$where_sql;
    $total = $params ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : (int)$wpdb->get_var($count_sql);
    // Map orderby to actual column name (product uses product_id). Avoid SQL injection via whitelist mapping only.
    $orderby_column_map = [
        'id' => 'id',
        'code' => 'code',
        'product' => 'product_id',
        'status' => 'status',
        'created_at' => 'created_at',
        'assigned_at' => 'assigned_at',
        'verified_at' => 'verified_at',
        'qr_label_generated' => 'qr_label_generated'
    ];
    $order_col = isset($orderby_column_map[$orderby]) ? $orderby_column_map[$orderby] : 'created_at';
    $query_sql = 'SELECT * FROM '.$table.' '.$where_sql.' ORDER BY '.$order_col.' '.strtoupper($order).' LIMIT %d OFFSET %d';
    $query_params = $params; $query_params[] = $per_page; $query_params[] = $offset;
    $codes = $query_params ? $wpdb->get_results($wpdb->prepare($query_sql, ...$query_params)) : $wpdb->get_results($wpdb->prepare($query_sql, $per_page, $offset));
    // Removed manual POST-based label generation panel + legacy code.

    // Export (page or full)
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && current_user_can('manage_woocommerce') && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'wc_apc_export')) {
        $filename = 'authentic-codes-' . $filter . ($full_export?'-full':'-page') . '-' . date('Ymd-His') . '.csv';
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Product', 'Status', 'Created At', 'Assigned At', 'Verified At', 'QR Label Generated', 'QR Generated At']);
        if ($full_export) {
            $chunk = 1000; $export_offset = 0;
            while (true) {
                $sql_full = 'SELECT * FROM '.$table.' '.$where_sql.' ORDER BY created_at DESC LIMIT %d OFFSET %d';
                $exp_params = $params; $exp_params[] = $chunk; $exp_params[] = $export_offset;
                $batch = $exp_params ? $wpdb->get_results($wpdb->prepare($sql_full, ...$exp_params)) : $wpdb->get_results($wpdb->prepare($sql_full, $chunk, $export_offset));
                if (empty($batch)) break;
                foreach ($batch as $row) {
                    $generic = is_null($row->product_id) || (int)$row->product_id === 0;
                    $product_name = $generic ? __('Generic','wooauthentix') : ( ($p = wc_get_product($row->product_id)) ? $p->get_name() : __('Unknown','wooauthentix') );
                    fputcsv($output, [
                        $row->code,
                        $product_name,
                        wc_apc_status_label($row->status),
                        $row->created_at,
                        $row->assigned_at,
                        $row->verified_at,
                        (int)$row->qr_label_generated === 1 ? '1' : '0',
                        $row->qr_generated_at
                    ]);
                }
                $export_offset += $chunk;
                if ($export_offset >= $total) break;
                @ob_flush(); @flush();
            }
        } else {
            foreach ($codes as $row) {
                $generic = is_null($row->product_id) || (int)$row->product_id === 0;
                $product_name = $generic ? __('Generic','wooauthentix') : ( ($p = wc_get_product($row->product_id)) ? $p->get_name() : __('Unknown','wooauthentix') );
                fputcsv($output, [
                    $row->code,
                    $product_name,
                    wc_apc_status_label($row->status),
                    $row->created_at,
                    $row->assigned_at,
                    $row->verified_at,
                    (int)$row->qr_label_generated === 1 ? '1' : '0',
                    $row->qr_generated_at
                ]);
            }
        }
        fclose($output);
        exit;
    }
    $export_url = add_query_arg([
        'page'=>'wc-apc-codes',
        'filter'=>$filter,
        'orderby'=>$orderby,
        'order'=>$order,
        'per_page'=>$per_page,
        'export'=>'csv',
        '_wpnonce'=>wp_create_nonce('wc_apc_export')
    ]);
    $full_export_url = add_query_arg([
        'page'=>'wc-apc-codes',
        'filter'=>$filter,
        'orderby'=>$orderby,
        'order'=>$order,
        'per_page'=>$per_page,
        'export'=>'csv',
        'full_export'=>'1',
        '_wpnonce'=>wp_create_nonce('wc_apc_export')
    ]);
    // Product list removed (no product filter UI)
    ?>
    <div class="wrap" style="margin-top:2em;">
        <h2><?php echo esc_html__('Authenticity Codes','wooauthentix'); ?></h2>
        <form method="get" style="margin-bottom:1em; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="page" value="wc-apc-codes">
            <label><?php echo esc_html__('Status','wooauthentix'); ?>
                <select name="filter">
                    <option value="all" <?php selected($filter, 'all'); ?>><?php echo esc_html__('All','wooauthentix'); ?></option>
                    <option value="verified" <?php selected($filter, 'verified'); ?>><?php echo esc_html__('Verified','wooauthentix'); ?></option>
                    <option value="assigned" <?php selected($filter, 'assigned'); ?>><?php echo esc_html__('Assigned','wooauthentix'); ?></option>
                    <option value="unassigned" <?php selected($filter, 'unassigned'); ?>><?php echo esc_html__('Unassigned','wooauthentix'); ?></option>
                </select>
            </label>
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
            <!-- Code prefix and date filters removed -->
            <label><?php echo esc_html__('Per page','wooauthentix'); ?>
                <select name="per_page">
                    <?php foreach($allowed_page_sizes as $sz): ?>
                        <option value="<?php echo esc_attr($sz); ?>" <?php selected($per_page,$sz); ?>><?php echo esc_html($sz); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button"><?php echo esc_html__('Filter','wooauthentix'); ?></button>
            <a href="<?php echo esc_url($export_url); ?>" class="button"><?php echo esc_html__('Export CSV','wooauthentix'); ?></a>
            <a href="<?php echo esc_url($full_export_url); ?>" class="button button-secondary"><?php echo esc_html__('Full Export CSV','wooauthentix'); ?></a>
        </form>
    <form method="post">
            <?php wp_nonce_field('wc_apc_bulk_codes','wc_apc_bulk_codes_nonce'); ?>
            <div class="wc-apc-bulk-actions-top" style="margin:0 0 8px; display:flex; gap:8px; align-items:center;">
                <select name="bulk_action" class="wc-apc-bulk-select">
                    <option value=""><?php echo esc_html__('Bulk actions','wooauthentix'); ?></option>
                    <option value="delete_unassigned"><?php echo esc_html__('Delete selected unassigned','wooauthentix'); ?></option>
                    <option value="delete_all_unassigned_filtered"><?php echo esc_html__('Delete ALL unassigned (filtered)','wooauthentix'); ?></option>
                    <option value="delete_all_filtered"><?php echo esc_html__('Delete ALL (filtered, ANY status)','wooauthentix'); ?></option>
                    <option value="generate_qr_labels"><?php echo esc_html__('Generate QR Labels (selected)','wooauthentix'); ?></option>
                </select>
                <button type="submit" name="wc_apc_bulk_delete" class="button button-secondary" onclick="return wcApcBulkDeleteConfirm(this);"><?php echo esc_html__('Apply','wooauthentix'); ?></button>
            </div>
            <style>
            /* Hide ID column (header + cells) while retaining data for checkboxes and internal logic */
            .wc-apc-col-id{display:none;}
            </style>
            <table class="widefat fixed striped">
                <thead>
                    <?php
                    // Helper to build sortable header links
                    function wc_apc_sort_link($label,$col,$current_orderby,$current_order){
                        $next_order = ($current_orderby===$col && $current_order==='asc') ? 'desc':'asc';
                        $arrow = '';
                        if($current_orderby===$col){
                            // Use actual arrow glyphs instead of literal unicode escape text
                            $arrow = $current_order==='asc' ? ' ▲' : ' ▼';
                        }
                        $url = add_query_arg([
                            'page'=>'wc-apc-codes',
                            'filter'=>isset($_GET['filter'])?sanitize_text_field($_GET['filter']):'all',
                            'orderby'=>$col,
                            'order'=>$next_order,
                            'per_page'=>isset($_GET['per_page'])?intval($_GET['per_page']):50,
                            'paged'=>1
                        ]);
                        return '<a href="'.esc_url($url).'" style="text-decoration:none;">'.esc_html($label.$arrow).'</a>';
                    }
                    ?>
                    <tr>
                        <th style="width:24px;"><input type="checkbox" onclick="jQuery('.wc-apc-cb').prop('checked', this.checked);" /></th>
                        <th class="wc-apc-col-id"><?php echo wc_apc_sort_link(__('ID','wooauthentix'),'id',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Code','wooauthentix'),'code',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Product','wooauthentix'),'product',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Status','wooauthentix'),'status',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Created','wooauthentix'),'created_at',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Assigned','wooauthentix'),'assigned_at',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('Verified','wooauthentix'),'verified_at',$orderby,$order); ?></th>
                        <th><?php echo wc_apc_sort_link(__('QR Label','wooauthentix'),'qr_label_generated',$orderby,$order); ?></th>
                        <!-- Actions column removed -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)) : ?>
                        <tr><td colspan="9"><?php echo esc_html__('No codes found.','wooauthentix'); ?></td></tr>
                    <?php else : foreach ($codes as $row) :
                        $generic = is_null($row->product_id) || (int)$row->product_id === 0;
                        $product_name = $generic ? __('Generic','wooauthentix') : ( ($product = wc_get_product($row->product_id)) ? $product->get_name() : __('Unknown','wooauthentix') );
                        ?>
                        <tr>
                            <td><?php if ((int)$row->status === 0) : ?><input type="checkbox" class="wc-apc-cb" name="delete_ids[]" value="<?php echo intval($row->id); ?>" /><?php endif; ?></td>
                            <td class="wc-apc-col-id"><?php echo intval($row->id); ?></td>
                            <td><?php echo esc_html($row->code); ?></td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td><?php echo wp_kses_post(wc_apc_status_badge($row->status)); ?></td>
                            <td><?php echo esc_html($row->created_at); ?></td>
                            <td><?php echo esc_html($row->assigned_at); ?></td>
                            <td><?php echo esc_html($row->verified_at); ?></td>
                            <td><?php echo (isset($row->qr_label_generated) && intval($row->qr_label_generated) === 1) ? '<span style="color:green;font-weight:600;">'.esc_html__('Yes','wooauthentix').'</span>' : '&mdash;'; ?></td>
                            <!-- Per-row actions removed -->
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <!-- Bottom bulk action removed -->
            <script>
            function wcApcBulkDeleteConfirm(btn){
                var form = btn.closest('form');
                var sel = form.querySelector('.wc-apc-bulk-actions-top .wc-apc-bulk-select');
                if(!sel) return false; var act=sel.value; if(!act) return false;
                if(act==='delete_unassigned') return confirm('<?php echo esc_js(__('Delete selected unassigned codes? This cannot be undone.','wooauthentix')); ?>');
                if(act==='delete_all_unassigned_filtered') return confirm('<?php echo esc_js(__('Delete ALL filtered unassigned codes? This cannot be undone. Continue?','wooauthentix')); ?>');
                if(act==='delete_all_filtered') return confirm('<?php echo esc_js(__('DANGEROUS: Delete ALL filtered codes (including assigned / verified). This may orphan orders. Type OK to confirm in the next prompt.','wooauthentix')); ?>') && prompt('<?php echo esc_js(__('Type DELETE to confirm deletion of ALL filtered codes:','wooauthentix')); ?>')==='DELETE';
                if(act==='generate_qr_labels') return true;
                return false;
            }
            (function(){
                var form=document.currentScript.closest('form');
                if(!form) return;
                form.addEventListener('submit', function(e){
                    if(!form.querySelector('[name=wc_apc_bulk_delete]')) return;
                    var ids=[].map.call(form.querySelectorAll('input[name="delete_ids[]"]:checked'), function(cb){return cb.value;});
                    var legacy=[].map.call(form.querySelectorAll('input[name="delete_codes[]"]:checked'), function(cb){return cb.value;});
                    var debugBox=document.getElementById('wc-apc-bulk-debug');
                    if(!debugBox){ debugBox=document.createElement('div'); debugBox.id='wc-apc-bulk-debug'; debugBox.style.display='none'; form.appendChild(debugBox);} 
                    debugBox.innerHTML='<textarea style="display:none" name="_bulk_debug">'+ids.join(',')+' | '+legacy.join(',')+'</textarea>';
                });
            })();
            </script>
            
        </form>
        <?php
        // Pagination
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($p=1;$p<=$total_pages;$p++) {
                $url = esc_url(add_query_arg([
                    'page'=>'wc-apc-codes',
                    'filter'=>$filter,
                    'per_page'=>$per_page,
                    'paged'=>$p,
                    'orderby'=>$orderby,
                    'order'=>$order
                ]));
                $class = $p === $page_num ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo '<a'.$class.' href="'.$url.'">'.intval($p).'</a> ';
            }
            echo '</div></div>';
        }
        ?>
        
    </div>
    <?php
}

if (!function_exists('wc_apc_status_label')) {
    function wc_apc_status_label($status) {
        switch ((int)$status) {
            case 2: return __('Verified','wooauthentix');
            case 1: return __('Assigned','wooauthentix');
            case 0: default: return __('Unassigned','wooauthentix');
        }
    }
}
if (!function_exists('wc_apc_status_badge')) {
    function wc_apc_status_badge($status) {
        $label = wc_apc_status_label($status);
        $color = '#777';
        if ((int)$status === 0) $color = 'green';
        elseif ((int)$status === 1) $color = 'orange';
        elseif ((int)$status === 2) $color = 'blue';
        return '<span style="color:'.$color.';font-weight:bold;">'.esc_html($label).'</span>';
    }
}
