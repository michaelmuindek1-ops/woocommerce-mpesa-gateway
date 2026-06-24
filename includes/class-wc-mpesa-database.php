<?php
class WC_MPesa_Database {
    
    private static $table_name;
    
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'wc_mpesa_transactions';
    }
    
    public static function create_tables() {
        global $wpdb;
        
        self::init();
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            merchant_request_id varchar(255) DEFAULT NULL,
            checkout_request_id varchar(255) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            transaction_id varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            result_code int(11) DEFAULT NULL,
            result_description text DEFAULT NULL,
            mpesa_receipt_number varchar(50) DEFAULT NULL,
            transaction_date datetime DEFAULT NULL,
            raw_response longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY checkout_request_id (checkout_request_id),
            KEY transaction_id (transaction_id),
            KEY mpesa_receipt_number (mpesa_receipt_number)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log_transaction($data) {
        global $wpdb;
        self::init();
        
        $wpdb->insert(
            self::$table_name,
            [
                'order_id' => $data['order_id'],
                'merchant_request_id' => $data['merchant_request_id'] ?? null,
                'checkout_request_id' => $data['checkout_request_id'] ?? null,
                'phone_number' => $data['phone_number'],
                'amount' => $data['amount'],
                'status' => $data['status'] ?? 'pending',
                'raw_response' => $data['raw_response'] ?? null,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    public static function update_transaction($checkout_request_id, $data) {
        global $wpdb;
        self::init();
        
        $wpdb->update(
            self::$table_name,
            $data,
            ['checkout_request_id' => $checkout_request_id],
            null,
            ['%s']
        );
    }
    
    public static function get_transactions($order_id = null, $limit = 50, $offset = 0) {
        global $wpdb;
        self::init();
        
        $where = '';
        $params = [];
        
        if ($order_id) {
            $where = "WHERE order_id = %d";
            $params[] = $order_id;
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, [$limit, $offset])
        );
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_transaction_by_checkout_id($checkout_request_id) {
        global $wpdb;
        self::init();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE checkout_request_id = %s",
            $checkout_request_id
        ));
    }
}