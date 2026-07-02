<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_PaymentMethod_Stripe extends SLN_PaymentMethod_Paypal//Abstract
{
    const VERSION = '0.1';

    private static $zeroDecimal = array(
        'JPY', 'VND', 'XOF', 'VUV', 'GNF', 'KRV', 'DJF', 'RWF', 'KMF', 'CLP', 'XPF', 'XAF', 'BIF', 'MGA'
    );

    public static function isZeroDecimal($currency)
    {
        return in_array($currency, self::$zeroDecimal);
    }

    public function getFields()
    {
        return array(
            'pay_stripe_apiKey',
            'pay_stripe_apiKeyPublic',
            'pay_stripe_method'
        );
    }

    public function dispatchThankYou(SLN_Shortcode_Salon_Step $shortcode, $booking = null)
    {
        // Validate required parameters
        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
        $op = isset($_GET['op']) ? sanitize_text_field($_GET['op']) : '';
        
        if ($mode == $this->getMethodKey() && !empty($op)) {

            $op = explode('-', $op);

            $action = $op[0];
            if($action == 'create'){
                if(empty($booking)){
                    $booking = $this->plugin->getBookingBuilder()->getLastBooking();
                }
                return $this->renderPayPage($booking, isset($_GET['pay_remaining_amount'])? $_GET['pay_remaining_amount']: 0);
            }

            if ($action == 'success') {

                if(empty($booking)){
                    $booking = $this->plugin->createBooking($op[1]);
                }

                $sessionID = $booking->getMeta('stripe_session_id');

                if (!$sessionID) {
                    return __('Your payment has not been completed', 'salon-booking-system');
                }

                $this->initApi();

                try {
                    $sessionCheckout = \Stripe\Checkout\Session::retrieve($sessionID);
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($sessionCheckout->payment_intent);

                    $paymentIntent = \Stripe\PaymentIntent::update($sessionCheckout->payment_intent, array(
                        'description' => "Booking #" . $booking->getId(),
                    ));

                    if ($paymentIntent->status === 'succeeded') {

                        $transactionID = $paymentIntent->charges->data[0]->balance_transaction;
                        $payRemainingAmount = isset($_GET['pay_remaining_amount']) && $_GET['pay_remaining_amount'];
                        $booking->markPaid($transactionID, $payRemainingAmount ? $booking->getRemaingAmountAfterPay(false) : 0);
                        return;
                    } else {
                        return __('Your payment has not been completed', 'salon-booking-system');
                    }

                } catch (Exception $ex) {
                    SLN_Plugin::addLog('stripe error: ' . $ex->getMessage());
                    return __('Payment failed, please try again', 'salon-booking-system');
                }

            } elseif ($action == 'cancel') {
                return __('Your payment has not been completed', 'salon-booking-system');
            } else {
                // Log unrecognized operation
                SLN_Plugin::addLog('Stripe: Unrecognized operation: ' . $action);
                throw new Exception('payment method operation not managed: ' . $action);
            }

        } else {
            // Improved error logging for debugging
            SLN_Plugin::addLog('Stripe payment callback error - Missing parameters');
            SLN_Plugin::addLog('Expected mode: ' . $this->getMethodKey());
            SLN_Plugin::addLog('Received mode: ' . $mode);
            SLN_Plugin::addLog('Received op: ' . $op);
            SLN_Plugin::addLog('Query string: ' . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'empty'));
            SLN_Plugin::addLog('Request URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'empty'));
            
            // User-friendly error message
            if (empty($mode) && empty($op)) {
                throw new Exception(__('Payment callback received without required parameters. Please contact support if this persists.', 'salon-booking-system'));
            } elseif ($mode != $this->getMethodKey()) {
                throw new Exception(__('Payment method mismatch. Please try again or contact support.', 'salon-booking-system'));
            } else {
                throw new Exception(__('Payment operation parameter missing. Please try again or contact support.', 'salon-booking-system'));
            }
        }
    }

    public function getApiKeyPublic()
    {
        return $this->plugin->getSettings()->get('pay_stripe_apiKeyPublic');
    }

    public function getApiKey()
    {
        return $this->plugin->getSettings()->get('pay_stripe_apiKey');
    }

    public function getPaymentMethod()
    {
        $method = $this->plugin->getSettings()->get('pay_stripe_method');
        return $method ? $method : 'card';
    }

    public function renderPayButton($data){
        extract($data);
        $payUrl = add_query_arg(array('op' => 'create'), $payUrl);
        return $this->plugin->loadView('payment_method/' . $this->getMethodKey() . '/pay', compact('booking', 'paymentMethod', 'ajaxData', 'payUrl', 'payRemainingAmount'));
    }

    public function renderPayPage($booking, $payRemainingAmount)
    {

        if ($this->isAjax()) {
            $_SERVER['REQUEST_URI'] = add_query_arg(array('sln_step_page' => 'summary', 'submit_summary' => 'next', 'mode' => $this->getMethodKey(), 'op' => null, 'pay_remaining_amount' => $payRemainingAmount ? 1 : 0), str_replace(array($_SERVER["SERVER_NAME"], 'https://', 'http://', 'www.'), '', $_SERVER['HTTP_REFERER']));
        } else {
            $_SERVER['REQUEST_URI'] = add_query_arg(array('sln_step_page' => 'summary', 'submit_summary' => 'next', 'mode' => $this->getMethodKey(), 'op' => null, 'pay_remaining_amount' => $payRemainingAmount ? 1 : 0));
        }

        $successUrl = add_query_arg(['mode' => $this->getMethodKey(), 'op' => 'success-' . $booking->getId()], SLN_Func::currPageUrl());
        $cancelUrl = add_query_arg(['mode' => $this->getMethodKey(), 'op' => 'cancel-' . $booking->getId()], SLN_Func::currPageUrl());

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
        if ($payRemainingAmount) {
            $extraArgs['pay_remaining_amount'] = 1;
        }

        if (!empty($extraArgs)) {
            $successUrl = add_query_arg($extraArgs, $successUrl);
            $cancelUrl = add_query_arg($extraArgs, $cancelUrl);
        }

        $this->initApi();

        $settings = $this->plugin->getSettings();
        
        // Log booking details for debugging
        SLN_Plugin::addLog(sprintf(
            '[Stripe] renderPayPage - Booking #%d: Amount=%s, Deposit=%s, Tips=%s',
            $booking->getId(),
            $booking->getAmount(),
            $booking->getDeposit(),
            $booking->getTips()
        ));
        
        if($settings->isPaymentDepositFixedAmount()) {
            $amount = $booking->getDeposit() > 0 ? $booking->getDeposit() : $booking->getToPayAmount(false);
        }else {
            $amount = $booking->getDeposit($booking->getToPayAmount(false)) > 0 ? $booking->getDeposit($booking->getToPayAmount(false)) : $booking->getToPayAmount(false);
        }
        
        SLN_Plugin::addLog(sprintf('[Stripe] Calculated amount to charge: %s (before conversion)', $amount));
        
        $amount = intval((string)($amount * (self::isZeroDecimal($this->plugin->getSettings()->getCurrency()) ? 1 : 100)));

        if ($payRemainingAmount) {
            $amount = intval((string)($booking->getRemaingAmountAfterPay(false) * (self::isZeroDecimal($this->plugin->getSettings()->getCurrency()) ? 1 : 100)));
        }

        try {

            $sessionSettings = array(
                "success_url" => apply_filters('sln.payment.stripe.success_url', $successUrl),
                "cancel_url" => apply_filters('sln.payment.stripe.cancel_url', $cancelUrl),
                "line_items" => array(array(
                    'price_data' => array(
                        'currency' => strtolower($this->plugin->getSettings()->getCurrency()),
                        'unit_amount' => $amount,
                        'product_data' => array(
                            'name' => "Booking #" . $booking->getId(),
                        ),
                    ),
                    "quantity" => 1,
                )),
                'locale' => $this->plugin->getSettings()->getLocale(),
                'mode' => 'payment',
            );

            // omit payment_method_types option if we want all available methods to show up
            $paymentMethod = $this->getPaymentMethod();
            if (!empty($paymentMethod) && $paymentMethod != 'all') {
                $sessionSettings['payment_method_types'] = array($paymentMethod);
            }

            $response = \Stripe\Checkout\Session::create($sessionSettings);

            $sessionID = $response->id;

            $endpoints = \Stripe\WebhookEndpoint::all();
            $webhookEndpoints = get_option('sln_stripe_webhook_endpoints') ?? array();
            $haveEndpoint = false;
            foreach ($endpoints as $ep) {
                $ep->delete();

                if (SLN_Payment_Stripe::getWebhookUrl($payRemainingAmount) === $ep->url) {
                    foreach($webhookEndpoints as $i => $webhookEndpoint) {
                        if ($webhookEndpoint['url'] === $ep->url) {
                            if ($webhookEndpoint['id'] === $ep->id && self::VERSION === $webhookEndpoint['version']) {
                                $haveEndpoint = true;
                                break;
                            } else {
                                try {
                                    $ep->delete();
                                } catch (Exception $e) {}
                                unset($webhookEndpoints[$i]);
                            }
                        }
                    }
                    if (!$haveEndpoint) {
                        try {
                            $ep->delete();
                        } catch (Exception $e) {}
                    }
                }
            }

            foreach ($endpoints as $ep) {
                if (strstr($ep->url, 'sln_action=stripe_webhook') && !strstr($ep->url, 'pay_remaining_amount')) {
                    try {
                        $ep->delete();
                    } catch (Exception $e) {}
                }
            }

            if (!$haveEndpoint) {
                $endpoint = \Stripe\WebhookEndpoint::create([
                    'url' => SLN_Payment_Stripe::getWebhookUrl($payRemainingAmount),
                    'enabled_events' => [
                        'checkout.session.completed',
                    ],
                ]);
                $webhookEndpoints[] = array(
                    'id'  => $endpoint->id,
                    'url' => SLN_Payment_Stripe::getWebhookUrl($payRemainingAmount),
                    'secret' => $endpoint->secret,
                    'version' => self::VERSION,
                );
                update_option('sln_stripe_webhook_endpoints', $webhookEndpoints);
            }

        } catch (Exception $e) {

            SLN_Plugin::addLog('stripe error: ' . $e->getMessage());

            echo esc_html__('Payment method failed, details: ', 'salon-booking-system') . $e->getMessage();

            return true;
        }

        $booking->setMeta('stripe_session_id', $sessionID);
        
        // Log for diagnostic purposes (helps identify if session ID was saved)
        SLN_Plugin::addLog(sprintf(
            'Stripe: Session ID %s saved to booking #%d',
            $sessionID,
            $booking->getId()
        ));
        
        wp_redirect($response->url);
        die;
    }

    protected function initApi()
    {

        if (!class_exists('Stripe\Stripe')) {
            require_once __DIR__ . '/_stripe/vendor/autoload.php';
        }

        \Stripe\Stripe::setApiKey($this->plugin->getSettings()->get('pay_stripe_apiKey'));
    }

    public function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

}
