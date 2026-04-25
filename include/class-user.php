<?php
class WPCS_User {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new WPCS_Manager();
        
        add_action('woocommerce_account_dashboard', [$this, 'show_credit_box']);
        add_action('woocommerce_account_credit_endpoint', [$this, 'credit_details_page']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_credit_menu_item']);
        add_action('init', [$this, 'add_credit_endpoint']);
    }
    
    public function add_credit_endpoint() {
        add_rewrite_endpoint('credit', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }
    
    public function add_credit_menu_item($items) {
        $items['credit'] = __('اعتبار نسیه', 'wpcs');
        return $items;
    }
    
    public function show_credit_box() {
        $user_id = get_current_user_id();
        $credit = $this->manager->get_user_credit($user_id);
        
        if ($credit['limit'] == 0) {
            return;
        }
        
        ?>
        <div class="woocommerce-info wpcs-credit-box" style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3 style="margin-top: 0;"><?php _e('اطلاعات اعتبار نسیه', 'wpcs'); ?></h3>
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div>
                    <strong><?php _e('سقف اعتبار:', 'wpcs'); ?></strong><br>
                    <span style="font-size: 20px; color: #28a745;"><?php echo number_format($credit['limit']); ?></span> تومان
                </div>
                <div>
                    <strong><?php _e('اعتبار استفاده شده:', 'wpcs'); ?></strong><br>
                    <span style="font-size: 20px; color: #dc3545;"><?php echo number_format($credit['used']); ?></span> تومان
                </div>
                <div>
                    <strong><?php _e('اعتبار باقیمانده:', 'wpcs'); ?></strong><br>
                    <span style="font-size: 20px; color: #007bff;"><?php echo number_format($credit['remaining']); ?></span> تومان
                </div>
            </div>
            <?php if ($credit['remaining'] > 0): ?>
                <div style="margin-top: 15px;">
                    <div style="background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden;">
                        <div style="width: <?php echo ($credit['used'] / $credit['limit']) * 100; ?>%; background: #28a745; height: 20px;"></div>
                    </div>
                </div>
            <?php endif; ?>
            <p style="margin-top: 15px;">
                <a href="<?php echo wc_get_account_endpoint_url('credit'); ?>" class="button">
                    <?php _e('مشاهده جزئیات و تاریخچه', 'wpcs'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    public function credit_details_page() {
        $user_id = get_current_user_id();
        $credit = $this->manager->get_user_credit($user_id);
        
        global $wpdb;
        $history_table = $wpdb->prefix . 'wpcs_credit_history';
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$history_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ));
        ?>
        <h2><?php _e('جزئیات اعتبار نسیه', 'wpcs'); ?></h2>
        
        <div class="credit-summary" style="display: flex; gap: 20px; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <div style="flex: 1;">
                <strong><?php _e('سقف اعتبار:', 'wpcs'); ?></strong><br>
                <span style="font-size: 24px; color: #28a745;"><?php echo number_format($credit['limit']); ?></span> تومان
            </div>
            <div style="flex: 1;">
                <strong><?php _e('اعتبار استفاده شده:', 'wpcs'); ?></strong><br>
                <span style="font-size: 24px; color: #dc3545;"><?php echo number_format($credit['used']); ?></span> تومان
            </div>
            <div style="flex: 1;">
                <strong><?php _e('اعتبار باقیمانده:', 'wpcs'); ?></strong><br>
                <span style="font-size: 24px; color: #007bff;"><?php echo number_format($credit['remaining']); ?></span> تومان
            </div>
        </div>
        
        <?php if ($credit['used'] > 0): ?>
        <div class="credit-payment" style="margin: 20px 0; padding: 20px; background: #fff3cd; border-radius: 8px;">
            <h3><?php _e('پرداخت بخشی از بدهی', 'wpcs'); ?></h3>
            <form method="post" action="" id="credit-payment-form">
                <?php wp_nonce_field('wpcs_credit_payment', 'wpcs_payment_nonce'); ?>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="payment_amount"><?php _e('مبلغ پرداختی (تومان):', 'wpcs'); ?></label>
                        <input type="number" name="payment_amount" id="payment_amount" 
                               min="1000" max="<?php echo $credit['used']; ?>" 
                               step="1000" style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <button type="submit" name="pay_credit" class="button button-primary">
                            <?php _e('پرداخت', 'wpcs'); ?>
                        </button>
                    </div>
                </div>
                <p class="description"><?php _e('حداقل مبلغ پرداختی: 1,000 تومان', 'wpcs'); ?></p>
            </form>
        </div>
        <?php endif; ?>
        
        <h3><?php _e('تاریخچه تراکنش‌ها', 'wpcs'); ?></h3>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th><?php _e('نوع تراکنش', 'wpcs'); ?></th>
                    <th><?php _e('مبلغ', 'wpcs'); ?></th>
                    <th><?php _e('توضیحات', 'wpcs'); ?></th>
                    <th><?php _e('تاریخ', 'wpcs'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="4"><?php _e('هیچ تراکنشی یافت نشد.', 'wpcs'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $item): ?>
                    <tr>
                        <td data-title="<?php _e('نوع تراکنش', 'wpcs'); ?>">
                            <?php
                            $types = [
                                'order' => __('خرید', 'wpcs'),
                                'payment' => __('پرداخت', 'wpcs'),
                                'update_limit' => __('بروزرسانی سقف', 'wpcs')
                            ];
                            echo isset($types[$item->transaction_type]) ? $types[$item->transaction_type] : esc_html($item->transaction_type);
                            ?>
                        </td>
                        <td data-title="<?php _e('مبلغ', 'wpcs'); ?>"><?php echo number_format($item->amount); ?> تومان</td>
                        <td data-title="<?php _e('توضیحات', 'wpcs'); ?>"><?php echo esc_html($item->description); ?></td>
                        <td data-title="<?php _e('تاریخ', 'wpcs'); ?>"><?php echo esc_html($item->created_at); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        if (isset($_POST['pay_credit']) && isset($_POST['wpcs_payment_nonce']) && wp_verify_nonce($_POST['wpcs_payment_nonce'], 'wpcs_credit_payment')) {
            $amount = intval($_POST['payment_amount']);
            if ($amount > 0 && $amount <= $credit['used']) {
                if ($this->manager->settle_credit($user_id, $amount, sprintf(__('پرداخت توسط کاربر: %s تومان', 'wpcs'), number_format($amount)))) {
                    echo '<div class="woocommerce-message">' . __('پرداخت با موفقیت ثبت شد.', 'wpcs') . '</div>';
                    wp_redirect(wc_get_account_endpoint_url('credit'));
                    exit;
                } else {
                    echo '<div class="woocommerce-error">' . __('خطا در ثبت پرداخت. لطفا مجددا تلاش کنید.', 'wpcs') . '</div>';
                }
            } else {
                echo '<div class="woocommerce-error">' . __('مبلغ وارد شده معتبر نیست.', 'wpcs') . '</div>';
            }
        }
    }
}