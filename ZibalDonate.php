<?php
/*
Plugin Name: Zibal Donate - حمایت مالی امن
Plugin URI: https://zibal.ir/
Description: افزونه حمایت مالی امن زیبال
Version: 2.1
Requires PHP: 5.6
Author: Zibal
Author URI: https://zibal.ir/
Text Domain: zibal-donate
*/

if (!defined('ABSPATH')) {
    exit('Access denied!');
}

define('ZIBAL_DONATE_VERSION', '2.1');
define('ZIBAL_DONATE_DIR', plugin_dir_path(__FILE__));
define('ZIBAL_DONATE_URL', plugin_dir_url(__FILE__));
define('ZIBAL_DONATE_TABLE', 'zibal_donate_transactions');

require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-donate.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-api.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-admin.php';
require_once ZIBAL_DONATE_DIR . 'includes/class-zibal-shortcode.php';

function zibal_donate_init() {
    new ZibalDonate();
}
add_action('plugins_loaded', 'zibal_donate_init');

register_activation_hook(__FILE__, array('ZibalDonate', 'activate'));
register_deactivation_hook(__FILE__, array('ZibalDonate', 'deactivate'));
