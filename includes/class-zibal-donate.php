<?php
/**
 * کلاس اصلی پلاگین زیبال
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZibalDonate {
    
    private static $instance = null;
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * راه‌اندازی hook ها
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('zibal_cleanup_reports', array($this, 'cleanup_old_logs'));
        
        // راه‌اندازی کلاس‌های دیگر
        new ZibalAdmin();
        new ZibalShortcode();
    }
    
    /**
     * مقداردهی اولیه
     */
    public function init() {
        // بارگذاری متن‌ها
        load_plugin_textdomain('zibal-donate', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // تنظیم session برای امنیت
        if (!session_id()) {
            session_start();
        }
    }
    
    /**
     * پاکسازی گزارش‌های قدیمی (بیش از 50 تا)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        // شمارش کل رکوردها
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // اگر بیشتر از 50 رکورد داریم، قدیمی‌ها رو پاک کن
        if ($total_count > 50) {
            // حذف رکوردهای قدیمی (نگه داشتن 50 تای جدید)
            $wpdb->query("
                DELETE FROM $table_name 
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table_name 
                        ORDER BY created_at DESC 
                        LIMIT 50
                    ) AS keep_records
                )
            ");
            
            // لاگ کردن عملیات
            $deleted_count = $total_count - 50;
            error_log(sprintf(
                '[Zibal Donate] Cleanup: %d old records deleted, keeping latest 50 records',
                $deleted_count
            ));
        }
    }
    
    /**
     * بارگذاری فایل‌های CSS و JS
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'zibal-donate-style',
            ZIBAL_DONATE_URL . 'assets/css/zibal-donate.css',
            array(),
            ZIBAL_DONATE_VERSION
        );
        
        wp_enqueue_script(
            'zibal-donate-script',
            ZIBAL_DONATE_URL . 'assets/js/zibal-donate.js',
            array('jquery'),
            ZIBAL_DONATE_VERSION,
            true
        );
        
        // ارسال متغیرهای AJAX
        wp_localize_script('zibal-donate-script', 'zibal_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zibal_donate_nonce'),
            'messages' => array(
                'processing' => __('در حال پردازش...', 'zibal-donate'),
                'error' => __('خطا در پردازش درخواست', 'zibal-donate')
            )
        ));
    }
    
    /**
     * بارگذاری فایل‌های ادمین
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'zibal-donate') === false) {
            return;
        }
        
        wp_enqueue_style(
            'zibal-donate-admin',
            ZIBAL_DONATE_URL . 'assets/css/admin.css',
            array(),
            ZIBAL_DONATE_VERSION
        );
        
        wp_enqueue_script(
            'zibal-donate-admin',
            ZIBAL_DONATE_URL . 'assets/js/admin.js',
            array('jquery'),
            ZIBAL_DONATE_VERSION,
            true
        );
    }
    
    /**
     * فعال‌سازی پلاگین
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        
        // ایجاد صفحه callback
        self::create_callback_page();
        
        // تنظیم cron job برای پاکسازی گزارش‌های قدیمی
        if (!wp_next_scheduled('zibal_cleanup_reports')) {
            wp_schedule_event(time(), 'daily', 'zibal_cleanup_reports');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * غیرفعال‌سازی پلاگین
     */
    public static function deactivate() {
        // حذف cron job
        $timestamp = wp_next_scheduled('zibal_cleanup_reports');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'zibal_cleanup_reports');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * ایجاد جداول دیتابیس
     */
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            track_id varchar(100) NOT NULL,
            amount bigint(20) NOT NULL,
            description text,
            name varchar(255),
            mobile varchar(20),
            email varchar(100),
            status varchar(20) DEFAULT 'pending',
            ref_number varchar(100),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY track_id (track_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * تنظیم گزینه‌های پیش‌فرض
     */
    private static function set_default_options() {
        $defaults = array(
            'ZD_MerchantID' => '',
            'ZD_IsOK' => 'با تشکر، پرداخت شما با موفقیت انجام شد.',
            'ZD_IsError' => 'متاسفانه پرداخت انجام نشد. لطفاً مجدداً تلاش کنید.',
            'ZD_Unit' => 'تومان',
            'ZD_TotalAmount' => 0,
            'ZD_TotalPayment' => 0,
            'ZD_RequiredFields' => array('name', 'amount'),
            'ZD_MinAmount' => 1000,
            'ZD_MaxAmount' => 50000000,
            // رنگ‌های پیش‌فرض
            'ZD_FormBackgroundColor' => '#ffffff',
            'ZD_InputBackgroundColor' => '#fafbfc',
            'ZD_InputBorderColor' => '#e1e5e9',
            'ZD_ButtonBackgroundColor' => '#007cba',
            'ZD_ButtonHoverColor' => '#005a87',
            'ZD_TitleColor' => '#2c3e50',
            'ZD_LabelColor' => '#2c3e50',
            'ZD_TextColor' => '#2c3e50'
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * ایجاد صفحه callback
     */
    private static function create_callback_page() {
        $page_title = 'نتیجه پرداخت';
        $page_content = '[zibal_donate_callback]';
        $page_template = '';
        
        $page = get_page_by_title($page_title);
        
        if (!$page) {
            $page_data = array(
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'post_slug' => 'zibal-donate-callback'
            );
            
            $page_id = wp_insert_post($page_data);
            update_option('ZD_CallbackPageID', $page_id);
        }
    }
    
    /**
     * CSS پیش‌فرض
     */
    private static function get_default_css() {
        return '/* استایل پیش‌فرض فرم زیبال */
.zibal-donate-form {
    max-width: 500px;
    margin: 20px auto;
    padding: 30px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    direction: rtl;
    font-family: "IRANSans", Tahoma, Arial, sans-serif;
}

.zibal-form-group {
    margin-bottom: 20px;
}

.zibal-form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
}

.zibal-form-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.zibal-form-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 3px rgba(0,124,186,0.1);
}

.zibal-submit-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.zibal-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,124,186,0.3);
}

.zibal-message {
    padding: 15px;
    margin: 15px 0;
    border-radius: 6px;
    text-align: center;
}

.zibal-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.zibal-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.zibal-loading {
    display: none;
    text-align: center;
    padding: 20px;
}

.zibal-spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007cba;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.zibal-amount-unit {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    font-size: 14px;
}

.zibal-amount-wrapper {
    position: relative;
}

@media (max-width: 768px) {
    .zibal-donate-form {
        margin: 10px;
        padding: 20px;
    }
}';
    }
}