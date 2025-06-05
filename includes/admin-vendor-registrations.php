<?php
/**
 * Vendor Dashboard - Admin Page for Managing Pending Vendor Registrations.
 */

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Add the submenu page for pending registrations.
 */
function vdb_admin_add_pending_registrations_page() {
    add_submenu_page(
        'vendor-admin-settings',                              // Parent slug
        __( 'Pending Vendor Registrations', 'vendor-dashboard' ), // Page title
        __( 'Pending Registrations', 'vendor-dashboard' ),    // Menu title
        'manage_options',                                     // Capability
        'vdb-pending-registrations',                          // Menu slug
        'vdb_admin_render_pending_registrations_page'         // Callback function
    );
}
add_action( 'admin_menu', 'vdb_admin_add_pending_registrations_page' );

/**
 * Handle approval/denial actions.
 */
function vdb_admin_handle_registration_actions() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'vdb-pending-registrations' ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $action    = isset( $_GET['vdb_reg_action'] ) ? sanitize_key( $_GET['vdb_reg_action'] ) : '';
    $user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
    $_wpnonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';

    if ( ! $action || ! $user_id || ! $_wpnonce ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        wp_safe_redirect( admin_url( 'admin.php?page=vdb-pending-registrations&vdb_message=error_user_not_found' ) );
        exit;
    }
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $dashboard_url = vdb_get_dashboard_page_url() ?: home_url('/');
    $login_link_html = sprintf('<a href="%s">%s</a>', esc_url($dashboard_url), esc_html__('Log in to your dashboard', 'vendor-dashboard'));


    if ( $action === 'approve' ) {
        if ( ! wp_verify_nonce( $_wpnonce, 'vdb_approve_user_' . $user_id ) ) {
            wp_die( 'Security check failed for approval.' );
        }
        $user->set_role( VENDOR_DASHBOARD_ROLE );
        update_user_meta( $user_id, VDB_REGISTRATION_STATUS_META_KEY, 'approved' );
        
        $subject_approve = sprintf( __( '[%s] Your Vendor Application Approved!', 'vendor-dashboard' ), get_bloginfo( 'name' ) );
        $message_approve_html = '<p>' . sprintf( __( 'Hello %s,', 'vendor-dashboard' ), esc_html( $user->first_name ?: $user->user_login ) ) . '</p>';
        $message_approve_html .= '<p>' . sprintf( __( 'Congratulations! Your vendor application for %s has been approved.', 'vendor-dashboard' ), get_bloginfo( 'name' ) ) . '</p>';
        $message_approve_html .= '<p>' . sprintf( __( 'You can now log in to your vendor dashboard: %s', 'vendor-dashboard' ), $login_link_html ) . '</p>';
        $message_approve_html .= '<p>' . __( 'Thank you,', 'vendor-dashboard' ) . '<br>' . get_bloginfo( 'name' ) . '</p>';
        wp_mail( $user->user_email, $subject_approve, $message_approve_html, $headers );

        wp_safe_redirect( admin_url( 'admin.php?page=vdb-pending-registrations&vdb_message=approved&user=' . $user->user_login ) );
        exit;

    } elseif ( $action === 'deny' ) {
        if ( ! wp_verify_nonce( $_wpnonce, 'vdb_deny_user_' . $user_id ) ) {
            wp_die( 'Security check failed for denial.' );
        }
        update_user_meta( $user_id, VDB_REGISTRATION_STATUS_META_KEY, 'denied' );
        
        $subject_deny = sprintf( __( '[%s] Your Vendor Application Status', 'vendor-dashboard' ), get_bloginfo( 'name' ) );
        $message_deny_html = '<p>' . sprintf( __( 'Hello %s,', 'vendor-dashboard' ), esc_html( $user->first_name ?: $user->user_login ) ) . '</p>';
        $message_deny_html .= '<p>' . sprintf( __( 'We regret to inform you that your recent vendor application for %s has not been approved at this time.', 'vendor-dashboard' ), get_bloginfo( 'name' ) ) . '</p>';
        $message_deny_html .= '<p>' . __( 'If you have any questions, please contact site support.', 'vendor-dashboard' ) . '</p>';
        $message_deny_html .= '<p>' . __( 'Thank you,', 'vendor-dashboard' ) . '<br>' . get_bloginfo( 'name' ) . '</p>';
        wp_mail( $user->user_email, $subject_deny, $message_deny_html, $headers );

        wp_safe_redirect( admin_url( 'admin.php?page=vdb-pending-registrations&vdb_message=denied&user=' . $user->user_login ) );
        exit;
    }
}
add_action( 'admin_init', 'vdb_admin_handle_registration_actions' );


/**
 * Render the pending registrations page.
 */
function vdb_admin_render_pending_registrations_page() {
    ?>
    <div class="wrap vdb-admin-page">
        <h1><?php esc_html_e( 'Pending Vendor Registrations', 'vendor-dashboard' ); ?></h1>

        <?php
        if ( isset( $_GET['vdb_message'] ) ) {
            $message_code = sanitize_key( $_GET['vdb_message'] );
            $user_login = isset( $_GET['user'] ) ? sanitize_user( $_GET['user'] ) : '';
            $notice_class = 'notice-success';
            $message_text = '';

            switch ( $message_code ) {
                case 'approved':
                    $message_text = sprintf( esc_html__( 'Vendor "%s" has been approved.', 'vendor-dashboard' ), esc_html( $user_login ) );
                    break;
                case 'denied':
                    $message_text = sprintf( esc_html__( 'Vendor "%s" application has been denied.', 'vendor-dashboard' ), esc_html( $user_login ) );
                    break;
                case 'error_user_not_found':
                    $message_text = esc_html__( 'Error: User not found for processing.', 'vendor-dashboard' );
                    $notice_class = 'notice-error';
                    break;
            }
            if ( $message_text ) {
                echo '<div id="message" class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . $message_text . '</p></div>';
            }
        }
        ?>

        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-username"><?php esc_html_e( 'Username', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Brand Name', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-email"><?php esc_html_e( 'Email', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-store-desc"><?php esc_html_e( 'Store Desc.', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-website"><?php esc_html_e( 'Website', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-admin-notes"><?php esc_html_e( 'Admin Notes', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Registered', 'vendor-dashboard' ); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'vendor-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php
                $pending_vendors = get_users( array(
                    'meta_key'   => VDB_REGISTRATION_STATUS_META_KEY,
                    'meta_value' => 'pending',
                    'orderby'    => 'registered',
                    'order'      => 'DESC',
                ) );

                if ( ! empty( $pending_vendors ) ) :
                    foreach ( $pending_vendors as $user ) :
                        $brand_name    = get_user_meta( $user->ID, 'vdb_brand_name', true );
                        $store_desc    = get_user_meta( $user->ID, VDB_REG_STORE_DESC_KEY, true );
                        $store_website = get_user_meta( $user->ID, VDB_REG_STORE_WEBSITE_KEY, true );
                        $admin_notes   = get_user_meta( $user->ID, VDB_REG_ADMIN_NOTES_KEY, true );

                        $approve_link = wp_nonce_url( admin_url( 'admin.php?page=vdb-pending-registrations&vdb_reg_action=approve&user_id=' . $user->ID ), 'vdb_approve_user_' . $user->ID );
                        $deny_link    = wp_nonce_url( admin_url( 'admin.php?page=vdb-pending-registrations&vdb_reg_action=deny&user_id=' . $user->ID ), 'vdb_deny_user_' . $user->ID );
                        ?>
                        <tr>
                            <td class="username column-username has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Username', 'vendor-dashboard' ); ?>">
                                <?php echo get_avatar( $user->ID, 32 ); ?>
                                <strong><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a></strong>
                            </td>
                            <td data-colname="<?php esc_attr_e( 'Brand Name', 'vendor-dashboard' ); ?>"><?php echo esc_html( $brand_name ); ?></td>
                            <td data-colname="<?php esc_attr_e( 'Email', 'vendor-dashboard' ); ?>"><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
                            <td data-colname="<?php esc_attr_e( 'Store Desc.', 'vendor-dashboard' ); ?>"><?php echo esc_html( wp_trim_words( $store_desc, 15, '...' ) ); ?></td>
                            <td data-colname="<?php esc_attr_e( 'Website', 'vendor-dashboard' ); ?>"><?php echo $store_website ? '<a href="'.esc_url($store_website).'" target="_blank">'.esc_html($store_website).'</a>' : 'N/A'; ?></td>
                            <td data-colname="<?php esc_attr_e( 'Admin Notes', 'vendor-dashboard' ); ?>"><?php echo esc_html( wp_trim_words( $admin_notes, 15, '...' ) ); ?></td>
                            <td data-colname="<?php esc_attr_e( 'Registered', 'vendor-dashboard' ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></td>
                            <td data-colname="<?php esc_attr_e( 'Actions', 'vendor-dashboard' ); ?>">
                                <a href="<?php echo esc_url( $approve_link ); ?>" class="button button-primary" style="margin-right: 5px;"><?php esc_html_e( 'Approve', 'vendor-dashboard' ); ?></a>
                                <a href="<?php echo esc_url( $deny_link ); ?>" class="button button-secondary"><?php esc_html_e( 'Deny', 'vendor-dashboard' ); ?></a>
                            </td>
                        </tr>
                        <?php
                    endforeach;
                else :
                    ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e( 'No pending vendor registrations found.', 'vendor-dashboard' ); ?></td>
                    </tr>
                    <?php
                endif;
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>