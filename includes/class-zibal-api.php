<?php
/**
 * کلاس API زیبال با امنیت بالا
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZibalAPI {
    
    private $merchant_id;
    private $api_base_urls = array(
        'https://gateway.zibal.ir/',
        'https://gateway.zibal.io/'
    );
    private $gateway_urls = array(
        'https://gateway.zibal.ir/start/',
        'https://gateway.zibal.io/start/'
    );
    private $current_gateway_index = 0;
    private $timeout = 30;
    
    public function __construct() {
        $this->merchant_id = get_option('ZD_MerchantID');
    }
    
    /**
     * ارسال درخواست پرداخت
     */
    public function request_payment($data) {
        // اعتبارسنجی داده‌ها
        $validated_data = $this->validate_payment_data($data);
        if (is_wp_error($validated_data)) {
            return $validated_data;
        }
        
        // آماده‌سازی داده‌های ارسال
        $request_data = array(
            'merchant' => $this->merchant_id,
            'amount' => $validated_data['amount'],
            'description' => $this->sanitize_description($validated_data['description']),
            'mobile' => $this->sanitize_mobile($validated_data['mobile']),
            'callbackUrl' => $this->get_callback_url()
        );
        
        // ثبت تراکنش در دیتابیس
        $transaction_id = $this->save_transaction($validated_data);
        if (!$transaction_id) {
            return new WP_Error('db_error', 'خطا در ذخیره تراکنش');
        }
        
        // ارسال درخواست به زیبال
        $response = $this->send_request('v1/request', $request_data);
        
        if (is_wp_error($response)) {
            $this->update_transaction_status($transaction_id, 'failed', $response->get_error_message());
            return $response;
        }
        
        if ($response['result'] == 100) {
            // به‌روزرسانی تراکنش با track_id
            $this->update_transaction($transaction_id, array(
                'track_id' => $response['trackId'],
                'status' => 'pending'
            ));
            
            // استفاده از gateway URL مناسب
            $gateway_url = $this->gateway_urls[$this->current_gateway_index] . $response['trackId'];
            
            return array(
                'success' => true,
                'redirect_url' => $gateway_url,
                'track_id' => $response['trackId']
            );
        } else {
            $error_message = $this->get_error_message($response['result']);
            $this->update_transaction_status($transaction_id, 'failed', $error_message);
            return new WP_Error('payment_error', $error_message);
        }
    }
    
    /**
     * تایید پرداخت
     */
    public function verify_payment($track_id) {
        if (empty($track_id)) {
            return new WP_Error('invalid_track_id', 'شناسه تراکنش نامعتبر است');
        }
        
        // بررسی وجود تراکنش در دیتابیس
        $transaction = $this->get_transaction_by_track_id($track_id);
        if (!$transaction) {
            return new WP_Error('transaction_not_found', 'تراکنش یافت نشد');
        }
        
        // اگر قبلاً تایید شده
        if ($transaction->status === 'completed') {
            return array(
                'success' => true,
                'already_verified' => true,
                'ref_number' => $transaction->ref_number,
                'amount' => $transaction->amount
            );
        }
        
        $verify_data = array(
            'merchant' => $this->merchant_id,
            'trackId' => $track_id
        );
        
        $response = $this->send_request('v1/verify', $verify_data);
        
        if (is_wp_error($response)) {
            $this->update_transaction_status($transaction->id, 'failed', $response->get_error_message());
            return $response;
        }
        
        if ($response['result'] == 100) {
            // به‌روزرسانی تراکنش
            $this->update_transaction($transaction->id, array(
                'status' => 'completed',
                'ref_number' => $response['refNumber']
            ));
            
            // به‌روزرسانی مجموع پرداخت‌ها
            $this->update_total_amount($response['amount']);
            
            return array(
                'success' => true,
                'ref_number' => $response['refNumber'],
                'amount' => $response['amount']
            );
        } else {
            $error_message = $this->get_error_message($response['result']);
            $this->update_transaction_status($transaction->id, 'failed', $error_message);
            return new WP_Error('verify_error', $error_message);
        }
    }
    
    /**
     * ارسال درخواست امن به API با fallback
     */
    private function send_request($endpoint, $data) {
        $last_error = null;
        
        // تلاش با هر دو gateway
        foreach ($this->api_base_urls as $index => $base_url) {
            $this->current_gateway_index = $index;
            $url = $base_url . $endpoint;
            
            $args = array(
                'method' => 'POST',
                'timeout' => $this->timeout,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'User-Agent' => 'ZibalDonate/' . ZIBAL_DONATE_VERSION . ' WordPress/' . get_bloginfo('version')
                ),
                'body' => wp_json_encode($data),
                'sslverify' => true,
                'httpversion' => '1.1'
            );
            
            $response = wp_remote_post($url, $args);
            
            // اگر خطا نبود، پردازش کن
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $decoded = json_decode($body, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // موفقیت - لاگ کردن gateway استفاده شده
                        if ($index > 0) {
                            $this->log_error('Gateway Fallback Success', array(
                                'endpoint' => $endpoint,
                                'gateway' => $base_url,
                                'message' => 'Primary gateway failed, fallback successful'
                            ));
                        }
                        return $decoded;
                    } else {
                        $last_error = 'JSON Decode Error: ' . json_last_error_msg();
                    }
                } else {
                    $last_error = 'HTTP Error: Response code ' . $response_code;
                }
            } else {
                $last_error = 'Connection Error: ' . $response->get_error_message();
            }
            
            // لاگ کردن خطا و تلاش با gateway بعدی
            $this->log_error('Gateway Request Failed', array(
                'endpoint' => $endpoint,
                'gateway' => $base_url,
                'error' => $last_error,
                'attempt' => $index + 1
            ));
        }
        
        // اگر هیچ gateway جواب نداد
        $this->log_error('All Gateways Failed', array(
            'endpoint' => $endpoint,
            'last_error' => $last_error
        ));
        
        return new WP_Error('api_error', 'خطا در ارتباط با درگاه پرداخت. لطفاً مجدداً تلاش کنید.');
    }
    
    /**
     * اعتبارسنجی داده‌های پرداخت
     */
    private function validate_payment_data($data) {
        $errors = new WP_Error();
        
        // بررسی merchant ID
        if (empty($this->merchant_id)) {
            $errors->add('no_merchant', 'کد درگاه پرداخت تنظیم نشده است');
        }
        
        // بررسی مبلغ
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            $errors->add('invalid_amount', 'مبلغ وارد شده نامعتبر است');
        } else {
            $amount = intval($data['amount']);
            $min_amount = get_option('ZD_MinAmount', 1000);
            $max_amount = get_option('ZD_MaxAmount', 50000000);
            
            if ($amount < $min_amount) {
                $errors->add('amount_too_low', sprintf('حداقل مبلغ قابل پرداخت %s ریال است', number_format($min_amount)));
            }
            
            if ($amount > $max_amount) {
                $errors->add('amount_too_high', sprintf('حداکثر مبلغ قابل پرداخت %s ریال است', number_format($max_amount)));
            }
        }
        
        // بررسی نام
        if (empty($data['name'])) {
            $errors->add('no_name', 'نام و نام خانوادگی الزامی است');
        } elseif (strlen($data['name']) < 2) {
            $errors->add('name_too_short', 'نام باید حداقل 2 کاراکتر باشد');
        }
        
        // بررسی موبایل
        if (!empty($data['mobile']) && !$this->validate_mobile($data['mobile'])) {
            $errors->add('invalid_mobile', 'شماره موبایل نامعتبر است');
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return array(
            'amount' => intval($data['amount']),
            'name' => sanitize_text_field($data['name']),
            'mobile' => sanitize_text_field($data['mobile']),
            'description' => sanitize_textarea_field($data['description']),
            'email' => sanitize_email($data['email'])
        );
    }
    
    /**
     * اعتبارسنجی شماره موبایل
     */
    private function validate_mobile($mobile) {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        return preg_match('/^09[0-9]{9}$/', $mobile);
    }
    
    /**
     * پاک‌سازی توضیحات
     */
    private function sanitize_description($description) {
        return wp_strip_all_tags($description);
    }
    
    /**
     * پاک‌سازی شماره موبایل
     */
    private function sanitize_mobile($mobile) {
        return preg_replace('/[^0-9]/', '', $mobile);
    }
    
    /**
     * دریافت URL بازگشت
     */
    private function get_callback_url() {
        $callback_page_id = get_option('ZD_CallbackPageID');
        if ($callback_page_id) {
            return get_permalink($callback_page_id);
        }
        
        return home_url('/zibal-donate-callback/');
    }
    
    /**
     * ذخیره تراکنش در دیتابیس
     */
    private function save_transaction($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        $insert_data = array(
            'amount' => $data['amount'],
            'description' => $data['description'],
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'],
            'status' => 'pending',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            $this->log_error('Database Insert Failed', $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * به‌روزرسانی تراکنش
     */
    private function update_transaction($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table_name, $data, array('id' => $id));
    }
    
    /**
     * به‌روزرسانی وضعیت تراکنش
     */
    private function update_transaction_status($id, $status, $error_message = '') {
        $data = array('status' => $status);
        if ($error_message) {
            $data['description'] = $data['description'] . ' | Error: ' . $error_message;
        }
        
        return $this->update_transaction($id, $data);
    }
    
    /**
     * دریافت تراکنش با track_id
     */
    private function get_transaction_by_track_id($track_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE track_id = %s",
            $track_id
        ));
    }
    
    /**
     * به‌روزرسانی مجموع پرداخت‌ها
     */
    private function update_total_amount($amount) {
        $current_total = get_option('ZD_TotalAmount', 0);
        $new_total = $current_total + $amount;
        update_option('ZD_TotalAmount', $new_total);
        
        $current_count = get_option('ZD_TotalPayment', 0);
        update_option('ZD_TotalPayment', $current_count + 1);
    }
    
    /**
     * دریافت IP کلاینت
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * ثبت خطا - فقط در error_log وردپرس
     */
    private function log_error($message, $data = array()) {
        // فقط در error_log وردپرس ثبت می‌شود
        error_log(sprintf(
            '[Zibal Donate] %s: %s',
            $message,
            wp_json_encode($data)
        ));
    }
    
    /**
     * تست اتصال به gateway ها
     */
    public function test_gateways() {
        $results = array();
        
        foreach ($this->api_base_urls as $index => $base_url) {
            $url = $base_url . 'v1/request';
            
            $start_time = microtime(true);
            
            $args = array(
                'method' => 'POST',
                'timeout' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8'
                ),
                'body' => wp_json_encode(array(
                    'merchant' => 'zibal',
                    'amount' => 1000,
                    'callbackUrl' => home_url()
                )),
                'sslverify' => true
            );
            
            $response = wp_remote_post($url, $args);
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $results[$base_url] = array(
                'status' => !is_wp_error($response) ? 'success' : 'failed',
                'response_time' => $response_time . 'ms',
                'error' => is_wp_error($response) ? $response->get_error_message() : null
            );
        }
        
        return $results;
    }
    
    /**
     * دریافت پیام خطا
     */
    private function get_error_message($code) {
        $messages = array(
            100 => 'با موفقیت تایید شد',
            102 => 'merchant یافت نشد',
            103 => 'merchant غیرفعال',
            104 => 'merchant نامعتبر',
            105 => 'amount بایستی بزرگتر از 1,000 ریال باشد',
            106 => 'callbackUrl نامعتبر می‌باشد',
            113 => 'amount مبلغ تراکنش از سقف مجاز بیشتر است',
            201 => 'قبلاً تایید شده',
            202 => 'سفارش پرداخت نشده یا ناموفق بوده است',
            203 => 'trackId نامعتبر می‌باشد'
        );
        
        return isset($messages[$code]) ? $messages[$code] : 'خطای نامشخص در پردازش پرداخت';
    }
}