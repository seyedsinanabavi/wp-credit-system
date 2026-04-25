<?php
class WPCS_Reports {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_reports_menu']);
    }
    
    public function add_reports_menu() {
        add_submenu_page(
            'wpcs',
            __('گزارشات پیشرفته', 'wpcs'),
            __('گزارشات پیشرفته', 'wpcs'),
            'manage_options',
            'wpcs-advanced-reports',
            [$this, 'advanced_reports_page']
        );
    }
    
    public function advanced_reports_page() {
        global $wpdb;
        $history_table = $wpdb->prefix . 'wpcs_credit_history';
        $user_credit_table = $wpdb->prefix . 'wpcs_user_credit';
        
        // آمار کلی
        $total_credit_limit = $wpdb->get_var("SELECT SUM(credit_limit) FROM {$user_credit_table}");
        $total_credit_used = $wpdb->get_var("SELECT SUM(credit_used) FROM {$user_credit_table}");
        
        // ۱۰ کاربر با بیشترین بدهی
        $top_debtors = $wpdb->get_results("
            SELECT user_id, credit_used, credit_limit 
            FROM {$user_credit_table} 
            WHERE credit_used > 0 
            ORDER BY credit_used DESC 
            LIMIT 10
        ");
        
        // گزارش ماهانه
        $monthly_report = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN transaction_type = 'order' THEN amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as total_payments
            FROM {$history_table}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('گزارشات پیشرفته', 'wpcs'); ?></h1>
            
            <div class="summary-boxes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0;"><?php _e('مجموع سقف اعتبارات', 'wpcs'); ?></h3>
                    <p style="font-size: 28px; margin: 0;"><?php echo number_format($total_credit_limit); ?> تومان</p>
                </div>
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0;"><?php _e('مجموع بدهی جاری', 'wpcs'); ?></h3>
                    <p style="font-size: 28px; margin: 0;"><?php echo number_format($total_credit_used); ?> تومان</p>
                </div>
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0;"><?php _e('درصد استفاده از اعتبار', 'wpcs'); ?></h3>
                    <p style="font-size: 28px; margin: 0;">
                        <?php echo $total_credit_limit > 0 ? round(($total_credit_used / $total_credit_limit) * 100, 2) : 0; ?>%
                    </p>
                </div>
            </div>
            
            <h2><?php _e('۱۰ کاربر با بیشترین بدهی', 'wpcs'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('شناسه کاربر', 'wpcs'); ?></th>
                        <th><?php _e('نام کاربر', 'wpcs'); ?></th>
                        <th><?php _e('بدهی جاری', 'wpcs'); ?></th>
                        <th><?php _e('سقف اعتبار', 'wpcs'); ?></th>
                        <th><?php _e('درصد استفاده', 'wpcs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_debtors as $debtor): 
                        $user = get_userdata($debtor->user_id);
                        $percentage = $debtor->credit_limit > 0 ? ($debtor->credit_used / $debtor->credit_limit) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo $debtor->user_id; ?></td>
                        <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                        <td><?php echo number_format($debtor->credit_used); ?> تومان</td>
                        <td><?php echo number_format($debtor->credit_limit); ?> تومان</td>
                        <td>
                            <div style="width: 100px; background: #e9ecef; border-radius: 5px; overflow: hidden; display: inline-block;">
                                <div style="width: <?php echo min(100, $percentage); ?>%; background: #dc3545; height: 20px;"></div>
                            </div>
                            <?php echo round($percentage, 1); ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php _e('گزارش ماهانه', 'wpcs'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ماه', 'wpcs'); ?></th>
                        <th><?php _e('مجموع فروش نسیه', 'wpcs'); ?></th>
                        <th><?php _e('مجموع تسویه‌ها', 'wpcs'); ?></th>
                        <th><?php _e('بدهی خالص ماه', 'wpcs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_report as $report): ?>
                    <tr>
                        <td><?php echo esc_html($report->month); ?></td>
                        <td><?php echo number_format($report->total_sales); ?> تومان</td>
                        <td><?php echo number_format($report->total_payments); ?> تومان</td>
                        <td><?php echo number_format($report->total_sales - $report->total_payments); ?> تومان</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px;">
                <a href="javascript:void(0)" onclick="window.print()" class="button button-primary">
                    <?php _e('چاپ گزارش', 'wpcs'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}