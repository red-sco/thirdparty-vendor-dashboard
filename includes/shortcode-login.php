<?php
/**
 * Handles the [vendor_dashboard] Shortcode for Frontend Login & Dashboard Display.
 * Includes Vendor Registration Form.
 */

if ( ! defined( 'WPINC' ) ) { die; }

/** Register the shortcode */
function vdb_register_shortcode() { add_shortcode( 'vendor_dashboard', 'vdb_render_login_shortcode' ); }
add_action( 'init', 'vdb_register_shortcode' );

/** Render the shortcode content */
function vdb_render_login_shortcode( $atts ) {
    ob_start();

    $current_action = isset( $_GET['vdb_action'] ) ? sanitize_key( $_GET['vdb_action'] ) : 'login';

    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        if ( in_array( VENDOR_DASHBOARD_ROLE, (array) $current_user->roles ) ) {
            // --- Vendor is logged in ---
            $vendor_id = $current_user->ID;
            $brand_name = vdb_get_vendor_brand_name( $vendor_id ) ?: $current_user->display_name;
            $current_section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'overview';
            $dashboard_base_url = get_permalink();
            $overview_url = esc_url( add_query_arg( 'section', 'overview', $dashboard_base_url ) );
            $products_url = esc_url( add_query_arg( 'section', 'products', $dashboard_base_url ) );
            $orders_url = esc_url( add_query_arg( 'section', 'orders', $dashboard_base_url ) );
            $coupons_url = esc_url( add_query_arg( 'section', 'coupons', $dashboard_base_url ) );
            $earnings_url = esc_url( add_query_arg( 'section', 'earnings', $dashboard_base_url ) ); 
            $profile_settings_url = esc_url( add_query_arg( 'section', 'profile-settings', $dashboard_base_url ) );


            ?>
            <div class="vdb-dashboard-container">
                <div class="vdb-header">
                     <h2><?php printf( esc_html__( '%s Dashboard', 'vendor-dashboard' ), esc_html( $brand_name ) ); ?></h2>
                     <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="button vdb-logout-button"><?php esc_html__('Log Out', 'vendor-dashboard'); ?></a>
                </div>
                <nav class="vdb-navigation-links">
                    <a href="<?php echo $overview_url; ?>" class="<?php echo $current_section === 'overview' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Overview', 'vendor-dashboard' ); ?></a>
                    <a href="<?php echo $products_url; ?>" class="<?php echo $current_section === 'products' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Products', 'vendor-dashboard' ); ?></a>
                    <a href="<?php echo $orders_url; ?>" class="<?php echo $current_section === 'orders' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Orders', 'vendor-dashboard' ); ?></a>
                    <a href="<?php echo $coupons_url; ?>" class="<?php echo $current_section === 'coupons' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Coupons', 'vendor-dashboard' ); ?></a>
                    <a href="<?php echo $earnings_url; ?>" class="<?php echo $current_section === 'earnings' ? 'is-active' : ''; ?>"><?php esc_html_e( 'My Earnings', 'vendor-dashboard' ); ?></a> 
                    <a href="<?php echo $profile_settings_url; ?>" class="<?php echo $current_section === 'profile-settings' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Profile Settings', 'vendor-dashboard' ); ?></a>
                </nav>
                <div class="vdb-content-area vdb-content-<?php echo esc_attr($current_section); ?>">
                    <?php
                    switch ( $current_section ) {
                        case 'products':
                            echo '<h3>' . esc_html__( 'Products', 'vendor-dashboard' ) . '</h3>';
                            vdb_render_product_editor_form();
                            vdb_render_product_list( $vendor_id );
                            break;
                        case 'orders':
                            if ( function_exists('vdb_render_order_list') ) {
                                vdb_render_order_list( $vendor_id );
                            } else {
                                echo '<h3>' . esc_html__( 'Orders', 'vendor-dashboard' ) . '</h3>';
                                echo '<p><em>' . esc_html__( 'Error: Order rendering function not found.', 'vendor-dashboard') . '</em></p>';
                            }
                            break;
                        case 'coupons':
                            echo '<h3>' . esc_html__( 'Manage Coupons', 'vendor-dashboard' ) . '</h3>';
                            if ( function_exists('vdb_render_coupon_editor_form') ) { vdb_render_coupon_editor_form(); }
                            if ( function_exists('vdb_render_coupon_list') ) { vdb_render_coupon_list( $vendor_id ); }
                            else { echo '<p><em>' . esc_html__( 'Error: Coupon rendering functions not found.', 'vendor-dashboard') . '</em></p>';}
                            break;
                        case 'earnings': 
                            if ( function_exists('vdb_render_vendor_earnings_section') ) {
                                vdb_render_vendor_earnings_section( $vendor_id );
                            } else {
                                echo '<h3>' . esc_html__( 'My Earnings', 'vendor-dashboard' ) . '</h3>';
                                echo '<p><em>' . esc_html__( 'Error: Earnings display function not found.', 'vendor-dashboard') . '</em></p>';
                            }
                            break;
                        case 'profile-settings':
                            echo '<h3>' . esc_html__( 'Profile Settings', 'vendor-dashboard' ) . '</h3>';
                            if ( function_exists('vdb_render_profile_settings_form') ) { vdb_render_profile_settings_form( $vendor_id ); }
                            else { echo '<p><em>' . esc_html__( 'Error: Profile settings form function not found.', 'vendor-dashboard') . '</em></p>'; }
                            break;
                        case 'overview':
                        default:
                            echo '<h3>' . esc_html__( 'Overview', 'vendor-dashboard' ) . '</h3>';
                            $sales_7_days = vdb_get_vendor_sales_summary( $vendor_id, 7 );
                            $sales_30_days = vdb_get_vendor_sales_summary( $vendor_id, 30 );
                            $order_status_counts = vdb_get_vendor_order_status_counts( $vendor_id, array('wc-processing', 'wc-on-hold') );
                            $pending_products = vdb_get_vendor_pending_product_count( $vendor_id );
                            $low_stock_count = vdb_get_vendor_low_stock_product_count( $vendor_id );
                            $recent_orders = vdb_get_vendor_recent_orders( $vendor_id, 5 );
                            $brand_logo_id = get_user_meta( $vendor_id, 'vdb_brand_logo_id', true );
                            $public_store_url = vdb_get_vendor_store_url($vendor_id); 
                            ?>
                            <div class="vdb-overview-widgets">
                                <?php
                                if (function_exists('vdb_render_notification_center')) {
                                    vdb_render_notification_center($vendor_id);
                                }
                                ?>

                                <?php if ( $brand_logo_id ) : $brand_logo_url_medium = wp_get_attachment_image_url( $brand_logo_id, 'medium' ); if ($brand_logo_url_medium) : ?>
                                    <div class="vdb-widget vdb-widget-brand-logo"><h4><?php echo esc_html($brand_name); ?></h4><div class="vdb-brand-logo-container"><img src="<?php echo esc_url($brand_logo_url_medium); ?>" alt="<?php echo esc_attr($brand_name); ?> Logo"></div><p class="vdb-widget-actions" style="text-align:center; margin-top:10px; padding-top:0; border-top:none;"><a href="<?php echo esc_url($profile_settings_url); ?>" class="vdb-widget-link"><?php esc_html_e('Edit Profile', 'vendor-dashboard'); ?></a></p></div>
                                <?php endif; endif; ?>
                                
                                <?php if (function_exists('vdb_render_earnings_summary_widget_content')): ?>
                                <div class="vdb-widget vdb-widget-earnings-summary">
                                    <h4><?php esc_html_e('Earnings Summary', 'vendor-dashboard'); ?></h4>
                                    <?php vdb_render_earnings_summary_widget_content($vendor_id); ?>
                                </div>
                                <?php endif; ?>


                                <?php if ( $public_store_url ) : ?>
                                <div class="vdb-widget vdb-widget-public-store-link">
                                    <h4><?php esc_html_e('Your Public Store', 'vendor-dashboard'); ?></h4>
                                    <p><?php esc_html_e('View your public store page where customers can see your profile and products.', 'vendor-dashboard'); ?></p>
                                    <p><a href="<?php echo esc_url($public_store_url); ?>" class="button vdb-button" target="_blank"><?php esc_html_e('View Store Page', 'vendor-dashboard'); ?> <span class="dashicons dashicons-external"></span></a></p>
                                    <small><?php esc_html_e('Share this link with your customers!', 'vendor-dashboard'); ?></small>
                                </div>
                                <?php endif; ?>

                                <div class="vdb-widget vdb-widget-sales"><h4><?php esc_html_e('Sales Performance', 'vendor-dashboard'); ?></h4><div class="vdb-widget-grid"><div><span class="vdb-kpi-value"><?php echo wc_price($sales_7_days['total_sales']); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Last 7 Days', 'vendor-dashboard'); ?></span></div><div><span class="vdb-kpi-value"><?php echo esc_html($sales_7_days['order_count']); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Orders (7 Days)', 'vendor-dashboard'); ?></span></div><div><span class="vdb-kpi-value"><?php echo wc_price($sales_30_days['total_sales']); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Last 30 Days', 'vendor-dashboard'); ?></span></div><div><span class="vdb-kpi-value"><?php echo esc_html($sales_30_days['order_count']); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Orders (30 Days)', 'vendor-dashboard'); ?></span></div></div><small><?php esc_html_e('Sales totals reflect the value of your items in completed/processing orders.', 'vendor-dashboard'); ?></small></div>
                                <div class="vdb-widget vdb-widget-orders"><h4><?php esc_html_e('Order Summary', 'vendor-dashboard'); ?></h4><p><span class="vdb-kpi-value"><?php echo esc_html( $order_status_counts['wc-processing'] ?? 0 ); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Processing Orders', 'vendor-dashboard'); ?></span><a href="<?php echo $orders_url; ?>" class="vdb-widget-link"><?php esc_html_e('View Orders', 'vendor-dashboard'); ?> →</a></p><?php if (isset($order_status_counts['wc-on-hold']) && $order_status_counts['wc-on-hold'] > 0): ?><p><span class="vdb-kpi-value"><?php echo esc_html( $order_status_counts['wc-on-hold'] ); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Orders On Hold', 'vendor-dashboard'); ?></span></p><?php endif; ?></div>
                                <div class="vdb-widget vdb-widget-products"><h4><?php esc_html_e('Product Status', 'vendor-dashboard'); ?></h4><p><span class="vdb-kpi-value"><?php echo esc_html( $low_stock_count ); ?></span><span class="vdb-kpi-label"><?php printf(esc_html__('Products Low Stock (< %d)', 'vendor-dashboard'), VDB_LOW_STOCK_THRESHOLD); ?></span><a href="<?php echo $products_url; ?>" class="vdb-widget-link"><?php esc_html_e('View Products', 'vendor-dashboard'); ?> →</a></p><?php if ($pending_products > 0): ?><p><span class="vdb-kpi-value"><?php echo esc_html( $pending_products ); ?></span><span class="vdb-kpi-label"><?php esc_html_e('Products Pending Review', 'vendor-dashboard'); ?></span></p><?php endif; ?><p class="vdb-widget-actions"><a href="<?php echo esc_url( add_query_arg( 'section', 'products', $dashboard_base_url ) ); ?>#add-new" class="button vdb-add-new-product-overview"><?php esc_html_e('Add New Product', 'vendor-dashboard'); ?></a></p></div>
                                <?php if (!empty($recent_orders)): ?> <div class="vdb-widget vdb-widget-recent-orders"><h4><?php esc_html_e('Recent Orders', 'vendor-dashboard'); ?></h4><ul class="vdb-recent-orders-list"><?php foreach ($recent_orders as $order_data): ?><li><span class="order-number"><?php printf(esc_html__('#%s', 'vendor-dashboard'), esc_html($order_data['number'])); ?></span><span class="order-date"><?php echo esc_html($order_data['date']); ?></span><span class="order-status"><?php echo esc_html($order_data['status']); ?></span><span class="order-total"><?php echo wp_kses_post($order_data['total']); ?></span></li><?php endforeach; ?></ul><a href="<?php echo $orders_url; ?>" class="vdb-widget-link"><?php esc_html_e('View All Orders', 'vendor-dashboard'); ?> →</a></div><?php endif; ?>
                            </div>
                            <?php
                            break;
                    }
                    ?>
                </div>
            </div>
            <?php
        } else {
             echo '<div class="vendor-dashboard-error"><p>' . esc_html__( 'This dashboard is for registered vendors only.', 'vendor-dashboard' ) . '</p><p><a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">' . esc_html__('Log Out', 'vendor-dashboard') . '</a></p></div>';
         }
    } else { // User not logged in - Show Login or Registration Form
        
        $login_error_message = '';
        if ( isset( $_GET['vdb_login_error'] ) ) {
            $error_code = sanitize_key( $_GET['vdb_login_error'] );
            if ( $error_code === 'pending_approval' ) {
                $login_error_message = __( 'Your vendor application is pending approval. You will be notified by email once it has been reviewed.', 'vendor-dashboard' );
            } elseif ( $error_code === 'denied_access' ) {
                $login_error_message = __( 'Your vendor application was not approved. Please contact support if you believe this is an error.', 'vendor-dashboard' );
            }
        }

        // --- LOGIN FORM ---
        if ( $current_action === 'login' ) {
            $login_error_submit = null; 
            if ( isset( $_POST['vdb_login_nonce'] ) && wp_verify_nonce( sanitize_text_field($_POST['vdb_login_nonce']), 'vdb_frontend_login' ) ) { 
                $username = isset( $_POST['vdb_username'] ) ? sanitize_user( $_POST['vdb_username'] ) : ''; 
                $brand_name = isset( $_POST['vdb_brand_name'] ) ? sanitize_text_field( $_POST['vdb_brand_name'] ) : ''; 
                $password = isset( $_POST['vdb_password'] ) ? $_POST['vdb_password'] : ''; 
                $rememberme = isset( $_POST['vdb_rememberme'] ); 
                if ( empty( $username ) || empty( $brand_name ) || empty( $password ) ) { 
                    $login_error_submit = __( 'All fields required for login.', 'vendor-dashboard' ); 
                } else { 
                    $creds = array('user_login' => $username, 'user_password' => $password, 'remember' => $rememberme); 
                    $user = wp_signon( $creds, is_ssl() ); 
                    if ( is_wp_error( $user ) ) { 
                        $login_error_submit = $user->get_error_message(); 
                    } else { 
                        $stored_brand_name = vdb_get_vendor_brand_name( $user->ID );
                        if ( strtolower( trim( $stored_brand_name ) ) !== strtolower( trim( $brand_name ) ) ) {
                            $reg_status = get_user_meta($user->ID, VDB_REGISTRATION_STATUS_META_KEY, true);
                            if (in_array(VENDOR_DASHBOARD_ROLE, (array) $user->roles) || $reg_status === 'approved') {
                                wp_logout(); 
                                $login_error_submit = __( 'Login failed. Brand Name mismatch.', 'vendor-dashboard' ); 
                            } else {
                                wp_set_current_user( $user->ID ); 
                                wp_safe_redirect( get_permalink() ); 
                                exit;
                            }
                        } else {
                            wp_set_current_user( $user->ID ); 
                            wp_safe_redirect( get_permalink() ); 
                            exit; 
                        }
                    } 
                } 
            }
            ?>
            <div class="vendor-dashboard-login-form">
                <h2><?php esc_html_e( 'Vendor Login', 'vendor-dashboard' ); ?></h2>
                <?php if ( ! empty( $login_error_message ) ) : ?>
                    <div class="vdb-login-error vdb-profile-notice loading" style="display:block;"><?php echo esc_html( $login_error_message ); ?></div>
                <?php endif; ?>
                <?php if ( ! empty( $login_error_submit ) ) : ?>
                    <div class="vdb-login-error vdb-profile-notice error" style="display:block;"><?php echo wp_kses_post( $login_error_submit ); ?></div>
                <?php endif; ?>
                <?php $reg_success_msg = get_transient('vdb_registration_success'); if ($reg_success_msg): ?>
                    <div class="vdb-profile-notice success" style="display:block;"><?php echo esc_html($reg_success_msg); ?></div>
                <?php delete_transient('vdb_registration_success'); endif; ?>

                <form name="vdbloginform" id="vdbloginform" action="<?php echo esc_url( add_query_arg('vdb_action', 'login', get_permalink()) ); ?>" method="post">
                    <p><label><?php esc_html_e('Username','vendor-dashboard');?><br /><input type="text" name="vdb_username" class="input" value="<?php echo isset($_POST['vdb_username']) ? esc_attr($_POST['vdb_username']) : ''; ?>" required /></label></p>
                    <p><label><?php esc_html_e('Brand Name','vendor-dashboard');?><br /><input type="text" name="vdb_brand_name" class="input" value="<?php echo isset($_POST['vdb_brand_name']) ? esc_attr($_POST['vdb_brand_name']) : ''; ?>" required /></label></p>
                    <p><label><?php esc_html_e('Password','vendor-dashboard');?><br /><input type="password" name="vdb_password" class="input" value="" autocomplete="current-password" required /></label></p>
                    <p class="login-remember"><label><input name="vdb_rememberme" type="checkbox" value="forever" /> <?php esc_html_e('Remember Me','vendor-dashboard');?></label></p>
                    <p class="login-submit"><input type="submit" name="wp-submit" class="button button-primary" value="<?php esc_attr_e('Log In', 'vendor-dashboard'); ?>" />
                    <?php wp_nonce_field( 'vdb_frontend_login', 'vdb_login_nonce' ); ?>
                    </p>
                </form>
                <p class="vdb-form-toggle">
                    <?php esc_html_e("Don't have an account?", 'vendor-dashboard'); ?> 
                    <a href="<?php echo esc_url( add_query_arg( 'vdb_action', 'register', get_permalink() ) ); ?>"><?php esc_html_e('Register as a Vendor', 'vendor-dashboard'); ?></a>
                </p>
            </div>
            <?php
        } elseif ( $current_action === 'register' ) {
            // --- REGISTRATION FORM ---
            $reg_errors = get_transient('vdb_registration_error');
            ?>
            <div class="vendor-dashboard-register-form vendor-dashboard-login-form"> <?php // Added vendor-dashboard-login-form class for similar styling ?>
                <h2><?php esc_html_e( 'Register as a Vendor', 'vendor-dashboard' ); ?></h2>
                <?php if ( $reg_errors ): ?>
                    <div class="vdb-login-error vdb-profile-notice error" style="display:block;">
                        <?php foreach ( (array) $reg_errors as $error ): ?>
                            <p><?php echo esc_html( $error ); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php delete_transient('vdb_registration_error'); endif; ?>

                <form name="vdbregisterform" id="vdbregisterform" action="<?php echo esc_url( add_query_arg('vdb_action', 'register', get_permalink()) ); ?>" method="post">
                    <input type="hidden" name="vdb_action" value="register_vendor_frontend">
                    <?php wp_nonce_field( 'vdb_frontend_register_action', 'vdb_register_frontend_nonce' ); ?>

                    <p><label for="vdb_reg_username"><?php esc_html_e('Username', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="text" name="vdb_reg_username" id="vdb_reg_username" class="input" value="<?php echo isset($_POST['vdb_reg_username']) ? esc_attr($_POST['vdb_reg_username']) : ''; ?>" required></p>

                    <p><label for="vdb_reg_email"><?php esc_html_e('Email Address', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="email" name="vdb_reg_email" id="vdb_reg_email" class="input" value="<?php echo isset($_POST['vdb_reg_email']) ? esc_attr($_POST['vdb_reg_email']) : ''; ?>" required></p>
                    
                    <p><label for="vdb_reg_password"><?php esc_html_e('Password', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="password" name="vdb_reg_password" id="vdb_reg_password" class="input" required autocomplete="new-password"></p>

                    <p><label for="vdb_reg_confirm_password"><?php esc_html_e('Confirm Password', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="password" name="vdb_reg_confirm_password" id="vdb_reg_confirm_password" class="input" required autocomplete="new-password"></p>

                    <p><label for="vdb_reg_brand_name"><?php esc_html_e('Brand Name / Store Name', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="text" name="vdb_reg_brand_name" id="vdb_reg_brand_name" class="input" value="<?php echo isset($_POST['vdb_reg_brand_name']) ? esc_attr($_POST['vdb_reg_brand_name']) : ''; ?>" required></p>

                    <p><label for="vdb_reg_first_name"><?php esc_html_e('First Name', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <input type="text" name="vdb_reg_first_name" id="vdb_reg_first_name" class="input" value="<?php echo isset($_POST['vdb_reg_first_name']) ? esc_attr($_POST['vdb_reg_first_name']) : ''; ?>" required></p>

                    <p><label for="vdb_reg_last_name"><?php esc_html_e('Last Name (Optional)', 'vendor-dashboard'); ?></label>
                    <input type="text" name="vdb_reg_last_name" id="vdb_reg_last_name" class="input" value="<?php echo isset($_POST['vdb_reg_last_name']) ? esc_attr($_POST['vdb_reg_last_name']) : ''; ?>"></p>

                    <p><label for="vdb_reg_store_description"><?php esc_html_e('Brief Description of Your Store/Products', 'vendor-dashboard'); ?> <span class="required">*</span></label>
                    <textarea name="vdb_reg_store_description" id="vdb_reg_store_description" class="input" rows="4" required><?php echo isset($_POST['vdb_reg_store_description']) ? esc_textarea($_POST['vdb_reg_store_description']) : ''; ?></textarea></p>

                    <p><label for="vdb_reg_store_website"><?php esc_html_e('Link to Your Existing Website (Optional)', 'vendor-dashboard'); ?></label>
                    <input type="url" name="vdb_reg_store_website" id="vdb_reg_store_website" class="input" value="<?php echo isset($_POST['vdb_reg_store_website']) ? esc_attr($_POST['vdb_reg_store_website']) : ''; ?>" placeholder="https://example.com"></p>
                    
                    <p><label for="vdb_reg_admin_notes"><?php esc_html_e('Anything Else Admin Should Know? (Optional)', 'vendor-dashboard'); ?></label>
                    <textarea name="vdb_reg_admin_notes" id="vdb_reg_admin_notes" class="input" rows="3"><?php echo isset($_POST['vdb_reg_admin_notes']) ? esc_textarea($_POST['vdb_reg_admin_notes']) : ''; ?></textarea></p>

                    <p class="login-submit">
                        <input type="submit" name="vdb_reg_submit" class="button button-primary" value="<?php esc_attr_e('Register', 'vendor-dashboard'); ?>" />
                    </p>
                </form>
                 <p class="vdb-form-toggle">
                    <?php esc_html_e('Already have an account?', 'vendor-dashboard'); ?> 
                    <a href="<?php echo esc_url( add_query_arg( 'vdb_action', 'login', get_permalink() ) ); ?>"><?php esc_html_e('Log In', 'vendor-dashboard'); ?></a>
                </p>
            </div>
            <?php
        }
    }
    return ob_get_clean();
}
?>