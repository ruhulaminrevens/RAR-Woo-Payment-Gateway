<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAR_WAP_Display {
    public static function init() {
        add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_summary' ), 20 );
        add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_summary' ), 20, 4 );
        add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'inject_totals' ), 20, 3 );
    }

    public static function inject_totals( $totals, $order, $tax_display ) {
        if ( ! $order instanceof WC_Order || 'rar_advance_payment' !== $order->get_payment_method() ) {
            return $totals;
        }

        $amount  = (float) $order->get_meta( '_rar_wap_required_amount' );
        $balance = (float) $order->get_meta( '_rar_wap_balance_due' );
        $status  = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';

        $extra = array(
            'rar_wap_advance' => array(
                'label' => __( 'Advance payment:', 'rar-woo-advance-payment' ),
                'value' => wc_price( $amount, array( 'currency' => $order->get_currency() ) ) . ' — ' . esc_html( ucfirst( $status ) ),
            ),
            'rar_wap_due' => array(
                'label' => __( 'Due on delivery:', 'rar-woo-advance-payment' ),
                'value' => wc_price( $balance, array( 'currency' => $order->get_currency() ) ),
            ),
        );

        if ( isset( $totals['payment_method'] ) ) {
            $before = array();
            foreach ( $totals as $key => $row ) {
                if ( 'payment_method' === $key ) {
                    $before += $extra;
                }
                $before[ $key ] = $row;
            }
            return $before;
        }

        return $totals + $extra;
    }

    public static function order_summary( $order ) {
        if ( ! $order instanceof WC_Order || 'rar_advance_payment' !== $order->get_payment_method() ) {
            return;
        }
        self::render_summary( $order );
    }

    public static function email_summary( $order, $sent_to_admin, $plain_text, $email ) {
        if ( $plain_text || ! $order instanceof WC_Order || 'rar_advance_payment' !== $order->get_payment_method() ) {
            return;
        }
        self::render_summary( $order );
    }

    private static function render_summary( WC_Order $order ) {
        $status = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';
        $amount = (float) $order->get_meta( '_rar_wap_required_amount' );
        $balance = (float) $order->get_meta( '_rar_wap_balance_due' );
        $channel = $order->get_meta( '_rar_wap_channel_label' );
        ?>
        <section class="rar-wap-order-summary" style="margin:18px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px">
            <h3 style="margin:0 0 10px"><?php esc_html_e( 'Payment Summary', 'rar-woo-advance-payment' ); ?></h3>
            <p style="margin:4px 0"><strong><?php esc_html_e( 'Method:', 'rar-woo-advance-payment' ); ?></strong> <?php echo esc_html( $channel ); ?></p>
            <p style="margin:4px 0"><strong><?php esc_html_e( 'Advance:', 'rar-woo-advance-payment' ); ?></strong> <?php echo wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ); ?> — <?php echo esc_html( ucfirst( $status ) ); ?></p>
            <p style="margin:4px 0"><strong><?php esc_html_e( 'Due on delivery:', 'rar-woo-advance-payment' ); ?></strong> <?php echo wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ); ?></p>
        </section>
        <?php
    }
}
