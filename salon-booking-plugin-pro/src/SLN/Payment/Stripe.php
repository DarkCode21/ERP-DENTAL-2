<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotValidated
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

class SLN_Payment_Stripe
{
    protected $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public static function getWebhookUrl($isPayRemainingAmount = false)
    {
        $url = add_query_arg(['sln_action' => 'stripe_webhook', 'pay_remaining_amount' => $isPayRemainingAmount ? 1 : 0], home_url('/'));
        return apply_filters('sln.payment.stipe.webhookurl', $url);
    }

    public function execute()
    {
        if (!isset($_GET['sln_action']) || $_GET['sln_action'] !== 'stripe_webhook') {
            return;
        }
        $this->initApi();

        $webhook_endpoints = get_option('sln_stripe_webhook_endpoints') ?? array();
        $current_url = SLN_Func::currPageUrl();

        $endpoints = \Stripe\WebhookEndpoint::all();

        $endpoint = false;
        foreach($endpoints as $ep) {
            foreach($webhook_endpoints as $webhook_endpoint) {
                if ($current_url === $webhook_endpoint['url'] && $current_url === $ep->url && $webhook_endpoint['id'] === $ep->id) {
                    $endpoint = $webhook_endpoint;
                }
            }
        }

        if ( ! $endpoint ) {
            return;
        }

        $endpoint_secret = $endpoint['secret'];

        $payload = @file_get_contents('php://input');
        $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }

        // Handle the event
        $checkoutSession = null;
        switch ($event->type) {
            case 'checkout.session.completed':
                $checkoutSession = $event->data->object; // contains a \Stripe\Checkout\Session
                // Then define and call a method to handle the successful payment intent.
                // handlePaymentIntentSucceeded($paymentIntent);
                break;
        }

        $payRemainingAmount = isset($_GET['pay_remaining_amount']) && $_GET['pay_remaining_amount'];

        if ($checkoutSession) {
            $statuses = array(SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT);
            if ($payRemainingAmount) {
                $statuses[] = SLN_Enum_BookingStatus::PAID;
            }
            $booking  = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->getOne(array(
                'post_status' => $statuses,
                '@wp_query'   => array(
                    'meta_query' => array(
                        array(
                            'key'     => '_sln_booking_stripe_session_id',
                            'value'   => $checkoutSession->id,
                            'compare' => '=',
                        )
                    ),
                )
            ));

            $paymentIntent = \Stripe\PaymentIntent::retrieve($checkoutSession->payment_intent);
        }

        if ($booking) {
            try {
                $paymentIntent = \Stripe\PaymentIntent::update($paymentIntent->id, array(
                        'description' => "Booking #" . $booking->getId(),
                ));
                $checkShop = True;
                if(defined('SLNMS_VERSION')){
                    $musltishop = \SalonMultishop\Addon::getInstance()->getCurrentShop()->getId();
                    if($musltishop != $booking->getMeta('shop')){
                        $checkShop = false;
                    }
                }

                if ($paymentIntent->status === 'succeeded' && $checkShop) {
                    $transactionID = $paymentIntent->charges->data[0]->balance_transaction;
                    $booking->markPaid($transactionID, $payRemainingAmount ? $booking->getRemaingAmountAfterPay(false) : 0);
                }

            } catch (Exception $ex) {
                    SLN_Plugin::addLog('stripe error: ' . $ex->getMessage());
            }
        } else if ($checkoutSession && isset($paymentIntent)) {
            // CRITICAL: Booking not found but payment was received
            // Log and notify WITHOUT triggering retry (always return 200)
            
            // Only alert if payment actually succeeded (not just session created)
            if (isset($paymentIntent->status) && $paymentIntent->status === 'succeeded') {
                $sessionId = $checkoutSession->id;
                $amount = isset($checkoutSession->amount_total) ? ($checkoutSession->amount_total / 100) : 0;
                $customerEmail = isset($checkoutSession->customer_details->email) ? $checkoutSession->customer_details->email : 'N/A';
                
                SLN_Plugin::addLog(sprintf(
                    '=== STRIPE PAYMENT RECEIVED BUT BOOKING NOT FOUND ===\nSession ID: %s\nAmount: %s\nCustomer: %s\nPayment Status: %s',
                    $sessionId,
                    $amount,
                    $customerEmail,
                    $paymentIntent->status
                ));
                
                // Store minimal recovery data
                $recoveryData = array(
                    'timestamp' => current_time('mysql'),
                    'session_id' => $sessionId,
                    'amount' => $amount,
                    'customer_email' => $customerEmail,
                    'transaction_id' => isset($paymentIntent->charges->data[0]->balance_transaction) ? $paymentIntent->charges->data[0]->balance_transaction : null,
                );
                
                // Store with timestamp to avoid overwrites
                $optionKey = '_sln_stripe_missing_booking_' . time() . '_' . substr($sessionId, -8);
                update_option($optionKey, $recoveryData, false);
                
                // Send notification to salon admin/owner
                $this->notifySalonAdmin($sessionId, $amount, $customerEmail, $recoveryData['transaction_id'], $optionKey);
            }
        }

        // ALWAYS return 200 OK (never trigger retry)
        header('HTTP/1.1 200 OK');

        exit();

    }

    protected function initApi() {

        if ( ! class_exists('Stripe\Stripe') ) {
            require_once __DIR__ . '/../PaymentMethod/_stripe/vendor/autoload.php';
        }

        \Stripe\Stripe::setApiKey($this->plugin->getSettings()->get('pay_stripe_apiKey'));
    }

    /**
     * Notify salon admin/owner when payment received but booking not found
     * 
     * @param string $sessionId Stripe session ID
     * @param float $amount Payment amount
     * @param string $customerEmail Customer email
     * @param string $transactionId Stripe transaction ID
     * @param string $optionKey WordPress option key where recovery data is stored
     */
    private function notifySalonAdmin($sessionId, $amount, $customerEmail, $transactionId, $optionKey)
    {
        $settings = $this->plugin->getSettings();
        $salonName = $settings->getSalonName();
        $salonEmail = $settings->getSalonEmail();
        $adminEmail = get_option('admin_email');
        
        // Send to both salon email and WordPress admin email (in case they're different)
        $recipients = array_unique(array_filter(array($salonEmail, $adminEmail)));
        
        $subject = sprintf(
            '[URGENT] Payment Received But Booking Missing - %s',
            $salonName
        );
        
        $message = sprintf(
            "URGENT: A payment was successfully processed but the booking could not be found in the system.\n\n" .
            "=== PAYMENT DETAILS ===\n" .
            "Amount: %s\n" .
            "Customer Email: %s\n" .
            "Transaction ID: %s\n" .
            "Session ID: %s\n" .
            "Date/Time: %s\n\n" .
            "=== ACTION REQUIRED ===\n" .
            "1. Contact the customer immediately: %s\n" .
            "2. Get their appointment details (date, time, services)\n" .
            "3. Create the booking manually in WordPress admin\n" .
            "4. Set status to PAID and add transaction ID: %s\n" .
            "5. Send confirmation to customer\n\n" .
            "=== RECOVERY DATA ===\n" .
            "Full payment details stored in WordPress option:\n%s\n\n" .
            "To retrieve: get_option('%s')\n\n" .
            "=== DIAGNOSTIC ===\n" .
            "Check log file: wp-content/uploads/sln_uploads/log.txt\n" .
            "Search for: 'STRIPE PAYMENT RECEIVED BUT BOOKING NOT FOUND'\n\n" .
            "This is an automated alert from Salon Booking System.\n" .
            "Website: %s",
            $settings->getCurrencySymbol() . number_format($amount, 2),
            $customerEmail,
            $transactionId ? $transactionId : 'N/A',
            $sessionId,
            current_time('mysql'),
            $customerEmail,
            $transactionId ? $transactionId : 'N/A',
            $optionKey,
            $optionKey,
            get_site_url()
        );
        
        $headers = array(
            'From: Salon Booking System <noreply@' . parse_url(get_site_url(), PHP_URL_HOST) . '>',
            'Reply-To: ' . $adminEmail,
            'Content-Type: text/plain; charset=UTF-8'
        );
        
        foreach ($recipients as $recipient) {
            $sent = wp_mail($recipient, $subject, $message, $headers);
            
            if ($sent) {
                SLN_Plugin::addLog(sprintf(
                    'Stripe: Admin notification sent to %s (payment received but booking not found)',
                    $recipient
                ));
            } else {
                SLN_Plugin::addLog(sprintf(
                    'Stripe: Failed to send admin notification to %s',
                    $recipient
                ));
            }
        }
        
        // Also send via ErrorNotification system for centralized tracking
        if (class_exists('SLN_Helper_ErrorNotification')) {
            SLN_Helper_ErrorNotification::send(
                'STRIPE_BOOKING_NOT_FOUND',
                'Stripe payment succeeded but booking not found',
                sprintf(
                    "Session ID: %s\nAmount: %s\nCustomer: %s\nTransaction ID: %s\nRecovery data: %s",
                    $sessionId,
                    $amount,
                    $customerEmail,
                    $transactionId ? $transactionId : 'N/A',
                    $optionKey
                )
            );
        }
    }

}
