<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAR_WAP_Admin {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'admin_post_rar_wap_verify', array( __CLASS__, 'handle_verify' ) );
        add_action( 'admin_post_rar_wap_reject', array( __CLASS__, 'handle_reject' ) );
        add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_legacy_column' ), 25 );
        add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 25, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_hpos_column' ), 25 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_column' ), 25, 2 );
    }

    public static function add_meta_box() {
        $screen = 'shop_order';
        if ( function_exists( 'wc_get_page_screen_id' ) ) {
            try {
                $screen = wc_get_page_screen_id( 'shop-order' );
            } catch ( Throwable $e ) {
                $screen = 'shop_order';
            }
        }

        add_meta_box(
            'rar-wap-payment-box',
            __( 'Advance Payment', 'rar-woo-advance-payment' ),
            array( __CLASS__, 'render_meta_box' ),
            $screen,
            'side',
            'high'
        );

        if ( 'shop_order' !== $screen ) {
            add_meta_box(
                'rar-wap-payment-box',
                __( 'Advance Payment', 'rar-woo-advance-payment' ),
                array( __CLASS__, 'render_meta_box' ),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    private static function get_order_from_screen( $object ) {
        if ( $object instanceof WC_Order ) {
            return $object;
        }
        if ( $object instanceof WP_Post ) {
            return wc_get_order( $object->ID );
        }
        if ( is_numeric( $object ) ) {
            return wc_get_order( absint( $object ) );
        }
        return false;
    }

    public static function render_meta_box( $object ) {
        $order = self::get_order_from_screen( $object );
        if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
            echo '<p>' . esc_html__( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) . '</p>';
            return;
        }

        $status    = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';
        $amount    = (float) $order->get_meta( '_rar_wap_required_amount' );
        $balance   = (float) $order->get_meta( '_rar_wap_balance_due' );
        $channel   = $order->get_meta( '_rar_wap_channel_label' );
        $payer     = $order->get_meta( '_rar_wap_payer' );
        $reference = $order->get_meta( '_rar_wap_reference' );
        $verify_url = wp_nonce_url( admin_url( 'admin-post.php?action=rar_wap_verify&order_id=' . $order->get_id() ), 'rar_wap_verify_' . $order->get_id() );
        $reject_url = wp_nonce_url( admin_url( 'admin-post.php?action=rar_wap_reject&order_id=' . $order->get_id() ), 'rar_wap_reject_' . $order->get_id() );

        echo '<p><strong>Status:</strong> ' . esc_html( ucfirst( $status ) ) . '</p>';
        echo '<p><strong>Channel:</strong> ' . esc_html( $channel ) . '<br>';
        echo '<strong>Pay now:</strong> ' . wp_kses_post( wc_price( $amount ) ) . '<br>';
        echo '<strong>Due on delivery:</strong> ' . wp_kses_post( wc_price( $balance ) ) . '<br>';
        echo '<strong>Payer:</strong> ' . esc_html( $payer ) . '<br>';
        echo '<strong>Reference:</strong> <code>' . esc_html( $reference ) . '</code></p>';

        if ( 'verified' !== $status ) {
            echo '<p><a class="button button-primary" href="' . esc_url( $verify_url ) . '">' . esc_html__( 'Verify Payment', 'rar-woo-advance-payment' ) . '</a> ';
            echo '<a class="button" href="' . esc_url( $reject_url ) . '">' . esc_html__( 'Mark Unverified', 'rar-woo-advance-payment' ) . '</a></p>';
        } else {
            echo '<p style="color:#087f5b"><strong>✓ Payment verified</strong></p>';
        }
    }

    public static function handle_verify() {
        self::assert_admin_request( 'rar_wap_verify' );
        $order_id = absint( $_GET['order_id'] ?? 0 );
        check_admin_referer( 'rar_wap_verify_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Order not found.', 'rar-woo-advance-payment' ) );
        }

        $order->update_meta_data( '_rar_wap_status', 'verified' );
        $order->update_meta_data( '_rar_wap_verified_at', current_time( 'mysql' ) );
        $order->update_meta_data( '_rar_wap_verified_by', get_current_user_id() );
        $order->save();

        $gateway = self::gateway_instance();
        $target_status = $gateway ? $gateway->get_option( 'after_verify_status', 'processing' ) : 'processing';
        if ( $target_status && ! $order->has_status( $target_status ) ) {
            $order->update_status( $target_status, 'Advance payment manually verified by admin.' );
        } else {
            $order->add_order_note( 'Advance payment manually verified by admin.' );
        }
        if ( $gateway ) {
            $gateway->send_verification_email( $order, true );
        }

        wp_safe_redirect( $order->get_edit_order_url() );
        exit;
    }

    public static function handle_reject() {
        self::assert_admin_request( 'rar_wap_reject' );
        $order_id = absint( $_GET['order_id'] ?? 0 );
        check_admin_referer( 'rar_wap_reject_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Order not found.', 'rar-woo-advance-payment' ) );
        }

        $order->update_meta_data( '_rar_wap_status', 'unverified' );
        $order->update_meta_data( '_rar_wap_rejected_at', current_time( 'mysql' ) );
        $order->save();
        $order->add_order_note( 'Advance payment marked unverified by admin. Customer notified.' );

        $gateway = self::gateway_instance();
        if ( $gateway ) {
            $gateway->send_verification_email( $order, false );
        }

        wp_safe_redirect( $order->get_edit_order_url() );
        exit;
    }

    private static function assert_admin_request( $action ) {
        if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage this order.', 'rar-woo-advance-payment' ) );
        }
    }

    private static function gateway_instance() {
        if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
            return false;
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        return ( isset( $gateways['rar_advance_payment'] ) && $gateways['rar_advance_payment'] instanceof RAR_WAP_Gateway ) ? $gateways['rar_advance_payment'] : false;
    }

    public static function add_legacy_column( $columns ) {
        $columns['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
        return $columns;
    }

    public static function render_legacy_column( $column, $post_id ) {
        if ( 'rar_wap_payment' === $column ) {
            self::render_column_value( wc_get_order( $post_id ) );
        }
    }

    public static function add_hpos_column( $columns ) {
        $columns['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
        return $columns;
    }

    public static function render_hpos_column( $column, $order ) {
        if ( 'rar_wap_payment' === $column ) {
            self::render_column_value( self::get_order_from_screen( $order ) );
        }
    }

    private static function render_column_value( $order ) {
        if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
            echo '—';
            return;
        }
        $status = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';
        $amount = (float) $order->get_meta( '_rar_wap_required_amount' );
        $balance = (float) $order->get_meta( '_rar_wap_balance_due' );
        echo '<strong>' . esc_html( ucfirst( $status ) ) . '</strong><br><small>' . wp_kses_post( wc_price( $amount ) ) . ' / due ' . wp_kses_post( wc_price( $balance ) ) . '</small>';
    }
}
