jQuery(document).ready(function($) {
    'use strict';
    
    $('#zibal-donate-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#zibal-submit-btn');
        var messages = $('#zibal-messages');
        
        messages.empty();
        
        if (!validateForm(form)) {
            return false;
        }
        
        submitBtn.prop('disabled', true);
        submitBtn.find('.btn-text').hide();
        submitBtn.find('.btn-loading').show();
        
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
                    var loadingDiv = $('<div class="zibal-loading" id="zibal-loading">' +
                        '<div class="zibal-spinner"></div>' +
                        '<p>در حال انتقال به درگاه پرداخت...</p>' +
                        '</div>');
                    
                    form.hide();
                    form.parent().append(loadingDiv);
                    loadingDiv.show();
                    
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                    
                } else {
                    var errorMessage = response.data || zibal_ajax.messages.error;
                    showError(errorMessage);
                    resetButton(submitBtn);
                    scrollToMessages();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', errorThrown);
                showError('خطا در ارسال درخواست. لطفاً مجدداً تلاش کنید.');
                resetButton(submitBtn);
                scrollToMessages();
            }
        });
    });
    
    $('#zibal-mobile').on('input', function() {
        var mobile = $(this).val().replace(/[^0-9]/g, '');
        var pattern = /^09[0-9]{9}$/;
        
        if (mobile && !pattern.test(mobile)) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });
    
    $('#zibal-amount').on('input', function() {
        var field = $(this);
        var value = field.val();
        var cursorPosition = field.prop("selectionStart");
        var digits = value.replace(/\D/g, '');
        var formattedValue = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        
        field.val(formattedValue);
        
        var newCursorPosition = cursorPosition + (formattedValue.length - value.length);
        field[0].setSelectionRange(newCursorPosition, newCursorPosition);
        
        var amount = parseInt(digits);
        var minAmount = parseInt(field.attr('min'));
        var maxAmount = parseInt(field.attr('max'));
        
        if (amount && (amount < minAmount || amount > maxAmount)) {
            field.addClass('error');
        } else {
            field.removeClass('error');
        }
    });
    
    $('#zibal-mobile').on('keypress', function(e) {
        var charCode = (e.which) ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
    });
    
    function validateForm(form) {
        var isValid = true;
        var firstErrorField = null;
        
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
        
        var mobile = $('#zibal-mobile').val().replace(/[^0-9]/g, '');
        if (mobile && !/^09[0-9]{9}$/.test(mobile)) {
            $('#zibal-mobile').addClass('error');
            if (!firstErrorField) {
                firstErrorField = $('#zibal-mobile');
            }
            isValid = false;
        }
        
        var email = $('#zibal-email').val().trim();
        if (email && !isValidEmail(email)) {
            $('#zibal-email').addClass('error');
            if (!firstErrorField) {
                firstErrorField = $('#zibal-email');
            }
            isValid = false;
        }
        
        var amountValue = $('#zibal-amount').val().replace(/,/g, '');
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
    
    function showError(message) {
        var messages = $('#zibal-messages');
        messages.empty().append($('<div>', {
            'class': 'zibal-message zibal-error',
            text: message
        }));
    }
    
    function resetButton(submitBtn) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.btn-text').show();
        submitBtn.find('.btn-loading').hide();
    }
    
    function scrollToMessages() {
        var messages = $('#zibal-messages');
        if (messages.length) {
            $('html, body').animate({
                scrollTop: messages.offset().top - 100
            }, 500);
        }
    }
    
    function scrollToElement(element) {
        if (element.length) {
            $('html, body').animate({
                scrollTop: element.offset().top - 100
            }, 500);
        }
    }
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    $('.zibal-form-input').on('focus', function() {
        $(this).removeClass('error');
    });
    
    $(document).on('click', '.copy-shortcode', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(shortcode).select();
        document.execCommand('copy');
        tempInput.remove();
        
        $(this).text('کپی شد!').addClass('copied');
        setTimeout(function() {
            $(this).text('کپی').removeClass('copied');
        }.bind(this), 2000);
    });
});
