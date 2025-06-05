<?php
/**
 * Vendor Dashboard - Admin Earnings Management Page
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Register the admin submenu page for earnings management.
 */
function vdb_admin_register_earnings_page() {
    add_submenu_page(
        'vendor-admin-settings', 
        __( 'Vendor Payouts', 'vendor-dashboard' ), 
        __( 'Payouts', 'vendor-dashboard' ), 
        'manage_woocommerce', 
        'vdb-vendor-payouts', 
        'vdb_admin_render_earnings_page' 
    );
}
add_action( 'admin_menu', 'vdb_admin_register_earnings_page' );

/**
 * Handle payout form submission.
 */
function vdb_admin_handle_payout_submission() {
    if ( ! isset( $_POST['vdb_payout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field($_POST['vdb_payout_nonce']), 'vdb_process_payout' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) { 
        wp_die( esc_html__( 'You do not have permission to process payouts.', 'vendor-dashboard' ) );
    }

    $vendor_id     = isset( $_POST['vdb_payout_vendor_id'] ) ? absint( $_POST['vdb_payout_vendor_id'] ) : 0;
    $payout_amount = isset( $_POST['vdb_payout_amount'] ) ? wc_format_decimal( sanitize_text_field( $_POST['vdb_payout_amount'] ) ) : 0.00;
    $payout_method_used = isset( $_POST['vdb_payout_method_used'] ) ? sanitize_text_field( $_POST['vdb_payout_method_used'] ) : ''; // Changed name
    $payout_ref    = isset( $_POST['vdb_payout_ref'] ) ? sanitize_text_field( $_POST['vdb_payout_ref'] ) : '';
    $payout_notes  = isset( $_POST['vdb_payout_notes'] ) ? sanitize_textarea_field( $_POST['vdb_payout_notes'] ) : '';

    if ( ! $vendor_id || $payout_amount <= 0 || empty( $payout_method_used ) ) {
        add_settings_error( 'vdb_payouts', 'invalid_data', __( 'Invalid payout data provided. Please check vendor, amount, and method used.', 'vendor-dashboard' ), 'error' );
        return;
    }

    $summary_data = vdb_get_vendor_earnings_summary_data( $vendor_id );
    if ( $payout_amount > $summary_data['current_balance'] ) {
        add_settings_error( 'vdb_payouts', 'insufficient_balance', __( 'Payout amount exceeds vendor\'s available balance.', 'vendor-dashboard' ), 'error' );
        return;
    }

    $payout_data_arr = array(
        'vendor_id'     => $vendor_id,
        'amount_paid'   => $payout_amount,
        'payout_method' => $payout_method_used, // Use the method admin actually used
        'transaction_ref' => $payout_ref,
        'notes'         => $payout_notes,
        'currency'      => get_woocommerce_currency(), 
        'payout_date'   => current_time( 'mysql' ),
        'processed_by_admin_id' => get_current_user_id(),
    );

    $payout_id = vdb_earnings_insert_payout_log( $payout_data_arr );

    if ( $payout_id ) {
        vdb_mark_earnings_as_paid( $vendor_id, $payout_amount, $payout_id );
        add_settings_error( 'vdb_payouts', 'payout_success', __( 'Payout recorded successfully.', 'vendor-dashboard' ), 'updated' );

        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            $message = sprintf(__('A payout of %s%s has been processed for your account via %s.', 'vendor-dashboard'), get_woocommerce_currency_symbol(), $payout_amount, $payout_method_used);
            if ($payout_ref) {
                $message .= sprintf(__(' Reference: %s.', 'vendor-dashboard'), $payout_ref);
            }
            $earnings_url = vdb_get_dashboard_page_url() ? add_query_arg('section', 'earnings', vdb_get_dashboard_page_url()) : '';
            vdb_add_vendor_notification($vendor_id, $message, 'payout_processed', $earnings_url);
        }
        wp_safe_redirect( admin_url('admin.php?page=vdb-vendor-payouts&vd_payout_success=1&processed_vendor_id=' . $vendor_id) ); // Pass vendor ID back for selection
        exit;

    } else {
        add_settings_error( 'vdb_payouts', 'payout_failed', __( 'Failed to record payout.', 'vendor-dashboard' ), 'error' );
    }
}
add_action( 'admin_init', 'vdb_admin_handle_payout_submission' );


/**
 * Render the admin earnings management page.
 */
function vdb_admin_render_earnings_page() {
    $all_vendors_query_args = array(
        'role'    => VENDOR_DASHBOARD_ROLE,
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => array('ID', 'display_name') 
    );
    $all_vendors_users = get_users( $all_vendors_query_args ); 

    // If redirected after payout, keep the vendor selected
    $selected_vendor_id = isset($_GET['processed_vendor_id']) ? absint($_GET['processed_vendor_id']) : (isset($_GET['vendor_id']) ? absint($_GET['vendor_id']) : 0);
    
    $selected_vendor_balance = 0.00;
    $selected_vendor_payout_info_html = '';
    $vendor_preferred_method = '';

    if ($selected_vendor_id && function_exists('vdb_get_vendor_earnings_summary_data') && function_exists('vdb_get_formatted_vendor_payout_details')) {
        $summary = vdb_get_vendor_earnings_summary_data($selected_vendor_id);
        $selected_vendor_balance = $summary['current_balance'];

        $payout_details = vdb_get_formatted_vendor_payout_details($selected_vendor_id);
        $vendor_preferred_method = get_user_meta($selected_vendor_id, VDB_PAYOUT_METHOD_KEY, true);

        $selected_vendor_payout_info_html = '<h4>' . __('Vendor Payout Details', 'vendor-dashboard') . '</h4>';
        $selected_vendor_payout_info_html .= '<p><strong>' . __('Preferred Method:', 'vendor-dashboard') . '</strong> ' . esc_html($payout_details['method_label']) . '</p>';
        $selected_vendor_payout_info_html .= '<div class="vdb-admin-payout-details-box">' . wp_kses_post($payout_details['details_html']) . '</div>';
    }

    $history_paged = isset( $_GET['ph_paged'] ) ? absint( $_GET['ph_paged'] ) : 1;
    $filter_vendor_id_hist = isset( $_GET['filter_vendor_id'] ) ? absint( $_GET['filter_vendor_id'] ) : 0;
    $filter_date_from_hist = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
    $filter_date_to_hist   = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';

    $history_args = array(
        'items_per_page'   => 20,
        'paged'            => $history_paged,
        'filter_vendor_id' => $filter_vendor_id_hist,
        'filter_date_from' => $filter_date_from_hist,
        'filter_date_to'   => $filter_date_to_hist,
    );
    $payout_history_data = vdb_admin_get_all_payout_history( $history_args );
    ?>
    <div class="wrap vdb-admin-page">
        <h1><?php esc_html_e( 'Vendor Payouts Management', 'vendor-dashboard' ); ?></h1>
        <?php 
        if (isset($_GET['vd_payout_success'])) {
            echo '<div id="message" class="notice notice-success is-dismissible"><p>' . esc_html__('Payout recorded successfully.', 'vendor-dashboard') . '</p></div>';
        }
        settings_errors( 'vdb_payouts' ); 
        ?>

        <div class="vdb-admin-section">
            <h2><?php esc_html_e( 'Record New Payout', 'vendor-dashboard' ); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=vdb-vendor-payouts')); // Submit to self for processing via admin_init hook ?>">
                <?php wp_nonce_field( 'vdb_process_payout', 'vdb_payout_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="vdb_payout_vendor_id_select"><?php esc_html_e( 'Select Vendor', 'vendor-dashboard' ); ?></label></th>
                        <td>
                            <select name="vdb_payout_vendor_id_select" id="vdb_payout_vendor_id_select" required 
                                    onchange="if(this.value) { window.location.href = '<?php echo esc_url(admin_url('admin.php?page=vdb-vendor-payouts')); ?>&vendor_id=' + this.value; } else { window.location.href = '<?php echo esc_url(admin_url('admin.php?page=vdb-vendor-payouts')); ?>'; }">
                                <option value=""><?php esc_html_e( '-- Select Vendor --', 'vendor-dashboard' ); ?></option>
                                <?php
                                if ( ! empty( $all_vendors_users ) ) {
                                    foreach ( $all_vendors_users as $vendor_user_obj ) {
                                        $loop_vendor_summary = vdb_get_vendor_earnings_summary_data($vendor_user_obj->ID);
                                        $loop_vendor_balance = $loop_vendor_summary['current_balance'];
                                        $brand_name = vdb_get_vendor_brand_name($vendor_user_obj->ID) ?: $vendor_user_obj->display_name;
                                        ?>
                                        <option value="<?php echo esc_attr( $vendor_user_obj->ID ); ?>" <?php selected( $selected_vendor_id, $vendor_user_obj->ID ); ?>>
                                            <?php echo esc_html( $brand_name ); ?>
                                            (<?php esc_html_e('Available:', 'vendor-dashboard'); ?> <?php echo wc_price( $loop_vendor_balance ); ?>)
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                            <?php // Hidden field to actually submit the selected vendor ID with the payout form ?>
                            <?php if ($selected_vendor_id): ?>
                                <input type="hidden" name="vdb_payout_vendor_id" value="<?php echo esc_attr($selected_vendor_id); ?>" />
                            <?php endif; ?>

                             <?php if ($selected_vendor_id && !empty($selected_vendor_payout_info_html)): ?>
                                <div style="margin-top: 15px; padding: 10px; background-color: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px;">
                                    <?php echo $selected_vendor_payout_info_html; ?>
                                </div>
                            <?php elseif ($selected_vendor_id): ?>
                                 <div style="margin-top: 15px; padding: 10px; background-color: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px;">
                                    <p><?php esc_html_e('Payout details might not be fully set up by this vendor, or their preferred method is not specified.', 'vendor-dashboard');?></p>
                                 </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                     <?php if ($selected_vendor_id && $selected_vendor_balance > 0): ?>
                    <tr valign="top">
                        <th scope="row"><label for="vdb_payout_amount"><?php esc_html_e( 'Payout Amount', 'vendor-dashboard' ); ?></label></th>
                        <td>
                            <input type="text" name="vdb_payout_amount" id="vdb_payout_amount" class="wc_input_price regular-text" 
                                   value="<?php echo esc_attr(number_format(floatval($selected_vendor_balance), wc_get_price_decimals(), '.', '')); ?>" 
                                   data-max="<?php echo esc_attr(number_format(floatval($selected_vendor_balance), wc_get_price_decimals(), '.', '')); ?>"
                                   required />
                            <p class="description"><?php printf(esc_html__('Max available: %s', 'vendor-dashboard'), wc_price($selected_vendor_balance)); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="vdb_payout_method_used"><?php esc_html_e( 'Payout Method Used', 'vendor-dashboard' ); ?></label></th>
                        <td>
                            <select name="vdb_payout_method_used" id="vdb_payout_method_used" required>
                                <option value=""><?php esc_html_e( '-- Select Method --', 'vendor-dashboard' ); ?></option>
                                <option value="PayPal" <?php selected($vendor_preferred_method, 'paypal'); ?>><?php esc_html_e( 'PayPal', 'vendor-dashboard' ); ?></option>
                                <option value="Stripe Connect" <?php selected($vendor_preferred_method, 'stripe_connect'); ?>><?php esc_html_e( 'Stripe Connect', 'vendor-dashboard' ); ?></option>
                                <option value="Other"><?php esc_html_e( 'Other (Manual)', 'vendor-dashboard' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select the method you actually used to send the payment.', 'vendor-dashboard');?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="vdb_payout_ref"><?php esc_html_e( 'Transaction Reference (Optional)', 'vendor-dashboard' ); ?></label></th>
                        <td><input type="text" name="vdb_payout_ref" id="vdb_payout_ref" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="vdb_payout_notes"><?php esc_html_e( 'Notes (Optional)', 'vendor-dashboard' ); ?></label></th>
                        <td><textarea name="vdb_payout_notes" id="vdb_payout_notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                    <?php endif; ?>
                </table>
                 <?php if ($selected_vendor_id && $selected_vendor_balance > 0): ?>
                    <?php submit_button( __( 'Record Payout', 'vendor-dashboard' ) ); ?>
                <?php elseif ($selected_vendor_id && $selected_vendor_balance <= 0): ?>
                     <p><?php esc_html_e('This vendor has no balance available for payout.', 'vendor-dashboard'); ?></p>
                <?php else: ?>
                    <p><?php esc_html_e('Select a vendor to view their payout details or record a payout if they have an available balance.', 'vendor-dashboard'); ?></p>
                <?php endif; ?>
            </form>
        </div>

        <?php 
            $vendors_balances_list_data = array();
            if (!empty($all_vendors_users)) {
                foreach($all_vendors_users as $vendor_user_item_for_table) {
                    $summary_item_for_table = vdb_get_vendor_earnings_summary_data($vendor_user_item_for_table->ID);
                    $vendors_balances_list_data[] = array(
                        'vendor_id' => $vendor_user_item_for_table->ID,
                        'brand_name' => vdb_get_vendor_brand_name($vendor_user_item_for_table->ID) ?: $vendor_user_item_for_table->display_name,
                        'available_balance' => $summary_item_for_table['current_balance'],
                        'currency' => get_woocommerce_currency() 
                    );
                }
            }
        ?>
        <div class="vdb-admin-section">
            <h2><?php esc_html_e( 'All Vendor Balances Overview', 'vendor-dashboard' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Vendor', 'vendor-dashboard' ); ?></th>
                        <th><?php esc_html_e( 'Available Balance', 'vendor-dashboard' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'vendor-dashboard' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $vendors_balances_list_data ) ) : ?>
                        <?php foreach ( $vendors_balances_list_data as $vendor_item_in_table ) : ?>
                            <tr>
                                <td><?php echo esc_html( $vendor_item_in_table['brand_name'] ); ?> (ID: <?php echo esc_html($vendor_item_in_table['vendor_id']); ?>)</td>
                                <td><?php echo wc_price( $vendor_item_in_table['available_balance'], array('currency' => $vendor_item_in_table['currency']) ); ?></td>
                                <td>
                                    <?php if (floatval($vendor_item_in_table['available_balance']) > 0): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-vendor-payouts&vendor_id=' . $vendor_item_in_table['vendor_id'])); ?>" class="button button-secondary">
                                        <?php esc_html_e('Process Payout', 'vendor-dashboard'); ?>
                                    </a>
                                    <?php else: ?>
                                        <?php esc_html_e('No balance', 'vendor-dashboard'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3"><?php esc_html_e( 'No vendors found.', 'vendor-dashboard' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="vdb-admin-section">
            <h2><?php esc_html_e('Payout History', 'vendor-dashboard'); ?></h2>
            <form method="get" class="vdb-filters-form" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="vdb-vendor-payouts" />
                <select name="filter_vendor_id">
                    <option value="0"><?php esc_html_e('All Vendors', 'vendor-dashboard'); ?></option>
                    <?php
                    if ( ! empty( $all_vendors_users ) ) {
                        foreach ( $all_vendors_users as $vendor_user_obj_filter ) {
                             $brand_name_filter = vdb_get_vendor_brand_name($vendor_user_obj_filter->ID) ?: $vendor_user_obj_filter->display_name;
                            echo '<option value="' . esc_attr( $vendor_user_obj_filter->ID ) . '" ' . selected( $filter_vendor_id_hist, $vendor_user_obj_filter->ID, false ) . '>' . esc_html( $brand_name_filter ) . '</option>';
                        }
                    }
                    ?>
                </select>
                <input type="date" name="filter_date_from" value="<?php echo esc_attr($filter_date_from_hist); ?>" placeholder="<?php esc_attr_e('Date From', 'vendor-dashboard'); ?>" />
                <input type="date" name="filter_date_to" value="<?php echo esc_attr($filter_date_to_hist); ?>" placeholder="<?php esc_attr_e('Date To', 'vendor-dashboard'); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'vendor-dashboard'); ?>" />
                <?php if ($filter_vendor_id_hist || $filter_date_from_hist || $filter_date_to_hist): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vdb-vendor-payouts')); ?>" class="button button-secondary"><?php esc_html_e('Clear Filters', 'vendor-dashboard'); ?></a>
                <?php endif; ?>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Payout ID', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Vendor', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Amount', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Date', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Method', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Reference', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Notes', 'vendor-dashboard'); ?></th>
                        <th><?php esc_html_e('Processed By', 'vendor-dashboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $payout_history_data['payouts'] ) ) : ?>
                    <?php foreach ( $payout_history_data['payouts'] as $payout ) : ?>
                        <tr>
                            <td><?php echo esc_html( $payout['payout_id'] ); ?></td>
                            <td><?php echo esc_html( $payout['vendor_brand_name'] ?: $payout['vendor_display_name'] ); ?> (ID: <?php echo esc_html($payout['vendor_id']);?>)</td>
                            <td><?php echo wc_price( $payout['amount_paid'], array('currency' => $payout['currency']) ); ?></td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option('time_format'), strtotime( $payout['payout_date'] ) ) ); ?></td>
                            <td><?php echo esc_html( $payout['payout_method'] ); ?></td>
                            <td><?php echo esc_html( $payout['transaction_ref'] ?: 'N/A' ); ?></td>
                            <td><?php echo esc_html( $payout['notes'] ? wp_trim_words($payout['notes'], 10, '...') : 'N/A' ); ?></td>
                            <td><?php echo esc_html( $payout['admin_display_name'] ?: __('Unknown', 'vendor-dashboard') ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="8"><?php esc_html_e( 'No payout records found matching your criteria.', 'vendor-dashboard' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            if ( $payout_history_data['total_pages'] > 1 ) {
                $base_pagination_url = admin_url('admin.php?page=vdb-vendor-payouts');
                if ($filter_vendor_id_hist) $base_pagination_url = add_query_arg('filter_vendor_id', $filter_vendor_id_hist, $base_pagination_url);
                if ($filter_date_from_hist) $base_pagination_url = add_query_arg('filter_date_from', $filter_date_from_hist, $base_pagination_url);
                if ($filter_date_to_hist) $base_pagination_url = add_query_arg('filter_date_to', $filter_date_to_hist, $base_pagination_url);

                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links( array(
                    'base'      => $base_pagination_url . '%_%',
                    'format'    => '&ph_paged=%#%', 
                    'current'   => $history_paged,
                    'total'     => $payout_history_data['total_pages'],
                    'prev_text' => __('«'),
                    'next_text' => __('»'),
                    'type'      => 'plain',
                ) );
                echo '</div></div>';
            }
            ?>
        </div>
    </div>
    <style>
        .vdb-admin-payout-details-box p { margin: 0.5em 0; font-size: 0.9em;}
        .vdb-admin-payout-details-box p strong { font-weight: 600;}
        .vdb-filters-form select, .vdb-filters-form input[type="date"], .vdb-filters-form .button { vertical-align: middle; margin-right: 5px;}
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#vdb_payout_amount').on('change keyup', function(){
                var max = parseFloat($(this).data('max'));
                var current = parseFloat($(this).val());
                if (isNaN(current)) current = 0;
                if (current > max) {
                    $(this).val(max.toFixed(<?php echo wc_get_price_decimals(); ?>));
                }
            });
            $('input[name="filter_date_from"], input[name="filter_date_to"]').on('focus', function(){
                if (typeof $(this).datepicker === 'function') {
                    $(this).datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });
                }
            });
        });
    </script>
    <?php
}
?>