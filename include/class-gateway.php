<?php
class WPCS_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'wpcs_credit_gateway';
        $this->icon = apply_filters('woocommerce_wpcs_credit_gateway_icon', '');
        $this->has_fields = false;
        $this->method_title = __('پرداخت نسیه', 'wpcs');
        $this->method_description = __('خرید با استفاده از اعتبار نسیه تعیین شده برای کاربر', 'wpcs');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', __('پرداخت نسیه', 'wpcs'));
        $this->description = $this->get_option('description', __('مبلغ خرید از اعتبار نسیه شما کسر خواهد شد.', 'wpcs'));
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
    }
    
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('فعال/غیرفعال', 'wpcs'),
                'type' => 'checkbox',
                'label' => __('فعال کردن درگاه پرداخت نسیه', 'wpcs'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('عنوان', 'wpcs'),
                'type' => 'text',
                'description' => __('عنوان درگاه پرداخت در صفحه تسویه حساب', 'wpcs'),
                'default' => __('پرداخت نسیه', 'wpcs'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('توضیحات', 'wpcs'),
                'type' => 'textarea',
                'description' => __('توضیحات درگاه پرداخت در صفحه تسویه حساب', 'wpcs'),
                'default' => __('مبلغ خرید از اعتبار نسیه شما کسر خواهد شد.', 'wpcs')
            ]
        ];
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        // بررسی مجدد اعتبار قبل از پرداخت نهایی
        $manager = new WPCS_Manager();
        $credit = $manager->get_user_credit($user_id);
        
        if ($credit['remaining'] < $order->get_total()) {
            wc_add_notice(__('اعتبار شما کافی نیست.', 'wpcs'), 'error');
            return;
        }
        
        // کاهش موجودی و ثبت تراکنش
        $order->update_status('processing', __('پرداخت نسیه - در انتظار تایید', 'wpcs'));
        $order->payment_complete();
        
        WC()->cart->empty_cart();
        
        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }
    
    public function receipt_page($order_id) {
        echo '<p>' . __('پرداخت شما با موفقیت ثبت شد.', 'wpcs') . '</p>';
    }
}