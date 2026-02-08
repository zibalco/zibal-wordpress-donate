<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZibalAdmin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_post_zibal_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_test_zibal_gateways', array($this, 'ajax_test_gateways'));
    }
    

    public function add_admin_menu() {
        add_menu_page(
            'تنظیمات زیبال',
            'حمایت مالی زیبال',
            'manage_options',
            'zibal-donate',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'zibal-donate',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'zibal-donate',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'zibal-donate',
            'استایل',
            'استایل',
            'manage_options',
            'zibal-style',
            array($this, 'style_page')
        );
        
        add_submenu_page(
            'zibal-donate',
            'تراکنش‌ها',
            'تراکنش‌ها',
            'manage_options',
            'zibal-transactions',
            array($this, 'transactions_page')
        );
        
        add_submenu_page(
            'zibal-donate',
            'گزارشات',
            'گزارشات',
            'manage_options',
            'zibal-reports',
            array($this, 'reports_page')
        );
    }

    public function admin_init() {
        register_setting('zibal_donate_settings', 'ZD_MerchantID');
        register_setting('zibal_donate_settings', 'ZD_IsOK');
        register_setting('zibal_donate_settings', 'ZD_IsError');
        register_setting('zibal_donate_settings', 'ZD_Unit');
        register_setting('zibal_donate_settings', 'ZD_MinAmount');
        register_setting('zibal_donate_settings', 'ZD_MaxAmount');
        
        register_setting('zibal_style_settings', 'ZD_FormBackgroundColor');
        register_setting('zibal_style_settings', 'ZD_InputBackgroundColor');
        register_setting('zibal_style_settings', 'ZD_InputBorderColor');
        register_setting('zibal_style_settings', 'ZD_ButtonBackgroundColor');
        register_setting('zibal_style_settings', 'ZD_ButtonHoverColor');
        register_setting('zibal_style_settings', 'ZD_TitleColor');
        register_setting('zibal_style_settings', 'ZD_LabelColor');
        register_setting('zibal_style_settings', 'ZD_TextColor');
    }
    

    public function admin_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('zibal_messages', 'zibal_message', 'تنظیمات با موفقیت ذخیره شد.', 'updated');
        }
        
        settings_errors('zibal_messages');
        
        $total_amount = get_option('ZD_TotalAmount', 0);
        $total_payments = get_option('ZD_TotalPayment', 0);
        
        ?>
        <div class="wrap">
            <h1>تنظیمات حمایت مالی زیبال</h1>
            
            <!-- آمار کلی -->
            <div class="zibal-stats-cards">
                <div class="zibal-stat-card">
                    <h3>مجموع پرداخت‌ها</h3>
                    <p class="stat-number"><?php echo number_format($total_amount); ?> ریال</p>
                </div>
                <div class="zibal-stat-card">
                    <h3>تعداد تراکنش‌ها</h3>
                    <p class="stat-number"><?php echo number_format($total_payments); ?></p>
                </div>
                <div class="zibal-stat-card">
                    <h3>میانگین پرداخت</h3>
                    <p class="stat-number">
                        <?php echo $total_payments > 0 ? number_format($total_amount / $total_payments) : '0'; ?> ریال
                    </p>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('zibal_donate_settings');
                do_settings_sections('zibal_donate_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ZD_MerchantID">کد درگاه پرداخت (مرچنت)</label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="ZD_MerchantID" 
                                name="ZD_MerchantID" 
                                value="<?php echo esc_attr(get_option('ZD_MerchantID')); ?>" 
                                class="regular-text"
                                required
                            />
                            <button type="button" id="test-gateway" class="button button-secondary" style="margin-right: 10px;">
                                تست اتصال
                            </button>
                            <p class="description">
                                کد درگاه پرداخت خود را از پنل زیبال دریافت کنید. 
                                برای تست می‌توانید از "zibal" استفاده کنید.
                            </p>
                            <div id="gateway-test-results" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ZD_Unit">واحد پول</label>
                        </th>
                        <td>
                            <select id="ZD_Unit" name="ZD_Unit">
                                <option value="تومان" <?php selected(get_option('ZD_Unit'), 'تومان'); ?>>تومان</option>
                                <option value="ریال" <?php selected(get_option('ZD_Unit'), 'ریال'); ?>>ریال</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ZD_MinAmount">حداقل مبلغ (ریال)</label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                id="ZD_MinAmount" 
                                name="ZD_MinAmount" 
                                value="<?php echo esc_attr(get_option('ZD_MinAmount', 1000)); ?>" 
                                class="regular-text"
                                min="1000"
                            />
                            <p class="description">حداقل مبلغ قابل پرداخت به ریال</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ZD_MaxAmount">حداکثر مبلغ (ریال)</label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                id="ZD_MaxAmount" 
                                name="ZD_MaxAmount" 
                                value="<?php echo esc_attr(get_option('ZD_MaxAmount', 50000000)); ?>" 
                                class="regular-text"
                                min="1000"
                            />
                            <p class="description">حداکثر مبلغ قابل پرداخت به ریال</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ZD_IsOK">پیام پرداخت موفق</label>
                        </th>
                        <td>
                            <textarea 
                                id="ZD_IsOK" 
                                name="ZD_IsOK" 
                                rows="3" 
                                class="large-text"
                            ><?php echo esc_textarea(get_option('ZD_IsOK')); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ZD_IsError">پیام خطا در پرداخت</label>
                        </th>
                        <td>
                            <textarea 
                                id="ZD_IsError" 
                                name="ZD_IsError" 
                                rows="3" 
                                class="large-text"
                            ><?php echo esc_textarea(get_option('ZD_IsError')); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
            
            <!-- راهنمای استفاده -->
            <div class="zibal-help-section">
                <h2>راهنمای استفاده</h2>
                <div class="zibal-help-content">
                    <h3>نحوه استفاده از shortcode:</h3>
                    <code>[ZibalDonate]</code>
                    
                    <h3>پارامترهای اختیاری:</h3>
                    <ul>
                        <li><code>title="عنوان فرم"</code> - عنوان فرم پرداخت</li>
                        <li><code>description="توضیحات"</code> - توضیحات فرم</li>
                        <li><code>min_amount="1000"</code> - حداقل مبلغ</li>
                        <li><code>max_amount="1000000"</code> - حداکثر مبلغ</li>
                        <li><code>required_fields="name,amount"</code> - فیلدهای اجباری</li>
                        <li><code>button_text="پرداخت کنید"</code> - متن دکمه</li>
                    </ul>
                    
                    <h3>مثال کامل:</h3>
                    <code>[ZibalDonate title="حمایت از پروژه" description="از پروژه ما حمایت کنید" min_amount="5000" button_text="حمایت کنید"]</code>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ZD_UseCustomStyle').change(function() {
                if ($(this).is(':checked')) {
                    $('#custom-style-row').show();
                } else {
                    $('#custom-style-row').hide();
                }
            });
            
            $('#test-gateway').on('click', function() {
                var btn = $(this);
                var originalText = btn.text();
                var resultsDiv = $('#gateway-test-results');
                
                btn.text('در حال تست...').prop('disabled', true);
                resultsDiv.html('<p>در حال بررسی اتصال به gateway های زیبال...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_zibal_gateways',
                        nonce: '<?php echo wp_create_nonce('zibal_test_gateway'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; margin-top: 10px;">';
                            html += '<h4 style="margin-top: 0;">نتایج تست اتصال:</h4>';
                            html += '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<tr style="background: #f9f9f9;"><th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Gateway</th><th style="padding: 8px; text-align: center; border: 1px solid #ddd;">وضعیت</th><th style="padding: 8px; text-align: center; border: 1px solid #ddd;">زمان پاسخ</th></tr>';
                            
                            $.each(response.data, function(gateway, result) {
                                var statusColor = result.status === 'success' ? '#46b450' : '#dc3232';
                                var statusText = result.status === 'success' ? '✓ موفق' : '✗ ناموفق';
                                
                                html += '<tr>';
                                html += '<td style="padding: 8px; border: 1px solid #ddd;">' + gateway + '</td>';
                                html += '<td style="padding: 8px; text-align: center; border: 1px solid #ddd; color: ' + statusColor + '; font-weight: bold;">' + statusText + '</td>';
                                html += '<td style="padding: 8px; text-align: center; border: 1px solid #ddd;">' + result.response_time + '</td>';
                                html += '</tr>';
                                
                                if (result.error) {
                                    html += '<tr><td colspan="3" style="padding: 8px; border: 1px solid #ddd; color: #dc3232; font-size: 12px;">خطا: ' + result.error + '</td></tr>';
                                }
                            });
                            
                            html += '</table>';
                            html += '<p style="margin-bottom: 0; margin-top: 10px; font-size: 12px; color: #666;">در صورت عدم موفقیت gateway اول، به صورت خودکار از gateway دوم استفاده می‌شود.</p>';
                            html += '</div>';
                            
                            resultsDiv.html(html);
                        } else {
                            resultsDiv.html('<div class="notice notice-error"><p>خطا در تست اتصال: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultsDiv.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                    },
                    complete: function() {
                        btn.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <style>
        .zibal-stats-cards {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .zibal-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            flex: 1;
            text-align: center;
        }
        
        .zibal-stat-card h3 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }
        
        .zibal-help-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .zibal-help-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .zibal-help-content ul {
            margin: 10px 0;
            padding-right: 20px;
        }
        </style>
        <?php
    }
    

    public function style_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('zibal_style_messages', 'zibal_style_message', 'تنظیمات استایل با موفقیت ذخیره شد.', 'updated');
        }
        
        settings_errors('zibal_style_messages');
        
        $form_bg = get_option('ZD_FormBackgroundColor', '#ffffff');
        $input_bg = get_option('ZD_InputBackgroundColor', '#fafbfc');
        $input_border = get_option('ZD_InputBorderColor', '#e1e5e9');
        $button_bg = get_option('ZD_ButtonBackgroundColor', '#007cba');
        $button_hover = get_option('ZD_ButtonHoverColor', '#005a87');
        $title_color = get_option('ZD_TitleColor', '#2c3e50');
        $label_color = get_option('ZD_LabelColor', '#2c3e50');
        $text_color = get_option('ZD_TextColor', '#2c3e50');
        
        ?>
        <div class="wrap">
            <h1>تنظیمات استایل فرم پرداخت</h1>
            
            <div class="zibal-style-container">
                <div class="zibal-style-settings">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('zibal_style_settings');
                        do_settings_sections('zibal_style_settings');
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ZD_FormBackgroundColor">رنگ پس‌زمینه فرم</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_FormBackgroundColor" 
                                        name="ZD_FormBackgroundColor" 
                                        value="<?php echo esc_attr($form_bg); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($form_bg); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_InputBackgroundColor">رنگ پس‌زمینه فیلدها</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_InputBackgroundColor" 
                                        name="ZD_InputBackgroundColor" 
                                        value="<?php echo esc_attr($input_bg); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($input_bg); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_InputBorderColor">رنگ حاشیه فیلدها</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_InputBorderColor" 
                                        name="ZD_InputBorderColor" 
                                        value="<?php echo esc_attr($input_border); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($input_border); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_ButtonBackgroundColor">رنگ دکمه پرداخت</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_ButtonBackgroundColor" 
                                        name="ZD_ButtonBackgroundColor" 
                                        value="<?php echo esc_attr($button_bg); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($button_bg); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_ButtonHoverColor">رنگ دکمه هنگام hover</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_ButtonHoverColor" 
                                        name="ZD_ButtonHoverColor" 
                                        value="<?php echo esc_attr($button_hover); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($button_hover); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_TitleColor">رنگ عنوان</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_TitleColor" 
                                        name="ZD_TitleColor" 
                                        value="<?php echo esc_attr($title_color); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($title_color); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_LabelColor">رنگ برچسب‌ها</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_LabelColor" 
                                        name="ZD_LabelColor" 
                                        value="<?php echo esc_attr($label_color); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($label_color); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ZD_TextColor">رنگ متن</label>
                                </th>
                                <td>
                                    <input 
                                        type="color" 
                                        id="ZD_TextColor" 
                                        name="ZD_TextColor" 
                                        value="<?php echo esc_attr($text_color); ?>" 
                                        class="color-picker"
                                    />
                                    <input 
                                        type="text" 
                                        class="color-text" 
                                        value="<?php echo esc_attr($text_color); ?>" 
                                        readonly
                                    />
                                </td>
                            </tr>
                        </table>
                        
                        <div class="zibal-style-actions">
                            <?php submit_button('ذخیره تنظیمات استایل'); ?>
                            <button type="button" id="reset-colors" class="button">بازگردانی به حالت پیش‌فرض</button>
                        </div>
                    </form>
                </div>
                
                <div class="zibal-style-preview">
                    <h3>پیش‌نمایش فرم</h3>
                    <div class="zibal-preview-container">
                        <div class="zibal-donate-form" id="preview-form">
                            <h3 class="zibal-form-title">حمایت مالی</h3>
                            <p class="zibal-form-description">از پروژه ما حمایت کنید</p>
                            
                            <div class="zibal-form-group">
                                <label class="zibal-form-label">مبلغ *</label>
                                <div class="zibal-amount-wrapper">
                                    <input type="text" class="zibal-form-input" value="50,000" readonly>
                                    <span class="zibal-amount-unit">تومان</span>
                                </div>
                            </div>
                            
                            <div class="zibal-form-group">
                                <label class="zibal-form-label">نام و نام خانوادگی *</label>
                                <input type="text" class="zibal-form-input" value="نمونه نام" readonly>
                            </div>
                            
                            <div class="zibal-form-group">
                                <button type="button" class="zibal-submit-btn">پرداخت</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .zibal-style-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .zibal-style-settings {
            flex: 1;
        }
        
        .zibal-style-preview {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            position: sticky;
            top: 32px;
            height: fit-content;
        }
        
        .zibal-preview-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .color-picker {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .color-text {
            width: 80px;
            margin-right: 10px;
            text-align: center;
            font-family: monospace;
        }
        
        .zibal-style-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        #reset-colors {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        #reset-colors:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        @media (max-width: 1200px) {
            .zibal-style-container {
                flex-direction: column;
            }
            
            .zibal-style-preview {
                position: static;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            function updatePreview() {
                var formBg = $('#ZD_FormBackgroundColor').val();
                var inputBg = $('#ZD_InputBackgroundColor').val();
                var inputBorder = $('#ZD_InputBorderColor').val();
                var buttonBg = $('#ZD_ButtonBackgroundColor').val();
                var buttonHover = $('#ZD_ButtonHoverColor').val();
                var titleColor = $('#ZD_TitleColor').val();
                var labelColor = $('#ZD_LabelColor').val();
                var textColor = $('#ZD_TextColor').val();
                
                var previewForm = $('#preview-form');
                
                previewForm.css('background-color', formBg);
                previewForm.find('.zibal-form-title').css('color', titleColor);
                previewForm.find('.zibal-form-label').css('color', labelColor);
                previewForm.find('.zibal-form-description').css('color', textColor);
                previewForm.find('.zibal-form-input').css({
                    'background-color': inputBg,
                    'border-color': inputBorder,
                    'color': textColor
                });
                previewForm.find('.zibal-submit-btn').css('background', buttonBg);
                
                $('<style id="hover-style">').remove();
                $('<style id="hover-style">')
                    .prop('type', 'text/css')
                    .html('#preview-form .zibal-submit-btn:hover { background: ' + buttonHover + ' !important; }')
                    .appendTo('head');
            }
            
            $('.color-picker').on('input change', function() {
                var colorValue = $(this).val();
                $(this).siblings('.color-text').val(colorValue);
                updatePreview();
            });
            
            $('#reset-colors').on('click', function() {
                if (confirm('آیا مطمئن هستید که می‌خواهید تمام رنگ‌ها را به حالت پیش‌فرض بازگردانید؟')) {
                    $('#ZD_FormBackgroundColor').val('#ffffff');
                    $('#ZD_InputBackgroundColor').val('#fafbfc');
                    $('#ZD_InputBorderColor').val('#e1e5e9');
                    $('#ZD_ButtonBackgroundColor').val('#007cba');
                    $('#ZD_ButtonHoverColor').val('#005a87');
                    $('#ZD_TitleColor').val('#2c3e50');
                    $('#ZD_LabelColor').val('#2c3e50');
                    $('#ZD_TextColor').val('#2c3e50');
                    
                    $('.color-picker').each(function() {
                        $(this).siblings('.color-text').val($(this).val());
                    });
                    
                    updatePreview();
                }
            });
            
            updatePreview();
        });
        </script>
            margin: 20px 0;
        }
        
        .zibal-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            flex: 1;
            text-align: center;
        }
        
        .zibal-stat-card h3 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }
        
        .zibal-help-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .zibal-help-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .zibal-help-content ul {
            margin: 10px 0;
            padding-right: 20px;
        }
        </style>
        <?php
    }
    

    public function transactions_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $where_clause = '';
        if ($status_filter) {
            $where_clause = $wpdb->prepare(" WHERE status = %s", $status_filter);
        }
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
        $total_pages = ceil($total_items / $per_page);
        
        ?>
        <div class="wrap">
            <h1>تراکنش‌های پرداخت</h1>
            
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="zibal-transactions">
                    <select name="status">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>در انتظار</option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>>تکمیل شده</option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>>ناموفق</option>
                    </select>
                    <?php submit_button('فیلتر', 'secondary', 'filter', false); ?>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>شماره مرجع</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">هیچ تراکنشی یافت نشد.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo esc_html($transaction->track_id ?: $transaction->id); ?></td>
                                <td><?php echo esc_html($transaction->name); ?></td>
                                <td><?php echo number_format($transaction->amount); ?> ریال</td>
                                <td>
                                    <span class="status-<?php echo esc_attr($transaction->status); ?>">
                                        <?php
                                        $statuses = array(
                                            'pending' => 'در انتظار',
                                            'completed' => 'تکمیل شده',
                                            'failed' => 'ناموفق'
                                        );
                                        echo $statuses[$transaction->status] ?? $transaction->status;
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction->ref_number ?: '-'); ?></td>
                                <td><?php echo esc_html(date('Y/m/d H:i', strtotime($transaction->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .status-pending { color: #f56e28; }
        .status-completed { color: #46b450; }
        .status-failed { color: #dc3232; }
        </style>
        <?php
    }
    

    public function reports_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_transactions,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions
            FROM $table_name
        ");
        
        $monthly_stats = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as amount
            FROM $table_name 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        
        ?>
        <div class="wrap">
            <h1>گزارشات پرداخت</h1>
            
            <!-- آمار کلی -->
            <div class="zibal-stats-grid">
                <div class="zibal-stat-card">
                    <h3>کل تراکنش‌ها</h3>
                    <p class="stat-number"><?php echo number_format($stats->total_transactions); ?></p>
                </div>
                <div class="zibal-stat-card">
                    <h3>تراکنش‌های موفق</h3>
                    <p class="stat-number success"><?php echo number_format($stats->successful_transactions); ?></p>
                </div>
                <div class="zibal-stat-card">
                    <h3>تراکنش‌های ناموفق</h3>
                    <p class="stat-number failed"><?php echo number_format($stats->failed_transactions); ?></p>
                </div>
                <div class="zibal-stat-card">
                    <h3>مجموع درآمد</h3>
                    <p class="stat-number"><?php echo number_format($stats->total_amount); ?> ریال</p>
                </div>
            </div>
            
            <!-- آمار ماهانه -->
            <div class="zibal-monthly-stats">
                <h2>آمار ماهانه</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ماه</th>
                            <th>تعداد تراکنش</th>
                            <th>مبلغ کل</th>
                            <th>میانگین</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_stats as $stat): ?>
                            <tr>
                                <td><?php echo esc_html($stat->month); ?></td>
                                <td><?php echo number_format($stat->transactions); ?></td>
                                <td><?php echo number_format($stat->amount); ?> ریال</td>
                                <td>
                                    <?php 
                                    $avg = $stat->transactions > 0 ? $stat->amount / $stat->transactions : 0;
                                    echo number_format($avg); 
                                    ?> ریال
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .zibal-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-number.success { color: #46b450; }
        .stat-number.failed { color: #dc3232; }
        
        .zibal-monthly-stats {
            margin-top: 30px;
        }
        </style>
        <?php
    }
    

    public function ajax_test_gateways() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zibal_test_gateway')) {
            wp_send_json_error('درخواست نامعتبر است.');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('شما دسترسی لازم را ندارید.');
            return;
        }
        
        $api = new ZibalAPI();
        $results = $api->test_gateways();
        
        wp_send_json_success($results);
    }
}