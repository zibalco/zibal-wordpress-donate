<?php
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
        $this->merchant_id = sanitize_text_field(get_option('ZD_MerchantID'));
    }
    
   
    public function request_payment($data) {
        $validated_data = $this->validate_payment_data($data);
        if (is_wp_error($validated_data)) {
            return $validated_data;
        }

        $validated_data['callback_token'] = wp_generate_password(32, false, false);
        $validated_data['ip_address'] = $this->get_client_ip();
        $transaction_id = $this->save_transaction($validated_data);
        if (!$transaction_id) {
            return new WP_Error('db_error', 'خطا در ذخیره تراکنش');
        }

        $rate_limit_result = $this->enforce_request_rate_limit($transaction_id, $validated_data['ip_address']);
        if (is_wp_error($rate_limit_result)) {
            $this->delete_transaction($transaction_id);
            return $rate_limit_result;
        }

        $request_data = array(
            'merchant' => $this->merchant_id,
            'amount' => $validated_data['amount'],
            'description' => $this->sanitize_description($validated_data['description']),
            'mobile' => $this->sanitize_mobile($validated_data['mobile']),
            'callbackUrl' => $this->get_callback_url($transaction_id, $validated_data['callback_token'])
        );

        $response = $this->send_request('v1/request', $request_data);

        if (is_wp_error($response)) {
            $this->update_transaction_status($transaction_id, 'failed', $response->get_error_message());
            return $response;
        }
        
        if (!isset($response['result'])) {
            $this->update_transaction_status($transaction_id, 'failed', 'Invalid Zibal response');
            return new WP_Error('payment_error', 'پاسخ درگاه پرداخت نامعتبر است');
        }

        if ((int) $response['result'] === 100 && !empty($response['trackId'])) {
            $track_id = sanitize_text_field($response['trackId']);
            $this->update_transaction($transaction_id, array(
                'track_id' => $track_id,
                'status' => 'pending'
            ));
            
            $gateway_url = $this->gateway_urls[$this->current_gateway_index] . rawurlencode($track_id);
            
            return array(
                'success' => true,
                'redirect_url' => $gateway_url,
                'track_id' => $track_id
            );
        } else {
            $error_message = $this->get_zibal_response_message($response);
            $this->update_transaction_status($transaction_id, 'failed', $error_message, (int) $response['result']);
            return new WP_Error('payment_error', $error_message);
        }
    }
    

    public function verify_payment($track_id, $transaction_id = 0, $callback_token = '') {
        $track_id = sanitize_text_field($track_id);
        $transaction_id = absint($transaction_id);
        $callback_token = sanitize_text_field($callback_token);

        if (empty($track_id)) {
            return new WP_Error('invalid_track_id', 'شناسه تراکنش نامعتبر است');
        }
        
        $transaction = $transaction_id ? $this->get_transaction_by_id($transaction_id) : $this->get_transaction_by_track_id($track_id);
        if (!$transaction) {
            return new WP_Error('transaction_not_found', 'تراکنش یافت نشد');
        }

        if (!hash_equals((string) $transaction->track_id, (string) $track_id)) {
            return new WP_Error('track_mismatch', 'اطلاعات تراکنش با پرداخت ثبت‌شده همخوانی ندارد');
        }

        if (!empty($transaction->callback_token) && !hash_equals((string) $transaction->callback_token, (string) $callback_token)) {
            return new WP_Error('callback_token_mismatch', 'اطلاعات بازگشت پرداخت معتبر نیست');
        }
        
        if ($transaction->status === 'completed') {
            return $this->get_completed_transaction_result($transaction, true);
        }

        $claim_result = $this->claim_transaction_for_verification($transaction->id);
        if (is_wp_error($claim_result)) {
            return $claim_result;
        }

        if (!$claim_result) {
            $current_transaction = $this->get_transaction_by_id($transaction->id);
            if ($current_transaction && $current_transaction->status === 'completed') {
                return $this->get_completed_transaction_result($current_transaction, true);
            }

            if ($current_transaction && $current_transaction->status === 'verifying') {
                return new WP_Error('verify_in_progress', 'پردازش تراکنش در حال انجام است. لطفاً چند لحظه بعد دوباره بررسی کنید');
            }

            return new WP_Error('invalid_transaction_state', 'وضعیت فعلی تراکنش اجازه تایید مجدد را نمی‌دهد');
        }
        
        $verify_data = array(
            'merchant' => $this->merchant_id,
            'trackId' => $track_id
        );
        
        $response = $this->send_request('v1/verify', $verify_data);
        
        if (is_wp_error($response)) {
            $this->update_transaction_status($transaction->id, 'pending', $response->get_error_message());
            return $response;
        }
        
        if (!isset($response['result'])) {
            $this->update_transaction_status($transaction->id, 'needs_review', 'Invalid Zibal verify response');
            return new WP_Error('verify_error', 'پاسخ تایید پرداخت نامعتبر است');
        }

        if ((int) $response['result'] === 100) {
            $verified_amount = isset($response['amount']) ? absint($response['amount']) : 0;
            if ($verified_amount !== absint($transaction->amount)) {
                $this->update_transaction_status($transaction->id, 'needs_review', 'Amount mismatch', (int) $response['result']);
                return new WP_Error('amount_mismatch', 'مبلغ تایید شده با مبلغ تراکنش همخوانی ندارد');
            }

            $ref_number = isset($response['refNumber']) ? sanitize_text_field($response['refNumber']) : '';
            $card_number = $this->mask_card_number($this->get_response_field($response, array('cardNumber', 'card_number', 'cardNo')));
            $paid_at = sanitize_text_field($this->get_response_field($response, array('paidAt', 'paid_at', 'paymentDate', 'createdAt'), current_time('mysql')));
            $zibal_message = $this->get_zibal_response_message($response);
            $completion_result = $this->complete_transaction($transaction->id, array(
                'status' => 'completed',
                'ref_number' => $ref_number,
                'card_number' => $card_number,
                'paid_at' => $paid_at,
                'zibal_result' => (int) $response['result'],
                'zibal_message' => $zibal_message
            ));

            if ($completion_result !== 1) {
                $current_transaction = $this->get_transaction_by_id($transaction->id);
                if ($current_transaction && $current_transaction->status === 'completed') {
                    return $this->get_completed_transaction_result($current_transaction, true);
                }

                $this->update_transaction_status($transaction->id, 'needs_review', 'Atomic completion update failed');
                return new WP_Error('completion_conflict', 'ثبت نهایی تراکنش انجام نشد. تراکنش نیازمند بررسی است');
            }
            
            $this->update_total_amount($verified_amount);
            
            return array(
                'success' => true,
                'ref_number' => $ref_number,
                'track_id' => $track_id,
                'amount' => $verified_amount,
                'card_number' => $card_number,
                'paid_at' => $paid_at,
                'zibal_result' => (int) $response['result'],
                'zibal_message' => $zibal_message
            );
        } elseif ((int) $response['result'] === 201) {
            $error_message = $this->get_zibal_response_message($response);
            $this->update_transaction_status($transaction->id, 'needs_review', $error_message, (int) $response['result']);
            return new WP_Error('already_verified_remote', $error_message, array('zibal_result' => (int) $response['result']));
        } else {
            $error_message = $this->get_zibal_response_message($response);
            $this->update_transaction_status($transaction->id, 'failed', $error_message, (int) $response['result']);
            return new WP_Error('verify_error', $error_message, array('zibal_result' => (int) $response['result']));
        }
    }

    public function record_callback_failure($track_id, $transaction_id = 0, $callback_token = '', $callback_status = '') {
        $track_id = sanitize_text_field($track_id);
        $transaction_id = absint($transaction_id);
        $callback_token = sanitize_text_field($callback_token);
        $callback_status = sanitize_text_field($callback_status);

        $transaction = $transaction_id ? $this->get_transaction_by_id($transaction_id) : $this->get_transaction_by_track_id($track_id);
        if (!$transaction) {
            return new WP_Error('transaction_not_found', 'تراکنش یافت نشد');
        }

        if ($track_id && $transaction->track_id && !hash_equals((string) $transaction->track_id, (string) $track_id)) {
            return new WP_Error('track_mismatch', 'اطلاعات تراکنش با پرداخت ثبت‌شده همخوانی ندارد');
        }

        if (!empty($transaction->callback_token) && !hash_equals((string) $transaction->callback_token, (string) $callback_token)) {
            return new WP_Error('callback_token_mismatch', 'اطلاعات بازگشت پرداخت معتبر نیست');
        }

        if ($transaction->status === 'completed') {
            return array(
                'message' => 'این تراکنش قبلاً با موفقیت ثبت شده است',
                'status' => 'completed',
            );
        }

        $message = $this->get_callback_status_message($callback_status);

        return array(
            'message' => $message . ' وضعیت نهایی تنها پس از استعلام از زیبال ثبت می‌شود.',
            'status' => $transaction->status,
        );
    }
    

    private function send_request($endpoint, $data) {
        $last_error = null;
        
        foreach ($this->api_base_urls as $index => $base_url) {
            $this->current_gateway_index = $index;
            $url = $base_url . $endpoint;
            
            $args = array(
                'method' => 'POST',
                'timeout' => $this->timeout,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'User-Agent' => $this->get_request_user_agent()
                ),
                'body' => wp_json_encode($data),
                'sslverify' => true,
                'httpversion' => '1.1'
            );
            
            $response = wp_remote_post($url, $args);
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $decoded = json_decode($body, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        if ($index > 0) {
                            $this->log_error('Gateway Fallback Success', array(
                                'endpoint' => $endpoint,
                                'gateway' => $base_url,
                                'message' => 'Primary gateway failed, fallback successful'
                            ));
                        }
                        return $decoded;
                    } else {
                        $last_error = json_last_error() === JSON_ERROR_NONE ? 'Invalid JSON response shape' : 'JSON Decode Error: ' . json_last_error_msg();
                    }
                } else {
                    $last_error = 'HTTP Error: Response code ' . $response_code;
                }
            } else {
                $last_error = 'Connection Error: ' . $response->get_error_message();
            }
            
            $this->log_error('Gateway Request Failed', array(
                'endpoint' => $endpoint,
                'gateway' => $base_url,
                'error' => $last_error,
                'attempt' => $index + 1
            ));
        }
        
        $this->log_error('All Gateways Failed', array(
            'endpoint' => $endpoint,
            'last_error' => $last_error
        ));
        
        return new WP_Error('api_error', 'خطا در ارتباط با درگاه پرداخت. لطفاً مجدداً تلاش کنید.');
    }
    

    private function validate_payment_data($data) {
        $errors = new WP_Error();
        
        if (empty($this->merchant_id)) {
            $errors->add('no_merchant', 'کد درگاه پرداخت تنظیم نشده است');
        }
        
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            $errors->add('invalid_amount', 'مبلغ وارد شده نامعتبر است');
        } else {
            $amount = intval($data['amount']);
            $min_amount = absint(get_option('ZD_MinAmount', 1000));
            $max_amount = absint(get_option('ZD_MaxAmount', 50000000));
            
            if ($amount < $min_amount) {
                $errors->add('amount_too_low', sprintf('حداقل مبلغ قابل پرداخت %s ریال است', number_format($min_amount)));
            }
            
            if ($amount > $max_amount) {
                $errors->add('amount_too_high', sprintf('حداکثر مبلغ قابل پرداخت %s ریال است', number_format($max_amount)));
            }
        }
        
        if (empty($data['name'])) {
            $errors->add('no_name', 'نام و نام خانوادگی الزامی است');
        } elseif (strlen($data['name']) < 2) {
            $errors->add('name_too_short', 'نام باید حداقل 2 کاراکتر باشد');
        } elseif (strlen($data['name']) > 255) {
            $errors->add('name_too_long', 'نام وارد شده بیش از حد مجاز است');
        }
        
        if (!empty($data['mobile']) && !$this->validate_mobile($data['mobile'])) {
            $errors->add('invalid_mobile', 'شماره موبایل نامعتبر است');
        }

        if (!empty($data['email']) && strlen($data['email']) > 100) {
            $errors->add('email_too_long', 'ایمیل وارد شده بیش از حد مجاز است');
        }

        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors->add('description_too_long', 'توضیحات وارد شده بیش از حد مجاز است');
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return array(
            'amount' => intval($data['amount']),
            'name' => sanitize_text_field($data['name']),
            'mobile' => isset($data['mobile']) ? sanitize_text_field($data['mobile']) : '',
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'email' => isset($data['email']) ? sanitize_email($data['email']) : ''
        );
    }
    

    private function validate_mobile($mobile) {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        return preg_match('/^09[0-9]{9}$/', $mobile);
    }
    

    private function sanitize_description($description) {
        return wp_strip_all_tags($description);
    }

    private function sanitize_mobile($mobile) {
        return preg_replace('/[^0-9]/', '', $mobile);
    }
    

    private function get_callback_url($transaction_id = 0, $callback_token = '') {
        $callback_page_id = get_option('ZD_CallbackPageID');
        if ($callback_page_id) {
            $base_url = get_permalink($callback_page_id);
        } else {
            $base_url = home_url('/zibal-donate-callback/');
        }
        if (!$base_url) {
            $base_url = home_url('/zibal-donate-callback/');
        }

        return add_query_arg(
            array(
                'zd_payment' => absint($transaction_id),
                'zd_token' => $callback_token,
            ),
            $base_url
        );
    }
    

    private function save_transaction($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;

        $insert_data = array(
            'amount' => $data['amount'],
            'description' => $data['description'],
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'],
            'status' => 'requesting',
            'callback_token' => $data['callback_token'],
            'ip_address' => $data['ip_address'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            $this->log_error('Database Insert Failed', $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    

    private function update_transaction($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table_name, $data, array('id' => $id));
    }

    private function complete_transaction($id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table_name,
            $data,
            array(
                'id' => absint($id),
                'status' => 'verifying',
            )
        );
    }

    private function claim_transaction_for_verification($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $stale_threshold = gmdate('Y-m-d H:i:s', current_time('timestamp') - (2 * MINUTE_IN_SECONDS));
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name
             SET status = %s, updated_at = %s
             WHERE id = %d
             AND (status = %s OR (status = %s AND updated_at < %s))",
            'verifying',
            current_time('mysql'),
            absint($id),
            'pending',
            'verifying',
            $stale_threshold
        ));

        if ($result === false) {
            return new WP_Error('verification_lock_failed', 'امکان قفل کردن تراکنش برای تایید وجود ندارد');
        }

        return $result === 1;
    }
    

    private function update_transaction_status($id, $status, $error_message = '', $zibal_result = null) {
        $data = array('status' => $status);
        if ($error_message) {
            $data['zibal_message'] = sanitize_text_field($error_message);
        }
        if ($zibal_result !== null) {
            $data['zibal_result'] = intval($zibal_result);
        }
        
        return $this->update_transaction($id, $data);
    }
    

    private function get_transaction_by_track_id($track_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE track_id = %s",
            $track_id
        ));
    }

    private function get_transaction_by_id($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            absint($id)
        ));
    }

    private function get_completed_transaction_result($transaction, $already_verified = false) {
        return array(
            'success' => true,
            'already_verified' => (bool) $already_verified,
            'ref_number' => $transaction->ref_number,
            'track_id' => $transaction->track_id,
            'amount' => $transaction->amount,
            'card_number' => $transaction->card_number,
            'paid_at' => $transaction->paid_at,
        );
    }

    private function enforce_request_rate_limit($transaction_id, $ip_address) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $per_ip_limit = absint(apply_filters('zibal_donate_rate_limit_per_ip', 5));
        $per_ip_window = absint(apply_filters('zibal_donate_rate_limit_per_ip_window', 10 * MINUTE_IN_SECONDS));
        $global_limit = absint(apply_filters('zibal_donate_rate_limit_global', 20));
        $global_window = absint(apply_filters('zibal_donate_rate_limit_global_window', MINUTE_IN_SECONDS));
        $concurrent_limit = absint(apply_filters('zibal_donate_rate_limit_concurrent', 5));

        if ($per_ip_limit > 0 && $per_ip_window > 0) {
            $ip_threshold = gmdate('Y-m-d H:i:s', current_time('timestamp') - $per_ip_window);
            $ip_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                 WHERE ip_address = %s AND created_at >= %s AND id <= %d",
                $ip_address,
                $ip_threshold,
                absint($transaction_id)
            ));

            if ($ip_count > $per_ip_limit) {
                return new WP_Error('rate_limited', 'تعداد درخواست‌های پرداخت بیش از حد مجاز است. لطفاً چند دقیقه دیگر تلاش کنید');
            }
        }

        if ($global_limit > 0 && $global_window > 0) {
            $global_threshold = gmdate('Y-m-d H:i:s', current_time('timestamp') - $global_window);
            $global_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                 WHERE created_at >= %s AND id <= %d",
                $global_threshold,
                absint($transaction_id)
            ));

            if ($global_count > $global_limit) {
                return new WP_Error('rate_limited', 'سامانه پرداخت موقتاً پرترافیک است. لطفاً چند دقیقه دیگر تلاش کنید');
            }
        }

        if ($concurrent_limit > 0) {
            $concurrent_threshold = gmdate('Y-m-d H:i:s', current_time('timestamp') - (2 * MINUTE_IN_SECONDS));
            $concurrent_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                 WHERE status = %s AND created_at >= %s AND id <= %d",
                'requesting',
                $concurrent_threshold,
                absint($transaction_id)
            ));

            if ($concurrent_count > $concurrent_limit) {
                return new WP_Error('rate_limited', 'ظرفیت هم‌زمان درگاه تکمیل است. لطفاً چند دقیقه دیگر تلاش کنید');
            }
        }

        return true;
    }

    private function delete_transaction($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        return $wpdb->delete($table_name, array('id' => absint($id)), array('%d'));
    }

    private function update_total_amount($amount) {
        $current_total = absint(get_option('ZD_TotalAmount', 0));
        $new_total = $current_total + absint($amount);
        update_option('ZD_TotalAmount', $new_total);
        
        $current_count = absint(get_option('ZD_TotalPayment', 0));
        update_option('ZD_TotalPayment', $current_count + 1);
    }
    

    private function get_client_ip() {
        $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!filter_var($remote_address, FILTER_VALIDATE_IP)) {
            $remote_address = '0.0.0.0';
        }

        $client_ip = apply_filters('zibal_donate_client_ip', $remote_address, $_SERVER);
        $client_ip = sanitize_text_field($client_ip);

        return filter_var($client_ip, FILTER_VALIDATE_IP) ? $client_ip : $remote_address;
    }
    

    private function log_error($message, $data = array()) {
        error_log(sprintf(
            '[Zibal Donate] %s: %s',
            $message,
            wp_json_encode($data)
        ));
    }
    

    public function test_gateways() {
        $results = array();
        
        foreach ($this->api_base_urls as $index => $base_url) {
            $url = $base_url . 'v1/request';
            
            $start_time = microtime(true);
            
            $args = array(
                'method' => 'POST',
                'timeout' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'User-Agent' => $this->get_request_user_agent()
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

    private function get_callback_status_message($status) {
        $messages = array(
            '0' => 'پرداخت توسط کاربر لغو شد یا در درگاه تکمیل نشد',
            '1' => 'پرداخت توسط کاربر لغو شد',
            'cancelled' => 'پرداخت توسط کاربر لغو شد',
            'canceled' => 'پرداخت توسط کاربر لغو شد',
            'failed' => 'پرداخت در درگاه ناموفق بود',
        );

        return isset($messages[$status]) ? $messages[$status] : 'پرداخت ناموفق بود یا کاربر به درگاه بازنگشت';
    }

    private function get_zibal_response_message($response) {
        $message = $this->get_response_field($response, array('message', 'errorMessage', 'error', 'description'));
        if ($message !== '') {
            return sanitize_text_field($message);
        }

        return isset($response['result']) ? $this->get_error_message((int) $response['result']) : 'خطای نامشخص در پردازش پرداخت';
    }

    private function get_response_field($response, $keys, $default = '') {
        foreach ($keys as $key) {
            if (isset($response[$key]) && $response[$key] !== '') {
                return is_scalar($response[$key]) ? (string) $response[$key] : $default;
            }
        }

        return $default;
    }

    private function mask_card_number($card_number) {
        $card_number = sanitize_text_field($card_number);
        if ($card_number === '') {
            return '';
        }

        if (strpos($card_number, '*') !== false || strpos($card_number, '-') !== false) {
            return $card_number;
        }

        $digits = preg_replace('/\D+/', '', $card_number);
        if (strlen($digits) < 10) {
            return $card_number;
        }

        return substr($digits, 0, 6) . str_repeat('*', max(0, strlen($digits) - 10)) . substr($digits, -4);
    }

    private function get_request_user_agent() {
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $parts = array(
            'ZibalDonate/' . ZIBAL_DONATE_VERSION,
            'WordPress/' . get_bloginfo('version'),
        );

        if ($site_host) {
            $parts[] = 'Site/' . $site_host;
        }

        return sanitize_text_field(implode(' ', $parts));
    }
}
