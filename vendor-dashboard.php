<?php
/**
 * Plugin Name:       Vendor Dashboard
 * Plugin URI:        https://example.com/vendor-dashboard
 * Description:       Provides a simple frontend dashboard for vendors to manage their products and shipping.
 * Version:           0.1.51
 * Author:            Generic
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vendor-dashboard
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   8.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

/** Define constants */
define( 'VENDOR_DASHBOARD_VERSION', '0.1.51' ); // Version updated
define( 'VENDOR_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'VENDOR_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'VENDOR_DASHBOARD_ROLE', 'vendor' );
define( 'VDB_LOW_STOCK_THRESHOLD', 5 );
define( 'VDB_MAX_NOTIFICATIONS', 30 ); 
define( 'VDB_NOTIFICATIONS_PER_PAGE', 10 ); 
define( 'VDB_PUBLIC_STORE_BASE_SLUG', 'partners' ); 
define( 'VDB_COMMISSION_RATE', 0.10 ); // Default/fallback if option not set
define( 'VDB_REGISTRATION_STATUS_META_KEY', '_vdb_registration_status' );
define( 'VDB_REG_STORE_DESC_KEY', '_vdb_store_description' );
define( 'VDB_REG_STORE_WEBSITE_KEY', '_vdb_store_website' );
define( 'VDB_REG_ADMIN_NOTES_KEY', '_vdb_admin_notes' );


/** Activation Hook: Add Role and Capabilities, Create Pages, Setup DB */
function vdb_activate() {
    $vendor_caps = array(
        'read' => true, 'edit_product' => true, 'read_product' => true, 'delete_product' => true, 'edit_products' => true, 'delete_products' => true, 'edit_published_products' => true, 'publish_products' => true, 'delete_published_products' => true, 'upload_files' => true, 'assign_terms' => true, 'edit_dashboard' => false, 'manage_options' => false, 'edit_shop_order' => true,
        'edit_shop_coupon' => true, 'read_shop_coupon' => true, 'delete_shop_coupon' => true, 'edit_shop_coupons' => true, 'publish_shop_coupons' => true,
        'delete_published_shop_coupons' => true, 
    );
    add_role( VENDOR_DASHBOARD_ROLE, __( 'Vendor', 'vendor-dashboard' ), $vendor_caps );
    $vendor_role = get_role( VENDOR_DASHBOARD_ROLE );
    if ( $vendor_role instanceof WP_Role ) {
        $vendor_role->add_cap( 'edit_shop_order', true ); $vendor_role->add_cap( 'assign_terms', true ); $vendor_role->add_cap( 'upload_files', true );
        $vendor_role->add_cap( 'edit_shop_coupon', true ); $vendor_role->add_cap( 'read_shop_coupon', true ); $vendor_role->add_cap( 'delete_shop_coupon', true ); $vendor_role->add_cap( 'edit_shop_coupons', true ); $vendor_role->add_cap( 'publish_shop_coupons', true );
        $vendor_role->add_cap( 'delete_published_shop_coupons', true ); 
    }
    vdb_create_plugin_pages();
    if (function_exists('vdb_earnings_create_tables')) { 
        vdb_earnings_create_tables();
    }
    vdb_public_store_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'vdb_activate' );

/** Deactivation Hook: Remove Role and Capabilities */
function vdb_deactivate() {
    $vendor_role = get_role( VENDOR_DASHBOARD_ROLE );
    if ( $vendor_role instanceof WP_Role ) {
        $vendor_role->remove_cap( 'edit_shop_order' );
        $vendor_role->remove_cap( 'edit_shop_coupon' ); $vendor_role->remove_cap( 'read_shop_coupon' ); $vendor_role->remove_cap( 'delete_shop_coupon' ); $vendor_role->remove_cap( 'edit_shop_coupons' ); $vendor_role->remove_cap( 'publish_shop_coupons' );
        $vendor_role->remove_cap( 'delete_published_shop_coupons' ); 
    }
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'vdb_deactivate' );

/** Create necessary plugin pages on activation */
function vdb_create_plugin_pages() {
    $pages_to_create = array(
        'vendordashboard' => array(
            'title' => __('Vendor Dashboard', 'vendor-dashboard'),
            'content' => '[vendor_dashboard]'
        ),
        'vendorpublicstore' => array(
            'title' => __('Vendor Stores', 'vendor-dashboard'),
            'content' => '[vdb_vendor_list]'
        )
    );

    foreach ($pages_to_create as $slug => $page_data) {
        $page_check_by_slug = get_page_by_path($slug, OBJECT, 'page');
        if ( ! $page_check_by_slug ) {
            $shortcode_page_exists = false;
            $query_args = array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_wp_page_template', 'compare' => 'EXISTS', ), ), 's' => $page_data['content'], );
            $shortcode_search_string = $page_data['content'];
            $filter_shortcode_search = function( $where ) use ( $shortcode_search_string ) { global $wpdb; $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s ", '%' . $wpdb->esc_like( $shortcode_search_string ) . '%' ); return $where; };
            add_filter( 'posts_where', $filter_shortcode_search, 10, 1 );
            $query = new WP_Query( $query_args );
            remove_filter( 'posts_where', $filter_shortcode_search, 10 );
            if ($query->have_posts()) { $shortcode_page_exists = true; }
            wp_reset_postdata();
            if (!$shortcode_page_exists) {
                $page_id = wp_insert_post(array( 'post_title' => $page_data['title'], 'post_content' => $page_data['content'], 'post_status' => 'publish', 'post_type' => 'page', 'post_name' => $slug, 'comment_status' => 'closed', 'ping_status' => 'closed', ));
                if ( $page_id && !is_wp_error($page_id) ) { update_option('vdb_page_id_' . str_replace('-', '_', $slug), $page_id); }
            }
        }
    }
}


/** Include necessary files. */
require_once VENDOR_DASHBOARD_PATH . 'includes/admin-page.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/admin-vendor-registrations.php'; // New file for managing registrations
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-functions.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-public-store-functions.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-notifications.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-products.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-orders.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-coupons.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/shortcode-login.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vdb-earnings-db.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vdb-earnings-core-functions.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-earnings-display.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/vendor-dashboard-payout-settings.php'; 
require_once VENDOR_DASHBOARD_PATH . 'includes/admin/vdb-admin-earnings-page.php';
require_once VENDOR_DASHBOARD_PATH . 'includes/admin/vdb-admin-reports-page.php'; 


/** Public Store Rewrite Rules & Query Vars */
function vdb_public_store_rewrite_rules() {
    add_rewrite_rule( '^' . VDB_PUBLIC_STORE_BASE_SLUG . '/([^/]+)/?$', 'index.php?is_vendor_store_page=1&vendor_slug=$matches[1]', 'top' );
    add_rewrite_rule( '^' . VDB_PUBLIC_STORE_BASE_SLUG . '/([^/]+)/page/([0-9]+)/?$', 'index.php?is_vendor_store_page=1&vendor_slug=$matches[1]&paged=$matches[2]', 'top' );
}
add_action( 'init', 'vdb_public_store_rewrite_rules' );

function vdb_public_store_query_vars( $vars ) {
    $vars[] = 'is_vendor_store_page';
    $vars[] = 'vendor_slug';
    return $vars;
}
add_filter( 'query_vars', 'vdb_public_store_query_vars' );

/** Template Include for Public Store Page */
function vdb_public_store_template_include( $template ) {
    if ( get_query_var( 'is_vendor_store_page' ) && get_query_var( 'vendor_slug' ) ) {
        $new_template = VENDOR_DASHBOARD_PATH . 'includes/vendor-public-store-template.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'vdb_public_store_template_include', 99 );

/** Enqueue frontend scripts and styles */
function vdb_enqueue_frontend_assets() {
    global $post;
    $is_public_store_page = (get_query_var( 'is_vendor_store_page' ) && get_query_var( 'vendor_slug' ));
    $has_dashboard_shortcode = (is_a($post, 'WP_Post') && isset($post->post_content) && has_shortcode( $post->post_content, 'vendor_dashboard' ));
    $has_vendorlist_shortcode = (is_a($post, 'WP_Post') && isset($post->post_content) && has_shortcode( $post->post_content, 'vdb_vendor_list' ));


    if ( $is_public_store_page || $has_dashboard_shortcode || $has_vendorlist_shortcode ) {
        wp_enqueue_style( 'vendor-dashboard-frontend-css', VENDOR_DASHBOARD_URL . 'assets/css/vendor-dashboard-frontend.css', array(), VENDOR_DASHBOARD_VERSION . '.' . filemtime(VENDOR_DASHBOARD_PATH . 'assets/css/vendor-dashboard-frontend.css') );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css' );
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4' );


        wp_enqueue_script( 'vendor-dashboard-frontend-js', VENDOR_DASHBOARD_URL . 'assets/js/vendor-dashboard-frontend.js', array('jquery', 'jquery-ui-datepicker'), VENDOR_DASHBOARD_VERSION . '.' . filemtime(VENDOR_DASHBOARD_PATH . 'assets/js/vendor-dashboard-frontend.js'), true );

        $js_data = array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'fetch_nonce' => wp_create_nonce( 'vdb_fetch_product_nonce' ), 'save_nonce'  => wp_create_nonce( 'vdb_save_product_nonce' ), 'save_profile_nonce' => wp_create_nonce( 'vdb_save_profile_nonce' ), 'fetch_coupon_nonce' => wp_create_nonce( 'vdb_fetch_coupon_nonce' ), 'save_coupon_nonce'  => wp_create_nonce( 'vdb_save_coupon_nonce' ),
            'notification_nonce' => wp_create_nonce('vdb_notification_nonce'),
            'text' => array(
                'remove_image_confirm' => __('Are you sure you want to remove this image?', 'vendor-dashboard'),
                'loading_data' => __('Loading data...', 'vendor-dashboard'),
                'data_loaded' => __('Data loaded.', 'vendor-dashboard'),
                'no_current_image' => __('No current image.', 'vendor-dashboard'),
                'no_gallery_images' => __('No gallery images assigned.', 'vendor-dashboard'),
                'saving' => __('Saving...', 'vendor-dashboard'),
                'error_loading' => __('Error loading data.', 'vendor-dashboard'),
                'error_saving' => __('Error saving.', 'vendor-dashboard'),
                'edit_product_title' => __('Edit Product', 'vendor-dashboard'),
                'add_new_product_title' => __('Add New Product', 'vendor-dashboard'),
                'profile_saving' => __('Saving profile...', 'vendor-dashboard'),
                'profile_saved' => __('Profile saved successfully!', 'vendor-dashboard'),
                'profile_save_error' => __('Error saving profile.', 'vendor-dashboard'),
                'remove_logo_confirm' => __('Are you sure you want to remove your brand logo?', 'vendor-dashboard'),
                'edit_coupon_title' => __('Edit Coupon', 'vendor-dashboard'),
                'add_new_coupon_title' => __('Add New Coupon', 'vendor-dashboard'),
                'error_loading_coupon' => __('Error loading coupon data.', 'vendor-dashboard'),
                'coupon_data_loaded' => __('Coupon data loaded.', 'vendor-dashboard'),
                'delete_coupon_confirm' => __('Are you sure you want to delete this coupon? This action cannot be undone.', 'vendor-dashboard'),
                'notification_dismiss_confirm' => __('Are you sure you want to mark this notification as read?', 'vendor-dashboard'),
                'notifications_dismiss_all_confirm' => __('Are you sure you want to mark all notifications as read?', 'vendor-dashboard'),
                'notification_delete_confirm' => __('Are you sure you want to permanently delete this notification?', 'vendor-dashboard'),
                'notifications_delete_all_confirm' => __('Are you sure you want to permanently delete ALL notifications? This cannot be undone.', 'vendor-dashboard'),
                'remove_banner_confirm' => __('Are you sure you want to remove your store banner?', 'vendor-dashboard'),
                'remove_public_avatar_confirm' => __('Are you sure you want to remove your public store avatar/logo?', 'vendor-dashboard'),
            )
        );
        $categories_data = array(); $product_categories = get_terms( array('taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC') ); if ( ! is_wp_error( $product_categories ) ) { foreach ($product_categories as $category) { $categories_data[$category->term_id] = $category->name; } } $js_data['categories'] = $categories_data;
        $shipping_classes_data = array(); $shipping_classes = get_terms( array('taxonomy' => 'product_shipping_class', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC') ); if ( ! is_wp_error( $shipping_classes ) ) { foreach ($shipping_classes as $shipping_class) { $shipping_classes_data[$shipping_class->term_id] = $shipping_class->name; } } $js_data['shipping_classes'] = $shipping_classes_data;
        if (is_user_logged_in()) { $current_user = wp_get_current_user(); if (in_array(VENDOR_DASHBOARD_ROLE, (array)$current_user->roles)) { $vendor_products_query = new WP_Query(array('post_type' => 'product', 'author' => $current_user->ID, 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids')); $vendor_products_for_select = array(); if ($vendor_products_query->have_posts()) { foreach ($vendor_products_query->posts as $product_id) { $vendor_products_for_select[$product_id] = get_the_title($product_id); } } wp_reset_postdata(); $js_data['vendor_products'] = $vendor_products_for_select; } }
        $js_data['coupon_discount_types'] = array( 'percent' => __( 'Percentage discount', 'woocommerce' ), 'fixed_cart' => __( 'Fixed cart discount', 'woocommerce' ), 'fixed_product' => __( 'Fixed product discount', 'woocommerce' ), );
        wp_localize_script('vendor-dashboard-frontend-js','vdbDashboardData', $js_data);
    }
}
add_action( 'wp_enqueue_scripts', 'vdb_enqueue_frontend_assets' );

// PRODUCT AJAX HANDLERS
add_action( 'wp_ajax_vdb_get_product_data', 'vdb_ajax_get_product_data' );
add_action( 'wp_ajax_vdb_save_product_data', 'vdb_ajax_save_product_data' );
function vdb_ajax_get_product_data() {
    ob_start();
    try {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'vdb_fetch_product_nonce' ) ) { throw new Exception('Nonce verification failed [Fetch Product].', 403); }
        if ( ! is_user_logged_in() ) { throw new Exception('Permission denied [Login].', 403); }
        $current_user_obj = wp_get_current_user();
        if ( ! in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user_obj->roles ) ) { throw new Exception('Permission denied [Role].', 403); }
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) { throw new Exception('Invalid Product ID.', 400); }
        $product_post = get_post( $product_id );
        if ( ! $product_post || 'product' !== $product_post->post_type ) { throw new Exception('Product not found.', 404); }
        if ( $current_user_obj->ID != $product_post->post_author ) { throw new Exception('Ownership mismatch.', 403); }
        $product = wc_get_product( $product_id );
        if ( ! $product ) { throw new Exception('Cannot get WC_Product object.', 500); }

        $featured_image_id = $product->get_image_id('edit');
        $featured_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'thumbnail') : '';
        $gallery_image_ids = $product->get_gallery_image_ids('edit');
        $gallery_images = array();
        if ( ! empty($gallery_image_ids) && is_array($gallery_image_ids) ) {
            foreach( $gallery_image_ids as $gid ) {
                $url = wp_get_attachment_image_url( $gid, 'thumbnail' );
                if ($url) { $gallery_images[] = array('id' => $gid, 'url' => $url); }
            }
        }
        $short_description = $product->get_short_description('edit');
        $category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
        $selected_category_id = ( !is_wp_error($category_ids) && !empty($category_ids) ) ? $category_ids[0] : 0;
        $weight = $product->get_weight('edit');
        $length = $product->get_length('edit');
        $width  = $product->get_width('edit');
        $height = $product->get_height('edit');
        $shipping_class_id = $product->get_shipping_class_id('edit');
        $tag_terms = wp_get_post_terms( $product_id, 'product_tag', array('fields' => 'names') );
        $tags_string = ( !is_wp_error($tag_terms) && !empty($tag_terms) ) ? implode(', ', $tag_terms) : '';
        $tax_status = $product->get_tax_status('edit');
        $tax_class = $product->get_tax_class('edit');

        $data_to_send = array(
            'id' => $product->get_id(),
            'title' => $product->get_name('edit') ?? '',
            'sku' => $product->get_sku('edit') ?? '',
            'description' => $product->get_description('edit') ?? '',
            'short_description' => $short_description ?? '',
            'regular_price' => $product->get_regular_price('edit') ?? '',
            'sale_price' => $product->get_sale_price('edit') ?? '',
            'stock_quantity' => $product->managing_stock() ? ($product->get_stock_quantity('edit') ?? '') : null,
            'featured_image_id' => $featured_image_id,
            'featured_image_url' => $featured_image_url,
            'gallery_images' => $gallery_images,
            'category_id' => $selected_category_id,
            'weight' => $weight ?? '',
            'length' => $length ?? '',
            'width' => $width ?? '',
            'height' => $height ?? '',
            'shipping_class_id' => $shipping_class_id ?? 0,
            'tags' => $tags_string,
            'tax_status' => $tax_status ?? 'taxable',
            'tax_class' => $tax_class ?? '',
        );
        ob_end_clean();
        wp_send_json_success( $data_to_send );
    } catch (Exception $e) {
        ob_end_clean();
        $error_code = $e->getCode() ?: 500;
        if ($error_code < 400 || $error_code > 599) { $error_code = 500; }
        error_log("VDB Fetch Product Caught Exception: Code: $error_code, Message: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Fetch Product Error: ' . $e->getMessage() ), $error_code );
    }
}

function vdb_ajax_save_product_data() {
    ob_start();
    try {
        $nonce = isset($_POST['vdb_save_product_nonce']) ? sanitize_text_field(wp_unslash($_POST['vdb_save_product_nonce'])) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce , 'vdb_save_product_nonce' ) ) { throw new Exception('Security check failed [Save Product].', 403); }
        if ( ! is_user_logged_in() ) { throw new Exception('Permission denied [Login].', 403); }
        $current_user_obj = wp_get_current_user();
        if ( ! in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user_obj->roles ) ) { throw new Exception('Permission denied [Role].', 403); }
        $current_product_id = isset($_POST['vdb_product_id']) ? absint($_POST['vdb_product_id']) : 0;
        if ( $current_product_id > 0 && ! current_user_can('edit_product', $current_product_id ) ) { throw new Exception('Permission denied [Product Edit].', 403); }
        if ( $current_product_id === 0 && ! current_user_can('publish_products') ) { throw new Exception('Permission denied [Create Product].', 403); }

        $product_id = $current_product_id;
        $title = isset( $_POST['vdb_title'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_title'] ) ) : '';
        $sku = isset( $_POST['vdb_sku'] ) ? wc_clean( wp_unslash( $_POST['vdb_sku'] ) ) : '';
        $description = isset( $_POST['vdb_description'] ) ? wp_kses_post( wp_unslash( $_POST['vdb_description'] ) ) : '';
        $short_description = isset( $_POST['vdb_short_description'] ) ? wp_kses_post( wp_unslash( $_POST['vdb_short_description'] ) ) : '';
        $regular_price = isset( $_POST['vdb_regular_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_regular_price'] ) ) ) : '';
        $sale_price = isset( $_POST['vdb_sale_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_sale_price'] ) ) ) : '';
        $stock_quantity_raw = isset( $_POST['vdb_stock_quantity'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['vdb_stock_quantity'] ) ) ) : '';
        $stock_quantity = ($stock_quantity_raw !== '') ? wc_stock_amount( $stock_quantity_raw ) : null;
        $category_id = isset( $_POST['vdb_category'] ) ? absint( $_POST['vdb_category'] ) : 0;
        $weight = isset( $_POST['vdb_weight'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_weight'] ) ) ) : '';
        $length = isset( $_POST['vdb_length'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_length'] ) ) ) : '';
        $width = isset( $_POST['vdb_width'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_width'] ) ) ) : '';
        $height = isset( $_POST['vdb_height'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['vdb_height'] ) ) ) : '';
        $shipping_class_id_input = isset( $_POST['vdb_shipping_class'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_shipping_class'] ) ) : '-1';
        $shipping_class_id = in_array($shipping_class_id_input, ['-1', '0'], true) ? 0 : absint( $shipping_class_id_input );
        $tags_input = isset( $_POST['vdb_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_tags'] ) ) : '';
        $remove_featured_image = isset( $_POST['vdb_remove_featured_image'] ) && $_POST['vdb_remove_featured_image'] === '1';
        $remove_gallery_ids = isset( $_POST['vdb_gallery_remove_ids_marked'] ) && is_array($_POST['vdb_gallery_remove_ids_marked']) ? array_map( 'absint', $_POST['vdb_gallery_remove_ids_marked'] ) : array();
        $tax_status = isset($_POST['vdb_tax_status']) ? sanitize_key($_POST['vdb_tax_status']) : 'taxable';
        $tax_class = isset($_POST['vdb_tax_class']) ? sanitize_text_field(wp_unslash($_POST['vdb_tax_class'])) : '';

        if ( empty($title) ) { throw new Exception('Product Name is required.', 400); }
        if ( $category_id > 0 && ! term_exists( $category_id, 'product_cat' ) ) { throw new Exception('Selected category is invalid.', 400); }
        if ( $shipping_class_id > 0 && ! term_exists( $shipping_class_id, 'product_shipping_class' ) ) { throw new Exception('Selected shipping class is invalid (ID: ' . $shipping_class_id . ').', 400); }
        if ( ! in_array( $tax_status, array( 'taxable', 'shipping', 'none' ) ) ) { $tax_status = 'taxable'; }
        $allowed_tax_classes = array_merge( array(''), WC_Tax::get_tax_class_slugs() );
        if ( ! in_array( $tax_class, $allowed_tax_classes ) ) { $tax_class = ''; }

        $message = ''; $saved_product_id = 0;
        $post_data = array( 'post_title' => $title, 'post_content' => $description, 'post_excerpt' => $short_description );
        if ( $product_id > 0 ) {
            $post_data['ID'] = $product_id;
            $updated_post_id = wp_update_post( $post_data, true );
            if ( is_wp_error( $updated_post_id ) ) { throw new Exception( 'WP Error updating post: ' . $updated_post_id->get_error_message() ); }
            $saved_product_id = $product_id;
            $product = wc_get_product( $saved_product_id );
            if ( ! $product ) { throw new Exception( 'Cannot load product to save meta [Update].' ); }
            $message = __( 'Product updated successfully!', 'vendor-dashboard' );
        } else {
            $post_data['post_status'] = 'draft'; $post_data['post_author'] = $current_user_obj->ID; $post_data['post_type'] = 'product';
            $new_product_id = wp_insert_post( $post_data, true );
            if ( is_wp_error( $new_product_id ) ) { throw new Exception( 'WP Error creating post: ' . $new_product_id->get_error_message() ); }
            if ( ! $new_product_id ) { throw new Exception( 'Failed to create new product post.' ); }
            $saved_product_id = $new_product_id;
            $product = wc_get_product( $saved_product_id );
            if ( ! $product ) { wp_delete_post($saved_product_id, true); throw new Exception( 'Cannot load WC_Product object after create.' ); }
            wp_set_object_terms( $saved_product_id, 'simple', 'product_type', false );
            $message = __( 'Product created successfully! Status: Draft.', 'vendor-dashboard' );
        }

        if ($product && $saved_product_id > 0) {
            $product->set_props(array( 'sku' => $sku, 'regular_price' => $regular_price, 'sale_price' => $sale_price, 'manage_stock' => ($stock_quantity !== null), 'stock_quantity' => ($stock_quantity !== null) ? $stock_quantity : null, 'weight' => $weight, 'length' => $length, 'width' => $width, 'height' => $height, 'tax_status' => $tax_status, 'tax_class' => $tax_class, 'shipping_class_id' => $shipping_class_id ));
            $product->save();
            wp_set_object_terms( $saved_product_id, ($category_id > 0) ? array($category_id) : array(), 'product_cat', false );
            $tag_names = array_map('trim', explode(',', $tags_input)); $tag_names = array_filter($tag_names);
            wp_set_object_terms( $saved_product_id, $tag_names, 'product_tag', false );
            
            $product->set_attributes( array() );
            $product->save();

        } else if ($saved_product_id > 0) { throw new Exception('Failed to load product object for meta update.'); }

        $image_removal_message = '';
        if ($saved_product_id > 0 && $remove_featured_image) { if (delete_post_thumbnail($saved_product_id)) { $image_removal_message = ' Featured image removed.'; update_post_meta($saved_product_id, '_thumbnail_id', ''); } else { $image_removal_message = ' Failed to remove featured image.'; error_log("VDB: Failed to remove featured image for product ID {$saved_product_id}."); } }
        $image_upload_message = '';
        if ($saved_product_id > 0 && !$remove_featured_image && isset( $_FILES['vdb_featured_image'] ) && !empty( $_FILES['vdb_featured_image']['tmp_name'] ) ) { if ( ! current_user_can( 'upload_files' ) ) { $image_upload_message = ' Insufficient permissions to upload featured image.'; } else { if(!function_exists('media_handle_upload')){ require_once( ABSPATH . 'wp-admin/includes/file.php' ); require_once( ABSPATH . 'wp-admin/includes/image.php' ); require_once( ABSPATH . 'wp-admin/includes/media.php' );} $attachment_id = media_handle_upload( 'vdb_featured_image', $saved_product_id ); if ( is_wp_error( $attachment_id ) ) { $image_upload_message = ' Featured image upload failed: ' . $attachment_id->get_error_message(); } else { if ( set_post_thumbnail( $saved_product_id, $attachment_id ) ) { $image_upload_message = ' Featured image updated.'; } else { $image_upload_message = ' Featured image uploaded but failed to set.'; } } } }
        $gallery_removal_message = '';
        if ($saved_product_id > 0 && !empty($remove_gallery_ids)) { $product_for_gallery = wc_get_product($saved_product_id); if ($product_for_gallery) { $current_gallery_ids = $product_for_gallery->get_gallery_image_ids('edit'); if (!is_array($current_gallery_ids)) $current_gallery_ids = array(); $updated_gallery_ids = array_diff($current_gallery_ids, $remove_gallery_ids); $product_for_gallery->set_gallery_image_ids($updated_gallery_ids); $product_for_gallery->save(); $removed_count = count($remove_gallery_ids); $gallery_removal_message = sprintf(' %d gallery image(s) marked for removal processed.', $removed_count); } else { $gallery_removal_message = ' Error loading product to remove gallery images.'; } }
        $gallery_upload_message = ''; $new_gallery_attachment_ids = array();
        if ( $saved_product_id > 0 && isset( $_FILES['vdb_gallery_images'] ) && isset( $_FILES['vdb_gallery_images']['name'] ) && is_array( $_FILES['vdb_gallery_images']['name'] ) ) { if ( ! current_user_can( 'upload_files' ) ) { $gallery_upload_message = ' Insufficient permissions to upload gallery images.'; } else { if(!function_exists('media_handle_upload')){ require_once( ABSPATH . 'wp-admin/includes/file.php' ); require_once( ABSPATH . 'wp-admin/includes/image.php' ); require_once( ABSPATH . 'wp-admin/includes/media.php' );} $files = array(); foreach ( $_FILES['vdb_gallery_images'] as $key => $all ) { foreach ( $all as $i => $val ) { if ( !empty( $val ) && isset($_FILES['vdb_gallery_images']['error'][$i]) && $_FILES['vdb_gallery_images']['error'][$i] === UPLOAD_ERR_OK ) { $files[$i][$key] = $val; } } } $successful_uploads = 0; $failed_uploads = 0; foreach ( $files as $file_index => $file_data ) { $_FILES['vdb_single_gallery_upload'] = $file_data; $attachment_id = media_handle_upload( 'vdb_single_gallery_upload', $saved_product_id ); unset( $_FILES['vdb_single_gallery_upload'] ); if ( is_wp_error( $attachment_id ) ) { $failed_uploads++; error_log('VDB Save Gallery Image Upload Error: [' . (isset($file_data['name']) ? $file_data['name'] : 'unknown') . '] ' . $attachment_id->get_error_message() . ' for product ID ' . $saved_product_id); } else { $new_gallery_attachment_ids[] = $attachment_id; $successful_uploads++; } } if ( ! empty( $new_gallery_attachment_ids ) ) { $product_for_gallery = wc_get_product( $saved_product_id ); if ($product_for_gallery) { $existing_gallery_ids = $product_for_gallery->get_gallery_image_ids('edit'); if ( ! is_array($existing_gallery_ids) ) { $existing_gallery_ids = array(); } $updated_gallery_ids = array_unique( array_merge( $existing_gallery_ids, $new_gallery_attachment_ids ) ); $product_for_gallery->set_gallery_image_ids( $updated_gallery_ids ); $product_for_gallery->save(); $gallery_upload_message = sprintf(' %d gallery image(s) added.', $successful_uploads); if ($failed_uploads > 0) { $gallery_upload_message .= sprintf(' %d failed.', $failed_uploads); } } else { $gallery_upload_message = ' Error loading product to update gallery meta after upload.'; } } elseif ($failed_uploads > 0) { $gallery_upload_message = sprintf(' %d gallery image upload(s) failed.', $failed_uploads); } else if (empty($files) && isset($_FILES['vdb_gallery_images']) && isset($_FILES['vdb_gallery_images']['name']) && is_array($_FILES['vdb_gallery_images']['name']) && count(array_filter($_FILES['vdb_gallery_images']['name'])) > 0) { $total_files_attempted = count(array_filter($_FILES['vdb_gallery_images']['name'])); $gallery_upload_message = sprintf(' %d gallery image upload(s) failed (check file size/type/errors).', $total_files_attempted); } } }

        ob_end_clean();
        wp_send_json_success( array( 'message' => trim( $message . $image_removal_message . $image_upload_message . $gallery_removal_message . $gallery_upload_message ) ) );

    } catch ( Exception $e ) {
        ob_end_clean();
        $error_code = $e->getCode() ?: 500;
        if ($error_code < 400 || $error_code > 599) { $error_code = 500; }
        error_log("VDB Save Product Caught Exception: Code: $error_code, Message: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Save Product Error: ' . $e->getMessage() ), $error_code );
    }
}

// SHIPPING AJAX HANDLER (Unchanged)
add_action( 'wp_ajax_vdb_save_shipping_data', 'vdb_ajax_save_shipping_data' );

// PROFILE SETTINGS AJAX HANDLER
add_action( 'wp_ajax_vdb_save_profile_settings', 'vdb_ajax_save_profile_settings' );
function vdb_ajax_save_profile_settings() { 
    ob_start(); 
    try { 
        $nonce = isset($_POST['vdb_save_profile_nonce']) ? sanitize_text_field(wp_unslash($_POST['vdb_save_profile_nonce'])) : ''; 
        if ( ! $nonce || ! wp_verify_nonce( $nonce , 'vdb_save_profile_nonce' ) ) { 
            throw new Exception(__('Security check failed [Profile Save].', 'vendor-dashboard'), 403); 
        } 
        if ( ! is_user_logged_in() ) { 
            throw new Exception(__('Permission denied [Login].', 'vendor-dashboard'), 403); 
        } 
        $current_user_obj = wp_get_current_user(); 
        if ( ! in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user_obj->roles ) ) { 
            throw new Exception(__('Permission denied [Role].', 'vendor-dashboard'), 403); 
        } 
        $vendor_id = $current_user_obj->ID; 
        $message = ''; 
        
        $new_email = isset( $_POST['vdb_profile_email'] ) ? sanitize_email( wp_unslash( $_POST['vdb_profile_email'] ) ) : ''; 
        $confirm_email = isset( $_POST['vdb_profile_email_confirm'] ) ? sanitize_email( wp_unslash( $_POST['vdb_profile_email_confirm'] ) ) : ''; 
        if ( !empty($new_email) && $new_email !== $current_user_obj->user_email ) { 
            if ( empty($confirm_email) ) { throw new Exception(__('Please confirm your new email address.', 'vendor-dashboard'), 400); } 
            if ( $new_email !== $confirm_email ) { throw new Exception(__('New email addresses do not match.', 'vendor-dashboard'), 400); } 
            if ( ! is_email( $new_email ) ) { throw new Exception(__('Invalid new email address format.', 'vendor-dashboard'), 400); } 
            if ( email_exists( $new_email ) && email_exists( $new_email ) != $vendor_id ) { throw new Exception(__('This email address is already registered by another user.', 'vendor-dashboard'), 400); } 
            $user_update_result = wp_update_user( array( 'ID' => $vendor_id, 'user_email' => $new_email ) ); 
            if ( is_wp_error( $user_update_result ) ) { throw new Exception(__('Error updating email: ', 'vendor-dashboard') . $user_update_result->get_error_message(), 500); } 
            $message .= __('Email address updated. ', 'vendor-dashboard'); 
        } elseif (!empty($new_email) && empty($confirm_email) && $new_email !== $current_user_obj->user_email) { 
            throw new Exception(__('Please confirm your new email address in the "Confirm Email Address" field if you wish to change it.', 'vendor-dashboard'), 400); 
        } 
        $brand_name = isset( $_POST['vdb_profile_brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_profile_brand_name'] ) ) : ''; 
        if ( empty($brand_name) ) { throw new Exception(__('Brand Name is required.', 'vendor-dashboard'), 400); } 
        $current_brand_name = get_user_meta($vendor_id, 'vdb_brand_name', true); 
        if ($brand_name !== $current_brand_name) { update_user_meta( $vendor_id, 'vdb_brand_name', $brand_name ); $message .= __('Brand name updated. ', 'vendor-dashboard');} 
        $remove_logo = isset( $_POST['vdb_remove_brand_logo'] ) && $_POST['vdb_remove_brand_logo'] === '1'; 
        $current_logo_id = get_user_meta($vendor_id, 'vdb_brand_logo_id', true); 
        if ($remove_logo && $current_logo_id) { 
            if (wp_delete_attachment($current_logo_id, true)) { delete_user_meta($vendor_id, 'vdb_brand_logo_id'); $message .= __('Brand logo removed. ', 'vendor-dashboard'); } 
            else { $message .= __('Failed to remove existing brand logo. ', 'vendor-dashboard'); error_log("VDB: Failed to delete attachment ID {$current_logo_id} for vendor {$vendor_id}");} 
        } elseif ( isset( $_FILES['vdb_profile_brand_logo'] ) && !empty( $_FILES['vdb_profile_brand_logo']['tmp_name'] ) ) { 
            if ( ! current_user_can( 'upload_files' ) ) { throw new Exception(__('Insufficient permissions to upload logo.', 'vendor-dashboard'), 403); } 
            if(!function_exists('media_handle_upload')){ require_once( ABSPATH . 'wp-admin/includes/file.php' ); require_once( ABSPATH . 'wp-admin/includes/image.php' ); require_once( ABSPATH . 'wp-admin/includes/media.php' );} 
            $attachment_id = media_handle_upload( 'vdb_profile_brand_logo', 0 ); 
            if ( is_wp_error( $attachment_id ) ) { throw new Exception(__('Brand logo upload failed: ', 'vendor-dashboard') . $attachment_id->get_error_message(), 500); } 
            else { if ($current_logo_id) { wp_delete_attachment($current_logo_id, true); } update_user_meta( $vendor_id, 'vdb_brand_logo_id', $attachment_id ); $message .= __('Brand logo updated. ', 'vendor-dashboard'); } 
        } 
        $remove_public_avatar = isset( $_POST['vdb_remove_public_store_avatar'] ) && $_POST['vdb_remove_public_store_avatar'] === '1'; $current_public_avatar_id = get_user_meta($vendor_id, 'vdb_public_store_avatar_id', true); if ($remove_public_avatar && $current_public_avatar_id) { if (wp_delete_attachment($current_public_avatar_id, true)) { delete_user_meta($vendor_id, 'vdb_public_store_avatar_id'); $message .= __('Public store avatar removed. ', 'vendor-dashboard'); } else { $message .= __('Failed to remove existing public store avatar. ', 'vendor-dashboard'); error_log("VDB: Failed to delete public avatar attachment ID {$current_public_avatar_id} for vendor {$vendor_id}"); } } elseif ( isset( $_FILES['vdb_profile_public_store_avatar'] ) && !empty( $_FILES['vdb_profile_public_store_avatar']['tmp_name'] ) ) { if ( ! current_user_can( 'upload_files' ) ) { throw new Exception(__('Insufficient permissions to upload public store avatar.', 'vendor-dashboard'), 403); } if(!function_exists('media_handle_upload')){ require_once( ABSPATH . 'wp-admin/includes/file.php' ); require_once( ABSPATH . 'wp-admin/includes/image.php' ); require_once( ABSPATH . 'wp-admin/includes/media.php' );} $public_avatar_attachment_id = media_handle_upload( 'vdb_profile_public_store_avatar', 0 ); if ( is_wp_error( $public_avatar_attachment_id ) ) { throw new Exception(__('Public store avatar upload failed: ', 'vendor-dashboard') . $public_avatar_attachment_id->get_error_message(), 500); } else { if ($current_public_avatar_id) { wp_delete_attachment($current_public_avatar_id, true); } update_user_meta( $vendor_id, 'vdb_public_store_avatar_id', $public_avatar_attachment_id ); $message .= __('Public store avatar updated. ', 'vendor-dashboard'); } } 
        $remove_banner = isset( $_POST['vdb_remove_store_banner'] ) && $_POST['vdb_remove_store_banner'] === '1'; $current_banner_id = get_user_meta($vendor_id, 'vdb_public_store_banner_id', true); if ($remove_banner && $current_banner_id) { if (wp_delete_attachment($current_banner_id, true)) { delete_user_meta($vendor_id, 'vdb_public_store_banner_id'); $message .= __('Store banner removed. ', 'vendor-dashboard'); } else { $message .= __('Failed to remove existing store banner. ', 'vendor-dashboard'); error_log("VDB: Failed to delete banner attachment ID {$current_banner_id} for vendor {$vendor_id}"); } } elseif ( isset( $_FILES['vdb_profile_store_banner'] ) && !empty( $_FILES['vdb_profile_store_banner']['tmp_name'] ) ) { if ( ! current_user_can( 'upload_files' ) ) { throw new Exception(__('Insufficient permissions to upload store banner.', 'vendor-dashboard'), 403); } if(!function_exists('media_handle_upload')){ require_once( ABSPATH . 'wp-admin/includes/file.php' ); require_once( ABSPATH . 'wp-admin/includes/image.php' ); require_once( ABSPATH . 'wp-admin/includes/media.php' );} $banner_attachment_id = media_handle_upload( 'vdb_profile_store_banner', 0 ); if ( is_wp_error( $banner_attachment_id ) ) { throw new Exception(__('Store banner upload failed: ', 'vendor-dashboard') . $banner_attachment_id->get_error_message(), 500); } else { if ($current_banner_id) { wp_delete_attachment($current_banner_id, true); } update_user_meta( $vendor_id, 'vdb_public_store_banner_id', $banner_attachment_id ); $message .= __('Store banner updated. ', 'vendor-dashboard'); } } 
        $about_us_content = isset( $_POST['vdb_about_us'] ) ? wp_kses_post( wp_unslash( $_POST['vdb_about_us'] ) ) : ''; $shipping_policy_content = isset( $_POST['vdb_shipping_policy'] ) ? wp_kses_post( wp_unslash( $_POST['vdb_shipping_policy'] ) ) : ''; $return_policy_content = isset( $_POST['vdb_return_policy'] ) ? wp_kses_post( wp_unslash( $_POST['vdb_return_policy'] ) ) : ''; $old_about_us = get_user_meta($vendor_id, 'vdb_about_us', true); $old_shipping_policy = get_user_meta($vendor_id, 'vdb_shipping_policy', true); $old_return_policy = get_user_meta($vendor_id, 'vdb_return_policy', true); if ($about_us_content !== $old_about_us) { update_user_meta( $vendor_id, 'vdb_about_us', $about_us_content ); $message .= __('About Us content updated. ', 'vendor-dashboard'); } if ($shipping_policy_content !== $old_shipping_policy) { update_user_meta( $vendor_id, 'vdb_shipping_policy', $shipping_policy_content ); $message .= __('Shipping policy updated. ', 'vendor-dashboard'); } if ($return_policy_content !== $old_return_policy) { update_user_meta( $vendor_id, 'vdb_return_policy', $return_policy_content ); $message .= __('Return policy updated. ', 'vendor-dashboard'); } 
        $public_phone = isset( $_POST['vdb_profile_public_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['vdb_profile_public_phone'] ) ) : ''; $show_public_email = isset( $_POST['vdb_profile_show_public_email'] ) && $_POST['vdb_profile_show_public_email'] === 'yes'; update_user_meta( $vendor_id, 'vdb_public_phone', $public_phone ); update_user_meta( $vendor_id, 'vdb_show_public_email', $show_public_email ? 'yes' : 'no' ); $address_fields = ['street_1', 'street_2', 'city', 'state', 'zip', 'country']; $address_data = []; foreach($address_fields as $field) { $address_data[$field] = isset($_POST["vdb_profile_public_address_{$field}"]) ? sanitize_text_field(wp_unslash($_POST["vdb_profile_public_address_{$field}"])) : ''; } update_user_meta($vendor_id, 'vdb_public_address', $address_data); if ( $public_phone || $show_public_email || count(array_filter($address_data)) > 0 ) { $message .= __('Public contact & address info updated. ', 'vendor-dashboard'); } 
        $social_platforms = vdb_get_social_platforms(); $social_updated = false; foreach ( $social_platforms as $platform_key => $platform_name ) { $meta_key = 'vdb_social_' . $platform_key; if ( isset( $_POST[ $meta_key ] ) ) { $new_social_url = esc_url_raw( wp_unslash( $_POST[ $meta_key ] ) ); $old_social_url = get_user_meta( $vendor_id, $meta_key, true); if ($new_social_url !== $old_social_url) { update_user_meta( $vendor_id, $meta_key, $new_social_url ); $social_updated = true; } } } if ($social_updated) { $message .= __('Social links updated. ', 'vendor-dashboard'); } 

        if ( function_exists( 'vdb_save_payout_settings' ) ) {
            if ( vdb_save_payout_settings( $vendor_id, $_POST ) ) {
                $message .= __('Payout settings updated. ', 'vendor-dashboard');
            }
        }

        if ( empty(trim($message)) ) { 
            $message = __('No changes detected in profile.', 'vendor-dashboard'); 
        } 
        ob_end_clean(); 
        wp_send_json_success( array( 'message' => trim($message) ) ); 
    } catch ( Exception $e ) { 
        ob_end_clean(); 
        $error_code = $e->getCode() ?: 500; 
        if ($error_code < 400 || $error_code > 599) { $error_code = 500; } 
        error_log("VDB Profile Save Caught Exception: Code: $error_code, Message: " . $e->getMessage()); 
        wp_send_json_error( array( 'message' => 'Profile Save Error: ' . $e->getMessage() ), $error_code ); 
    } 
}

// COUPON AJAX HANDLERS & VALIDATION HOOK (Unchanged)
add_action( 'wp_ajax_vdb_get_coupon_data', 'vdb_ajax_get_coupon_data_callback' );
add_action( 'wp_ajax_vdb_save_coupon_data', 'vdb_ajax_save_coupon_data_callback' );
add_action( 'wp_ajax_vdb_delete_coupon_data', 'vdb_ajax_delete_coupon_data_callback' );
add_filter( 'woocommerce_coupon_is_valid_for_product', 'vdb_validate_vendor_coupon_for_product', 20, 4 );
add_filter( 'woocommerce_coupon_is_valid', 'vdb_overall_coupon_validity_check_for_vendor_items', 20, 2 );
function vdb_validate_vendor_coupon_for_product( $valid, $product, $coupon, $values ) { $coupon_id = $coupon->get_id(); $vdb_vendor_id = get_post_meta( $coupon_id, '_vdb_vendor_id', true ); if ( ! empty( $vdb_vendor_id ) ) { $product_author_id = get_post_field( 'post_author', $product->get_id() ); if ( absint( $product_author_id ) !== absint( $vdb_vendor_id ) ) { return false; } } return $valid; }
function vdb_overall_coupon_validity_check_for_vendor_items( $valid, $coupon ) { if ( ! $valid ) return false; $coupon_id = $coupon->get_id(); $vdb_vendor_id = get_post_meta( $coupon_id, '_vdb_vendor_id', true ); if ( ! empty( $vdb_vendor_id ) && is_object(WC()->cart) ) { $coupon_product_ids = $coupon->get_product_ids(); if ( ! empty( $coupon_product_ids ) ) { $found_matching_vendor_product_in_cart = false; foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) { $product_in_cart = $cart_item['data']; if ( $product_in_cart ) { $product_author_id = get_post_field( 'post_author', $product_in_cart->get_id() ); if ( absint( $product_author_id ) === absint( $vdb_vendor_id ) && in_array( $product_in_cart->get_id(), $coupon_product_ids ) ) { $found_matching_vendor_product_in_cart = true; break; } } } if ( ! $found_matching_vendor_product_in_cart ) { return false; } } else { $vendor_product_in_cart = false; foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) { $product_in_cart = $cart_item['data']; if ( $product_in_cart ) { $product_author_id = get_post_field( 'post_author', $product_in_cart->get_id() ); if ( absint( $product_author_id ) === absint( $vdb_vendor_id ) ) { $vendor_product_in_cart = true; break; } } } if (!$vendor_product_in_cart) { return false; } } } return $valid; }

// NOTIFICATION SYSTEM HOOKS & AJAX (Unchanged)
add_action( 'woocommerce_order_status_processing', 'vdb_trigger_new_order_notification', 10, 2 );
add_action( 'woocommerce_order_status_on-hold', 'vdb_trigger_new_order_notification', 10, 2 );
function vdb_trigger_new_order_notification( $order_id, $order ) { if ( ! $order_id || ! $order ) return; $vendor_items_in_order = array(); foreach ( $order->get_items() as $item_id => $item ) { $product = $item->get_product(); if ( $product ) { $product_id = $product->get_id(); $vendor_id = get_post_field( 'post_author', $product_id ); if ( user_can( $vendor_id, VENDOR_DASHBOARD_ROLE ) ) { if (!isset($vendor_items_in_order[$vendor_id])) { $vendor_items_in_order[$vendor_id] = array(); } $vendor_items_in_order[$vendor_id][] = $item->get_name(); } } } foreach ($vendor_items_in_order as $vendor_id => $item_names) { $item_list_str = count($item_names) > 1 ? implode(', ', array_slice($item_names, 0, 2)) . (count($item_names) > 2 ? ' and others' : '') : $item_names[0]; $message = sprintf( __( 'You have a new order (#%1$s) containing: %2$s.', 'vendor-dashboard' ), $order->get_order_number(), esc_html($item_list_str) ); $dashboard_base_url = vdb_get_dashboard_page_url(); $order_section_url = $dashboard_base_url ? esc_url( add_query_arg( 'section', 'orders', $dashboard_base_url ) ) : ''; vdb_add_vendor_notification( $vendor_id, $message, 'new_order', $order_section_url ); } }
add_action( 'transition_post_status', 'vdb_trigger_product_approved_notification', 10, 3 );
function vdb_trigger_product_approved_notification( $new_status, $old_status, $post ) { if ( $post->post_type === 'product' && $new_status === 'publish' && $old_status !== 'publish' ) { $vendor_id = $post->post_author; if ( user_can( $vendor_id, VENDOR_DASHBOARD_ROLE ) ) { $message = sprintf( __( 'Your product "%s" has been approved and published!', 'vendor-dashboard' ), esc_html( $post->post_title ) ); $dashboard_base_url = vdb_get_dashboard_page_url(); $product_section_url = $dashboard_base_url ? esc_url( add_query_arg( 'section', 'products', $dashboard_base_url ) . '#product-' . $post->ID ) : ''; vdb_add_vendor_notification( $vendor_id, $message, 'product_approved', get_edit_post_link($post->ID, 'raw') ); } } }
add_action('wp_ajax_vdb_mark_notification_read', 'vdb_ajax_mark_notification_read_callback');
function vdb_ajax_mark_notification_read_callback() { check_ajax_referer('vdb_notification_nonce', 'nonce'); if (!is_user_logged_in() || !current_user_can(VENDOR_DASHBOARD_ROLE)) { wp_send_json_error(array('message' => __('Permission denied.', 'vendor-dashboard')), 403); } $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : null; if (!$notification_id) { wp_send_json_error(array('message' => __('Invalid notification ID.', 'vendor-dashboard')), 400); } $vendor_id = get_current_user_id(); if (vdb_mark_notification_as_read($vendor_id, $notification_id)) { wp_send_json_success(array('message' => __('Notification marked as read.', 'vendor-dashboard'))); } else { wp_send_json_error(array('message' => __('Could not mark notification as read or notification not found.', 'vendor-dashboard')), 500); } }
add_action('wp_ajax_vdb_mark_all_notifications_read', 'vdb_ajax_mark_all_notifications_read_callback');
function vdb_ajax_mark_all_notifications_read_callback() { check_ajax_referer('vdb_notification_nonce', 'nonce'); if (!is_user_logged_in() || !current_user_can(VENDOR_DASHBOARD_ROLE)) { wp_send_json_error(array('message' => __('Permission denied.', 'vendor-dashboard')), 403); } $vendor_id = get_current_user_id(); if (vdb_mark_all_notifications_as_read($vendor_id)) { wp_send_json_success(array('message' => __('All notifications marked as read.', 'vendor-dashboard'))); } else { wp_send_json_error(array('message' => __('Could not mark all notifications as read.', 'vendor-dashboard')), 500); } }
add_action('wp_ajax_vdb_delete_notification', 'vdb_ajax_delete_notification_callback');
function vdb_ajax_delete_notification_callback() { $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : null; if (!$notification_id) { wp_send_json_error(array('message' => __('Invalid notification ID for nonce check.', 'vendor-dashboard')), 400); return; } check_ajax_referer('vdb_delete_notification_' . $notification_id, 'nonce'); if (!is_user_logged_in() || !current_user_can(VENDOR_DASHBOARD_ROLE)) { wp_send_json_error(array('message' => __('Permission denied.', 'vendor-dashboard')), 403); } $vendor_id = get_current_user_id(); if (function_exists('vdb_delete_notification') && vdb_delete_notification($vendor_id, $notification_id)) { wp_send_json_success(array('message' => __('Notification deleted.', 'vendor-dashboard'))); } else { wp_send_json_error(array('message' => __('Could not delete notification or notification not found.', 'vendor-dashboard')), 500); } }
add_action('wp_ajax_vdb_delete_all_notifications', 'vdb_ajax_delete_all_notifications_callback');
function vdb_ajax_delete_all_notifications_callback() { check_ajax_referer('vdb_notification_nonce', 'nonce'); if (!is_user_logged_in() || !current_user_can(VENDOR_DASHBOARD_ROLE)) { wp_send_json_error(array('message' => __('Permission denied.', 'vendor-dashboard')), 403); } $vendor_id = get_current_user_id(); if (function_exists('vdb_delete_all_notifications') && vdb_delete_all_notifications($vendor_id)) { wp_send_json_success(array('message' => __('All notifications deleted.', 'vendor-dashboard'))); } else { wp_send_json_error(array('message' => __('Could not delete all notifications.', 'vendor-dashboard')), 500); } }

/** Public Store Shortcodes */
add_shortcode( 'vdb_vendor_list', 'vdb_render_vendor_list_shortcode' );
add_shortcode( 'vendor_public_store', 'vdb_public_store_link_shortcode_handler' );
function vdb_public_store_link_shortcode_handler( $atts ) { $atts = shortcode_atts( array( 'vendor_slug' => '', ), $atts, 'vendor_public_store' ); if ( empty( $atts['vendor_slug'] ) ) { return '<p class="vdb-error">' . esc_html__( 'Error: Vendor slug not specified for shortcode.', 'vendor-dashboard' ) . '</p>'; } $vendor_user = vdb_get_vendor_by_slug( $atts['vendor_slug'] ); if ( ! $vendor_user ) { return '<p class="vdb-error">' . esc_html__( 'Error: Vendor not found for the specified slug.', 'vendor-dashboard' ) . '</p>'; } $store_url = vdb_get_vendor_store_url_by_slug( $atts['vendor_slug'] ); $brand_name = vdb_get_vendor_brand_name( $vendor_user->ID ); if ( ! $store_url ) { return '<p class="vdb-error">' . esc_html__( 'Error: Could not generate store URL.', 'vendor-dashboard' ) . '</p>'; } ob_start(); ?> <div class="vdb-public-store-shortcode-link"> <p><?php printf( esc_html__( 'Visit the store of %s.', 'vendor-dashboard'), esc_html( $brand_name ) ); ?></p> <a href="<?php echo esc_url( $store_url ); ?>" class="button vdb-button"> <?php printf( esc_html__( 'View %s Store', 'vendor-dashboard'), esc_html( $brand_name ) ); ?> </a> </div> <?php return ob_get_clean(); }


/**
 * Handle frontend vendor registration form submission.
 */
function vdb_handle_frontend_vendor_registration() {
    if ( ! isset( $_POST['vdb_action'] ) || $_POST['vdb_action'] !== 'register_vendor_frontend' ) {
        return;
    }
    if ( ! isset( $_POST['vdb_register_frontend_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['vdb_register_frontend_nonce'] ), 'vdb_frontend_register_action' ) ) {
        set_transient( 'vdb_registration_error', array( __( 'Security check failed. Please try again.', 'vendor-dashboard' ) ), 60 );
        return;
    }

    $errors = new WP_Error();

    $username       = isset( $_POST['vdb_reg_username'] ) ? sanitize_user( $_POST['vdb_reg_username'], true ) : '';
    $email          = isset( $_POST['vdb_reg_email'] ) ? sanitize_email( $_POST['vdb_reg_email'] ) : '';
    $password       = isset( $_POST['vdb_reg_password'] ) ? $_POST['vdb_reg_password'] : '';
    $confirm_pwd    = isset( $_POST['vdb_reg_confirm_password'] ) ? $_POST['vdb_reg_confirm_password'] : '';
    $brand_name     = isset( $_POST['vdb_reg_brand_name'] ) ? sanitize_text_field( $_POST['vdb_reg_brand_name'] ) : '';
    $first_name     = isset( $_POST['vdb_reg_first_name'] ) ? sanitize_text_field( $_POST['vdb_reg_first_name'] ) : '';
    $last_name      = isset( $_POST['vdb_reg_last_name'] ) ? sanitize_text_field( $_POST['vdb_reg_last_name'] ) : '';
    $store_desc     = isset( $_POST['vdb_reg_store_description'] ) ? sanitize_textarea_field( $_POST['vdb_reg_store_description'] ) : '';
    $store_website  = isset( $_POST['vdb_reg_store_website'] ) ? esc_url_raw( $_POST['vdb_reg_store_website'] ) : '';
    $admin_notes    = isset( $_POST['vdb_reg_admin_notes'] ) ? sanitize_textarea_field( $_POST['vdb_reg_admin_notes'] ) : '';

    // Validation
    if ( empty( $username ) ) $errors->add( 'username_empty', __( 'Username is required.', 'vendor-dashboard' ) );
    if ( ! validate_username( $username ) ) $errors->add( 'username_invalid', __( 'Invalid username.', 'vendor-dashboard' ) );
    if ( username_exists( $username ) ) $errors->add( 'username_exists', __( 'Username already exists.', 'vendor-dashboard' ) );
    if ( empty( $email ) ) $errors->add( 'email_empty', __( 'Email is required.', 'vendor-dashboard' ) );
    if ( ! is_email( $email ) ) $errors->add( 'email_invalid', __( 'Invalid email address.', 'vendor-dashboard' ) );
    if ( email_exists( $email ) ) $errors->add( 'email_exists', __( 'Email address already registered.', 'vendor-dashboard' ) );
    if ( empty( $password ) ) $errors->add( 'password_empty', __( 'Password is required.', 'vendor-dashboard' ) );
    if ( $password !== $confirm_pwd ) $errors->add( 'password_mismatch', __( 'Passwords do not match.', 'vendor-dashboard' ) );
    if ( empty( $brand_name ) ) $errors->add( 'brand_name_empty', __( 'Brand Name is required.', 'vendor-dashboard' ) );
    if ( empty( $first_name ) ) $errors->add( 'first_name_empty', __( 'First Name is required.', 'vendor-dashboard' ) ); // First name now required
    if ( empty( $store_desc ) ) $errors->add( 'store_desc_empty', __( 'Store description is required.', 'vendor-dashboard' ) );
    
    if ( $errors->has_errors() ) {
        set_transient( 'vdb_registration_error', $errors->get_error_messages(), 60 );
        return;
    }

    // Create user
    $user_data = array(
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => $password,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => $brand_name,
        'role'         => 'subscriber', 
    );
    $user_id = wp_insert_user( $user_data );

    if ( is_wp_error( $user_id ) ) {
        set_transient( 'vdb_registration_error', $user_id->get_error_messages(), 60 );
        return;
    }

    update_user_meta( $user_id, 'vdb_brand_name', $brand_name );
    update_user_meta( $user_id, VDB_REG_STORE_DESC_KEY, $store_desc );
    update_user_meta( $user_id, VDB_REG_STORE_WEBSITE_KEY, $store_website );
    update_user_meta( $user_id, VDB_REG_ADMIN_NOTES_KEY, $admin_notes );
    update_user_meta( $user_id, VDB_REGISTRATION_STATUS_META_KEY, 'pending' );

    // Notify admin - Improved HTML Email
    $admin_email = get_option( 'admin_email' );
    $approval_link = admin_url( 'admin.php?page=vdb-pending-registrations' );
    $subject_admin = sprintf( __( '[%s] New Vendor Registration Pending Approval', 'vendor-dashboard' ), get_bloginfo( 'name' ) );
    
    $message_admin_html = '<p>' . __( 'A new vendor has registered and is awaiting approval:', 'vendor-dashboard' ) . '</p>';
    $message_admin_html .= '<ul>';
    $message_admin_html .= '<li><strong>' . __( 'Username:', 'vendor-dashboard' ) . '</strong> ' . esc_html( $username ) . '</li>';
    $message_admin_html .= '<li><strong>' . __( 'Email:', 'vendor-dashboard' ) . '</strong> ' . esc_html( $email ) . '</li>';
    $message_admin_html .= '<li><strong>' . __( 'Brand Name:', 'vendor-dashboard' ) . '</strong> ' . esc_html( $brand_name ) . '</li>';
    $message_admin_html .= '<li><strong>' . __( 'First Name:', 'vendor-dashboard' ) . '</strong> ' . esc_html( $first_name ) . '</li>';
    if ($last_name) $message_admin_html .= '<li><strong>' . __( 'Last Name:', 'vendor-dashboard' ) . '</strong> ' . esc_html( $last_name ) . '</li>';
    $message_admin_html .= '<li><strong>' . __( 'Store Description:', 'vendor-dashboard' ) . '</strong><br>' . nl2br( esc_html( $store_desc ) ) . '</li>';
    if ($store_website) $message_admin_html .= '<li><strong>' . __( 'Website:', 'vendor-dashboard' ) . '</strong> <a href="' . esc_url( $store_website ) . '">' . esc_html( $store_website ) . '</a></li>';
    if ($admin_notes) $message_admin_html .= '<li><strong>' . __( 'Notes for Admin:', 'vendor-dashboard' ) . '</strong><br>' . nl2br( esc_html( $admin_notes ) ) . '</li>';
    $message_admin_html .= '</ul>';
    $message_admin_html .= '<p>' . sprintf( __( 'Please review and approve/deny the application here: <a href="%s">%s</a>', 'vendor-dashboard' ), esc_url( $approval_link ), esc_url( $approval_link ) ) . '</p>';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail( $admin_email, $subject_admin, $message_admin_html, $headers );

    // Notify applicant - Improved HTML Email
    $subject_applicant = sprintf( __( '[%s] Your Vendor Application is Received', 'vendor-dashboard' ), get_bloginfo( 'name' ) );
    $message_applicant_html = '<p>' . sprintf( __( 'Hello %s,', 'vendor-dashboard' ), esc_html( $first_name ?: $username ) ) . '</p>';
    $message_applicant_html .= '<p>' . sprintf( __( 'Thank you for registering to become a vendor on %s.', 'vendor-dashboard' ), get_bloginfo( 'name' ) ) . '</p>';
    $message_applicant_html .= '<p>' . __( 'Your application is currently pending review by our admin team. You will be notified by email once a decision has been made.', 'vendor-dashboard' ) . '</p>';
    $message_applicant_html .= '<p>' . __( 'Thank you,', 'vendor-dashboard' ) . '<br>' . get_bloginfo( 'name' ) . '</p>';
    wp_mail( $email, $subject_applicant, $message_applicant_html, $headers );

    set_transient( 'vdb_registration_success', __( 'Registration successful! Your application is pending review. You will be notified by email.', 'vendor-dashboard' ), 60 );
    wp_safe_redirect( add_query_arg( 'vdb_reg_status', 'success', remove_query_arg( array('vdb_action', 'vdb_register_frontend_nonce', '_wp_http_referer', 'vdb_reg_username', 'vdb_reg_email', 'vdb_reg_password', 'vdb_reg_confirm_password', 'vdb_reg_brand_name', 'vdb_reg_first_name', 'vdb_reg_last_name', 'vdb_reg_store_description', 'vdb_reg_store_website', 'vdb_reg_admin_notes', 'vdb_reg_submit') ) ) );
    exit;
}
add_action( 'init', 'vdb_handle_frontend_vendor_registration' );


/**
 * Check login for pending or denied vendor registrations.
 */
function vdb_check_pending_denied_login($user_login, $user) {
    if (in_array(VENDOR_DASHBOARD_ROLE, (array) $user->roles)) {
        return;
    }

    $registration_status = get_user_meta($user->ID, VDB_REGISTRATION_STATUS_META_KEY, true);
    $dashboard_page_id = vdb_get_dashboard_page_id_from_shortcode(); 
    $redirect_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url('/');
    // Ensure vdb_action is not carried over in error redirects from login
    $redirect_url = remove_query_arg('vdb_action', $redirect_url);


    if ($registration_status === 'pending') {
        wp_logout();
        wp_safe_redirect(add_query_arg('vdb_login_error', 'pending_approval', $redirect_url));
        exit;
    } elseif ($registration_status === 'denied') {
        wp_logout();
        wp_safe_redirect(add_query_arg('vdb_login_error', 'denied_access', $redirect_url));
        exit;
    }
}
add_action('wp_login', 'vdb_check_pending_denied_login', 10, 2);

/** Helper function to get the dashboard page ID based on the shortcode */
function vdb_get_dashboard_page_id_from_shortcode() {
    global $wpdb;
    $page_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_type = 'page' AND post_status = 'publish' LIMIT 1",
        '%[vendor_dashboard]%'
    ));
    return $page_id;
}

?>