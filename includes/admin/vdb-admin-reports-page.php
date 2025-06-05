<?php
/**
 * Vendor Dashboard - Admin Earnings & Commission Reports Page
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Register the admin submenu page for reports.
 */
function vdb_admin_register_reports_page() {
    add_submenu_page(
        'vendor-admin-settings', // Parent slug
        __( 'Vendor Earnings Reports', 'vendor-dashboard' ), // Page title
        __( 'Earnings Reports', 'vendor-dashboard' ), // Menu title
        'manage_woocommerce', // Capability
        'vdb-earnings-reports', // Menu slug
        'vdb_admin_render_reports_page' // Callback function
    );
}
add_action( 'admin_menu', 'vdb_admin_register_reports_page' );

/**
 * Render the admin reports page.
 */
function vdb_admin_render_reports_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to view these reports.', 'vendor-dashboard' ) );
    }

    // --- Get Filters ---
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overall_summary';

    $filter_period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'all_time';
    $filter_vendor = isset( $_GET['filter_vendor_id'] ) ? absint( $_GET['filter_vendor_id'] ) : 0;
    $filter_category = isset( $_GET['filter_cat_id'] ) ? absint( $_GET['filter_cat_id'] ) : 0;
    $custom_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
    $custom_date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

    list($date_from, $date_to) = vdb_parse_date_period_filter($filter_period, $custom_date_from, $custom_date_to);

    $report_args = array(
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'vendor_id' => $filter_vendor,
        'category_id' => $filter_category,
    );

    $all_vendors_users = get_users( array( 'role' => VENDOR_DASHBOARD_ROLE, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array('ID', 'display_name') ) );
    $product_categories = get_terms( array('taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC') );

    ?>
    <div class="wrap vdb-admin-page vdb-reports-page">
        <h1><?php esc_html_e( 'Vendor Earnings & Commission Reports', 'vendor-dashboard' ); ?></h1>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-earnings-reports&tab=overall_summary')); ?>" class="nav-tab <?php echo $current_tab === 'overall_summary' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Overall Summary', 'vendor-dashboard'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-earnings-reports&tab=vendor_performance')); ?>" class="nav-tab <?php echo $current_tab === 'vendor_performance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Vendor Performance', 'vendor-dashboard'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-earnings-reports&tab=product_performance')); ?>" class="nav-tab <?php echo $current_tab === 'product_performance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Product Performance', 'vendor-dashboard'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-earnings-reports&tab=detailed_log')); ?>" class="nav-tab <?php echo $current_tab === 'detailed_log' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Detailed Earnings Log', 'vendor-dashboard'); ?></a>
        </nav>

        <form method="GET" class="vdb-report-filters">
            <input type="hidden" name="page" value="vdb-earnings-reports">
            <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">

            <select name="period">
                <option value="all_time" <?php selected($filter_period, 'all_time'); ?>><?php esc_html_e('All Time', 'vendor-dashboard'); ?></option>
                <option value="this_month" <?php selected($filter_period, 'this_month'); ?>><?php esc_html_e('This Month', 'vendor-dashboard'); ?></option>
                <option value="last_month" <?php selected($filter_period, 'last_month'); ?>><?php esc_html_e('Last Month', 'vendor-dashboard'); ?></option>
                <option value="last_7_days" <?php selected($filter_period, 'last_7_days'); ?>><?php esc_html_e('Last 7 Days', 'vendor-dashboard'); ?></option>
                <option value="last_30_days" <?php selected($filter_period, 'last_30_days'); ?>><?php esc_html_e('Last 30 Days', 'vendor-dashboard'); ?></option>
                <option value="this_year" <?php selected($filter_period, 'this_year'); ?>><?php esc_html_e('This Year', 'vendor-dashboard'); ?></option>
                <option value="custom" <?php selected($filter_period, 'custom'); ?>><?php esc_html_e('Custom Date Range', 'vendor-dashboard'); ?></option>
            </select>

            <span id="vdb-custom-date-range-fields" style="<?php echo $filter_period === 'custom' ? '' : 'display:none;'; ?>">
                <input type="date" name="date_from" value="<?php echo esc_attr($custom_date_from); ?>" placeholder="<?php esc_attr_e('From YYYY-MM-DD', 'vendor-dashboard'); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($custom_date_to); ?>" placeholder="<?php esc_attr_e('To YYYY-MM-DD', 'vendor-dashboard'); ?>">
            </span>

            <?php if ( $current_tab === 'vendor_performance' || $current_tab === 'product_performance' || $current_tab === 'detailed_log' ) : ?>
            <select name="filter_vendor_id">
                <option value="0"><?php esc_html_e('All Vendors', 'vendor-dashboard'); ?></option>
                <?php foreach ($all_vendors_users as $vendor) :
                    $brand_name = vdb_get_vendor_brand_name($vendor->ID) ?: $vendor->display_name;
                ?>
                    <option value="<?php echo esc_attr($vendor->ID); ?>" <?php selected($filter_vendor, $vendor->ID); ?>><?php echo esc_html($brand_name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <?php if ( $current_tab === 'product_performance' ) : ?>
            <select name="filter_cat_id">
                <option value="0"><?php esc_html_e('All Categories', 'vendor-dashboard'); ?></option>
                <?php foreach ($product_categories as $category) : ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($filter_category, $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit" class="button button-primary"><?php esc_html_e('Filter Report', 'vendor-dashboard'); ?></button>
             <?php if ($filter_period !== 'all_time' || $filter_vendor || $filter_category || $custom_date_from || $custom_date_to): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-earnings-reports&tab=' . $current_tab)); ?>" class="button"><?php esc_html_e('Clear Filters', 'vendor-dashboard'); ?></a>
            <?php endif; ?>
        </form>

        <div class="vdb-report-content vdb-admin-section">
            <?php
            switch ($current_tab) {
                case 'vendor_performance':
                    vdb_render_vendor_performance_report($report_args);
                    break;
                case 'product_performance':
                    vdb_render_product_performance_report($report_args);
                    break;
                case 'detailed_log':
                    vdb_render_detailed_earnings_log_report($report_args);
                    break;
                case 'overall_summary':
                default:
                    vdb_render_overall_summary_report($report_args);
                    break;
            }
            ?>
        </div>
    </div>
    <style>
        .vdb-reports-page .nav-tab-wrapper { margin-bottom: 20px; }
        .vdb-report-filters { margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;}
        .vdb-report-filters select, .vdb-report-filters input[type="date"] { margin-right: 5px; }
        .vdb-report-content table { margin-top: 15px; }
        .vdb-summary-kpis { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
        .vdb-summary-kpis .kpi-box { background: #fff; border: 1px solid #e5e5e5; padding: 15px; flex: 1; min-width: 200px; text-align: center; border-radius: 3px; }
        .vdb-summary-kpis .kpi-box h4 { margin: 0 0 5px 0; font-size: 1em; color: #555; }
        .vdb-summary-kpis .kpi-box .amount { font-size: 1.8em; font-weight: 600; color: #333; }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('select[name="period"]').on('change', function(){
                if ($(this).val() === 'custom') {
                    $('#vdb-custom-date-range-fields').show();
                } else {
                    $('#vdb-custom-date-range-fields').hide();
                    $('#vdb-custom-date-range-fields input[type="date"]').val('');
                }
            }).trigger('change'); // Trigger on page load
        });
    </script>
    <?php
}

/**
 * Helper to parse date period filter into date_from and date_to.
 */
function vdb_parse_date_period_filter($period, $custom_from, $custom_to) {
    $date_from = '';
    $date_to = '';
    $current_time = current_time('timestamp');

    switch ($period) {
        case 'this_month':
            $date_from = date('Y-m-01', $current_time);
            $date_to = date('Y-m-t', $current_time);
            break;
        case 'last_month':
            $date_from = date('Y-m-01', strtotime('first day of last month', $current_time));
            $date_to = date('Y-m-t', strtotime('last day of last month', $current_time));
            break;
        case 'last_7_days':
            $date_from = date('Y-m-d', strtotime('-6 days', $current_time)); // Including today
            $date_to = date('Y-m-d', $current_time);
            break;
        case 'last_30_days':
            $date_from = date('Y-m-d', strtotime('-29 days', $current_time)); // Including today
            $date_to = date('Y-m-d', $current_time);
            break;
        case 'this_year':
            $date_from = date('Y-01-01', $current_time);
            $date_to = date('Y-12-31', $current_time);
            break;
        case 'custom':
            $date_from = !empty($custom_from) ? date('Y-m-d', strtotime($custom_from)) : '';
            $date_to = !empty($custom_to) ? date('Y-m-d', strtotime($custom_to)) : '';
            break;
        case 'all_time':
        default:
            // No date filtering
            break;
    }
    return array($date_from, $date_to);
}

// --- Render functions for each report tab ---

function vdb_render_overall_summary_report($report_args) {
    $summary = vdb_get_platform_overall_earnings_summary($report_args);
    ?>
    <h3><?php esc_html_e('Platform Earnings Overview', 'vendor-dashboard'); ?></h3>
    <div class="vdb-summary-kpis">
        <div class="kpi-box">
            <h4><?php esc_html_e('Total Gross Sales (Vendor Items)', 'vendor-dashboard'); ?></h4>
            <span class="amount"><?php echo wc_price($summary['total_gross_sales']); ?></span>
        </div>
        <div class="kpi-box">
            <h4><?php esc_html_e('Total Platform Commission', 'vendor-dashboard'); ?></h4>
            <span class="amount"><?php echo wc_price($summary['total_platform_commission']); ?></span>
        </div>
        <div class="kpi-box">
            <h4><?php esc_html_e('Total Net Earnings to Vendors', 'vendor-dashboard'); ?></h4>
            <span class="amount"><?php echo wc_price($summary['total_net_vendor_earnings']); ?></span>
        </div>
        <div class="kpi-box">
            <h4><?php esc_html_e('Total Paid Out to Vendors', 'vendor-dashboard'); ?></h4>
            <span class="amount"><?php echo wc_price($summary['total_paid_out']); ?></span>
        </div>
         <div class="kpi-box">
            <h4><?php esc_html_e('Total Outstanding (Payable)', 'vendor-dashboard'); ?></h4>
            <span class="amount"><?php echo wc_price($summary['total_outstanding_balance']); ?></span>
        </div>
    </div>
    <?php
}

function vdb_render_vendor_performance_report($report_args) {
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1; // For WP_List_Table or custom pagination
    $report_args['paged'] = $paged;
    $report_args['items_per_page'] = 20; // Or make configurable

    $data = vdb_get_vendor_performance_summary($report_args);
    ?>
    <h3><?php esc_html_e('Vendor Performance Summary', 'vendor-dashboard'); ?></h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Vendor', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Gross Sales', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Platform Commission', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Net Earnings', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Paid Out', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Available Balance', 'vendor-dashboard'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($data['vendors'])) : foreach ($data['vendors'] as $vendor_data) : ?>
            <tr>
                <td><?php echo esc_html($vendor_data['vendor_name']); ?> (ID: <?php echo esc_html($vendor_data['vendor_id']); ?>)</td>
                <td><?php echo wc_price($vendor_data['total_gross_sales']); ?></td>
                <td><?php echo wc_price($vendor_data['total_platform_commission']); ?></td>
                <td><?php echo wc_price($vendor_data['total_net_earnings']); ?></td>
                <td><?php echo wc_price($vendor_data['total_paid_out']); ?></td>
                <td><?php echo wc_price($vendor_data['current_available_balance']); ?></td>
            </tr>
            <?php endforeach; else : ?>
            <tr><td colspan="6"><?php esc_html_e('No vendor data found for the selected criteria.', 'vendor-dashboard'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php vdb_render_report_pagination($data['total_items'], $report_args['items_per_page'], $paged); ?>
    <?php
}

function vdb_render_product_performance_report($report_args) {
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $report_args['paged'] = $paged;
    $report_args['items_per_page'] = 20;

    $data = vdb_get_product_performance_summary($report_args);
     ?>
    <h3><?php esc_html_e('Product Performance Summary', 'vendor-dashboard'); ?></h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Product', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Vendor', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Units Sold', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Gross Sales', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Platform Commission', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Vendor Net Earnings', 'vendor-dashboard'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($data['products'])) : foreach ($data['products'] as $product_data) : ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url(get_edit_post_link($product_data['product_id'])); ?>" target="_blank">
                        <?php echo esc_html($product_data['product_name']); ?> (ID: <?php echo esc_html($product_data['product_id']); ?>)
                    </a>
                </td>
                <td><?php echo esc_html($product_data['vendor_name']); ?></td>
                <td><?php echo esc_html($product_data['units_sold']); ?></td>
                <td><?php echo wc_price($product_data['total_gross_sales']); ?></td>
                <td><?php echo wc_price($product_data['total_platform_commission']); ?></td>
                <td><?php echo wc_price($product_data['total_net_earnings']); ?></td>
            </tr>
            <?php endforeach; else : ?>
            <tr><td colspan="6"><?php esc_html_e('No product data found for the selected criteria.', 'vendor-dashboard'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php vdb_render_report_pagination($data['total_items'], $report_args['items_per_page'], $paged); ?>
    <?php
}

function vdb_render_detailed_earnings_log_report($report_args) {
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $report_args['paged'] = $paged;
    $report_args['items_per_page'] = 25;

    $data = vdb_get_detailed_earnings_log($report_args);
    ?>
    <h3><?php esc_html_e('Detailed Earnings Log', 'vendor-dashboard'); ?></h3>
     <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Earning ID', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Order', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Product', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Vendor', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Qty', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Gross', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Comm. Rate', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Commission', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Net Earning', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Date', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Status', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Payout ID', 'vendor-dashboard'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($data['earnings'])) : foreach ($data['earnings'] as $earning) : ?>
            <tr>
                <td><?php echo esc_html($earning['earning_id']); ?></td>
                <td><a href="<?php echo esc_url(get_edit_post_link($earning['order_id'])); ?>" target="_blank">#<?php echo esc_html(get_post_field('post_name', $earning['order_id']) ?: $earning['order_id']); ?></a></td>
                <td><?php echo esc_html($earning['product_name']); ?> (ID: <?php echo esc_html($earning['product_id']); ?>)</td>
                <td><?php echo esc_html($earning['vendor_name']); ?></td>
                <td><?php echo esc_html($earning['quantity_sold']); ?></td>
                <td><?php echo wc_price($earning['gross_amount_vendor_items'], array('currency' => $earning['currency'])); ?></td>
                <td><?php echo esc_html(number_format_i18n($earning['commission_rate'] * 100, 2) . '%'); ?></td>
                <td><?php echo wc_price($earning['commission_amount'], array('currency' => $earning['currency'])); ?></td>
                <td><?php echo wc_price($earning['net_earning'], array('currency' => $earning['currency'])); ?></td>
                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($earning['order_date']))); ?></td>
                <td><?php echo esc_html(ucfirst($earning['status'])); ?></td>
                <td><?php echo esc_html($earning['payout_id'] ?: 'N/A'); ?></td>
            </tr>
            <?php endforeach; else : ?>
            <tr><td colspan="12"><?php esc_html_e('No earnings found for the selected criteria.', 'vendor-dashboard'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php vdb_render_report_pagination($data['total_items'], $report_args['items_per_page'], $paged); ?>
    <?php
}

/** Helper for pagination output */
function vdb_render_report_pagination($total_items, $per_page, $current_page) {
    if ($total_items > $per_page) {
        $total_pages = ceil($total_items / $per_page);
        $base_url = remove_query_arg( 'paged', esc_url_raw( add_query_arg( null, null ) ) ); // Maintain existing query vars

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( array(
            'base'      => $base_url . '%_%',
            'format'    => '&paged=%#%',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('«'),
            'next_text' => __('»'),
            'type'      => 'plain',
        ) );
        echo '</div></div>';
    }
}
?>