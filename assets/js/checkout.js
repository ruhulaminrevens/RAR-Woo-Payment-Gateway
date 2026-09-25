/**
 * RAR Advance Payment — classic checkout behaviour.
 */
( function ( $ ) {
	'use strict';

	var L = window.rarWapCheckout || {};
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

	function channelType() {
		var $checked = $( 'input[name="rar_wap_channel"]:checked' );
		return $checked.length ? String( $checked.closest( '.rar-wap-channel' ).data( 'type' ) || '' ) : '';
	}

	function sync() {
		$( '.rar-wap-channel' ).removeClass( 'is-active' );
		$( '.rar-wap-channel input[type="radio"]:checked' ).closest( '.rar-wap-channel' ).addClass( 'is-active' );
	}

	function updatePlaceholders() {
		var type = channelType();
		var $payer = $( '#rar_wap_payer' );
		var $reference = $( '#rar_wap_reference' );

		if ( type === 'mfs' ) {
			$payer.attr( { placeholder: L.phMobile || '01XXXXXXXXX', inputmode: 'tel' } );
			$reference.attr( 'placeholder', L.phTrx || '' );
		} else if ( type === 'bank' ) {
			$payer.attr( { placeholder: L.phAccount || '', inputmode: 'text' } );
			$reference.attr( 'placeholder', L.phBankTrx || '' );
		} else {
			$payer.attr( { placeholder: L.phGeneric || '', inputmode: 'text' } );
			$reference.attr( 'placeholder', L.phTrx || '' );
		}
	}

	function setFieldError( $field, message ) {
		var $row = $field.closest( '.form-row' );
		$row.find( '.rar-wap-inline-error' ).remove();
		$row.removeClass( 'woocommerce-invalid' );
		if ( message ) {
			$row.addClass( 'woocommerce-invalid' );
			$( '<small class="rar-wap-inline-error" role="alert"></small>' ).text( message ).appendTo( $row );
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

	/** WooCommerce re-renders the payment box on every checkout update; restore what the customer typed. */
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

	function legacyCopy( value, done ) {
		var input = document.createElement( 'textarea' );
		input.value = value;
		input.setAttribute( 'readonly', 'readonly' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		document.body.removeChild( input );
	}

	function copyText( value, button ) {
		if ( ! value ) {
			return;
		}
		var done = function () {
			var $btn = $( button );
			var old = $btn.data( 'label' ) || $btn.text();
			$btn.data( 'label', old ).addClass( 'is-copied' ).text( L.copied || 'Copied ✓' );
			window.setTimeout( function () {
				$btn.removeClass( 'is-copied' ).text( old );
			}, 1400 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then( done ).catch( function () {
				legacyCopy( value, done );
			} );
		} else {
			legacyCopy( value, done );
		}
	}

	$( document.body ).on( 'change', 'input[name="rar_wap_channel"]', function () {
		sync();
		updatePlaceholders();
		remember();
		setFieldError( $( '#rar_wap_payer' ), '' );
	} );

	$( document.body ).on( 'input', '#rar_wap_payer, #rar_wap_reference', remember );

	$( document.body ).on( 'blur', '#rar_wap_payer', function () {
		var $field = $( this );
		var value = bnDigits( $field.val() ).trim();
		if ( channelType() === 'mfs' && L.validateMfs && value ) {
			var mobile = normalizeMobile( value );
			if ( mobile ) {
				$field.val( mobile );
				setFieldError( $field, '' );
			} else {
				setFieldError( $field, L.errMobile || '' );
			}
		} else {
			$field.val( value );
			setFieldError( $field, '' );
		}
		remember();
	} );

	$( document.body ).on( 'blur', '#rar_wap_reference', function () {
		var $field = $( this );
		var value = bnDigits( $field.val() ).toUpperCase().replace( /\s+/g, '' );
		$field.val( value );
		setFieldError( $field, value && ! /^[A-Z0-9][A-Z0-9\-_\/.#]{3,39}$/.test( value ) ? ( L.errTrx || '' ) : '' );
		remember();
	} );

	$( document.body ).on( 'click', '.rar-wap-copy', function ( e ) {
		e.preventDefault();
		e.stopPropagation();
		copyText( String( $( this ).data( 'copy' ) || '' ), this );
	} );

	$( document.body ).on( 'update_checkout', remember );

	$( document.body ).on( 'updated_checkout payment_method_selected', function () {
		restore();
		sync();
		updatePlaceholders();
	} );

	$( function () {
		sync();
		updatePlaceholders();
	} );
}( jQuery ) );
