<?php
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.curl_curl_setopt
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.curl_curl_error
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.curl_curl_close

class SLN_Payment_Paypal
{
    const TEST_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
    const PROD_URL = 'https://www.paypal.com/cgi-bin/webscr';
    protected $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    function reverseCheckIpn()
    {
        $raw_post_data  = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost         = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        $req = 'cmd=_notify-validate';
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }
        $isTest = $this->plugin->getSettings()->isPaypalTest();


        $ch = curl_init($this->getBaseUrl());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $isTest ? 0 : 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isTest ? 0 : 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        if (!($res = curl_exec($ch))) {
            SLN_Plugin::addLog("Got " . curl_error($ch) . " when processing IPN data");
            curl_close($ch);
            exit;
        }
        curl_close($ch);


        return (strcmp($res, "VERIFIED") == 0);
    }


    public function getUrl($id, $amount, $title, $extraArgs = array())
    {
        $settings = $this->plugin->getSettings();
        $url      = SLN_Func::currPageUrl();
	$url	  = apply_filters('sln.booking.payment.paypal.get-url', $url);

        $extraArgs = is_array($extraArgs) ? array_filter($extraArgs) : array();

        $pageArgs = $this->extractPageArgs($url);
        $baseUrl  = remove_query_arg(array_keys($pageArgs), $url);

        $notifyUrl = add_query_arg(array_merge($pageArgs, array('op' => 'notify-' . $id)), $baseUrl);
        $returnUrl = add_query_arg(array_merge($pageArgs, array('op' => 'success-' . $id)), $baseUrl);
        $cancelUrl = add_query_arg(array_merge($pageArgs, array('op' => 'cancel-' . $id)), $baseUrl);

        if (!empty($extraArgs)) {
            $notifyUrl = add_query_arg($extraArgs, $notifyUrl);
            $returnUrl = add_query_arg($extraArgs, $returnUrl);
            $cancelUrl = add_query_arg($extraArgs, $cancelUrl);
        }

        return $this->getBaseUrl() . "?"
        . http_build_query(
            array(
                'notify_url'    => $notifyUrl,
                'return'        => $returnUrl,
                'cancel_return' => $cancelUrl,
                'cmd'           => '_xclick',
                'business'      => $settings->getPaypalEmail(),
                'currency_code' => $settings->getCurrency(),
                'amount'        => $amount,
                'item_name'     => $title
            )
        );
    }

    private function extractPageArgs($url)
    {
        $args = array();
        $parts = wp_parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $args);
        }
        if (isset($args['page_id']) && strpos($args['page_id'], '?') !== false) {
            $subParts = explode('?', $args['page_id'], 2);
            $args['page_id'] = $subParts[0];
            if (!empty($subParts[1])) {
                parse_str($subParts[1], $extra);
                $args = array_merge($extra, $args);
            }
        }
        return $args;
    }

    private function getBaseUrl()
    {
        $isTest = $this->plugin->getSettings()->isPaypalTest();
        return $isTest ?
            self::TEST_URL : self::PROD_URL;
    }

    function isCompleted($amount)
    {
        return $this->isAmountValid(floatval($_POST['mc_gross']), floatval($amount)) 
            && ($_POST['payment_status'] == 'Completed' || $_POST['payment_status'] == 'Pending');
    }
    
    /**
     * Check if paid amount matches expected amount with tolerance for floating point issues
     * 
     * @param float $paidAmount Amount paid (from PayPal)
     * @param float $expectedAmount Expected amount (from booking)
     * @param float $tolerance Acceptable difference (default 0.02 = 2 cents)
     * @return bool
     */
    function isAmountValid($paidAmount, $expectedAmount, $tolerance = 0.02)
    {
        // Round both to 2 decimal places to avoid floating point precision issues
        $paid = round(floatval($paidAmount), 2);
        $expected = round(floatval($expectedAmount), 2);
        
        // Check if amounts match within tolerance
        $difference = abs($paid - $expected);
        
        return $difference <= $tolerance;
    }
    
    function getTransactionId(){
        return sanitize_text_field(wp_unslash( $_POST['txn_id'] ));
    }
}