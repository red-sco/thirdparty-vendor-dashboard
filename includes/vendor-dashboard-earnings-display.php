<?php
/**
 * Vendor Dashboard - Earnings Section Display Functions
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Renders the "My Earnings" section for the vendor dashboard.
 */
function vdb_render_vendor_earnings_section( $vendor_id ) {
    $vendor_id = absint( $vendor_id );
    $summary_data = vdb_get_vendor_earnings_summary_data( $vendor_id );
    $current_page = isset( $_GET['tr_paged'] ) ? absint( $_GET['tr_paged'] ) : 1;
    $history = vdb_get_vendor_transaction_history( $vendor_id, 15, $current_page );
    $currency_symbol = get_woocommerce_currency_symbol();
    ?>
    <h3><?php esc_html_e( 'My Earnings', 'vendor-dashboard' ); ?></h3>

    <div class="vdb-payout-notice">
        <p>
            <span class="dashicons dashicons-info" style="color:#0073aa;"></span>
            <?php esc_html_e( 'Your earnings become available as soon as the order is completed or processing. Payouts for your available balance are processed by the site administrators weekly on Thursdays.', 'vendor-dashboard' ); ?>
        </p>
    </div>

    <div class="vdb-earnings-summary-boxes">
        <div class="vdb-summary-box">
            <span class="vdb-box-label"><?php esc_html_e( 'Available Balance', 'vendor-dashboard' ); ?></span>
            <span class="vdb-box-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['current_balance'], 2 ) ); ?></span>
            <small><?php esc_html_e( 'Funds ready for payout.', 'vendor-dashboard' ); ?></small>
        </div>
        <div class="vdb-summary-box">
            <span class="vdb-box-label"><?php esc_html_e( 'Total Sales Credited', 'vendor-dashboard' ); ?></span>
            <span class="vdb-box-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['total_credited'], 2 ) ); ?></span>
            <small><?php esc_html_e( 'Net earnings from your sales (after commission).', 'vendor-dashboard' ); ?></small>
        </div>
        <div class="vdb-summary-box">
            <span class="vdb-box-label"><?php esc_html_e( 'Total Paid Out', 'vendor-dashboard' ); ?></span>
            <span class="vdb-box-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['total_paid_out'], 2 ) ); ?></span>
             <small><?php esc_html_e( 'Total amount withdrawn.', 'vendor-dashboard' ); ?></small>
        </div>
    </div>

    <h4><?php esc_html_e( 'Transaction History', 'vendor-dashboard' ); ?></h4>
    <?php if ( ! empty( $history['transactions'] ) ) : ?>
        <table class="vdb-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'vendor-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'vendor-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'vendor-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'vendor-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'vendor-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $history['transactions'] as $transaction ) : ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( get_option('date_format'), strtotime( $transaction['date'] ) ) ); ?></td>
                        <td>
                            <?php if ( $transaction['type'] === 'earning' ) : ?>
                                <span class="vdb-transaction-type earning"><?php esc_html_e( 'Sale', 'vendor-dashboard' ); ?></span>
                            <?php elseif ( $transaction['type'] === 'payout' ) : ?>
                                 <span class="vdb-transaction-type payout"><?php esc_html_e( 'Payout', 'vendor-dashboard' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $transaction['type'] === 'earning' && $transaction['order_id'] ) : ?>
                                <?php 
                                    $order = wc_get_order($transaction['order_id']);
                                    if ($order) {
                                        printf( esc_html__( 'Order #%s', 'vendor-dashboard' ), esc_html( $order->get_order_number() ) ); 
                                    } else {
                                        printf( esc_html__( 'Order ID: %s', 'vendor-dashboard' ), esc_html( $transaction['order_id'] ) ); 
                                    }
                                ?>
                            <?php elseif ( $transaction['type'] === 'payout' ) : ?>
                                <?php echo esc_html( $transaction['payout_method'] ?: __('Withdrawal', 'vendor-dashboard') ); ?>
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo floatval($transaction['amount']) < 0 ? 'vdb-amount-debit' : 'vdb-amount-credit'; ?>">
                            <?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( abs( $transaction['amount'] ), 2 ) ); // Use abs for display consistency ?>
                        </td>
                        <td>
                            <?php
                            $status_text = '';
                            // 'pending_clearance' case is effectively removed for new earnings
                            switch ( $transaction['status'] ) {
                                case 'available': $status_text = __('Available', 'vendor-dashboard'); break;
                                case 'paid': $status_text = ($transaction['type'] === 'earning') ? __('Credited to Payout', 'vendor-dashboard') : __('Paid', 'vendor-dashboard'); break;
                                case 'cancelled': $status_text = __('Cancelled', 'vendor-dashboard'); break;
                                default: $status_text = ucfirst(str_replace('_', ' ', $transaction['status'])); break;
                            }
                            echo esc_html($status_text);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        if ( $history['total_pages'] > 1 ) {
            echo '<div class="vdb-pagination">';
            $dashboard_page_url = vdb_get_dashboard_page_url();
            $base_pagination_url = $dashboard_page_url ? add_query_arg( 'section', 'earnings', $dashboard_page_url ) : '';
            
            echo paginate_links( array(
                'base'      => $base_pagination_url . '%_%',
                'format'    => (strpos($base_pagination_url, '?') === false ? '?' : '&') . 'tr_paged=%#%', // Ensure correct format
                'current'   => $current_page,
                'total'     => $history['total_pages'],
                'prev_text' => __('« Previous'),
                'next_text' => __('Next »'),
            ) );
            echo '</div>';
        }
        ?>
    <?php else : ?>
        <p><?php esc_html_e( 'No transactions found yet.', 'vendor-dashboard' ); ?></p>
    <?php endif; ?>
    <style>
        .vdb-payout-notice { background-color: #eef7ff; border: 1px solid #a8cbee; color: #31708f; padding: 10px 15px; margin-bottom: 25px; border-radius: 4px; }
        .vdb-payout-notice p { margin: 0; display: flex; align-items: center; }
        .vdb-payout-notice .dashicons { margin-right: 8px; font-size: 18px; }
        .vdb-earnings-summary-boxes { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .vdb-summary-box { flex: 1; background: #f9f9f9; border: 1px solid #e0e0e0; padding: 20px; border-radius: 4px; text-align: center; min-width: 200px; }
        .vdb-box-label { display: block; font-size: 0.9em; color: #555; margin-bottom: 5px; font-weight: 500; }
        .vdb-box-value { display: block; font-size: 1.8em; color: #2c3338; font-weight: 600; margin-bottom: 8px; }
        .vdb-summary-box small { font-size: 0.8em; color: #777; }
        .vdb-transaction-type.earning { color: green; font-weight:500; }
        .vdb-transaction-type.payout { color: red; font-weight:500;}
        .vdb-amount-credit { color: green; }
        .vdb-amount-debit { color: red; }
    </style>
    <?php
}

/**
 * Renders the Earnings Summary widget for the vendor overview page.
 */
function vdb_render_earnings_summary_widget_content( $vendor_id ) {
    $summary_data = vdb_get_vendor_earnings_summary_data( $vendor_id );
    $currency_symbol = get_woocommerce_currency_symbol();
    $earnings_section_url = vdb_get_dashboard_page_url() ? add_query_arg( 'section', 'earnings', vdb_get_dashboard_page_url() ) : '#';
    ?>
    <div class="vdb-widget-grid">
        <div>
            <span class="vdb-kpi-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['current_balance'], 2 ) ); ?></span>
            <span class="vdb-kpi-label"><?php esc_html_e('Available Balance', 'vendor-dashboard'); ?></span>
        </div>
        <div>
            <span class="vdb-kpi-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['total_credited'], 2 ) ); ?></span>
            <span class="vdb-kpi-label"><?php esc_html_e('Total Credited', 'vendor-dashboard'); ?></span>
        </div>
         <div>
            <span class="vdb-kpi-value"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format_i18n( $summary_data['total_paid_out'], 2 ) ); ?></span>
            <span class="vdb-kpi-label"><?php esc_html_e('Total Paid Out', 'vendor-dashboard'); ?></span>
        </div>
    </div>
    <p style="text-align: right; margin-top: 10px;">
        <a href="<?php echo esc_url($earnings_section_url); ?>" class="vdb-widget-link"><?php esc_html_e('View Full Earnings Report', 'vendor-dashboard'); ?> →</a>
    </p>
    <?php
}
?>