<?php
/**
 * Helper functions for the Public Vendor Store Page & Vendor List.
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Get a vendor WP_User object by their user_nicename (slug).
 */
function vdb_get_vendor_by_slug( $slug ) { /* Unchanged */ if ( empty( $slug ) ) { return false; } $user = get_user_by( 'slug', $slug ); if ( $user && in_array( VENDOR_DASHBOARD_ROLE, (array) $user->roles, true ) ) { return $user; } return false; }

/**
 * Get the URL for a vendor's public store page by slug.
 */
function vdb_get_vendor_store_url_by_slug( $slug ) { /* Unchanged */ if ( empty( $slug ) ) { return ''; } return home_url( '/' . VDB_PUBLIC_STORE_BASE_SLUG . '/' . $slug . '/' ); }

/**
 * Get the URL for a vendor's public store page by vendor ID.
 */
function vdb_get_vendor_store_url( $vendor_id ) { /* Unchanged */ $vendor_user = get_userdata( $vendor_id ); if ( ! $vendor_user || ! in_array( VENDOR_DASHBOARD_ROLE, (array) $vendor_user->roles, true ) ) { return ''; } return vdb_get_vendor_store_url_by_slug( $vendor_user->user_nicename ); }

/**
 * Get the vendor's public store banner URL.
 */
function vdb_get_public_store_banner_url( $vendor_id, $size = 'full' ) { /* Unchanged */ $banner_id = get_user_meta( $vendor_id, 'vdb_public_store_banner_id', true ); if ( $banner_id ) { $image_url = wp_get_attachment_image_url( $banner_id, $size ); return $image_url ? $image_url : ''; } return ''; }

/**
 * Get the vendor's public store avatar URL (uses dedicated public avatar first).
 *
 * @param int $vendor_id The vendor ID.
 * @param int $size The avatar size (approximate).
 * @return string Avatar URL or empty string if no logo and Gravatar is disabled/not found.
 */
function vdb_get_vendor_public_store_avatar_url( $vendor_id, $size = 150 ) {
    $public_avatar_id = get_user_meta( $vendor_id, 'vdb_public_store_avatar_id', true );
    if ( $public_avatar_id ) {
        $avatar_url = wp_get_attachment_image_url( $public_avatar_id, array($size, $size) );
        if ($avatar_url) return $avatar_url;
        $avatar_url_medium = wp_get_attachment_image_url( $public_avatar_id, 'medium' );
        if ($avatar_url_medium) return $avatar_url_medium;
    }
    $brand_logo_id = get_user_meta( $vendor_id, 'vdb_brand_logo_id', true );
    if ( $brand_logo_id ) {
        $logo_url = wp_get_attachment_image_url( $brand_logo_id, array($size, $size) );
        if ($logo_url) return $logo_url;
        $logo_url_medium = wp_get_attachment_image_url( $brand_logo_id, 'medium' );
        if ($logo_url_medium) return $logo_url_medium;
    }
    return '';
}

/** Get vendor's public address. */
function vdb_get_vendor_public_address_data( $vendor_id ) { /* Unchanged */ $address_data = get_user_meta( $vendor_id, 'vdb_public_address', true ); return is_array($address_data) ? $address_data : []; }

/** Get formatted vendor's public address. */
function vdb_get_formatted_vendor_public_address( $vendor_id ) { /* Unchanged */ $address_parts = []; $address_data = vdb_get_vendor_public_address_data( $vendor_id ); if ( ! empty( $address_data['street_1'] ) ) { $address_parts[] = esc_html( $address_data['street_1'] ); } if ( ! empty( $address_data['street_2'] ) ) { $address_parts[] = esc_html( $address_data['street_2'] ); } $city_state_zip = []; if ( ! empty( $address_data['city'] ) ) { $city_state_zip[] = esc_html( $address_data['city'] ); } if ( ! empty( $address_data['state'] ) ) { $city_state_zip[] = esc_html( $address_data['state'] ); } if ( ! empty( $address_data['zip'] ) ) { $city_state_zip[] = esc_html( $address_data['zip'] ); } if ( !empty($city_state_zip) ) { $address_parts[] = implode( ' ', $city_state_zip ); } if ( ! empty( $address_data['country'] ) && class_exists('WC_Countries') ) { $countries_obj = new WC_Countries(); $countries = $countries_obj->get_countries(); $country_name = isset($countries[$address_data['country']]) ? $countries[$address_data['country']] : $address_data['country']; $address_parts[] = esc_html( $country_name ); } elseif (! empty($address_data['country'])) { $address_parts[] = esc_html( $address_data['country'] ); } return implode( '<br>', array_filter($address_parts) ); }

/** Get vendor's public phone number. */
function vdb_get_vendor_public_phone( $vendor_id ) { /* Unchanged */ return get_user_meta( $vendor_id, 'vdb_public_phone', true ); }

/** Check if vendor wants to show their email publicly. */
function vdb_vendor_show_public_email( $vendor_id ) { /* Unchanged */ return get_user_meta( $vendor_id, 'vdb_show_public_email', true ) === 'yes'; }

/** Get an array of defined social platforms. */
function vdb_get_social_platforms() { /* Unchanged */ return apply_filters('vdb_social_platforms', array( 'facebook' => __('Facebook', 'vendor-dashboard'), 'twitter' => __('Twitter (X)', 'vendor-dashboard'), 'instagram' => __('Instagram', 'vendor-dashboard'), 'linkedin' => __('LinkedIn', 'vendor-dashboard'), 'youtube' => __('YouTube', 'vendor-dashboard'), 'pinterest' => __('Pinterest', 'vendor-dashboard'), )); }

/** Get vendor's social media profiles. */
function vdb_get_vendor_social_profiles( $vendor_id ) { /* Unchanged */ $profiles = array(); $platforms = vdb_get_social_platforms(); foreach ( $platforms as $key => $name ) { $url = get_user_meta( $vendor_id, 'vdb_social_' . $key, true ); if ( ! empty( $url ) ) { $profiles[ $key ] = esc_url( $url ); } } return $profiles; }

/** Get Font Awesome icon class for a social platform key. */
function vdb_get_social_icon_class( $platform_key ) { /* Unchanged */ $icons = apply_filters('vdb_social_icon_classes', array( 'facebook' => 'fab fa-facebook-f', 'twitter' => 'fab fa-twitter', 'instagram' => 'fab fa-instagram', 'linkedin' => 'fab fa-linkedin-in', 'youtube' => 'fab fa-youtube', 'pinterest' => 'fab fa-pinterest-p', )); return isset( $icons[ $platform_key ] ) ? $icons[ $platform_key ] : 'fas fa-link'; }

/** Display store sidebar categories for the vendor. */
function vdb_public_store_sidebar_categories( $vendor_id ) { /* Unchanged */ $args = array( 'post_type' => 'product', 'posts_per_page' => -1, 'author' => $vendor_id, 'fields' => 'ids', 'post_status' => 'publish'); $vendor_product_ids = get_posts( $args ); if ( empty( $vendor_product_ids ) ) { return; } $product_cats = wp_get_object_terms( $vendor_product_ids, 'product_cat', array('orderby' => 'name', 'order' => 'ASC', 'hide_empty' => true) ); if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) { echo '<aside class="widget vdb-store-widget widget_product_categories">'; echo '<h3 class="widget-title">' . esc_html__( 'Product Categories', 'vendor-dashboard' ) . '</h3>'; echo '<ul class="product-categories">'; foreach ( $product_cats as $category ) { $ancestors = get_ancestors($category->term_id, 'product_cat'); $depth_class = 'level-' . count($ancestors); $cat_link = add_query_arg( array( 'product_cat_filter' => $category->slug ), vdb_get_vendor_store_url($vendor_id) ); echo '<li class="cat-item cat-item-' . esc_attr( $category->term_id ) . ' ' . esc_attr($depth_class) . '">'; echo '<a href="' . esc_url( $cat_link ) . '">' . esc_html( $category->name ) . '</a>'; echo '</li>'; } echo '</ul>'; echo '</aside>'; } }

/** Display store sidebar contact info for the vendor. */
function vdb_public_store_sidebar_contact_info( $vendor_user ) { /* Unchanged (already conditional) */ if ( ! $vendor_user instanceof WP_User ) return; $vendor_id = $vendor_user->ID; $public_phone = vdb_get_vendor_public_phone( $vendor_id ); $show_public_email = vdb_vendor_show_public_email( $vendor_id ); $formatted_address = vdb_get_formatted_vendor_public_address( $vendor_id ); if ( empty($public_phone) && !($show_public_email && $vendor_user->user_email) && empty($formatted_address) ) { return; } ob_start(); ?> <aside class="widget vdb-store-widget widget_text vdb-contact-widget"> <h3 class="widget-title"><?php esc_html_e('Contact Vendor', 'vendor-dashboard'); ?></h3> <div class="textwidget"> <ul> <?php if ( ! empty( $public_phone ) ) : ?> <li><i class="fas fa-phone vdb-icon"></i> <?php echo esc_html( $public_phone ); ?></li> <?php endif; ?> <?php if ( $show_public_email && $vendor_user->user_email ) : ?> <li><i class="fas fa-envelope vdb-icon"></i> <a href="mailto:<?php echo esc_attr( antispambot( $vendor_user->user_email ) ); ?>"><?php echo esc_html( antispambot( $vendor_user->user_email ) ); ?></a></li> <?php endif; ?> <?php if ( !empty($formatted_address) ) : ?> <li class="vdb-address-widget"><i class="fas fa-map-marker-alt vdb-icon"></i> <?php echo wp_kses_post( $formatted_address ); ?></li> <?php endif; ?> </ul> </div> </aside> <?php echo ob_get_clean(); }

/** Shortcode to render a list of vendors. */
function vdb_render_vendor_list_shortcode( $atts ) { /* Unchanged */ $atts = shortcode_atts( array( 'orderby' => 'display_name', 'order' => 'ASC', 'number' => -1, ), $atts, 'vdb_vendor_list' ); $vendor_users = get_users( array( 'role' => VENDOR_DASHBOARD_ROLE, 'orderby' => sanitize_text_field( $atts['orderby'] ), 'order' => sanitize_text_field( $atts['order'] ), 'number' => intval( $atts['number'] ), ) ); if ( empty( $vendor_users ) ) { return '<p>' . esc_html__( 'No vendors found.', 'vendor-dashboard' ) . '</p>'; } ob_start(); ?> <div class="vdb-vendor-list-wrap"> <ul class="vdb-vendor-list"> <?php foreach ( $vendor_users as $vendor_user ) : $vendor_id = $vendor_user->ID; $brand_name = vdb_get_vendor_brand_name( $vendor_id ); $store_url = vdb_get_vendor_store_url( $vendor_id ); $avatar_url = vdb_get_vendor_public_store_avatar_url( $vendor_id, 100 ); ?> <li class="vdb-vendor-list-item"> <div class="vdb-vendor-list-avatar"> <?php if ( $avatar_url ) : ?> <a href="<?php echo esc_url( $store_url ); ?>"><img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>"></a> <?php else: ?> <a href="<?php echo esc_url( $store_url ); ?>" class="vdb-vendor-list-avatar-default"> <span><?php echo esc_html( mb_substr( $brand_name, 0, 1 ) ); ?></span> </a> <?php endif; ?> </div> <div class="vdb-vendor-list-info"> <h3 class="vdb-vendor-list-name"><a href="<?php echo esc_url( $store_url ); ?>"><?php echo esc_html( $brand_name ); ?></a></h3> <?php $short_address = vdb_get_formatted_vendor_public_address( $vendor_id ); if ( $short_address ): ?> <p class="vdb-vendor-list-address"><?php echo wp_kses_post( wp_trim_words( strip_tags($short_address), 10, '...' ) ); ?></p> <?php endif; ?> <a href="<?php echo esc_url( $store_url ); ?>" class="button vdb-button vdb-button-small"><?php esc_html_e('Visit Store', 'vendor-dashboard'); ?></a> </div> </li> <?php endforeach; ?> </ul> </div> <?php return ob_get_clean(); }

// NEW Helper functions for About Us and Policies
/**
 * Get the vendor's "About Us" content.
 * @param int $vendor_id The vendor ID.
 * @return string The "About Us" content (HTML allowed via wp_kses_post).
 */
function vdb_get_vendor_about_us( $vendor_id ) {
    if ( ! $vendor_id ) return '';
    return get_user_meta( $vendor_id, 'vdb_about_us', true );
}

/**
 * Get the vendor's shipping policy content.
 * @param int $vendor_id The vendor ID.
 * @return string The shipping policy content (HTML allowed via wp_kses_post).
 */
function vdb_get_vendor_shipping_policy( $vendor_id ) {
    if ( ! $vendor_id ) return '';
    return get_user_meta( $vendor_id, 'vdb_shipping_policy', true );
}

/**
 * Get the vendor's return policy content.
 * @param int $vendor_id The vendor ID.
 * @return string The return policy content (HTML allowed via wp_kses_post).
 */
function vdb_get_vendor_return_policy( $vendor_id ) {
    if ( ! $vendor_id ) return '';
    return get_user_meta( $vendor_id, 'vdb_return_policy', true );
}

?>