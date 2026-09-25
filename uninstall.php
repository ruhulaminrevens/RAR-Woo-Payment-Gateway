<?php
/**
 * Uninstall: remove plugin settings and scheduled jobs only.
 *
 * Historical order payment metadata (Transaction IDs, verification audit trail)
 * and stored payment screenshots are intentionally preserved for accounting.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_rar_advance_payment_settings' );
delete_option( 'rar_wap_version' );
delete_transient( 'rar_wap_pending_count' );
delete_transient( 'rar_wap_schedule_checked' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'rar_wap_maintenance' );
	as_unschedule_all_actions( 'rar_wap_dispatch_webhook' );
	as_unschedule_all_actions( 'rar_wap_send_submission_emails' );
}
