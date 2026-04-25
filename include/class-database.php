<?php
class WPCS_Database {
    
    public static function install() {
        global $wpdb;
        
        $table_user_credit = $wpdb->prefix . 'wpcs_user_credit';
        $table_history = $wpdb->prefix . 'wpcs_credit_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_user_credit} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            credit_limit bigint(20) NOT NULL DEFAULT 0,
            credit_used bigint(20) NOT NULL DEFAULT 0,
            credit_level varchar(50) DEFAULT NULL,
            allowed_roles text DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id_unique (user_id),
            KEY user_id (user_id)
        ) {$charset_collate};
        
        CREATE TABLE IF NOT EXISTS {$table_history} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            transaction_type varchar(50) NOT NULL,
            amount bigint(20) NOT NULL,
            before_balance bigint(20) DEFAULT 0,
            after_balance bigint(20) DEFAULT 0,
            order_id bigint(20) DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // ایجاد ایندکس‌های اضافی
        $wpdb->query("ALTER TABLE {$table_user_credit} ADD INDEX idx_user_status (user_id, status)");
        $wpdb->query("ALTER TABLE {$table_history} ADD INDEX idx_user_transaction (user_id, transaction_type)");
    }
    
    public static function uninstall() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'wpcs_user_credit',
            $wpdb->prefix . 'wpcs_credit_history'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option('wpcs_level1');
        delete_option('wpcs_level2');
        delete_option('wpcs_level3');
        delete_option('wpcs_enable_sms');
        delete_option('wpcs_sms_url');
        delete_option('wpcs_sms_key');
        delete_option('wpcs_sms_sender');
    }
}