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
			bkash:['bkash_number','bkash_type','bkash_logo'],
			nagad:['nagad_number','nagad_type','nagad_logo'],
			rocket:['rocket_number','rocket_type','rocket_logo'],
			banglaqr:['banglaqr_image','banglaqr_note','banglaqr_logo'],
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

	function renderPreview($input,previewClass,alt){
		if(!$input.length)return;
		var $row=$input.closest('td');
		$row.find('.'+previewClass).remove();
		var url=$.trim($input.val()||'');
		if(url){
			$('<img>',{class:previewClass,src:url,alt:alt||''}).appendTo($row);
		}
	}

	function initMediaPicker(config){
		var $input=$('#'+config.id);
		if(!$input.length||$input.data('rar-media-ready'))return;
		$input.data('rar-media-ready',1);

		var $tools=$('<span class="rar-wap-media-tools"></span>');
		var $choose=$('<button type="button" class="button"></button>').text(config.chooseLabel||'Choose image');
		var $clear=$('<button type="button" class="button-link-delete">Clear</button>');
		$tools.append($choose,$clear);
		$input.after($tools);

		function refresh(){
			renderPreview($input,config.previewClass||'rar-wap-logo-preview',config.alt||'Preview');
		}

		$choose.on('click',function(e){
			e.preventDefault();
			if(!window.wp||!wp.media)return;
			var frame=wp.media({
				title:config.title||'Select image',
				button:{text:config.buttonLabel||'Use this image'},
				multiple:false,
				library:{type:'image'}
			});
			frame.on('select',function(){
				var attachment=frame.state().get('selection').first().toJSON();
				if(attachment&&attachment.url){
					$input.val(attachment.url).trigger('change');
					refresh();
				}
			});
			frame.open();
		});

		$clear.on('click',function(e){
			e.preventDefault();
			$input.val('').trigger('change');
			refresh();
		});

		$input.on('input change',refresh);
		refresh();
	}

	function initMediaPickers(){
		var base='woocommerce_rar_advance_payment_';
		[
			{id:base+'bkash_logo',title:'Select bKash logo',alt:'bKash logo preview'},
			{id:base+'nagad_logo',title:'Select Nagad logo',alt:'Nagad logo preview'},
			{id:base+'rocket_logo',title:'Select Rocket logo',alt:'Rocket logo preview'},
			{id:base+'banglaqr_logo',title:'Select Bangla QR channel logo',alt:'Bangla QR logo preview'},
			{id:base+'banglaqr_image',title:'Select payable Bangla QR image',alt:'Bangla QR payment preview',previewClass:'rar-wap-qr-preview'}
		].forEach(initMediaPicker);
	}

	$(document).on('change','#woocommerce_rar_advance_payment_amount_rule,#woocommerce_rar_advance_payment_enforcement',syncRules);
	$(document).on('change','[id^="woocommerce_rar_advance_payment_"][id$="_enabled"]',function(){
		var id=this.id.replace('woocommerce_rar_advance_payment_','').replace('_enabled','');
		if(['bkash','nagad','rocket','banglaqr','bank'].indexOf(id)!==-1)syncChannel(id);
	});

	$(function(){
		syncAll();
		initMediaPickers();
	});
})(jQuery);
