/**
 * RAR Advance Payment — WooCommerce Checkout Blocks integration.
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
	var channels = settings.channels || [];
	var title = decode( settings.title || 'Advance Payment' );

	function t( key ) {
		return S[ key ] || key;
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
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		document.body.removeChild( input );
	}

	function CopyButton( props ) {
		var state = useState( false );
		return el(
			'button',
			{
				type: 'button',
				className: 'rar-wap-copy' + ( state[ 0 ] ? ' is-copied' : '' ),
				onClick: function ( e ) {
					e.preventDefault();
					e.stopPropagation();
					copyText( props.value, function () {
						state[ 1 ]( true );
						window.setTimeout( function () { state[ 1 ]( false ); }, 1400 );
					} );
				},
			},
			state[ 0 ] ? t( 'copied' ) : t( 'copy' )
		);
	}

	function ChannelIcon( props ) {
		var c = props.channel;
		if ( c.logo ) {
			return el( 'span', { className: 'rar-wap-channel-icon has-logo is-' + c.key, 'aria-hidden': 'true' }, el( 'img', { src: c.logo, alt: '' } ) );
		}
		return el( 'span', { className: 'rar-wap-channel-icon is-' + c.key, 'aria-hidden': 'true' }, c.type === 'qr' ? '▦' : ( c.type === 'bank' ? '🏦' : '💳' ) );
	}

	function ChannelDetails( props ) {
		var c = props.channel;
		var parts = [];
		if ( c.type === 'qr' ) {
			parts.push(
				el( 'span', { className: 'rar-wap-qr', key: 'qr' },
					el( 'a', { className: 'rar-wap-qr-link', href: c.qr, target: '_blank', rel: 'noopener' }, el( 'img', { src: c.qr, alt: 'Bangla QR' } ) ),
					el( 'small', { className: 'rar-wap-qr-enlarge' }, t( 'qr_enlarge' ) )
				)
			);
			if ( c.instruction ) {
				parts.push( el( 'span', { className: 'rar-wap-channel-note', key: 'note' }, c.instruction ) );
			}
			return parts;
		}
		if ( c.number ) {
			parts.push(
				el( 'span', { className: 'rar-wap-destination', key: 'dest' },
					el( 'span', null,
						el( 'small', null, c.type === 'mfs' ? t( 'instruction' ) : t( 'account_type' ) ),
						el( 'strong', null, c.instruction || c.label ),
						el( 'code', null, c.number )
					),
					el( CopyButton, { value: c.number } )
				)
			);
		}
		if ( c.details ) {
			parts.push(
				el( 'span', { className: 'rar-wap-bank-details', key: 'details' },
					c.details.split( /\n/ ).map( function ( line, i ) {
						return el( 'span', { key: i, style: { display: 'block' } }, line );
					} )
				)
			);
		}
		return parts;
	}

	function Content( props ) {
		var amounts = useCartAmounts();
		var chState = useState( channels.length === 1 ? channels[ 0 ].key : '' );
		var payerState = useState( '' );
		var refState = useState( '' );
		var channel = chState[ 0 ];
		var payer = payerState[ 0 ];
		var reference = refState[ 0 ];
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

		useEffect( function () {
			if ( ! onSetup ) {
				return undefined;
			}
			return onSetup( function () {
				if ( ! selected ) {
					return { type: types.ERROR, message: t( 'err_channel' ) };
				}
				var cleanPayer = bnDigits( payer ).trim();
				if ( /\b(otp|pin|password|passcode|cvv|cvc)\b/i.test( payer + ' ' + reference ) ) {
					return { type: types.ERROR, message: t( 'err_sensitive' ) };
				}
				if ( selected.type === 'mfs' && settings.validateMfs ) {
					cleanPayer = normalizeMobile( payer );
					if ( ! cleanPayer ) {
						return { type: types.ERROR, message: t( 'err_mobile' ) };
					}
				} else if ( cleanPayer.length < 4 ) {
					return { type: types.ERROR, message: t( 'err_payer' ) };
				}
				var ref = normalizeRef( reference );
				if ( ! /^[A-Z0-9][A-Z0-9\-_\/.#]{3,39}$/.test( ref ) ) {
					return { type: types.ERROR, message: t( 'err_trx' ) };
				}
				return {
					type: types.SUCCESS,
					meta: {
						paymentMethodData: {
							rar_wap_channel: selected.key,
							rar_wap_payer: cleanPayer,
							rar_wap_reference: ref,
						},
					},
				};
			} );
		}, [ onSetup, channel, payer, reference ] );

		var isMfs = selected && selected.type === 'mfs';
		var isBank = selected && selected.type === 'bank';

		return el( 'div', { className: 'rar-wap-box rar-wap-blocks' },
			settings.description ? el( 'div', { className: 'rar-wap-intro' }, decode( settings.description ) ) : null,
			el( 'div', { className: 'rar-wap-trust-strip', role: 'note' },
				el( 'span', null, '✓ ' + t( 'trust_manual' ) ),
				el( 'span', null, '🔒 ' + t( 'trust_nopin' ) ),
				el( 'span', null, '✓ ' + t( 'trust_linked' ) )
			),
			amounts ? el( 'div', { className: 'rar-wap-summary' },
				el( 'div', { className: 'is-primary' },
					el( 'span', null, t( 'pay_now' ) ),
					el( 'strong', null, amounts.amount_due_text ),
					el( 'small', null, t( 'transfer_exact' ) )
				),
				el( 'div', null,
					el( 'span', null, t( 'due_on_delivery' ) ),
					el( 'strong', null, amounts.balance_due_text ),
					el( 'small', null, parseFloat( amounts.balance_due ) > 0 ? t( 'remaining_balance' ) : t( 'fully_paid_now' ) )
				)
			) : null,
			el( 'ol', { className: 'rar-wap-steps' },
				el( 'li', null, el( 'span', null, '1' ), el( 'strong', null, t( 'step_channel' ) ) ),
				el( 'li', null, el( 'span', null, '2' ), el( 'strong', null, t( 'step_pay' ) ) ),
				el( 'li', null, el( 'span', null, '3' ), el( 'strong', null, t( 'step_submit' ) ) )
			),
			el( 'p', { className: 'rar-wap-help' }, t( 'help' ) ),
			el( 'div', { className: 'rar-wap-channels', role: 'radiogroup' },
				channels.map( function ( c ) {
					var active = c.key === channel;
					return el( 'label', { key: c.key, className: 'rar-wap-channel' + ( active ? ' is-active' : '' ), 'data-channel': c.key },
						el( 'span', { className: 'rar-wap-channel-head' },
							el( 'input', { type: 'radio', name: 'rar_wap_channel_block', value: c.key, checked: active, onChange: function () { chState[ 1 ]( c.key ); } } ),
							el( ChannelIcon, { channel: c } ),
							el( 'span', null, el( 'strong', null, c.label ), el( 'small', null, t( 'tap_view' ) ) )
						),
						active ? el( 'span', { className: 'rar-wap-channel-details', style: { display: 'block' } }, el( ChannelDetails, { channel: c } ) ) : null
					);
				} )
			),
			el( 'div', { className: 'rar-wap-reference-fields' },
				el( 'p', { className: 'form-row form-row-wide' },
					el( 'label', { htmlFor: 'rar_wap_payer_b' }, t( 'payer_label' ), ' *' ),
					el( 'input', {
						id: 'rar_wap_payer_b', className: 'input-text', type: 'text', inputMode: 'tel', autoComplete: 'off', maxLength: 80,
						placeholder: isMfs ? t( 'ph_mobile' ) : ( isBank ? t( 'ph_account' ) : t( 'ph_generic_payer' ) ),
						value: payer,
						onChange: function ( e ) { payerState[ 1 ]( e.target.value ); },
					} )
				),
				el( 'p', { className: 'form-row form-row-wide' },
					el( 'label', { htmlFor: 'rar_wap_reference_b' }, t( 'trx_label' ), ' *' ),
					el( 'input', {
						id: 'rar_wap_reference_b', className: 'input-text', type: 'text', autoComplete: 'off', spellCheck: false, maxLength: 60,
						placeholder: isBank ? t( 'ph_bank_trx' ) : t( 'ph_trx' ),
						value: reference,
						onChange: function ( e ) { refState[ 1 ]( e.target.value ); },
						onBlur: function () { refState[ 1 ]( normalizeRef( reference ) ); },
					} ),
					el( 'small', { className: 'rar-wap-field-note' }, t( 'trx_note' ) )
				)
			),
			el( 'p', { className: 'rar-wap-safety' }, el( 'strong', null, '🔒 ' + t( 'security_title' ) ), ' ', t( 'security' ) ),
			el( 'p', { className: 'rar-wap-verification-note' }, t( 'verification_note' ) )
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
		return el( 'div', { className: 'rar-wap-box' }, el( 'p', null, title + ' — ' + t( 'help' ) ) );
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
