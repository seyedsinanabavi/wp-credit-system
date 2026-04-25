<?php
/**
 * Plugin Name: WP Credit System Pro
 * Description: سیستم کامل خرید نسیه با سطوح کاربری، سقف اعتبار، پرداخت بخشی، گزارش مالی، محدودیت رول‌ها و ارسال پیامک
 * Version: 3.0.0
 * Author: SeyedSinaNabavi
 * Text Domain: wpcs
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// تعریف ثابت‌ها
define('WPCS_VERSION', '3.0.0');
define('WPCS_PATH', plugin_dir_path(__FILE__));
define('WPCS_URL', plugin_dir_url(__FILE__));
define('WPCS_BASENAME', plugin_basename(__FILE__));

// بررسی نصب ووکامرس
function wpcs_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('WP Credit System requires WooCommerce to be installed and activated.', 'wpcs') . '</p></div>';
        });
        return false;
    }
    return true;
}

// بارگذاری فایل‌ها
add_action('plugins_loaded', 'wpcs_load_files');
function wpcs_load_files() {
    if (!wpcs_check_woocommerce()) {
        return;
    }
    
    require_once WPCS_PATH . 'includes/class-database.php';
    require_once WPCS_PATH . 'includes/class-manager.php';
    require_once WPCS_PATH . 'includes/class-gateway.php';
    require_once WPCS_PATH . 'includes/class-admin.php';
    require_once WPCS_PATH . 'includes/class-user.php';
    require_once WPCS_PATH . 'includes/class-rest.php';
    require_once WPCS_PATH . 'includes/class-reports.php';
    require_once WPCS_PATH . 'includes/class-sms.php';
    
    // مقداردهی اولیه کلاس‌ها
    new WPCS_Manager();
    new WPCS_Admin();
    new WPCS_User();
    new WPCS_REST();
    new WPCS_Reports();
    new WPCS_SMS();
}

// فعالسازی افزونه
register_activation_hook(__FILE__, 'wpcs_activate');
function wpcs_activate() {
    require_once WPCS_PATH . 'includes/class-database.php';
    WPCS_Database::install();
    
    // تنظیمات پیش‌فرض
    add_option('wpcs_level1', 1000000);
    add_option('wpcs_level2', 5000000);
    add_option('wpcs_level3', 10000000);
    add_option('wpcs_enable_sms', 'no');
}

// افزودن درگاه پرداخت به ووکامرس
add_filter('woocommerce_payment_gateways', 'wpcs_add_gateway');
function wpcs_add_gateway($gateways) {
    if (class_exists('WPCS_Gateway')) {
        $gateways[] = 'WPCS_Gateway';
    }
    return $gateways;
}

// بارگذاری استایل
add_action('wp_enqueue_scripts', 'wpcs_enqueue_assets');
function wpcs_enqueue_assets() {
    if (is_account_page()) {
        wp_enqueue_style('wpcs-style', WPCS_URL . 'assets/style.css', [], WPCS_VERSION);
    }
}

// بارگذاری استایل ادمین
add_action('admin_enqueue_scripts', 'wpcs_admin_assets');
function wpcs_admin_assets($hook) {
    if (strpos($hook, 'wpcs') !== false) {
        wp_enqueue_style('wpcs-admin-style', WPCS_URL . 'assets/style.css', [], WPCS_VERSION);
    }
}