<?php
/**
 * Vendor Dashboard - Payout Settings Functions
 */

if ( ! defined( 'WPINC' ) ) { die; }

// Define user meta keys for payout settings
define( 'VDB_PAYOUT_METHOD_KEY', '_vdb_payout_method_preference' );
define( 'VDB_PAYPAL_EMAIL_KEY', '_vdb_paypal_email' );
define( 'VDB_STRIPE_ACCOUNT_ID_KEY', '_vdb_stripe_account_id' ); // New for Stripe

// Remove Bank Detail Keys as they are no longer used
// define( 'VDB_BANK_AC_NAME_KEY', '_vdb_bank_account_name' );
// define( 'VDB_BANK_AC_NUMBER_KEY', '_vdb_bank_account_number' );
// define( 'VDB_BANK_NAME_KEY', '_vdb_bank_name' );
// define( 'VDB_BANK_SORT_CODE_KEY', '_vdb_bank_sort_code' );
// define( 'VDB_BANK_IBAN_KEY', '_vdb_bank_iban' );
// define( 'VDB_BANK_BIC_SWIFT_KEY', '_vdb_bank_bic_swift' );

/**
 * Render the Payout Settings form fields within the Profile Settings tab.
 */
function vdb_render_payout_settings_fields( $vendor_id ) {
    $current_method = get_user_meta( $vendor_id, VDB_PAYOUT_METHOD_KEY, true ) ?: 'paypal'; // Default to paypal
    $paypal_email = get_user_meta( $vendor_id, VDB_PAYPAL_EMAIL_KEY, true );
    $stripe_account_id = get_user_meta( $vendor_id, VDB_STRIPE_ACCOUNT_ID_KEY, true );

    ?>
    <div class="vdb-form-section">
        <h4><?php esc_html_e( 'Payout Settings', 'vendor-dashboard' ); ?></h4>
        <p>
            <label for="vdb_payout_method"><?php esc_html_e( 'Preferred Payout Method', 'vendor-dashboard' ); ?></label>
            <select name="vdb_payout_method" id="vdb_payout_method">
                <option value="paypal" <?php selected( $current_method, 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'vendor-dashboard' ); ?></option>
                <option value="stripe_connect" <?php selected( $current_method, 'stripe_connect' ); ?>><?php esc_html_e( 'Stripe Connect', 'vendor-dashboard' ); ?></option>
            </select>
        </p>

        <div id="vdb-payout-paypal-fields" class="vdb-payout-method-fields" style="<?php echo $current_method === 'paypal' ? '' : 'display:none;'; ?>">
            <p>
                <label for="vdb_paypal_email"><?php esc_html_e( 'PayPal Email Address', 'vendor-dashboard' ); ?></label>
                <input type="email" name="vdb_paypal_email" id="vdb_paypal_email" value="<?php echo esc_attr( $paypal_email ); ?>" class="regular-text">
            </p>
        </div>

        <div id="vdb-payout-stripe-connect-fields" class="vdb-payout-method-fields" style="<?php echo $current_method === 'stripe_connect' ? '' : 'display:none;'; ?>">
            <p>
                <label for="vdb_stripe_account_id"><?php esc_html_e( 'Stripe Account ID', 'vendor-dashboard' ); ?></label>
                <input type="text" name="vdb_stripe_account_id" id="vdb_stripe_account_id" value="<?php echo esc_attr( $stripe_account_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., acct_xxxxxxxxxxxxxxxxx', 'vendor-dashboard'); ?>">
                <small>
                    <?php esc_html_e('You will need to connect your Stripe account to our platform. ', 'vendor-dashboard'); ?>
                    <?php // TODO: Replace #stripe-connect-onboarding-link with the actual link or instructions for vendors to connect/find their ID ?>
                    <a href="#stripe-connect-onboarding-link" target="_blank"><?php esc_html_e('Instructions for connecting to Stripe.', 'vendor-dashboard'); ?></a>
                </small>
            </p>
        </div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            function togglePayoutFields() {
                var selectedMethod = $('#vdb_payout_method').val();
                $('.vdb-payout-method-fields').hide();
                if (selectedMethod === 'paypal') {
                    $('#vdb-payout-paypal-fields').show();
                } else if (selectedMethod === 'stripe_connect') {
                    $('#vdb-payout-stripe-connect-fields').show();
                }
            }
            $('#vdb_payout_method').on('change', togglePayoutFields);
            togglePayoutFields(); // Initial call
        });
    </script>
    <?php
}

/**
 * Save payout settings when vendor profile is updated.
 */
function vdb_save_payout_settings( $vendor_id, $posted_data ) {
    $updated = false;

    if ( isset( $posted_data['vdb_payout_method'] ) ) {
        $payout_method = sanitize_text_field( $posted_data['vdb_payout_method'] );
        if ( in_array( $payout_method, array( 'paypal', 'stripe_connect' ) ) ) { // Updated allowed methods
            if ( update_user_meta( $vendor_id, VDB_PAYOUT_METHOD_KEY, $payout_method ) ) $updated = true;
        }
    }

    if ( isset( $posted_data['vdb_paypal_email'] ) ) {
        $paypal_email = sanitize_email( $posted_data['vdb_paypal_email'] );
        if ( is_email( $paypal_email ) || empty( $paypal_email ) ) { 
            if ( update_user_meta( $vendor_id, VDB_PAYPAL_EMAIL_KEY, $paypal_email ) ) $updated = true;
        }
    }

    if ( isset( $posted_data['vdb_stripe_account_id'] ) ) {
        $stripe_id = sanitize_text_field( wp_unslash( $posted_data['vdb_stripe_account_id'] ) );
        // Basic validation for Stripe account ID format (acct_...)
        if ( preg_match('/^acct_[a-zA-Z0-9]+$/', $stripe_id) || empty($stripe_id) ) { // Allow clearing
            if ( update_user_meta( $vendor_id, VDB_STRIPE_ACCOUNT_ID_KEY, $stripe_id ) ) $updated = true;
        }
    }
    
    // Remove old bank detail meta if they exist
    delete_user_meta( $vendor_id, '_vdb_bank_account_name' );
    delete_user_meta( $vendor_id, '_vdb_bank_account_number' );
    delete_user_meta( $vendor_id, '_vdb_bank_name' );
    delete_user_meta( $vendor_id, '_vdb_bank_sort_code' );
    delete_user_meta( $vendor_id, '_vdb_bank_iban' );
    delete_user_meta( $vendor_id, '_vdb_bank_bic_swift' );

    return $updated;
}

/**
 * Get formatted payout details for a vendor.
 */
function vdb_get_formatted_vendor_payout_details( $vendor_id ) {
    $method_pref = get_user_meta( $vendor_id, VDB_PAYOUT_METHOD_KEY, true );
    $output = array(
        'method_label' => __('Not Specified', 'vendor-dashboard'),
        'details_html' => '<p>' . __('Payout details not yet provided by the vendor.', 'vendor-dashboard') . '</p>'
    );

    if ( $method_pref === 'paypal' ) {
        $output['method_label'] = __('PayPal', 'vendor-dashboard');
        $paypal_email = get_user_meta( $vendor_id, VDB_PAYPAL_EMAIL_KEY, true );
        if ( $paypal_email && is_email($paypal_email) ) {
            $output['details_html'] = '<p><strong>' . __('PayPal Email:', 'vendor-dashboard') . '</strong> ' . esc_html( $paypal_email ) . '</p>';
        } else {
            $output['details_html'] = '<p>' . __('PayPal email not provided or invalid.', 'vendor-dashboard') . '</p>';
        }
    } elseif ( $method_pref === 'stripe_connect' ) {
        $output['method_label'] = __('Stripe Connect', 'vendor-dashboard');
        $stripe_account_id = get_user_meta( $vendor_id, VDB_STRIPE_ACCOUNT_ID_KEY, true );
        if ( $stripe_account_id && preg_match('/^acct_[a-zA-Z0-9]+$/', $stripe_account_id) ) {
            $output['details_html'] = '<p><strong>' . __('Stripe Account ID:', 'vendor-dashboard') . '</strong> ' . esc_html( $stripe_account_id ) . '</p>';
             $output['details_html'] .= '<p><a href="https://dashboard.stripe.com/connect/accounts/' . esc_attr($stripe_account_id) . '" target="_blank" class="button button-small">' . __('View on Stripe', 'vendor-dashboard') . '</a></p>';
        } else {
            $output['details_html'] = '<p>' . __('Stripe Account ID not provided or invalid. Vendor needs to connect their Stripe account.', 'vendor-dashboard') . '</p>';
        }
    }
    return $output;
}
?>