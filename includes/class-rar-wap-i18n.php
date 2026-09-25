<?php
/**
 * Customer-facing bilingual text (English / বাংলা / both).
 *
 * English strings pass through __() so .po/.mo translations still work.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_I18n {

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
			'pay_now'            => array( __( 'Pay now', 'rar-woo-advance-payment' ), 'এখন পরিশোধ' ),
			'due_on_delivery'    => array( __( 'Due on delivery', 'rar-woo-advance-payment' ), 'ডেলিভারিতে পরিশোধ' ),
			'transfer_exact'     => array( __( 'Transfer this exact amount', 'rar-woo-advance-payment' ), 'ঠিক এই পরিমাণ টাকা পাঠান' ),
			'remaining_balance'  => array( __( 'Remaining order balance', 'rar-woo-advance-payment' ), 'অর্ডারের বাকি টাকা' ),
			'fully_paid_now'     => array( __( 'Nothing due on delivery', 'rar-woo-advance-payment' ), 'ডেলিভারিতে কিছু দিতে হবে না' ),
			'step_channel'       => array( __( 'Choose channel', 'rar-woo-advance-payment' ), 'মাধ্যম বাছাই করুন' ),
			'step_pay'           => array( __( 'Pay exact amount', 'rar-woo-advance-payment' ), 'সঠিক পরিমাণ পাঠান' ),
			'step_submit'        => array( __( 'Submit Transaction ID', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি দিন' ),
			'trust_manual'       => array( __( 'Manual verification', 'rar-woo-advance-payment' ), 'ম্যানুয়াল যাচাই' ),
			'trust_nopin'        => array( __( 'No PIN / OTP', 'rar-woo-advance-payment' ), 'PIN / OTP লাগবে না' ),
			'trust_linked'       => array( __( 'Order-linked reference', 'rar-woo-advance-payment' ), 'অর্ডার-সংযুক্ত রেফারেন্স' ),
			'help'               => array(
				__( 'Select a channel below, complete the transfer in your banking/MFS app, then enter the number you paid from and the Transaction ID.', 'rar-woo-advance-payment' ),
				'নিচে একটি মাধ্যম বেছে নিন, আপনার ব্যাংক/MFS অ্যাপ থেকে টাকা পাঠান, তারপর যে নম্বর থেকে পাঠিয়েছেন সেটি ও ট্রানজেকশন আইডি লিখুন।',
			),
			'tap_view'           => array( __( 'Tap to view payment instructions', 'rar-woo-advance-payment' ), 'পেমেন্ট নির্দেশনা দেখতে ট্যাপ করুন' ),
			'instruction'        => array( __( 'Instruction', 'rar-woo-advance-payment' ), 'নির্দেশনা' ),
			'account_type'       => array( __( 'Account', 'rar-woo-advance-payment' ), 'অ্যাকাউন্ট' ),
			'copy'               => array( __( 'Copy', 'rar-woo-advance-payment' ), 'কপি' ),
			'copied'             => array( __( 'Copied ✓', 'rar-woo-advance-payment' ), 'কপি হয়েছে ✓' ),
			'qr_enlarge'         => array( __( 'Tap / click the QR to view full size', 'rar-woo-advance-payment' ), 'বড় করে দেখতে QR-এ ট্যাপ করুন' ),
			'payer_label'        => array( __( 'Paid from (mobile / account number)', 'rar-woo-advance-payment' ), 'যে নম্বর/অ্যাকাউন্ট থেকে পাঠিয়েছেন' ),
			'trx_label'          => array( __( 'Transaction ID / Reference', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি / রেফারেন্স' ),
			'trx_note'           => array( __( 'Used only to match your transfer with this order.', 'rar-woo-advance-payment' ), 'শুধু আপনার পেমেন্ট এই অর্ডারের সাথে মেলাতে ব্যবহার হবে।' ),
			'security_title'     => array( __( 'Security notice:', 'rar-woo-advance-payment' ), 'নিরাপত্তা সতর্কতা:' ),
			'security'           => array(
				__( 'We will never ask for your PIN, password, OTP or card security code.', 'rar-woo-advance-payment' ),
				'আমরা কখনো আপনার PIN, পাসওয়ার্ড, OTP বা কার্ডের সিকিউরিটি কোড চাইব না।',
			),
			'verification_note'  => array(
				__( 'Submitting a Transaction ID does not automatically verify the payment. We confirm the transfer before fulfilment.', 'rar-woo-advance-payment' ),
				'ট্রানজেকশন আইডি জমা দিলেই পেমেন্ট নিশ্চিত হয় না। ডেলিভারির আগে আমরা পেমেন্ট যাচাই করব।',
			),
			'ph_mobile'          => array( __( 'e.g. 01XXXXXXXXX', 'rar-woo-advance-payment' ), 'যেমন 01XXXXXXXXX' ),
			'ph_account'         => array( __( 'Account number / last 4 digits', 'rar-woo-advance-payment' ), 'অ্যাকাউন্ট নম্বর / শেষ ৪ ডিজিট' ),
			'ph_generic_payer'   => array( __( '01XXXXXXXXX or account reference', 'rar-woo-advance-payment' ), '01XXXXXXXXX বা অ্যাকাউন্ট রেফারেন্স' ),
			'ph_trx'             => array( __( 'Enter the Transaction ID exactly', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি হুবহু লিখুন' ),
			'ph_bank_trx'        => array( __( 'Bank transfer reference / trace ID', 'rar-woo-advance-payment' ), 'ব্যাংক ট্রান্সফার রেফারেন্স / ট্রেস আইডি' ),
			'err_channel'        => array( __( 'Please choose a payment channel.', 'rar-woo-advance-payment' ), 'অনুগ্রহ করে একটি পেমেন্ট মাধ্যম বাছাই করুন।' ),
			'err_payer'          => array( __( 'Please enter the mobile number or account you paid from.', 'rar-woo-advance-payment' ), 'যে নম্বর/অ্যাকাউন্ট থেকে টাকা পাঠিয়েছেন সেটি লিখুন।' ),
			'err_mobile'         => array( __( 'Please enter a valid Bangladeshi mobile number (e.g. 01XXXXXXXXX).', 'rar-woo-advance-payment' ), 'সঠিক মোবাইল নম্বর লিখুন (যেমন 01XXXXXXXXX)।' ),
			'err_trx'            => array( __( 'Please enter a valid Transaction ID / Reference (4–40 letters or digits).', 'rar-woo-advance-payment' ), 'সঠিক ট্রানজেকশন আইডি লিখুন (৪–৪০ অক্ষর/সংখ্যা)।' ),
			'err_sensitive'      => array( __( 'Never enter a PIN, password, OTP or security code. Enter only the paying number and the Transaction ID.', 'rar-woo-advance-payment' ), 'কখনো PIN, পাসওয়ার্ড বা OTP লিখবেন না। শুধু নম্বর ও ট্রানজেকশন আইডি লিখুন।' ),
			'err_duplicate'      => array( __( 'This Transaction ID has already been used for another order. Please check it or contact support.', 'rar-woo-advance-payment' ), 'এই ট্রানজেকশন আইডি আগে অন্য অর্ডারে ব্যবহার হয়েছে। আইডি যাচাই করুন বা সাপোর্টে যোগাযোগ করুন।' ),
			'err_amount'         => array( __( 'The advance amount could not be calculated. Please refresh checkout or choose another payment method.', 'rar-woo-advance-payment' ), 'অগ্রিম টাকার পরিমাণ হিসাব করা যায়নি। পেজ রিফ্রেশ করুন বা অন্য পেমেন্ট পদ্ধতি বেছে নিন।' ),
			'status_submitted'   => array( __( 'Awaiting verification', 'rar-woo-advance-payment' ), 'যাচাইয়ের অপেক্ষায়' ),
			'status_verified'    => array( __( 'Verified', 'rar-woo-advance-payment' ), 'যাচাই সম্পন্ন' ),
			'status_unverified'  => array( __( 'Needs attention', 'rar-woo-advance-payment' ), 'সংশোধন প্রয়োজন' ),
			'payment_status'     => array( __( 'Advance payment status', 'rar-woo-advance-payment' ), 'অগ্রিম পেমেন্টের অবস্থা' ),
			'method'             => array( __( 'Method', 'rar-woo-advance-payment' ), 'মাধ্যম' ),
			'advance'            => array( __( 'Advance', 'rar-woo-advance-payment' ), 'অগ্রিম' ),
			'reference'          => array( __( 'Transaction ID', 'rar-woo-advance-payment' ), 'ট্রানজেকশন আইডি' ),
			'update_details'     => array( __( 'Update payment details', 'rar-woo-advance-payment' ), 'পেমেন্টের তথ্য সংশোধন করুন' ),
			'update_help'        => array( __( 'We could not match your payment yet. Please check and re-submit the correct details.', 'rar-woo-advance-payment' ), 'আপনার পেমেন্ট এখনো মেলানো যায়নি। সঠিক তথ্য আবার জমা দিন।' ),
			'resubmit_button'    => array( __( 'Re-submit for verification', 'rar-woo-advance-payment' ), 'আবার যাচাইয়ের জন্য পাঠান' ),
			'proof_title'        => array( __( 'Payment screenshot (optional)', 'rar-woo-advance-payment' ), 'পেমেন্টের স্ক্রিনশট (ঐচ্ছিক)' ),
			'proof_help'         => array( __( 'Upload a screenshot of the successful transfer to speed up verification. JPG, PNG, WebP or PDF.', 'rar-woo-advance-payment' ), 'দ্রুত যাচাইয়ের জন্য সফল পেমেন্টের স্ক্রিনশট আপলোড করুন। JPG, PNG, WebP বা PDF।' ),
			'proof_button'       => array( __( 'Upload screenshot', 'rar-woo-advance-payment' ), 'স্ক্রিনশট আপলোড করুন' ),
			'proof_received'     => array( __( 'Screenshot received. Thank you!', 'rar-woo-advance-payment' ), 'স্ক্রিনশট পাওয়া গেছে। ধন্যবাদ!' ),
			'resubmitted'        => array( __( 'Thank you. Your updated payment details were sent for verification.', 'rar-woo-advance-payment' ), 'ধন্যবাদ। আপনার সংশোধিত তথ্য যাচাইয়ের জন্য পাঠানো হয়েছে।' ),
			'reason'             => array( __( 'Reason', 'rar-woo-advance-payment' ), 'কারণ' ),
			'next_step_pending'  => array( __( 'We are checking your transfer. You will be notified once it is verified.', 'rar-woo-advance-payment' ), 'আমরা আপনার পেমেন্ট যাচাই করছি। যাচাই হলে আপনাকে জানানো হবে।' ),
			'next_step_verified' => array( __( 'Your advance payment is confirmed. Thank you!', 'rar-woo-advance-payment' ), 'আপনার অগ্রিম পেমেন্ট নিশ্চিত হয়েছে। ধন্যবাদ!' ),
		);

		return self::$strings;
	}

	/**
	 * Plain-text string (for notices, attributes and JS).
	 */
	public static function t( $key ) {
		$strings = self::strings();
		if ( ! isset( $strings[ $key ] ) ) {
			return $key;
		}
		list( $en, $bn ) = $strings[ $key ];

		switch ( self::mode() ) {
			case 'en':
				return $en;
			case 'bn':
				return $bn;
			default:
				return $en . ' / ' . $bn;
		}
	}

	/**
	 * Escaped HTML string. In "both" mode, the Bangla part is a separate span.
	 */
	public static function h( $key ) {
		$strings = self::strings();
		if ( ! isset( $strings[ $key ] ) ) {
			return esc_html( $key );
		}
		list( $en, $bn ) = $strings[ $key ];

		switch ( self::mode() ) {
			case 'en':
				return esc_html( $en );
			case 'bn':
				return esc_html( $bn );
			default:
				return esc_html( $en ) . ' <span class="rar-wap-bn" lang="bn">' . esc_html( $bn ) . '</span>';
		}
	}

	public static function e( $key ) {
		echo self::h( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in h().
	}
}
