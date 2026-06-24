/**
 * M-PESA Payment Handler
 */
(function($) {
    'use strict';

    const MpesaPayment = {
        init: function() {
            this.orderId = wc_mpesa_params.order_id || 0;
            this.checkoutRequestId = null;
            this.statusCheckInterval = null;
            this.timeoutId = null;
            this.maxAttempts = 24; // 2 minutes (5 seconds * 24)
            this.currentAttempt = 0;
            
            this.bindEvents();
            this.autoInitiate();
        },

        bindEvents: function() {
            $('#initiate-mpesa-payment').on('click', this.initiatePayment.bind(this));
            $('#resend-mpesa-stk').on('click', this.initiatePayment.bind(this));
        },

        autoInitiate: function() {
            // Auto-initiate payment after 1 second
            setTimeout(() => {
                if ($('#initiate-mpesa-payment').length) {
                    $('#initiate-mpesa-payment').trigger('click');
                }
            }, 1000);
        },

        initiatePayment: function(e) {
            if (e) e.preventDefault();
            
            const self = this;
            const $button = $('#initiate-mpesa-payment');
            const $resendBtn = $('#resend-mpesa-stk');
            const $statusDiv = $('#mpesa-payment-status');
            const $messageEl = $('.mpesa-status-message');
            const $detailsEl = $('.mpesa-status-details');
            
            // Disable buttons
            $button.prop('disabled', true).text(wc_mpesa_params.i18n_processing || 'Processing...');
            $resendBtn.hide();
            
            // Show status
            $statusDiv.slideDown();
            $messageEl
                .removeClass('success error')
                .addClass('processing')
                .html('<strong>Initiating payment...</strong><br>Please check your phone for the M-PESA PIN prompt.');
            
            // Clear previous intervals
            if (this.statusCheckInterval) {
                clearInterval(this.statusCheckInterval);
            }
            if (this.timeoutId) {
                clearTimeout(this.timeoutId);
            }

            // Make AJAX request
            $.ajax({
                url: wc_mpesa_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'process_mpesa_payment',
                    order_id: this.orderId || $('input[name="order_id"]').val(),
                    phone: $('input[name="mpesa_phone"]').val(),
                    nonce: wc_mpesa_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.checkoutRequestId = response.data.checkout_request_id;
                        
                        $messageEl
                            .removeClass('processing')
                            .addClass('success')
                            .html('<strong>STK Push sent!</strong><br>Please check your phone and enter your M-PESA PIN.');
                        
                        $detailsEl.html(`
                            <small>Checkout ID: ${response.data.checkout_request_id}</small><br>
                            <small>If you don't see the prompt, check your phone or resend.</small>
                        `);
                        
                        // Show resend button
                        $resendBtn.show();
                        $button.hide();
                        
                        // Start checking payment status
                        self.startStatusCheck(response.data.checkout_request_id);
                        
                    } else {
                        const errorMsg = response.data.message || 'Payment failed to initiate. Please try again.';
                        
                        $messageEl
                            .removeClass('processing')
                            .addClass('error')
                            .html(`<strong>Error:</strong> ${errorMsg}`);
                        
                        $button.prop('disabled', false)
                            .text(wc_mpesa_params.i18n_pay || 'Pay with M-PESA');
                        $resendBtn.show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $messageEl
                        .removeClass('processing')
                        .addClass('error')
                        .html('<strong>Connection Error</strong><br>Please check your internet connection and try again.');
                    
                    $button.prop('disabled', false)
                        .text(wc_mpesa_params.i18n_pay || 'Pay with M-PESA');
                    $resendBtn.show();
                }
            });
        },

        startStatusCheck: function(checkoutRequestId) {
            const self = this;
            this.currentAttempt = 0;
            
            // Check status every 5 seconds
            this.statusCheckInterval = setInterval(function() {
                self.currentAttempt++;
                
                $.ajax({
                    url: wc_mpesa_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'check_mpesa_payment_status',
                        order_id: self.orderId || $('input[name="order_id"]').val(),
                        checkout_request_id: checkoutRequestId,
                        nonce: wc_mpesa_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const status = response.data.status;
                            const $messageEl = $('.mpesa-status-message');
                            
                            switch(status) {
                                case 'completed':
                                    self.paymentComplete(response.data.receipt);
                                    break;
                                    
                                case 'failed':
                                    self.paymentFailed(response.data.receipt);
                                    break;
                                    
                                case 'processing':
                                    $messageEl
                                        .removeClass('error')
                                        .addClass('processing')
                                        .html('<strong>Payment Processing...</strong><br>Please wait while we confirm your payment.');
                                    break;
                                    
                                default:
                                    // Still pending, continue waiting
                                    if (self.currentAttempt >= self.maxAttempts) {
                                        self.paymentTimeout();
                                    }
                            }
                        }
                    },
                    error: function() {
                        // Don't show error for status checks, just continue trying
                        if (self.currentAttempt >= self.maxAttempts) {
                            self.paymentTimeout();
                        }
                    }
                });
            }, 5000);

            // Stop checking after 2 minutes
            this.timeoutId = setTimeout(function() {
                self.paymentTimeout();
            }, 120000);
        },

        paymentComplete: function(receipt) {
            // Clear intervals
            this.clearIntervals();
            
            const $messageEl = $('.mpesa-status-message');
            const $detailsEl = $('.mpesa-status-details');
            const $statusIcon = $('.mpesa-status-icon');
            
            // Update UI
            $statusIcon.html('<span style="font-size: 40px;">✅</span>');
            $messageEl
                .removeClass('processing error')
                .addClass('success')
                .html('<strong>Payment Confirmed!</strong><br>Your payment has been received successfully.');
            
            $detailsEl.html(`
                <div class="mpesa-receipt-info">
                    <strong>M-PESA Receipt:</strong> ${receipt || 'N/A'}<br>
                    <strong>Amount:</strong> ${$('.order-total .amount').text() || 'Confirmed'}<br>
                    <small>Redirecting to order confirmation page...</small>
                </div>
            `);
            
            // Hide buttons
            $('#initiate-mpesa-payment, #resend-mpesa-stk').hide();
            
            // Redirect after short delay
            setTimeout(() => {
                const redirectUrl = wc_mpesa_params.redirect_url || 
                                  window.location.href.replace('order-pay', 'order-received');
                window.location.href = redirectUrl;
            }, 3000);
        },

        paymentFailed: function(receipt) {
            this.clearIntervals();
            
            const $messageEl = $('.mpesa-status-message');
            const $statusIcon = $('.mpesa-status-icon');
            const $button = $('#initiate-mpesa-payment');
            const $resendBtn = $('#resend-mpesa-stk');
            
            $statusIcon.html('<span style="font-size: 40px;">❌</span>');
            $messageEl
                .removeClass('processing success')
                .addClass('error')
                .html('<strong>Payment Failed</strong><br>The payment was not completed. Please try again.');
            
            $button.prop('disabled', false)
                .text(wc_mpesa_params.i18n_pay || 'Pay with M-PESA')
                .show();
            $resendBtn.show();
        },

        paymentTimeout: function() {
            this.clearIntervals();
            
            const $messageEl = $('.mpesa-status-message');
            const $button = $('#initiate-mpesa-payment');
            const $resendBtn = $('#resend-mpesa-stk');
            
            $messageEl
                .removeClass('processing success')
                .addClass('error')
                .html('<strong>Payment Timeout</strong><br>The payment request has expired. Please try again.');
            
            $button.prop('disabled', false)
                .text(wc_mpesa_params.i18n_pay || 'Pay with M-PESA')
                .show();
            $resendBtn.show();
        },

        clearIntervals: function() {
            if (this.statusCheckInterval) {
                clearInterval(this.statusCheckInterval);
                this.statusCheckInterval = null;
            }
            if (this.timeoutId) {
                clearTimeout(this.timeoutId);
                this.timeoutId = null;
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#initiate-mpesa-payment').length) {
            MpesaPayment.init();
        }
    });

})(jQuery);
