<?php
/**
 * Template for the Public Vendor Store Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$vendor_slug = get_query_var( 'vendor_slug' );
$vendor_user = vdb_get_vendor_by_slug( $vendor_slug );
$paged       = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

if ( ! $vendor_user ) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    include( get_query_template( '404' ) );
    exit;
}

$vendor_id           = $vendor_user->ID;
$brand_name          = vdb_get_vendor_brand_name( $vendor_id );
$banner_url          = vdb_get_public_store_banner_url( $vendor_id, 'full' );
$public_avatar_url   = vdb_get_vendor_public_store_avatar_url( $vendor_id, 150 );
$public_address      = vdb_get_formatted_vendor_public_address( $vendor_id );
$public_phone        = vdb_get_vendor_public_phone( $vendor_id );
$show_public_email   = vdb_vendor_show_public_email( $vendor_id );
$public_email        = $vendor_user->user_email;
$social_profiles     = vdb_get_vendor_social_profiles( $vendor_id );

$vendor_about_us         = vdb_get_vendor_about_us( $vendor_id );
$vendor_shipping_policy  = vdb_get_vendor_shipping_policy( $vendor_id );
$vendor_return_policy    = vdb_get_vendor_return_policy( $vendor_id );

$category_filter_slug = isset($_GET['product_cat_filter']) ? sanitize_key($_GET['product_cat_filter']) : '';

// NEW: Get sort and search parameters
$current_sort_by = isset($_GET['sortby']) ? sanitize_key($_GET['sortby']) : 'newest';
$current_search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

// If sorting or searching, reset pagination to page 1
if (isset($_GET['sortby']) || isset($_GET['s'])) {
    $paged = 1;
}


get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="vdb-public-store-wrap">
    <div id="vdb-store-header" class="vdb-store-header">
        <div class="vdb-store-banner-area">
            <?php if ( $banner_url ) : ?>
                <img src="<?php echo esc_url( $banner_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?> <?php esc_attr_e('Banner', 'vendor-dashboard'); ?>" class="vdb-store-banner-img">
            <?php else : ?>
                <div class="vdb-store-banner-img-default"></div>
            <?php endif; ?>
        </div>
        <div class="vdb-store-header-content-area">
            <div class="vdb-store-avatar-area">
                 <?php if ( $public_avatar_url ) : ?>
                    <img src="<?php echo esc_url( $public_avatar_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?> <?php esc_attr_e('Avatar', 'vendor-dashboard'); ?>" class="vdb-store-avatar-img">
                <?php else: ?>
                    <div class="vdb-store-avatar-img-default">
                        <span><?php echo esc_html( mb_substr( $brand_name, 0, 1 ) ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="vdb-store-info-area">
                <h1 class="vdb-store-name"><?php echo esc_html( $brand_name ); ?></h1>
                <ul class="vdb-store-contact-info">
                    <?php if ( $public_address ) : ?>
                        <li class="vdb-store-address"><i class="fas fa-map-marker-alt vdb-icon"></i> <?php echo wp_kses_post( $public_address ); ?></li>
                    <?php endif; ?>
                    <?php if ( $public_phone ) : ?>
                        <li class="vdb-store-phone"><i class="fas fa-phone vdb-icon"></i> <?php echo esc_html( $public_phone ); ?></li>
                    <?php endif; ?>
                    <?php if ( $show_public_email && $public_email ) : ?>
                        <li class="vdb-store-email"><i class="fas fa-envelope vdb-icon"></i> <a href="mailto:<?php echo esc_attr( antispambot( $public_email ) ); ?>"><?php echo esc_html( antispambot( $public_email ) ); ?></a></li>
                    <?php endif; ?>
                </ul>
                <?php if ( ! empty( $social_profiles ) ) : ?>
                    <ul class="vdb-store-social-profiles">
                        <?php foreach ( $social_profiles as $platform_key => $url ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" title="<?php echo esc_attr( ucfirst( str_replace('-', ' ', $platform_key) ) ); ?>">
                                    <i class="<?php echo esc_attr( vdb_get_social_icon_class( $platform_key ) ); ?> vdb-icon"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php do_action( 'vdb_public_store_after_header', $vendor_user ); ?>

    <?php if ( ! empty( $vendor_about_us ) ) : ?>
        <div class="vdb-store-about-us-section vdb-store-section">
            <h2 class="vdb-section-title"><?php printf( esc_html__( 'About %s', 'vendor-dashboard' ), esc_html( $brand_name ) ); ?></h2>
            <div class="vdb-section-content">
                <?php echo wp_kses_post( wpautop( $vendor_about_us ) ); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="vdb-store-body-wrap">
        <div id="vdb-store-sidebar" class="vdb-store-sidebar">
            <?php
            do_action( 'vdb_public_store_sidebar_before', $vendor_user );
            vdb_public_store_sidebar_categories( $vendor_id );
            do_action( 'vdb_public_store_sidebar_after', $vendor_user );
            ?>
        </div>

        <div id="vdb-primary" class="vdb-store-main-content">
            <?php // NEW: Store Controls (Sort & Search) ?>
            <div class="vdb-store-controls">
                <form method="GET" action="<?php echo esc_url(vdb_get_vendor_store_url($vendor_id)); ?>" class="vdb-store-filter-form">
                    <?php if (!empty($category_filter_slug)): ?>
                        <input type="hidden" name="product_cat_filter" value="<?php echo esc_attr($category_filter_slug); ?>">
                    <?php endif; ?>

                    <div class="vdb-search-control">
                        <label for="vdb-store-search" class="screen-reader-text"><?php esc_html_e('Search this store', 'vendor-dashboard'); ?></label>
                        <input type="search" id="vdb-store-search" name="s" value="<?php echo esc_attr($current_search_term); ?>" placeholder="<?php esc_attr_e('Search products in this store...', 'vendor-dashboard'); ?>">
                    </div>

                    <div class="vdb-sort-control">
                        <label for="vdb-sortby"><?php esc_html_e('Sort by:', 'vendor-dashboard'); ?></label>
                        <select name="sortby" id="vdb-sortby" onchange="this.form.submit()">
                            <option value="newest" <?php selected($current_sort_by, 'newest'); ?>><?php esc_html_e('Newest arrivals', 'vendor-dashboard'); ?></option>
                            <option value="popularity_sales" <?php selected($current_sort_by, 'popularity_sales'); ?>><?php esc_html_e('Popularity (Sales)', 'vendor-dashboard'); ?></option>
                            <option value="rating" <?php selected($current_sort_by, 'rating'); ?>><?php esc_html_e('Average rating', 'vendor-dashboard'); ?></option>
                            <option value="price_asc" <?php selected($current_sort_by, 'price_asc'); ?>><?php esc_html_e('Price: Low to High', 'vendor-dashboard'); ?></option>
                            <option value="price_desc" <?php selected($current_sort_by, 'price_desc'); ?>><?php esc_html_e('Price: High to Low', 'vendor-dashboard'); ?></option>
                            <option value="title_asc" <?php selected($current_sort_by, 'title_asc'); ?>><?php esc_html_e('Name: A to Z', 'vendor-dashboard'); ?></option>
                            <option value="title_desc" <?php selected($current_sort_by, 'title_desc'); ?>><?php esc_html_e('Name: Z to A', 'vendor-dashboard'); ?></option>
                        </select>
                    </div>
                    
                    <button type="submit" class="vdb-filter-submit-button button"><?php esc_html_e('Go', 'vendor-dashboard'); // Button for search, sort is onchange ?></button>
                </form>
            </div>

            <div id="vdb-store-products" class="woocommerce" role="main">
                <?php
                $args = array(
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'author'         => $vendor_id,
                    'posts_per_page' => wc_get_loop_prop( 'per_page', get_option( 'posts_per_page', 12 ) ),
                    'paged'          => $paged,
                );

                if (!empty($category_filter_slug)) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => $category_filter_slug
                        )
                    );
                }

                if (!empty($current_search_term)) {
                    $args['s'] = $current_search_term;
                }

                switch ($current_sort_by) {
                    case 'price_asc':
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = '_price';
                        $args['order'] = 'ASC';
                        break;
                    case 'price_desc':
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = '_price';
                        $args['order'] = 'DESC';
                        break;
                    case 'popularity_sales': // Using total_sales for popularity
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = 'total_sales';
                        $args['order'] = 'DESC';
                        break;
                    case 'rating':
                        $args['orderby'] = 'meta_value_num'; // Can also use 'comment_count' or a dedicated rating meta
                        $args['meta_key'] = '_wc_average_rating'; // WooCommerce average rating
                        $args['order'] = 'DESC';
                        break;
                    case 'title_asc':
                        $args['orderby'] = 'title';
                        $args['order'] = 'ASC';
                        break;
                    case 'title_desc':
                        $args['orderby'] = 'title';
                        $args['order'] = 'DESC';
                        break;
                    case 'newest':
                    default:
                        $args['orderby'] = 'date';
                        $args['order'] = 'DESC';
                        break;
                }

                $vendor_products_query = new WP_Query( $args );

                if ( $vendor_products_query->have_posts() ) : ?>
                    <h2 class="vdb-products-heading"><?php printf( esc_html__( 'Products from %s', 'vendor-dashboard' ), esc_html( $brand_name ) ); ?></h2>
                    <?php woocommerce_product_loop_start(); ?>
                        <?php while ( $vendor_products_query->have_posts() ) : $vendor_products_query->the_post(); ?>
                            <?php wc_get_template_part( 'content', 'product' ); ?>
                        <?php endwhile; ?>
                    <?php woocommerce_product_loop_end(); ?>
                    <?php
                        // Pagination
                        $pagination_base = vdb_get_vendor_store_url($vendor_id) . 'page/%#%/';
                        $pagination_format = ''; // Handled by page/%#%/ in base

                        // Build array of current query args to persist in pagination links
                        $current_pagination_query_args = array();
                        if (!empty($current_sort_by) && $current_sort_by !== 'newest') {
                            $current_pagination_query_args['sortby'] = $current_sort_by;
                        }
                        if (!empty($current_search_term)) {
                            $current_pagination_query_args['s'] = $current_search_term;
                        }
                        if (!empty($category_filter_slug)) {
                            $current_pagination_query_args['product_cat_filter'] = $category_filter_slug;
                        }
                        
                        echo '<nav class="woocommerce-pagination vdb-store-pagination">';
                        echo paginate_links( apply_filters( 'woocommerce_pagination_args', array(
                            'base'         => esc_url_raw(add_query_arg($current_pagination_query_args, $pagination_base)),
                            'format'       => $pagination_format,
                            'add_args'     => false, // We've added them to the base
                            'current'      => max( 1, $paged ),
                            'total'        => $vendor_products_query->max_num_pages,
                            'prev_text'    => is_rtl() ? '→' : '←',
                            'next_text'    => is_rtl() ? '←' : '→',
                            'type'         => 'list',
                            'end_size'     => 3,
                            'mid_size'     => 3,
                        ) ) );
                        echo '</nav>';
                    ?>
                <?php else : ?>
                    <p class="woocommerce-info">
                        <?php
                        if (!empty($current_search_term)) {
                            printf(esc_html__('No products found matching your search term "%s" in this store.', 'vendor-dashboard'), esc_html($current_search_term));
                        } elseif (!empty($category_filter_slug)) {
                            $cat = get_term_by('slug', $category_filter_slug, 'product_cat');
                            printf(esc_html__('No products found in the category "%s" for this vendor.', 'vendor-dashboard'), esc_html($cat ? $cat->name : $category_filter_slug));
                        } else {
                            esc_html_e('No products were found for this vendor.', 'vendor-dashboard');
                        }
                        ?>
                    </p>
                <?php endif; wp_reset_postdata(); ?>
            </div>
        </div>
    </div> <?php // End .vdb-store-body-wrap ?>

    <?php if ( ! empty( $vendor_shipping_policy ) || ! empty( $vendor_return_policy ) ) : ?>
        <div class="vdb-store-policies-section vdb-store-section">
            <h2 class="vdb-section-title"><?php esc_html_e( 'Store Policies', 'vendor-dashboard' ); ?></h2>
            <div class="vdb-policies-grid">
                <?php if ( ! empty( $vendor_shipping_policy ) ) : ?>
                    <div class="vdb-policy-item">
                        <h3 class="vdb-policy-title"><?php esc_html_e( 'Shipping Policy', 'vendor-dashboard' ); ?></h3>
                        <div class="vdb-policy-content">
                            <?php echo wp_kses_post( wpautop( $vendor_shipping_policy ) ); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $vendor_return_policy ) ) : ?>
                     <div class="vdb-policy-item">
                        <h3 class="vdb-policy-title"><?php esc_html_e( 'Return & Refund Policy', 'vendor-dashboard' ); ?></h3>
                        <div class="vdb-policy-content">
                            <?php echo wp_kses_post( wpautop( $vendor_return_policy ) ); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div> <?php // End .vdb-public-store-wrap ?>

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer( 'shop' ); ?>