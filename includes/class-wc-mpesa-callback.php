<?php
class WC_MPesa_Callback {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('wc-mpesa/v1', '/callback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_callback'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('wc-mpesa/v1', '/confirmation', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_confirmation'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('wc-mpesa/v1', '/validation', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_validation'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function handle_callback($request) {
        $data = $request->get_body();
        $callback_data = json_decode($data, true);
        
        WC_MPesa_Logger::log('Callback Received: ' . $data);
        
        // Send immediate response to Safaricom
        $response = [
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ];
        
        // Process callback asynchronously
        $this->process_callback($callback_data);
        
        return rest_ensure_response($response);
    }
    
    public function process_callback($data) {
        try {
            $stk_callback = $data['Body']['stkCallback'] ?? null;
            
            if (!$stk_callback) {
                throw new Exception('Invalid callback data structure');
            }
            
            $merchant_request_id = $stk_callback['MerchantRequestID'];
            $checkout_request_id = $stk_callback['CheckoutRequestID'];
            $result_code = $stk_callback['ResultCode'];
            $result_desc = $stk_callback['ResultDesc'];
            
            // Get transaction from database
            $transaction = WC_MPesa_Database::get_transaction_by_checkout_id($checkout_request_id);
            
            if (!$transaction) {
                throw new Exception('Transaction not found for checkout ID: ' . $checkout_request_id);
            }
            
            $order = wc_get_order($transaction->order_id);
            
            if (!$order) {
                throw new Exception('Order not found: ' . $transaction->order_id);
            }
            
            // Update transaction status
            $update_data = [
                'result_code' => $result_code,
                'result_description' => $result_desc,
                'status' => ($result_code === 0) ? 'completed' : 'failed',
                'updated_at' => current_time('mysql')
            ];
            
            if ($result_code === 0) {
                $callback_metadata = $stk_callback['CallbackMetadata']['Item'] ?? [];
                $metadata = $this->parse_metadata($callback_metadata);
                
                $update_data['transaction_id'] = $metadata['MpesaReceiptNumber'] ?? '';
                $update_data['mpesa_receipt_number'] = $metadata['MpesaReceiptNumber'] ?? '';
                $update_data['transaction_date'] = $metadata['TransactionDate'] ?? current_time('mysql');
            }
            
            WC_MPesa_Database::update_transaction($checkout_request_id, $update_data);
            
            // Update order status
            if ($result_code === 0) {
                // Mark order paid in Woo
                $order->payment_complete($update_data['mpesa_receipt_number']);

                $order->add_order_note(sprintf(
                    __('M-PESA payment completed. Receipt: %s', 'wc-mpesa-gateway'),
                    $update_data['mpesa_receipt_number']
                ));
            } else {
                $order->update_status('failed', sprintf(
                    __('M-PESA payment failed: %s', 'wc-mpesa-gateway'),
                    $result_desc
                ));
            }
            
        } catch (Exception $e) {
            WC_MPesa_Logger::log('Callback Processing Error: ' . $e->getMessage(), 'error');
        }
    }
    
    private function parse_metadata($items) {
        $metadata = [];
        foreach ($items as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }
        return $metadata;
    }
    
    public function handle_confirmation($request) {
        WC_MPesa_Logger::log('Confirmation Received: ' . $request->get_body());
        return rest_ensure_response(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
    
    public function handle_validation($request) {
        WC_MPesa_Logger::log('Validation Received: ' . $request->get_body());
        return rest_ensure_response(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}