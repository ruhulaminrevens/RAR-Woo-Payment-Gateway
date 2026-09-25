/**
 * RAR Advance Payment — order screen panel, dashboard queue and tools.
 */
( function ( $ ) {
	'use strict';

	var A = window.rarWapAdmin || {};
	var I = A.i18n || {};

	function request( data ) {
		return $.post( A.ajaxUrl, $.extend( { nonce: A.nonce }, data ) );
	}

	function errorMessage( xhr ) {
		return ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) || I.failed;
	}

	function updateBadge( count ) {
		var $badge = $( '#adminmenu a[href*="page=rar-wap-payments"] .awaiting-mod' );
		if ( ! $badge.length ) {
			return;
		}
		if ( count > 0 ) {
			$badge.attr( 'class', 'awaiting-mod count-' + count ).find( '.pending-count' ).text( count );
		} else {
			$badge.remove();
		}
	}

	/* Order edit panel. */
	$( document ).on( 'click', '.rar-wap-panel .rar-wap-act', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $panel = $btn.closest( '.rar-wap-panel' );
		var op = $btn.data( 'op' );
		var confirmText = { verify: I.confirmVerify, reject: I.confirmReject, reset: I.confirmReset }[ op ];

		if ( confirmText && ! window.confirm( confirmText ) ) {
			return;
		}

		var data = { action: 'rar_wap_order_action', op: op, order_id: $panel.data( 'order-id' ) };
		if ( op === 'verify' ) {
			data.amount = $panel.find( '.rar-wap-amount' ).val();
			data.note = $panel.find( '.rar-wap-note' ).val();
		} else if ( op === 'reject' ) {
			data.reason = $panel.find( '.rar-wap-reason' ).val();
			data.note = $panel.find( '.rar-wap-reject-note' ).val();
		}

		$panel.find( 'button' ).prop( 'disabled', true );
		$panel.find( '.rar-wap-panel-msg' ).removeClass( 'is-error' ).text( I.working );

		request( data ).done( function ( res ) {
			if ( res && res.success ) {
				$panel.html( res.data.panel );
				$panel.find( '.rar-wap-panel-msg' ).text( res.data.message );
				updateBadge( res.data.pending );
				// Reflect the new WooCommerce order status in the edit form without a reload.
				if ( res.data.order_status ) {
					$( '#order_status option' ).filter( function () {
						return $( this ).text() === res.data.order_status;
					} ).prop( 'selected', true ).trigger( 'change.select2' );
				}
			} else {
				$panel.find( '.rar-wap-panel-msg' ).addClass( 'is-error' ).text( ( res && res.data && res.data.message ) || I.failed );
				$panel.find( 'button' ).prop( 'disabled', false );
			}
		} ).fail( function ( xhr ) {
			$panel.find( '.rar-wap-panel-msg' ).addClass( 'is-error' ).text( errorMessage( xhr ) );
			$panel.find( 'button' ).prop( 'disabled', false );
		} );
	} );

	/* Dashboard queue rows. */
	$( document ).on( 'click', '.rar-wap-row-act', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $row = $btn.closest( 'tr' );
		var op = $btn.data( 'op' );
		var data = { action: 'rar_wap_order_action', op: op, order_id: $row.data( 'order-id' ) };

		if ( op === 'verify' ) {
			var amount = window.prompt( I.amountPrompt, String( $row.data( 'amount' ) ) );
			if ( amount === null ) {
				return;
			}
			data.amount = amount;
		} else if ( op === 'reject' ) {
			var note = window.prompt( I.reasonPrompt, '' );
			if ( note === null ) {
				return;
			}
			data.reason = 'not_found';
			data.note = note;
		}

		$row.find( 'button' ).prop( 'disabled', true );
		$row.addClass( 'is-working' );

		request( data ).done( function ( res ) {
			$row.removeClass( 'is-working' );
			if ( res && res.success ) {
				$row.find( '.rar-wap-row-status' ).html( $( '<span/>' ).addClass( 'rar-wap-column-status ' + res.data.status ).text( res.data.status_label ) );
				$row.find( '.rar-wap-row-actions' ).text( '✓' );
				$row.addClass( 'is-done' );
				updateBadge( res.data.pending );
			} else {
				window.alert( ( res && res.data && res.data.message ) || I.failed );
				$row.find( 'button' ).prop( 'disabled', false );
			}
		} ).fail( function ( xhr ) {
			$row.removeClass( 'is-working' );
			window.alert( errorMessage( xhr ) );
			$row.find( 'button' ).prop( 'disabled', false );
		} );
	} );

	/* Dashboard tools. */
	$( document ).on( 'click', '.rar-wap-tool', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $msg = $( '.rar-wap-tool-msg' );
		$btn.prop( 'disabled', true );
		$msg.removeClass( 'is-error' ).text( I.working );

		request( { action: 'rar_wap_tool', tool: $btn.data( 'tool' ) } ).done( function ( res ) {
			$msg.toggleClass( 'is-error', ! ( res && res.success ) ).text( ( res && res.data && res.data.message ) || I.failed );
		} ).fail( function ( xhr ) {
			$msg.addClass( 'is-error' ).text( errorMessage( xhr ) );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* Confirm the bulk "verify" action on the orders list. */
	$( document ).on( 'submit', '#posts-filter, #wc-orders-filter', function () {
		var action = $( this ).find( 'select[name="action"]' ).val();
		var action2 = $( this ).find( 'select[name="action2"]' ).val();
		if ( ( action === 'rar_wap_bulk_verify' || action2 === 'rar_wap_bulk_verify' ) && ! window.confirm( I.confirmBulk ) ) {
			return false;
		}
		return true;
	} );
}( jQuery ) );
