<?php
class WPCS_Manager {
    
    private $table;
    private $history_table;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'wpcs_user_credit';
        $this->history_table = $wpdb->prefix . 'wpcs_credit_history';
        
        add_action('woocommerce_checkout_process', [$this, 'check_credit_limit']);
        add_action('woocommerce_order_status_processing', [$this, 'apply_credit_from_order']);
        add_action('woocommerce_order_status_completed', [$this, 'apply_credit_from_order']);
    }
    
    public function get_user_record($user_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND status = 'active'",
                $user_id
            ),
            ARRAY_A
        );
    }
    
    public function get_user_credit($user_id) {
        $record = $this->get_user_record($user_id);
        if (!$record) {
            return [
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'level' => null
            ];
        }
        
        return [
            'limit' => (int)$record['credit_limit'],
            'used' => (int)$record['credit_used'],
            'remaining' => (int)$record['credit_limit'] - (int)$record['credit_used'],
            'level' => $record['credit_level']
        ];
    }
    
    public function check_credit_limit() {
        if (!isset($_POST['payment_method']) || sanitize_text_field($_POST['payment_method']) !== 'wpcs_credit_gateway') {
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wc_add_notice(__('برای خرید نسیه باید وارد حساب کاربری خود شوید.', 'wpcs'), 'error');
            return;
        }
        
        $record = $this->get_user_record($user_id);
        
        if (!$record) {
            wc_add_notice(__('اعتبار نسیه برای شما تعریف نشده است. با مدیر سایت تماس بگیرید.', 'wpcs'), 'error');
            return;
        }
        
        // بررسی رول‌های مجاز
        $allowed_roles = json_decode($record['allowed_roles'], true);
        if (!empty($allowed_roles) && is_array($allowed_roles)) {
            $user = wp_get_current_user();
            $has_role = !empty(array_intersect($allowed_roles, (array)$user->roles));
            
            if (!$has_role) {
                wc_add_notice(__('نقش کاربری شما مجاز به استفاده از خرید نسیه نیست.', 'wpcs'), 'error');
                return;
            }
        }
        
        $cart_total = (float)WC()->cart->get_total('edit');
        $credit_used = (float)$record['credit_used'];
        $credit_limit = (float)$record['credit_limit'];
        
        if (($credit_used + $cart_total) > $credit_limit) {
            wc_add_notice(
                sprintf(
                    __('سقف اعتبار شما کافی نیست. سقف اعتبار: %s تومان - اعتبار استفاده شده: %s تومان', 'wpcs'),
                    number_format($credit_limit),
                    number_format($credit_used)
                ),
                'error'
            );
        }
    }
    
    public function apply_credit_from_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== 'wpcs_credit_gateway') {
            return;
        }
        
        // جلوگیری از اجرای مجدد
        if ($order->get_meta('_wpcs_credit_applied') === 'yes') {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $amount = (float)$order->get_total();
        $before_balance = $this->get_user_credit($user_id)['used'];
        
        $result = $this->add_credit_usage($user_id, $amount, 'order', $order_id, 'خرید نسیه - سفارش #' . $order_id);
        
        if ($result) {
            $order->update_meta_data('_wpcs_credit_applied', 'yes');
            $order->save();
            
            // ارسال پیامک در صورت فعال بودن
            $enable_sms = get_option('wpcs_enable_sms', 'no');
            if ($enable_sms === 'yes') {
                $sms = new WPCS_SMS();
                $phone = $order->get_billing_phone();
                $message = sprintf(
                    __('سفارش نسیه شما با موفقیت ثبت شد. مبلغ: %s تومان', 'wpcs'),
                    number_format($amount)
                );
                $sms->send_sms($phone, $message);
            }
        }
    }
    
    public function add_credit_usage($user_id, $amount, $type, $order_id = null, $description = '') {
        if ($amount <= 0) {
            return false;
        }
        
        $record = $this->get_user_record($user_id);
        if (!$record) {
            return false;
        }
        
        $new_used = (int)$record['credit_used'] + (int)$amount;
        $limit = (int)$record['credit_limit'];
        
        if ($new_used > $limit) {
            return false;
        }
        
        $this->wpdb->update(
            $this->table,
            ['credit_used' => $new_used],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        );
        
        $this->add_history($user_id, $type, $amount, $record['credit_used'], $new_used, $order_id, $description);
        
        return true;
    }
    
    public function settle_credit($user_id, $amount, $description = '') {
        if ($amount <= 0) {
            return false;
        }
        
        $record = $this->get_user_record($user_id);
        if (!$record) {
            return false;
        }
        
        $current_used = (int)$record['credit_used'];
        $new_used = max(0, $current_used - (int)$amount);
        
        $result = $this->wpdb->update(
            $this->table,
            ['credit_used' => $new_used],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        );
        
        if ($result !== false) {
            $desc = $description ?: sprintf(__('پرداخت بخشی از بدهی: %s تومان', 'wpcs'), number_format($amount));
            $this->add_history($user_id, 'payment', $amount, $current_used, $new_used, null, $desc);
            return true;
        }
        
        return false;
    }
    
    public function update_credit_limit($user_id, $limit, $level = null, $allowed_roles = null) {
        $record = $this->get_user_record($user_id);
        
        $data = [
            'credit_limit' => (int)$limit,
            'updated_at' => current_time('mysql')
        ];
        
        if ($level !== null) {
            $data['credit_level'] = $level;
        }
        
        if ($allowed_roles !== null) {
            $data['allowed_roles'] = is_array($allowed_roles) ? json_encode($allowed_roles) : $allowed_roles;
        }
        
        if ($record) {
            $result = $this->wpdb->update(
                $this->table,
                $data,
                ['user_id' => $user_id],
                array_fill(0, count($data), '%s'),
                ['%d']
            );
        } else {
            $data['user_id'] = $user_id;
            $data['created_at'] = current_time('mysql');
            $result = $this->wpdb->insert($this->table, $data);
        }
        
        if ($result && $limit > 0) {
            $this->add_history($user_id, 'update_limit', $limit, null, null, null, 
                sprintf(__('سقف اعتبار به %s تومان تغییر یافت', 'wpcs'), number_format($limit)));
        }
        
        return $result !== false;
    }
    
    private function add_history($user_id, $type, $amount, $before = null, $after = null, $order_id = null, $description = '') {
        $this->wpdb->insert(
            $this->history_table,
            [
                'user_id' => $user_id,
                'transaction_type' => $type,
                'amount' => (int)$amount,
                'before_balance' => $before ? (int)$before : 0,
                'after_balance' => $after ? (int)$after : 0,
                'order_id' => $order_id,
                'description' => $description,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );
    }
}