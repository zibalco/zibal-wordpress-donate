<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZibalDonate {
    
    private static $instance = null;
    
    public function __construct() {
        $this->init_hooks();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('zibal_cleanup_reports', array($this, 'cleanup_old_logs'));
        
        new ZibalAdmin();
        new ZibalShortcode();
    }
    
    public function init() {
        load_plugin_textdomain('zibal-donate', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (get_option('ZD_DB_VERSION') !== ZIBAL_DONATE_VERSION) {
            self::create_tables();
            update_option('ZD_DB_VERSION', ZIBAL_DONATE_VERSION);
        }
        
        if (!session_id()) {
            session_start();
        }
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($total_count > 50) {
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
            
            $deleted_count = $total_count - 50;
            error_log(sprintf(
                '[Zibal Donate] Cleanup: %d old records deleted, keeping latest 50 records',
                $deleted_count
            ));
        }
    }
    
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
        
        wp_localize_script('zibal-donate-script', 'zibal_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zibal_donate_nonce'),
            'messages' => array(
                'processing' => __('در حال پردازش...', 'zibal-donate'),
                'error' => __('خطا در پردازش درخواست', 'zibal-donate')
            )
        ));
    }
    
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
    
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::create_callback_page();
        update_option('ZD_DB_VERSION', ZIBAL_DONATE_VERSION);
        
        if (!wp_next_scheduled('zibal_cleanup_reports')) {
            wp_schedule_event(time(), 'daily', 'zibal_cleanup_reports');
        }
        
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        $timestamp = wp_next_scheduled('zibal_cleanup_reports');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'zibal_cleanup_reports');
        }
        
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . ZIBAL_DONATE_TABLE;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            track_id varchar(100) DEFAULT NULL,
            callback_token varchar(64) DEFAULT NULL,
            amount bigint(20) NOT NULL,
            description text,
            name varchar(255),
            mobile varchar(20),
            email varchar(100),
            status varchar(20) DEFAULT 'pending',
            ref_number varchar(100),
            card_number varchar(32),
            paid_at varchar(100),
            zibal_result int DEFAULT NULL,
            zibal_message text,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY track_id (track_id),
            KEY callback_token (callback_token),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
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
    
    private static function create_callback_page() {
        $page_title = 'نتیجه پرداخت';
        $page_content = '[zibal_donate_callback]';
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
}
