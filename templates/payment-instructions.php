<?php
/**
 * M-PESA Payment Instructions Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mpesa-payment-instructions">
    <div class="mpesa-instructions-header">
        <img src="<?php echo WC_MPESA_PLUGIN_URL . 'assets/images/mpesa-logo.png'; ?>" 
             alt="M-PESA Logo" class="mpesa-logo" />
        <h2><?php _e('M-PESA Payment Instructions', 'wc-mpesa-gateway'); ?></h2>
    </div>
    
    <div class="mpesa-instructions-steps">
        <div class="mpesa-step">
            <div class="step-number">1</div>
            <div class="step-content">
                <h3><?php _e('STK Push Notification', 'wc-mpesa-gateway'); ?></h3>
                <p><?php _e('You will receive an M-PESA payment prompt on your phone. This is an STK push notification asking you to enter your M-PESA PIN.', 'wc-mpesa-gateway'); ?></p>
            </div>
        </div>
        
        <div class="mpesa-step">
            <div class="step-number">2</div>
            <div class="step-content">
                <h3><?php _e('Enter Your PIN', 'wc-mpesa-gateway'); ?></h3>
                <p><?php _e('Enter your M-PESA PIN to authorize the payment. Make sure you have sufficient funds in your M-PESA account.', 'wc-mpesa-gateway'); ?></p>
            </div>
        </div>
        
        <div class="mpesa-step">
            <div class="step-number">3</div>
            <div class="step-content">
                <h3><?php _e('Payment Confirmation', 'wc-mpesa-gateway'); ?></h3>
                <p><?php _e('After entering your PIN, you will receive an M-PESA confirmation SMS. Your order will be processed automatically once payment is confirmed.', 'wc-mpesa-gateway'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="mpesa-important-notes">
        <h3><?php _e('Important Notes', 'wc-mpesa-gateway'); ?></h3>
        <ul>
            <li><?php _e('Keep your phone nearby and ensure you have sufficient M-PESA balance', 'wc-mpesa-gateway'); ?></li>
            <li><?php _e('The STK push notification is valid for 2 minutes only', 'wc-mpesa-gateway'); ?></li>
            <li><?php _e('Do not navigate away from this page until payment is confirmed', 'wc-mpesa-gateway'); ?></li>
            <li><?php _e('If you don\'t receive the prompt within 30 seconds, click the "Resend STK Push" button', 'wc-mpesa-gateway'); ?></li>
            <li><?php _e('For assistance, contact our support team', 'wc-mpesa-gateway'); ?></li>
        </ul>
    </div>
    
    <div class="mpesa-payment-actions">
        <button type="button" class="button alt" id="initiate-mpesa-payment">
            <?php _e('Pay with M-PESA', 'wc-mpesa-gateway'); ?>
        </button>
        <button type="button" class="button" id="resend-mpesa-stk" style="display:none;">
            <?php _e('Resend STK Push', 'wc-mpesa-gateway'); ?>
        </button>
    </div>
    
    <div id="mpesa-payment-status" style="display:none;">
        <div class="mpesa-status-card">
            <div class="mpesa-status-icon">
                <span class="mpesa-spinner"></span>
            </div>
            <p class="mpesa-status-message"></p>
            <div class="mpesa-status-details"></div>
        </div>
    </div>
</div>

<style>
    .mpesa-payment-instructions {
        max-width: 600px;
        margin: 30px auto;
        padding: 30px;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    .mpesa-instructions-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .mpesa-logo {
        max-width: 120px;
        height: auto;
        margin-bottom: 15px;
    }
    
    .mpesa-instructions-header h2 {
        color: #2d3748;
        font-size: 24px;
        margin: 0;
    }
    
    .mpesa-instructions-steps {
        margin-bottom: 30px;
    }
    
    .mpesa-step {
        display: flex;
        align-items: flex-start;
        margin-bottom: 20px;
        padding: 15px;
        background: #f7fafc;
        border-radius: 8px;
        border-left: 4px solid #48bb78;
    }
    
    .step-number {
        background: #48bb78;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .step-content h3 {
        margin: 0 0 5px 0;
        color: #2d3748;
        font-size: 16px;
    }
    
    .step-content p {
        margin: 0;
        color: #718096;
        font-size: 14px;
        line-height: 1.5;
    }
    
    .mpesa-important-notes {
        background: #fffaf0;
        border: 1px solid #fbd38d;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .mpesa-important-notes h3 {
        color: #c05621;
        margin-top: 0;
    }
    
    .mpesa-important-notes ul {
        margin: 10px 0 0 0;
        padding-left: 20px;
    }
    
    .mpesa-important-notes li {
        color: #744210;
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .mpesa-payment-actions {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .mpesa-payment-actions .button {
        padding: 12px 30px;
        font-size: 16px;
        border-radius: 5px;
        margin: 5px;
        transition: all 0.3s ease;
    }
    
    .mpesa-payment-actions .button.alt {
        background: #48bb78;
        color: white;
        border: none;
    }
    
    .mpesa-payment-actions .button.alt:hover {
        background: #38a169;
    }
    
    .mpesa-payment-actions .button.alt:disabled {
        background: #a0aec0;
        cursor: not-allowed;
    }
    
    #mpesa-payment-status {
        text-align: center;
    }
    
    .mpesa-status-card {
        padding: 20px;
        background: #f7fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .mpesa-status-icon {
        margin-bottom: 15px;
    }
    
    .mpesa-spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid #e2e8f0;
        border-top: 4px solid #48bb78;
        border-radius: 50%;
        animation: mpesa-spin 1s linear infinite;
    }
    
    @keyframes mpesa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .mpesa-status-message {
        font-size: 16px;
        margin: 10px 0;
        padding: 10px;
        border-radius: 5px;
    }
    
    .mpesa-status-message.processing {
        background: #ebf8ff;
        color: #2b6cb0;
        border: 1px solid #bee3f8;
    }
    
    .mpesa-status-message.success {
        background: #f0fff4;
        color: #22543d;
        border: 1px solid #c6f6d5;
    }
    
    .mpesa-status-message.error {
        background: #fff5f5;
        color: #742a2a;
        border: 1px solid #fed7d7;
    }
    
    .mpesa-status-details {
        font-size: 14px;
        color: #718096;
        margin-top: 10px;
    }
    
    .mpesa-receipt-info {
        background: #f0fff4;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10px;
    }
    
    .mpesa-receipt-info strong {
        color: #22543d;
    }
</style>