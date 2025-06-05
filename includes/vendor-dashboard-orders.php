<?php
/**
 * Functions for handling the Orders section of the Vendor Dashboard.
 * Fetches and displays orders containing the specific vendor's products.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Get orders containing products for a specific vendor.
 * Loops through recent orders and checks item authors.
 *
 * @param int $vendor_id The ID of the vendor.
 * @param int $limit     How many orders to attempt to fetch and check.
 * @param array $statuses Order statuses to include (default: processing, completed, on-hold).
 * @return array Array of WC_Order objects relevant to the vendor.
 */
function vdb_get_vendor_orders( $vendor_id, $limit = 50, $statuses = array('wc-processing', 'wc-completed', 'wc-on-hold') ) {
    if ( ! $vendor_id || ! function_exists('wc_get_orders') ) {
        return array();
    }

    $vendor_orders = array();
    $order_ids_checked = array(); // Avoid processing the same order multiple times if fetched by status blocks

    $query_args = array(
        'limit'   => $limit, // Limit the initial query for performance
        'orderby' => 'date',
        'order'   => 'DESC',
        'status'  => $statuses, // Query by specified statuses
        'return'  => 'ids', // Get only IDs initially
    );
    $order_ids = wc_get_orders( $query_args );

    if ( empty( $order_ids ) ) {
        return $vendor_orders; // No orders found matching criteria
    }

    // Loop through order IDs and check items
    foreach ( $order_ids as $order_id ) {
        // Ensure we have an integer ID
        $order_id = absint($order_id);
        if (isset($order_ids_checked[$order_id])) {
            continue; // Skip if already processed
        }
        $order_ids_checked[$order_id] = true;

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            error_log("VDB: Failed to get order object for ID: " . $order_id);
            continue;
        }

        $vendor_has_item_in_order = false;
        foreach ( $order->get_items() as $item_id => $item ) {
            // Check if it's a product item
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }
            $product_id = $item->get_product_id();

            // Check if product exists and belongs to the vendor
            $product_post = get_post( $product_id );
            if ( $product_post && $product_post->post_author == $vendor_id ) {
                $vendor_has_item_in_order = true;
                break; // Found one item, no need to check further for this order
            }
        }

        if ( $vendor_has_item_in_order ) {
            $vendor_orders[] = $order; // Add the WC_Order object to our results
        }
    } // End foreach $order_ids

    return $vendor_orders;
}


/**
 * Render the vendor's order list table.
 * Displays orders fetched by vdb_get_vendor_orders and allows adding shipping info.
 *
 * @param int $vendor_id The ID of the vendor.
 */
function vdb_render_order_list( $vendor_id ) {
    // Fetch orders specific to this vendor
    $vendor_orders = vdb_get_vendor_orders( $vendor_id );

    $vdb_shipping_providers = array(
        '' => __('-- Select Provider --', 'vendor-dashboard'),
        'royalmail' => __('Royal Mail', 'vendor-dashboard'),
        'fedex' => __('FedEx', 'vendor-dashboard'),
        'usps' => __('USPS', 'vendor-dashboard'),
        'dhl' => __('DHL Express', 'vendor-dashboard'),
        'parcelforce' => __('Parcel Force', 'vendor-dashboard'),
        'ups' => __('UPS', 'vendor-dashboard'),
        'canadapost' => __('Canada Post', 'vendor-dashboard'),
        'auspost' => __('Australia Post', 'vendor-dashboard'),
        'chinapost' => __('China Post', 'vendor-dashboard'),
        'epacket' => __('ePacket', 'vendor-dashboard'),
        'delhievry' => __('Delhievry', 'vendor-dashboard'), // As per image
        'other' => __('Other', 'vendor-dashboard')
    );

    ?>
    <h4><?php esc_html_e('Your Orders', 'vendor-dashboard'); ?></h4>
    <p><?php esc_html_e('Showing orders that contain one or more of your products.', 'vendor-dashboard'); ?></p>

    <table class="vdb-table widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Order', 'vendor-dashboard' ); ?></th>
                <th><?php esc_html_e( 'Date', 'vendor-dashboard' ); ?></th>
                <th><?php esc_html_e( 'Status', 'vendor-dashboard' ); ?></th>
                <th><?php esc_html_e( 'Customer', 'vendor-dashboard' ); ?></th>
                <th><?php esc_html_e( 'Your Items & Shipping', 'vendor-dashboard' ); ?></th>
                <th><?php esc_html_e( 'Order Total', 'vendor-dashboard' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $vendor_orders ) ) : ?>
                <?php foreach ( $vendor_orders as $order ) : ?>
                    <?php
                        $order_id = $order->get_id();
                        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        if (trim($customer_name) === '') {
                            $customer_name = $order->get_billing_company() ?: __('Guest', 'vendor-dashboard');
                        }
                    ?>
                    <tr>
                        <td>
                            <?php
                            $admin_order_url = get_edit_post_link( $order_id );
                            if ( $admin_order_url ) {
                                printf( '<a href="%s" target="_blank" title="%s">#%s</a>',
                                    esc_url( $admin_order_url ),
                                    esc_attr__('View Order Details (Admin)', 'vendor-dashboard'),
                                    esc_html( $order->get_order_number() )
                                );
                            } else {
                                echo '#' . esc_html( $order->get_order_number() );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                        <td>
                            <mark class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                <span><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                            </mark>
                        </td>
                        <td><?php echo esc_html( $customer_name ); ?></td>
                        <td> <?php // Items Column ?>
                            <ul style="margin: 0; padding: 0; list-style: none;">
                                <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                                    <?php
                                        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;
                                        $product_id = $item->get_product_id();
                                        $product_post = get_post( $product_id );

                                        if ( $product_post && $product_post->post_author == $vendor_id ) :
                                            $saved_provider_meta = wc_get_order_item_meta( $item_id, '_tracking_provider', true );
                                            $tracking_number   = wc_get_order_item_meta( $item_id, '_tracking_number', true );
                                            $date_shipped_ts   = wc_get_order_item_meta( $item_id, '_date_shipped', true );
                                            
                                            // Determine provider key and custom value for the form
                                            $current_provider_key_for_select = '';
                                            $custom_provider_value_for_input = '';

                                            if (!empty($saved_provider_meta)) {
                                                $normalized_saved_provider = strtolower(trim($saved_provider_meta));
                                                $found_in_list = false;
                                                foreach ($vdb_shipping_providers as $key => $name) {
                                                    if (strtolower($key) === $normalized_saved_provider || strtolower(trim($name)) === $normalized_saved_provider) {
                                                        $current_provider_key_for_select = $key;
                                                        $found_in_list = true;
                                                        break;
                                                    }
                                                }
                                                if (!$found_in_list && $normalized_saved_provider !== 'other') {
                                                    $current_provider_key_for_select = 'other';
                                                    $custom_provider_value_for_input = $saved_provider_meta;
                                                } elseif ($current_provider_key_for_select === 'other' && $found_in_list) {
                                                     // If 'other' itself was saved as the key, custom input should be shown for entering the actual name
                                                     // This case is less likely if we save the custom name directly
                                                } else if ($normalized_saved_provider === 'other' && empty($custom_provider_value_for_input)){
                                                    // If 'other' was selected but no custom name was provided and saved (e.g. an old save).
                                                    // We should still select 'other' to allow editing.
                                                    $current_provider_key_for_select = 'other';
                                                }
                                            }
                                            $tracking_link = vdb_get_tracking_link($current_provider_key_for_select ?: $custom_provider_value_for_input, $tracking_number);


                                    ?>
                                    <li class="vdb-order-item" data-order-id="<?php echo esc_attr($order_id); ?>" data-item-id="<?php echo esc_attr($item_id); ?>" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dotted #eee; position: relative;">
                                        <strong class="vdb-item-name"><?php echo esc_html( $item->get_name() ); ?></strong>
                                        <span class="vdb-item-qty">× <?php echo esc_html( $item->get_quantity() ); ?></span>
                                        <span class="vdb-item-total" style="float: right; font-weight: bold;"><?php echo wp_kses_post( wc_price( $item->get_total(), array('currency' => $order->get_currency()) ) ); ?></span>

                                        <div class="vdb-shipping-details" style="margin-top: 8px; font-size: 0.9em; clear: both;">
                                             <?php if ($order->has_status(array('processing', 'on-hold'))): ?>
                                                <?php $nonce = wp_create_nonce( 'vdb_save_shipping_' . $item_id ); ?>
                                                <form class="vdb-shipping-form" method="post" onsubmit="vdbHandleShippingSave(this); return false;" style="display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; margin-top: 5px; padding: 8px; background-color: #f9f9f9; border: 1px solid #eee; border-radius: 3px;">
                                                    <input type="hidden" name="action" value="vdb_save_shipping_data">
                                                    <input type="hidden" name="vdb_order_id" value="<?php echo esc_attr($order_id); ?>">
                                                    <input type="hidden" name="vdb_item_id" value="<?php echo esc_attr($item_id); ?>">
                                                    <input type="hidden" name="vdb_shipping_nonce" value="<?php echo esc_attr($nonce); ?>">

                                                    <div style="flex-basis: 180px; flex-grow: 1;">
                                                        <label for="vdb_tracking_provider_<?php echo esc_attr($item_id); ?>" style="display: block; font-size: 0.9em; margin-bottom: 2px;"><?php esc_html_e('Provider', 'vendor-dashboard'); ?></label>
                                                        <select name="vdb_tracking_provider" id="vdb_tracking_provider_<?php echo esc_attr($item_id); ?>" class="vdb-shipping-provider-select" style="width: 100%; padding: 4px;" required>
                                                            <?php foreach ($vdb_shipping_providers as $key => $name): ?>
                                                                <option value="<?php echo esc_attr($key); ?>" <?php selected( $current_provider_key_for_select, $key ); ?>>
                                                                    <?php echo esc_html($name); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="text" name="vdb_custom_provider_name" value="<?php echo esc_attr($custom_provider_value_for_input); ?>" placeholder="<?php esc_attr_e('Enter Provider Name', 'vendor-dashboard'); ?>" class="vdb-custom-provider-name-input" style="width: 100%; margin-top: 3px; padding: 4px; <?php echo ($current_provider_key_for_select === 'other') ? '' : 'display:none;'; ?>" <?php echo ($current_provider_key_for_select === 'other') ? 'required' : ''; ?>>
                                                    </div>

                                                    <div style="flex-basis: 180px; flex-grow: 1;">
                                                        <label for="vdb_tracking_number_<?php echo esc_attr($item_id); ?>" style="display: block; font-size: 0.9em; margin-bottom: 2px;"><?php esc_html_e('Tracking #', 'vendor-dashboard'); ?></label>
                                                        <input type="text" name="vdb_tracking_number" id="vdb_tracking_number_<?php echo esc_attr($item_id); ?>" value="<?php echo esc_attr( $tracking_number ); ?>" placeholder="<?php esc_attr_e('Tracking Number', 'vendor-dashboard'); ?>" style="width: 100%; padding: 4px;" required>
                                                    </div>

                                                    <div style="flex-basis: 130px; flex-grow: 0;">
                                                        <label for="vdb_date_shipped_<?php echo esc_attr($item_id); ?>" style="display: block; font-size: 0.9em; margin-bottom: 2px;"><?php esc_html_e('Date Shipped', 'vendor-dashboard'); ?></label>
                                                        <input type="date" name="vdb_date_shipped" id="vdb_date_shipped_<?php echo esc_attr($item_id); ?>" value="<?php echo esc_attr( $date_shipped_ts ? date('Y-m-d', $date_shipped_ts) : date('Y-m-d') ); ?>" placeholder="<?php esc_attr_e('YYYY-MM-DD', 'vendor-dashboard'); ?>" style="width: 100%; padding: 4px;">
                                                    </div>

                                                    <div style="align-self: flex-end;">
                                                        <button type="submit" class="button button-secondary button-small vdb-save-shipping" style="padding: 4px 8px !important; line-height: normal !important; height: auto !important;"><?php esc_html_e( 'Save', 'vendor-dashboard' ); ?></button>
                                                    </div>
                                                    <span class="vdb-shipping-notice" style="display: none; margin-left: 5px; font-style: italic; font-size: 0.9em; flex-basis: 100%; text-align: right;"></span>
                                                </form>
                                             <?php elseif (!empty($tracking_number)): 
                                                $display_provider_name = '';
                                                if ($current_provider_key_for_select === 'other' && !empty($custom_provider_value_for_input)) {
                                                    $display_provider_name = $custom_provider_value_for_input;
                                                } elseif (isset($vdb_shipping_providers[$current_provider_key_for_select])) {
                                                    $display_provider_name = $vdb_shipping_providers[$current_provider_key_for_select];
                                                } else {
                                                    $display_provider_name = $saved_provider_meta ?: __('N/A', 'vendor-dashboard');
                                                }
                                             ?>
                                                 <div style="font-size: 0.9em; color: #555; margin-top: 5px; padding: 8px; background-color: #f0f5f0; border: 1px solid #e0e5e0; border-radius: 3px;">
                                                    <strong><?php esc_html_e('Provider:', 'vendor-dashboard'); ?></strong> <?php echo esc_html($display_provider_name); ?><br>
                                                    <strong><?php esc_html_e('Tracking:', 'vendor-dashboard'); ?></strong>
                                                    <?php if ($tracking_link): ?>
                                                        <a href="<?php echo esc_url($tracking_link); ?>" target="_blank"><?php echo esc_html($tracking_number); ?></a>
                                                    <?php else: echo esc_html($tracking_number); endif; ?>
                                                    <?php if ($date_shipped_ts): ?>
                                                        <br><strong><?php esc_html_e('Shipped:', 'vendor-dashboard'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), $date_shipped_ts)); ?>
                                                    <?php endif; ?>
                                                 </div>
                                            <?php else: ?>
                                                 <p style="margin: 5px 0 0 0; color: #777; font-style: italic; font-size: 0.9em;"><?php esc_html_e('Awaiting fulfillment.', 'vendor-dashboard'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr> <td colspan="6"><?php esc_html_e( 'No orders containing your products were found.', 'vendor-dashboard' ); ?></td> </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * AJAX handler to save shipping tracking data for an order item.
 * Uses wc_update_order_item_meta.
 * Also attempts to mark the order as 'completed' if all shippable items are tracked
 * and sends a notification email to the customer.
 */
function vdb_ajax_save_shipping_data() {
    ob_start(); 

    try {
        $nonce = isset($_POST['vdb_shipping_nonce']) ? sanitize_text_field($_POST['vdb_shipping_nonce']) : '';
        $item_id = isset($_POST['vdb_item_id']) ? absint($_POST['vdb_item_id']) : 0;
        if (!$item_id || !wp_verify_nonce($nonce, 'vdb_save_shipping_' . $item_id)) {
            throw new Exception(__('Security check failed.', 'vendor-dashboard'), 403);
        }

        if (!is_user_logged_in()) {
            throw new Exception(__('Permission denied. Please log in.', 'vendor-dashboard'), 403);
        }
        $current_user = wp_get_current_user();
        if (!in_array(VENDOR_DASHBOARD_ROLE, (array) $current_user->roles)) {
            throw new Exception(__('Permission denied. Not a vendor.', 'vendor-dashboard'), 403);
        }
        $vendor_id = $current_user->ID;

        $order_id = isset($_POST['vdb_order_id']) ? absint($_POST['vdb_order_id']) : 0;
        if (!$order_id) {
            throw new Exception(__('Invalid Order ID.', 'vendor-dashboard'), 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
             throw new Exception(__('Order not found.', 'vendor-dashboard'), 404);
        }

        $item = $order->get_item($item_id);
        if (!$item || !is_a($item, 'WC_Order_Item_Product')) {
             throw new Exception(__('Order item not found or not a product.', 'vendor-dashboard'), 404);
        }

        $product_id = $item->get_product_id();
        $product_post = get_post($product_id);
        if (!$product_post || $product_post->post_author != $vendor_id) {
            throw new Exception(__('Permission denied. You do not own this product.', 'vendor-dashboard'), 403);
        }

        $provider_key_input = isset($_POST['vdb_tracking_provider']) ? sanitize_text_field(wp_unslash($_POST['vdb_tracking_provider'])) : '';
        $custom_provider_name = isset($_POST['vdb_custom_provider_name']) ? sanitize_text_field(wp_unslash($_POST['vdb_custom_provider_name'])) : '';
        $tracking_number = isset($_POST['vdb_tracking_number']) ? sanitize_text_field(wp_unslash($_POST['vdb_tracking_number'])) : '';
        $date_shipped_str = isset($_POST['vdb_date_shipped']) ? sanitize_text_field(wp_unslash($_POST['vdb_date_shipped'])) : '';

        $vdb_shipping_providers_list = array( // For display name lookup
            'royalmail' => __('Royal Mail', 'vendor-dashboard'), 'fedex' => __('FedEx', 'vendor-dashboard'), 'usps' => __('USPS', 'vendor-dashboard'), 'dhl' => __('DHL Express', 'vendor-dashboard'), 'parcelforce' => __('Parcel Force', 'vendor-dashboard'), 'ups' => __('UPS', 'vendor-dashboard'), 'canadapost' => __('Canada Post', 'vendor-dashboard'), 'auspost' => __('Australia Post', 'vendor-dashboard'), 'chinapost' => __('China Post', 'vendor-dashboard'), 'epacket' => __('ePacket', 'vendor-dashboard'), 'delhievry' => __('Delhievry', 'vendor-dashboard')
        );

        $provider_key_to_save = $provider_key_input;
        $provider_display_name_for_email_and_meta = '';

        if ($provider_key_input === 'other') {
            if (empty($custom_provider_name)) {
                throw new Exception(__('Custom Provider Name is required when "Other" is selected.', 'vendor-dashboard'), 400);
            }
            $provider_display_name_for_email_and_meta = $custom_provider_name;
            // For 'other', we save the custom name as the meta value, not the key 'other'
            // This makes display easier later. The key 'other' is just for form logic.
            // However, vdb_get_tracking_link needs to know it was custom.
            // So we can still save the key '_tracking_provider_key' = 'other' if needed,
            // and '_tracking_provider' = $custom_provider_name.
            // For simplicity, let's save the custom name directly to _tracking_provider.
            // If the actual key "other" is needed for link generation, we'll pass $provider_key_input to vdb_get_tracking_link.
            $provider_key_to_save = $custom_provider_name; // This will be saved in _tracking_provider
        } elseif (isset($vdb_shipping_providers_list[$provider_key_input])) {
            $provider_display_name_for_email_and_meta = $vdb_shipping_providers_list[$provider_key_input];
            $provider_key_to_save = $provider_key_input; // Save the key for _tracking_provider
        } else {
             throw new Exception(__('Invalid Shipping Provider selected.', 'vendor-dashboard'), 400);
        }


        if (empty($provider_key_to_save) && empty($provider_display_name_for_email_and_meta)) { // Should be caught by above
            throw new Exception(__('Shipping Provider is required.', 'vendor-dashboard'), 400);
        }
        if (empty($tracking_number)) {
            throw new Exception(__('Tracking Number is required.', 'vendor-dashboard'), 400);
        }

        $date_shipped_ts = !empty($date_shipped_str) ? strtotime($date_shipped_str . ' 00:00:00') : '';
        if ($date_shipped_ts === false && !empty($date_shipped_str)) {
             throw new Exception(__('Invalid Date Shipped format.', 'vendor-dashboard'), 400);
        }
        
        // Save the chosen key (e.g. 'royalmail') or the custom name if 'other' was selected.
        // This is what vdb_get_tracking_link will use.
        wc_update_order_item_meta($item_id, '_tracking_provider', $provider_key_to_save);
        // For display purposes, if you want to store the friendly name from the dropdown if a key was selected:
        // wc_update_order_item_meta($item_id, '_tracking_provider_label', $provider_display_name_for_email_and_meta);

        wc_update_order_item_meta($item_id, '_tracking_number', $tracking_number);
        if (!empty($date_shipped_ts)) {
            wc_update_order_item_meta($item_id, '_date_shipped', $date_shipped_ts);
        } else {
             wc_delete_order_item_meta($item_id, '_date_shipped');
        }

        if (function_exists('ast_save_tracking_item')) { // Compatibility with AST
            do_action('woocommerce_ast_save_tracking_item', $order_id, $item_id, array(
                'tracking_provider' => $provider_key_to_save, // AST might expect a key or a label.
                'tracking_number'   => $tracking_number,
                'date_shipped'      => $date_shipped_ts,
            ));
        }
        
        $order->add_order_note(sprintf(
            __('Shipping info updated for item "%s" by vendor %s: Provider: %s, Tracking #: %s.', 'vendor-dashboard'),
            $item->get_name(),
            $current_user->display_name,
            esc_html($provider_display_name_for_email_and_meta), // Use display name for note
            $tracking_number
        ));

        // --- Check and Update Order Status to Completed ---
        $order = wc_get_order($order_id); 
        $order_completed_this_action = false;

        if ($order && $order->has_status('processing')) {
            $all_shippable_items_tracked = true;
            foreach ($order->get_items() as $order_item_id_check => $order_item_check) {
                if (!$order_item_check->is_type('line_item')) continue;
                $product_check = $order_item_check->get_product();
                if ($product_check && $product_check->needs_shipping()) {
                    $item_tracking_number_check = wc_get_order_item_meta($order_item_id_check, '_tracking_number', true);
                    if (empty($item_tracking_number_check)) {
                        $all_shippable_items_tracked = false;
                        break; 
                    }
                }
            }

            if ($all_shippable_items_tracked) {
                $order->update_status('completed', __('All shippable items tracked; order completed by vendor shipping update.', 'vendor-dashboard'));
                $order_completed_this_action = true;
            }
        }
        
        // --- Send Email if Order was Completed by this action ---
        if ($order_completed_this_action) {
            $customer_email = $order->get_billing_email();
            if ($customer_email) {
                $brand_name = vdb_get_vendor_brand_name($vendor_id) ?: get_bloginfo('name');
                $brand_logo_id = get_user_meta($vendor_id, 'vdb_brand_logo_id', true);
                $brand_logo_url = $brand_logo_id ? wp_get_attachment_image_url($brand_logo_id, 'medium') : '';
                
                // The $provider_key_input is the actual key ('other', 'royalmail', etc.)
                // The $provider_display_name_for_email_and_meta is the friendly name or custom name.
                $tracking_url = vdb_get_tracking_link($provider_key_input, $tracking_number);


                $email_subject = sprintf(__('[%s] Your Order #%s Has Shipped!', 'vendor-dashboard'), $brand_name, $order->get_order_number());
                
                $email_body = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
                if ($brand_logo_url) {
                    $email_body .= '<p style="text-align:center;"><img src="' . esc_url($brand_logo_url) . '" alt="' . esc_attr($brand_name) . ' Logo" style="max-width:180px; max-height:100px; height:auto; margin-bottom:15px;" /></p>';
                }
                $email_body .= '<p style="text-align:center;font-size:1.2em;font-weight:bold;">' . esc_html($brand_name) . '</p>';
                $email_body .= '<p>' . sprintf(__('Dear %s,', 'vendor-dashboard'), $order->get_billing_first_name()) . '</p>';
                $email_body .= '<p>' . sprintf(__('Great news! Your order #%s containing items from %s has been shipped.', 'vendor-dashboard'), $order->get_order_number(), esc_html($brand_name)) . '</p>';
                $email_body .= '<p><strong>' . __('Tracking Details for your item(s):', 'vendor-dashboard') . '</strong></p>';
                $email_body .= '<ul>';
                $email_body .= '<li><strong>' . __('Provider:', 'vendor-dashboard') . '</strong> ' . esc_html($provider_display_name_for_email_and_meta) . '</li>';
                $email_body .= '<li><strong>' . __('Tracking Number:', 'vendor-dashboard') . '</strong> ' . esc_html($tracking_number) . '</li>';
                if ($tracking_url) {
                    $email_body .= '<li><strong>' . __('Track your order:', 'vendor-dashboard') . '</strong> <a href="' . esc_url($tracking_url) . '">' . esc_html($tracking_url) . '</a></li>';
                }
                if ($date_shipped_ts) {
                     $email_body .= '<li><strong>' . __('Date Shipped:', 'vendor-dashboard') . '</strong> ' . esc_html(date_i18n(get_option('date_format'), $date_shipped_ts)) . '</li>';
                }
                $email_body .= '</ul>';
                $email_body .= '<p>' . __('Thank you for your order!', 'vendor-dashboard') . '</p>';
                $email_body .= '</div>';

                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                wp_mail($customer_email, $email_subject, $email_body, $headers);
            }
        }
        // --- END Send Email ---

        ob_end_clean();
        wp_send_json_success(array(
            'message' => __('Shipping information saved successfully.', 'vendor-dashboard')
        ));

    } catch (Exception $e) {
        ob_end_clean(); 
        $error_code = is_numeric($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        error_log("VDB Save Shipping Error: " . $e->getMessage());
        wp_send_json_error(
            array('message' => 'Error: ' . $e->getMessage()),
            $error_code
        );
    }
}

?>