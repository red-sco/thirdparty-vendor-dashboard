<?php
/**
 * Vendor Dashboard Earnings - Core Processing Functions
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Get the currently configured commission rate.
 * Retrieves from options, falls back to constant if not set.
 *
 * @return float The commission rate (e.g., 0.10 for 10%).
 */
function vdb_get_current_commission_rate() {
    $commission_percentage = get_option( 'vdb_commission_rate_percentage', null );

    if ( $commission_percentage === null ) { 
        return defined('VDB_COMMISSION_RATE') ? floatval(VDB_COMMISSION_RATE) : 0.10;
    }
    return floatval( $commission_percentage ) / 100; 
}

/**
 * Calculate and log earnings when an order is processed or completed.
 * Earnings are made available immediately and logged per order item.
 *
 * @param int $order_id
 * @param string $old_status (Not directly used, but part of the hook signature)
 * @param string $new_status
 * @param WC_Order $order
 */
function vdb_handle_order_processed_for_earnings( $order_id, $old_status, $new_status, $order ) {
    if ( ! $order_id || ! $order ) {
        return;
    }

    $trigger_statuses = apply_filters('vdb_earnings_trigger_order_statuses', array( 'processing', 'completed' ));
    if ( ! in_array( $new_status, $trigger_statuses ) ) {
        return;
    }

    $commission_rate = vdb_get_current_commission_rate();
    $order_date      = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
    $currency        = $order->get_currency();

    foreach ( $order->get_items() as $item_id => $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }
        
        $product_id = $item->get_product_id();
        // $variation_id = $item->get_variation_id(); // Get variation ID if it's a variation
        // $actual_product_id_for_author = $variation_id ? $variation_id : $product_id; // Author is on parent product
        
        $product_obj = wc_get_product( $product_id ); // Get product object to get author

        if ( $product_obj ) {
            $vendor_id = get_post_field( 'post_author', $product_obj->get_id() ); // Author from parent product

            if ( user_can( $vendor_id, VENDOR_DASHBOARD_ROLE ) ) {
                $item_gross_total  = $item->get_total(); // This is line total after discounts
                $item_quantity     = $item->get_quantity();
                $commission_amount = $item_gross_total * $commission_rate;
                $net_earning       = $item_gross_total - $commission_amount;
                
                $earning_data = array(
                    'vendor_id'                 => $vendor_id,
                    'order_id'                  => $order_id,
                    'order_item_id'             => $item_id, // Store the WooCommerce order item ID
                    'product_id'                => $product_id, // Store the specific product ID (or variation ID if $item->get_product_id() returns that)
                    'quantity_sold'             => $item_quantity,
                    'gross_amount_vendor_items' => $item_gross_total, // Gross for this item
                    'commission_rate'           => $commission_rate,
                    'commission_amount'         => $commission_amount,
                    'net_earning'               => $net_earning,
                    'currency'                  => $currency,
                    'order_date'                => $order_date,
                    'status'                    => 'available', 
                );
                vdb_earnings_insert_earning_log( $earning_data );
            }
        }
    }
}
add_action( 'woocommerce_order_status_changed', 'vdb_handle_order_processed_for_earnings', 20, 4 );

?>