/**
 * RAR Advance Payment — dashboard, order panel and orders-list interactions (v2.1).
 */
( function ( $ ) {
	'use strict';

	var A = window.rarWapAdmin || {};
	var I = A.i18n || {};
	var baseTitle = document.title.replace( /^\(\d+\)\s*/, '' );

	function request( data ) {
		return $.post( A.ajaxUrl, $.extend( { nonce: A.nonce }, data ) );
	}

	function errorMessage( xhr ) {
		return ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) || I.failed;
	}

	/* ---------- Toasts ---------- */
	function toast( message, type, action ) {
		var $wrap = $( '.rwa-toasts' );
		if ( ! $wrap.length ) {
			$wrap = $( '<div class="rwa-toasts" aria-live="polite"></div>' ).appendTo( document.body );
		}
		var $t = $( '<div class="rwa-toast"></div>' ).toggleClass( 'is-error', type === 'error' ).append( $( '<span></span>' ).text( message ) );
		if ( action ) {
			$( '<button type="button"></button>' ).text( action.label ).on( 'click', function () {
				action.run();
				$t.remove();
			} ).appendTo( $t );
		}
		$wrap.append( $t );
		window.setTimeout( function () {
			$t.fadeOut( 200, function () { $t.remove(); } );
		}, action ? 12000 : 4000 );
	}

	/* ---------- Badge + title ---------- */
	function updateBadge( count ) {
		count = parseInt( count, 10 ) || 0;
		var $link = $( '#adminmenu a[href*="page=rar-wap-payments"]' );
		var $badge = $link.find( '.awaiting-mod' );
		if ( count > 0 ) {
			if ( ! $badge.length ) {
				$badge = $( '<span class="awaiting-mod"><span class="pending-count"></span></span>' ).appendTo( $link );
				$link.append( ' ' );
			}
			$badge.attr( 'class', 'awaiting-mod count-' + count ).find( '.pending-count' ).text( count );
		} else {
			$badge.remove();
		}
		if ( $( '.rwa' ).length ) {
			document.title = ( count > 0 ? '(' + count + ') ' : '' ) + baseTitle;
		}
	}

	/* ---------- Copy chips ---------- */
	function copy( value, $el ) {
		var done = function () {
			$el.addClass( 'is-done' );
			window.setTimeout( function () { $el.removeClass( 'is-done' ); }, 1200 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then( done );
			return;
		}
		var t = document.createElement( 'textarea' );
		t.value = value;
		t.style.position = 'fixed';
		t.style.opacity = '0';
		document.body.appendChild( t );
		t.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		document.body.removeChild( t );
	}

	$( document ).on( 'click', '.rwa-chip', function ( e ) {
		e.preventDefault();
		copy( String( $( this ).data( 'copy' ) ), $( this ) );
		toast( I.copied || 'Copied' );
	} );

	/* ---------- Live region refresh ---------- */
	function refreshLive( quiet ) {
		var $live = $( '#rwa-live' );
		if ( ! $live.length ) {
			return $.Deferred().resolve().promise();
		}
		$live.addClass( 'is-refreshing' );
		return $.get( window.location.href ).done( function ( html ) {
			var doc = new DOMParser().parseFromString( html, 'text/html' );
			var fresh = doc.getElementById( 'rwa-live' );
			var root = doc.querySelector( '.rwa' );
			if ( fresh ) {
				$live.html( fresh.innerHTML );
			}
			if ( root ) {
				$( '.rwa' ).attr( 'data-poll', root.getAttribute( 'data-poll' ) );
			}
			if ( ! quiet ) {
				toast( I.updated || 'Updated' );
			}
		} ).always( function () {
			$live.removeClass( 'is-refreshing' );
		} );
	}

	/* ---------- Polling for new payments ---------- */
	function poll() {
		if ( document.hidden || ! $( '.rwa' ).length || $( '#rwa-modal' ).is( ':visible' ) ) {
			return;
		}
		request( { action: 'rar_wap_poll' } ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				return;
			}
			updateBadge( res.data.pending );
			var current = String( $( '.rwa' ).attr( 'data-poll' ) || '' );
			if ( res.data.token !== current ) {
				var before = parseInt( current, 10 ) || 0;
				$( '.rwa' ).attr( 'data-poll', res.data.token );
				if ( res.data.pending > before ) {
					toast( I.newPayment || 'New payment received', 'info', { label: I.refresh || 'Refresh', run: function () { refreshLive( true ); } } );
				} else {
					refreshLive( true );
				}
			}
		} );
	}

	/* ---------- Modal (dashboard) ---------- */
	var $modal = $();
	var current = null;

	function openModal( op, $row ) {
		$modal = $( '#rwa-modal' );
		if ( ! $modal.length ) {
			return;
		}
		current = { op: op, id: $row.data( 'order-id' ), $row: $row };
		var titles = { verify: I.verifyTitle, reject: I.rejectTitle, reset: I.resetTitle };
		var buttons = { verify: I.verifyBtn, reject: I.rejectBtn, reset: I.resetBtn };
		$modal.find( '#rwa-modal-title' ).text( ( titles[ op ] || '' ) + ' #' + $row.data( 'number' ) );
		$modal.find( '.rwa-modal-go' ).text( buttons[ op ] || 'OK' ).toggleClass( 'is-primary', op !== 'reject' ).toggleClass( 'is-danger', op === 'reject' );
		$modal.find( '.rwa-modal-body' ).each( function () {
			this.hidden = $( this ).data( 'for' ) !== op;
		} );
		var facts = [
			[ I.channel, $row.data( 'channel' ) ],
			[ 'TrxID', $row.data( 'ref' ) ],
			[ I.paidFrom, $row.data( 'payer' ) ],
			[ I.orderTotal, $row.data( 'total' ) ],
		];
		var $dl = $modal.find( '.rwa-modal-facts' ).empty();
		facts.forEach( function ( f ) {
			$( '<div></div>' ).append( $( '<dt></dt>' ).text( f[ 0 ] ), $( '<dd></dd>' ).text( f[ 1 ] || '—' ) ).appendTo( $dl );
		} );
		$modal.find( 'input[name="amount"]' ).val( $row.data( 'amount' ) );
		$modal.find( 'input[name="note"]' ).val( '' );
		$modal.find( 'select[name="reason"]' ).prop( 'selectedIndex', 0 );
		$modal.prop( 'hidden', false );
		window.setTimeout( function () {
			$modal.find( '.rwa-modal-body:not([hidden]) :input:first, .rwa-modal-go' ).first().trigger( 'focus' );
		}, 30 );
	}

	function closeModal() {
		$( '#rwa-modal' ).prop( 'hidden', true );
		current = null;
	}

	$( document ).on( 'click', '.rwa-open', function ( e ) {
		e.preventDefault();
		openModal( $( this ).data( 'op' ), $( this ).closest( '.rwa-row' ) );
	} );
	$( document ).on( 'click', '#rwa-modal [data-close]', closeModal );
	$( document ).on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && current ) {
			closeModal();
		}
	} );

	$( document ).on( 'submit', '#rwa-modal form', function ( e ) {
		e.preventDefault();
		if ( ! current ) {
			return;
		}
		var $body = $( this ).find( '.rwa-modal-body:not([hidden])' );
		var data = { action: 'rar_wap_order_action', op: current.op, order_id: current.id };
		if ( current.op === 'verify' ) {
			data.amount = $body.find( 'input[name="amount"]' ).val();
			data.note = $body.find( 'input[name="note"]' ).val();
		} else if ( current.op === 'reject' ) {
			data.reason = $body.find( 'select[name="reason"]' ).val();
			data.note = $body.find( 'input[name="note"]' ).val();
		}
		var $row = current.$row;
		var $go = $( this ).find( '.rwa-modal-go' ).prop( 'disabled', true );
		$row.addClass( 'is-busy' );

		request( data ).done( function ( res ) {
			if ( res && res.success ) {
				closeModal();
				updateBadge( res.data.pending );
				toast( res.data.message );
				refreshLive( true );
			} else {
				toast( ( res && res.data && res.data.message ) || I.failed, 'error' );
				$row.removeClass( 'is-busy' );
			}
		} ).fail( function ( xhr ) {
			toast( errorMessage( xhr ), 'error' );
			$row.removeClass( 'is-busy' );
		} ).always( function () {
			$go.prop( 'disabled', false );
		} );
	} );

	/* ---------- Dashboard filters ---------- */
	$( document ).on( 'change', '.rwa-range', function () {
		var custom = $( this ).val() === 'custom';
		$( this ).closest( 'form' ).find( '.rwa-dates' ).prop( 'hidden', ! custom );
		if ( ! custom ) {
			$( this ).closest( 'form' ).find( '.rwa-dates input' ).val( '' );
			$( this ).closest( 'form' ).trigger( 'submit' );
		}
	} );
	$( document ).on( 'change', '.rwa-toolbar select[name="channel"]', function () {
		$( this ).closest( 'form' ).trigger( 'submit' );
	} );

	/* ---------- Tools ---------- */
	$( document ).on( 'click', '.rwa-tool', function ( e ) {
		e.preventDefault();
		var $btn = $( this ).prop( 'disabled', true );
		request( { action: 'rar_wap_tool', tool: $btn.data( 'tool' ) } ).done( function ( res ) {
			toast( ( res && res.data && res.data.message ) || I.failed, res && res.success ? '' : 'error' );
		} ).fail( function ( xhr ) {
			toast( errorMessage( xhr ), 'error' );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* ---------- Order edit panel ---------- */
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
				toast( res.data.message );
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

	/* ---------- Orders list bulk confirm ---------- */
	$( document ).on( 'submit', '#posts-filter, #wc-orders-filter', function () {
		var action = $( this ).find( 'select[name="action"]' ).val();
		var action2 = $( this ).find( 'select[name="action2"]' ).val();
		if ( ( action === 'rar_wap_bulk_verify' || action2 === 'rar_wap_bulk_verify' ) && ! window.confirm( I.confirmBulk ) ) {
			return false;
		}
		return true;
	} );

	$( function () {
		if ( $( '.rwa' ).length ) {
			updateBadge( parseInt( $( '.rwa' ).attr( 'data-poll' ), 10 ) || 0 );
			window.setInterval( poll, 45000 );
			document.addEventListener( 'visibilitychange', function () {
				if ( ! document.hidden ) {
					poll();
				}
			} );
		}
	} );
}( jQuery ) );
