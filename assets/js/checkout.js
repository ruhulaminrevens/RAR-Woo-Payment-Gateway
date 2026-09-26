/**
 * RAR Advance Payment — classic checkout behaviour (v2.1).
 */
( function ( $ ) {
	'use strict';

	var S = window.rarWapCheckout || {};
	var memory = { channel: '', payer: '', reference: '' };

	function bnDigits( value ) {
		return String( value || '' ).replace( /[০-৯]/g, function ( d ) {
			return String( '০১২৩৪৫৬৭৮৯'.indexOf( d ) );
		} );
	}

	function normalizeMobile( value ) {
		var digits = bnDigits( value ).replace( /\D+/g, '' );
		if ( digits.indexOf( '880' ) === 0 ) {
			digits = digits.substring( 2 );
		}
		return /^01[3-9]\d{8,9}$/.test( digits ) ? digits : '';
	}

	function selectedTile() {
		return $( '.rar-wap input[name="rar_wap_channel"]:checked' ).closest( '.rw-tile' );
	}

	function sync() {
		var $root = $( '.rar-wap' );
		if ( ! $root.length ) {
			return;
		}
		var value = $root.find( 'input[name="rar_wap_channel"]:checked' ).val() || '';
		$root.find( '.rw-tile' ).removeClass( 'is-on' ).attr( 'aria-checked', 'false' );
		var $tile = selectedTile().addClass( 'is-on' ).attr( 'aria-checked', 'true' );

		$root.find( '.rw-panel' ).each( function () {
			this.hidden = $( this ).data( 'panel' ) !== value;
		} );
		$root.find( '.rw-pick' ).prop( 'hidden', !! value );

		var type = String( $tile.data( 'type' ) || '' );
		var $payer = $( '#rar_wap_payer' );
		if ( $tile.length ) {
			$root.find( '.rw-payer-label' ).text( $tile.data( 'payer' ) );
		}
		$payer.attr( {
			placeholder: type === 'mfs' ? ( S.ph_mobile || '01XXXXXXXXX' ) : ( S.ph_account || '' ),
			inputmode: type === 'mfs' ? 'tel' : 'text',
		} );
		$( '#rar_wap_reference' ).attr( 'placeholder', type === 'bank' ? ( S.ph_bank_trx || '' ) : ( S.ph_trx || '' ) );
	}

	function setError( $input, message ) {
		var $field = $input.closest( '.rw-field' );
		$field.removeClass( 'is-bad' ).find( '.rw-err' ).remove();
		if ( message ) {
			$field.addClass( 'is-bad' );
			$( '<span class="rw-err" role="alert"></span>' ).text( message ).appendTo( $field );
		}
	}

	function remember() {
		if ( ! $( '#rar_wap_payer' ).length ) {
			return;
		}
		memory.channel = $( 'input[name="rar_wap_channel"]:checked' ).val() || memory.channel;
		memory.payer = $( '#rar_wap_payer' ).val() || '';
		memory.reference = $( '#rar_wap_reference' ).val() || '';
	}

	/* WooCommerce re-renders the payment box on every checkout update; restore what was typed. */
	function restore() {
		if ( memory.channel && ! $( 'input[name="rar_wap_channel"]:checked' ).length ) {
			$( 'input[name="rar_wap_channel"]' ).filter( function () {
				return this.value === memory.channel;
			} ).prop( 'checked', true );
		}
		if ( memory.payer && ! $( '#rar_wap_payer' ).val() ) {
			$( '#rar_wap_payer' ).val( memory.payer );
		}
		if ( memory.reference && ! $( '#rar_wap_reference' ).val() ) {
			$( '#rar_wap_reference' ).val( memory.reference );
		}
	}

	function legacyCopy( value ) {
		var input = document.createElement( 'textarea' );
		input.value = value;
		input.setAttribute( 'readonly', 'readonly' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {}
		document.body.removeChild( input );
		return ok;
	}

	function copyText( value, button ) {
		var $btn = $( button );
		var $label = $btn.find( 'span' );
		var done = function () {
			var old = $btn.data( 'label' ) || $label.text();
			$btn.data( 'label', old ).addClass( 'is-done' );
			$label.text( S.copied || 'Copied' );
			if ( navigator.vibrate ) {
				navigator.vibrate( 20 );
			}
			window.setTimeout( function () {
				$btn.removeClass( 'is-done' );
				$label.text( old );
			}, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then( done ).catch( function () {
				if ( legacyCopy( value ) ) {
					done();
				}
			} );
		} else if ( legacyCopy( value ) ) {
			done();
		}
	}

	$( document.body ).on( 'change', '.rar-wap input[name="rar_wap_channel"]', function () {
		sync();
		remember();
		setError( $( '#rar_wap_payer' ), '' );
	} );

	$( document.body ).on( 'input', '#rar_wap_payer, #rar_wap_reference', function () {
		setError( $( this ), '' );
		remember();
	} );

	$( document.body ).on( 'blur', '#rar_wap_payer', function () {
		var $input = $( this );
		var value = bnDigits( $input.val() ).trim();
		if ( String( selectedTile().data( 'type' ) ) === 'mfs' && S.validateMfs && value ) {
			var mobile = normalizeMobile( value );
			$input.val( mobile || value );
			setError( $input, mobile ? '' : ( S.err_mobile || '' ) );
		} else {
			$input.val( value );
		}
		remember();
	} );

	$( document.body ).on( 'blur', '#rar_wap_reference', function () {
		var $input = $( this );
		var value = bnDigits( $input.val() ).toUpperCase().replace( /\s+/g, '' );
		$input.val( value );
		setError( $input, value && ! /^[A-Z0-9][A-Z0-9\-_\/.#]{3,39}$/.test( value ) ? ( S.err_trx || '' ) : '' );
		remember();
	} );

	$( document.body ).on( 'click', '.rar-wap .rw-copy', function ( e ) {
		e.preventDefault();
		e.stopPropagation();
		copyText( String( $( this ).data( 'copy' ) || '' ), this );
	} );

	$( document.body ).on( 'update_checkout', remember );
	$( document.body ).on( 'updated_checkout payment_method_selected', function () {
		restore();
		sync();
	} );

	$( sync );
}( jQuery ) );
