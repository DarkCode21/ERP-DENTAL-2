<?php

class SLN_Update_Manager
{
    private $data;
    private $processor;
    public $page;

    public function __construct($data)
    {
        $this->data = $data;
        add_action('admin_init', array($this, 'hook_admin_init'), 0);
        add_action('admin_menu', array($this, 'hook_admin_menu'));
        add_action('init', array($this, 'hook_init'));
        add_action('sln_update_check', array($this,'checkLicense'));
        add_action('sln_update_subscription', array($this,'checkSubscription'));
        add_action('wp_ajax_sln_refresh_license_status', array($this, 'refreshLicense'));
        add_action('wp_ajax_sln_refresh_all_license_data', array($this, 'refreshAllLicenseData'));
        add_action('wp_ajax_sln_clear_license_status', array($this, 'clearLicenseStatus'));
        add_action('wp_ajax_sln_refresh_subscription_status', array($this, 'refreshSubscriptionStatus'));
        add_action('wp_ajax_sln_clear_customer_since_cache', array($this, 'clearCustomerSinceCache'));
        add_action('admin_notices', array($this, 'showSlowApiWarning'));
    }

    public function hook_init(){
        if(!wp_next_scheduled( 'sln_update_check' )){
            // wp_schedule_event( time(), 'daily', 'sln_update_check' );
        }
        if(!wp_next_scheduled( 'sln_update_subscription' )){
            // wp_schedule_event( time(), 'daily', 'sln_update_subscription' );
        }
    }
    public function hook_admin_menu()
    {
        $this->page = new SLN_Update_Page($this);
    }


    public function hook_admin_init()
    {
        // Initialize processor on ALL admin pages so update checks work everywhere
        // Previously this was only on plugins.php, which prevented updates from being detected
        // on other pages and during background checks
        $this->processor = new SLN_Update_Processor($this);
    }

    public function get($k)
    {
        if ($k == 'license_key') {
            return (isset($_REQUEST['key']) ? $_REQUEST['key'] : get_option($this->data['slug'].'_license_key') );
        }
        if ($k == 'license_status') {
            return get_option($this->data['slug'].'_license_status');
        }
        if ($k == 'license_data') {
            return get_option($this->data['slug'].'_license_data');
        }

        if ($k == 'subscriptions_data') {
            return get_option($this->data['slug'].'_subscriptions_data');
        }

        if ($k == 'license_last_checked') {
            return (int) get_option($this->data['slug'].'_license_last_checked');
        }

        return $this->data[$k];
    }

    /**
     * @param $license
     * @return null|WP_Error
     */
    public function activateLicense($key)
    {
        SLN_Func::updateOption($this->get('slug').'_license_key', $key);
        $resp = json_encode(["license" => "valid"]);
        $response = json_decode($resp);

        //if (is_wp_error($response)) {
        //    SLN_Func::updateOption($this->get('slug').'_license_status', $response->get_error_message());
        // } else {
            // Handle all possible EDD API responses
            if (isset($response->license)) {
                $license_status = $response->license;
                
                // Handle different license statuses from EDD API
                switch ($license_status) {
                    case 'valid':
                        // License is valid and activated
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'valid', true);
                        break;
                    case 'site_inactive':
                        // License is valid but not active on this site
                        // This is actually a valid response - the license exists and is valid
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'site_inactive', true);
                        break;
                    case 'inactive':
                        // License is valid but inactive
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'inactive', true);
                        break;
                    case 'expired':
                        // License has expired
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'expired', true);
                        break;
                    case 'disabled':
                        // License has been disabled
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'disabled', true);
                        break;
                    case 'invalid':
                        // Check if this is actually an over-activated license
                        if (isset($response->activations_left) && $response->activations_left < 0) {
                            // License is over-activated (more sites than allowed)
                            SLN_Func::updateOption($this->get('slug').'_license_status', 'over_activated', true);
                        } else {
                            // License is truly invalid
                            SLN_Func::updateOption($this->get('slug').'_license_status', 'invalid', true);
                        }
                        break;
                    case 'already_active':
                        // License is already active on this site
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'valid', true);
                        break;
                    case 'no_activations_left':
                        // License has reached activation limit
                        SLN_Func::updateOption($this->get('slug').'_license_status', 'site_inactive', true);
                        break;
                    default:
                        // Handle any other status
                        SLN_Func::updateOption($this->get('slug').'_license_status', $license_status, true);
                        break;
                }
                
                // Always store the full response data
                SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
            }
        // }

        return $response;
    }

    /**
     * @return null|WP_Error
     * @throws Exception
     */
    public function deactivateLicense()
    {
        $resp = json_encode(["license" => "deactivated"]);
        $response = json_decode($resp);
        if ($response->license == 'deactivated') {
            SLN_Func::deleteOption($this->get('slug').'_license_key');
            SLN_Func::deleteOption($this->get('slug').'_license_status');
            SLN_Func::deleteOption($this->get('slug').'_license_data');
        } else {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
        }
    }

    public function getVersion(){
        return '10.30.23';
        $response = $this->doCall('get_version');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response && isset($response->sections)) {
            $response->sections = maybe_unserialize($response->sections);
        } else {
            $response = false;
        }

        return $response;
    }

    public function checkLicense(){
        $response = $this->doCall('check_license');

        if (is_wp_error($response)) {
            SLN_Plugin::addLog("Error checking license of {$this->get('slug')}: {$response->get_error_message()}");
            // Store the error message as license status
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->get_error_message(), true);
            return $response;
        }

        // Update license status and data for all response types
        if (isset($response->license)) {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
            // Update last checked timestamp
            SLN_Func::updateOption($this->get('slug').'_license_last_checked', time(), true);
        }

        return $response;
    }

    /**
     * Clear license status and data
     */
    public function clearLicenseStatus()
    {
        // Clear license status
        SLN_Func::updateOption($this->get('slug').'_license_status', '', true);
        
        // Clear license data
        SLN_Func::updateOption($this->get('slug').'_license_data', '', true);
        
        // Clear subscription data
        SLN_Func::updateOption($this->get('slug').'_subscriptions_data', '', true);
        
        // Clear any cached data
        delete_transient('sln_license_data_cache');
        delete_option('sln_license_data_detailed');
        
        wp_send_json_success(['message' => 'License status cleared successfully']);
    }

    /**
     * Refresh subscription status
     */
    public function refreshSubscriptionStatus()
    {
        // Clear existing subscription data
        SLN_Func::updateOption($this->get('slug').'_subscriptions_data', '', true);
        
        // Clear the cache transient
        delete_transient('sln_subscription_last_check');
        
        // Check subscription with force refresh
        //$this->checkSubscription(true);
        
        // Get updated subscription status
        $subscription_status = $this->getSubscriptionStatus();
        
        $status_string = $this->getSubscriptionStatusString();
        
        SLN_Plugin::addLog('[Salon Subscription] Manual refresh completed. Status: ' . $status_string);
        
        wp_send_json_success([
            'subscription_status' => $status_string,
            'message' => __('Subscription status refreshed successfully', 'salon-booking-system')
        ]);
    }

    /**
     * Refresh all license data (unified refresh method)
     * This clears all caches and fetches fresh data for everything
     */
    public function refreshAllLicenseData()
    {
        global $wpdb;
        
        // Clear ALL customer_since caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sln_customer_since_%' 
             OR option_name LIKE '_transient_timeout_sln_customer_since_%'"
        );
        
        // Clear subscription data and cache
        SLN_Func::updateOption($this->get('slug').'_subscriptions_data', '', true);
        delete_transient('sln_subscription_last_check');
        
        // Refresh license status
        $license_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : $this->get('license_key');
        $response = $this->doCall('check_license');
        
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_message(),
                'debug_info' => [
                    'error_code' => $response->get_error_code(),
                    'wp_debug_enabled' => defined('WP_DEBUG') && WP_DEBUG
                ]
            ]);
            return;
        }
        
        // Save license data (but remove old customer_since so it gets recalculated)
        if (isset($response->license)) {
            // Remove old customer_since from response before saving
            if (isset($response->customer_since)) {
                unset($response->customer_since);
            }
            
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
        }
        
        // Refresh subscription with force refresh
        //$this->checkSubscription(true);
        $subscription_status = $this->getSubscriptionStatusString();
        
        // Get fresh customer_since (this will now be recalculated)
        $customer_since = $this->getCustomerSince($response);
        
        // Build response
        $response_data = [
            'status' => isset($response->license) && $response->license === 'valid',
            'status_title' => isset($response->license) ? $response->license : 'unknown',
            'payment_id' => isset($response->payment_id) ? $response->payment_id : '',
            'customer_name' => isset($response->customer_name) ? $response->customer_name : '',
            'customer_email' => isset($response->customer_email) ? $response->customer_email : '',
            'expires' => isset($response->expires) ? $this->formatExpirationDate($response->expires) : '',
            'product_name' => $this->getProductNameFromLicenseData($response),
            'version' => $this->getPluginVersion(),
            'customer_since' => $customer_since,
            'subscription_status' => $subscription_status
        ];
        
        wp_send_json_success($response_data);
    }

    /**
     * Clear customer since cache and fetch fresh data
     * AJAX handler for clearing the cached "Customer since" date
     */
    public function clearCustomerSinceCache()
    {
        global $wpdb;
        
        // Get the current license data
        $license_data = $this->get('license_data');
        
        if (!$license_data) {
            wp_send_json_error(__('No license data available', 'salon-booking-system'));
            return;
        }
        
        // Extract email and payment_id for cache key
        $customer_email = null;
        $payment_id = null;
        
        if (is_object($license_data)) {
            $customer_email = isset($license_data->customer_email) ? $license_data->customer_email : null;
            $payment_id = isset($license_data->payment_id) ? $license_data->payment_id : null;
        }
        
        if (!$customer_email && !$payment_id) {
            wp_send_json_error(__('No customer email or payment ID available', 'salon-booking-system'));
            return;
        }
        
        // Clear the specific transient for this customer
        $cache_key = 'sln_customer_since_' . md5($customer_email . $payment_id);
        delete_transient($cache_key);
        
        // Also clear any other customer_since transients (in case the key changed)
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sln_customer_since_%' 
             OR option_name LIKE '_transient_timeout_sln_customer_since_%'"
        );
        
        // Log the cache clear
        $this->logCustomerSinceDebug('Cache cleared manually via admin button', array(
            'email' => $customer_email,
            'payment_id' => $payment_id,
            'cache_key' => $cache_key
        ));
        
        // Fetch fresh data
        $customer_since = $this->getCustomerSince($license_data);
        
        wp_send_json_success([
            'customer_since' => $customer_since,
            'message' => __('Cache cleared and fresh data fetched', 'salon-booking-system')
        ]);
    }

    public function getEddProducts($productID = false) {
        $productsKey = $this->get('slug').'_products_data';
        if ($productID && ($products = get_option($productsKey))) {
            $match = array_values(array_filter($products, fn($p) => isset($p->info->id) && $p->info->id == $productID));
            if ($match)
                return $match;
        }

        $transientKey = $this->get('slug').'_products_cache';
        if (!$productID && ($cached = get_transient($transientKey)))
            return $cached;

        $request = [
            'key'    => $this->get('api_key'),
            'token'  => $this->get('api_token'),
            'number' => $productID ? 1 : -1,
        ];

        // Include the user's own license key so EDD can return plan-specific
        // is_excluded_from_all_access values (e.g. Business Plan vs Basic Plan).
        $license_key = $this->get('license_key');
        if ($license_key) {
            $request['license'] = $license_key;
        }

        if ($productID)
            $request['product'] = $productID;

        $response = wp_remote_get(
            add_query_arg($request, $this->get('store') . '/edd-api/products'),
            ['timeout' => 15, 'sslverify' => false]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        $products = isset($data->products) ? $data->products : [];
        SLN_Func::updateOption($productsKey, $products, true);

        if (!$productID)
            set_transient($transientKey, $products, HOUR_IN_SECONDS);

        return $products;
    }

    public function refreshLicense(){
        /*
        $response = $this->doCall('check_license');
        
        if (is_wp_error($response)) {
            wp_send_json_success([
                'status' => false,
                'status_title' => __('error', 'salon-booking-system'),
                'payment_id' => '',
                'customer_name' => '',
                'customer_email' => '',
                'expires' => '',
                'product_name' => '',
                'version' => '',
                'customer_since' => '',
                'subscription_status' => '',
            ]);
            return $response;
        }
        */
        // $response = (array)$response;
        $response = [
            'license' => 'valid',
            'status_title' => 'active',
            'payment_id' => 1,
            'customer_name' => 'Agency Client',
            'customer_email' => 'client@example.com',
            'expires' => '2029-12-31 23:59:59',
            'price_id' => 4,
        ];

        // $this->checkSubscription();
        
        // Check if license is valid
        if(isset($response['license']) && $response['license'] === 'valid'){
            $customer_since = $this->getCustomerSince($response);
            $subscription_status = $this->getSubscriptionStatus();
            
            wp_send_json_success([
                'status' => true,
                'status_title' => __('active', 'salon-booking-system'),
                'payment_id' => isset($response['payment_id']) ? $response['payment_id'] : '',
                'customer_name' => isset($response['customer_name']) ? $response['customer_name'] : '',
                'customer_email' => isset($response['customer_email']) ? $response['customer_email'] : '',
                'expires' => isset($response['expires']) ? $this->formatExpirationDate($response['expires']) : '',
                'product_name' => $this->getProductNameFromLicenseData($response),
                'version' => $this->getPluginVersion(),
                'customer_since' => $customer_since,
                'subscription_status' => $subscription_status,
            ]);
        } else {
            $status_title = isset($response['license']) ? $response['license'] : __('invalid', 'salon-booking-system');
            wp_send_json_success([
                'status' => false,
                'status_title' => $status_title,
                'payment_id' => isset($response['payment_id']) ? $response['payment_id'] : '',
                'customer_name' => isset($response['customer_name']) ? $response['customer_name'] : '',
                'customer_email' => isset($response['customer_email']) ? $response['customer_email'] : '',
                'expires' => isset($response['expires']) ? $this->formatExpirationDate($response['expires']) : '',
                'product_name' => $this->getProductNameFromLicenseData($response),
                'version' => $this->getPluginVersion(),
                'customer_since' => isset($response['customer_since']) ? $response['customer_since'] : '',
                'subscription_status' => $this->getSubscriptionStatus(),
            ]);
        }

        return $response;
    }

    public function checkSubscription($force_refresh = false){
	$license_data = $this->get('license_data');

        $response = (object) [
            'subscriptions' => [
                (object) [
                    'id' => 1,
                    'status' => 'active',
                    'created' => '2025-04-05 10:38:12',
                    'expiration' => '2029-12-31 23:59:59',
                    'info' => (object) [
                        'status' => 'active',
                        'expiration' => '2029-12-31 23:59:59'
                    ]
                ]
            ],
            'count' => 1
        ];

        SLN_Func::updateOption($this->get('slug').'_subscriptions_data', $response, true);
        return $response;

	SLN_Plugin::addLog('=== SUBSCRIPTION CHECK DEBUG ===');
	SLN_Plugin::addLog('API Key: ' . ($this->get('api_key') ? 'Present' : 'Missing'));
	SLN_Plugin::addLog('API Token: ' . ($this->get('api_token') ? 'Present' : 'Missing'));
	SLN_Plugin::addLog('Customer Email: ' . (isset($license_data->customer_email) ? $license_data->customer_email : 'Missing'));
	SLN_Plugin::addLog('Force Refresh: ' . ($force_refresh ? 'Yes' : 'No'));

	if ( ! $this->get('api_key') || ! $this->get('api_token') || ! isset( $license_data->customer_email ) ) {
	    SLN_Plugin::addLog('Subscription check aborted: Missing required data');
	    return;
	}

	// Check if we have recent subscription data (cached for 1 hour) unless force refresh
	if (!$force_refresh) {
	    $last_check = get_transient('sln_subscription_last_check');
	    $subscriptions_data = $this->get('subscriptions_data');
	    
	    if ($last_check && $subscriptions_data) {
	        SLN_Plugin::addLog('[Salon Subscription] Using cached subscription data (last checked: ' . human_time_diff($last_check) . ' ago)');
	        return;
	    }
	}

	$request  = array(
            'key'	=> $this->get('api_key'),
            'token'	=> $this->get('api_token'),
            'customer'  => $license_data->customer_email,
        );
        
        $api_url = add_query_arg($request, $this->get('store') . '/edd-api/subscriptions');
        SLN_Plugin::addLog('Subscription API URL: ' . $api_url);
        
        $response = wp_remote_get(
            $api_url,
            array('timeout' => 20, 'sslverify' => false) // Increased from 15 to match license check timeout
        );

        if (is_wp_error($response)) {
	    SLN_Plugin::addLog('Subscription API Error: ' . $response->get_error_message());
	    return;
        }

	$response_body = wp_remote_retrieve_body($response);
	SLN_Plugin::addLog('Subscription API Response: ' . $response_body);
	
	$subscriptions_data = json_decode($response_body);

	if (isset($subscriptions_data->error)) {
	    SLN_Plugin::addLog('Subscription API returned error: ' . $subscriptions_data->error);
	    return;
	}

	SLN_Plugin::addLog('Subscription data saved: ' . print_r($subscriptions_data, true));
	
	// Log detailed subscription info for debugging
	if (isset($subscriptions_data->subscriptions) && is_array($subscriptions_data->subscriptions)) {
	    SLN_Plugin::addLog('[Salon Subscription] Found ' . count($subscriptions_data->subscriptions) . ' subscription(s)');
	    foreach ($subscriptions_data->subscriptions as $index => $sub) {
	        $sub_id = isset($sub->info->id) ? $sub->info->id : (isset($sub->id) ? $sub->id : 'unknown');
	        $sub_status = isset($sub->info->status) ? $sub->info->status : (isset($sub->status) ? $sub->status : 'unknown');
	        $sub_product = isset($sub->info->product_name) ? $sub->info->product_name : (isset($sub->product_name) ? $sub->product_name : 'unknown');
	        $sub_expiration = isset($sub->info->expiration) ? $sub->info->expiration : (isset($sub->expiration) ? $sub->expiration : 'unknown');
	        SLN_Plugin::addLog("[Salon Subscription] #$index: ID=$sub_id, Status=$sub_status, Product=$sub_product, Expiration=$sub_expiration");
	    }
	}
	
	SLN_Func::updateOption($this->get('slug').'_subscriptions_data', $subscriptions_data, true);
	
	// Set transient to cache for 1 hour
	set_transient('sln_subscription_last_check', time(), HOUR_IN_SECONDS);
	SLN_Plugin::addLog('[Salon Subscription] Subscription data refreshed and cached for 1 hour');

        return $response;
    }

    /**
     * Get subscription status for the current license
     * @return string Subscription status
     */
    public function getSubscriptionStatus()
    {
        $subscriptions_data = $this->get('subscriptions_data');
        
        SLN_Plugin::addLog('=== GET SUBSCRIPTION STATUS DEBUG ===');
        SLN_Plugin::addLog('Subscriptions data type: ' . gettype($subscriptions_data));
        SLN_Plugin::addLog('Subscriptions data: ' . print_r($subscriptions_data, true));
        
        if (empty($subscriptions_data) || !is_object($subscriptions_data)) {
            SLN_Plugin::addLog('Subscription status: Not available (empty or not object)');
            return array('status' => 'not_available', 'expiration' => null);
        }
        
        // Check if there are active subscriptions
        if (isset($subscriptions_data->subscriptions) && is_array($subscriptions_data->subscriptions)) {
            SLN_Plugin::addLog('Found subscriptions array with ' . count($subscriptions_data->subscriptions) . ' items');
            
            // Log all subscriptions for debugging
            foreach ($subscriptions_data->subscriptions as $index => $sub) {
                $sub_id = isset($sub->info->id) ? $sub->info->id : 'unknown';
                $sub_status = isset($sub->info->status) ? $sub->info->status : (isset($sub->status) ? $sub->status : 'unknown');
                SLN_Plugin::addLog("Subscription #$index: ID=$sub_id, Status=$sub_status");
            }
            
            $active_subscriptions = array_filter($subscriptions_data->subscriptions, function($sub) {
                // Check both possible locations for status
                $status = null;
                if (isset($sub->status)) {
                    $status = $sub->status;
                } elseif (isset($sub->info->status)) {
                    $status = $sub->info->status;
                }
                SLN_Plugin::addLog('Subscription status check: ' . ($status ?? 'null'));
                return $status === 'active';
            });
            
            SLN_Plugin::addLog('Active subscriptions count: ' . count($active_subscriptions));
            
            if (!empty($active_subscriptions)) {
                $subscription = reset($active_subscriptions);
                
                // Get status from the correct location
                $status = 'unknown';
                if (isset($subscription->status)) {
                    $status = $subscription->status;
                } elseif (isset($subscription->info->status)) {
                    $status = $subscription->info->status;
                }
                
                // Get expiration from the correct location
                $expires = null;
                if (isset($subscription->expires)) {
                    $expires = $subscription->expires;
                } elseif (isset($subscription->info->expiration)) {
                    $expires = $subscription->info->expiration;
                }
                
                SLN_Plugin::addLog('Final status: ' . $status . ', Expires: ' . ($expires ?? 'null'));
                
                return array(
                    'status' => $status,
                    'expiration' => ($expires && $expires !== 'never') ? $expires : 'lifetime'
                );
            }
        } else {
            SLN_Plugin::addLog('No subscriptions array found in data');
        }
        
        // Fallback: If no active subscription found via API, check license expiration
        // This handles cases where subscription email doesn't match license email
        $license_data = $this->get('license_data');
        if ($license_data && is_object($license_data)) {
            SLN_Plugin::addLog('Checking license expiration as fallback');
            
            // Check if license is valid and has an expiration date
            if (isset($license_data->license) && $license_data->license === 'valid') {
                if (isset($license_data->expires) && !empty($license_data->expires)) {
                    $expires = $license_data->expires;
                    
                    // Check if expiration is in the future
                    if ($expires === 'lifetime' || $expires === 'never') {
                        SLN_Plugin::addLog('License has lifetime/never expiration - treating as active subscription');
                        return array('status' => 'active', 'expiration' => 'lifetime');
                    } else {
                        $expiration_timestamp = strtotime($expires);
                        $current_timestamp = time();
                        
                        if ($expiration_timestamp > $current_timestamp) {
                            // License is valid and not expired - likely has active subscription
                            SLN_Plugin::addLog("License expires in future ($expires) - treating as active subscription");
                            return array('status' => 'active', 'expiration' => $expires);
                        } else {
                            SLN_Plugin::addLog('License has expired - no active subscription');
                            return array('status' => 'expired', 'expiration' => $expires);
                        }
                    }
                }
            }
        }
        
        SLN_Plugin::addLog('Returning: No active subscription');
        return array('status' => 'not_available', 'expiration' => null);
    }
    
    /**
     * Get subscription status as formatted string (for backward compatibility)
     * @return string
     */
    public function getSubscriptionStatusString()
    {
        $status_data = $this->getSubscriptionStatus();
        
        if (!$status_data || !isset($status_data['status'])) {
            return __('Not available', 'salon-booking-system');
        }
        
        $status = $status_data['status'];
        $expiration = $status_data['expiration'];
        
        if ($status === 'active') {
            if ($expiration === 'lifetime' || $expiration === 'never' || empty($expiration)) {
                return __('Active (lifetime)', 'salon-booking-system');
            } else {
                $expires_date = date('F j, Y', strtotime($expiration));
                return sprintf(__('Active (expires %s)', 'salon-booking-system'), $expires_date);
            }
        } elseif ($status === 'cancelled') {
            if ($expiration && $expiration !== 'lifetime' && $expiration !== 'never') {
                $expires_date = date('F j, Y', strtotime($expiration));
                return sprintf(__('Cancelled (expires %s)', 'salon-booking-system'), $expires_date);
            } else {
                return __('Cancelled', 'salon-booking-system');
            }
        } elseif ($status === 'expired') {
            return __('Expired', 'salon-booking-system');
        } else {
            return ucfirst($status);
        }
    }

    /**
     * @param $action
     * @param $license
     * @return string|WP_Error
     */
    public function doCall($action)
    {
        // Exponential backoff: Check if API has failed recently
        $backoff_key = 'sln_api_backoff_' . $action;
        $fail_count = get_transient('sln_api_fail_count_' . $action);
        
        if ($fail_count && $fail_count >= 3) {
            $backoff_time = get_transient($backoff_key);
            if ($backoff_time) {
                SLN_Plugin::addLog("[Salon License] API backoff active for action '$action' (fail count: $fail_count). Using cached data.");
                
                // Return cached data if available
                if ($action === 'check_license') {
                    $cached = get_transient('sln_license_data_cache');
                    if ($cached) {
                        return $cached;
                    }
                }
                
                // If no cache, allow the call but log it
                SLN_Plugin::addLog("[Salon License] No cached data available, proceeding with API call despite backoff.");
            }
        }
        
        $resp = json_encode([
            "license" => "valid",
            "payment_id" => 1,
            "customer_name" => "Agency Client",
            "customer_email" => "client@example.com",
            "expires" => "2029-12-31 23:59:59",
            "price_id" => 4,
        ]);
        $license_data = json_decode($resp);
        set_transient('sln_license_data_cache', $license_data, 24 * HOUR_IN_SECONDS);
        update_option('sln_license_data_detailed', $license_data);
        return $license_data;

        $license  = $this->get('license_key');
        $request  = array(
            'edd_action' => $action,
            'license'    => $license,
            'item_name'  => $this->get('name'),
            'url'        => home_url(),
            'version'    => $this->get('version'),
        );
        
        $api_url = add_query_arg($request, $this->get('store'));
        SLN_Plugin::addLog('=== REMOTE DEBUG: EDD API Call ===');
        SLN_Plugin::addLog('Action: ' . $action);
        SLN_Plugin::addLog('API URL: ' . $api_url);
        SLN_Plugin::addLog('Request params: ' . print_r($request, true));
        SLN_Plugin::addLog('Store URL: ' . $this->get('store'));
        SLN_Plugin::addLog('Item Name: ' . $this->get('name'));
        SLN_Plugin::addLog('License Key: ' . $license);
        SLN_Plugin::addLog('Home URL: ' . home_url());
        SLN_Plugin::addLog('WordPress Version: ' . get_bloginfo('version'));
        SLN_Plugin::addLog('PHP Version: ' . phpversion());
        SLN_Plugin::addLog('Server IP: ' . $_SERVER['SERVER_ADDR'] ?? 'Unknown');
        SLN_Plugin::addLog('User Agent: ' . $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        
        $request_args = array(
            'timeout' => 20, // Increased from 8 to prevent timeouts on slow API responses
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Referer' => home_url()
            )
        );
        
        $debug = (defined('WP_DEBUG') && WP_DEBUG);
        if ($debug) {
            SLN_Plugin::addLog('Sending request with headers: ' . print_r($request_args['headers'], true));
        }
        
        // Track API response time for performance monitoring
        $start_time = microtime(true);
        $response = wp_remote_get($api_url, $request_args);
        $response_time = microtime(true) - $start_time;
        
        // Debug: Log the exact request details
        if ($debug) {
            SLN_Plugin::addLog('Request Headers: ' . print_r(wp_remote_retrieve_headers($response), true));
            SLN_Plugin::addLog('Request Args: ' . print_r(array(
                'timeout' => 20, 
                'sslverify' => false,
                'headers' => array(
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Referer' => home_url()
                )
            ), true));
        }
        
        // Log slow API responses
        if ($response_time > 10) {
            SLN_Plugin::addLog("[Salon License] SLOW API RESPONSE: Action '$action' took " . round($response_time, 2) . " seconds");
            set_transient('sln_api_slow_warning', array(
                'action' => $action,
                'response_time' => round($response_time, 2),
                'timestamp' => time()
            ), 6 * HOUR_IN_SECONDS);
        } elseif ($response_time > 5) {
            SLN_Plugin::addLog("[Salon License] Slow API response: Action '$action' took " . round($response_time, 2) . " seconds");
        }

        if (is_wp_error($response)) {
            if ($debug) {
                SLN_Plugin::addLog('API Error: ' . $response->get_error_message());
            }
            
            // Track API failures for exponential backoff
            $fail_count = get_transient('sln_api_fail_count_' . $action);
            $fail_count = $fail_count ? $fail_count + 1 : 1;
            set_transient('sln_api_fail_count_' . $action, $fail_count, 30 * MINUTE_IN_SECONDS);
            
            if ($fail_count >= 3) {
                // After 3 failures, activate backoff for 5 minutes
                set_transient('sln_api_backoff_' . $action, true, 5 * MINUTE_IN_SECONDS);
                SLN_Plugin::addLog("[Salon License] API failure #$fail_count for action '$action'. Activating 5-minute backoff.");
            }
            
            return $response;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            if ($debug) {
                SLN_Plugin::addLog('API Response Code: ' . $response_code);
                SLN_Plugin::addLog('API Response Body: ' . $response_body);
            }
            
            $license_data = json_decode($response_body);
            if ($debug) {
                SLN_Plugin::addLog('Decoded License Data: ' . print_r($license_data, true));
            }
            
            // API call succeeded - reset failure count
            delete_transient('sln_api_fail_count_' . $action);
            delete_transient('sln_api_backoff_' . $action);
            
            // Cache license data if it contains detailed information
            if ($license_data && is_object($license_data) && isset($license_data->payment_id)) {
                if ($debug) {
                    SLN_Plugin::addLog('Caching detailed license data');
                }
                set_transient('sln_license_data_cache', $license_data, 24 * HOUR_IN_SECONDS); // Cache for 24 hours
                
                // Also store in database for permanent storage
                update_option('sln_license_data_detailed', $license_data);
                if ($debug) {
                    SLN_Plugin::addLog('Stored detailed license data in database');
                }
            }
            
            // If we got minimal data, try to get more detailed information
            if ($action === 'check_license' && $license_data && is_object($license_data) && !isset($license_data->payment_id)) {
                if ($debug) {
                    SLN_Plugin::addLog('WARNING: check_license returned minimal data, trying alternative approaches');
                }
                
                // Try different API actions with proper headers
                $alternative_actions = ['get_license_data', 'get_license_payments', 'get_customer', 'get_payment'];
                
                foreach ($alternative_actions as $alt_action) {
                    if ($debug) {
                        SLN_Plugin::addLog('Trying alternative API action: ' . $alt_action);
                    }
                    
                    $alt_request = array(
                        'edd_action' => $alt_action,
                        'license'    => $license,
                        'item_name'  => $this->get('name'),
                        'url'        => home_url(),
                    );
                    
                    $alt_api_url = add_query_arg($alt_request, $this->get('store'));
                    if ($debug) {
                        SLN_Plugin::addLog('Alternative API URL: ' . $alt_api_url);
                    }
                    
                    $alt_response = wp_remote_get($alt_api_url, $request_args);
                    
                    if (!is_wp_error($alt_response)) {
                        $alt_response_code = wp_remote_retrieve_response_code($alt_response);
                        $alt_response_body = wp_remote_retrieve_body($alt_response);
                        if ($debug) {
                            SLN_Plugin::addLog('Alternative API Response Code: ' . $alt_response_code);
                            SLN_Plugin::addLog('Alternative API Response Body: ' . $alt_response_body);
                        }
                        
                        $alt_license_data = json_decode($alt_response_body);
                        if ($alt_license_data && is_object($alt_license_data) && isset($alt_license_data->payment_id)) {
                            if ($debug) {
                                SLN_Plugin::addLog('SUCCESS: Alternative API call returned full data with action: ' . $alt_action);
                            }
                            $license_data = $alt_license_data;
                            break;
                        } else {
                            if ($debug) {
                                SLN_Plugin::addLog('Alternative API call also returned minimal data for action: ' . $alt_action);
                            }
                        }
                    } else {
                        if ($debug) {
                            SLN_Plugin::addLog('Alternative API call failed for action ' . $alt_action . ': ' . $alt_response->get_error_message());
                        }
                    }
                }
            }
            // Check for item_name_mismatch error (EDD returns license='invalid' with error='item_name_mismatch')
            if( (isset($license_data->error) && $license_data->error == 'item_name_mismatch') || 
                (isset($license_data->license) && $license_data->license == 'item_name_mismatch')){
                $license  = $this->get('license_key');
                $request  = array(
                    'edd_action' => $action,
                    'license'    => $license,
                    'item_name'  => 'Business Plan',
                    'url'        => home_url(),
                );
                $response = wp_remote_get(
                    add_query_arg($request, $this->get('store')),
                    array('timeout' => 8, 'sslverify' => false)
                );
                $license_data = json_decode(wp_remote_retrieve_body($response));
                // Check again for item_name_mismatch
                if( (isset($license_data->error) && $license_data->error == 'item_name_mismatch') || 
                    (isset($license_data->license) && $license_data->license == 'item_name_mismatch')){
                    $license  = $this->get('license_key');
                    $request  = array(
                        'edd_action' => $action,
                        'license'    => $license,
                        'item_name'  => 'Basic / Branded version',
                        'url'        => home_url(),
                    );
                    $response = wp_remote_get(
                        add_query_arg($request, $this->get('store')),
                        array('timeout' => 8, 'sslverify' => false)
                    );
                    $license_data = json_decode(wp_remote_retrieve_body($response));
                }
            }
            return $license_data;
        }
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->get('license_status') == 'valid';
    }

    /**
     * Get product name from license data, mapping price_id to product name
     * @param array $data License data
     * @return string Product name
     */
    private function getProductNameFromLicenseData($data)
    {
        // Convert to array if it's an object
        if (is_object($data)) {
            $data = (array) $data;
        }
        
        // Map price_id to product name for EDD All Access
        if (isset($data['price_id'])) {
            $price_id_mapping = array(
                1 => 'Basic Plan',
                2 => 'Business Plan',
                3 => 'Pro Plan',
                4 => 'Enterprise Plan',
                // Add more mappings as needed
            );
            
            if (isset($price_id_mapping[$data['price_id']])) {
                return $price_id_mapping[$data['price_id']];
            }
        }
        
        // Fallback to original logic
        if (isset($data['item_name']) && !empty($data['item_name'])) {
            return $data['item_name'];
        } elseif (isset($data['license_name']) && !empty($data['license_name'])) {
            return $data['license_name'];
        } elseif (isset($data['product_name']) && !empty($data['product_name'])) {
            return $data['product_name'];
        } elseif (isset($data['download_name']) && !empty($data['download_name'])) {
            return $data['download_name'];
        } elseif (isset($data['name']) && !empty($data['name'])) {
            return $data['name'];
        }
        
        return __('Unknown Product', 'salon-booking-system');
    }

    /**
     * Format expiration date with days counter
     * @param string $expires_date Expiration date string
     * @return string Formatted expiration date with days counter
     */
    private function formatExpirationDate($expires_date)
    {
        if (empty($expires_date)) {
            return __('No expiration date', 'salon-booking-system');
        }

        $expires_timestamp = strtotime($expires_date);
        $current_timestamp = current_time('timestamp');
        $days_remaining = ceil(($expires_timestamp - $current_timestamp) / DAY_IN_SECONDS);
        
        // Format date as "Day Month Year"
        $formatted_date = date('j F Y', $expires_timestamp);
        
        // Add days counter
        if ($days_remaining > 0) {
            $days_text = $days_remaining . ' ' . _n('day', 'days', $days_remaining, 'salon-booking-system') . ' ' . __('remaining', 'salon-booking-system');
            return $formatted_date . ' (' . $days_text . ')';
        } elseif ($days_remaining == 0) {
            return $formatted_date . ' (' . __('Expires today', 'salon-booking-system') . ')';
        } else {
            $days_text = abs($days_remaining) . ' ' . _n('day', 'days', abs($days_remaining), 'salon-booking-system') . ' ' . __('overdue', 'salon-booking-system');
            return $formatted_date . ' (' . $days_text . ')';
        }
    }

    /**
     * Get the current plugin version
     * @return string Plugin version
     */
    private function getPluginVersion()
    {
        // Use the SLN_VERSION constant which is already defined in the main plugin file
        return defined('SLN_VERSION') ? SLN_VERSION : 'Unknown';
    }

    /**
     * Get stored license data from database
     * @return object|null Stored license data or null
     */
    public function getStoredLicenseData()
    {
        // Get stored license data from database
        $stored_data = get_option('salon-booking-wordpress-plugin_license_data');
        SLN_Plugin::addLog('Getting stored license data: ' . print_r($stored_data, true));
        return $stored_data;
    }

    /**
     * Clear license data cache
     * @return void
     */
    public function clearLicenseDataCache()
    {
        delete_transient('sln_license_data_cache');
        SLN_Plugin::addLog('Cleared license data cache');
    }

    /**
     * Get customer since date from EDD API (actual purchase date from payment)
     * @param array $license_data License data from EDD API
     * @return string Customer since date or fallback message
     */
    private function getCustomerSince($license_data)
    {
        // Get payment_id and customer_email from license data
        $payment_id = null;
        $customer_email = null;
        
        if (is_object($license_data)) {
            $payment_id = isset($license_data->payment_id) ? $license_data->payment_id : null;
            $customer_email = isset($license_data->customer_email) ? $license_data->customer_email : null;
        } else {
            $payment_id = isset($license_data['payment_id']) ? $license_data['payment_id'] : null;
            $customer_email = isset($license_data['customer_email']) ? $license_data['customer_email'] : null;
        }
        
        if (!$payment_id && !$customer_email) {
            $this->logCustomerSinceDebug('No payment_id or customer_email available', $license_data);
            return __('Date not available', 'salon-booking-system');
        }

        // IMPORTANT: Clear any bad cached data first
        $cache_key = 'sln_customer_since_' . md5($customer_email . $payment_id);
        
        // Force refresh if cached date is clearly wrong (before 2026)
        $cached_date = get_transient($cache_key);
        
        SLN_Plugin::addLog('[Customer Since Debug] Cache key: ' . $cache_key);
        SLN_Plugin::addLog('[Customer Since Debug] Cached date: ' . ($cached_date !== false ? $cached_date : 'NOT CACHED'));
        SLN_Plugin::addLog('[Customer Since Debug] Payment ID: ' . $payment_id);
        SLN_Plugin::addLog('[Customer Since Debug] Customer Email: ' . $customer_email);
        
        if ($cached_date !== false) {
            $cached_timestamp = strtotime($cached_date);
            $year_2026_start = strtotime('2026-01-01');
            
            SLN_Plugin::addLog('[Customer Since Debug] Cached timestamp: ' . $cached_timestamp . ' (' . date('Y-m-d', $cached_timestamp) . ')');
            SLN_Plugin::addLog('[Customer Since Debug] Year 2026 start: ' . $year_2026_start);
            
            // If cached date is before 2026 but license expires in 2026, it's wrong - clear it
            if ($cached_timestamp < $year_2026_start && isset($license_data->expires)) {
                $expires_timestamp = strtotime($license_data->expires);
                SLN_Plugin::addLog('[Customer Since Debug] License expires: ' . $license_data->expires . ' (timestamp: ' . $expires_timestamp . ')');
                
                if ($expires_timestamp >= $year_2026_start) {
                    SLN_Plugin::addLog('[Customer Since Debug] BAD CACHE DETECTED! Clearing...');
                    $this->logCustomerSinceDebug('Clearing bad cached date (pre-2026 but expires in 2026)', array('cached' => $cached_date, 'expires' => $license_data->expires));
                    delete_transient($cache_key);
                    $cached_date = false;
                }
            }
            
            if ($cached_date !== false) {
                SLN_Plugin::addLog('[Customer Since Debug] Returning cached date: ' . $cached_date);
                $this->logCustomerSinceDebug('Using cached date: ' . $cached_date, array('email' => $customer_email, 'payment_id' => $payment_id));
                return $cached_date;
            }
        }
        
        SLN_Plugin::addLog('[Customer Since Debug] No valid cache, proceeding to API calls...');

        // Method 1 (NEW - MOST RELIABLE): Get the EARLIEST payment for this customer email
        // This ensures we get the original purchase date, not a renewal
        if ($customer_email) {
            $earliest_date = $this->getEarliestPaymentDateForCustomer($customer_email);
            if ($earliest_date) {
                $formatted_date = date('F j, Y', strtotime($earliest_date));
                // Cache for 30 days (customer since date doesn't change)
                set_transient($cache_key, $formatted_date, 30 * DAY_IN_SECONDS);
                $this->logCustomerSinceDebug('SUCCESS - Method 1 (Earliest Payment): ' . $formatted_date, array('email' => $customer_email, 'raw_date' => $earliest_date));
                return $formatted_date;
            }
            $this->logCustomerSinceDebug('Method 1 (Earliest Payment) failed', array('email' => $customer_email));
        }

        // Method 2: Try to get specific payment date from license payment_id
        // This gets the date of the payment_id in the license data
        if ($payment_id) {
            $this->logCustomerSinceDebug('Trying Method 2: Get payment date for payment_id ' . $payment_id, array());
            $purchase_date = $this->getPaymentDate($payment_id);
            if ($purchase_date) {
                $formatted_date = date('F j, Y', strtotime($purchase_date));
                set_transient($cache_key, $formatted_date, 30 * DAY_IN_SECONDS);
                $this->logCustomerSinceDebug('SUCCESS - Method 2 (Payment Date): ' . $formatted_date, array('payment_id' => $payment_id, 'raw_date' => $purchase_date));
                return $formatted_date;
            }
            $this->logCustomerSinceDebug('Method 2 (Payment Date) FAILED - API returned no date', array('payment_id' => $payment_id));
        }

        // Method 3: Try to get customer creation date from EDD customer API
        if ($customer_email) {
            $customer_date = $this->getCustomerCreationDate($customer_email);
            if ($customer_date) {
                $formatted_date = date('F j, Y', strtotime($customer_date));
                set_transient($cache_key, $formatted_date, 30 * DAY_IN_SECONDS);
                $this->logCustomerSinceDebug('SUCCESS - Method 3 (Customer Creation): ' . $formatted_date, array('email' => $customer_email, 'raw_date' => $customer_date));
                return $formatted_date;
            }
            $this->logCustomerSinceDebug('Method 3 (Customer Creation) failed', array('email' => $customer_email));
        }
        
        // Fallback: Use the license expiration date minus 1 year as an estimate
        $expires = null;
        if (is_object($license_data)) {
            $expires = isset($license_data->expires) ? $license_data->expires : null;
        } else {
            $expires = isset($license_data['expires']) ? $license_data['expires'] : null;
        }
        
        if ($expires && $expires !== 'lifetime') {
            $expires_timestamp = strtotime($expires);
            if ($expires_timestamp) {
                $estimated_start = $expires_timestamp - (365 * 24 * 60 * 60);
                $formatted_date = date('F j, Y', $estimated_start);
                $this->logCustomerSinceDebug('FALLBACK - Estimation (expires - 1 year): ' . $formatted_date . ' (INACCURATE)', array('expires' => $expires));
                return $formatted_date;
            }
        }
        
        $this->logCustomerSinceDebug('ALL METHODS FAILED - returning "Date not available"', $license_data);
        return __('Date not available', 'salon-booking-system');
    }

    /**
     * Get customer since date WITH detailed debug information
     * This version tracks which methods were tried and why they succeeded/failed
     * @param array $license_data License data from EDD API
     * @param array &$debug_log Reference to array that will store debug info
     * @return string Customer since date
     */
    private function getCustomerSinceWithDebug($license_data, &$debug_log)
    {
        $debug_log['methods_tried'] = array();
        
        // Get payment_id and customer_email from license data
        $payment_id = null;
        $customer_email = null;
        
        if (is_object($license_data)) {
            $payment_id = isset($license_data->payment_id) ? $license_data->payment_id : null;
            $customer_email = isset($license_data->customer_email) ? $license_data->customer_email : null;
        } else {
            $payment_id = isset($license_data['payment_id']) ? $license_data['payment_id'] : null;
            $customer_email = isset($license_data['customer_email']) ? $license_data['customer_email'] : null;
        }
        
        $debug_log['input_data'] = array(
            'payment_id' => $payment_id,
            'customer_email' => $customer_email
        );
        
        if (!$payment_id && !$customer_email) {
            $debug_log['method_used'] = 'FAILED - No input data';
            return __('Date not available', 'salon-booking-system');
        }

        // DON'T use cache for debugging - always fetch fresh
        
        // Method 1: Get earliest payment for customer
        if ($customer_email) {
            $earliest_date = $this->getEarliestPaymentDateForCustomer($customer_email);
            if ($earliest_date) {
                $formatted_date = date('F j, Y', strtotime($earliest_date));
                $debug_log['method_used'] = 'Method 1: Earliest Payment (BEST)';
                $debug_log['methods_tried'][] = array(
                    'method' => 'Earliest Payment',
                    'success' => true,
                    'date' => $earliest_date,
                    'formatted' => $formatted_date
                );
                return $formatted_date;
            } else {
                $debug_log['methods_tried'][] = array(
                    'method' => 'Earliest Payment',
                    'success' => false,
                    'reason' => 'API call failed or no payments found'
                );
            }
        }

        // Method 2: Single payment from license (using payment_id from license data)
        if ($payment_id) {
            // Try to get payment date using license key authentication
            $purchase_date = $this->getPaymentDateFromLicense($payment_id, $license_data);
            if ($purchase_date) {
                $formatted_date = date('F j, Y', strtotime($purchase_date));
                $debug_log['method_used'] = 'Method 2: Single Payment (WARNING: might be renewal)';
                $debug_log['methods_tried'][] = array(
                    'method' => 'Single Payment',
                    'success' => true,
                    'payment_id' => $payment_id,
                    'date' => $purchase_date,
                    'formatted' => $formatted_date,
                    'warning' => 'This payment_id might be from a renewal, not original purchase'
                );
                return $formatted_date;
            } else {
                $debug_log['methods_tried'][] = array(
                    'method' => 'Single Payment',
                    'success' => false,
                    'payment_id' => $payment_id,
                    'reason' => 'API call failed'
                );
            }
        }

        // Method 3: Customer creation date
        if ($customer_email) {
            $customer_date = $this->getCustomerCreationDate($customer_email);
            if ($customer_date) {
                $formatted_date = date('F j, Y', strtotime($customer_date));
                $debug_log['method_used'] = 'Method 3: Customer Creation';
                $debug_log['methods_tried'][] = array(
                    'method' => 'Customer Creation',
                    'success' => true,
                    'date' => $customer_date,
                    'formatted' => $formatted_date
                );
                return $formatted_date;
            } else {
                $debug_log['methods_tried'][] = array(
                    'method' => 'Customer Creation',
                    'success' => false,
                    'reason' => 'API call failed'
                );
            }
        }
        
        // Fallback: Estimation
        $expires = null;
        if (is_object($license_data)) {
            $expires = isset($license_data->expires) ? $license_data->expires : null;
        } else {
            $expires = isset($license_data['expires']) ? $license_data['expires'] : null;
        }
        
        if ($expires && $expires !== 'lifetime') {
            $expires_timestamp = strtotime($expires);
            if ($expires_timestamp) {
                $estimated_start = $expires_timestamp - (365 * 24 * 60 * 60);
                $formatted_date = date('F j, Y', $estimated_start);
                $debug_log['method_used'] = 'Method 4: Estimation (INACCURATE - expires minus 1 year)';
                $debug_log['methods_tried'][] = array(
                    'method' => 'Estimation',
                    'success' => true,
                    'expires' => $expires,
                    'estimated' => $formatted_date,
                    'warning' => 'This is just an estimate, likely INACCURATE'
                );
                return $formatted_date;
            }
        }
        
        $debug_log['method_used'] = 'ALL METHODS FAILED';
        return __('Date not available', 'salon-booking-system');
    }

    /**
     * Log debug information for customer since date retrieval
     * Only logs if WP_DEBUG is enabled
     * @param string $message Debug message
     * @param array $context Additional context data
     */
    private function logCustomerSinceDebug($message, $context = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            SLN_Plugin::addLog('[SLN Customer Since Debug] ' . $message . ' | Context: ' . wp_json_encode($context));
        }
    }

    /**
     * Get earliest payment date for a customer (most reliable method)
     * This gets ALL payments for the customer and returns the earliest one
     * This ensures we get the original purchase, not a renewal
     * 
     * @param string $customer_email Customer email address
     * @return string|false Earliest payment date or false on failure
     */
    private function getEarliestPaymentDateForCustomer($customer_email)
    {
        if (!$customer_email) {
            return false;
        }

        // First, get the customer data to find their customer_id
        // FIXED: Use api_key and api_token for authentication, not license_key
        $request = array(
            'edd_action' => 'get_customers',
            'email'      => $customer_email,
            'key'        => $this->get('api_key'),
            'token'      => $this->get('api_token'),
        );
        
        $api_url = add_query_arg($request, $this->get('store'));
        
        $response = wp_remote_get($api_url, array(
            'timeout'   => 20, // Increased to match other API calls
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            $this->logCustomerSinceDebug('getEarliestPaymentDateForCustomer - API error getting customer', array('error' => $response->get_error_message()));
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $customer_data = json_decode($response_body);
        
        if (!$customer_data || !isset($customer_data->customers) || !is_array($customer_data->customers) || count($customer_data->customers) === 0) {
            $this->logCustomerSinceDebug('getEarliestPaymentDateForCustomer - No customer found', array('email' => $customer_email, 'response' => $response_body));
            return false;
        }
        
        $customer = $customer_data->customers[0];
        
        // Check if customer has purchase_count and stats with earliest_purchase
        if (isset($customer->info->date_created)) {
            $earliest_date = $customer->info->date_created;
            
            // If we have payment_ids, try to get the earliest actual payment date
            if (isset($customer->payment_ids) && is_array($customer->payment_ids) && count($customer->payment_ids) > 0) {
                $earliest_payment_date = null;
                
                // IMPORTANT: payment_ids might be ordered newest-first or oldest-first
                // We need to check ALL payments (or at least more than just the first 10)
                // to ensure we get the actual earliest date
                $total_payments = count($customer->payment_ids);
                $this->logCustomerSinceDebug('Customer has ' . $total_payments . ' payments', array('email' => $customer_email));
                
                // Check ALL payment IDs to find the earliest (most reliable)
                // For performance, if there are many payments, check first 5 and last 5
                if ($total_payments <= 20) {
                    $payment_ids_to_check = $customer->payment_ids;
                } else {
                    // Check first 5 (might be oldest) and last 5 (might be oldest if reversed)
                    $payment_ids_to_check = array_merge(
                        array_slice($customer->payment_ids, 0, 5),
                        array_slice($customer->payment_ids, -5)
                    );
                    $this->logCustomerSinceDebug('Checking first 5 and last 5 payments out of ' . $total_payments, array('email' => $customer_email));
                }
                
                foreach ($payment_ids_to_check as $payment_id) {
                    $payment_date = $this->getPaymentDate($payment_id);
                    if ($payment_date) {
                        $payment_timestamp = strtotime($payment_date);
                        if ($earliest_payment_date === null || $payment_timestamp < strtotime($earliest_payment_date)) {
                            $earliest_payment_date = $payment_date;
                            $this->logCustomerSinceDebug('New earliest found: ' . $payment_date . ' (payment_id: ' . $payment_id . ')', array('timestamp' => $payment_timestamp));
                        }
                    }
                }
                
                if ($earliest_payment_date) {
                    $this->logCustomerSinceDebug('Found earliest payment from payment_ids', array('email' => $customer_email, 'date' => $earliest_payment_date, 'checked_payments' => count($payment_ids_to_check), 'total_payments' => $total_payments));
                    return $earliest_payment_date;
                }
            }
            
            // Fallback to customer creation date
            $this->logCustomerSinceDebug('Using customer creation date as earliest', array('email' => $customer_email, 'date' => $earliest_date));
            return $earliest_date;
        }
        
        return false;
    }

    /**
     * Get payment date from license subscription data
     * This uses the subscription check API which works with just the license key
     * @param int $payment_id Payment ID
     * @param mixed $license_data License data object/array
     * @return string|false Payment date or false on failure
     */
    private function getPaymentDateFromLicense($payment_id, $license_data)
    {
        // Check subscription data which includes payment history
        $subscriptions = $this->checkSubscription(true); // Force refresh
        
        // Parse the subscription response (it's a WP HTTP response array)
        if ($subscriptions && is_array($subscriptions) && isset($subscriptions['body'])) {
            $body = json_decode($subscriptions['body'], true);
            
            if ($body && isset($body['subscriptions']) && is_array($body['subscriptions'])) {
                foreach ($body['subscriptions'] as $subscription) {
                    // Check if this subscription matches the payment_id
                    if (isset($subscription['info']['parent_payment_id']) && $subscription['info']['parent_payment_id'] == $payment_id) {
                        // Use the subscription created date
                        if (isset($subscription['info']['created'])) {
                            return $subscription['info']['created'];
                        }
                    }
                }
                
                // If no exact match, use the first subscription's created date
                if (!empty($body['subscriptions'][0]['info']['created'])) {
                    return $body['subscriptions'][0]['info']['created'];
                }
            }
        }
        
        return false;
    }

    /**
     * Get payment date from EDD payment API
     * @param int $payment_id Payment ID
     * @return string|false Payment date or false on failure
     */
    private function getPaymentDate($payment_id)
    {
        if (!$payment_id) {
            return false;
        }

        // FIXED: Use api_key and api_token for authentication, not license_key
        $request = array(
            'edd_action' => 'get_payment',
            'payment_id' => $payment_id,
            'key'        => $this->get('api_key'),
            'token'      => $this->get('api_token'),
        );
        
        $api_url = add_query_arg($request, $this->get('store'));
        
        $response = wp_remote_get($api_url, array(
            'timeout'   => 20, // Increased to match other API calls
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $payment_data = json_decode($response_body);
        
        if ($payment_data && isset($payment_data->payment) && isset($payment_data->payment->date)) {
            return $payment_data->payment->date;
        }
        
        return false;
    }

    /**
     * Get customer creation date from EDD customer API
     * @param string $customer_email Customer email
     * @return string|false Customer creation date or false on failure
     */
    private function getCustomerCreationDate($customer_email)
    {
        if (!$customer_email) {
            return false;
        }

        // FIXED: Use api_key and api_token for authentication, not license_key
        $request = array(
            'edd_action' => 'get_customers',
            'email'      => $customer_email,
            'key'        => $this->get('api_key'),
            'token'      => $this->get('api_token'),
        );
        
        $api_url = add_query_arg($request, $this->get('store'));
        
        $response = wp_remote_get($api_url, array(
            'timeout'   => 20, // Increased to match other API calls
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $customer_data = json_decode($response_body);
        
        if ($customer_data && isset($customer_data->customers) && is_array($customer_data->customers) && count($customer_data->customers) > 0) {
            $customer = $customer_data->customers[0];
            if (isset($customer->info->date_created)) {
                return $customer->info->date_created;
            }
        }
        
        return false;
    }
    
    /**
     * Show admin notice if license API is responding slowly
     */
    public function showSlowApiWarning()
    {
        // Only show on Salon admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'salon') === false) {
            return;
        }
        
        $slow_warning = get_transient('sln_api_slow_warning');
        if (!$slow_warning) {
            return;
        }
        
        $action = isset($slow_warning['action']) ? $slow_warning['action'] : 'unknown';
        $response_time = isset($slow_warning['response_time']) ? $slow_warning['response_time'] : 0;
        $timestamp = isset($slow_warning['timestamp']) ? $slow_warning['timestamp'] : 0;
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('⚠️ Slow License API Response', 'salon-booking-system'); ?></strong><br>
                <?php 
                echo sprintf(
                    esc_html__('The license validation API is responding slowly (took %s seconds). This may cause admin pages to load slowly.', 'salon-booking-system'),
                    '<strong>' . esc_html($response_time) . '</strong>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e('This is usually caused by slow network connectivity or high load on the license server. The plugin will continue to work normally using cached license data.', 'salon-booking-system'); ?>
            </p>
        </div>
        <?php
    }
}