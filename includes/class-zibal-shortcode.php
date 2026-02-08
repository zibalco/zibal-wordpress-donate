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
    
unction donate_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'حمایت مالی',
            'description' => '',
            'min_amount' => get_option('ZD_MinAmount', 1000),
            'max_amount' => get_option('ZD_MaxAmount', 50000000),
            'required_fields' => 'name,amount',
            'show_description' => 'true',
            'button_text' => 'پرداخت'
        ), $atts);
        
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
        
        $status = sanitize_text_field($_GET['status']);
        $track_id = sanitize_text_field($_GET['trackId']);
        
        ob_start();
        $this->render_callback_result($status, $track_id);
        return ob_get_clean();
    }
    

    private function render_donate_form($atts) {
        $nonce = wp_create_nonce('zibal_donate_form');
        $unit = get_option('ZD_Unit', 'تومان');
        $required_fields = explode(',', $atts['required_fields']);
        
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
                        مبلغ <?php echo in_array('amount', $required_fields) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <div class="zibal-amount-wrapper">
                        <input 
                            type="text" 
                            id="zibal-amount" 
                            name="amount" 
                            class="zibal-form-input" 
                            min="<?php echo esc_attr($atts['min_amount']); ?>"
                            max="<?php echo esc_attr($atts['max_amount']); ?>"
                            step="<?php echo $unit === 'تومان' ? '100' : '1000'; ?>"
                            placeholder="مبلغ مورد نظر را وارد کنید"
                            <?php echo in_array('amount', $required_fields) ? 'required' : ''; ?>
                        >
                        <span class="zibal-amount-unit"><?php echo esc_html($unit); ?></span>
                    </div>
                    <small class="zibal-help-text">
                        حداقل: <?php echo number_format($atts['min_amount']); ?> ریال - 
                        حداکثر: <?php echo number_format($atts['max_amount']); ?> ریال
                    </small>
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-name">
                        نام و نام خانوادگی <?php echo in_array('name', $required_fields) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="text" 
                        id="zibal-name" 
                        name="name" 
                        class="zibal-form-input"
                        placeholder="نام و نام خانوادگی خود را وارد کنید"
                        maxlength="255"
                        <?php echo in_array('name', $required_fields) ? 'required' : ''; ?>
                    >
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-mobile">
                        شماره موبایل <?php echo in_array('mobile', $required_fields) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="tel" 
                        id="zibal-mobile" 
                        name="mobile" 
                        class="zibal-form-input"
                        placeholder="09xxxxxxxxx"
                        pattern="09[0-9]{9}"
                        maxlength="11"
                        <?php echo in_array('mobile', $required_fields) ? 'required' : ''; ?>
                    >
                </div>
                
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-email">
                        ایمیل <?php echo in_array('email', $required_fields) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input 
                        type="email" 
                        id="zibal-email" 
                        name="email" 
                        class="zibal-form-input"
                        placeholder="example@domain.com"
                        <?php echo in_array('email', $required_fields) ? 'required' : ''; ?>
                    >
                </div>
                
                <?php if ($atts['show_description'] === 'true'): ?>
                <div class="zibal-form-group">
                    <label class="zibal-form-label" for="zibal-description">
                        توضیحات <?php echo in_array('description', $required_fields) ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <textarea 
                        id="zibal-description" 
                        name="description" 
                        class="zibal-form-input"
                        rows="3"
                        placeholder="توضیحات اضافی (اختیاری)"
                        maxlength="500"
                        <?php echo in_array('description', $required_fields) ? 'required' : ''; ?>
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
    

    private function render_callback_result($status, $track_id) {
        if ($status == '2') {
            $api = new ZibalAPI();
            $result = $api->verify_payment($track_id);
            
            if (is_wp_error($result)) {
                $this->render_error_message($result->get_error_message());
            } else {
                $this->render_success_message($result);
            }
        } else {
            $this->render_cancel_message();
        }
    }
    

    private function render_success_message($result) {
        $success_message = get_option('ZD_IsOK', 'پرداخت شما با موفقیت انجام شد.');
        ?>
        <div class="zibal-callback-result zibal-success">
            <h3>پرداخت موفق</h3>
            <p><?php echo esc_html($success_message); ?></p>
            
            <?php if (!empty($result['ref_number'])): ?>
                <div class="zibal-transaction-details">
                    <p><strong>شماره مرجع:</strong> <?php echo esc_html($result['ref_number']); ?></p>
                    <p><strong>مبلغ:</strong> <?php echo number_format($result['amount']); ?> ریال</p>
                    <p><strong>تاریخ:</strong> <?php echo date('Y/m/d H:i'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="zibal-actions">
                <a href="<?php echo home_url(); ?>" class="zibal-btn zibal-btn-primary">
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
            <p class="zibal-error-details"><?php echo esc_html($message); ?></p>
            
            <div class="zibal-actions">
                <a href="javascript:history.back()" class="zibal-btn zibal-btn-secondary">
                    تلاش مجدد
                </a>
                <a href="<?php echo home_url(); ?>" class="zibal-btn zibal-btn-primary">
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>
        <?php
    }
    

    private function render_cancel_message() {
        ?>
        <div class="zibal-callback-result zibal-cancel">
            <div class="zibal-cancel-icon">⚠️</div>
            <h3>پرداخت لغو شد</h3>
            <p>پرداخت توسط شما لغو شد یا با مشکل مواجه شد.</p>
            
            <div class="zibal-actions">
                <a href="javascript:history.back()" class="zibal-btn zibal-btn-secondary">
                    تلاش مجدد
                </a>
                <a href="<?php echo home_url(); ?>" class="zibal-btn zibal-btn-primary">
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
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zibal_donate_nonce')) {
            wp_send_json_error('درخواست نامعتبر است.');
            return;
        }
        
        if (!isset($_POST['form_data'])) {
            wp_send_json_error('داده‌های فرم یافت نشد.');
            return;
        }
        
        parse_str($_POST['form_data'], $form_data);
        
        if (!isset($form_data['zibal_nonce']) || !wp_verify_nonce($form_data['zibal_nonce'], 'zibal_donate_form')) {
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
        $amount_raw = str_replace(',', '', $form_data['amount']); 
        $amount = intval($amount_raw);
        
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
            'mobile' => sanitize_text_field($form_data['mobile'] ?? ''),
            'email' => sanitize_email($form_data['email'] ?? ''),
            'description' => sanitize_textarea_field($form_data['description'] ?? '')
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