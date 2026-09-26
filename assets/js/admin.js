/**
 * RAR Advance Payment — gateway settings screen (v2.1): tabs, channel cards,
 * switches, live amount preview and media pickers.
 */
( function ( $ ) {
	'use strict';

	var P = 'woocommerce_rar_advance_payment_';
	var C = window.rarWapSettings || {};
	var I = C.i18n || {};
	var CHANNELS = {
		bkash: { name: 'bKash', type: 'mfs', fields: [ 'number', 'type', 'logo' ], need: 'number' },
		nagad: { name: 'Nagad', type: 'mfs', fields: [ 'number', 'type', 'logo' ], need: 'number' },
		rocket: { name: 'Rocket', type: 'mfs', fields: [ 'number', 'type', 'logo' ], need: 'number' },
		upay: { name: 'Upay', type: 'mfs', fields: [ 'number', 'type', 'logo' ], need: 'number' },
		banglaqr: { name: 'Bangla QR', type: 'qr', fields: [ 'image', 'note', 'logo' ], need: 'image' },
		bank: { name: 'Bank Transfer (NPSB)', type: 'bank', fields: [ 'details', 'account', 'logo' ], need: 'details' },
		custom: { name: 'Custom channel', type: 'custom', fields: [ 'label', 'number', 'details', 'logo' ], need: 'label' },
	};
	var ICONS = {
		mfs: 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm0 3v13h10V5H7zm5 14.2a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z',
		qr: 'M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v3h-3v-3zm4 0h3v7h-3v-3h-2v-2h2v-2zm-4 5h2v2h-2v-2z',
		bank: 'M12 3 3 7v2h18V7l-9-4zM5 11h2v7H5v-7zm4 0h2v7H9v-7zm4 0h2v7h-2v-7zm4 0h2v7h-2v-7zM3 20h18v2H3v-2z',
		custom: 'M4 5h13a3 3 0 0 1 3 3v1h-5a4 4 0 0 0 0 8h5v1a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3zm11 6h7v4h-7a2 2 0 1 1 0-4z',
	};
	var SECTIONS = [
		[ 'rollout_heading', 'general', I.general ],
		[ 'rules_heading', 'rules', I.rules ],
		[ 'verification_heading', 'verification', I.verification ],
		[ 'notifications_heading', 'notifications', I.notifications ],
		[ 'channel_heading', 'channels', I.channels ],
	];

	function row( id ) {
		return $( '#' + P + id ).closest( 'tr' );
	}

	function toggleRow( id, show ) {
		row( id ).toggle( !! show );
	}

	/* ---------- Tabs ---------- */
	function buildTabs( $form ) {
		var $nav = $( '<nav class="rws-tabs" role="tablist"></nav>' );
		var $first = null;

		SECTIONS.forEach( function ( s ) {
			var $h = $( '#' + P + s[ 0 ] );
			if ( ! $h.length ) {
				return;
			}
			var $pane = $( '<section class="rws-pane" role="tabpanel"></section>' ).attr( 'data-pane', s[ 1 ] );
			var $desc = $h.next( 'p' );
			if ( $desc.length && $desc.text().trim() ) {
				$pane.append( $( '<p class="rws-pane-intro"></p>' ).text( $desc.text() ) );
			}
			var $table = $h.nextAll( 'table.form-table' ).first();
			$h.before( $pane );
			$pane.append( $table );
			$h.hide();
			$desc.hide();
			$( '<button type="button" class="rws-tab" role="tab"></button>' ).attr( 'data-tab', s[ 1 ] ).text( s[ 2 ] || $h.text() ).appendTo( $nav );
			$first = $first || $pane;
		} );

		if ( ! $first ) {
			return;
		}
		$form.find( '.rws-pane' ).first().before( $nav );

		// Hide WooCommerce's default heading/intro — the hero replaces them.
		$form.find( '.rws-hero' ).nextAll( 'h2' ).first().hide().next( 'p' ).hide();
		$form.find( 'table.form-table' ).filter( function () {
			return ! $( this ).closest( '.rws-pane' ).length && ! $( this ).find( 'tr' ).length;
		} ).hide();

		var saved = '';
		try {
			saved = window.sessionStorage.getItem( 'rwsTab' ) || '';
		} catch ( e ) {}
		showTab( ( window.location.hash || '' ).replace( '#rws-', '' ) || saved || 'general' );
	}

	function showTab( key ) {
		if ( ! $( '.rws-pane[data-pane="' + key + '"]' ).length ) {
			key = 'general';
		}
		$( '.rws-pane' ).each( function () {
			this.hidden = $( this ).data( 'pane' ) !== key;
		} );
		$( '.rws-tab' ).each( function () {
			var on = $( this ).data( 'tab' ) === key;
			$( this ).toggleClass( 'is-on', on ).attr( 'aria-selected', on ? 'true' : 'false' );
		} );
		try {
			window.sessionStorage.setItem( 'rwsTab', key );
		} catch ( e ) {}
	}

	$( document ).on( 'click', '.rws-tab', function () {
		showTab( $( this ).data( 'tab' ) );
	} );
	$( document ).on( 'click', '[data-rws-tab]', function ( e ) {
		e.preventDefault();
		showTab( $( this ).data( 'rws-tab' ) );
		$( 'html,body' ).animate( { scrollTop: $( '.rws-tabs' ).offset().top - 40 }, 200 );
	} );

	/* ---------- Channel cards ---------- */
	function buildChannels() {
		var $pane = $( '.rws-pane[data-pane="channels"]' );
		if ( ! $pane.length ) {
			return;
		}
		var $grid = $( '<div class="rws-channels"></div>' );

		Object.keys( CHANNELS ).forEach( function ( key ) {
			var def = CHANNELS[ key ];
			var $toggle = $( '#' + P + key + '_enabled' );
			if ( ! $toggle.length ) {
				return;
			}
			var $enabledRow = $toggle.closest( 'tr' );
			var $card = $( '<div class="rws-ch"></div>' ).attr( 'data-ch', key ).css( '--rwa-tint', ( C.tints || {} )[ key ] || '#475569' );
			var $ico = $( '<span class="rws-ch-ico"></span>' ).html( '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICONS[ def.type ] + '"/></svg>' );
			var $state = $( '<small class="rws-ch-state"></small>' );
			var $switch = $( '<label></label>' ).append( $toggle.addClass( 'rws-switch' ) );
			var $head = $( '<div class="rws-ch-head"></div>' ).append( $ico, $( '<span></span>' ).append( $( '<strong></strong>' ).text( def.name ), $state ), $switch );
			var $body = $( '<div class="rws-ch-body"></div>' );

			def.fields.forEach( function ( f ) {
				var $input = $( '#' + P + key + '_' + f );
				if ( ! $input.length ) {
					return;
				}
				var $tr = $input.closest( 'tr' );
				var label = $tr.find( 'th label' ).first().text().trim();
				var $td = $tr.find( 'td' ).first();
				var $field = $( '<div class="rws-field"></div>' ).append( $( '<span></span>' ).text( label ) );
				$field.append( $td.find( 'fieldset' ).length ? $td.find( 'fieldset' ).children().not( 'legend' ) : $td.children() );
				$body.append( $field );
				$tr.remove();
			} );
			$enabledRow.remove();

			$card.append( $head, $body ).appendTo( $grid );
		} );

		$pane.find( 'table.form-table' ).before( $grid );
		if ( ! $pane.find( 'table.form-table tr' ).length ) {
			$pane.find( 'table.form-table' ).hide();
		}
		syncChannels();
	}

	function syncChannels() {
		$( '.rws-ch' ).each( function () {
			var key = $( this ).data( 'ch' );
			var def = CHANNELS[ key ];
			var on = $( '#' + P + key + '_enabled' ).is( ':checked' );
			var filled = $.trim( $( '#' + P + key + '_' + def.need ).val() || '' ) !== '';
			if ( key === 'custom' ) {
				filled = filled && ( $.trim( $( '#' + P + 'custom_number' ).val() || '' ) !== '' || $.trim( $( '#' + P + 'custom_details' ).val() || '' ) !== '' );
			}
			var logo = $.trim( $( '#' + P + key + '_logo' ).val() || '' );
			$( this ).toggleClass( 'is-on', on );
			$( this ).find( '.rws-ch-state' ).text( on ? ( filled ? I.ready : I.needsNumber ) : I.off ).css( 'color', on && ! filled ? '#b45309' : '' );
			var $ico = $( this ).find( '.rws-ch-ico' );
			if ( logo ) {
				$ico.html( $( '<img alt="">' ).attr( 'src', logo ) );
			} else if ( $ico.find( 'img' ).length ) {
				$ico.html( '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICONS[ def.type ] + '"/></svg>' );
			}
		} );
	}

	/* ---------- Conditional rows ---------- */
	function syncRules() {
		var rule = $( '#' + P + 'amount_rule' ).val();
		var zeroFixed = $( '#' + P + 'shipping_zero_behaviour' ).val() === 'fixed';
		toggleRow( 'fixed_amount', rule === 'fixed' || ( rule === 'shipping' && zeroFixed ) );
		toggleRow( 'percentage', rule === 'percent' || rule === 'shipping_percent' );
		toggleRow( 'shipping_zero_behaviour', rule === 'shipping' );

		var required = $( '#' + P + 'enforcement' ).val() === 'required';
		toggleRow( 'hide_cod_when_required', required );
		toggleRow( 'required_min_total', required );
		toggleRow( 'proof_max_mb', $( '#' + P + 'proof_upload' ).is( ':checked' ) );
		toggleRow( 'webhook_secret', $.trim( $( '#' + P + 'webhook_url' ).val() || '' ) !== '' );
		preview();
	}

	/* ---------- Live amount preview (mirrors RAR_WAP_Gateway::compute_amount) ---------- */
	function num( id ) {
		return parseFloat( $( '#' + P + id ).val() ) || 0;
	}

	function money( v ) {
		return ( C.currency || '' ) + ' ' + ( Math.round( v * 100 ) / 100 ).toLocaleString( undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 } );
	}

	function preview() {
		var $box = $( '.rws-preview' );
		if ( ! $box.length ) {
			return;
		}
		var products = parseFloat( $box.find( '[data-p="products"]' ).val() ) || 0;
		var shipping = parseFloat( $box.find( '[data-p="shipping"]' ).val() ) || 0;
		var total = products + shipping;
		var rule = $( '#' + P + 'amount_rule' ).val();
		var pct = Math.min( 100, Math.max( 1, num( 'percentage' ) || 20 ) );
		var fixed = Math.max( 0, num( 'fixed_amount' ) );
		var amount;

		switch ( rule ) {
			case 'fixed': amount = fixed; break;
			case 'percent': amount = total * pct / 100; break;
			case 'shipping_percent': amount = shipping + products * pct / 100; break;
			case 'full': amount = total; break;
			default:
				amount = shipping;
				if ( amount <= 0 && $( '#' + P + 'shipping_zero_behaviour' ).val() === 'fixed' ) {
					amount = fixed;
				}
		}
		var round = $( '#' + P + 'round_amount' ).val();
		if ( round === 'up' ) {
			amount = Math.ceil( amount - 0.0001 );
		} else if ( round === 'ten' ) {
			amount = Math.ceil( ( amount - 0.0001 ) / 10 ) * 10;
		}
		amount = Math.max( 0, Math.min( amount, total ) );

		var min = num( 'min_order_total' );
		var max = num( 'max_order_total' );
		var hidden = amount <= 0 || ( min > 0 && total < min ) || ( max > 0 && total > max );
		$box.find( 'output' ).html( '' ).append(
			$( '<small></small>' ).text( I.payNow ),
			$( '<b></b>' ).text( hidden ? '—' : money( amount ) ),
			$( '<small></small>' ).text( hidden ? I.hidden : money( total - amount ) + ' ' + I.onDelivery )
		);
	}

	function buildPreview() {
		var $pane = $( '.rws-pane[data-pane="rules"]' );
		if ( ! $pane.length ) {
			return;
		}
		var $box = $( '<div class="rws-preview"></div>' ).append(
			$( '<h4></h4>' ).text( I.preview ),
			$( '<label></label>' ).text( I.products ).append( '<input type="number" min="0" step="1" value="1500" data-p="products">' ),
			$( '<label></label>' ).text( I.shipping ).append( '<input type="number" min="0" step="1" value="120" data-p="shipping">' ),
			$( '<span></span>' ),
			$( '<output aria-live="polite"></output>' )
		);
		$pane.find( '.rws-pane-intro' ).after( $box );
		$box.on( 'input', 'input', preview );
		preview();
	}

	/* ---------- Media pickers ---------- */
	function renderPreview( $input, cls ) {
		var $wrap = $input.parent();
		$wrap.find( '.' + cls ).remove();
		var url = $.trim( $input.val() || '' );
		if ( url ) {
			$( '<img alt="">' ).addClass( cls ).attr( 'src', url ).insertAfter( $wrap.find( '.rar-wap-media-tools' ) );
		}
	}

	function initMediaPicker( id, cls ) {
		var $input = $( '#' + P + id );
		if ( ! $input.length || $input.data( 'rws-media' ) ) {
			return;
		}
		$input.data( 'rws-media', 1 );
		var $tools = $( '<span class="rar-wap-media-tools"></span>' );
		var $choose = $( '<button type="button" class="button"></button>' ).text( I.choose || 'Choose image' );
		var $clear = $( '<button type="button" class="button-link rws-remove"></button>' ).text( I.remove || 'Remove' );
		$tools.append( $choose, $clear ).insertAfter( $input );

		$choose.on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			var frame = wp.media( { multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				if ( a && a.url ) {
					$input.val( a.url ).trigger( 'change' );
				}
			} );
			frame.open();
		} );
		$clear.on( 'click', function ( e ) {
			e.preventDefault();
			$input.val( '' ).trigger( 'change' );
		} );
		$input.on( 'input change', function () {
			renderPreview( $input, cls );
			syncChannels();
		} );
		renderPreview( $input, cls );
	}

	$( function () {
		var $form = $( '#mainform' );
		if ( ! $form.length || ! $( '#' + P + 'enabled' ).length ) {
			return;
		}
		$form.addClass( 'rws' );
		buildTabs( $form );
		buildChannels();
		buildPreview();

		$form.find( 'input[type=checkbox]' ).filter( function () {
			return this.id.indexOf( P ) === 0;
		} ).addClass( 'rws-switch' );

		[ 'bkash_logo', 'nagad_logo', 'rocket_logo', 'upay_logo', 'banglaqr_logo', 'bank_logo', 'custom_logo' ].forEach( function ( id ) {
			initMediaPicker( id, 'rar-wap-logo-preview' );
		} );
		initMediaPicker( 'banglaqr_image', 'rar-wap-qr-preview' );

		$form.on( 'change input', 'select, input', function () {
			syncRules();
			syncChannels();
		} );
		syncRules();
	} );
}( jQuery ) );
