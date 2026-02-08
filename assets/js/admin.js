jQuery(document).ready(function($) {
    'use strict';
    
    $('#ZD_UseCustomStyle').change(function() {
        if ($(this).is(':checked')) {
            $('#custom-style-row').slideDown(300);
        } else {
            $('#custom-style-row').slideUp(300);
        }
    });
    
    $('form').on('submit', function(e) {
        var merchantId = $('#ZD_MerchantID').val().trim();
        var minAmount = parseInt($('#ZD_MinAmount').val());
        var maxAmount = parseInt($('#ZD_MaxAmount').val());
        
        var errors = [];
        
        if (!merchantId) {
            errors.push('کد درگاه پرداخت الزامی است.');
        }
        
        if (minAmount < 1000) {
            errors.push('حداقل مبلغ نمی‌تواند کمتر از 1000 ریال باشد.');
        }
        
        if (maxAmount < minAmount) {
            errors.push('حداکثر مبلغ نمی‌تواند کمتر از حداقل مبلغ باشد.');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('خطاهای زیر را برطرف کنید:\n\n' + errors.join('\n'));
            return false;
        }
    });
    
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var originalText = btn.text();
        var merchantId = $('#ZD_MerchantID').val().trim();
        
        if (!merchantId) {
            alert('ابتدا کد درگاه پرداخت را وارد کنید.');
            return;
        }
        
        btn.text('در حال تست...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_zibal_connection',
                merchant_id: merchantId,
                nonce: $('#zibal_admin_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('اتصال با موفقیت برقرار شد!');
                } else {
                    alert('خطا در اتصال: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            },
            complete: function() {
                btn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    $('#show-advanced').on('click', function(e) {
        e.preventDefault();
        $('.advanced-settings').slideToggle();
        $(this).text($(this).text() === 'نمایش تنظیمات پیشرفته' ? 'مخفی کردن تنظیمات پیشرفته' : 'نمایش تنظیمات پیشرفته');
    });
    
    $('<style>')
        .prop('type', 'text/css')
        .html('.copied { background-color: #00a32a !important; color: white !important; }')
        .appendTo('head');
});