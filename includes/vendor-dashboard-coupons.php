<?php
/**
 * Functions for handling the Coupons section of the Vendor Dashboard.
 */
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Get coupons created by a specific vendor.
 *
 * @param int $vendor_id The ID of the vendor.
 * @return WP_Query
 */
function vdb_get_vendor_coupons_query( $vendor_id ) {
    $args = array(
        'post_type'      => 'shop_coupon',
        'posts_per_page' => 20, // Add pagination later if needed
        'post_status'    => 'publish', // Or any relevant statuses
        'author'         => $vendor_id, // Check if coupon was created by vendor
        // Meta query to ensure it's explicitly marked as a vendor coupon
        // This is a stronger check than just post_author if admins can also create coupons for vendors.
        'meta_query'     => array(
            array(
                'key'   => '_vdb_vendor_id',
                'value' => $vendor_id,
                'compare' => '=',
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'paged'          => get_query_var('paged') ? get_query_var('paged') : 1,
    );
    return new WP_Query( $args );
}

/**
 * Render the vendor's coupon list.
 *
 * @param int $vendor_id The ID of the vendor.
 */
function vdb_render_coupon_list( $vendor_id ) {
    $coupons_query = vdb_get_vendor_coupons_query( $vendor_id );
    ?>
    <h4><?php esc_html_e( 'Your Coupons', 'vendor-dashboard' ); ?></h4>
    <button class="button vdb-add-new-coupon"><?php esc_html_e( 'Add New Coupon', 'vendor-dashboard' ); ?></button>

    <table class="vdb-table widefat striped" style="margin-top:15px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Code', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Type', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Amount', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Description', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Usage / Limit', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Expiry Date', 'vendor-dashboard'); ?></th>
                <th><?php esc_html_e('Actions', 'vendor-dashboard'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $coupons_query->have_posts() ) : ?>
                <?php while ( $coupons_query->have_posts() ) : $coupons_query->the_post(); ?>
                    <?php
                    $coupon_id = get_the_ID();
                    $coupon = new WC_Coupon( $coupon_id );
                    $usage_count = $coupon->get_usage_count();
                    $usage_limit = $coupon->get_usage_limit();
                    $usage_display = $usage_count . ' / ' . ($usage_limit > 0 ? $usage_limit : '∞');
                    $expiry_date = $coupon->get_date_expires();
                    $coupon_amount = $coupon->get_amount();
                    $discount_type = $coupon->get_discount_type();
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $coupon->get_code() ); ?></strong></td>
                        <td><?php echo esc_html( wc_get_coupon_type( $discount_type ) ); ?></td>
                        <td>
                            <?php
                            if ( $coupon_amount ) {
                                if ( 'percent' === $discount_type ) {
                                    echo esc_html( $coupon_amount ) . '%';
                                } else {
                                    echo wp_kses_post( wc_price( $coupon_amount ) );
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $coupon->get_description() ?: '—' ); ?></td>
                        <td><?php echo wp_kses_post( $usage_display ); ?></td>
                        <td><?php echo esc_html( $expiry_date ? $expiry_date->date_i18n('Y-m-d') : '—' ); ?></td>
                        <td>
                            <button class="button vdb-edit-coupon" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
                                <?php esc_html_e( 'Edit', 'vendor-dashboard' ); ?>
                            </button>
                            <?php
                            $delete_nonce = wp_create_nonce('vdb_delete_coupon_' . $coupon_id); ?>
                            <button class="button button-link-delete vdb-delete-coupon" data-coupon-id="<?php echo esc_attr($coupon_id); ?>" data-nonce="<?php echo esc_attr($delete_nonce); ?>" style="margin-left: 5px; color: #d63638;">
                                <?php esc_html_e('Delete', 'vendor-dashboard'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr><td colspan="7"><?php esc_html_e( 'No coupons found.', 'vendor-dashboard' ); ?></td></tr>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </tbody>
    </table>
    <div class="vdb-pagination">
        <?php
        $base_url = remove_query_arg( 'paged', esc_url_raw( add_query_arg( null, null ) ) );
        echo paginate_links( array(
            'base'      => $base_url . '%_%',
            'format'    => (strpos($base_url, '?') === false ? '?' : '&') . 'paged=%#%',
            'current'   => max( 1, get_query_var('paged') ),
            'total'     => $coupons_query->max_num_pages,
            'prev_text' => __('« Previous'),
            'next_text' => __('Next »'),
            'add_args' => false, 
        ) );
        ?>
    </div>
    <?php
}

/**
 * Render the coupon editor form structure.
 */
function vdb_render_coupon_editor_form() {
    // Nonce is added via JS localized data `vdbDashboardData.save_coupon_nonce`
    // Vendor products also via `vdbDashboardData.vendor_products`
    ?>
    <div id="vdb-coupon-editor-container" style="display: none; margin-top: 20px; border: 1px solid #dcdcde; background: #f0f0f1; border-radius: 4px;">
        <h3 style="font-size: 1.5em; padding: 15px 25px; margin: 0; background-color: #fff; border-bottom: 1px solid #dcdcde; border-radius: 4px 4px 0 0; color: #1d2327;"><?php esc_html_e( 'Coupon Editor', 'vendor-dashboard' ); ?></h3>
        <form id="vdb-coupon-editor-form" method="post">
            <input type="hidden" name="vdb_coupon_id" id="vdb_edit_coupon_id" value="">
            <input type="hidden" name="vdb_save_coupon_nonce_field" value="<?php echo wp_create_nonce('vdb_save_coupon_nonce'); ?>">


            <div class="vdb-form-section"> 
                <h4><?php esc_html_e('General', 'vendor-dashboard'); ?></h4>
                <div class="vdb-form-grid">
                    <p>
                        <label for="vdb_coupon_code"><?php esc_html_e('Coupon Code', 'vendor-dashboard'); ?></label>
                        <input type="text" name="vdb_coupon_code" id="vdb_coupon_code" required>
                        <small><?php esc_html_e('The code customers will enter to apply the discount.', 'vendor-dashboard'); ?></small>
                    </p>
                    <p>
                        <label for="vdb_coupon_description"><?php esc_html_e('Description (Optional)', 'vendor-dashboard'); ?></label>
                        <textarea name="vdb_coupon_description" id="vdb_coupon_description" rows="2"></textarea>
                    </p>
                </div>
                <p>
                    <label for="vdb_coupon_discount_type"><?php esc_html_e('Discount Type', 'vendor-dashboard'); ?></label>
                    <select name="vdb_coupon_discount_type" id="vdb_coupon_discount_type">
                        <?php /* Options populated by JS from vdbDashboardData.coupon_discount_types */ ?>
                    </select>
                </p>
                <p>
                    <label for="vdb_coupon_amount"><?php esc_html_e('Coupon Amount', 'vendor-dashboard'); ?></label>
                    <input type="text" inputmode="decimal" name="vdb_coupon_amount" id="vdb_coupon_amount" class="short wc_input_price" required>
                    <small><?php esc_html_e('Value of the coupon. For percentage, enter without % sign (e.g., 10 for 10%).', 'vendor-dashboard'); ?></small>
                </p>
                <p>
                    <label><input type="checkbox" name="vdb_coupon_free_shipping" id="vdb_coupon_free_shipping" value="yes"> <?php esc_html_e('Allow free shipping', 'vendor-dashboard'); ?></label>
                    <small><?php esc_html_e('Check this box if the coupon grants free shipping. A free shipping method must be enabled in your shipping zone and be set to require "a valid free shipping coupon".', 'vendor-dashboard'); ?></small>
                </p>
                <p>
                    <label for="vdb_coupon_expiry_date"><?php esc_html_e('Coupon Expiry Date (Optional)', 'vendor-dashboard'); ?></label>
                    <input type="text" name="vdb_coupon_expiry_date" id="vdb_coupon_expiry_date" class="vdb-datepicker" placeholder="YYYY-MM-DD">
                </p>
            </div>

            <div class="vdb-form-section"> 
                <h4><?php esc_html_e('Usage Restriction', 'vendor-dashboard'); ?></h4>
                <div class="vdb-form-grid">
                    <p>
                        <label for="vdb_coupon_min_spend"><?php esc_html_e('Minimum Spend (Optional)', 'vendor-dashboard'); ?></label>
                        <input type="text" inputmode="decimal" name="vdb_coupon_min_spend" id="vdb_coupon_min_spend" class="short wc_input_price">
                    </p>
                    <p>
                        <label for="vdb_coupon_max_spend"><?php esc_html_e('Maximum Spend (Optional)', 'vendor-dashboard'); ?></label>
                        <input type="text" inputmode="decimal" name="vdb_coupon_max_spend" id="vdb_coupon_max_spend" class="short wc_input_price">
                    </p>
                </div>
                <p>
                    <label><input type="checkbox" name="vdb_coupon_individual_use" id="vdb_coupon_individual_use" value="yes"> <?php esc_html_e('Individual use only', 'vendor-dashboard'); ?></label>
                    <small><?php esc_html_e('Check this box if the coupon cannot be used in conjunction with other coupons.', 'vendor-dashboard'); ?></small>
                </p>
                <p>
                    <label><input type="checkbox" name="vdb_coupon_exclude_sale_items" id="vdb_coupon_exclude_sale_items" value="yes"> <?php esc_html_e('Exclude sale items', 'vendor-dashboard'); ?></label>
                    <small><?php esc_html_e('Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are no sale items in the cart.', 'vendor-dashboard'); ?></small>
                </p>
                <p>
                    <label for="vdb_coupon_product_ids"><?php esc_html_e('Products (Optional - Applies to your products only)', 'vendor-dashboard'); ?></label>
                    <select name="vdb_coupon_product_ids[]" id="vdb_coupon_product_ids" multiple="multiple" class="wc-product-search" data-placeholder="<?php esc_attr_e( 'Search for your products…', 'vendor-dashboard' ); ?>" style="width:100%;">
                        <?php /* Options populated by JS from vdbDashboardData.vendor_products */ ?>
                    </select>
                    <small><?php esc_html_e('Select products this coupon will apply to. If left blank, it applies to all your products. This coupon will ONLY ever apply to items you own.', 'vendor-dashboard'); ?></small>
                </p>
                <p>
                    <label for="vdb_coupon_exclude_product_ids"><?php esc_html_e('Exclude Products (Optional - Applies to your products only)', 'vendor-dashboard'); ?></label>
                    <select name="vdb_coupon_exclude_product_ids[]" id="vdb_coupon_exclude_product_ids" multiple="multiple" class="wc-product-search" data-placeholder="<?php esc_attr_e( 'Search for your products to exclude…', 'vendor-dashboard' ); ?>" style="width:100%;">
                         <?php /* Options populated by JS */ ?>
                    </select>
                </p>
            </div>

            <div class="vdb-form-section"> 
                <h4><?php esc_html_e('Usage Limits', 'vendor-dashboard'); ?></h4>
                <div class="vdb-form-grid">
                    <p>
                        <label for="vdb_coupon_usage_limit"><?php esc_html_e('Usage limit per coupon (Optional)', 'vendor-dashboard'); ?></label>
                        <input type="number" name="vdb_coupon_usage_limit" id="vdb_coupon_usage_limit" class="short" step="1" min="0">
                    </p>
                    <p>
                        <label for="vdb_coupon_usage_limit_per_user"><?php esc_html_e('Usage limit per user (Optional)', 'vendor-dashboard'); ?></label>
                        <input type="number" name="vdb_coupon_usage_limit_per_user" id="vdb_coupon_usage_limit_per_user" class="short" step="1" min="0">
                    </p>
                </div>
            </div>

            <p style="margin-top: 25px; border-top: 1px solid #dcdcde; padding: 20px 25px; margin-bottom: 0; background: #f7f7f7; border-radius: 0 0 4px 4px; text-align: right;">
                <button type="submit" class="button button-primary vdb-save-coupon"><?php esc_html_e( 'Save Coupon', 'vendor-dashboard' ); ?></button>
                <button type="button" class="button vdb-cancel-coupon-edit"><?php esc_html_e( 'Cancel', 'vendor-dashboard' ); ?></button>
            </p>
            <div class="vdb-editor-notice" style="display: none; margin: 10px 25px;"></div>
        </form>
    </div>
    <?php
}

/**
 * AJAX Callback: Fetch coupon data for editing.
 */
function vdb_ajax_get_coupon_data_callback() {
    ob_start();
    try {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'vdb_fetch_coupon_nonce' ) ) {
            throw new Exception( __( 'Nonce verification failed [Fetch Coupon].', 'vendor-dashboard' ), 403 );
        }
        if ( ! is_user_logged_in() ) {
            throw new Exception( __( 'Permission denied [Login].', 'vendor-dashboard' ), 403 );
        }
        $current_user = wp_get_current_user();
        if ( ! in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user->roles ) ) {
            throw new Exception( __( 'Permission denied [Role].', 'vendor-dashboard' ), 403 );
        }

        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id ) {
            throw new Exception( __( 'Invalid Coupon ID.', 'vendor-dashboard' ), 400 );
        }

        $coupon_post = get_post( $coupon_id );
        if ( ! $coupon_post || 'shop_coupon' !== $coupon_post->post_type ) {
            throw new Exception( __( 'Coupon not found.', 'vendor-dashboard' ), 404 );
        }

        $vdb_meta_vendor_id = get_post_meta( $coupon_id, '_vdb_vendor_id', true );
        if ( absint($vdb_meta_vendor_id) !== $current_user->ID && absint($coupon_post->post_author) !== $current_user->ID) {
             throw new Exception( __( 'Permission denied. You do not own this coupon.', 'vendor-dashboard' ), 403 );
        }


        $coupon = new WC_Coupon( $coupon_id );
        $expiry_date = $coupon->get_date_expires();

        $data_to_send = array(
            'id'                       => $coupon->get_id(),
            'code'                     => $coupon->get_code( 'edit' ),
            'description'              => $coupon->get_description( 'edit' ),
            'discount_type'            => $coupon->get_discount_type( 'edit' ),
            'amount'                   => $coupon->get_amount( 'edit' ),
            'free_shipping'            => $coupon->get_free_shipping( 'edit' ), 
            'expiry_date'              => $expiry_date ? $expiry_date->date( 'Y-m-d' ) : '',
            'minimum_amount'           => $coupon->get_minimum_amount( 'edit' ),
            'maximum_amount'           => $coupon->get_maximum_amount( 'edit' ),
            'individual_use'           => $coupon->get_individual_use( 'edit' ), 
            'exclude_sale_items'       => $coupon->get_exclude_sale_items( 'edit' ), 
            'product_ids'              => $coupon->get_product_ids( 'edit' ), 
            'excluded_product_ids'     => $coupon->get_excluded_product_ids( 'edit' ), 
            'usage_limit'              => $coupon->get_usage_limit( 'edit' ),
            'usage_limit_per_user'     => $coupon->get_usage_limit_per_user( 'edit' ),
        );
        ob_end_clean();
        wp_send_json_success( $data_to_send );

    } catch (Exception $e) {
        ob_end_clean();
        $error_code = is_numeric($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        error_log("VDB Get Coupon Data Error: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Get Coupon Data Error: ' . $e->getMessage() ), $error_code );
    }
}


/**
 * AJAX Callback: Save coupon data.
 */
function vdb_ajax_save_coupon_data_callback() {
    ob_start();
    try {
        $nonce = isset($_POST['vdb_save_coupon_nonce_field']) ? sanitize_text_field($_POST['vdb_save_coupon_nonce_field']) : '';
        if ( ! wp_verify_nonce( $nonce, 'vdb_save_coupon_nonce' ) ) {
            throw new Exception( __( 'Security check failed [Save Coupon].', 'vendor-dashboard' ), 403 );
        }

        if ( ! is_user_logged_in() ) {
            throw new Exception( __( 'Permission denied. Please log in.', 'vendor-dashboard' ), 403 );
        }
        $current_user = wp_get_current_user();
        if ( ! in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user->roles ) ) {
            throw new Exception( __( 'Permission denied. Not a vendor.', 'vendor-dashboard' ), 403 );
        }
        $vendor_id = $current_user->ID;

        $coupon_id = isset( $_POST['vdb_coupon_id'] ) ? absint( $_POST['vdb_coupon_id'] ) : 0;

        if ( $coupon_id > 0 ) {
            if ( ! current_user_can( 'edit_shop_coupon', $coupon_id ) ) {
                throw new Exception( __( 'Permission denied to edit this coupon.', 'vendor-dashboard' ), 403 );
            }
            $vdb_meta_vendor_id_check = get_post_meta( $coupon_id, '_vdb_vendor_id', true );
             $coupon_author_check = get_post_field('post_author', $coupon_id);
            if ( absint($vdb_meta_vendor_id_check) !== $vendor_id && absint($coupon_author_check) !== $vendor_id) {
                 throw new Exception( __( 'Permission denied. You do not own this coupon for editing.', 'vendor-dashboard' ), 403 );
            }
        } else { 
            if ( ! current_user_can( 'publish_shop_coupons' ) ) {
                 throw new Exception( __( 'Permission denied to create coupons.', 'vendor-dashboard' ), 403 );
            }
        }

        $coupon_code = isset( $_POST['vdb_coupon_code'] ) ? wc_sanitize_coupon_code( wp_unslash( $_POST['vdb_coupon_code'] ) ) : '';
        if ( empty( $coupon_code ) ) {
            throw new Exception( __( 'Coupon code is required.', 'vendor-dashboard' ), 400 );
        }
        
        $existing_coupon_id_by_code = wc_get_coupon_id_by_code( $coupon_code );
        if ( $existing_coupon_id_by_code && $existing_coupon_id_by_code !== $coupon_id ) {
            throw new Exception( __( 'Coupon code already exists.', 'vendor-dashboard' ), 400 );
        }

        $description = isset( $_POST['vdb_coupon_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vdb_coupon_description'] ) ) : '';
        $discount_type = isset( $_POST['vdb_coupon_discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_coupon_discount_type'] ) ) : '';
        $coupon_amount = isset( $_POST['vdb_coupon_amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_coupon_amount'] ) ) ) : '';
        $free_shipping = isset( $_POST['vdb_coupon_free_shipping'] ) && $_POST['vdb_coupon_free_shipping'] === 'yes';
        $expiry_date_str = isset( $_POST['vdb_coupon_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_coupon_expiry_date'] ) ) : '';
        $expiry_timestamp = !empty($expiry_date_str) ? strtotime( $expiry_date_str . ' 23:59:59' ) : null; 

        $min_spend = isset( $_POST['vdb_coupon_min_spend'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_coupon_min_spend'] ) ) ) : '';
        $max_spend = isset( $_POST['vdb_coupon_max_spend'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_coupon_max_spend'] ) ) ) : '';
        $individual_use = isset( $_POST['vdb_coupon_individual_use'] ) && $_POST['vdb_coupon_individual_use'] === 'yes';
        $exclude_sale_items = isset( $_POST['vdb_coupon_exclude_sale_items'] ) && $_POST['vdb_coupon_exclude_sale_items'] === 'yes';

        $product_ids_raw = isset( $_POST['vdb_coupon_product_ids'] ) && is_array( $_POST['vdb_coupon_product_ids'] ) ? $_POST['vdb_coupon_product_ids'] : array();
        $excluded_product_ids_raw = isset( $_POST['vdb_coupon_exclude_product_ids'] ) && is_array( $_POST['vdb_coupon_exclude_product_ids'] ) ? $_POST['vdb_coupon_exclude_product_ids'] : array();
        
        $validated_product_ids = array();
        foreach ( $product_ids_raw as $pid_raw ) {
            $pid = absint( $pid_raw );
            if ( $pid > 0 && get_post_field( 'post_author', $pid ) == $vendor_id ) {
                $validated_product_ids[] = $pid;
            }
        }
        $validated_excluded_product_ids = array();
        foreach ( $excluded_product_ids_raw as $pid_raw ) {
            $pid = absint( $pid_raw );
            if ( $pid > 0 && get_post_field( 'post_author', $pid ) == $vendor_id ) {
                $validated_excluded_product_ids[] = $pid;
            }
        }
        
        $usage_limit = isset( $_POST['vdb_coupon_usage_limit'] ) ? absint( $_POST['vdb_coupon_usage_limit'] ) : '';
        $usage_limit_per_user = isset( $_POST['vdb_coupon_usage_limit_per_user'] ) ? absint( $_POST['vdb_coupon_usage_limit_per_user'] ) : '';

        $coupon_data = array(
            'post_title'   => $coupon_code, 'post_content' => '', 'post_status'  => 'publish', 'post_author'  => $vendor_id, 'post_type'    => 'shop_coupon', 'post_excerpt' => $description,
        );

        if ( $coupon_id > 0 ) {
            $coupon_data['ID'] = $coupon_id;
            $updated_post_id = wp_update_post( $coupon_data, true );
            if ( is_wp_error( $updated_post_id ) ) {
                throw new Exception( __( 'Error updating coupon: ', 'vendor-dashboard' ) . $updated_post_id->get_error_message(), 500 );
            }
        } else {
            $coupon_id = wp_insert_post( $coupon_data, true );
            if ( is_wp_error( $coupon_id ) ) {
                throw new Exception( __( 'Error creating coupon: ', 'vendor-dashboard' ) . $coupon_id->get_error_message(), 500 );
            }
        }

        update_post_meta( $coupon_id, 'discount_type', $discount_type );
        update_post_meta( $coupon_id, 'coupon_amount', $coupon_amount );
        update_post_meta( $coupon_id, 'individual_use', $individual_use ? 'yes' : 'no' );
        update_post_meta( $coupon_id, 'product_ids', implode( ',', array_map( 'absint', $validated_product_ids ) ) );
        update_post_meta( $coupon_id, 'exclude_product_ids', implode( ',', array_map( 'absint', $validated_excluded_product_ids ) ) );
        update_post_meta( $coupon_id, 'usage_limit', $usage_limit );
        update_post_meta( $coupon_id, 'usage_limit_per_user', $usage_limit_per_user );
        update_post_meta( $coupon_id, 'expiry_date', $expiry_timestamp ? date('Y-m-d', $expiry_timestamp) : '' ); 
        update_post_meta( $coupon_id, 'free_shipping', $free_shipping ? 'yes' : 'no' );
        update_post_meta( $coupon_id, 'exclude_sale_items', $exclude_sale_items ? 'yes' : 'no' );
        update_post_meta( $coupon_id, 'minimum_amount', $min_spend );
        update_post_meta( $coupon_id, 'maximum_amount', $max_spend );
        update_post_meta( $coupon_id, '_vdb_vendor_id', $vendor_id );
        delete_transient( 'wc_coupon_props_' . $coupon_id );

        ob_end_clean();
        wp_send_json_success( array( 'message' => __( 'Coupon saved successfully!', 'vendor-dashboard' ) ) );

    } catch (Exception $e) {
        ob_end_clean();
        $error_code = is_numeric($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        error_log("VDB Save Coupon Error: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Save Coupon Error: ' . $e->getMessage() ), $error_code );
    }
}

/**
 * AJAX Callback: Delete coupon data.
 */
function vdb_ajax_delete_coupon_data_callback() {
    ob_start();
    try {
        $coupon_id = isset($_POST['coupon_id']) ? absint($_POST['coupon_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!$coupon_id || !wp_verify_nonce($nonce, 'vdb_delete_coupon_' . $coupon_id)) {
            throw new Exception(__('Security check failed or invalid coupon ID.', 'vendor-dashboard'), 403);
        }

        if (!is_user_logged_in()) {
            throw new Exception(__('Permission denied. Please log in.', 'vendor-dashboard'), 403);
        }
        $current_user = wp_get_current_user();
        if (!in_array(VENDOR_DASHBOARD_ROLE, (array)$current_user->roles)) {
            throw new Exception(__('Permission denied. Not a vendor.', 'vendor-dashboard'), 403);
        }
        
        $vdb_meta_vendor_id = get_post_meta($coupon_id, '_vdb_vendor_id', true);
        $coupon_author = get_post_field('post_author', $coupon_id);
        if (absint($vdb_meta_vendor_id) !== $current_user->ID && absint($coupon_author) !== $current_user->ID) {
            throw new Exception(__('Permission denied. You do not own this coupon.', 'vendor-dashboard'), 403);
        }
        
        if (!current_user_can('delete_shop_coupon', $coupon_id)) {
             throw new Exception(__('You do not have permission to delete this coupon.', 'vendor-dashboard'), 403);
        }

        $result = wp_delete_post($coupon_id, true); 

        if ($result === false || is_wp_error($result)) {
            throw new Exception(__('Failed to delete coupon.', 'vendor-dashboard'), 500);
        }
        
        ob_end_clean();
        wp_send_json_success(array('message' => __('Coupon deleted successfully.', 'vendor-dashboard')));

    } catch (Exception $e) {
        ob_end_clean();
        $error_code = is_numeric($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        error_log("VDB Delete Coupon Error: " . $e->getMessage());
        wp_send_json_error(array('message' => 'Delete Coupon Error: ' . $e->getMessage()), $error_code);
    }
}

?>