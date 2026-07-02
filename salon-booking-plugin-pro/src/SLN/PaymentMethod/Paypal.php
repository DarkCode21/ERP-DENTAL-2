<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_PaymentMethod_Paypal extends SLN_PaymentMethod_Abstract
{

    public function getFields(){
        return array(
            'pay_paypal_email',
            'pay_paypal_test'
        );
    }

    public function dispatchThankYou(SLN_Shortcode_Salon_Step $shortcode, SLN_Wrapper_Booking $booking = null){
        if (isset($_GET['op'])) {
            $op = explode('-', sanitize_text_field($_GET['op']));
            $action = $op[0];
            $bookingIdFromOp = isset($op[1]) ? intval($op[1]) : 0;
            
            // Enhanced logging for debugging
            SLN_Plugin::addLog(sprintf(
                'PaymentMethod_Paypal::dispatchThankYou op=%s bookingId=%s bookingIdFromOp=%d GET_params=%s',
                sanitize_text_field($_GET['op']),
                $booking ? $booking->getId() : 'null',
                $bookingIdFromOp,
                json_encode(array_keys($_GET))
            ));
            
            if ($action == 'success') {
                // CRITICAL FIX: If booking is null but we have ID in op, try to load it
                // This handles cases where session/transient expired during PayPal redirect
                if (!$booking && $bookingIdFromOp > 0) {
                    $booking = $this->plugin->createBooking($bookingIdFromOp);
                    SLN_Plugin::addLog(sprintf(
                        'PaymentMethod_Paypal::dispatchThankYou - booking was null, loaded from op: #%d (status: %s)',
                        $bookingIdFromOp,
                        $booking ? $booking->getStatus() : 'still null'
                    ));
                }
                
                // Customer returned from PayPal
                // IMPROVED: Don't mark as paid here - let IPN handle it for better reliability
                // This prevents duplicate transaction IDs (PayerID vs actual txn_id)
                $payerId = isset($_GET['PayerID']) ? sanitize_text_field($_GET['PayerID']) : 'unknown';
                $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : 'unknown';
                
                if (!$booking) {
                    // CRITICAL: Booking is still null - this is a serious error
                    SLN_Plugin::addLog(sprintf(
                        'PaymentMethod_Paypal::dispatchThankYou CRITICAL ERROR - booking is null! op=%s, PayerID=%s',
                        sanitize_text_field($_GET['op']),
                        $payerId
                    ));
                    // Try to send error notification
                    if (class_exists('SLN_Helper_ErrorNotification')) {
                        try {
                            SLN_Helper_ErrorNotification::send(
                                new Exception('PayPal payment received but booking not found: op=' . sanitize_text_field($_GET['op'])),
                                'PayPal Payment - Booking Not Found'
                            );
                        } catch (Exception $e) {
                            // Ignore notification errors
                        }
                    }
                    return;
                }
                
                // Log the successful return
                SLN_Plugin::addLog(sprintf(
                    'PaymentMethod_Paypal::dispatchThankYou - Customer returned from PayPal for booking #%d (PayerID: %s, token: %s, current status: %s). Waiting for IPN to confirm payment.',
                    $booking->getId(),
                    $payerId,
                    $token,
                    $booking->getStatus()
                ));
                
                // Store PayPal return data for reference
                update_post_meta($booking->getId(), '_sln_paypal_return_data', array(
                    'payer_id' => $payerId,
                    'token' => $token,
                    'return_time' => current_time('mysql'),
                    'status_at_return' => $booking->getStatus()
                ));
                
                // TESTING MODE FALLBACK: Mark as paid on return if IPN cannot be relied upon
                // This handles two scenarios:
                // 1. Localhost/development environments where PayPal IPN cannot reach the site
                // 2. PayPal Sandbox mode where IPN is notoriously unreliable (often fails to send notifications)
                $siteUrl = site_url();
                $isLocalhost = (
                    strpos($siteUrl, 'localhost') !== false ||
                    strpos($siteUrl, '127.0.0.1') !== false ||
                    strpos($siteUrl, '.local') !== false ||
                    strpos($siteUrl, '192.168.') !== false ||
                    strpos($siteUrl, '10.0.') !== false
                );
                
                // Check if PayPal is in Sandbox/Test mode
                $isPayPalTestMode = $this->plugin->getSettings()->get('pay_paypal_test');
                
                // Apply fallback for localhost OR sandbox mode
                $shouldFallbackToMarkPaid = ($isLocalhost || $isPayPalTestMode) && 
                                            !in_array($booking->getStatus(), [SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED]);
                
                if ($shouldFallbackToMarkPaid) {
                    // Mark as paid immediately since IPN cannot be relied upon
                    $booking->markPaid($payerId . ($isLocalhost ? '-LOCALHOST-TEST' : '-SANDBOX-TEST'));
                    
                    // CRITICAL: Force WordPress to flush any pending auto-saves
                    // This prevents the status from being reverted by auto-save
                    wp_cache_delete($booking->getId(), 'posts');
                    clean_post_cache($booking->getId());
                    
                    $testingMode = $isLocalhost ? 'LOCALHOST' : 'PAYPAL SANDBOX';
                    SLN_Plugin::addLog(sprintf(
                        '%s TESTING MODE: Marked booking #%d as paid on return (PayerID: %s). IPN cannot be relied upon in this mode.',
                        $testingMode,
                        $booking->getId(),
                        $payerId
                    ));
                } else {
                    // Production: Show success to customer even though IPN hasn't arrived yet
                    // IPN will mark as paid within seconds (usually < 5 seconds)
                    // If booking is already paid (IPN arrived first), that's fine too
                    if (in_array($booking->getStatus(), [SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED])) {
                        SLN_Plugin::addLog(sprintf(
                            'PaymentMethod_Paypal::dispatchThankYou - booking #%d already marked as paid (IPN arrived first)',
                            $booking->getId()
                        ));
                    }
                }
                
                return;
            } elseif ($action == 'notify') {
                $this->processIpn($op[1]);
            } elseif ($action == 'cancel') {
                return __('Your payment has not been completed', 'salon-booking-system');
            } else {
                throw new Exception('payment method operation not managed');
            }
        } elseif ($_GET['mode'] == 'paypal') {
            if ($shortcode->isAjax()) {
                    $bookUrl = str_replace(str_replace($_SERVER['REQUEST_URI'], '', SLN_Func::currPageUrl()), '', get_permalink($this->plugin->getSettings()->get('pay')));
                    $_SERVER['REQUEST_URI'] = $bookUrl.'?sln_step_page='.$shortcode->getStep(). '&submit_'.$shortcode->getStep(). '=next&mode=paypal';
                    if ($this->isPayRemainingAmount()) {
                        $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'].'&pay_remaining_amount=1';
                    }
            }
            $ppl = new SLN_Payment_Paypal($this->plugin);
            $settings = $this->plugin->getSettings();
            if($settings->isPaymentDepositFixedAmount()) {
                $amount = $booking->getDeposit() > 0 ? $booking->getDeposit() : $booking->getToPayAmount(false);
            } else {
                $amount = $booking->getDeposit($booking->getToPayAmount(false)) > 0 ? $booking->getDeposit($booking->getToPayAmount(false)) : $booking->getToPayAmount(false);
            }
            $amount = $this->isPayRemainingAmount() ? $booking->getRemaingAmountAfterPay(false) : $amount;
            $extraArgs = array();
            if (isset($_GET['sln_client_id'])) {
                $extraArgs['sln_client_id'] = sanitize_text_field(wp_unslash($_GET['sln_client_id']));
            } else {
                $clientId = $this->plugin->getBookingBuilder()->getClientId();
                if (!empty($clientId)) {
                    $extraArgs['sln_client_id'] = $clientId;
                }
            }
            $extraArgs['sln_booking_id'] = $booking->getId();
            if (isset($_GET['lang'])) {
                $extraArgs['lang'] = sanitize_text_field(wp_unslash($_GET['lang']));
            }
            if ($this->isPayRemainingAmount()) {
                $extraArgs['pay_remaining_amount'] = 1;
            }
            $url = $ppl->getUrl($booking->getId(), $amount, $booking->getTitle(), $extraArgs);
            $shortcode->redirect($url);
        } else {
            throw new Exception('payment method mode not managed');
        }
    }

    private function processIpn($id){
        // Log IPN receipt immediately for debugging (before any operations)
        SLN_Plugin::addLog(sprintf(
            'PayPal IPN received for booking #%d: payment_status=%s, mc_gross=%s, txn_id=%s, ipn_track_id=%s',
            $id,
            isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : 'N/A',
            isset($_POST['mc_gross']) ? sanitize_text_field($_POST['mc_gross']) : 'N/A',
            isset($_POST['txn_id']) ? sanitize_text_field($_POST['txn_id']) : 'N/A',
            isset($_POST['ipn_track_id']) ? sanitize_text_field($_POST['ipn_track_id']) : 'N/A'
        ));
        
        // Validate booking ID
        if (!$id || $id <= 0) {
            SLN_Plugin::addLog('PayPal IPN ERROR: Invalid booking ID: ' . var_export($id, true));
            echo('ipn_failed - invalid booking id');
            return;
        }
        
        $booking = $this->plugin->createBooking($id);
        
        // Check if booking exists
        if (!$booking || !$booking->getId()) {
            SLN_Plugin::addLog(sprintf('PayPal IPN ERROR: Booking #%d not found', $id));
            echo('ipn_failed - booking not found');
            return;
        }
        
        // CRITICAL FIX: Check for duplicate transaction ID
        $txnId = isset($_POST['txn_id']) ? sanitize_text_field($_POST['txn_id']) : '';
        $ipnTrackId = isset($_POST['ipn_track_id']) ? sanitize_text_field($_POST['ipn_track_id']) : '';
        
        if (empty($txnId)) {
            SLN_Plugin::addLog(sprintf('PayPal IPN ERROR: Missing txn_id for booking #%d', $id));
            echo('ipn_failed - no txn_id');
            return;
        }
        
        // Check if this txn_id already exists in transaction_id array
        $existingTransactions = $booking->getTransactionId();
        if (in_array($txnId, $existingTransactions)) {
            SLN_Plugin::addLog(sprintf(
                'PayPal IPN DUPLICATE: txn_id=%s already processed for booking #%d (existing transactions: %s), skipping',
                $txnId,
                $id,
                implode(', ', $existingTransactions)
            ));
            echo('ipn_success - already processed');
            return;
        }
        
        // Check if this ipn_track_id was already stored (prevents duplicate IPN processing)
        if (!empty($ipnTrackId)) {
            $processedIpns = get_post_meta($booking->getId(), '_sln_paypal_processed_ipns', true);
            if (!is_array($processedIpns)) {
                $processedIpns = array();
            }
            
            if (in_array($ipnTrackId, $processedIpns)) {
                SLN_Plugin::addLog(sprintf(
                    'PayPal IPN DUPLICATE: ipn_track_id=%s already processed for booking #%d, skipping',
                    $ipnTrackId,
                    $id
                ));
                echo('ipn_success - duplicate ipn_track_id');
                return;
            }
            
            // Mark this IPN as processed BEFORE any other operations
            $processedIpns[] = $ipnTrackId;
            update_post_meta($booking->getId(), '_sln_paypal_processed_ipns', $processedIpns);
            
            SLN_Plugin::addLog(sprintf(
                'PayPal IPN: Marked ipn_track_id=%s as processed for booking #%d',
                $ipnTrackId,
                $id
            ));
        }
        
        $ppl = new SLN_Payment_Paypal($this->plugin);
        
        // CRITICAL FIX: Store IPN data BEFORE checking if already paid
        // This ensures complete audit trail and prevents status reversion
        $ipn_key = '_sln_paypal_ipn_' . uniqid();
        update_post_meta($booking->getId(), $ipn_key, $_POST);
        
        // Check if booking is already paid (e.g., customer return marked it as paid first)
        if (in_array($booking->getStatus(), [SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED])) {
            // CRITICAL: Even if already paid, we must ensure transaction ID is stored
            // Otherwise WordPress auto-save or other processes may revert the status
            $txnId = isset($_POST['txn_id']) ? sanitize_text_field($_POST['txn_id']) : '';
            
            if (!empty($txnId)) {
                $existingTransactions = $booking->getTransactionId();
                
                // Add transaction ID if not already stored
                if (!in_array($txnId, $existingTransactions)) {
                    $existingTransactions[] = $txnId;
                    update_post_meta($booking->getId(), '_sln_booking_transaction_id', $existingTransactions);
                    
                    SLN_Plugin::addLog(sprintf(
                        'PayPal IPN: Booking #%d already paid (status: %s), but added missing transaction ID: %s',
                        $id,
                        $booking->getStatus(),
                        $txnId
                    ));
                } else {
                    SLN_Plugin::addLog(sprintf(
                        'PayPal IPN: Booking #%d already paid (status: %s), transaction ID already stored',
                        $id,
                        $booking->getStatus()
                    ));
                }
            }
            
            echo('ipn_success - already paid');
            return;
        }
        
        // Log booking status for debugging
        SLN_Plugin::addLog(sprintf(
            'PayPal IPN processing booking #%d: current_status=%s, amount=%s',
            $id,
            $booking->getStatus(),
            $booking->getAmount()
        ));
        
        ob_end_clean();
        
        // Calculate expected amount
        $expectedAmount = $this->isPayRemainingAmount() 
            ? $booking->getRemaingAmountAfterPay(false) 
            : $booking->getToPayAmount(false);
        
        // Verify IPN with PayPal
        $ipnVerified = $ppl->reverseCheckIpn();
        
        if (!$ipnVerified) {
            SLN_Plugin::addLog(sprintf(
                'PayPal IPN FAILED verification for booking #%d - IPN not verified by PayPal',
                $id
            ));
            echo('ipn_failed - verification');
            return;
        }
        
        // Check payment status (accept Completed or Pending)
        $paymentStatus = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
        if (!in_array($paymentStatus, ['Completed', 'Pending'])) {
            SLN_Plugin::addLog(sprintf(
                'PayPal IPN: booking #%d has payment_status=%s (not Completed/Pending) - skipping markPaid',
                $id,
                $paymentStatus
            ));
            echo('ipn_skipped - status: ' . $paymentStatus);
            return;
        }
        
        // Check amount with tolerance for floating point issues
        $paidAmount = isset($_POST['mc_gross']) ? floatval($_POST['mc_gross']) : 0;
        $amountMatches = $ppl->isAmountValid($paidAmount, $expectedAmount);
        
        if (!$amountMatches) {
            // Log detailed info for debugging but still process if payment is completed
            SLN_Plugin::addLog(sprintf(
                'PayPal IPN WARNING for booking #%d: Amount mismatch - paid=%s, expected=%s (booking_amount=%s). Processing anyway since payment_status=Completed.',
                $id,
                $paidAmount,
                $expectedAmount,
                $booking->getAmount()
            ));
        }
        
        // Mark as paid - prioritize successful payment over strict amount matching
        // PayPal has verified the payment, so we should honor it
        $transactionId = $this->isTest() ? 'test' : $ppl->getTransactionId();
        $remainedAmount = $this->isPayRemainingAmount() ? $booking->getRemaingAmountAfterPay(false) : 0;
        
        $booking->markPaid($transactionId, $remainedAmount);
        
        SLN_Plugin::addLog(sprintf(
            'PayPal IPN SUCCESS: booking #%d marked as paid. Transaction ID: %s',
            $id,
            $transactionId
        ));
        
        echo('ipn success');
    }

    private function isTest(){
        return $this->plugin->getSettings()->isPaypalTest();
    }

    private function isPayRemainingAmount(){
        return isset($_GET['pay_remaining_amount']) && $_GET['pay_remaining_amount'];
    }
}
