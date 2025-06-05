<?php
/**
 * Vendor Dashboard Earnings - Database Functions
 */

if ( ! defined( 'WPINC' ) ) { die; }

global $wpdb;
define( 'VDB_EARNINGS_LOG_TABLE', $wpdb->prefix . 'vdb_earnings_log' );
define( 'VDB_PAYOUTS_LOG_TABLE', $wpdb->prefix . 'vdb_payouts_log' );

/**
 * Create custom database tables for earnings and payouts.
 * Earnings log now stores per order item.
 */
function vdb_earnings_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql_earnings_log = "CREATE TABLE " . VDB_EARNINGS_LOG_TABLE . " (
        earning_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vendor_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT UNSIGNED NOT NULL,
        order_item_id BIGINT UNSIGNED NOT NULL, 
        product_id BIGINT UNSIGNED NOT NULL,
        quantity_sold INT UNSIGNED NOT NULL DEFAULT 1,
        gross_amount_vendor_items DECIMAL(10,2) NOT NULL DEFAULT '0.00', 
        commission_rate DECIMAL(4,2) NOT NULL DEFAULT '0.10',
        commission_amount DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        net_earning DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        currency VARCHAR(3) NOT NULL,
        order_date DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'available', 
        payout_id BIGINT UNSIGNED NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (earning_id),
        INDEX idx_vendor_id (vendor_id),
        INDEX idx_order_id (order_id),
        INDEX idx_order_item_id (order_item_id),
        INDEX idx_product_id (product_id),
        INDEX idx_status (status),
        INDEX idx_payout_id (payout_id)
    ) $charset_collate;";

    $sql_payouts_log = "CREATE TABLE " . VDB_PAYOUTS_LOG_TABLE . " (
        payout_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vendor_id BIGINT UNSIGNED NOT NULL,
        amount_paid DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        currency VARCHAR(3) NOT NULL,
        payout_date DATETIME NOT NULL,
        payout_method VARCHAR(50) NOT NULL,
        transaction_ref VARCHAR(255) NULL DEFAULT NULL,
        notes TEXT NULL DEFAULT NULL,
        processed_by_admin_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (payout_id),
        INDEX idx_vendor_id (vendor_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_earnings_log );
    dbDelta( $sql_payouts_log );

    if ($wpdb->get_var("SHOW TABLES LIKE '" . VDB_EARNINGS_LOG_TABLE . "'") != VDB_EARNINGS_LOG_TABLE) {
        error_log('VDB Error: Earnings log table (' . VDB_EARNINGS_LOG_TABLE . ') not created/updated.');
    }
    if ($wpdb->get_var("SHOW TABLES LIKE '" . VDB_PAYOUTS_LOG_TABLE . "'") != VDB_PAYOUTS_LOG_TABLE) {
        error_log('VDB Error: Payouts log table (' . VDB_PAYOUTS_LOG_TABLE . ') not created.');
    }
}

function vdb_earnings_insert_earning_log( $earning_data ) {
    global $wpdb;
    $existing_earning = $wpdb->get_var( $wpdb->prepare(
        "SELECT earning_id FROM " . VDB_EARNINGS_LOG_TABLE . " WHERE vendor_id = %d AND order_id = %d AND order_item_id = %d",
        $earning_data['vendor_id'], $earning_data['order_id'], $earning_data['order_item_id']
    ) );
    if ( $existing_earning ) {
        error_log('VDB Info: Attempted to insert duplicate earning for order_id: ' . $earning_data['order_id'] . ', item_id: ' . $earning_data['order_item_id'] . ' and vendor_id: ' . $earning_data['vendor_id']);
        return false;
    }
    $defaults = array( 'status' => 'available', 'created_at' => current_time( 'mysql' ), );
    $data = wp_parse_args( $earning_data, $defaults );
    $result = $wpdb->insert( VDB_EARNINGS_LOG_TABLE, $data );
    if ( $result ) { return $wpdb->insert_id; }
    return false;
}

function vdb_get_vendor_earnings_summary_data( $vendor_id ) {
    global $wpdb;
    $vendor_id = absint( $vendor_id );
    $summary = array( 'current_balance' => 0.00, 'total_credited'  => 0.00, 'total_paid_out'  => 0.00, );
    $summary['current_balance'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_earning) FROM " . VDB_EARNINGS_LOG_TABLE . " WHERE vendor_id = %d AND status = 'available'", $vendor_id ) );
    $summary['total_credited'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_earning) FROM " . VDB_EARNINGS_LOG_TABLE . " WHERE vendor_id = %d AND status IN ('available', 'paid')", $vendor_id ) );
    $summary['total_paid_out'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount_paid) FROM " . VDB_PAYOUTS_LOG_TABLE . " WHERE vendor_id = %d", $vendor_id ) );
    return $summary;
}

function vdb_get_vendor_transaction_history( $vendor_id, $items_per_page = 15, $paged = 1 ) {
    global $wpdb;
    $vendor_id = absint( $vendor_id ); $offset = ( $paged - 1 ) * $items_per_page;
    $earnings_sql = $wpdb->prepare( "SELECT earning_id as id, order_id, order_item_id, product_id, net_earning as amount, currency, order_date as date, status, 'earning' as type, NULL as payout_method FROM " . VDB_EARNINGS_LOG_TABLE . " WHERE vendor_id = %d", $vendor_id );
    $payouts_sql = $wpdb->prepare( "SELECT payout_id as id, NULL as order_id, NULL as order_item_id, NULL as product_id, amount_paid * -1 as amount, currency, payout_date as date, 'paid' as status, 'payout' as type, payout_method FROM " . VDB_PAYOUTS_LOG_TABLE . " WHERE vendor_id = %d", $vendor_id );
    $count_sql = "SELECT COUNT(*) FROM ( ($earnings_sql) UNION ALL ($payouts_sql) ) AS combined_transactions";
    $total_items = (int) $wpdb->get_var( $count_sql ); $total_pages = ceil( $total_items / $items_per_page );
    $combined_sql = "SELECT * FROM ( ($earnings_sql) UNION ALL ($payouts_sql) ) AS combined_transactions ORDER BY date DESC LIMIT %d OFFSET %d";
    $transactions = $wpdb->get_results( $wpdb->prepare( $combined_sql, $items_per_page, $offset ), ARRAY_A );
    return array( 'transactions' => $transactions, 'total_items'  => $total_items, 'total_pages'  => $total_pages, );
}

function vdb_earnings_insert_payout_log( $payout_data ) {
    global $wpdb;
    $defaults = array( 'currency' => get_woocommerce_currency(), 'payout_date' => current_time( 'mysql' ), 'created_at' => current_time( 'mysql' ), 'processed_by_admin_id' => get_current_user_id(), );
    $data = wp_parse_args( $payout_data, $defaults ); $result = $wpdb->insert( VDB_PAYOUTS_LOG_TABLE, $data );
    if ( $result ) { return $wpdb->insert_id; } return false;
}

function vdb_mark_earnings_as_paid( $vendor_id, $paid_amount, $payout_id ) {
    global $wpdb; $vendor_id = absint( $vendor_id ); $paid_amount = floatval( $paid_amount ); $payout_id = absint( $payout_id ); $amount_to_cover = $paid_amount; $updated_any = false;
    $available_earnings = $wpdb->get_results( $wpdb->prepare( "SELECT earning_id, net_earning FROM " . VDB_EARNINGS_LOG_TABLE . " WHERE vendor_id = %d AND status = 'available' ORDER BY order_date ASC, earning_id ASC", $vendor_id ), OBJECT_K );
    if ( empty( $available_earnings ) ) { return false; }
    foreach ( $available_earnings as $earning ) { if ( $amount_to_cover <= 0 ) { break; } $earning_value = floatval( $earning->net_earning ); if ( $earning_value <= $amount_to_cover ) { $wpdb->update( VDB_EARNINGS_LOG_TABLE, array( 'status' => 'paid', 'payout_id' => $payout_id ), array( 'earning_id' => $earning->earning_id ), array( '%s', '%d' ), array( '%d' ) ); $amount_to_cover -= $earning_value; $updated_any = true; } else { break; } }
    return $updated_any;
}

function vdb_admin_get_vendors_with_balances() {
    global $wpdb;
    $sql = "SELECT u.ID as vendor_id, u.display_name, um.meta_value as brand_name, COALESCE(SUM(el.net_earning), 0.00) as available_balance, MIN(el.currency) as currency FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'vdb_brand_name' LEFT JOIN " . VDB_EARNINGS_LOG_TABLE . " el ON u.ID = el.vendor_id AND el.status = 'available' INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = '{$wpdb->prefix}capabilities' AND cap.meta_value LIKE '%\"" . VENDOR_DASHBOARD_ROLE . "\"%' GROUP BY u.ID, u.display_name, um.meta_value ORDER BY u.display_name ASC";
    return $wpdb->get_results( $sql, ARRAY_A );
}

function vdb_admin_get_all_payout_history( $args = array() ) {
    global $wpdb; $defaults = array( 'items_per_page' => 20, 'paged' => 1, 'filter_vendor_id' => 0, 'filter_date_from' => '', 'filter_date_to' => '', ); $args = wp_parse_args( $args, $defaults ); $items_per_page = absint( $args['items_per_page'] ); $paged = absint( $args['paged'] ); $offset = ( $paged - 1 ) * $items_per_page;
    $select_clause = "SELECT pl.*, v.display_name as vendor_display_name, v_um.meta_value as vendor_brand_name, a.display_name as admin_display_name FROM " . VDB_PAYOUTS_LOG_TABLE . " pl LEFT JOIN {$wpdb->users} v ON pl.vendor_id = v.ID LEFT JOIN {$wpdb->usermeta} v_um ON pl.vendor_id = v_um.user_id AND v_um.meta_key = 'vdb_brand_name' LEFT JOIN {$wpdb->users} a ON pl.processed_by_admin_id = a.ID";
    $where_clauses = array("1=1"); $params = array();
    if ( ! empty( $args['filter_vendor_id'] ) ) { $where_clauses[] = "pl.vendor_id = %d"; $params[] = absint( $args['filter_vendor_id'] ); } if ( ! empty( $args['filter_date_from'] ) ) { $where_clauses[] = "pl.payout_date >= %s"; $params[] = $args['filter_date_from'] . ' 00:00:00'; } if ( ! empty( $args['filter_date_to'] ) ) { $where_clauses[] = "pl.payout_date <= %s"; $params[] = $args['filter_date_to'] . ' 23:59:59'; }
    $where_sql = " WHERE " . implode( " AND ", $where_clauses ); $count_sql = "SELECT COUNT(pl.payout_id) FROM " . VDB_PAYOUTS_LOG_TABLE . " pl " . $where_sql;
    $total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // Always prepare if params can exist
    $total_pages = ceil( $total_items / $items_per_page );
    $order_by_clause = " ORDER BY pl.payout_date DESC, pl.payout_id DESC"; $limit_clause = $wpdb->prepare( " LIMIT %d OFFSET %d", $items_per_page, $offset ); $main_query_sql = $select_clause . $where_sql . $order_by_clause . $limit_clause;
    $payouts = $wpdb->get_results( $wpdb->prepare( $main_query_sql, $params ), ARRAY_A ); // Always prepare
    return array( 'payouts' => $payouts, 'total_items' => $total_items, 'total_pages' => $total_pages, );
}

// --- NEW FUNCTIONS FOR REPORTS ---

function vdb_get_platform_overall_earnings_summary( $args = array() ) {
    global $wpdb;
    $date_params = array();
    $earnings_date_conditions = "";
    $payout_date_conditions = "";
    $available_date_conditions = "";

    if ( ! empty( $args['date_from'] ) ) {
        $earnings_date_conditions .= $wpdb->prepare(" AND el.order_date >= %s", $args['date_from'] . ' 00:00:00');
        $payout_date_conditions .= $wpdb->prepare(" AND pl.payout_date >= %s", $args['date_from'] . ' 00:00:00');
        $available_date_conditions .= $wpdb->prepare(" AND el.order_date >= %s", $args['date_from'] . ' 00:00:00'); // For outstanding, filter earnings by order_date
    }
    if ( ! empty( $args['date_to'] ) ) {
        $earnings_date_conditions .= $wpdb->prepare(" AND el.order_date <= %s", $args['date_to'] . ' 23:59:59');
        $payout_date_conditions .= $wpdb->prepare(" AND pl.payout_date <= %s", $args['date_to'] . ' 23:59:59');
        $available_date_conditions .= $wpdb->prepare(" AND el.order_date <= %s", $args['date_to'] . ' 23:59:59');
    }

    $sql_gross = "SELECT SUM(el.gross_amount_vendor_items) FROM " . VDB_EARNINGS_LOG_TABLE . " el WHERE el.status IN ('available', 'paid')" . $earnings_date_conditions;
    $sql_commission = "SELECT SUM(el.commission_amount) FROM " . VDB_EARNINGS_LOG_TABLE . " el WHERE el.status IN ('available', 'paid')" . $earnings_date_conditions;
    $sql_net_vendor = "SELECT SUM(el.net_earning) FROM " . VDB_EARNINGS_LOG_TABLE . " el WHERE el.status IN ('available', 'paid')" . $earnings_date_conditions;
    $sql_paid_out = "SELECT SUM(pl.amount_paid) FROM " . VDB_PAYOUTS_LOG_TABLE . " pl WHERE 1=1" . $payout_date_conditions;
    $sql_outstanding = "SELECT SUM(el.net_earning) FROM " . VDB_EARNINGS_LOG_TABLE . " el WHERE el.status = 'available'" . $available_date_conditions;

    $summary = array(
        'total_gross_sales'         => (float) $wpdb->get_var( $sql_gross ),
        'total_platform_commission' => (float) $wpdb->get_var( $sql_commission ),
        'total_net_vendor_earnings' => (float) $wpdb->get_var( $sql_net_vendor ),
        'total_paid_out'            => (float) $wpdb->get_var( $sql_paid_out ),
        'total_outstanding_balance' => (float) $wpdb->get_var( $sql_outstanding ),
    );
    return $summary;
}


function vdb_get_vendor_performance_summary($args = array()) {
    global $wpdb;
    $items_per_page = isset($args['items_per_page']) ? absint($args['items_per_page']) : 20;
    $paged = isset($args['paged']) ? absint($args['paged']) : 1;
    $offset = ($paged - 1) * $items_per_page;

    $base_params = array(); // For count query
    $query_params = array(); // For main query, might include date params twice

    $date_where_clause_earnings = "";
    $date_where_clause_payouts = "";
    
    if (!empty($args['date_from'])) {
        $date_from_sql = $args['date_from'] . ' 00:00:00';
        $date_where_clause_earnings .= " AND el.order_date >= %s";
        $date_where_clause_payouts .= " AND pl.payout_date >= %s";
        $base_params[] = $date_from_sql; 
        $query_params[] = $date_from_sql; // For earnings part
        $query_params[] = $date_from_sql; // For payouts part
    }
    if (!empty($args['date_to'])) {
        $date_to_sql = $args['date_to'] . ' 23:59:59';
        $date_where_clause_earnings .= " AND el.order_date <= %s";
        $date_where_clause_payouts .= " AND pl.payout_date <= %s";
        $base_params[] = $date_to_sql;
        $query_params[] = $date_to_sql; // For earnings part
        $query_params[] = $date_to_sql; // For payouts part
    }
    
    $vendor_filter_sql_part = "";
    if (!empty($args['vendor_id'])) {
        $vendor_filter_sql_part = " AND u.ID = %d ";
        $base_params[] = $args['vendor_id'];
        $query_params[] = $args['vendor_id'];
    }

    $count_sql = "SELECT COUNT(DISTINCT u.ID)
                  FROM {$wpdb->users} u
                  INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
                  WHERE 1=1 {$vendor_filter_sql_part}";
    $count_query_params = array_merge( ["{$wpdb->prefix}capabilities", '%"' . VENDOR_DASHBOARD_ROLE . '"%'], $base_params);
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_query_params));


    $sql = "SELECT
                u.ID as vendor_id,
                COALESCE(um.meta_value, u.display_name) as vendor_name,
                COALESCE(SUM(CASE WHEN el.status IN ('available', 'paid') THEN el.gross_amount_vendor_items ELSE 0 END), 0) as total_gross_sales,
                COALESCE(SUM(CASE WHEN el.status IN ('available', 'paid') THEN el.commission_amount ELSE 0 END), 0) as total_platform_commission,
                COALESCE(SUM(CASE WHEN el.status IN ('available', 'paid') THEN el.net_earning ELSE 0 END), 0) as total_net_earnings,
                COALESCE(po.total_paid_out, 0) as total_paid_out,
                COALESCE(SUM(CASE WHEN el.status = 'available' THEN el.net_earning ELSE 0 END), 0) as current_available_balance
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'vdb_brand_name'
            LEFT JOIN " . VDB_EARNINGS_LOG_TABLE . " el ON u.ID = el.vendor_id {$date_where_clause_earnings}
            LEFT JOIN (
                SELECT vendor_id, SUM(amount_paid) as total_paid_out
                FROM " . VDB_PAYOUTS_LOG_TABLE . " pl
                WHERE 1=1 {$date_where_clause_payouts}
                GROUP BY vendor_id
            ) po ON u.ID = po.vendor_id
            WHERE 1=1 {$vendor_filter_sql_part}
            GROUP BY u.ID, vendor_name, po.total_paid_out
            ORDER BY vendor_name ASC
            LIMIT %d OFFSET %d";
    
    // Construct final parameters for the main query
    $final_query_params = array_merge( ["{$wpdb->prefix}capabilities", '%"' . VENDOR_DASHBOARD_ROLE . '"%'], $query_params, [$items_per_page, $offset]);
    $vendors = $wpdb->get_results($wpdb->prepare($sql, $final_query_params), ARRAY_A);

    return array('vendors' => $vendors, 'total_items' => $total_items);
}


function vdb_get_product_performance_summary($args = array()) {
    global $wpdb;
    $items_per_page = isset($args['items_per_page']) ? absint($args['items_per_page']) : 20;
    $paged = isset($args['paged']) ? absint($args['paged']) : 1;
    $offset = ($paged - 1) * $items_per_page;

    $where_clauses = array("el.status IN ('available', 'paid')");
    $params = array();

    if (!empty($args['date_from'])) { $where_clauses[] = "el.order_date >= %s"; $params[] = $args['date_from'] . ' 00:00:00'; }
    if (!empty($args['date_to'])) { $where_clauses[] = "el.order_date <= %s"; $params[] = $args['date_to'] . ' 23:59:59'; }
    if (!empty($args['vendor_id'])) { $where_clauses[] = "el.vendor_id = %d"; $params[] = $args['vendor_id']; }
    if (!empty($args['category_id'])) {
        $where_clauses[] = "p.ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' AND t.term_id = %d))";
        $params[] = $args['category_id'];
    }
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    $count_sql = "SELECT COUNT(DISTINCT el.product_id) 
                  FROM " . VDB_EARNINGS_LOG_TABLE . " el 
                  LEFT JOIN {$wpdb->posts} p ON el.product_id = p.ID" . $where_sql; // p is used for category filter if active
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

    $sql = "SELECT 
                el.product_id,
                p.post_title as product_name,
                el.vendor_id,
                COALESCE(um.meta_value, u.display_name) as vendor_name,
                SUM(el.quantity_sold) as units_sold,
                SUM(el.gross_amount_vendor_items) as total_gross_sales,
                SUM(el.commission_amount) as total_platform_commission,
                SUM(el.net_earning) as total_net_earnings
            FROM " . VDB_EARNINGS_LOG_TABLE . " el
            JOIN {$wpdb->posts} p ON el.product_id = p.ID
            JOIN {$wpdb->users} u ON el.vendor_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um ON el.vendor_id = um.user_id AND um.meta_key = 'vdb_brand_name'
            " . $where_sql . "
            GROUP BY el.product_id, p.post_title, el.vendor_id, vendor_name
            ORDER BY total_gross_sales DESC
            LIMIT %d OFFSET %d";
    
    $full_params = array_merge($params, [$items_per_page, $offset]);
    $products = $wpdb->get_results($wpdb->prepare($sql, $full_params), ARRAY_A);

    return array('products' => $products, 'total_items' => $total_items);
}

function vdb_get_detailed_earnings_log($args = array()) {
    global $wpdb;
    $items_per_page = isset($args['items_per_page']) ? absint($args['items_per_page']) : 25;
    $paged = isset($args['paged']) ? absint($args['paged']) : 1;
    $offset = ($paged - 1) * $items_per_page;

    $where_clauses = array("1=1"); // Start with a base true condition
    $params = array();

    if (!empty($args['date_from'])) { $where_clauses[] = "el.order_date >= %s"; $params[] = $args['date_from'] . ' 00:00:00'; }
    if (!empty($args['date_to'])) { $where_clauses[] = "el.order_date <= %s"; $params[] = $args['date_to'] . ' 23:59:59'; }
    if (!empty($args['vendor_id'])) { $where_clauses[] = "el.vendor_id = %d"; $params[] = $args['vendor_id']; }
    if (!empty($args['category_id'])) {
         $where_clauses[] = "el.product_id IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' AND t.term_id = %d))";
        $params[] = $args['category_id'];
    }
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    $count_sql = "SELECT COUNT(el.earning_id) FROM " . VDB_EARNINGS_LOG_TABLE . " el " . $where_sql; // No need for p join in count if category filter is handled as subquery for product_id
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
    
    $sql = "SELECT 
                el.*,
                p.post_title as product_name,
                COALESCE(um.meta_value, u.display_name) as vendor_name
            FROM " . VDB_EARNINGS_LOG_TABLE . " el
            LEFT JOIN {$wpdb->posts} p ON el.product_id = p.ID
            LEFT JOIN {$wpdb->users} u ON el.vendor_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um ON el.vendor_id = um.user_id AND um.meta_key = 'vdb_brand_name'
            " . $where_sql . "
            ORDER BY el.order_date DESC, el.earning_id DESC
            LIMIT %d OFFSET %d";

    $full_params = array_merge($params, [$items_per_page, $offset]);
    $earnings = $wpdb->get_results($wpdb->prepare($sql, $full_params), ARRAY_A);

    return array('earnings' => $earnings, 'total_items' => $total_items);
}

?>