/**
 * RAR Advance Payment — WooCommerce Checkout Blocks integration (v2.1).
 * Plain ES5 + wp.element (no build step needed on shared hosting).
 */
( function () {
	'use strict';

	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings || ! window.wp || ! window.wp.element ) {
		return;
	}

	var registry = window.wc.wcBlocksRegistry;
	var settings = window.wc.wcSettings.getSetting( 'rar_advance_payment_data', {} ) || {};
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var decode = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ) || function ( s ) { return s; };
	var S = settings.strings || {};
	var BN = S._bn || {};
	var channels = settings.channels || [];
	var tints = settings.tints || {};
	var title = decode( settings.title || 'Advance Payment' );
	var ICONS = {
		mfs: 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm0 3v13h10V5H7zm5 14.2a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z',
		qr: 'M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v3h-3v-3zm4 0h3v7h-3v-3h-2v-2h2v-2zm-4 5h2v2h-2v-2z',
		bank: 'M12 3 3 7v2h18V7l-9-4zM5 11h2v7H5v-7zm4 0h2v7H9v-7zm4 0h2v7h-2v-7zm4 0h2v7h-2v-7zM3 20h18v2H3v-2z',
		copy: 'M8 3h9a2 2 0 0 1 2 2v11h-2V5H8V3zM5 7h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zm0 2v10h9V9H5z',
		shield: 'M12 2 4 5v6c0 5 3.4 9.6 8 11 4.6-1.4 8-6 8-11V5l-8-3zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.8-4.8 1.4 1.4-6.2 6.2z',
	};

	function t( key ) {
		return S[ key ] || key;
	}

	function fmt( key, value ) {
		return t( key ).replace( '%s', value );
	}

	function dual( key ) {
		return BN[ key ] ? [ t( key ), el( 'span', { className: 'rw-bn', lang: 'bn', key: 'bn' }, BN[ key ] ) ] : t( key );
	}

	function svg( name ) {
		return el( 'svg', { viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false' }, el( 'path', { d: ICONS[ name ] || ICONS.mfs } ) );
	}

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

	function normalizeRef( value ) {
		return bnDigits( value ).toUpperCase().replace( /\s+/g, '' );
	}

	function useCartAmounts() {
		if ( ! window.wp.data || ! window.wp.data.useSelect ) {
			return null;
		}
		return window.wp.data.useSelect( function ( select ) {
			var store = select( 'wc/store/cart' );
			var cart = store && store.getCartData ? store.getCartData() : null;
			return cart && cart.extensions && cart.extensions[ 'rar-wap' ] ? cart.extensions[ 'rar-wap' ] : null;
		}, [] );
	}

	function copyText( value, done ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then( done ).catch( function () {} );
			return;
		}
		var input = document.createElement( 'textarea' );
		input.value = value;
		input.setAttribute( 'readonly', 'readonly' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		try {
			if ( document.execCommand( 'copy' ) ) {
				done();
			}
		} catch ( e ) {}
		document.body.removeChild( input );
	}

	function CopyButton( props ) {
		var state = useState( false );
		return el(
			'button',
			{
				type: 'button',
				className: 'rw-copy' + ( state[ 0 ] ? ' is-done' : '' ),
				'aria-label': props.label || t( 'copy' ),
				onClick: function ( e ) {
					e.preventDefault();
					e.stopPropagation();
					copyText( props.value, function () {
						state[ 1 ]( true );
						window.setTimeout( function () { state[ 1 ]( false ); }, 1500 );
					} );
				},
			},
			svg( 'copy' ),
			el( 'span', null, state[ 0 ] ? t( 'copied' ) : t( 'copy' ) )
		);
	}

	function Icon( props ) {
		var c = props.channel;
		if ( c.logo ) {
			return el( 'span', { className: 'rw-ico has-logo', 'aria-hidden': 'true' }, el( 'img', { src: c.logo, alt: '' } ) );
		}
		return el( 'span', { className: 'rw-ico', style: { '--rw-tint': tints[ c.key ] || tints.custom }, 'aria-hidden': 'true' }, svg( c.type ) );
	}

	function Row( label, value, copyValue, mono ) {
		return el( 'div', { className: 'rw-row', key: label },
			el( 'div', null, el( 'small', null, label ), el( 'strong', { className: mono ? 'rw-mono' : '' }, value ) ),
			copyValue ? el( CopyButton, { value: copyValue } ) : null
		);
	}

	function Panel( props ) {
		var c = props.channel;
		var parts = [];
		if ( c.type === 'qr' ) {
			parts.push( el( 'div', { className: 'rw-qr', key: 'qr' },
				el( 'a', { href: c.qr, target: '_blank', rel: 'noopener' }, el( 'img', { src: c.qr, alt: 'Bangla QR' } ) ),
				el( 'p', null, c.instruction || t( 'scan_qr' ) ),
				el( 'a', { className: 'rw-link', href: c.qr, target: '_blank', rel: 'noopener' }, t( 'open_qr' ) + ' ↗' )
			) );
		} else if ( c.number ) {
			var label = c.type === 'mfs' ? t( 'send_to' ) + ( c.instruction ? ' · ' + c.instruction : '' ) : t( 'account_no' );
			parts.push( Row( label, c.number, c.number.replace( /[^0-9A-Za-z]/g, '' ), true ) );
		}
		if ( props.amounts ) {
			parts.push( Row( t( 'amount' ), props.amounts.amount_due_text, props.amounts.amount_due, false ) );
		}
		if ( c.details ) {
			parts.push( el( 'div', { className: 'rw-details', key: 'd' }, c.details.split( /\n/ ).map( function ( line, i ) {
				return el( 'div', { key: i }, line );
			} ) ) );
		}
		return el( 'div', { className: 'rw-panel' }, parts );
	}

	function Step( n, key ) {
		return el( 'div', { className: 'rw-step' }, el( 'i', null, n ), el( 'span', null, dual( key ) ) );
	}

	function Content( props ) {
		var amounts = useCartAmounts();
		var chState = useState( channels.length === 1 ? channels[ 0 ].key : '' );
		var payerState = useState( '' );
		var refState = useState( '' );
		var errState = useState( {} );
		var channel = chState[ 0 ];
		var payer = payerState[ 0 ];
		var reference = refState[ 0 ];
		var errors = errState[ 0 ];
		var eventRegistration = props.eventRegistration || {};
		var emitResponse = props.emitResponse || {};
		var onSetup = eventRegistration.onPaymentSetup || eventRegistration.onPaymentProcessing;
		var types = emitResponse.responseTypes || { SUCCESS: 'success', ERROR: 'error' };

		var selected = null;
		channels.forEach( function ( c ) {
			if ( c.key === channel ) {
				selected = c;
			}
		} );
		var isMfs = selected && selected.type === 'mfs';

		useEffect( function () {
			if ( ! onSetup ) {
				return undefined;
			}
			return onSetup( function () {
				if ( ! selected ) {
					return { type: types.ERROR, message: t( 'err_channel' ) };
				}
				if ( /\b(otp|pin|password|passcode|cvv|cvc)\b/i.test( payer + ' ' + reference ) ) {
					return { type: types.ERROR, message: t( 'err_sensitive' ) };
				}
				var cleanPayer = bnDigits( payer ).trim();
				if ( isMfs && settings.validateMfs ) {
					cleanPayer = normalizeMobile( payer );
					if ( ! cleanPayer ) {
						errState[ 1 ]( { payer: t( 'err_mobile' ) } );
						return { type: types.ERROR, message: t( 'err_mobile' ) };
					}
				} else if ( cleanPayer.length < 4 ) {
					errState[ 1 ]( { payer: t( 'err_payer' ) } );
					return { type: types.ERROR, message: t( 'err_payer' ) };
				}
				var ref = normalizeRef( reference );
				if ( ! /^[A-Z0-9][A-Z0-9\-_\/.#]{3,39}$/.test( ref ) ) {
					errState[ 1 ]( { reference: t( 'err_trx' ) } );
					return { type: types.ERROR, message: t( 'err_trx' ) };
				}
				return {
					type: types.SUCCESS,
					meta: { paymentMethodData: { rar_wap_channel: selected.key, rar_wap_payer: cleanPayer, rar_wap_reference: ref } },
				};
			} );
		}, [ onSetup, channel, payer, reference ] );

		function field( id, label, value, setter, extra, hint ) {
			var err = errors[ id ];
			return el( 'p', { className: 'rw-field' + ( err ? ' is-bad' : '' ) },
				el( 'label', { htmlFor: 'rw_' + id }, label ),
				el( 'input', Object.assign( {
					id: 'rw_' + id, type: 'text', autoComplete: 'off', value: value,
					onChange: function ( e ) { setter( e.target.value ); errState[ 1 ]( {} ); },
				}, extra ) ),
				hint ? el( 'small', null, hint ) : null,
				err ? el( 'span', { className: 'rw-err', role: 'alert' }, err ) : null
			);
		}

		var balance = amounts ? parseFloat( amounts.balance_due ) : 0;

		return el( 'div', { className: 'rar-wap', style: { '--rw-accent': settings.accent || '#0f8a6b' } },
			amounts ? el( 'div', { className: 'rw-amount' },
				el( 'div', { className: 'rw-amount-now' }, el( 'span', null, t( 'pay_now' ) ), el( 'strong', null, amounts.amount_due_text ) ),
				el( 'div', { className: 'rw-amount-rest' }, balance > 0
					? [ el( 'span', { key: 'a' }, t( 'on_delivery' ) ), el( 'strong', { key: 'b' }, amounts.balance_due_text ) ]
					: el( 'span', null, t( 'nothing_on_delivery' ) ) )
			) : null,
			Step( 1, 'step_choose' ),
			el( 'div', { className: 'rw-tiles rw-n' + Math.min( 3, channels.length ) + ( channels.length % 2 ? ' rw-odd' : '' ), role: 'radiogroup' },
				channels.map( function ( c ) {
					var on = c.key === channel;
					return el( 'label', { key: c.key, className: 'rw-tile' + ( on ? ' is-on' : '' ) },
						el( 'input', { type: 'radio', name: 'rar_wap_channel_block', value: c.key, checked: on, onChange: function () { chState[ 1 ]( c.key ); errState[ 1 ]( {} ); } } ),
						el( Icon, { channel: c } ),
						el( 'span', { className: 'rw-tile-name' }, c.label ),
						el( 'span', { className: 'rw-check', 'aria-hidden': 'true' } )
					);
				} )
			),
			Step( 2, 'step_send' ),
			selected ? el( Panel, { channel: selected, amounts: amounts } ) : el( 'p', { className: 'rw-hint' }, t( 'pick_first' ) ),
			Step( 3, 'step_confirm' ),
			el( 'div', { className: 'rw-fields' },
				field( 'payer', isMfs ? fmt( 'payer_label', selected.label ) : t( 'payer_label_generic' ), payer, payerState[ 1 ], {
					inputMode: isMfs ? 'tel' : 'text', maxLength: 80,
					placeholder: isMfs ? t( 'ph_mobile' ) : t( 'ph_account' ),
				} ),
				field( 'reference', t( 'trx_label' ), reference, refState[ 1 ], {
					className: 'rw-trx', spellCheck: false, maxLength: 60,
					placeholder: selected && selected.type === 'bank' ? t( 'ph_bank_trx' ) : t( 'ph_trx' ),
					onBlur: function () { refState[ 1 ]( normalizeRef( reference ) ); },
				}, t( 'trx_hint' ) )
			),
			el( 'p', { className: 'rw-secure' }, svg( 'shield' ), el( 'span', null, dual( 'secure' ) ) )
		);
	}

	function Label( props ) {
		var PaymentMethodLabel = props.components && props.components.PaymentMethodLabel;
		var logos = settings.showLogos ? channels.filter( function ( c ) { return !! c.logo; } ) : [];
		return el( 'span', { className: 'rar-wap-block-label' },
			PaymentMethodLabel ? el( PaymentMethodLabel, { text: title } ) : title,
			logos.length ? el( 'span', { className: 'rar-wap-title-logos' }, logos.map( function ( c ) {
				return el( 'img', { key: c.key, className: 'rar-wap-title-logo', src: c.logo, alt: c.label } );
			} ) ) : null
		);
	}

	function Edit() {
		return el( 'div', { className: 'rar-wap' }, el( 'p', { className: 'rw-hint' }, title ) );
	}

	var config = {
		name: 'rar_advance_payment',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Edit, null ),
		canMakePayment: function () {
			return !! settings.visible && channels.length > 0;
		},
		ariaLabel: title,
		supports: { features: settings.supports || [ 'products' ] },
	};
	if ( settings.buttonText ) {
		config.placeOrderButtonLabel = settings.buttonText;
	}

	registry.registerPaymentMethod( config );
}() );
