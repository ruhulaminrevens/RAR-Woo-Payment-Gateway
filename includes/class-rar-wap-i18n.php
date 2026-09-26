<?php
/**
 * Customer-facing text (English / বাংলা / bilingual).
 *
 * In bilingual mode only the few key headings carry a Bangla sub-line, so the
 * checkout stays short and readable. Everything else uses the primary language.
 * English strings pass through __() so .po/.mo translations still work.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_I18n {

	/** Keys shown in both languages when mode = both. */
	const DUAL = array( 'step_choose', 'step_send', 'step_confirm', 'secure', 'status_title_submitted', 'status_title_verified', 'status_title_unverified' );

	/** @var array<string,array{0:string,1:string}>|null */
	private static $strings = null;

	/** @var string|null */
	private static $mode = null;

	public static function mode() {
		if ( null === self::$mode ) {
			$settings   = get_option( 'woocommerce_rar_advance_payment_settings', array() );
			$mode       = is_array( $settings ) && isset( $settings['customer_language'] ) ? (string) $settings['customer_language'] : 'both';
			self::$mode = in_array( $mode, array( 'en', 'bn', 'both' ), true ) ? $mode : 'both';
		}
		return self::$mode;
	}

	public static function reset() {
		self::$mode    = null;
		self::$strings = null;
	}

	private static function strings() {
		if ( null !== self::$strings ) {
			return self::$strings;
		}

		self::$strings = array(
			// Checkout.
			'pay_now'                 => array( __( 'Pay now', 'rar-woo-advance-payment' ), 'এখন দিন' ),
			'on_delivery'             => array( __( 'On delivery', 'rar-woo-advance-payment' ), 'ডেলিভারিতে' ),
			'nothing_on_delivery'     => array( __( 'Nothing to pay on delivery', 'rar-woo-advance-payment' ), 'ডেলিভারিতে কিছু দিতে হবে না' ),
			'step_choose'             => array( __( 'Choose how you pay', 'rar-woo-advance-payment' ), 'পেমেন্ট মাধ্যম বাছুন' ),
			'step_send'               => array( __( 'Send the money', 'rar-woo-advance-payment' ), 'টাকা পাঠান' ),
			'step_confirm'            => array( __( 'Confirm your payment', 'rar-woo-advance-payment' ), 'পেমেন্ট নিশ্চিত করুন' ),
			'pick_first'              => array( __( 'Select a method above to see where to send the money.', 'rar-woo-advance-payment' ), 'কোথায় টাকা পাঠাবেন দেখতে উপরে একটি মাধ্যম বাছুন।' ),
			'send_to'                 => array( __( 'Send to', 'rar-woo-advance-payment' ), 'এই নম্বরে পাঠান' ),
			'account_no'              => array( __( 'Account number', 'rar-woo-advance-payment' ), 'অ্যাকাউন্ট নম্বর' ),
			'amount'                  => array( __( 'Amount', 'rar-woo-advance-payment' ), 'পরিমাণ' ),
			'scan_qr'                 => array( __( 'Scan this QR with your banking or MFS app', 'rar-woo-advance-payment' ), 'ব্যাংক বা MFS অ্যাপ দিয়ে QR স্ক্যান করুন' ),
			'open_qr'                 => array( __( 'Open full size', 'rar-woo-advance-payment' ), 'বড় করে দেখুন' ),
			'copy'                    => array( __( 'Copy', 'rar-woo-advance-payment' ), 'কপি' ),
			'copied'                  => array( __( 'Copied', 'rar-woo-advance-payment' ), 'কপি হয়েছে' ),
			/* translators: %s: channel name */
			'payer_label'             => array( __( 'Your %s number', 'rar-woo-advance-payment' ), 'আপনার %s নম্বর' ),
			'payer_label_generic'     => array( __( 'Number / account you paid from', 'rar-woo-advance-payment' ), 'যে নম্বর/অ্যাকাউন্ট থেকে পাঠিয়েছেন' ),
			'trx_label'               => array( __( 'Transaction ID (TrxID)', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি (TrxID)' ),
			'trx_hint'                => array( __( 'Find it in the confirmation SMS or app history.', 'rar-woo-advance-payment' ), 'কনফার্মেশন SMS বা অ্যাপের হিস্টোরিতে পাবেন।' ),
			'secure'                  => array( __( 'Never share your PIN or OTP. We check every payment before shipping.', 'rar-woo-advance-payment' ), 'PIN বা OTP কখনো দেবেন না। শিপিংয়ের আগে আমরা পেমেন্ট যাচাই করি।' ),
			'ph_mobile'               => array( __( '01XXXXXXXXX', 'rar-woo-advance-payment' ), '01XXXXXXXXX' ),
			'ph_account'              => array( __( 'Account no. or last 4 digits', 'rar-woo-advance-payment' ), 'অ্যাকাউন্ট নম্বর বা শেষ ৪ ডিজিট' ),
			'ph_trx'                  => array( __( 'e.g. 8N7A6B5C4D', 'rar-woo-advance-payment' ), 'যেমন 8N7A6B5C4D' ),
			'ph_bank_trx'             => array( __( 'Transfer reference / trace ID', 'rar-woo-advance-payment' ), 'ট্রান্সফার রেফারেন্স / ট্রেস আইডি' ),

			// Validation.
			'err_channel'             => array( __( 'Please choose a payment method.', 'rar-woo-advance-payment' ), 'একটি পেমেন্ট মাধ্যম বাছাই করুন।' ),
			'err_payer'               => array( __( 'Please enter the number or account you paid from.', 'rar-woo-advance-payment' ), 'যে নম্বর/অ্যাকাউন্ট থেকে পাঠিয়েছেন সেটি লিখুন।' ),
			'err_mobile'              => array( __( 'Please enter a valid mobile number (01XXXXXXXXX).', 'rar-woo-advance-payment' ), 'সঠিক মোবাইল নম্বর লিখুন (01XXXXXXXXX)।' ),
			'err_trx'                 => array( __( 'Please enter a valid Transaction ID (4–40 letters or digits).', 'rar-woo-advance-payment' ), 'সঠিক ট্রানজেকশন আইডি লিখুন (৪–৪০ অক্ষর/সংখ্যা)।' ),
			'err_sensitive'           => array( __( 'Never enter a PIN, password or OTP. Enter only your number and the Transaction ID.', 'rar-woo-advance-payment' ), 'কখনো PIN, পাসওয়ার্ড বা OTP লিখবেন না। শুধু নম্বর ও ট্রানজেকশন আইডি দিন।' ),
			'err_duplicate'           => array( __( 'This Transaction ID was already used for another order. Please check it or contact us.', 'rar-woo-advance-payment' ), 'এই ট্রানজেকশন আইডি আগে অন্য অর্ডারে ব্যবহার হয়েছে। আইডি যাচাই করুন বা আমাদের জানান।' ),
			'err_amount'              => array( __( 'The advance amount could not be calculated. Please refresh the page or choose another payment method.', 'rar-woo-advance-payment' ), 'অগ্রিম টাকার পরিমাণ হিসাব করা যায়নি। পেজ রিফ্রেশ করুন বা অন্য পেমেন্ট পদ্ধতি বাছুন।' ),

			// Order page.
			'status_submitted'        => array( __( 'Checking payment', 'rar-woo-advance-payment' ), 'পেমেন্ট যাচাই চলছে' ),
			'status_verified'         => array( __( 'Payment confirmed', 'rar-woo-advance-payment' ), 'পেমেন্ট নিশ্চিত' ),
			'status_unverified'       => array( __( 'Needs your attention', 'rar-woo-advance-payment' ), 'আপনার সংশোধন প্রয়োজন' ),
			'status_title_submitted'  => array( __( 'We are checking your payment', 'rar-woo-advance-payment' ), 'আপনার পেমেন্ট যাচাই করা হচ্ছে' ),
			'status_title_verified'   => array( __( 'Your advance payment is confirmed', 'rar-woo-advance-payment' ), 'আপনার অগ্রিম পেমেন্ট নিশ্চিত হয়েছে' ),
			'status_title_unverified' => array( __( 'We could not find your payment', 'rar-woo-advance-payment' ), 'আপনার পেমেন্ট খুঁজে পাওয়া যায়নি' ),
			'status_text_submitted'   => array( __( 'This usually takes a short while. You will get an update by e-mail.', 'rar-woo-advance-payment' ), 'সাধারণত অল্প সময় লাগে। ই-মেইলে আপডেট পাবেন।' ),
			'status_text_verified'    => array( __( 'Thank you! Your order is moving to the next step.', 'rar-woo-advance-payment' ), 'ধন্যবাদ! আপনার অর্ডার পরবর্তী ধাপে যাচ্ছে।' ),
			'status_text_unverified'  => array( __( 'Please check the Transaction ID below and send it again.', 'rar-woo-advance-payment' ), 'নিচে ট্রানজেকশন আইডি যাচাই করে আবার পাঠান।' ),
			'track_sent'              => array( __( 'Sent', 'rar-woo-advance-payment' ), 'পাঠানো হয়েছে' ),
			'track_checking'          => array( __( 'Checking', 'rar-woo-advance-payment' ), 'যাচাই চলছে' ),
			'track_done'              => array( __( 'Confirmed', 'rar-woo-advance-payment' ), 'নিশ্চিত' ),
			'method'                  => array( __( 'Method', 'rar-woo-advance-payment' ), 'মাধ্যম' ),
			'advance'                 => array( __( 'Advance', 'rar-woo-advance-payment' ), 'অগ্রিম' ),
			'reference'               => array( __( 'Transaction ID', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি' ),
			'payment_status'          => array( __( 'Advance payment', 'rar-woo-advance-payment' ), 'অগ্রিম পেমেন্ট' ),
			'reason'                  => array( __( 'Reason', 'rar-woo-advance-payment' ), 'কারণ' ),
			'fix_title'               => array( __( 'Send the correct details', 'rar-woo-advance-payment' ), 'সঠিক তথ্য আবার পাঠান' ),
			'fix_button'              => array( __( 'Send for checking', 'rar-woo-advance-payment' ), 'যাচাইয়ের জন্য পাঠান' ),
			'proof_title'             => array( __( 'Add a payment screenshot', 'rar-woo-advance-payment' ), 'পেমেন্টের স্ক্রিনশট দিন' ),
			'proof_help'              => array( __( 'Optional — helps us confirm faster. JPG, PNG, WebP or PDF.', 'rar-woo-advance-payment' ), 'ঐচ্ছিক — দ্রুত যাচাইয়ে সাহায্য করে। JPG, PNG, WebP বা PDF।' ),
			'proof_choose'            => array( __( 'Choose file', 'rar-woo-advance-payment' ), 'ফাইল বাছুন' ),
			'proof_button'            => array( __( 'Upload', 'rar-woo-advance-payment' ), 'আপলোড' ),
			'proof_received'          => array( __( 'Screenshot received — thank you!', 'rar-woo-advance-payment' ), 'স্ক্রিনশট পাওয়া গেছে — ধন্যবাদ!' ),
			'resubmitted'             => array( __( 'Thank you! Your updated details were sent for checking.', 'rar-woo-advance-payment' ), 'ধন্যবাদ! আপনার সংশোধিত তথ্য যাচাইয়ের জন্য পাঠানো হয়েছে।' ),
		);

		return self::$strings;
	}

	private static function pair( $key ) {
		$strings = self::strings();
		return $strings[ $key ] ?? array( $key, $key );
	}

	/**
	 * Plain text in the primary language (notices, attributes, JS).
	 *
	 * @param string $key
	 * @param mixed  ...$args sprintf arguments.
	 */
	public static function t( $key, ...$args ) {
		list( $en, $bn ) = self::pair( $key );
		$text            = 'bn' === self::mode() ? $bn : $en;
		return $args ? vsprintf( $text, $args ) : $text;
	}

	/**
	 * Escaped HTML. Key headings get a Bangla sub-line in bilingual mode.
	 *
	 * @param string $key
	 * @param mixed  ...$args sprintf arguments.
	 */
	public static function h( $key, ...$args ) {
		list( $en, $bn ) = self::pair( $key );
		if ( $args ) {
			$en = vsprintf( $en, $args );
			$bn = vsprintf( $bn, $args );
		}

		switch ( self::mode() ) {
			case 'bn':
				return esc_html( $bn );
			case 'both':
				if ( in_array( $key, self::DUAL, true ) ) {
					return esc_html( $en ) . '<span class="rw-bn" lang="bn">' . esc_html( $bn ) . '</span>';
				}
				return esc_html( $en );
			default:
				return esc_html( $en );
		}
	}

	public static function e( $key, ...$args ) {
		echo self::h( $key, ...$args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in h().
	}

	/**
	 * Strings for JavaScript (checkout + blocks).
	 */
	public static function js_strings() {
		$out = array();
		foreach ( array_keys( self::strings() ) as $key ) {
			$out[ $key ] = self::t( $key );
		}
		$out['_bn'] = array();
		if ( 'both' === self::mode() ) {
			foreach ( self::DUAL as $key ) {
				$out['_bn'][ $key ] = self::pair( $key )[1];
			}
		}
		return $out;
	}
}
