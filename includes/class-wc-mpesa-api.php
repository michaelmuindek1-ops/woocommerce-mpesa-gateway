<?php
class WC_MPesa_API {
    
    private $environment;
    private $consumer_key;
    private $consumer_secret;
    private $passkey;
    private $shortcode;
    private $access_token;
    private $token_expiry;
    
    private $api_urls = [
        'sandbox' => [
            'auth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'query' => 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        ],
        'production' => [
            'auth' => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'query' => 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        ]
    ];
    
    public function __construct() {
        $settings = get_option('wc_mpesa_settings', []);
        $this->environment = $settings['environment'] ?? 'sandbox';
        $this->consumer_key = $settings['consumer_key'] ?? '';
        $this->consumer_secret = $settings['consumer_secret'] ?? '';
        $this->passkey = $settings['passkey'] ?? '';
        $this->shortcode = $settings['shortcode'] ?? '';
    }
    
    private function get_api_url($endpoint) {
        return $this->api_urls[$this->environment][$endpoint];
    }
    
    private function authenticate() {
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $response = wp_remote_get(
            $this->get_api_url('auth'),
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials
                ],
                'timeout' => 30
            ]
        );
        
        if (is_wp_error($response)) {
            throw new Exception('Authentication failed: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->token_expiry = time() + 3500; // Token expires in 1 hour
            return $this->access_token;
        }
        
        throw new Exception('Failed to get access token');
    }
    
    public function generate_password() {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        return [
            'timestamp' => $timestamp,
            'password' => $password
        ];
    }
    
    public function stk_push($phone_number, $amount, $account_reference, $transaction_desc) {
        try {
            if (!$this->access_token || time() > $this->token_expiry) {
                $this->authenticate();
            }
            
            $credentials = $this->generate_password();
            
            $phone_number = $this->format_phone_number($phone_number);
            
            $request_data = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $credentials['password'],
                'Timestamp' => $credentials['timestamp'],
                'TransactionType' => 'CustomerBuyGoodsOnline',
                'Amount' => round($amount),
                'PartyA' => $phone_number,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone_number,

                'CallBackURL' => $this->get_callback_url(),
                'AccountReference' => substr($account_reference, 0, 12),
                'TransactionDesc' => substr($transaction_desc, 0, 13)

            ];
            
            WC_MPesa_Logger::log('STK Push Request: ' . json_encode($request_data));
            
            $response = wp_remote_post(
                $this->get_api_url('stk_push'),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($request_data),
                    'timeout' => 60
                ]
            );
            
            if (is_wp_error($response)) {
                throw new Exception('STK Push failed: ' . $response->get_error_message());
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            WC_MPesa_Logger::log('STK Push Response: ' . $response_body);
            
            return $response_data;
            
        } catch (Exception $e) {
            WC_MPesa_Logger::log('STK Push Error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    public function query_stk_status($checkout_request_id) {
        try {
            if (!$this->access_token || time() > $this->token_expiry) {
                $this->authenticate();
            }
            
            $credentials = $this->generate_password();
            
            $request_data = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $credentials['password'],
                'Timestamp' => $credentials['timestamp'],
                'CheckoutRequestID' => $checkout_request_id
            ];
            
            $response = wp_remote_post(
                $this->get_api_url('query'),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($request_data),
                    'timeout' => 30
                ]
            );
            
            if (is_wp_error($response)) {
                throw new Exception('Query failed: ' . $response->get_error_message());
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
            
        } catch (Exception $e) {
            WC_MPesa_Logger::log('Query Error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    public function format_phone_number($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
            return '254' . $phone;
        }
        
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '254' . substr($phone, 1);
        }
        
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return $phone;
        }
        
        throw new Exception('Invalid phone number format');
    }
    
    public function get_callback_url() {
        return rest_url('wc-mpesa/v1/callback');
    }
    
    public function validate_callback_signature($data) {
        // Implement IP whitelist or HMAC validation here if needed
        return true;
    }
}