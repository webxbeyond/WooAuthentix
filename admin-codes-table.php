<?php
// admin-codes-table.php
if (!defined('ABSPATH')) exit;

function wc_apc_all_codes_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $table = $wpdb->prefix . (defined('WC_APC_TABLE') ? WC_APC_TABLE : 'wc_authentic_codes');
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    $search_code = isset($_GET['s_code']) ? strtoupper(sanitize_text_field($_GET['s_code'])) : '';
    // Product filter removed: always show all codes
    $product_filter = 0;
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
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
    if ($search_code) { $where_parts[] = 'code LIKE %s'; $params[] = $search_code.'%'; }
    // Removed product filter condition
    if ($date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from)) { $where_parts[] = 'DATE(created_at) >= %s'; $params[] = $date_from; }
    if ($date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)) { $where_parts[] = 'DATE(created_at) <= %s'; $params[] = $date_to; }
    $where_sql = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
    // Bulk deletion logic (selected, all unassigned filtered, all filtered)
    if (isset($_POST['wc_apc_bulk_delete']) && check_admin_referer('wc_apc_bulk_codes')) {
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        if ($bulk_action === 'delete_unassigned') {
            $del_codes = !empty($_POST['delete_codes']) ? array_map('sanitize_text_field', (array)$_POST['delete_codes']) : [];
            if (!empty($del_codes)) {
                $placeholders = implode(',', array_fill(0, count($del_codes), '%s'));
                $sql_del = "DELETE FROM $table WHERE status = 0 AND code IN ($placeholders)";
                $wpdb->query($wpdb->prepare($sql_del, ...$del_codes));
                echo '<div class="notice notice-success"><p>'.esc_html__('Selected unassigned codes deleted.','wooauthentix').'</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>'.esc_html__('No codes selected for deletion.','wooauthentix').'</p></div>';
            }
        } elseif ($bulk_action === 'delete_all_unassigned_filtered') {
            // Rebuild where without any status filter, then force status=0
            $where_no_status = [];
            foreach ($where_parts as $wp_part) {
                if (strpos($wp_part, 'status =') === 0) continue; // skip status filter
                $where_no_status[] = $wp_part;
            }
            $where_force = $where_no_status;
            $where_force[] = 'status = 0';
            $params_force = [];
            // Map original params excluding removed status param(s)
            $param_index = 0;
            foreach ($where_parts as $original_part) {
                if (strpos($original_part, 'status =') === 0) continue; // skip corresponding param
                // Pull next param from $params sequentially
                if (isset($params[$param_index])) {
                    $params_force[] = $params[$param_index];
                }
                $param_index++;
            }
            $where_delete_sql = $where_force ? ('WHERE '.implode(' AND ', $where_force)) : 'WHERE status = 0';
            // Count first
            $cnt_sql = "SELECT COUNT(*) FROM $table $where_delete_sql";
            $to_delete = $params_force ? (int)$wpdb->get_var($wpdb->prepare($cnt_sql, ...$params_force)) : (int)$wpdb->get_var($cnt_sql);
            if ($to_delete > 0) {
                $del_sql = "DELETE FROM $table $where_delete_sql";
                if ($params_force) { $wpdb->query($wpdb->prepare($del_sql, ...$params_force)); } else { $wpdb->query($del_sql); }
            }
            echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%d unassigned codes (filtered) deleted.','wooauthentix'), intval($to_delete)).'</p></div>';
        } elseif ($bulk_action === 'delete_all_filtered') {
            // Dangerous: delete everything matching current filter (including assigned / verified)
            $del_where_sql = $where_sql ?: ''; // uses status filter if chosen
            $cnt_sql = "SELECT COUNT(*) FROM $table $del_where_sql";
            $to_delete = $params ? (int)$wpdb->get_var($wpdb->prepare($cnt_sql, ...$params)) : (int)$wpdb->get_var($cnt_sql);
            if ($to_delete > 0) {
                $del_sql = "DELETE FROM $table $del_where_sql";
                if ($params) { $wpdb->query($wpdb->prepare($del_sql, ...$params)); } else { $wpdb->query($del_sql); }
            }
            echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%d codes (all statuses, filtered) deleted.','wooauthentix'), intval($to_delete)).'</p></div>';
        }
    }
    $count_sql = 'SELECT COUNT(*) FROM '.$table.' '.$where_sql;
    $total = $params ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : (int)$wpdb->get_var($count_sql);
    $query_sql = 'SELECT * FROM '.$table.' '.$where_sql.' ORDER BY created_at DESC LIMIT %d OFFSET %d';
    $query_params = $params; $query_params[] = $per_page; $query_params[] = $offset;
    $codes = $query_params ? $wpdb->get_results($wpdb->prepare($query_sql, ...$query_params)) : $wpdb->get_results($wpdb->prepare($query_sql, $per_page, $offset));
    // Export (page or full)
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && current_user_can('manage_woocommerce') && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'wc_apc_export')) {
        $filename = 'authentic-codes-' . $filter . ($full_export?'-full':'-page') . '-' . date('Ymd-His') . '.csv';
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Code', 'Product', 'Status', 'Created At', 'Assigned At', 'Verified At']);
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
                        $row->verified_at
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
                    $row->verified_at
                ]);
            }
        }
        fclose($output);
        exit;
    }
    $export_url = add_query_arg([
        'page'=>'wc-apc-codes',
        'filter'=>$filter,
        's_code'=>$search_code,
        'date_from'=>$date_from,
        'date_to'=>$date_to,
        'per_page'=>$per_page,
        'export'=>'csv',
        '_wpnonce'=>wp_create_nonce('wc_apc_export')
    ]);
    $full_export_url = add_query_arg([
        'page'=>'wc-apc-codes',
        'filter'=>$filter,
        's_code'=>$search_code,
        'date_from'=>$date_from,
        'date_to'=>$date_to,
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
            <label><?php echo esc_html__('Code begins','wooauthentix'); ?>
                <input type="text" name="s_code" value="<?php echo esc_attr($search_code); ?>" size="12" />
            </label>
            <!-- Product filter removed: always showing all products -->
            <label><?php echo esc_html__('From','wooauthentix'); ?> <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" /></label>
            <label><?php echo esc_html__('To','wooauthentix'); ?> <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" /></label>
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
            <?php wp_nonce_field('wc_apc_bulk_codes'); ?>
            <div class="wc-apc-bulk-actions-top" style="margin:0 0 8px; display:flex; gap:8px; align-items:center;">
                <select name="bulk_action" class="wc-apc-bulk-select">
                    <option value=""><?php echo esc_html__('Bulk actions','wooauthentix'); ?></option>
                    <option value="delete_unassigned"><?php echo esc_html__('Delete selected unassigned','wooauthentix'); ?></option>
                    <option value="delete_all_unassigned_filtered"><?php echo esc_html__('Delete ALL unassigned (filtered)','wooauthentix'); ?></option>
                    <option value="delete_all_filtered"><?php echo esc_html__('Delete ALL (filtered, ANY status)','wooauthentix'); ?></option>
                </select>
                <button type="submit" name="wc_apc_bulk_delete" class="button button-secondary" onclick="return wcApcBulkDeleteConfirm(this);"><?php echo esc_html__('Apply','wooauthentix'); ?></button>
            </div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:24px;"><input type="checkbox" onclick="jQuery('.wc-apc-cb').prop('checked', this.checked);" /></th>
                        <th><?php echo esc_html__('Code','wooauthentix'); ?></th>
                        <th><?php echo esc_html__('Product','wooauthentix'); ?></th>
                        <th><?php echo esc_html__('Status','wooauthentix'); ?></th>
                        <th><?php echo esc_html__('Created','wooauthentix'); ?></th>
                        <th><?php echo esc_html__('Assigned','wooauthentix'); ?></th>
                        <th><?php echo esc_html__('Verified','wooauthentix'); ?></th>
                        <!-- Actions column removed -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)) : ?>
                        <tr><td colspan="7"><?php echo esc_html__('No codes found.','wooauthentix'); ?></td></tr>
                    <?php else : foreach ($codes as $row) :
                        $generic = is_null($row->product_id) || (int)$row->product_id === 0;
                        $product_name = $generic ? __('Generic','wooauthentix') : ( ($product = wc_get_product($row->product_id)) ? $product->get_name() : __('Unknown','wooauthentix') );
                        ?>
                        <tr>
                            <td><?php if ((int)$row->status === 0) : ?><input type="checkbox" class="wc-apc-cb" name="delete_codes[]" value="<?php echo esc_attr($row->code); ?>" /><?php endif; ?></td>
                            <td><?php echo esc_html($row->code); ?></td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td><?php echo wp_kses_post(wc_apc_status_badge($row->status)); ?></td>
                            <td><?php echo esc_html($row->created_at); ?></td>
                            <td><?php echo esc_html($row->assigned_at); ?></td>
                            <td><?php echo esc_html($row->verified_at); ?></td>
                            <!-- Per-row actions removed -->
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                <select name="bulk_action" class="wc-apc-bulk-select">
                    <option value=""><?php echo esc_html__('Bulk actions','wooauthentix'); ?></option>
                    <option value="delete_unassigned"><?php echo esc_html__('Delete selected unassigned','wooauthentix'); ?></option>
                    <option value="delete_all_unassigned_filtered"><?php echo esc_html__('Delete ALL unassigned (filtered)','wooauthentix'); ?></option>
                    <option value="delete_all_filtered"><?php echo esc_html__('Delete ALL (filtered, ANY status)','wooauthentix'); ?></option>
                </select>
                <button type="submit" name="wc_apc_bulk_delete" class="button button-secondary" onclick="return wcApcBulkDeleteConfirm(this);"><?php echo esc_html__('Apply','wooauthentix'); ?></button>
            </div>
            <script>
            function wcApcBulkDeleteConfirm(btn){
                var sel = btn.closest('form').querySelector('.wc-apc-bulk-select');
                if(!sel) return false; var act=sel.value; if(!act) return false;
                if(act==='delete_unassigned') return confirm('<?php echo esc_js(__('Delete selected unassigned codes? This cannot be undone.','wooauthentix')); ?>');
                if(act==='delete_all_unassigned_filtered') return confirm('<?php echo esc_js(__('Delete ALL filtered unassigned codes? This cannot be undone. Continue?','wooauthentix')); ?>');
                if(act==='delete_all_filtered') return confirm('<?php echo esc_js(__('DANGEROUS: Delete ALL filtered codes (including assigned / verified). This may orphan orders. Type OK to confirm in the next prompt.','wooauthentix')); ?>') && prompt('<?php echo esc_js(__('Type DELETE to confirm deletion of ALL filtered codes:','wooauthentix')); ?>')==='DELETE';
                return false;
            }
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
                    's_code'=>$search_code,
                    // product_id removed
                    'per_page'=>$per_page,
                    'paged'=>$p
                ]));
                $class = $p === $page_num ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo '<a'.$class.' href="'.$url.'">'.intval($p).'</a> ';
            }
            echo '</div></div>';
        }
        ?>
        <p style="margin-top:1em;"><?php echo esc_html__('Showing paginated results. Use export for filtered page.','wooauthentix'); ?></p>
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
