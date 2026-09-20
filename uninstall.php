<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove only gateway settings. Historical order payment metadata is intentionally preserved.
delete_option( 'woocommerce_rar_advance_payment_settings' );
