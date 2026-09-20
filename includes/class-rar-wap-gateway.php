<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAR_WAP_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'rar_advance_payment';
        $this->method_title       = __( 'RAR Advance Payment', 'rar-woo-advance-payment' );
        $this->method_description = __( 'Manual advance/full payment with Bangla QR, bKash, Nagad, Rocket or NPSB bank transfer.', 'rar-woo-advance-payment' );
        $this->has_fields         = true;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', 'Advance Payment / Online Transfer' );
        $this->description = $this->get_option( 'description', 'Pay the required amount now using your preferred local payment method. Never share your PIN or OTP.' );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'rar-woo-advance-payment' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable RAR Advance Payment Gateway', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'safe_test_mode' => array(
                'title'       => __( 'Safe Test Mode', 'rar-woo-advance-payment' ),
                'type'        => 'checkbox',
                'label'       => __( 'Admins / Shop Managers only', 'rar-woo-advance-payment' ),
                'description' => __( 'Keep this ON while testing. Regular customers will continue to use the existing checkout/payment setup.', 'rar-woo-advance-payment' ),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'title' => array(
                'title'   => __( 'Checkout title', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => __( 'Advance Payment / Online Transfer', 'rar-woo-advance-payment' ),
            ),
            'description' => array(
                'title'   => __( 'Checkout description', 'rar-woo-advance-payment' ),
                'type'    => 'textarea',
                'default' => __( 'Pay the required amount now using Bangla QR, bKash, Nagad, Rocket or Bank Transfer (NPSB). Never share your PIN or OTP.', 'rar-woo-advance-payment' ),
            ),
            'enforcement' => array(
                'title'       => __( 'Payment requirement', 'rar-woo-advance-payment' ),
                'type'        => 'select',
                'default'     => 'optional',
                'options'     => array(
                    'optional' => __( 'Optional — keep COD available', 'rar-woo-advance-payment' ),
                    'required' => __( 'Required — customer must submit advance before placing order', 'rar-woo-advance-payment' ),
                ),
                'description' => __( 'Required mode can hide standard COD only when this gateway is fully configured.', 'rar-woo-advance-payment' ),
            ),
            'hide_cod_when_required' => array(
                'title'   => __( 'COD control', 'rar-woo-advance-payment' ),
                'type'    => 'checkbox',
                'label'   => __( 'Hide standard Cash on Delivery when advance is required', 'rar-woo-advance-payment' ),
                'default' => 'yes',
            ),
            'amount_rule' => array(
                'title'   => __( 'Amount to pay now', 'rar-woo-advance-payment' ),
                'type'    => 'select',
                'default' => 'shipping',
                'options' => array(
                    'shipping' => __( 'Full delivery/shipping fee', 'rar-woo-advance-payment' ),
                    'fixed'    => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
                    'percent'  => __( 'Percentage of order total', 'rar-woo-advance-payment' ),
                    'full'     => __( 'Full order total', 'rar-woo-advance-payment' ),
                ),
            ),
            'fixed_amount' => array(
                'title'             => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
                'type'              => 'price',
                'default'           => '200',
                'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            ),
            'percentage' => array(
                'title'             => __( 'Advance percentage', 'rar-woo-advance-payment' ),
                'type'              => 'number',
                'default'           => '20',
                'custom_attributes' => array( 'min' => '1', 'max' => '100', 'step' => '1' ),
            ),
            'after_submit_status' => array(
                'title'   => __( 'Order status after submission', 'rar-woo-advance-payment' ),
                'type'    => 'select',
                'default' => 'on-hold',
                'options' => array(
                    'on-hold'    => __( 'On hold — recommended for manual verification', 'rar-woo-advance-payment' ),
                    'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
                ),
            ),
            'after_verify_status' => array(
                'title'   => __( 'Order status after admin verification', 'rar-woo-advance-payment' ),
                'type'    => 'select',
                'default' => 'processing',
                'options' => array(
                    'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
                    'on-hold'    => __( 'Keep On hold', 'rar-woo-advance-payment' ),
                ),
            ),
            'admin_email' => array(
                'title'       => __( 'Admin notification email', 'rar-woo-advance-payment' ),
                'type'        => 'email',
                'default'     => get_option( 'admin_email' ),
                'description' => __( 'Receives advance payment submission alerts. Leave blank to use the site admin email.', 'rar-woo-advance-payment' ),
            ),
            'admin_submission_email' => array(
                'title'   => __( 'Admin payment alert', 'rar-woo-advance-payment' ),
                'type'    => 'checkbox',
                'label'   => __( 'Email admin when a customer submits payment details', 'rar-woo-advance-payment' ),
                'default' => 'yes',
            ),
            'customer_submission_email' => array(
                'title'       => __( 'Customer submission email', 'rar-woo-advance-payment' ),
                'type'        => 'checkbox',
                'label'       => __( 'Send an extra custom “payment submission received” email', 'rar-woo-advance-payment' ),
                'description' => __( 'Default OFF to avoid duplicate emails because WooCommerce may already send an On-hold/Order received email.', 'rar-woo-advance-payment' ),
                'default'     => 'no',
            ),

            'channel_heading' => array(
                'title'       => __( 'Payment Channels', 'rar-woo-advance-payment' ),
                'type'        => 'title',
                'description' => __( 'Enable at least one channel. Customers are never asked for PIN, password or OTP.', 'rar-woo-advance-payment' ),
            ),

            'bkash_enabled' => array(
                'title'   => 'bKash',
                'type'    => 'checkbox',
                'label'   => __( 'Enable bKash', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'bkash_number' => array(
                'title'   => __( 'bKash number', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => '',
            ),
            'bkash_type' => array(
                'title'   => __( 'bKash account type / instruction', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
            ),

            'nagad_enabled' => array(
                'title'   => 'Nagad',
                'type'    => 'checkbox',
                'label'   => __( 'Enable Nagad', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'nagad_number' => array(
                'title'   => __( 'Nagad number', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => '',
            ),
            'nagad_type' => array(
                'title'   => __( 'Nagad account type / instruction', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
            ),

            'rocket_enabled' => array(
                'title'   => 'Rocket',
                'type'    => 'checkbox',
                'label'   => __( 'Enable Rocket', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'rocket_number' => array(
                'title'   => __( 'Rocket number', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => '',
            ),
            'rocket_type' => array(
                'title'   => __( 'Rocket instruction', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => __( 'Send Money', 'rar-woo-advance-payment' ),
            ),

            'banglaqr_enabled' => array(
                'title'   => 'Bangla QR',
                'type'    => 'checkbox',
                'label'   => __( 'Enable Bangla QR', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'banglaqr_image' => array(
                'title'       => __( 'Bangla QR image URL', 'rar-woo-advance-payment' ),
                'type'        => 'url',
                'default'     => '',
                'description' => __( 'Upload the QR image to Media Library and paste its direct URL here.', 'rar-woo-advance-payment' ),
            ),
            'banglaqr_note' => array(
                'title'   => __( 'Bangla QR instruction', 'rar-woo-advance-payment' ),
                'type'    => 'text',
                'default' => __( 'Scan the QR with a supported banking/MFS app and pay the exact amount.', 'rar-woo-advance-payment' ),
            ),

            'bank_enabled' => array(
                'title'   => __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Bank Transfer / NPSB', 'rar-woo-advance-payment' ),
                'default' => 'no',
            ),
            'bank_details' => array(
                'title'       => __( 'Bank / NPSB details', 'rar-woo-advance-payment' ),
                'type'        => 'textarea',
                'default'     => '',
                'description' => __( 'Example: Bank name, account name, account number, branch/routing as needed. Do not put passwords or OTPs here.', 'rar-woo-advance-payment' ),
            ),
        );
    }

    public function is_safe_test_mode() {
        return 'yes' === $this->get_option( 'safe_test_mode', 'yes' );
    }

    public function is_force_required() {
        return 'required' === $this->get_option( 'enforcement', 'optional' );
    }

    public function get_enabled_channels() {
        $channels = array();

        if ( 'yes' === $this->get_option( 'bkash_enabled', 'no' ) && trim( (string) $this->get_option( 'bkash_number', '' ) ) !== '' ) {
            $channels['bkash'] = 'bKash';
        }
        if ( 'yes' === $this->get_option( 'nagad_enabled', 'no' ) && trim( (string) $this->get_option( 'nagad_number', '' ) ) !== '' ) {
            $channels['nagad'] = 'Nagad';
        }
        if ( 'yes' === $this->get_option( 'rocket_enabled', 'no' ) && trim( (string) $this->get_option( 'rocket_number', '' ) ) !== '' ) {
            $channels['rocket'] = 'Rocket';
        }
        if ( 'yes' === $this->get_option( 'banglaqr_enabled', 'no' ) && esc_url_raw( $this->get_option( 'banglaqr_image', '' ) ) ) {
            $channels['banglaqr'] = 'Bangla QR';
        }
        if ( 'yes' === $this->get_option( 'bank_enabled', 'no' ) && trim( (string) $this->get_option( 'bank_details', '' ) ) !== '' ) {
            $channels['bank'] = __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' );
        }

        return $channels;
    }

    public function is_configured_for_checkout() {
        return 'yes' === $this->enabled && ! empty( $this->get_enabled_channels() );
    }

    public function is_available() {
        if ( ! parent::is_available() ) {
            return false;
        }

        if ( $this->is_safe_test_mode() && ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }

        if ( empty( $this->get_enabled_channels() ) ) {
            return false;
        }

        return $this->get_amount_due_now() > 0;
    }

    public function get_cart_total() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 0.0;
        }

        return max( 0.0, (float) WC()->cart->get_total( 'edit' ) );
    }

    public function get_amount_due_now() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 0.0;
        }

        $total = $this->get_cart_total();
        $rule  = $this->get_option( 'amount_rule', 'shipping' );

        switch ( $rule ) {
            case 'fixed':
                $amount = (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) );
                break;
            case 'percent':
                $percent = min( 100, max( 1, (float) $this->get_option( 'percentage', '20' ) ) );
                $amount  = $total * ( $percent / 100 );
                break;
            case 'full':
                $amount = $total;
                break;
            case 'shipping':
            default:
                $amount = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
                break;
        }

        $amount = max( 0.0, min( $amount, $total ) );
        return (float) wc_format_decimal( $amount, wc_get_price_decimals() );
    }

    public function get_balance_due() {
        return max( 0.0, $this->get_cart_total() - $this->get_amount_due_now() );
    }

    private function channel_details_html( $key ) {
        switch ( $key ) {
            case 'bkash':
                return sprintf(
                    '<strong>%s</strong><br>%s: <code>%s</code>',
                    esc_html( $this->get_option( 'bkash_type', 'Send Money / Payment' ) ),
                    esc_html__( 'Number', 'rar-woo-advance-payment' ),
                    esc_html( $this->get_option( 'bkash_number', '' ) )
                );
            case 'nagad':
                return sprintf(
                    '<strong>%s</strong><br>%s: <code>%s</code>',
                    esc_html( $this->get_option( 'nagad_type', 'Send Money / Payment' ) ),
                    esc_html__( 'Number', 'rar-woo-advance-payment' ),
                    esc_html( $this->get_option( 'nagad_number', '' ) )
                );
            case 'rocket':
                return sprintf(
                    '<strong>%s</strong><br>%s: <code>%s</code>',
                    esc_html( $this->get_option( 'rocket_type', 'Send Money' ) ),
                    esc_html__( 'Number', 'rar-woo-advance-payment' ),
                    esc_html( $this->get_option( 'rocket_number', '' ) )
                );
            case 'banglaqr':
                $url = esc_url( $this->get_option( 'banglaqr_image', '' ) );
                return sprintf(
                    '<div class="rar-wap-qr"><img src="%1$s" alt="Bangla QR" loading="lazy"></div><p>%2$s</p>',
                    $url,
                    esc_html( $this->get_option( 'banglaqr_note', '' ) )
                );
            case 'bank':
                return nl2br( esc_html( $this->get_option( 'bank_details', '' ) ) );
        }
        return '';
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }

        $amount  = $this->get_amount_due_now();
        $balance = $this->get_balance_due();
        $channels = $this->get_enabled_channels();
        ?>
        <div class="rar-wap-box">
            <div class="rar-wap-summary">
                <div><span><?php esc_html_e( 'Pay now', 'rar-woo-advance-payment' ); ?></span><strong><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong></div>
                <div><span><?php esc_html_e( 'Due on delivery', 'rar-woo-advance-payment' ); ?></span><strong><?php echo wp_kses_post( wc_price( $balance ) ); ?></strong></div>
            </div>

            <p class="rar-wap-help"><?php esc_html_e( 'Choose a payment channel, send the exact Pay now amount, then enter the payer number/account and transaction/reference ID. Never enter your PIN, password or OTP here.', 'rar-woo-advance-payment' ); ?></p>

            <div class="rar-wap-channels">
                <?php foreach ( $channels as $key => $label ) : ?>
                    <label class="rar-wap-channel">
                        <span class="rar-wap-channel-head">
                            <input type="radio" name="rar_wap_channel" value="<?php echo esc_attr( $key ); ?>" <?php checked( isset( $_POST['rar_wap_channel'] ) ? wc_clean( wp_unslash( $_POST['rar_wap_channel'] ) ) : '', $key ); ?>>
                            <strong><?php echo esc_html( $label ); ?></strong>
                        </span>
                        <span class="rar-wap-channel-details" data-rar-channel="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $this->channel_details_html( $key ) ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="form-row form-row-wide">
                <label for="rar_wap_payer"><?php esc_html_e( 'Payer mobile / bank account reference', 'rar-woo-advance-payment' ); ?> <span class="required">*</span></label>
                <input type="text" class="input-text" name="rar_wap_payer" id="rar_wap_payer" autocomplete="off" maxlength="80" value="<?php echo isset( $_POST['rar_wap_payer'] ) ? esc_attr( wc_clean( wp_unslash( $_POST['rar_wap_payer'] ) ) ) : ''; ?>">
            </p>

            <p class="form-row form-row-wide">
                <label for="rar_wap_reference"><?php esc_html_e( 'Transaction ID / Reference', 'rar-woo-advance-payment' ); ?> <span class="required">*</span></label>
                <input type="text" class="input-text" name="rar_wap_reference" id="rar_wap_reference" autocomplete="off" maxlength="120" value="<?php echo isset( $_POST['rar_wap_reference'] ) ? esc_attr( wc_clean( wp_unslash( $_POST['rar_wap_reference'] ) ) ) : ''; ?>">
            </p>

            <p class="rar-wap-safety">🔒 <?php esc_html_e( 'We will never ask for your PIN, password or OTP.', 'rar-woo-advance-payment' ); ?></p>
        </div>
        <?php
    }

    public function validate_fields() {
        $channels = $this->get_enabled_channels();
        $channel  = isset( $_POST['rar_wap_channel'] ) ? sanitize_key( wp_unslash( $_POST['rar_wap_channel'] ) ) : '';
        $payer    = isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '';
        $reference = isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '';

        if ( ! $channel || ! isset( $channels[ $channel ] ) ) {
            wc_add_notice( __( 'Please choose a valid payment channel.', 'rar-woo-advance-payment' ), 'error' );
            return false;
        }
        if ( '' === trim( $payer ) ) {
            wc_add_notice( __( 'Please enter the payer mobile number or bank account reference.', 'rar-woo-advance-payment' ), 'error' );
            return false;
        }
        if ( '' === trim( $reference ) || strlen( $reference ) < 4 ) {
            wc_add_notice( __( 'Please enter a valid transaction ID / reference.', 'rar-woo-advance-payment' ), 'error' );
            return false;
        }
        if ( preg_match( '/\b(?:otp|pin|password)\b/i', $payer . ' ' . $reference ) ) {
            wc_add_notice( __( 'Never enter a PIN, password or OTP. Please enter only the payer/account reference and transaction/reference ID.', 'rar-woo-advance-payment' ), 'error' );
            return false;
        }

        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'Unable to create the order. Please try again.', 'rar-woo-advance-payment' ), 'error' );
            return array( 'result' => 'failure' );
        }

        $channels = $this->get_enabled_channels();
        $channel  = isset( $_POST['rar_wap_channel'] ) ? sanitize_key( wp_unslash( $_POST['rar_wap_channel'] ) ) : '';
        $payer    = isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '';
        $reference = isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '';

        $amount  = $this->calculate_amount_for_order( $order );
        $balance = max( 0.0, (float) $order->get_total() - $amount );

        $order->update_meta_data( '_rar_wap_status', 'submitted' );
        $order->update_meta_data( '_rar_wap_channel', $channel );
        $order->update_meta_data( '_rar_wap_channel_label', $channels[ $channel ] ?? $channel );
        $order->update_meta_data( '_rar_wap_payer', $payer );
        $order->update_meta_data( '_rar_wap_reference', $reference );
        $order->update_meta_data( '_rar_wap_required_amount', wc_format_decimal( $amount ) );
        $order->update_meta_data( '_rar_wap_balance_due', wc_format_decimal( $balance ) );
        $order->update_meta_data( '_rar_wap_rule', $this->get_option( 'amount_rule', 'shipping' ) );
        $order->update_meta_data( '_rar_wap_submitted_at', current_time( 'mysql' ) );
        $order->save();

        $status = $this->get_option( 'after_submit_status', 'on-hold' );
        $order->update_status(
            $status,
            sprintf(
                'Advance payment submitted via %1$s. Claimed amount: %2$s. Reference: %3$s. Awaiting manual verification.',
                $channels[ $channel ] ?? $channel,
                wp_strip_all_tags( wc_price( $amount ) ),
                $reference
            )
        );

        wc_reduce_stock_levels( $order_id );

        $this->send_submission_emails( $order );

        if ( WC()->cart ) {
            WC()->cart->empty_cart();
        }

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    public function calculate_amount_for_order( WC_Order $order ) {
        $total = max( 0.0, (float) $order->get_total() );
        $rule  = $this->get_option( 'amount_rule', 'shipping' );

        switch ( $rule ) {
            case 'fixed':
                $amount = (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) );
                break;
            case 'percent':
                $percent = min( 100, max( 1, (float) $this->get_option( 'percentage', '20' ) ) );
                $amount  = $total * ( $percent / 100 );
                break;
            case 'full':
                $amount = $total;
                break;
            case 'shipping':
            default:
                $amount = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
                break;
        }

        return (float) wc_format_decimal( max( 0.0, min( $amount, $total ) ), wc_get_price_decimals() );
    }

    private function send_submission_emails( WC_Order $order ) {
        $amount    = (float) $order->get_meta( '_rar_wap_required_amount' );
        $balance   = (float) $order->get_meta( '_rar_wap_balance_due' );
        $channel   = $order->get_meta( '_rar_wap_channel_label' );
        $reference = $order->get_meta( '_rar_wap_reference' );
        $payer     = $order->get_meta( '_rar_wap_payer' );
        $order_no  = $order->get_order_number();

        $admin_email = sanitize_email( $this->get_option( 'admin_email', '' ) );
        if ( ! $admin_email ) {
            $admin_email = sanitize_email( get_option( 'admin_email' ) );
        }

        if ( 'yes' === $this->get_option( 'admin_submission_email', 'yes' ) && $admin_email ) {
            $subject = sprintf( '[%s] Advance payment submitted — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
            $body    = $this->email_wrap(
                'Advance Payment Submitted',
                sprintf(
                    '<p><strong>Order:</strong> #%1$s</p><p><strong>Customer:</strong> %2$s</p><p><strong>Channel:</strong> %3$s</p><p><strong>Claimed amount:</strong> %4$s</p><p><strong>Due on delivery:</strong> %5$s</p><p><strong>Payer reference:</strong> %6$s</p><p><strong>Transaction/reference:</strong> %7$s</p><p>Please verify the transfer before fulfilment.</p>',
                    esc_html( $order_no ),
                    esc_html( $order->get_formatted_billing_full_name() ),
                    esc_html( $channel ),
                    wp_kses_post( wc_price( $amount ) ),
                    wp_kses_post( wc_price( $balance ) ),
                    esc_html( $payer ),
                    esc_html( $reference )
                )
            );
            wp_mail( $admin_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        }

        $customer_email = sanitize_email( $order->get_billing_email() );
        if ( 'yes' === $this->get_option( 'customer_submission_email', 'no' ) && $customer_email ) {
            $subject = sprintf( '[%s] Payment submission received — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
            $body    = $this->email_wrap(
                'Payment Submission Received',
                sprintf(
                    '<p>Dear %1$s,</p><p>আপনার payment information আমরা পেয়েছি। আমাদের team transaction টি verify করবে। Verification complete হলে order processing update জানানো হবে।</p><p><strong>Pay now:</strong> %2$s<br><strong>Due on delivery:</strong> %3$s<br><strong>Method:</strong> %4$s<br><strong>Reference:</strong> %5$s</p><p><strong>Important:</strong> Never share your PIN, password or OTP with anyone.</p>',
                    esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() ),
                    wp_kses_post( wc_price( $amount ) ),
                    wp_kses_post( wc_price( $balance ) ),
                    esc_html( $channel ),
                    esc_html( $reference )
                )
            );
            wp_mail( $customer_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        }
    }

    public function send_verification_email( WC_Order $order, $verified = true ) {
        $email = sanitize_email( $order->get_billing_email() );
        if ( ! $email ) {
            return;
        }

        $order_no = $order->get_order_number();
        if ( $verified ) {
            $subject = sprintf( '[%s] Advance payment verified — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
            $body = $this->email_wrap(
                'Payment Verified ✅',
                sprintf(
                    '<p>Dear %1$s,</p><p>আপনার advance payment successfully verify হয়েছে। Thank you! আপনার order এখন processing-এ যাবে।</p><p><strong>Verified amount:</strong> %2$s<br><strong>Due on delivery:</strong> %3$s</p>',
                    esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() ),
                    wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ) ) ),
                    wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_balance_due' ) ) )
                )
            );
        } else {
            $subject = sprintf( '[%s] Payment verification needs attention — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
            $body = $this->email_wrap(
                'Payment Verification Needs Attention',
                sprintf(
                    '<p>Dear %1$s,</p><p>দুঃখিত, আপনার দেওয়া payment reference এখনো verify করা যায়নি। Please check the transaction details and contact our support team with the correct reference.</p>',
                    esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() )
                )
            );
        }

        wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    private function email_wrap( $heading, $content ) {
        $site = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
        return '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#222;line-height:1.6">'
            . '<div style="background:#0f8a6b;color:#fff;padding:20px 24px;border-radius:10px 10px 0 0"><h2 style="margin:0">' . esc_html( $heading ) . '</h2></div>'
            . '<div style="border:1px solid #e5e7eb;border-top:0;padding:24px">' . $content
            . '<hr style="border:0;border-top:1px solid #eee;margin:24px 0"><p style="font-size:12px;color:#666">' . $site . ' · Secure payment reference notice</p></div></div>';
    }
}
