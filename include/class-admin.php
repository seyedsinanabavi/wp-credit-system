<?php
class WPCS_Admin {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new WPCS_Manager();
        
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'save_credit_settings']);
        add_action('admin_init', [$this, 'save_sms_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        
        // اضافه کردن ستون اعتبار به صفحه کاربران
        add_filter('manage_users_columns', [$this, 'add_user_credit_column']);
        add_filter('manage_users_custom_column', [$this, 'show_user_credit_column'], 10, 3);
        
        // افزودن متاباکس به صفحه ویرایش کاربر
        add_action('show_user_profile', [$this, 'user_credit_edit_fields']);
        add_action('edit_user_profile', [$this, 'user_credit_edit_fields']);
        add_action('personal_options_update', [$this, 'save_user_credit_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_credit_fields']);
    }
    
    public function add_admin_menus() {
        add_menu_page(
            __('سیستم نسیه', 'wpcs'),
            __('سیستم نسیه', 'wpcs'),
            'manage_options',
            'wpcs',
            [$this, 'main_admin_page'],
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'wpcs',
            __('مدیریت کاربران', 'wpcs'),
            __('مدیریت کاربران', 'wpcs'),
            'manage_options',
            'wpcs-users',
            [$this, 'users_management_page']
        );
        
        add_submenu_page(
            'wpcs',
            __('گزارشات', 'wpcs'),
            __('گزارشات', 'wpcs'),
            'manage_options',
            'wpcs-reports',
            [$this, 'reports_page']
        );
        
        add_submenu_page(
            'wpcs',
            __('تنظیمات پیامک', 'wpcs'),
            __('تنظیمات پیامک', 'wpcs'),
            'manage_options',
            'wpcs-sms',
            [$this, 'sms_settings_page']
        );
    }
    
    public function main_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات سیستم نسیه', 'wpcs'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpcs_save_settings', 'wpcs_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="level1"><?php _e('سطح ۱ - سقف اعتبار', 'wpcs'); ?></label></th>
                        <td>
                            <input type="number" name="level1" id="level1" value="<?php echo esc_attr(get_option('wpcs_level1', 1000000)); ?>" class="regular-text">
                            <p class="description"><?php _e('تومان - سقف اعتبار برای کاربران سطح ۱', 'wpcs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="level2"><?php _e('سطح ۲ - سقف اعتبار', 'wpcs'); ?></label></th>
                        <td>
                            <input type="number" name="level2" id="level2" value="<?php echo esc_attr(get_option('wpcs_level2', 5000000)); ?>" class="regular-text">
                            <p class="description"><?php _e('تومان - سقف اعتبار برای کاربران سطح ۲', 'wpcs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="level3"><?php _e('سطح ۳ - سقف اعتبار', 'wpcs'); ?></label></th>
                        <td>
                            <input type="number" name="level3" id="level3" value="<?php echo esc_attr(get_option('wpcs_level3', 10000000)); ?>" class="regular-text">
                            <p class="description"><?php _e('تومان - سقف اعتبار برای کاربران سطح ۳', 'wpcs'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('ذخیره تنظیمات', 'wpcs'), 'primary', 'save_credit_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function users_management_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('مدیریت اعتبار کاربران', 'wpcs'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpcs_save_user_credit', 'wpcs_user_nonce'); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('شناسه', 'wpcs'); ?></th>
                            <th><?php _e('نام کاربری', 'wpcs'); ?></th>
                            <th><?php _e('سقف اعتبار', 'wpcs'); ?></th>
                            <th><?php _e('اعتبار استفاده شده', 'wpcs'); ?></th>
                            <th><?php _e('اعتبار باقیمانده', 'wpcs'); ?></th>
                            <th><?php _e('سطح', 'wpcs'); ?></th>
                            <th><?php _e('عملیات', 'wpcs'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = get_users(['number' => 50]);
                        foreach ($users as $user) :
                            $credit = $this->manager->get_user_credit($user->ID);
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td>
                                <input type="number" name="credit_limit[<?php echo $user->ID; ?>]" 
                                       value="<?php echo esc_attr($credit['limit']); ?>" style="width: 120px;">
                            </td>
                            <td><?php echo number_format($credit['used']); ?></td>
                            <td><?php echo number_format($credit['remaining']); ?></td>
                            <td>
                                <select name="credit_level[<?php echo $user->ID; ?>]">
                                    <option value=""><?php _e('بدون سطح', 'wpcs'); ?></option>
                                    <option value="level1" <?php selected($credit['level'], 'level1'); ?>><?php _e('سطح ۱', 'wpcs'); ?></option>
                                    <option value="level2" <?php selected($credit['level'], 'level2'); ?>><?php _e('سطح ۲', 'wpcs'); ?></option>
                                    <option value="level3" <?php selected($credit['level'], 'level3'); ?>><?php _e('سطح ۳', 'wpcs'); ?></option>
                                </select>
                            </td>
                            <td>
                                <button type="submit" name="update_user[<?php echo $user->ID; ?>]" class="button button-small">
                                    <?php _e('بروزرسانی', 'wpcs'); ?>
                                </button>
                                <button type="submit" name="settle_credit[<?php echo $user->ID; ?>]" value="0" class="button button-small">
                                    <?php _e('تسویه کل', 'wpcs'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }
    
    public function save_credit_settings() {
        if (isset($_POST['save_credit_settings']) && isset($_POST['wpcs_nonce']) && wp_verify_nonce($_POST['wpcs_nonce'], 'wpcs_save_settings')) {
            update_option('wpcs_level1', intval($_POST['level1']));
            update_option('wpcs_level2', intval($_POST['level2']));
            update_option('wpcs_level3', intval($_POST['level3']));
            
            echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'wpcs') . '</p></div>';
        }
        
        if (isset($_POST['update_user']) && isset($_POST['wpcs_user_nonce']) && wp_verify_nonce($_POST['wpcs_user_nonce'], 'wpcs_save_user_credit')) {
            foreach ($_POST['update_user'] as $user_id => $value) {
                $limit = isset($_POST['credit_limit'][$user_id]) ? intval($_POST['credit_limit'][$user_id]) : 0;
                $level = isset($_POST['credit_level'][$user_id]) ? sanitize_text_field($_POST['credit_level'][$user_id]) : null;
                
                if ($limit > 0) {
                    $this->manager->update_credit_limit($user_id, $limit, $level);
                }
            }
            echo '<div class="notice notice-success"><p>' . __('اطلاعات کاربران بروزرسانی شد.', 'wpcs') . '</p></div>';
        }
        
        if (isset($_POST['settle_credit']) && isset($_POST['wpcs_user_nonce']) && wp_verify_nonce($_POST['wpcs_user_nonce'], 'wpcs_save_user_credit')) {
            foreach ($_POST['settle_credit'] as $user_id => $amount) {
                $credit = $this->manager->get_user_credit($user_id);
                if ($credit['used'] > 0) {
                    $this->manager->settle_credit($user_id, $credit['used'], __('تسویه کامل توسط ادمین', 'wpcs'));
                }
            }
            echo '<div class="notice notice-success"><p>' . __('تسویه حساب کاربران انجام شد.', 'wpcs') . '</p></div>';
        }
    }
    
    public function save_sms_settings() {
        if (isset($_POST['save_sms_settings']) && isset($_POST['wpcs_sms_nonce']) && wp_verify_nonce($_POST['wpcs_sms_nonce'], 'wpcs_save_sms')) {
            update_option('wpcs_enable_sms', sanitize_text_field($_POST['enable_sms']));
            update_option('wpcs_sms_url', esc_url_raw($_POST['sms_url']));
            update_option('wpcs_sms_key', sanitize_text_field($_POST['sms_key']));
            update_option('wpcs_sms_sender', sanitize_text_field($_POST['sms_sender']));
            
            echo '<div class="notice notice-success"><p>' . __('تنظیمات پیامک ذخیره شد.', 'wpcs') . '</p></div>';
        }
    }
    
    public function sms_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات پیامک', 'wpcs'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpcs_save_sms', 'wpcs_sms_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('فعالسازی پیامک', 'wpcs'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_sms" value="yes" <?php checked(get_option('wpcs_enable_sms', 'no'), 'yes'); ?>>
                                <?php _e('ارسال پیامک برای کاربران', 'wpcs'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_url"><?php _e('آدرس API پیامک', 'wpcs'); ?></label></th>
                        <td>
                            <input type="url" name="sms_url" id="sms_url" value="<?php echo esc_attr(get_option('wpcs_sms_url', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_key"><?php _e('کلید API', 'wpcs'); ?></label></th>
                        <td>
                            <input type="text" name="sms_key" id="sms_key" value="<?php echo esc_attr(get_option('wpcs_sms_key', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_sender"><?php _e('شماره فرستنده', 'wpcs'); ?></label></th>
                        <td>
                            <input type="text" name="sms_sender" id="sms_sender" value="<?php echo esc_attr(get_option('wpcs_sms_sender', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('ذخیره تنظیمات پیامک', 'wpcs'), 'primary', 'save_sms_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function reports_page() {
        global $wpdb;
        $history_table = $wpdb->prefix . 'wpcs_credit_history';
        
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$history_table}");
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$history_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('گزارشات مالی', 'wpcs'); ?></h1>
            
            <div class="summary-boxes" style="display: flex; gap: 20px; margin-bottom: 20px;">
                <?php
                $total_debt = $wpdb->get_var("SELECT SUM(amount) FROM {$history_table} WHERE transaction_type = 'order'");
                $total_payment = $wpdb->get_var("SELECT SUM(amount) FROM {$history_table} WHERE transaction_type = 'payment'");
                $current_debt = $total_debt - $total_payment;
                ?>
                <div class="summary-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px; flex: 1;">
                    <strong><?php _e('مجموع فروش نسیه:', 'wpcs'); ?></strong> <?php echo number_format($total_debt); ?> تومان
                </div>
                <div class="summary-box" style="background: #d4edda; padding: 15px; border-radius: 5px; flex: 1;">
                    <strong><?php _e('مبلغ تسویه شده:', 'wpcs'); ?></strong> <?php echo number_format($total_payment); ?> تومان
                </div>
                <div class="summary-box" style="background: #f8d7da; padding: 15px; border-radius: 5px; flex: 1;">
                    <strong><?php _e('بدهی جاری:', 'wpcs'); ?></strong> <?php echo number_format($current_debt); ?> تومان
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('شناسه کاربر', 'wpcs'); ?></th>
                        <th><?php _e('نوع تراکنش', 'wpcs'); ?></th>
                        <th><?php _e('مبلغ', 'wpcs'); ?></th>
                        <th><?php _e('مانده قبل', 'wpcs'); ?></th>
                        <th><?php _e('مانده بعد', 'wpcs'); ?></th>
                        <th><?php _e('توضیحات', 'wpcs'); ?></th>
                        <th><?php _e('تاریخ', 'wpcs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->user_id); ?></td>
                        <td>
                            <?php
                            $types = [
                                'order' => __('خرید', 'wpcs'),
                                'payment' => __('پرداخت', 'wpcs'),
                                'update_limit' => __('بروزرسانی سقف', 'wpcs')
                            ];
                            echo isset($types[$row->transaction_type]) ? $types[$row->transaction_type] : esc_html($row->transaction_type);
                            ?>
                        </td>
                        <td><?php echo number_format($row->amount); ?></td>
                        <td><?php echo number_format($row->before_balance); ?></td>
                        <td><?php echo number_format($row->after_balance); ?></td>
                        <td><?php echo esc_html($row->description); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
            if ($total > $per_page) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => ceil($total / $per_page),
                    'current' => $current_page
                ]);
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }
    
    public function add_user_credit_column($columns) {
        $columns['wpcs_credit'] = __('اعتبار نسیه', 'wpcs');
        return $columns;
    }
    
    public function show_user_credit_column($value, $column_name, $user_id) {
        if ($column_name === 'wpcs_credit') {
            $credit = $this->manager->get_user_credit($user_id);
            $used_percent = $credit['limit'] > 0 ? ($credit['used'] / $credit['limit']) * 100 : 0;
            return sprintf(
                '<div style="width:100%%; background:#f1f1f1;"><div style="width:%s%%; background:#4CAF50; color:white; padding:2px 0;">%s / %s</div></div>',
                $used_percent,
                number_format($credit['used']),
                number_format($credit['limit'])
            );
        }
        return $value;
    }
    
    public function user_credit_edit_fields($user) {
        $credit = $this->manager->get_user_credit($user->ID);
        ?>
        <h3><?php _e('اطلاعات اعتبار نسیه', 'wpcs'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="wpcs_credit_limit"><?php _e('سقف اعتبار', 'wpcs'); ?></label></th>
                <td>
                    <input type="number" name="wpcs_credit_limit" id="wpcs_credit_limit" 
                           value="<?php echo esc_attr($credit['limit']); ?>" class="regular-text">
                    <p class="description"><?php _e('تومان - سقف اعتبار کاربر', 'wpcs'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('اعتبار استفاده شده', 'wpcs'); ?></th>
                <td>
                    <?php echo number_format($credit['used']); ?> تومان
                </td>
            </tr>
            <tr>
                <th><?php _e('اعتبار باقیمانده', 'wpcs'); ?></th>
                <td>
                    <?php echo number_format($credit['remaining']); ?> تومان
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_user_credit_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['wpcs_credit_limit'])) {
            $limit = intval($_POST['wpcs_credit_limit']);
            if ($limit > 0) {
                $this->manager->update_credit_limit($user_id, $limit);
            }
        }
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'wpcs') !== false) {
            wp_add_inline_style('wp-admin', '
                .wp-list-table td { vertical-align: middle; }
                .summary-boxes { margin: 20px 0; }
                .summary-box { padding: 15px; border-radius: 8px; }
            ');
        }
    }
}