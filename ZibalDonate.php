<?php
/*
Plugin Name: Zibal Donate - حمایت مالی امن
Plugin URI: https://zibal.ir/
Description: افزونه حمایت مالی امن زیبال
Version: 2.0
Author: Abolfazl Abdollahi
Author URI: https://github.com/abolfazlabdollahii
Text Domain: zibal-donate
*/

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit('Access denied!');
}

// تعریف ثابت‌ها
define('ZIBAL_DONATE_VERSION', '2.0');
define('ZIBAL_DONATE_DIR', plugin_dir_path(__FILE__));
define('ZIBAL_DONATE_URL', plugin_dir_url(__FILE__));
define('ZIBAL_DONATE_TABLE', 'zibal_donate_transactions');

// بارگذاری کلاس اصلی
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-donate.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-api.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-admin.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-shortcode.php';

// راه‌اندازی پلاگین
function zibal_donate_init() {
    new ZibalDonate();
}
add_action('plugins_loaded', 'zibal_donate_init');

// فعال‌سازی پلاگین
register_activation_hook(__FILE__, array('ZibalDonate', 'activate'));

// غیرفعال‌سازی پلاگین
register_deactivation_hook(__FILE__, array('ZibalDonate', 'deactivate'));