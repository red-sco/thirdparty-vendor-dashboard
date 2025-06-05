<?php
/**
 * Functions for managing vendor notifications.
 */
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Adds a notification for a specific vendor and sends an email.
 *
 * @param int    $vendor_id The ID of the vendor.
 * @param string $message   The notification message.
 * @param string $type      The type of notification (e.g., 'info', 'new_order', 'low_stock_warning').
 * @param string $link      An optional link related to the notification.
 * @return bool True if the notification was added/updated, false otherwise.
 */
function vdb_add_vendor_notification( $vendor_id, $message, $type = 'info', $link = '' ) {
    if ( ! $vendor_id || empty( $message ) ) { return false; }

    $notifications = get_user_meta( $vendor_id, '_vdb_notifications', true );
    if ( ! is_array( $notifications ) ) { $notifications = array(); }

    $sanitized_message = sanitize_text_field( $message );
    $sanitized_type = sanitize_key( $type );

    // Check if an identical unread notification already exists to prevent duplicates and re-emailing
    foreach ($notifications as $existing_notification) {
        if (
            isset($existing_notification['read']) && !$existing_notification['read'] &&
            isset($existing_notification['type']) && $existing_notification['type'] === $sanitized_type &&
            isset($existing_notification['message']) && $existing_notification['message'] === $sanitized_message
        ) {
            return true; // Already exists and is unread, no need to add or email again
        }
    }

    $new_notification_data = array(
        'id'        => 'vdb_notif_' . uniqid() . '_' . dechex( time() ),
        'message'   => $sanitized_message,
        'type'      => $sanitized_type,
        'timestamp' => time(),
        'read'      => false,
        'link'      => $link ? esc_url_raw( $link ) : '',
    );
    array_unshift( $notifications, $new_notification_data );

    if ( count( $notifications ) > VDB_MAX_NOTIFICATIONS ) {
        $notifications = array_slice( $notifications, 0, VDB_MAX_NOTIFICATIONS );
    }

    $meta_updated = update_user_meta( $vendor_id, '_vdb_notifications', $notifications );

    // If user meta was updated (meaning a new notification was added or list was modified)
    // and the new notification is indeed present at the start of the array.
    if ( $meta_updated && !empty($notifications) && $notifications[0]['id'] === $new_notification_data['id'] ) {
        $vendor_info = get_userdata($vendor_id);
        if ($vendor_info && !empty($vendor_info->user_email)) {
            $vendor_email = $vendor_info->user_email;
            $blog_name = get_bloginfo('name');

            $subject = sprintf(
                __('[%s] New Dashboard Notification', 'vendor-dashboard'),
                $blog_name
            );

            $dashboard_url = vdb_get_dashboard_page_url();
            $notification_dashboard_link_html = $dashboard_url ? sprintf(
                '<p><a href="%s" style="color: #0073aa; text-decoration: none;">%s</a></p>',
                esc_url(add_query_arg('section', 'overview', $dashboard_url)),
                __('View all notifications on your dashboard', 'vendor-dashboard')
            ) : '';

            $email_body = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
            $email_body .= '<p>' . sprintf(esc_html__('Hello %s,', 'vendor-dashboard'), esc_html($vendor_info->display_name)) . '</p>';
            $email_body .= '<p>' . esc_html__('You have a new notification on your vendor dashboard:', 'vendor-dashboard') . '</p>';
            $email_body .= '<blockquote style="border-left: 4px solid #0073aa; margin: 1em 0; padding: 0.5em 10px; background-color: #f7f7f7;">';
            $email_body .= wp_kses_post($new_notification_data['message']); // The message itself
            $email_body .= '</blockquote>';

            if ($new_notification_data['link']) {
                 $email_body .= '<p>' . sprintf(
                     esc_html__('You can view this item directly: %s', 'vendor-dashboard'),
                     sprintf(
                         '<a href="%s" style="color: #0073aa; text-decoration: none;">%s</a>',
                         esc_url($new_notification_data['link']),
                         esc_html__('Click here', 'vendor-dashboard')
                        )
                    ) . '</p>';
            }
            $email_body .= $notification_dashboard_link_html;
            $email_body .= '<p>' . esc_html__('Thank you,', 'vendor-dashboard') . '<br>' . esc_html($blog_name) . '</p>';
            $email_body .= '</div>';

            $headers = array('Content-Type: text/html; charset=UTF-8');
            // Set a From address to potentially improve deliverability
            $admin_email = get_option('admin_email');
            $from_name = html_entity_decode($blog_name, ENT_QUOTES, 'UTF-8'); // Decode HTML entities for email header
            $headers[] = 'From: "' . $from_name . '" <' . $admin_email . '>';


            wp_mail($vendor_email, $subject, $email_body, $headers);
        }
    }
    return $meta_updated;
}

/**
 * Retrieves notifications for a vendor, supporting pagination.
 *
 * @param int  $vendor_id The ID of the vendor.
 * @param bool $unread_only Whether to fetch only unread notifications.
 * @param int  $paged The current page number.
 * @param int  $per_page Number of notifications per page.
 * @return array An array containing 'notifications', 'total_items', 'total_pages'.
 */
function vdb_get_vendor_notifications( $vendor_id, $unread_only = false, $paged = 1, $per_page = VDB_NOTIFICATIONS_PER_PAGE ) {
    if ( ! $vendor_id ) {
        return array('notifications' => array(), 'total_items' => 0, 'total_pages' => 0);
    }

    $all_notifications = get_user_meta( $vendor_id, '_vdb_notifications', true );
    if ( ! is_array( $all_notifications ) ) {
        $all_notifications = array();
    }

    // Filter by read status if needed *before* pagination
    $filtered_notifications = array();
    if ( $unread_only ) {
        foreach ( $all_notifications as $notification ) {
            if ( isset($notification['read']) && ! $notification['read'] ) {
                $filtered_notifications[] = $notification;
            }
        }
    } else {
        $filtered_notifications = $all_notifications;
    }

    // Sort by timestamp descending (newest first)
    usort($filtered_notifications, function($a, $b) {
        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });

    $total_items = count($filtered_notifications);
    $total_pages = ceil($total_items / $per_page);
    $paged = max(1, absint($paged)); // Ensure paged is at least 1
    $offset = ($paged - 1) * $per_page;

    // Get the slice for the current page
    $paged_notifications = array_slice( $filtered_notifications, $offset, $per_page );

    return array(
        'notifications' => $paged_notifications,
        'total_items'   => $total_items,
        'total_pages'   => $total_pages,
    );
}

/**
 * Marks a specific notification as read for a vendor.
 * (Unchanged from previous version)
 */
function vdb_mark_notification_as_read( $vendor_id, $notification_id ) {
    if ( ! $vendor_id || empty( $notification_id ) ) { return false; }
    $notifications = get_user_meta( $vendor_id, '_vdb_notifications', true );
    if ( ! is_array( $notifications ) || empty( $notifications ) ) { return false; }
    $updated = false;
    foreach ( $notifications as $key => $notification ) {
        if ( isset( $notification['id'] ) && $notification['id'] === $notification_id ) {
            if ( ! $notification['read'] ) { $notifications[ $key ]['read'] = true; $updated = true; }
            break; 
        }
    }
    if ( $updated ) { return update_user_meta( $vendor_id, '_vdb_notifications', $notifications ); }
    return false;
}

/**
 * Marks all notifications as read for a vendor.
 * (Unchanged from previous version)
 */
function vdb_mark_all_notifications_as_read( $vendor_id ) {
    if ( ! $vendor_id ) { return false; }
    $notifications = get_user_meta( $vendor_id, '_vdb_notifications', true );
    if ( ! is_array( $notifications ) || empty( $notifications ) ) { return false; }
    $updated_any = false;
    foreach ( $notifications as $key => $notification ) {
        if ( isset($notification['read']) && ! $notification['read'] ) { $notifications[ $key ]['read'] = true; $updated_any = true; }
    }
    if ( $updated_any ) { return update_user_meta( $vendor_id, '_vdb_notifications', $notifications ); }
    return false; 
}


/**
 * Deletes a specific notification for a vendor.
 *
 * @param int    $vendor_id        The ID of the vendor.
 * @param string $notification_id  The ID of the notification to delete.
 * @return bool True on success, false if notification not found.
 */
function vdb_delete_notification( $vendor_id, $notification_id ) {
    if ( ! $vendor_id || empty( $notification_id ) ) {
        return false;
    }

    $notifications = get_user_meta( $vendor_id, '_vdb_notifications', true );
    if ( ! is_array( $notifications ) || empty( $notifications ) ) {
        return false;
    }

    $initial_count = count($notifications);
    $found_key = null;

    foreach ( $notifications as $key => $notification ) {
        if ( isset( $notification['id'] ) && $notification['id'] === $notification_id ) {
            $found_key = $key;
            break;
        }
    }

    if ( $found_key !== null ) {
        unset( $notifications[ $found_key ] );
        // Re-index array numerically to ensure proper meta saving
        $notifications = array_values($notifications);
        return update_user_meta( $vendor_id, '_vdb_notifications', $notifications );
    }

    return false; // Notification not found
}

/**
 * Deletes ALL notifications for a vendor.
 *
 * @param int $vendor_id The ID of the vendor.
 * @return bool True on success, false on failure.
 */
function vdb_delete_all_notifications( $vendor_id ) {
    if ( ! $vendor_id ) {
        return false;
    }
    // Delete the entire meta key, effectively removing all notifications
    return delete_user_meta( $vendor_id, '_vdb_notifications' );
}


/**
 * Renders the notification center for the vendor dashboard with pagination.
 *
 * @param int $vendor_id
 */
function vdb_render_notification_center( $vendor_id ) {
    // Generate low stock notification
    $low_stock_count = vdb_get_vendor_low_stock_product_count( $vendor_id );
    if ( $low_stock_count > 0 ) {
        $dashboard_base_url = vdb_get_dashboard_page_url();
        $products_url_params = array('section' => 'products');
        // Determine stock status filter for low stock
        // In WC, "low stock" means stock quantity is <= the low stock threshold.
        // "Out of stock" is a separate status.
        // For simplicity, we'll link to all products, vendor can filter if needed.
        // If you have a specific filter for "low stock" in product list, use it:
        // $products_url_params['stock_status'] = 'lowstock'; // or however your filter is named
        $products_url = $dashboard_base_url ? esc_url( add_query_arg( $products_url_params, $dashboard_base_url ) ) : '#';
        $low_stock_message = sprintf(
            _n(
                'You have %d product low on stock (less than or equal to %d units).',
                'You have %d products low on stock (less than or equal to %d units).',
                $low_stock_count,
                'vendor-dashboard'
            ),
            $low_stock_count,
            VDB_LOW_STOCK_THRESHOLD
        );
        vdb_add_vendor_notification( $vendor_id, $low_stock_message, 'low_stock_warning', $products_url );
    }

    // Get current page for notifications
    $current_notif_page = isset( $_GET['notif_page'] ) ? absint( $_GET['notif_page'] ) : 1;

    // Fetch paginated notifications
    $notification_data = vdb_get_vendor_notifications( $vendor_id, false, $current_notif_page, VDB_NOTIFICATIONS_PER_PAGE );
    $notifications = $notification_data['notifications'];
    $total_items   = $notification_data['total_items'];
    $total_pages   = $notification_data['total_pages'];

    $all_unread_data = vdb_get_vendor_notifications( $vendor_id, true, 1, VDB_MAX_NOTIFICATIONS ); // Get all unread for count
    $unread_count = $all_unread_data['total_items'];

    $dashboard_url = vdb_get_dashboard_page_url();
    $base_url_for_pagination = $dashboard_url ? esc_url(add_query_arg('section', 'overview', $dashboard_url)) : '';
    ?>
    <div class="vdb-widget vdb-widget-notifications">
        <h4>
            <?php esc_html_e('Notifications', 'vendor-dashboard'); ?>
            <?php if ($unread_count > 0): ?>
                <span class="vdb-unread-count-badge"><?php echo esc_html($unread_count); ?></span>
            <?php endif; ?>
            <span class="vdb-notification-widget-actions"> <?php // Wrapper for actions ?>
                <?php if ($unread_count > 0): ?>
                     <a href="#" class="vdb-notifications-mark-all-read vdb-widget-link" style="font-size: 0.8em; margin-left: 10px;"><?php esc_html_e('Mark all read', 'vendor-dashboard'); ?></a>
                <?php endif; ?>
                 <?php if ($total_items > 0): ?>
                     <a href="#" class="vdb-notifications-delete-all vdb-widget-link" style="font-size: 0.8em; margin-left: 10px; color: #d63638;"><?php esc_html_e('Delete All', 'vendor-dashboard'); ?></a>
                 <?php endif; ?>
            </span>
        </h4>
        <?php if ( ! empty( $notifications ) ) : ?>
            <ul class="vdb-notifications-list">
                <?php foreach ( $notifications as $notification ) : ?>
                    <?php
                    $read_class = (isset($notification['read']) && $notification['read']) ? 'is-read' : 'is-unread';
                    $icon_class = 'dashicons dashicons-info-outline'; 
                    switch ($notification['type']) { case 'new_order': $icon_class = 'dashicons dashicons-cart'; break; case 'low_stock_warning': $icon_class = 'dashicons dashicons-warning'; break; case 'product_approved': $icon_class = 'dashicons dashicons-yes-alt'; break; case 'admin_message': $icon_class = 'dashicons dashicons-format-chat'; break; }
                    $delete_nonce = wp_create_nonce('vdb_delete_notification_' . $notification['id']); // Create nonce for delete
                    ?>
                    <li class="vdb-notification-item <?php echo esc_attr($read_class); ?> vdb-notification-type-<?php echo esc_attr($notification['type']); ?>" data-notification-id="<?php echo esc_attr($notification['id']); ?>">
                        <span class="vdb-notification-icon <?php echo esc_attr($icon_class); ?>"></span>
                        <div class="vdb-notification-content">
                            <span class="vdb-notification-message">
                                <?php echo wp_kses_post( $notification['message'] ); ?>
                            </span>
                            <span class="vdb-notification-timestamp">
                                <?php echo esc_html( human_time_diff( $notification['timestamp'], current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'vendor-dashboard' ) ); ?>
                            </span>
                        </div>
                        <span class="vdb-notification-actions"> <?php // Wrapper for actions ?>
                            <?php if (!empty($notification['link'])): ?>
                                <a href="<?php echo esc_url($notification['link']); ?>" class="vdb-notification-link" target="_blank" title="<?php esc_attr_e('View Details', 'vendor-dashboard'); ?>"><span class="dashicons dashicons-external"></span></a>
                            <?php endif; ?>
                             <a href="#" class="vdb-notification-delete" data-nonce="<?php echo esc_attr($delete_nonce); ?>" title="<?php esc_attr_e('Delete', 'vendor-dashboard'); ?>"><span class="dashicons dashicons-trash"></span></a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
             <div class="vdb-notifications-pagination">
                <?php
                echo paginate_links( array(
                    'base'      => $base_url_for_pagination . '%_%', // Base URL with overview section
                    'format'    => '¬if_page=%#%', // Query var for page number
                    'current'   => $current_notif_page,
                    'total'     => $total_pages,
                    'prev_text' => __('«'),
                    'next_text' => __('»'),
                    'mid_size'  => 1,
                    'add_args'  => false, // Let WordPress handle existing query vars automatically
                    'type'      => 'list', // Output as <ul> list
                ) );
                ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No notifications yet.', 'vendor-dashboard' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Helper function to get the dashboard page URL.
 * (Unchanged from previous version)
 */
function vdb_get_dashboard_page_url() {
    global $wpdb;
    $page_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_type = 'page' AND post_status = 'publish' LIMIT 1", '%[vendor_dashboard]%' ) );
    if ( $page_id ) { return get_permalink( $page_id ); }
    return ''; 
}
?>