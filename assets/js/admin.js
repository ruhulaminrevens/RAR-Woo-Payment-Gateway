(function($){
	'use strict';

	function rowFor(id){
		return $('#'+id).closest('tr');
	}

	function toggleRow(id,show){
		rowFor(id).toggleClass('rar-wap-field-hidden',!show);
	}

	function syncRules(){
		var rule=$('#woocommerce_rar_advance_payment_amount_rule').val();
		toggleRow('woocommerce_rar_advance_payment_fixed_amount',rule==='fixed');
		toggleRow('woocommerce_rar_advance_payment_percentage',rule==='percent');

		var required=$('#woocommerce_rar_advance_payment_enforcement').val()==='required';
		toggleRow('woocommerce_rar_advance_payment_hide_cod_when_required',required);
	}

	function syncChannel(prefix){
		var enabled=$('#woocommerce_rar_advance_payment_'+prefix+'_enabled').is(':checked');
		var map={
			bkash:['bkash_number','bkash_type'],
			nagad:['nagad_number','nagad_type'],
			rocket:['rocket_number','rocket_type'],
			banglaqr:['banglaqr_image','banglaqr_note'],
			bank:['bank_details']
		};
		(map[prefix]||[]).forEach(function(key){
			toggleRow('woocommerce_rar_advance_payment_'+key,enabled);
		});
	}

	function syncAll(){
		syncRules();
		['bkash','nagad','rocket','banglaqr','bank'].forEach(syncChannel);
	}

	function refreshQrPreview(){
		var $input=$('#woocommerce_rar_advance_payment_banglaqr_image');
		if(!$input.length)return;
		var $row=$input.closest('td');
		$row.find('.rar-wap-qr-preview').remove();
		var url=$.trim($input.val()||'');
		if(url){
			$('<img>',{class:'rar-wap-qr-preview',src:url,alt:'Bangla QR preview'}).appendTo($row);
		}
	}

	function initMediaPicker(){
		var $input=$('#woocommerce_rar_advance_payment_banglaqr_image');
		if(!$input.length||$input.data('rar-media-ready'))return;
		$input.data('rar-media-ready',1);

		var $tools=$('<span class="rar-wap-media-tools"></span>');
		var $choose=$('<button type="button" class="button">Choose image</button>');
		var $clear=$('<button type="button" class="button-link-delete">Clear</button>');
		$tools.append($choose,$clear);
		$input.after($tools);

		$choose.on('click',function(e){
			e.preventDefault();
			if(!window.wp||!wp.media)return;
			var frame=wp.media({
				title:'Select Bangla QR image',
				button:{text:'Use this image'},
				multiple:false,
				library:{type:'image'}
			});
			frame.on('select',function(){
				var attachment=frame.state().get('selection').first().toJSON();
				if(attachment&&attachment.url){
					$input.val(attachment.url).trigger('change');
					refreshQrPreview();
				}
			});
			frame.open();
		});

		$clear.on('click',function(e){
			e.preventDefault();
			$input.val('').trigger('change');
			refreshQrPreview();
		});

		$input.on('input change',refreshQrPreview);
		refreshQrPreview();
	}

	$(document).on('change','#woocommerce_rar_advance_payment_amount_rule,#woocommerce_rar_advance_payment_enforcement',syncRules);
	$(document).on('change','[id^="woocommerce_rar_advance_payment_"][id$="_enabled"]',function(){
		var id=this.id.replace('woocommerce_rar_advance_payment_','').replace('_enabled','');
		if(['bkash','nagad','rocket','banglaqr','bank'].indexOf(id)!==-1)syncChannel(id);
	});

	$(function(){
		syncAll();
		initMediaPicker();
	});
})(jQuery);
