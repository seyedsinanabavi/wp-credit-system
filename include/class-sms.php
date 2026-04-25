<?php
class WPCS_SMS {
    
    private $api_url;
    private $api_key;
    private $sender;
    private $enabled;
    
    public function __construct() {
        $this->api_url = get_option('wpcs_sms_url', '');
        $this->api_key = get_option('wpcs_sms_key', '');
        $this->sender = get_option('wpcs_sms_sender', '');
        $this->enabled = get_option('wpcs_enable_sms', 'no') === 'yes';
    }
    
    public function send_sms($phone, $message) {
        if (!$this->enabled) {
            return false;
        }
        
        if (empty($this->api_url) || empty($this->api_key) || empty($this->sender)) {
            return false;
        }
        
        if (empty($phone)) {
            return false;
        }
        
        $phone = $this->normalize_phone($phone);
        
        $response = wp_remote_post($this->api_url, [
            'timeout' => 30,
            'body' => [
                'apikey' => $this->api_key,
                'sender' => $this->sender,
                'mobile' => $phone,
                'message' => $message
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('WPCS SMS Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        return true;
    }
    
    private function normalize_phone($phone) {
        // حذف فاصله و کاراکترهای غیر عددی
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // تبدیل ۰۹xx به 989xx
        if (substr($phone, 0, 2) === '09') {
            $phone = '98' . substr($phone, 1);
        }
        
        // حذف صفر ابتدایی
        if (substr($phone, 0, 1) === '0') {
            $phone = '98' . substr($phone, 1);
        }
        
        // اضافه کردن 98 در ابتدا در صورت نبودن
        if (substr($phone, 0, 2) !== '98') {
            $phone = '98' . $phone;
        }
        
        return $phone;
    }
    
    public function send_credit_alert($user_id, $amount, $type) {
        $user = get_userdata($user_id);
        $phone = get_user_meta($user_id, 'billing_phone', true);
        
        if (empty($phone)) {
            return false;
        }
        
        if ($type === 'usage') {
            $message = sprintf(
                __('کاربر %s عزیز، مبلغ %s تومان از اعتبار نسیه شما کسر شد.', 'wpcs'),
                $user->display_name,
                number_format($amount)
            );
        } else {
            $message = sprintf(
                __('کاربر %s عزیز، پرداخت شما به مبلغ %s تومان با موفقیت ثبت شد.', 'wpcs'),
                $user->display_name,
                number_format($amount)
            );
        }
        
        return $this->send_sms($phone, $message);
    }
}