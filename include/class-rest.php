<?php
class WPCS_REST {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new WPCS_Manager();
        
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('wpcs/v1', '/credit/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_credit'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        register_rest_route('wpcs/v1', '/settle', [
            'methods' => 'POST',
            'callback' => [$this, 'settle_credit'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        register_rest_route('wpcs/v1', '/history/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_history'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }
    
    public function check_permission($request) {
        $user_id = $request->get_param('user_id');
        $current_user_id = get_current_user_id();
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if ($user_id && $current_user_id == $user_id) {
            return true;
        }
        
        return false;
    }
    
    public function get_user_credit($request) {
        $user_id = $request->get_param('user_id');
        $credit = $this->manager->get_user_credit($user_id);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $credit
        ]);
    }
    
    public function settle_credit($request) {
        $params = $request->get_json_params();
        $user_id = get_current_user_id();
        $amount = isset($params['amount']) ? intval($params['amount']) : 0;
        
        if ($amount <= 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('مبلغ نامعتبر است', 'wpcs')
            ]);
        }
        
        $credit = $this->manager->get_user_credit($user_id);
        
        if ($amount > $credit['used']) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('مبلغ بیشتر از بدهی جاری است', 'wpcs')
            ]);
        }
        
        $result = $this->manager->settle_credit($user_id, $amount, 
            sprintf(__('پرداخت از طریق API: %s تومان', 'wpcs'), number_format($amount)));
        
        return rest_ensure_response([
            'success' => $result,
            'message' => $result ? __('پرداخت با موفقیت ثبت شد', 'wpcs') : __('خطا در ثبت پرداخت', 'wpcs')
        ]);
    }
    
    public function get_user_history($request) {
        global $wpdb;
        $user_id = $request->get_param('user_id');
        $history_table = $wpdb->prefix . 'wpcs_credit_history';
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$history_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ));
        
        return rest_ensure_response([
            'success' => true,
            'data' => $history
        ]);
    }
}