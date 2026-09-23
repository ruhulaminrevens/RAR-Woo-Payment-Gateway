(function($){
	'use strict';

	function sync(){
		$('.rar-wap-channel').removeClass('is-active').attr('aria-checked','false');
		$('.rar-wap-channel input[type="radio"]:checked').each(function(){
			$(this).closest('.rar-wap-channel').addClass('is-active').attr('aria-checked','true');
		});
	}

	function copyText(value,button){
		if(!value)return;
		var done=function(){
			var $btn=$(button);
			var old=$btn.text();
			$btn.addClass('is-copied').text('Copied ✓');
			window.setTimeout(function(){$btn.removeClass('is-copied').text(old);},1400);
		};

		if(navigator.clipboard&&window.isSecureContext){
			navigator.clipboard.writeText(value).then(done).catch(function(){legacyCopy(value,done);});
		}else{
			legacyCopy(value,done);
		}
	}

	function legacyCopy(value,done){
		var input=document.createElement('textarea');
		input.value=value;
		input.setAttribute('readonly','readonly');
		input.style.position='fixed';
		input.style.opacity='0';
		document.body.appendChild(input);
		input.select();
		try{document.execCommand('copy');done();}catch(e){}
		document.body.removeChild(input);
	}

	function updatePlaceholders(){
		var channel=$('input[name="rar_wap_channel"]:checked').val()||'';
		var $payer=$('#rar_wap_payer');
		var $reference=$('#rar_wap_reference');

		if(channel==='bkash'||channel==='nagad'||channel==='rocket'){
			$payer.attr('placeholder','e.g. 01XXXXXXXXX');
			$reference.attr('placeholder','Enter Transaction ID exactly');
		}else if(channel==='bank'){
			$payer.attr('placeholder','Account reference / last 4 digits');
			$reference.attr('placeholder','Bank transfer reference / trace ID');
		}else if(channel==='banglaqr'){
			$payer.attr('placeholder','Payer mobile/account reference');
			$reference.attr('placeholder','Transaction/reference ID');
		}else{
			$payer.attr('placeholder','e.g. 01XXXXXXXXX or account reference');
			$reference.attr('placeholder','Enter the transaction/reference ID exactly');
		}
	}

	$(document.body).on('change','input[name="rar_wap_channel"]',function(){
		sync();
		updatePlaceholders();
	});

	$(document.body).on('click','.rar-wap-copy',function(e){
		e.preventDefault();
		e.stopPropagation();
		copyText(String($(this).data('copy')||''),this);
	});

	$(document.body).on('updated_checkout',function(){
		sync();
		updatePlaceholders();
	});

	$(function(){
		sync();
		updatePlaceholders();
	});
})(jQuery);
