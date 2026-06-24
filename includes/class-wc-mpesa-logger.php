<?php
class WC_MPesa_Logger {
    
    private static $logger;
    private static $debug;
    
    public static function init() {
        if (!self::$logger) {
            self::$logger = wc_get_logger();
            $settings = get_option('wc_mpesa_settings', []);
            self::$debug = $settings['debug'] ?? 'no';
        }
    }
    
    public static function log($message, $level = 'info') {
        self::init();
        
        if (self::$debug === 'yes' || $level === 'error') {
            self::$logger->log($level, '[M-PESA] ' . $message, [
                'source' => 'wc-mpesa-gateway'
            ]);
        }
    }
}