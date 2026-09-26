<?php
/**
 * Checkout Blocks integration.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class RAR_WAP_Blocks extends AbstractPaymentMethodType {

	/** @var string */
	protected $name = 'rar_advance_payment';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_rar_advance_payment_settings', array() );
	}

	public function is_active() {
		$gateway = RAR_WAP_Plugin::gateway();
		return $gateway ? $gateway->is_configured_for_checkout() : false;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'rar-wap-blocks',
			RAR_WAP_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-data' ),
			RAR_WAP_VERSION,
			true
		);
		wp_register_style( 'rar-wap-checkout', RAR_WAP_URL . 'assets/css/checkout.css', array(), RAR_WAP_VERSION );
		wp_enqueue_style( 'rar-wap-checkout' );

		return array( 'rar-wap-blocks' );
	}

	public function get_payment_method_data() {
		$gateway = RAR_WAP_Plugin::gateway();
		if ( ! $gateway ) {
			return array( 'visible' => false );
		}

		$channels = array();
		foreach ( $gateway->get_channel_config() as $channel ) {
			$channels[] = array(
				'key'         => $channel['key'],
				'type'        => $channel['type'],
				'label'       => $channel['label'],
				'instruction' => $channel['instruction'],
				'number'      => $channel['number'],
				'details'     => $channel['details'],
				'qr'          => $channel['qr'],
				'logo'        => $channel['logo'],
			);
		}

		return array(
			'title'       => $gateway->get_title(),
			'description' => wp_strip_all_tags( $gateway->get_description() ),
			'visible'     => $gateway->is_visible_to_current_user(),
			'showLogos'   => 'no' !== $gateway->get_option( 'show_logos_in_title', 'yes' ),
			'validateMfs' => 'no' !== $gateway->get_option( 'validate_mobile', 'yes' ),
			'buttonText'  => trim( (string) $gateway->get_option( 'order_button_text', '' ) ),
			'channels'    => $channels,
			'strings'     => RAR_WAP_I18n::js_strings(),
			'accent'      => RAR_WAP_Gateway::accent_color( $gateway->get_option( 'accent_color', '' ) ),
			'tints'       => RAR_WAP_Gateway::TINTS,
			'supports'    => array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ),
		);
	}
}
