<?php
/**
 * Handles the Admin Menu and Page for Vendor Dashboard Settings.
 * FINAL VERSION - Includes Registration Form & Working Vendor List
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

/** Add the top-level admin menu page. */
function vdb_add_admin_menu() {
    add_menu_page(
        __( 'Vendor Admin Settings', 'vendor-dashboard' ), 
        __( 'Vendor Admin', 'vendor-dashboard' ),         
        'manage_options',                                
        'vendor-admin-settings',                         
        'vdb_render_admin_page',                         
        'dashicons-store',                               
        58                                               
    );
    // Vendor Registrations Submenu will be added by admin-vendor-registrations.php
}
add_action( 'admin_menu', 'vdb_add_admin_menu' );

/** Register settings for the admin page */
function vdb_register_admin_settings() {
    register_setting( 'vdb_admin_settings_group', 'vdb_commission_rate_percentage', array(
        'type'              => 'number',
        'sanitize_callback' => 'vdb_sanitize_commission_rate',
        'default'           => defined('VDB_COMMISSION_RATE') ? VDB_COMMISSION_RATE * 100 : 10, // Store as percentage
    ) );

    add_settings_section(
        'vdb_commission_settings_section',
        __( 'Commission Settings', 'vendor-dashboard' ),
        null, // No callback needed for section intro
        'vendor-admin-settings' // Page slug
    );

    add_settings_field(
        'vdb_commission_rate_field',
        __( 'Vendor Commission Rate (%)', 'vendor-dashboard' ),
        'vdb_render_commission_rate_field',
        'vendor-admin-settings', // Page slug
        'vdb_commission_settings_section' // Section ID
    );
}
add_action( 'admin_init', 'vdb_register_admin_settings' );

/** Sanitize commission rate input */
function vdb_sanitize_commission_rate( $input ) {
    $input = floatval( $input );
    if ( $input < 0 ) {
        $input = 0;
    }
    if ( $input > 100 ) { // Assuming max 100%
        $input = 100;
    }
    return $input;
}

/** Render commission rate field */
function vdb_render_commission_rate_field() {
    $option = get_option( 'vdb_commission_rate_percentage', defined('VDB_COMMISSION_RATE') ? VDB_COMMISSION_RATE * 100 : 10 );
    ?>
    <input type="number" name="vdb_commission_rate_percentage" value="<?php echo esc_attr( $option ); ?>" min="0" max="100" step="0.01" />
    <p class="description"><?php esc_html_e( 'Enter the commission percentage the platform takes from vendor sales (e.g., 10 for 10%).', 'vendor-dashboard' ); ?></p>
    <?php
}


/** Handle Vendor Registration Form Submission. */
function vdb_handle_vendor_registration() {
     if ( ! isset( $_POST['vdb_register_nonce'], $_POST['vdb_action'] ) || $_POST['vdb_action'] !== 'register_vendor' ) { return; }
     if ( ! wp_verify_nonce( sanitize_text_field($_POST['vdb_register_nonce']), 'vdb_register_vendor_action' ) ) { wp_die('Security check failed.'); }
     if ( ! current_user_can('manage_options') ) { wp_die('Permission denied.'); }

     $username = isset( $_POST['vdb_username'] ) ? sanitize_user( $_POST['vdb_username'], true ) : '';
     $email = isset( $_POST['vdb_email'] ) ? sanitize_email( $_POST['vdb_email'] ) : '';
     $first_name = isset( $_POST['vdb_first_name'] ) ? sanitize_text_field( $_POST['vdb_first_name'] ) : '';
     $brand_name = isset( $_POST['vdb_brand_name'] ) ? sanitize_text_field( $_POST['vdb_brand_name'] ) : '';
     $password = isset( $_POST['vdb_password'] ) ? $_POST['vdb_password'] : ''; 
     $confirm_pwd = isset( $_POST['vdb_confirm_password'] ) ? $_POST['vdb_confirm_password'] : '';
     $errors = new WP_Error();

     if ( empty($username) ) $errors->add('err','Username required.');
     if ( !validate_username($username) ) $errors->add('err','Invalid username.');
     if ( username_exists($username) ) $errors->add('err','Username already exists.');
     if ( empty($email) ) $errors->add('err','Email required.');
     if ( !is_email($email) ) $errors->add('err','Invalid email.');
     if ( email_exists($email) ) $errors->add('err','Email already registered.');
     if ( empty($brand_name) ) $errors->add('err','Brand Name required.');
     if ( empty($password) ) $errors->add('err','Password required.');
     if ( $password !== $confirm_pwd ) $errors->add('err','Passwords do not match.');

     if ( $errors->has_errors() ) {
         set_transient('vdb_admin_errors', $errors->get_error_messages(), 60);
         wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_error=registration'));
         exit;
     }

     $user_data = array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => $password,
        'first_name' => $first_name,
        'display_name' => $brand_name, 
        'role'       => VENDOR_DASHBOARD_ROLE 
     );
     $user_id = wp_insert_user($user_data);

     if ( is_wp_error($user_id) ) {
         set_transient('vdb_admin_errors', $user_id->get_error_messages(), 60);
         wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_error=registration'));
         exit;
     }

     update_user_meta($user_id, 'vdb_brand_name', $brand_name);
     // For admin-created vendors, mark as approved directly
     update_user_meta($user_id, VDB_REGISTRATION_STATUS_META_KEY, 'approved');


     wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_success=registered'));
     exit;
}
add_action( 'admin_init', 'vdb_handle_vendor_registration' );

/** Handle Vendor Deletion. */
function vdb_handle_vendor_deletion() {
     if ( ! isset( $_GET['action'], $_GET['vendor_id'], $_GET['_wpnonce'] ) || $_GET['action'] !== 'delete_vendor' ) { return; } $vendor_id = absint($_GET['vendor_id']); $nonce = sanitize_text_field($_GET['_wpnonce']); if ( ! wp_verify_nonce($nonce, 'vdb_delete_vendor_' . $vendor_id) ) { wp_die('Security check failed.'); } if ( ! current_user_can('delete_users') ) { wp_die('Permission denied.'); } $user = get_userdata($vendor_id); if ( !$user || !in_array(VENDOR_DASHBOARD_ROLE, (array)$user->roles) ) { wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_error=delete_invalid_user')); exit; } require_once(ABSPATH . 'wp-admin/includes/user.php'); if ( wp_delete_user($vendor_id, get_current_user_id()) ) { wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_success=deleted')); } else { wp_safe_redirect(admin_url('admin.php?page=vendor-admin-settings&vd_error=delete_failed')); } exit;
}
add_action( 'admin_init', 'vdb_handle_vendor_deletion' );

/** Handle Product Assignment Form Submission. */
function vdb_handle_product_assignment() {
    if ( ! isset( $_POST['vdb_assign_nonce'], $_POST['vdb_action'] ) || $_POST['vdb_action'] !== 'assign_product' ) { return; } $vendor_id = isset( $_POST['vdb_vendor_id'] ) ? absint($_POST['vdb_vendor_id']) : 0; $product_id = isset( $_POST['vdb_product_id'] ) ? absint($_POST['vdb_product_id']) : 0; if ( ! $vendor_id || ! isset($_POST['vdb_assign_nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['vdb_assign_nonce']), 'vdb_assign_product_' . $vendor_id ) ) { set_transient('vdb_admin_errors', array(__('Security check failed. Please try again.', 'vendor-dashboard')), 60); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_error=nonce' ) ); exit; } if ( ! current_user_can( 'edit_others_posts' ) ) { set_transient('vdb_admin_errors', array(__('You do not have permission to assign products.', 'vendor-dashboard')), 60); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_error=capability' ) ); exit; } $errors = new WP_Error(); if ( empty( $product_id ) ) $errors->add( 'err', __( 'Product ID cannot be empty.', 'vendor-dashboard' ) ); $vendor_user = get_userdata( $vendor_id ); if ( ! $vendor_user || ! in_array( VENDOR_DASHBOARD_ROLE, (array) $vendor_user->roles ) ) $errors->add( 'err', __( 'Invalid vendor specified.', 'vendor-dashboard' ) ); $product = get_post( $product_id ); if ( ! $product ) { $errors->add( 'err', sprintf(__( 'Product with ID %d not found.', 'vendor-dashboard' ), $product_id ) ); } elseif ( 'product' !== $product->post_type ) { $errors->add( 'err', sprintf(__( 'Post ID %d is not a Product.', 'vendor-dashboard' ), $product_id ) ); } elseif ( $product->post_author == $vendor_id ) { set_transient( 'vdb_admin_notice', sprintf(__('Product %d is already assigned to this vendor.', 'vendor-dashboard'), $product_id), 60 ); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_notice=already_assigned&product=' . $product_id . '&vendor=' . $vendor_id ) ); exit; } if ( $errors->has_errors() ) { set_transient( 'vdb_admin_errors', $errors->get_error_messages(), 60 ); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_error=validation&vendor=' . $vendor_id ) ); exit; } $update_args = array( 'ID' => $product_id, 'post_author' => $vendor_id ); $updated = wp_update_post( $update_args, true ); if ( is_wp_error( $updated ) ) { set_transient( 'vdb_admin_errors', $updated->get_error_messages(), 60 ); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_error=update_failed_wp_error&vendor=' . $vendor_id ) ); exit; } elseif ( $updated === 0 ) { set_transient( 'vdb_admin_errors', array(__('Update returned 0, assignment might have failed silently.', 'vendor-dashboard')), 60 ); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_error=update_failed_zero&vendor=' . $vendor_id ) ); exit; } $product_title = get_the_title($product_id); $vendor_name = $vendor_user->display_name; $success_msg = sprintf(__('Product "%1$s" (ID: %2$d) successfully assigned to vendor "%3$s".','vendor-dashboard'), esc_html($product_title), esc_html($product_id), esc_html($vendor_name)); set_transient('vdb_admin_success', $success_msg, 60); wp_safe_redirect( admin_url( 'admin.php?page=vendor-admin-settings&vd_assign_success=1&product=' . $product_id . '&vendor=' . $vendor_id ) ); exit;
}
add_action( 'admin_init', 'vdb_handle_product_assignment' );


/** Render the content for the admin settings page. */
function vdb_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }

    $end_date = current_time('mysql'); $start_date = date('Y-m-d H:i:s', strtotime('-7 days', current_time('timestamp')));
    $sales = function_exists('vdb_get_all_vendor_sales_total') ? vdb_get_all_vendor_sales_total( $start_date, $end_date ) : 0;
    $vendors = get_users( array( 'role' => VENDOR_DASHBOARD_ROLE, 'orderby' => 'registered', 'order' => 'DESC' ) );

    ?>
    <div class="wrap vdb-admin-page">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php $admin_error = get_transient('vdb_admin_errors'); if ($admin_error): ?> <div id="message" class="notice notice-error is-dismissible"><p><?php echo wp_kses_post( implode('<br>', $admin_error) ); ?></p></div> <?php delete_transient('vdb_admin_errors'); endif; ?>
        <?php $admin_success = get_transient('vdb_admin_success'); if ($admin_success): ?> <div id="message" class="notice notice-success is-dismissible"><p><?php echo esc_html( $admin_success ); ?></p></div> <?php delete_transient('vdb_admin_success'); endif; ?>
        <?php $admin_notice = get_transient('vdb_admin_notice'); if ($admin_notice): ?> <div id="message" class="notice notice-warning is-dismissible"><p><?php echo esc_html( $admin_notice ); ?></p></div> <?php delete_transient('vdb_admin_notice'); endif; ?>
        <?php if ( isset( $_GET['vd_success'] ) && !get_transient('vdb_admin_success') ) { $msg = ''; if($_GET['vd_success']==='registered')$msg=__('Vendor registered!','vendor-dashboard'); if($_GET['vd_success']==='deleted')$msg=__('Vendor deleted.','vendor-dashboard'); if($msg) echo '<div id="message" class="notice notice-success is-dismissible"><p>'.esc_html($msg).'</p></div>'; } ?>
        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
             <div id="message" class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'vendor-dashboard'); ?></p></div>
        <?php endif; ?>


        <form method="post" action="options.php">
            <?php
                settings_fields( 'vdb_admin_settings_group' ); // Group name
                do_settings_sections( 'vendor-admin-settings' ); // Page slug
                submit_button();
            ?>
        </form>
        <hr style="margin: 30px 0;">


        <div class="vdb-admin-section vdb-register-section">
           <h2><?php esc_html_e('Register New Vendor','vendor-dashboard');?></h2>
           <form method="post" action="<?php echo esc_url( admin_url('admin.php?page=vendor-admin-settings') ); ?>">
                <input type="hidden" name="vdb_action" value="register_vendor">
                <?php wp_nonce_field('vdb_register_vendor_action','vdb_register_nonce'); ?>
                <div class="vdb-form-grid">
                    <div class="vdb-form-col">
                         <p><label><?php esc_html_e('Username','vendor-dashboard');?><br><input type="text" name="vdb_username" class="regular-text" required></label></p>
                         <p><label><?php esc_html_e('Email','vendor-dashboard');?><br><input type="email" name="vdb_email" class="regular-text" required></label></p>
                         <p><label><?php esc_html_e('First Name (Optional)','vendor-dashboard');?><br><input type="text" name="vdb_first_name" class="regular-text"></label></p>
                         <p><label><?php esc_html_e('Brand Name','vendor-dashboard');?><br><input type="text" name="vdb_brand_name" class="regular-text" required><br><small><?php esc_html_e('Public store name.','vendor-dashboard');?></small></label></p>
                    </div>
                    <div class="vdb-form-col">
                         <p><label><?php esc_html_e('Password','vendor-dashboard');?><br><input type="password" name="vdb_password" class="regular-text" required autocomplete="new-password"></label></p>
                         <p><label><?php esc_html_e('Confirm Password','vendor-dashboard');?><br><input type="password" name="vdb_confirm_password" class="regular-text" required autocomplete="new-password"></label></p>
                    </div>
                </div>
                <p><?php submit_button(__('Register Vendor','vendor-dashboard'), 'primary', 'vdb_submit_register', false);?></p>
            </form>
        </div>

        <div class="vdb-admin-section vdb-manage-section">
             <h2><?php esc_html_e('Manage Vendors','vendor-dashboard');?></h2>
             <div class="vdb-sales-summary">
                 <h3><?php esc_html_e('Sales Summary (Last 7 Days)', 'vendor-dashboard');?></h3>
                 <p><strong><?php esc_html_e('Total sales from all vendors:', 'vendor-dashboard');?></strong> <?php echo wp_kses_post(wc_price($sales));?></p>
                 <p><small><?php esc_html_e('Includes sales from products assigned to registered vendors.', 'vendor-dashboard');?></small></p>
             </div>

             <table class="wp-list-table widefat fixed striped users vdb-vendor-table">
                 <thead>
                     <tr> 
                         <th scope="col" class="manage-column column-username"><?php esc_html_e('Username','vendor-dashboard');?></th>
                         <th scope="col" class="manage-column column-brand_name"><?php esc_html_e('Brand Name','vendor-dashboard');?></th>
                         <th scope="col" class="manage-column column-email"><?php esc_html_e('Email','vendor-dashboard');?></th>
                         <th scope="col" class="manage-column column-registered"><?php esc_html_e('Registered','vendor-dashboard');?></th>
                         <th scope="col" class="manage-column column-products"><?php esc_html_e('Products','vendor-dashboard');?></th>
                         <th scope="col" class="manage-column column-assign-product" style="width: 220px;"><?php esc_html_e('Assign Product by ID','vendor-dashboard');?></th>
                     </tr>
                 </thead>
                 <tbody id="the-list">
                     <?php if ( ! empty( $vendors ) ) : ?>
                         <?php foreach ( $vendors as $vendor ) : ?>
                             <?php
                                 $vi = get_userdata( $vendor->ID );
                                 if ( ! $vi ) { continue; } 

                                 $bn = get_user_meta( $vendor->ID, 'vdb_brand_name', true );
                                 $pc = count_user_posts( $vendor->ID, 'product', true ); 

                                 $elink = admin_url('edit.php?post_type=product&author='.$vendor->ID);
                                 $dlink = wp_nonce_url( admin_url('admin.php?page=vendor-admin-settings&action=delete_vendor&vendor_id='.$vendor->ID), 'vdb_delete_vendor_'.$vendor->ID );
                             ?>
                             <tr> 
                                 <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                     <?php echo get_avatar( $vendor->ID, 32 ); ?>
                                     <strong><a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>"><?php echo esc_html( $vi->user_login ); ?></a></strong>
                                     <div class="row-actions">
                                         <span class="edit"><a href="<?php echo esc_url( $elink ); ?>"><?php esc_html_e( 'View Products', 'vendor-dashboard' ); ?></a> | </span>
                                         <span class="delete"><a href="<?php echo esc_url( $dlink ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete vendor? Products will be reassigned to you.', 'vendor-dashboard' ); ?>');"><?php esc_html_e( 'Delete Vendor', 'vendor-dashboard' ); ?></a></span>
                                     </div>
                                     <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'vendor-dashboard' ); ?></span></button>
                                 </td>
                                 <td data-colname="Brand Name"><?php echo esc_html( $bn ?: 'N/A' ); ?></td>
                                 <td data-colname="Email"><a href="mailto:<?php echo esc_attr( $vi->user_email ); ?>"><?php echo esc_html( $vi->user_email ); ?></a></td>
                                 <td data-colname="Registered"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $vi->user_registered ) ) ); ?></td>
                                 <td data-colname="Products"><a href="<?php echo esc_url( $elink ); ?>"><?php printf( esc_html__( '%d products', 'vendor-dashboard' ), $pc ); ?></a></td>
                                 <td data-colname="Assign Product by ID">
                                     <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=vendor-admin-settings' ) ); ?>" class="vdb-assign-form">
                                         <input type="hidden" name="vdb_action" value="assign_product">
                                         <input type="hidden" name="vdb_vendor_id" value="<?php echo esc_attr( $vendor->ID ); ?>">
                                         <?php wp_nonce_field( 'vdb_assign_product_' . $vendor->ID, 'vdb_assign_nonce' ); ?>
                                         <label for="vdb_product_id_<?php echo esc_attr( $vendor->ID ); ?>" class="screen-reader-text"><?php esc_html_e( 'Product ID', 'vendor-dashboard' ); ?></label>
                                         <input type="number" id="vdb_product_id_<?php echo esc_attr( $vendor->ID ); ?>" name="vdb_product_id" placeholder="<?php esc_attr_e( 'Product ID', 'vendor-dashboard' ); ?>" style="width: 100px;" min="1">
                                         <button type="submit" class="button button-secondary button-small"><?php esc_html_e( 'Assign', 'vendor-dashboard' ); ?></button>
                                     </form>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     <?php else : ?>
                         <tr><td colspan="6"><?php esc_html_e( 'No vendors found with the "Vendor" role assigned.', 'vendor-dashboard' ); ?></td></tr>
                     <?php endif; ?>
                 </tbody>
                 <tfoot> 
                    <tr><th>Username</th><th>Brand Name</th><th>Email</th><th>Registered</th><th>Products</th><th>Assign Product by ID</th></tr>
                 </tfoot>
             </table>
        </div>

    </div>
    <?php
} 
?>