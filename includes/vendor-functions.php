<?php
/**
 * Helper functions for the Vendor Dashboard plugin.
 * Includes Overview page data functions and Profile Settings.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

/** Get vendor product count (existing). */
function vdb_get_vendor_product_count( $vendor_id ) { /* Unchanged */ if ( ! $vendor_id || ! user_exists($vendor_id) ) { return 0; } return count_user_posts( $vendor_id, 'product', true ); }
/** Get total sales for *all* vendors (existing - used by admin page). */
function vdb_get_all_vendor_sales_total( $start_date, $end_date ) { /* Unchanged */ $total_sales = 0.00; $vendor_users = get_users( array( 'role' => VENDOR_DASHBOARD_ROLE, 'fields' => 'ID' ) ); if ( empty( $vendor_users ) ) { return $total_sales; } $args = array( 'limit' => -1, 'status' => array('wc-processing', 'wc-completed'), 'date_query' => array( array( 'after' => $start_date, 'before' => $end_date, 'inclusive' => true, ), ), 'return' => 'ids', ); $order_ids = wc_get_orders( $args ); if ( empty( $order_ids ) ) { return $total_sales; } foreach ( $order_ids as $order_id ) { $order = wc_get_order( $order_id ); if ( ! $order ) continue; $order_contains_vendor_product = false; foreach ( $order->get_items() as $item_id => $item ) { $product_id = $item->get_product_id(); if ( $product_id ) { $product_author_id = get_post_field( 'post_author', $product_id ); if ( $product_author_id && in_array( $product_author_id, $vendor_users ) ) { $order_contains_vendor_product = true; break; } } } if ($order_contains_vendor_product) { $total_sales += $order->get_total(); } } return $total_sales; }
/** Get Brand Name. */
function vdb_get_vendor_brand_name( $vendor_id ) { /* Unchanged */ if ( ! $vendor_id ) { return ''; } $brand_name = get_user_meta( $vendor_id, 'vdb_brand_name', true ); if (empty($brand_name)) { $user_info = get_userdata($vendor_id); $brand_name = $user_info ? $user_info->display_name : ''; } return $brand_name; }

// ============================================
// OVERVIEW PAGE FUNCTIONS (Unchanged from previous response)
// ============================================
function vdb_get_vendor_sales_summary( $vendor_id, $days = 7 ) { /* ... */ if ( ! $vendor_id || ! $days ) { return array( 'total_sales' => 0, 'order_count' => 0, 'avg_order_value' => 0 ); } $end_date = current_time('mysql'); $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", current_time('timestamp'))); $total_sales = 0.00; $order_ids_with_vendor_items = array(); $args = array( 'limit' => -1, 'status' => array('wc-processing', 'wc-completed'), 'date_query' => array( array( 'after' => $start_date, 'before' => $end_date, 'inclusive' => true, ), ), 'return' => 'ids', ); $order_ids = wc_get_orders( $args ); if ( ! empty( $order_ids ) ) { foreach ( $order_ids as $order_id ) { $order = wc_get_order( $order_id ); if ( ! $order ) continue; $order_has_vendor_item = false; foreach ( $order->get_items() as $item_id => $item ) { $product_id = $item->get_product_id(); if ( $product_id && get_post_field( 'post_author', $product_id ) == $vendor_id ) { $total_sales += $item->get_total(); $order_has_vendor_item = true; } } if ( $order_has_vendor_item ) { $order_ids_with_vendor_items[$order_id] = true; } } } $order_count = count( $order_ids_with_vendor_items ); $avg_order_value = ( $order_count > 0 ) ? $total_sales / $order_count : 0; $summary_data = array( 'total_sales' => $total_sales, 'order_count' => $order_count, 'avg_order_value' => $avg_order_value, ); return $summary_data; }
function vdb_get_vendor_order_status_counts( $vendor_id, $statuses = array('wc-processing', 'wc-on-hold') ) { /* ... */ if ( ! $vendor_id || empty($statuses) ) { return array_fill_keys( $statuses, 0 ); } $counts = array_fill_keys( $statuses, 0 ); $orders_counted = array(); $args = array( 'limit' => -1, 'status' => $statuses, 'return' => 'ids', ); $order_ids = wc_get_orders( $args ); if ( ! empty( $order_ids ) ) { foreach ( $order_ids as $order_id ) { if (isset($orders_counted[$order_id])) continue; $order = wc_get_order( $order_id ); if ( ! $order ) continue; $order_status = $order->get_status(); $wc_order_status = 'wc-' . $order_status; if (!in_array($wc_order_status, $statuses)) continue; foreach ( $order->get_items() as $item_id => $item ) { $product_id = $item->get_product_id(); if ( $product_id && get_post_field( 'post_author', $product_id ) == $vendor_id ) { $counts[ $wc_order_status ]++; $orders_counted[$order_id] = true; break; } } } } return $counts; }
function vdb_get_vendor_pending_product_count( $vendor_id ) { /* ... */ if ( ! $vendor_id ) return 0; $args = array( 'post_type' => 'product', 'post_status' => 'pending', 'author' => $vendor_id, 'posts_per_page' => 1, 'fields' => 'ids'); $query = new WP_Query($args); $count = (int) $query->found_posts; return $count; }
function vdb_get_vendor_low_stock_product_count( $vendor_id ) { /* ... */ if ( ! $vendor_id ) return 0; $threshold = defined('VDB_LOW_STOCK_THRESHOLD') ? (int) VDB_LOW_STOCK_THRESHOLD : 5; $args = array( 'post_type' => 'product', 'post_status' => 'publish', 'author' => $vendor_id, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_manage_stock', 'value' => 'yes',), array( 'key' => '_stock', 'value' => $threshold, 'compare' => '<=', 'type' => 'NUMERIC',), array( 'key' => '_stock_status', 'value' => 'instock',),),); $query = new WP_Query($args); $count = (int) $query->found_posts; return $count; }
function vdb_get_vendor_recent_orders( $vendor_id, $limit = 5 ) { /* ... */ if ( ! $vendor_id ) return array(); $recent_orders_formatted = array(); $orders_found = 0; $args = array( 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids', 'status' => array('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'),); $order_ids = wc_get_orders( $args ); if ( ! empty( $order_ids ) ) { foreach ( $order_ids as $order_id ) { if ($orders_found >= $limit) { break; } $order = wc_get_order( $order_id ); if ( ! $order ) continue; foreach ( $order->get_items() as $item_id => $item ) { $product_id = $item->get_product_id(); if ( $product_id && get_post_field( 'post_author', $product_id ) == $vendor_id ) { $recent_orders_formatted[] = array( 'id' => $order->get_id(), 'number' => $order->get_order_number(), 'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '', 'status' => wc_get_order_status_name( $order->get_status() ), 'total' => $order->get_formatted_order_total(), 'url' => $order->get_view_order_url() ); $orders_found++; break; } } } } return $recent_orders_formatted; }


// ============================================
// PROFILE SETTINGS FUNCTIONS
// ============================================

/**
 * Render the Profile Settings form for the vendor.
 */
function vdb_render_profile_settings_form( $vendor_id ) {
    $current_user_info = get_userdata($vendor_id);
    if (!$current_user_info) { echo '<p>' . esc_html__('Error: Could not load vendor data.', 'vendor-dashboard') . '</p>'; return; }
    $current_brand_name = vdb_get_vendor_brand_name( $vendor_id );
    $current_email = $current_user_info->user_email;

    $brand_logo_id = get_user_meta( $vendor_id, 'vdb_brand_logo_id', true );
    $brand_logo_url = $brand_logo_id ? wp_get_attachment_image_url( $brand_logo_id, 'medium' ) : '';

    $public_store_avatar_id = get_user_meta( $vendor_id, 'vdb_public_store_avatar_id', true );
    $public_store_avatar_url = $public_store_avatar_id ? wp_get_attachment_image_url( $public_store_avatar_id, 'medium' ) : '';

    $store_banner_id = get_user_meta( $vendor_id, 'vdb_public_store_banner_id', true );
    $store_banner_url = $store_banner_id ? wp_get_attachment_image_url( $store_banner_id, 'medium' ) : '';
    $public_phone = get_user_meta( $vendor_id, 'vdb_public_phone', true );
    $show_public_email = get_user_meta( $vendor_id, 'vdb_show_public_email', true ) === 'yes';
    $public_address = get_user_meta( $vendor_id, 'vdb_public_address', true );
    if (!is_array($public_address)) $public_address = [];
    $social_platforms = vdb_get_social_platforms();
    $social_links = vdb_get_vendor_social_profiles( $vendor_id );
    $countries = WC()->countries->get_countries();

    $about_us_content = get_user_meta( $vendor_id, 'vdb_about_us', true );
    $shipping_policy_content = get_user_meta( $vendor_id, 'vdb_shipping_policy', true );
    $return_policy_content = get_user_meta( $vendor_id, 'vdb_return_policy', true );
    ?>
    <div id="vdb-profile-settings-container">
        <form id="vdb-profile-settings-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="vdb_save_profile_nonce" value="<?php echo wp_create_nonce('vdb_save_profile_nonce'); ?>">
            <input type="hidden" name="vdb_remove_brand_logo" id="vdb_remove_brand_logo_flag" value="0">
            <input type="hidden" name="vdb_remove_public_store_avatar" id="vdb_remove_public_store_avatar_flag" value="0">
            <input type="hidden" name="vdb_remove_store_banner" id="vdb_remove_store_banner_flag" value="0">

            <div class="vdb-tabs">
                <ul class="vdb-tab-links">
                    <li><a href="#vdb-profile-tab-account" class="vdb-tab-active"><?php esc_html_e('Account & Brand', 'vendor-dashboard'); ?></a></li>
                    <li><a href="#vdb-profile-tab-public-store"><?php esc_html_e('Public Store Page', 'vendor-dashboard'); ?></a></li>
                    <li><a href="#vdb-profile-tab-payouts"><?php esc_html_e('Payout Settings', 'vendor-dashboard'); ?></a></li> <?php // New Tab ?>
                </ul>

                <div id="vdb-profile-tab-account" class="vdb-tab-content vdb-tab-active">
                    <div class="vdb-form-section"><h4><?php esc_html_e('Account Details', 'vendor-dashboard'); ?></h4><p><label for="vdb_profile_email"><?php esc_html_e('Email Address', 'vendor-dashboard'); ?></label><input type="email" name="vdb_profile_email" id="vdb_profile_email" value="<?php echo esc_attr($current_email); ?>" required class="regular-text"></p><p><label for="vdb_profile_email_confirm"><?php esc_html_e('Confirm Email Address', 'vendor-dashboard'); ?></label><input type="email" name="vdb_profile_email_confirm" id="vdb_profile_email_confirm" value="" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to keep current email', 'vendor-dashboard'); ?>"><small><?php esc_html_e('If you want to change your email, enter the new email address in both fields.', 'vendor-dashboard'); ?></small></p></div>
                    <div class="vdb-form-section"><h4><?php esc_html_e('Brand Details (for Dashboard)', 'vendor-dashboard'); ?></h4><p><label for="vdb_profile_brand_name"><?php esc_html_e('Brand Name', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_brand_name" id="vdb_profile_brand_name" value="<?php echo esc_attr($current_brand_name); ?>" required class="regular-text"></p></div>
                    <div class="vdb-form-section"><h4><?php esc_html_e('Brand Logo (for Dashboard & Emails)', 'vendor-dashboard'); ?></h4><div id="vdb-current-logo-preview" style="margin-bottom: 10px; min-height: 50px;"><?php if ($brand_logo_url): ?><div style="position:relative; display:inline-block;"><img src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php esc_attr_e('Current Brand Logo', 'vendor-dashboard'); ?>" style="max-width: 150px; height: auto; border: 1px solid #eee; vertical-align: middle;"><button type="button" class="vdb-remove-logo-btn" title="<?php esc_attr_e('Remove Logo', 'vendor-dashboard'); ?>">×</button></div><?php else: ?><span style="color: #777; font-style: italic;"><?php esc_html_e('No brand logo uploaded.', 'vendor-dashboard'); ?></span><?php endif; ?></div><div id="vdb-new-logo-preview" style="margin-bottom: 10px; max-width: 150px; display: none;"></div><p><label for="vdb_profile_brand_logo"><?php esc_html_e('Upload New Logo', 'vendor-dashboard'); ?></label><input type="file" name="vdb_profile_brand_logo" id="vdb_profile_brand_logo" accept="image/jpeg, image/png, image/gif"><small><?php esc_html_e('Recommended size: 300x150 pixels. Max file size: 2MB.', 'vendor-dashboard'); ?></small></p></div>
                </div>

                <div id="vdb-profile-tab-public-store" class="vdb-tab-content">
                    <div class="vdb-form-section">
                        <h4><?php esc_html_e('Public Store Avatar/Logo (for Public Store Page)', 'vendor-dashboard'); ?></h4>
                        <div id="vdb-current-public-store-avatar-preview" style="margin-bottom: 10px; min-height: 50px;">
                            <?php if ($public_store_avatar_url): ?>
                                <div style="position:relative; display:inline-block;">
                                    <img src="<?php echo esc_url($public_store_avatar_url); ?>" alt="<?php esc_attr_e('Current Public Store Avatar', 'vendor-dashboard'); ?>" style="max-width: 150px; height: auto; border: 1px solid #eee; vertical-align: middle; border-radius: 50%;">
                                    <button type="button" class="vdb-remove-public-store-avatar-btn" title="<?php esc_attr_e('Remove Public Avatar', 'vendor-dashboard'); ?>">×</button>
                                </div>
                            <?php else: ?>
                                <span style="color: #777; font-style: italic;"><?php esc_html_e('No public store avatar/logo uploaded.', 'vendor-dashboard'); ?></span>
                            <?php endif; ?>
                        </div>
                         <div id="vdb-new-public-store-avatar-preview" style="margin-bottom: 10px; max-width: 150px; display: none;"></div>
                        <p>
                            <label for="vdb_profile_public_store_avatar"><?php esc_html_e('Upload Public Store Avatar/Logo', 'vendor-dashboard'); ?></label>
                            <input type="file" name="vdb_profile_public_store_avatar" id="vdb_profile_public_store_avatar" accept="image/jpeg, image/png, image/gif">
                            <small><?php esc_html_e('Recommended size: 150x150 pixels (square). Max file size: 1MB.', 'vendor-dashboard'); ?></small>
                        </p>
                    </div>

                    <div class="vdb-form-section"><h4><?php esc_html_e('Store Banner (for Public Store Page)', 'vendor-dashboard'); ?></h4><div id="vdb-current-store-banner-preview" style="margin-bottom: 10px; min-height: 50px;"><?php if ($store_banner_url): ?><div style="position:relative; display:inline-block;"><img src="<?php echo esc_url($store_banner_url); ?>" alt="<?php esc_attr_e('Current Store Banner', 'vendor-dashboard'); ?>" style="max-width: 300px; height: auto; border: 1px solid #eee; vertical-align: middle;"><button type="button" class="vdb-remove-store-banner-btn" title="<?php esc_attr_e('Remove Banner', 'vendor-dashboard'); ?>">×</button></div><?php else: ?><span style="color: #777; font-style: italic;"><?php esc_html_e('No store banner uploaded.', 'vendor-dashboard'); ?></span><?php endif; ?></div><div id="vdb-new-store-banner-preview" style="margin-bottom: 10px; max-width: 300px; display: none;"></div><p><label for="vdb_profile_store_banner"><?php esc_html_e('Upload Store Banner', 'vendor-dashboard'); ?></label><input type="file" name="vdb_profile_store_banner" id="vdb_profile_store_banner" accept="image/jpeg, image/png, image/gif"><small><?php esc_html_e('Recommended size: 1200x300 pixels. Max file size: 2MB.', 'vendor-dashboard'); ?></small></p></div>
                    
                    <div class="vdb-form-section">
                        <h4><?php esc_html_e('About Your Store (for Public Store Page)', 'vendor-dashboard'); ?></h4>
                        <p>
                            <label for="vdb_profile_about_us"><?php esc_html_e('About Us / Store Description', 'vendor-dashboard'); ?></label>
                            <textarea name="vdb_about_us" id="vdb_profile_about_us" rows="6" class="large-text"><?php echo esc_textarea($about_us_content); ?></textarea>
                            <small><?php esc_html_e('Tell customers about your brand and what you offer. Basic HTML is allowed.', 'vendor-dashboard'); ?></small>
                        </p>
                    </div>

                    <div class="vdb-form-section">
                        <h4><?php esc_html_e('Store Policies (for Public Store Page)', 'vendor-dashboard'); ?></h4>
                        <p>
                            <label for="vdb_profile_shipping_policy"><?php esc_html_e('Shipping Policy', 'vendor-dashboard'); ?></label>
                            <textarea name="vdb_shipping_policy" id="vdb_profile_shipping_policy" rows="4" class="large-text"><?php echo esc_textarea($shipping_policy_content); ?></textarea>
                            <small><?php esc_html_e('Outline your shipping terms, processing times, etc. Basic HTML is allowed.', 'vendor-dashboard'); ?></small>
                        </p>
                        <p>
                            <label for="vdb_profile_return_policy"><?php esc_html_e('Return & Refund Policy', 'vendor-dashboard'); ?></label>
                            <textarea name="vdb_return_policy" id="vdb_profile_return_policy" rows="4" class="large-text"><?php echo esc_textarea($return_policy_content); ?></textarea>
                            <small><?php esc_html_e('Detail your return and refund procedures. Basic HTML is allowed.', 'vendor-dashboard'); ?></small>
                        </p>
                    </div>

                    <div class="vdb-form-section"><h4><?php esc_html_e('Public Contact Information', 'vendor-dashboard'); ?></h4><p><label for="vdb_profile_public_phone"><?php esc_html_e('Public Phone Number (Optional)', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_phone" id="vdb_profile_public_phone" value="<?php echo esc_attr($public_phone); ?>" class="regular-text"></p><p><label><input type="checkbox" name="vdb_profile_show_public_email" id="vdb_profile_show_public_email" value="yes" <?php checked($show_public_email); ?>> <?php esc_html_e('Show my email address publicly on my store page.', 'vendor-dashboard'); ?></label></p></div>
                    <div class="vdb-form-section"><h4><?php esc_html_e('Public Store Address (Optional)', 'vendor-dashboard'); ?></h4><p><label for="vdb_profile_public_address_street_1"><?php esc_html_e('Address line 1', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_address_street_1" id="vdb_profile_public_address_street_1" value="<?php echo esc_attr($public_address['street_1'] ?? ''); ?>" class="regular-text"></p><p><label for="vdb_profile_public_address_street_2"><?php esc_html_e('Address line 2', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_address_street_2" id="vdb_profile_public_address_street_2" value="<?php echo esc_attr($public_address['street_2'] ?? ''); ?>" class="regular-text"></p><div class="vdb-form-grid"><p><label for="vdb_profile_public_address_city"><?php esc_html_e('City', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_address_city" id="vdb_profile_public_address_city" value="<?php echo esc_attr($public_address['city'] ?? ''); ?>" class="regular-text"></p><p><label for="vdb_profile_public_address_state"><?php esc_html_e('State / County', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_address_state" id="vdb_profile_public_address_state" value="<?php echo esc_attr($public_address['state'] ?? ''); ?>" class="regular-text"></p></div><div class="vdb-form-grid"><p><label for="vdb_profile_public_address_zip"><?php esc_html_e('Postcode / ZIP', 'vendor-dashboard'); ?></label><input type="text" name="vdb_profile_public_address_zip" id="vdb_profile_public_address_zip" value="<?php echo esc_attr($public_address['zip'] ?? ''); ?>" class="regular-text"></p><p><label for="vdb_profile_public_address_country"><?php esc_html_e('Country', 'vendor-dashboard'); ?></label><select name="vdb_profile_public_address_country" id="vdb_profile_public_address_country" class="regular-text"><option value=""><?php esc_html_e( 'Select a country…', 'woocommerce' ); ?></option><?php foreach ( $countries as $key => $value ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $public_address['country'] ?? '' ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p></div></div>
                    <div class="vdb-form-section"><h4><?php esc_html_e('Social Media Links (Optional)', 'vendor-dashboard'); ?></h4><?php foreach ( $social_platforms as $key => $name ) : ?><p><label for="vdb_social_<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></label><input type="url" name="vdb_social_<?php echo esc_attr($key); ?>" id="vdb_social_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($social_links[$key] ?? ''); ?>" class="regular-text" placeholder="https://<?php echo esc_attr($key); ?>.com/yourpage"></p><?php endforeach; ?></div>
                </div>

                <?php // New Payouts Tab Content ?>
                <div id="vdb-profile-tab-payouts" class="vdb-tab-content">
                    <?php
                    if ( function_exists( 'vdb_render_payout_settings_fields' ) ) {
                        vdb_render_payout_settings_fields( $vendor_id );
                    } else {
                        echo '<p>' . esc_html__('Error: Payout settings fields could not be loaded.', 'vendor-dashboard') . '</p>';
                    }
                    ?>
                </div>

            </div>

            <p style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;"><button type="submit" class="button button-primary vdb-save-profile-settings"><?php esc_html_e('Save Profile', 'vendor-dashboard'); ?></button></p>
            <div class="vdb-profile-notice" style="display: none; margin-top: 10px;"></div>
        </form>
    </div>
    <?php
}

// ============================================
// SHIPPING HELPER FUNCTIONS (Unchanged)
// ============================================
function vdb_get_tracking_link( $provider_key_or_name, $tracking_number ) { /* ... */ if ( empty( $provider_key_or_name ) || empty( $tracking_number ) ) { return ''; } $sanitized_tracking_number = rawurlencode( $tracking_number ); $provider_key = strtolower( trim( $provider_key_or_name ) ); $url = ''; switch ( $provider_key ) { case 'royalmail': case 'royal mail': $url = "https://www.royalmail.com/track-your-item#/tracking-results/{$sanitized_tracking_number}"; break; case 'fedex': $url = "https://www.fedex.com/fedextrack/?trknbr={$sanitized_tracking_number}"; break; case 'usps': $url = "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={$sanitized_tracking_number}"; break; case 'dhl': case 'dhl express': $url = "https://www.dhl.com/en/express/tracking.html?AWB={$sanitized_tracking_number}&brand=DHL"; break; case 'parcelforce': case 'parcel force': $url = "https://www.parcelforce.com/track-trace?trackNumber={$sanitized_tracking_number}"; break; case 'ups': $url = "https://www.ups.com/track?loc=en_GB&tracknum={$sanitized_tracking_number}"; break; case 'canadapost': case 'canada post': $url = "https://www.canadapost-postescanada.ca/track-reperer/tblResult.aspx?trackingnumbers={$sanitized_tracking_number}"; break; case 'auspost': case 'australia post': $url = "https://auspost.com.au/mypost/track/#/details/{$sanitized_tracking_number}"; break; case 'chinapost': case 'china post': $url = "https://www.trackdog.com/china-post/{$sanitized_tracking_number}"; break; case 'epacket': $url = "https://www.17track.net/en/track?nums={$sanitized_tracking_number}"; break; case 'delhievry': $url = ''; break; case 'other': $url = ''; break; default: $url = ''; break; } return $url; }

?>