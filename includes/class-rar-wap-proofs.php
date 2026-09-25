<?php
/**
 * Private storage for customer payment screenshots.
 *
 * Files live in uploads/rar-wap-proofs/ with random names, a deny-all
 * .htaccess (LiteSpeed/Apache) and are only served to staff through a
 * nonce-checked admin endpoint.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Proofs {

	const DIR = 'rar-wap-proofs';

	const MIMES = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'pdf'      => 'application/pdf',
	);

	public static function init() {
		add_action( 'admin_post_rar_wap_proof', array( __CLASS__, 'serve' ) );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'delete_for_order' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for_order' ) );
	}

	public static function base_dir() {
		$upload = wp_upload_dir( null, false );
		return trailingslashit( $upload['basedir'] ) . self::DIR;
	}

	public static function protect_directory() {
		$dir = self::base_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$files = array(
			'.htaccess'  => "# RAR WAP private proofs\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'index.html' => '',
		);
		foreach ( $files as $name => $content ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return true;
	}

	private static function path_for( WC_Order $order ) {
		$relative = (string) $order->get_meta( '_rar_wap_proof_file' );
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return '';
		}
		$path = self::base_dir() . '/' . ltrim( $relative, '/' );
		return is_file( $path ) ? $path : '';
	}

	public static function is_image( WC_Order $order ) {
		return in_array( (string) $order->get_meta( '_rar_wap_proof_mime' ), array( 'image/jpeg', 'image/png', 'image/webp' ), true );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function handle_customer_upload( WC_Order $order ) {
		if ( $order->get_meta( '_rar_wap_proof_file' ) ) {
			return new WP_Error( 'rar_wap_proof_exists', RAR_WAP_I18n::t( 'proof_received' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by caller.
		if ( empty( $_FILES['rar_wap_proof'] ) || ! is_array( $_FILES['rar_wap_proof'] ) ) {
			return new WP_Error( 'rar_wap_proof_missing', __( 'Please choose a file to upload.', 'rar-woo-advance-payment' ) );
		}
		$file = $_FILES['rar_wap_proof']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable

		$max_mb = min( 10, max( 1, absint( RAR_WAP_Plugin::settings()['proof_max_mb'] ?? 4 ) ) );

		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'rar_wap_proof_error', __( 'The upload failed. Please try again with a smaller file.', 'rar-woo-advance-payment' ) );
		}
		if ( (int) $file['size'] > $max_mb * MB_IN_BYTES ) {
			/* translators: %d: MB */
			return new WP_Error( 'rar_wap_proof_size', sprintf( __( 'File is too large. Maximum %d MB.', 'rar-woo-advance-payment' ), $max_mb ) );
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( (string) $file['name'] ), self::MIMES );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'rar_wap_proof_type', __( 'Only JPG, PNG, WebP or PDF files are allowed.', 'rar-woo-advance-payment' ) );
		}
		if ( 0 === strpos( $check['type'], 'image/' ) && ! @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'rar_wap_proof_image', __( 'The image could not be read.', 'rar-woo-advance-payment' ) );
		}

		if ( ! self::protect_directory() ) {
			return new WP_Error( 'rar_wap_proof_dir', __( 'Upload storage is not writable. Please contact support.', 'rar-woo-advance-payment' ) );
		}

		$sub = gmdate( 'Y/m' );
		wp_mkdir_p( self::base_dir() . '/' . $sub );
		$name     = $order->get_id() . '-' . strtolower( wp_generate_password( 24, false, false ) ) . '.' . $check['ext'];
		$relative = $sub . '/' . $name;
		$target   = self::base_dir() . '/' . $relative;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new WP_Error( 'rar_wap_proof_move', __( 'The file could not be saved. Please try again.', 'rar-woo-advance-payment' ) );
		}
		chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		$order->update_meta_data( '_rar_wap_proof_file', $relative );
		$order->update_meta_data( '_rar_wap_proof_mime', $check['type'] );
		$order->update_meta_data( '_rar_wap_proof_uploaded_at', current_time( 'mysql' ) );
		RAR_WAP_Order::add_history( $order, 'proof_uploaded', '', $order->get_customer_id() );
		$order->save();
		$order->add_order_note( __( 'Customer uploaded a payment screenshot (see Advance Payment panel).', 'rar-woo-advance-payment' ) );

		RAR_WAP_Emails::admin_update( $order, 'proof_uploaded' );
		RAR_WAP_Order::fire( 'proof_uploaded', $order );
		return true;
	}

	public static function admin_url( WC_Order $order ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'rar_wap_proof',
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			'rar_wap_proof_' . $order->get_id()
		);
	}

	public static function admin_preview_html( WC_Order $order ) {
		if ( ! $order->get_meta( '_rar_wap_proof_file' ) ) {
			return '';
		}

		$url  = self::admin_url( $order );
		$html = '<div class="rar-wap-proof"><strong>📎 ' . esc_html__( 'Payment screenshot', 'rar-woo-advance-payment' ) . '</strong> <small>' . esc_html( (string) $order->get_meta( '_rar_wap_proof_uploaded_at' ) ) . '</small>';
		if ( self::is_image( $order ) ) {
			$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Payment screenshot', 'rar-woo-advance-payment' ) . '" loading="lazy"></a>';
		} else {
			$html .= '<br><a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open PDF', 'rar-woo-advance-payment' ) . '</a>';
		}
		return $html . '</div>';
	}

	public static function serve() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view this file.', 'rar-woo-advance-payment' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rar_wap_proof_' . $order_id );

		$order = wc_get_order( $order_id );
		$path  = $order ? self::path_for( $order ) : '';
		if ( ! $path ) {
			wp_die( esc_html__( 'File not found.', 'rar-woo-advance-payment' ), '', array( 'response' => 404 ) );
		}

		$mime = (string) $order->get_meta( '_rar_wap_proof_mime' );
		$mime = in_array( $mime, array_values( self::MIMES ), true ) ? $mime : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="order-' . $order_id . '-payment-proof.' . pathinfo( $path, PATHINFO_EXTENSION ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Remove the stored proof when an order is permanently deleted.
	 *
	 * @param int $order_id
	 */
	public static function delete_for_order( $order_id ) {
		if ( 'before_delete_post' === current_filter() && 'shop_order' !== get_post_type( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$path = self::path_for( $order );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
