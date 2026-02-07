jQuery(document).ready(function($) {
    'use strict';
    
    // مدیریت فرم پرداخت
    $('#zibal-donate-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#zibal-submit-btn');
        var messages = $('#zibal-messages');
        
        // پاک کردن پیام‌های قبلی
        messages.empty();
        
        // اعتبارسنجی فرم
        if (!validateForm(form)) {
            return false;
        }
        
        // نمایش loading روی دکمه
        submitBtn.prop('disabled', true);
        submitBtn.find('.btn-text').hide();
        submitBtn.find('.btn-loading').show();
        
        // ارسال AJAX
        $.ajax({
            url: zibal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'zibal_process_payment',
                nonce: zibal_ajax.nonce,
                form_data: form.serialize()
            },
            success: function(response) {
                if (response.success && response.data && response.data.redirect_url) {
                    // ایجاد و نمایش loading div
                    var loadingDiv = $('<div class="zibal-loading" id="zibal-loading">' +
                        '<div class="zibal-spinner"></div>' +
                        '<p>در حال انتقال به درگاه پرداخت...</p>' +
                        '</div>');
                    
                    // مخفی کردن فرم و نمایش loading
                    form.hide();
                    form.parent().append(loadingDiv);
                    loadingDiv.show();
                    
                    // انتقال به درگاه پرداخت بعد از 1.5 ثانیه
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                    
                } else {
                    // نمایش خطا
                    var errorMessage = response.data || zibal_ajax.messages.error;
                    showError(errorMessage);
                    resetButton(submitBtn);
                    scrollToMessages();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showError('خطا در ارسال درخواست. لطفاً مجدداً تلاش کنید.');
                resetButton(submitBtn);
                scrollToMessages();
            }
        });
    });
    
    // اعتبارسنجی شماره موبایل در هنگام تایپ
    $('#zibal-mobile').on('input', function() {
        var mobile = $(this).val().replace(/[^0-9]/g, '');
        var pattern = /^09[0-9]{9}$/;
        
        if (mobile && !pattern.test(mobile)) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // اعتبارسنجی مبلغ
    $('#zibal-amount').on('input', function() {
        var amount = parseInt($(this).val());
        var minAmount = parseInt($(this).attr('min'));
        var maxAmount = parseInt($(this).attr('max'));
        
        if (amount && (amount < minAmount || amount > maxAmount)) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
        
        // فرمت سه رقم سه رقم
        formatAmountField($(this));
    });
    
    // فرمت کردن مبلغ
    function formatAmountField(field) {
        var value = field.val().replace(/,/g, '');
        if (value && !isNaN(value)) {
            var formatted = parseInt(value).toLocaleString('fa-IR');
            field.val(formatted);
        }
    }
    
    // حذف کاما هنگام فوکوس برای ویرایش آسان‌تر
    $('#zibal-amount').on('focus', function() {
        var value = $(this).val().replace(/,/g, '');
        $(this).val(value);
    });
    
    // اضافه کردن کاما هنگام خروج از فیلد
    $('#zibal-amount').on('blur', function() {
        formatAmountField($(this));
    });
    
    // فقط اعداد برای فیلد موبایل
    $('#zibal-mobile').on('keypress', function(e) {
        var charCode = (e.which) ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
    });
    
    // تابع اعتبارسنجی فرم
    function validateForm(form) {
        var isValid = true;
        var firstErrorField = null;
        
        // بررسی فیلدهای اجباری
        form.find('input[required], textarea[required]').each(function() {
            var field = $(this);
            var value = field.val().trim();
            
            if (!value) {
                field.addClass('error');
                if (!firstErrorField) {
                    firstErrorField = field;
                }
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });
        
        // بررسی خاص شماره موبایل
        var mobile = $('#zibal-mobile').val().replace(/[^0-9]/g, '');
        if (mobile && !/^09[0-9]{9}$/.test(mobile)) {
            $('#zibal-mobile').addClass('error');
            if (!firstErrorField) {
                firstErrorField = $('#zibal-mobile');
            }
            isValid = false;
        }
        
        // بررسی ایمیل
        var email = $('#zibal-email').val().trim();
        if (email && !isValidEmail(email)) {
            $('#zibal-email').addClass('error');
            if (!firstErrorField) {
                firstErrorField = $('#zibal-email');
            }
            isValid = false;
        }
        
        // بررسی مبلغ
        var amountValue = $('#zibal-amount').val().replace(/,/g, ''); // حذف کاما
        var amount = parseInt(amountValue);
        var minAmount = parseInt($('#zibal-amount').attr('min'));
        var maxAmount = parseInt($('#zibal-amount').attr('max'));
        
        if (!amount || amount < minAmount || amount > maxAmount) {
            $('#zibal-amount').addClass('error');
            if (!firstErrorField) {
                firstErrorField = $('#zibal-amount');
            }
            isValid = false;
        }
        
        if (!isValid) {
            showError('لطفاً فیلدهای اجباری را به درستی پر کنید.');
            if (firstErrorField) {
                firstErrorField.focus();
                scrollToElement(firstErrorField);
            }
        }
        
        return isValid;
    }
    
    // تابع نمایش خطا
    function showError(message) {
        var messages = $('#zibal-messages');
        messages.html('<div class="zibal-message zibal-error">' + message + '</div>');
    }
    
    // تابع بازگردانی دکمه به حالت عادی
    function resetButton(submitBtn) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.btn-text').show();
        submitBtn.find('.btn-loading').hide();
    }
    
    // تابع اسکرول به پیام‌ها
    function scrollToMessages() {
        var messages = $('#zibal-messages');
        if (messages.length) {
            $('html, body').animate({
                scrollTop: messages.offset().top - 100
            }, 500);
        }
    }
    
    // تابع اسکرول به عنصر خاص
    function scrollToElement(element) {
        if (element.length) {
            $('html, body').animate({
                scrollTop: element.offset().top - 100
            }, 500);
        }
    }
    
    // تابع اعتبارسنجی ایمیل
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // حذف کلاس error هنگام فوکوس
    $('.zibal-form-input').on('focus', function() {
        $(this).removeClass('error');
    });
    
    // مدیریت کپی کردن shortcode
    $(document).on('click', '.copy-shortcode', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        
        // ایجاد element موقت برای کپی
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(shortcode).select();
        document.execCommand('copy');
        tempInput.remove();
        
        // نمایش پیام موفقیت
        $(this).text('کپی شد!').addClass('copied');
        setTimeout(() => {
            $(this).text('کپی').removeClass('copied');
        }, 2000);
    });
});