<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZibalShortcode {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_zibal_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_zibal_process_payment', array($this, 'ajax_process_payment'));
    }
    
    public function init() {
        add_shortcode('ZibalDonate', array($this, 'donate_form_shortcode'));
        add_shortcode('zibal_donate_callback', array($this, 'callback_shortcode'));
    }
    
    public function donate_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'حمایت مالی',
            'description' => '',
            'min_amount' => get_option('ZD_MinAmount', 1000),
            'max_amount' => get_option('ZD_MaxAmount', 50000000),
            'required_fields' => 'name,amount',
            'show_description' => 'true',
            'button_text' => 'پرداخت'
        ), $atts);

        $atts['title'] = sanitize_text_field($atts['title']);
        $atts['description'] = sanitize_text_field($atts['description']);
        $atts['min_amount'] = absint($atts['min_amount']);
        $atts['max_amount'] = absint($atts['max_amount']);
        $atts['required_fields'] = sanitize_text_field($atts['required_fields']);
        $atts['show_description'] = sanitize_key($atts['show_description']);
        $atts['button_text'] = sanitize_text_field($atts['button_text']);
        
        if (empty(get_option('ZD_MerchantID'))) {
            return '<div class="zibal-error">کد درگاه پرداخت تنظیم نشده است.</div>';
        }
        
        ob_start();
        $this->render_donate_form($atts);
        return ob_get_clean();
    }
    

    public function callback_shortcode($atts) {
        if (!isset($_GET['status']) || !isset($_GET['trackId'])) {
            return '<div class="zibal-error">اطلاعات تراکنش نامعتبر است.</div>';
        }
        
        $status = sanitize_text_field(wp_unslash($_GET['status']));
        $track_id = sanitize_text_field(wp_unslash($_GET['trackId']));
        $transaction_id = isset($_GET['zd_payment']) ? absint(wp_unslash($_GET['zd_payment'])) : 0;
        $callback_token = isset($_GET['zd_token']) ? sanitize_text_field(wp_unslash($_GET['zd_token'])) : '';
        
        ob_start();
        $this->render_callback_result($status, $track_id, $transaction_id, $callback_token);
        return ob_get_clean();
    }
    

    private function render_donate_form($atts) {
        $unit = get_option('ZD_Unit', 'تومان');
        $allowed_required_fields = array('amount', 'name', 'mobile', 'email', 'description');
        $required_fields = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $atts['required_fields']))));
        $required_fields = array_values(array_intersect($required_fields, $allowed_required_fields));
        
        $form_bg = get_option('ZD_FormBackgroundColor', '#ffffff');
        $input_bg = get_option('ZD_InputBackgroundColor', '#fafbfc');
        $input_border = get_option('ZD_InputBorderColor', '#e1e5e9');
        $button_bg = get_option('ZD_ButtonBackgroundColor', '#007cba');
        $button_hover = get_option('ZD_ButtonHoverColor', '#005a87');
        $title_color = get_option('ZD_TitleColor', '#2c3e50');
        $label_color = get_option('ZD_LabelColor', '#2c3e50');
        $text_color = get_option('ZD_TextColor', '#2c3e50');
        
        ?>
        <style>
        .zibal-donate-form {
            background-color: <?php echo esc_attr($form_bg); ?> !important;
        }
        
        .zibal-form-title {
            color: <?php echo esc_attr($title_color); ?> !important;
        }
        
        .zibal-form-label {
            color: <?php echo esc_attr($label_color); ?> !important;
        }
        
        .zibal-form-description,
        .zibal-help-text {
            color: <?php echo esc_attr($text_color); ?> !important;
        }
        
        .zibal-form-input {
            background-color: <?php echo esc_attr($input_bg); ?> !important;
            border-color: <?php echo esc_attr($input_border); ?> !important;
            color: <?php echo esc_attr($text_color); ?> !important;
        }
        
        .zibal-submit-btn {
            background: <?php echo esc_attr($button_bg); ?> !important;
        }
        
        .zibal-submit-btn:hover {
            background: <?php echo esc_attr($button_hover); ?> !important;
        }
        </style>
        
        <div class="zibal-donate-wrapper">
            <form class="zibal-donate-form" id="zibal-donate-form" method="post">
                <?php wp_nonce_field('zibal_donate_form', 'zibal_nonce'); ?>
                
                <?php if (!empty($atts['title'])): ?>
                    <h3 class="zibal-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>
                
                <?php if (!empty($atts['description'])): ?>
                    <p class="zibal-form-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>
                
                <div class="zibal-messages" id="zibal-messages"></div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-amount">
                        مبلغ <?php echo in_array('amount', $required_fields, true) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <div class="zibal-amount-wrapper">
                        <input 
                            type="text" 
                            id="zibal-amount" 
                            name="amount" 
                            class="zibal-form-input" 
                            min="<?php echo esc_attr($atts['min_amount']); ?>"
                            max="<?php echo esc_attr($atts['max_amount']); ?>"
                            step="<?php echo esc_attr($unit === 'تومان' ? '100' : '1000'); ?>"
                            placeholder="مبلغ مورد نظر را وارد کنید"
                            <?php echo in_array('amount', $required_fields, true) ? 'required' : ''; ?>
                        >
                        <span class="zibal-amount-unit"><?php echo esc_html($unit); ?></span>
                    </div>
                    <small class="zibal-help-text">
                        حداقل: <?php echo esc_html(number_format($atts['min_amount'])); ?> ریال -
                        حداکثر: <?php echo esc_html(number_format($atts['max_amount'])); ?> ریال
                    </small>
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-name">
                        نام و نام خانوادگی <?php echo in_array('name', $required_fields, true) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="text" 
                        id="zibal-name" 
                        name="name" 
                        class="zibal-form-input"
                        placeholder="نام و نام خانوادگی خود را وارد کنید"
                        maxlength="255"
                        <?php echo in_array('name', $required_fields, true) ? 'required' : ''; ?>
                    >
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-mobile">
                        شماره موبایل <?php echo in_array('mobile', $required_fields, true) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="tel" 
                        id="zibal-mobile" 
                        name="mobile" 
                        class="zibal-form-input"
                        placeholder="09xxxxxxxxx"
                        pattern="09[0-9]{9}"
                        maxlength="11"
                        <?php echo in_array('mobile', $required_fields, true) ? 'required' : ''; ?>
                    >
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-email">
                        ایمیل <?php echo in_array('email', $required_fields, true) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="email" 
                        id="zibal-email" 
                        name="email" 
                        class="zibal-form-input"
                        placeholder="example@domain.com"
                        <?php echo in_array('email', $required_fields, true) ? 'required' : ''; ?>
                    >
                </div>
                
                <?php if ($atts['show_description'] === 'true'): ?>
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-description">
                        توضیحات <?php echo in_array('description', $required_fields, true) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <textarea 
                        id="zibal-description" 
                        name="description" 
                        class="zibal-form-input"
                        rows="3"
                        placeholder="توضیحات اضافی (اختیاری)"
                        maxlength="500"
                        <?php echo in_array('description', $required_fields, true) ? 'required' : ''; ?>
                    ></textarea>
                </div>
                <?php endif; ?>
                
                <div class="zibal-form-group">
                    <button type="submit" class="zibal-submit-btn" id="zibal-submit-btn">
                        <span class="btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                        <span class="btn-loading" style="display: none;">
                            <span class="zibal-spinner"></span>
                            در حال پردازش...
                        </span>
                    </button>
                </div>
                
                <div class="zibal-security-note">
                    <small>
                        🔒 پرداخت شما از طریق درگاه امن زیبال انجام می‌شود
                    </small>
                </div>
            </form>
        </div>
        
        <?php
    }
    

    private function render_callback_result($status, $track_id, $transaction_id = 0, $callback_token = '') {
        $api = new ZibalAPI();
        $result = $api->verify_payment($track_id, $transaction_id, $callback_token);

        if (is_wp_error($result)) {
            if ($status === '2') {
                $this->render_error_message($result->get_error_message());
            } else {
                $this->render_cancel_message($result->get_error_message());
            }
        } else {
            $this->render_success_message($result);
        }
    }
    

    private function render_success_message($result) {
        $success_message = get_option('ZD_IsOK', 'پرداخت شما با موفقیت انجام شد.');
        ?>
        <div class="zibal-callback-result zibal-success">
            <h3>پرداخت موفق</h3>
            <p><?php echo esc_html($success_message); ?></p>
            
            <div class="zibal-transaction-details">
                <?php if (!empty($result['ref_number'])): ?>
                    <p><strong>شماره تراکنش:</strong> <?php echo esc_html($result['ref_number']); ?></p>
                <?php endif; ?>
                <?php if (!empty($result['track_id'])): ?>
                    <p><strong>شناسه پیگیری:</strong> <?php echo esc_html($result['track_id']); ?></p>
                <?php endif; ?>
                <p><strong>مبلغ:</strong> <?php echo esc_html(number_format(absint(isset($result['amount']) ? $result['amount'] : 0))); ?> ریال</p>
                <p><strong>زمان تراکنش:</strong> <?php echo esc_html(!empty($result['paid_at']) ? $result['paid_at'] : date_i18n('Y/m/d H:i')); ?></p>
                <?php if (!empty($result['card_number'])): ?>
                    <p><strong>شماره کارت:</strong> <?php echo esc_html($result['card_number']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="zibal-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="zibal-btn zibal-btn-primary">
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>
        <?php
    }
    

    private function render_error_message($message) {
        $error_message = get_option('ZD_IsError', 'متاسفانه پرداخت انجام نشد.');
        ?>
        <div class="zibal-callback-result zibal-error">
            <div class="zibal-error-icon">❌</div>
            <h3>خطا در پرداخت</h3>
            <p><?php echo esc_html($error_message); ?></p>
            <div class="zibal-transaction-details">
                <p><strong>خطای زیبال:</strong> <?php echo esc_html($message); ?></p>
            </div>
            
            <div class="zibal-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="zibal-btn zibal-btn-secondary">
                    تلاش مجدد
                </a>
                <a href="<?php echo esc_url(home_url()); ?>" class="zibal-btn zibal-btn-primary">
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>
        <?php
    }
    

    private function render_cancel_message($message = '') {
        if (!$message) {
            $message = 'پرداخت توسط شما لغو شد یا با مشکل مواجه شد.';
        }
        ?>
        <div class="zibal-callback-result zibal-cancel">
            <div class="zibal-cancel-icon">⚠️</div>
            <h3>پرداخت لغو شد</h3>
            <p><?php echo esc_html($message); ?></p>
            
            <div class="zibal-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="zibal-btn zibal-btn-secondary">
                    تلاش مجدد
                </a>
                <a href="<?php echo esc_url(home_url()); ?>" class="zibal-btn zibal-btn-primary">
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>
        <?php
    }
    
    public function ajax_process_payment() {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_die('Invalid request');
        }
        
        $ajax_nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$ajax_nonce || !wp_verify_nonce($ajax_nonce, 'zibal_donate_nonce')) {
            wp_send_json_error('درخواست نامعتبر است.');
            return;
        }
        
        if (!isset($_POST['form_data'])) {
            wp_send_json_error('داده‌های فرم یافت نشد.');
            return;
        }
        
        $serialized_form_data = wp_unslash($_POST['form_data']);
        if (!is_string($serialized_form_data) || strlen($serialized_form_data) > 4096) {
            wp_send_json_error('حجم داده‌های فرم بیش از حد مجاز است.');
            return;
        }

        wp_parse_str($serialized_form_data, $form_data);
        
        $form_nonce = isset($form_data['zibal_nonce']) ? sanitize_text_field($form_data['zibal_nonce']) : '';
        if (!$form_nonce || !wp_verify_nonce($form_nonce, 'zibal_donate_form')) {
            wp_send_json_error('فرم نامعتبر است.');
            return;
        }
        
        $required_fields = array('amount', 'name');
        foreach ($required_fields as $field) {
            if (empty($form_data[$field])) {
                wp_send_json_error('لطفاً تمام فیلدهای اجباری را پر کنید.');
                return;
            }
        }
        
        $unit = get_option('ZD_Unit', 'تومان');
        $amount_raw = preg_replace('/[^0-9]/', '', (string) $form_data['amount']);
        $amount = absint($amount_raw);
        
        if ($amount <= 0) {
            wp_send_json_error('مبلغ وارد شده نامعتبر است.');
            return;
        }
        
        if ($unit === 'تومان') {
            $amount = $amount * 10;
        }
        
        $payment_data = array(
            'amount' => $amount,
            'name' => sanitize_text_field($form_data['name']),
            'mobile' => sanitize_text_field(isset($form_data['mobile']) ? $form_data['mobile'] : ''),
            'email' => sanitize_email(isset($form_data['email']) ? $form_data['email'] : ''),
            'description' => sanitize_textarea_field(isset($form_data['description']) ? $form_data['description'] : '')
        );
        
        $api = new ZibalAPI();
        $result = $api->request_payment($payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        if (isset($result['success']) && $result['success'] === true) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('خطا در پردازش پرداخت.');
        }
    }
}
