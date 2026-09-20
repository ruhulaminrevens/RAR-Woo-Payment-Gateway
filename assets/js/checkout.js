(function($){
    function sync(){
        $('.rar-wap-channel').removeClass('is-active');
        $('.rar-wap-channel input[type="radio"]:checked').closest('.rar-wap-channel').addClass('is-active');
    }
    $(document.body).on('change','input[name="rar_wap_channel"]',sync);
    $(document.body).on('updated_checkout',sync);
    $(sync);
})(jQuery);
