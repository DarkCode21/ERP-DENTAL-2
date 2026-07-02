<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Update_Page
{
    /** @var SLN_Update_Manager */
    private $updater;
    private $pageSlug;
    private $pageName;

    public function __construct(SLN_Update_Manager $updater)
    {
        $this->updater  = $updater;
        $this->pageName = $this->updater->get('name').' License';
        $this->pageSlug = $this->updater->get('slug').'-license';
        add_plugins_page($this->pageName, $this->pageName, 'manage_options', $this->pageSlug, array($this, 'render'));
        add_action('admin_notices', array($this, 'hook_admin_notices'));
    }

    public function hook_admin_notices()
    {
        if (!$this->updater->isValid() && (empty($_GET['page']) || $_GET['page'] != $this->pageSlug)) {
            $licenseUrl = admin_url('/plugins.php?page='.$this->pageSlug);
            ?>
            <div id="sln-setting-error" class="updated error">
                <h3><?php echo esc_html($this->updater->get('name')).esc_html__(' needs a valid license', 'salon-booking-system') ?></h3>
                <p><a href="<?php echo esc_url($licenseUrl); ?>"><?php _e('<p>Please insert your license key', 'salon-booking-system'); ?></a>
                </p>
            </div>
            <?php
        }
    }

    public function render()
    {
        // Refresh only if data is stale (> 60 minutes) to avoid slow remote calls on each load
        $lastChecked = (int) $this->updater->get('license_last_checked');
        $isStale = !$lastChecked || (time() - $lastChecked) > HOUR_IN_SECONDS;
        if ($isStale) {
            $this->updater->checkLicense();
            $this->refreshLicenseIfNeeded();
        }

        if (isset($_POST['submit']) && isset($_POST['license_key'])) {
            $license_key = sanitize_text_field($_POST['license_key']);
            $response = $this->updater->activateLicense($license_key);
            if (is_wp_error($response)) {
                ?>
                <div id="sln-setting-error" class="updated error">
                    <p><?php echo esc_html('ERROR: '.$response->get_error_code().' - '.$response->get_error_message()); ?></p>
                </div>
                <?php
            } else {
                // Check the actual license status to determine the appropriate message
                $license_status = $this->updater->get('license_status');
                if ($license_status === 'valid') {
                    ?>
                    <div id="sln-setting-error" class="updated success">
                        <p><?php echo esc_html__('License activated successfully', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'site_inactive') {
                    ?>
                    <div id="sln-setting-error" class="updated notice notice-warning">
                        <p><?php echo esc_html__('License is valid but already active on another site. You may need to deactivate it from the other site first.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'inactive') {
                    ?>
                    <div id="sln-setting-error" class="updated notice notice-warning">
                        <p><?php echo esc_html__('License is valid but inactive. Please check your license status.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'expired') {
                    ?>
                    <div id="sln-setting-error" class="updated error">
                        <p><?php echo esc_html__('License has expired. Please renew your license.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'disabled') {
                    ?>
                    <div id="sln-setting-error" class="updated error">
                        <p><?php echo esc_html__('License has been disabled. Please contact support.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'invalid') {
                    ?>
                    <div id="sln-setting-error" class="updated error">
                        <p><?php echo esc_html__('Invalid license key. Please check your license key.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'over_activated') {
                    ?>
                    <div id="sln-setting-error" class="updated notice notice-warning">
                        <p><?php echo esc_html__('License is over-activated. You have more sites using this license than allowed. Please deactivate it from some sites first.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'already_active') {
                    ?>
                    <div id="sln-setting-error" class="updated success">
                        <p><?php echo esc_html__('License is already active on this site.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } elseif ($license_status === 'no_activations_left') {
                    ?>
                    <div id="sln-setting-error" class="updated notice notice-warning">
                        <p><?php echo esc_html__('License has reached its activation limit. You may need to deactivate it from another site first.', 'salon-booking-system') ?></p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div id="sln-setting-error" class="updated notice notice-warning">
                        <p><?php echo esc_html__('License status: ', 'salon-booking-system') . esc_html($license_status) ?></p>
                    </div>
                    <?php
                }
            }
        }
        if (isset($_POST['license_deactivate'])) {
            $response = $this->updater->deactivateLicense();
            if (is_wp_error($response)) {
                ?>
                <div id="sln-setting-error" class="updated error">
                    <p><?php echo esc_html($response->get_error_code().' - '.$response->get_error_message()); ?></p>
                </div>
                <?php
            } else {

                ?>
                <div id="sln-setting-error" class="updated success">
                    <p><?php echo esc_html__('License deactivated with success', 'salon-booking-system') ?></p>
                </div>
                <?php
            }
        }
        $license = $this->updater->get('license_key');
        $status  = $this->updater->get('license_status');
        $data    = $this->updater->get('license_data');
        
        // For debugging - get customer_since with detailed debug info
        $debug_log = array();
        if ($data && is_object($data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Use reflection to call the debug version
                $reflection = new ReflectionClass($this->updater);
                $method = $reflection->getMethod('getCustomerSinceWithDebug');
                $method->setAccessible(true);
                $customer_since = $method->invokeArgs($this->updater, array($data, &$debug_log));
                $data->customer_since = $customer_since;
            } elseif (empty($data->customer_since)) {
                // Regular method for non-debug mode
                $reflection = new ReflectionClass($this->updater);
                $method = $reflection->getMethod('getCustomerSince');
                $method->setAccessible(true);
                $customer_since = $method->invoke($this->updater, $data);
                $data->customer_since = $customer_since;
            }
        }
        
        ?>
        <div class="wrap">
        <h2><?php echo $this->pageName ?></h2>
        
        <form method="post" action="?page=<?php echo $this->pageSlug ?>">
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row" valign="top">
                        <?php esc_html_e('License Key', 'salon-booking-system'); ?>
                    </th>
                    <td>
                        <input id="license_key" name="license_key" type="text" class="regular-text"
                               required="required"
                               value="<?php esc_attr_e($license); ?>"/>
                        <?php if (empty($license)): ?>
                            <label class="description" for="license_key"><?php esc_html_e(
                                    'Enter your license key',
                                    'salon-booking-system'
                                ); ?></label>
                        <?php else: ?>
                            <button type="button" id="refresh-all-license-data" class="button button-primary" style="margin-left: 10px;">
                                <?php esc_html_e('Refresh All License Data', 'salon-booking-system'); ?>
                            </button>
                            <button type="button" id="clear-license-status" class="button button-secondary" style="margin-left: 5px;">
                                <?php esc_html_e('Clear & Reset License', 'salon-booking-system'); ?>
                            </button>
                            <br>
                            <small style="display: block; margin-left: 10px; margin-top: 3px; color: #666;">
                                <?php esc_html_e('Click "Refresh All" to update license status, subscription, and customer data', 'salon-booking-system'); ?>
                            </small>
                        <?php endif ?>
                    </td>
                </tr>
                <?php if ($license) { ?>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('License State', 'salon-booking-system'); ?>
                        </th>
                        <td class="status">
                            <?php if ($status == 'valid') { ?>
                                <span class="title" style="color:green;position: relative; top: -10px;"><?php esc_html_e('active', 'salon-booking-system'); ?></span>
                                <?php wp_nonce_field('nonce', 'nonce'); ?>&nbsp;
                                <input type="submit" class="button-secondary" name="license_deactivate"
                                       value="<?php esc_html_e('Deactivate License', 'salon-booking-system'); ?>"/>
                            <?php } elseif ($status == 'invalid') { ?>
                                <span style="color:red;"><?php esc_html_e('invalid', 'salon-booking-system'); ?></span>
                            <?php } elseif ($status == 'expired') { ?>
                                <span style="color:red;"><?php esc_html_e('expired', 'salon-booking-system'); ?></span>
                            <?php } elseif ($status == 'inactive') { ?>
                                <span style="color:orange;"><?php esc_html_e('inactive', 'salon-booking-system'); ?></span>
                            <?php } elseif ($status == 'site_inactive') { ?>
                                <span style="color:orange;"><?php esc_html_e('site inactive', 'salon-booking-system'); ?></span>
                                <br><small><?php esc_html_e('License is valid but not activated on this site. You may need to deactivate it from another site first.', 'salon-booking-system'); ?></small>
                            <?php } elseif ($status == 'disabled') { ?>
                                <span style="color:red;"><?php esc_html_e('disabled', 'salon-booking-system'); ?></span>
                            <?php } elseif ($status == 'over_activated') { ?>
                                <span style="color:orange;"><?php esc_html_e('over activated', 'salon-booking-system'); ?></span>
                                <br><small><?php esc_html_e('License is being used on more sites than allowed. Please deactivate it from some sites first.', 'salon-booking-system'); ?></small>
                            <?php } else { ?>
                                <span style="color:orange;">
                                    <?php 
                                    // Check if status contains error message
                                    if (strpos($status, 'error') !== false || strpos($status, 'Error') !== false) {
                                        esc_html_e('error', 'salon-booking-system');
                                    } else {
                                        esc_html_e('unknown', 'salon-booking-system');
                                    }
                                    ?>
                                    <?php if ($status && $status !== 'error' && $status !== 'unknown'): ?>
                                        <br><small><?php echo esc_html($status); ?></small>
                                    <?php endif; ?>
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Payment id', 'salon-booking-system'); ?>
                        </th>
                        <td class="payment_id" >
                            <?php echo isset($data->payment_id) ? esc_html($data->payment_id) : esc_html__('Not available', 'salon-booking-system'); ?>
                        </td>
                    </tr>
                    <tr valign="top" >
                        <th scope="row" valign="top">
                            <?php esc_html_e('Customer name', 'salon-booking-system'); ?>
                        </th>
                        <td class="customer_name">
                            <?php echo isset($data->customer_name) ? esc_html($data->customer_name) : esc_html__('Not available', 'salon-booking-system'); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Customer email', 'salon-booking-system'); ?>
                        </th>
                        <td  class="customer_email">
                            <?php echo isset($data->customer_email) ? esc_html($data->customer_email) : esc_html__('Not available', 'salon-booking-system'); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Customer since', 'salon-booking-system'); ?>
                        </th>
                        <td class="customer_since">
                            <?php 
                            if (isset($data->customer_since) && !empty($data->customer_since)) {
                                echo esc_html($data->customer_since);
                            } else {
                                echo esc_html__('Not available', 'salon-booking-system');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Product Name', 'salon-booking-system'); ?>
                        </th>
                        <td class="product_name">
                            <?php echo esc_html($this->getProductName($data)); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Expires', 'salon-booking-system'); ?>
                        </th>
                        <td class="expires">
                            <?php 
                            if (!empty($data->expires)) {
                                $expires_timestamp = strtotime($data->expires);
                                $current_timestamp = current_time('timestamp');
                                $days_remaining = ceil(($expires_timestamp - $current_timestamp) / DAY_IN_SECONDS);
                                
                                // Format date as "Day Month Year"
                                $formatted_date = date('j F Y', $expires_timestamp);
                                
                                echo esc_html($formatted_date);
                                
                                // Add days counter
                                if ($days_remaining > 0) {
                                    echo ' <span style="color: #0073aa; font-weight: bold;">(' . $days_remaining . ' ' . 
                                         _n('day', 'days', $days_remaining, 'salon-booking-system') . ' ' . 
                                         esc_html__('remaining', 'salon-booking-system') . ')</span>';
                                } elseif ($days_remaining == 0) {
                                    echo ' <span style="color: #d63638; font-weight: bold;">(' . esc_html__('Expires today', 'salon-booking-system') . ')</span>';
                                } else {
                                    echo ' <span style="color: #d63638; font-weight: bold;">(' . abs($days_remaining) . ' ' . 
                                         _n('day', 'days', abs($days_remaining), 'salon-booking-system') . ' ' . 
                                         esc_html__('overdue', 'salon-booking-system') . ')</span>';
                                }
                            } else {
                                echo esc_html__('No expiration date', 'salon-booking-system');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Version installed', 'salon-booking-system'); ?>
                        </th>
                        <td class="version">
                            <?php 
                            // Get the plugin version from the SLN_VERSION constant
                            $plugin_version = defined('SLN_VERSION') ? SLN_VERSION : 'Unknown';
                            
                            echo '<span style="font-weight: bold; color: #0073aa;">' . esc_html($plugin_version) . '</span>';
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Subscription Status', 'salon-booking-system'); ?>
                        </th>
                        <td class="subscription_status">
                            <?php 
                            // Get subscription status as formatted string
                            $subscription_status = $this->updater->getSubscriptionStatusString();
                            
                            // Style the status based on its value
                            $status_class = 'subscription-status';
                            if (strpos($subscription_status, 'Active') !== false) {
                                $status_class .= ' active';
                            } elseif (strpos($subscription_status, 'Not available') !== false) {
                                $status_class .= ' not-available';
                            } else {
                                $status_class .= ' inactive';
                            }
                            
                            echo '<span class="' . esc_attr($status_class) . '">' . esc_html($subscription_status) . '</span>';
                            ?>
                        </td>
                    </tr>
                    
                    <?php if (isset($data->license_limit) && isset($data->site_count) && isset($data->activations_left)): ?>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('License Usage', 'salon-booking-system'); ?>
                        </th>
                        <td class="license-usage">
                            <?php 
                            $limit = $data->license_limit;
                            $used = $data->site_count;
                            $left = $data->activations_left;
                            
                            echo sprintf(
                                __('%d of %d sites used (%d remaining)', 'salon-booking-system'),
                                $used,
                                $limit,
                                $left
                            );
                            
                            if ($left == 0) {
                                echo '<br><small style="color: #d63638;">' . esc_html__('License activation limit reached. Deactivate from another site to use on this site.', 'salon-booking-system') . '</small>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (current_user_can('update_plugins')): ?>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Plugin Actions', 'salon-booking-system'); ?>
                        </th>
                        <td class="plugin-actions">
                            <div class="sln-plugin-actions">
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('update.php?action=upgrade-plugin&plugin=' . SLN_PLUGIN_BASENAME),
                                    'upgrade-plugin_' . SLN_PLUGIN_BASENAME
                                ); ?>" class="button button-primary">
                                    <?php esc_html_e('Check for Updates', 'salon-booking-system'); ?>
                                </a>
                                
                                <!-- Debug: Check if wp_get_plugin_rollback_url exists -->
                                <?php if (function_exists('wp_get_plugin_rollback_url')): ?>
                                    <a href="<?php echo wp_get_plugin_rollback_url(SLN_PLUGIN_BASENAME); ?>" 
                                       class="button button-secondary sln-rollback-button" 
                                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to rollback? This will revert to the previous version.', 'salon-booking-system')); ?>')">
                                        <?php esc_html_e('Rollback to Previous Version', 'salon-booking-system'); ?>
                                    </a>
                                    <p><small>WordPress native rollback available</small></p>
                                <?php else: ?>
                                    <button type="button" class="button button-secondary" id="sln-show-rollback-options" onclick="toggleRollbackOptions()">
                                        <?php esc_html_e('Rollback Options', 'salon-booking-system'); ?>
                                    </button>
                                    <p><small>Custom rollback options (wp_get_plugin_rollback_url not available)</small></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!function_exists('wp_get_plugin_rollback_url')): ?>
                            <div id="sln-rollback-options" style="display: none; margin-top: 10px;">
                                <p><strong><?php esc_html_e('Available Rollback Versions:', 'salon-booking-system'); ?></strong></p>
                                <div id="sln-rollback-versions">
                                    <p><?php esc_html_e('Loading available versions...', 'salon-booking-system'); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php } ?>
                </tbody>
            </table>
            <?php if ($status != 'valid') { ?>
                <?php submit_button(__('Activate License', 'salon-booking-system')); ?>
            <?php } ?>
        </form>
        <style>
            
            /* Subscription Status Styling */
            .subscription-status {
                padding: 4px 8px;
                border-radius: 3px;
                font-weight: bold;
                font-size: 12px;
                text-transform: uppercase;
            }
            
            .subscription-status.active {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .subscription-status.inactive {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .subscription-status.not-available {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
        </style>
        <script>
            // Helper function to update license data in UI
            function updateLicenseData(response) {
                if (response.data.status) {
                    jQuery('.status .title').replaceWith('<span class="title" style="color:green;position: relative; top: -10px;">'+response.data.status_title+'</span>');
                } else {
                    jQuery('.status .title').replaceWith('<span class="title" style="color:red;position: relative; top: -10px;">'+response.data.status_title+'</span>');
                }
                
                jQuery('.payment_id').text(response.data.payment_id);
                jQuery('.customer_name').text(response.data.customer_name);
                jQuery('.customer_email').text(response.data.customer_email);
                jQuery('.customer_since').text(response.data.customer_since);
                jQuery('.expires').text(response.data.expires);
                jQuery('.product_name').text(response.data.product_name);
                
                // Update subscription status
                updateSubscriptionStatus(response.data.subscription_status);
            }
            
            // Helper function to update subscription status
            function updateSubscriptionStatus(subscriptionStatus) {
                if (subscriptionStatus) {
                    var statusClass = 'subscription-status';
                    if (subscriptionStatus.indexOf('Active') !== -1) {
                        statusClass += ' active';
                    } else if (subscriptionStatus.indexOf('Not available') !== -1) {
                        statusClass += ' not-available';
                    } else {
                        statusClass += ' inactive';
                    }
                    jQuery('.subscription_status').html('<span class="' + statusClass + '">' + subscriptionStatus + '</span>');
                }
            }
            
            
            // Add unified refresh all license data button functionality
            jQuery('#refresh-all-license-data').on('click', function(){
                var button = jQuery(this);
                var originalText = button.text();
                button.prop('disabled', true).text('<?php echo esc_js(__('Refreshing all data...', 'salon-booking-system')); ?>');
                
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'sln_refresh_all_license_data',
                        key: jQuery('#license_key').val()
                    },
                    success: function (response) {
                        if (response.success) {
                            // Update all license data fields
                            updateLicenseData(response);
                            
                            // Update subscription status
                            updateSubscriptionStatus(response.data.subscription_status);
                            
                            // Show detailed debug info if available
                            if (response.data.debug_info) {
                                console.log('License Refresh Debug Info:', response.data.debug_info);
                            }
                            
                            var message = '<?php echo esc_js(__('All license data refreshed successfully!', 'salon-booking-system')); ?>';
                            if (response.data.customer_since_method) {
                                message += '\n\n' + '<?php echo esc_js(__('Customer Since Method:', 'salon-booking-system')); ?>' + ' ' + response.data.customer_since_method;
                            }
                            alert(message);
                        } else {
                            alert('<?php echo esc_js(__('Error refreshing data: ', 'salon-booking-system')); ?>' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX error:', error);
                        alert('<?php echo esc_js(__('Error refreshing license data. Please try again.', 'salon-booking-system')); ?>');
                    },
                    complete: function (data) {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Add clear license status button functionality
            jQuery('#clear-license-status').on('click', function(){
                if (confirm('<?php echo esc_js(__('Are you sure you want to clear the license status? This will reset the license information.', 'salon-booking-system')); ?>')) {
                    var button = jQuery(this);
                    var originalText = button.text();
                    button.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'salon-booking-system')); ?>');
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'sln_clear_license_status'
                        },
                        success: function (response) {
                            if (response.success) {
                                alert('<?php echo esc_js(__('License status cleared. Please refresh the page.', 'salon-booking-system')); ?>');
                                location.reload();
                            } else {
                                alert('<?php echo esc_js(__('Error clearing license status.', 'salon-booking-system')); ?>');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('AJAX error:', error);
                            alert('<?php echo esc_js(__('Error clearing license status. Please try again.', 'salon-booking-system')); ?>');
                        },
                        complete: function (data) {
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                }
            });
            
        </script>
        
        <script>
        function toggleRollbackOptions() {
            var options = document.getElementById('sln-rollback-options');
            if (options.style.display === 'none') {
                options.style.display = 'block';
                showWordPressRollbackOptions();
            } else {
                options.style.display = 'none';
            }
        }
        
        function showWordPressRollbackOptions() {
            var versionsDiv = document.getElementById('sln-rollback-versions');
            if (versionsDiv) {
                versionsDiv.innerHTML = '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;">' +
                    '<h4>Loading Available Versions...</h4>' +
                    '<p>Fetching versions from salonbookingsystem.com...</p>' +
                    '</div>';
                
                // Fetch versions from EDD API
                fetchVersionsFromEDD();
            }
        }
        
        function fetchVersionsFromEDD() {
            var versionsDiv = document.getElementById('sln-rollback-versions');
            
            // Create nonce for security
            var nonce = '<?php echo wp_create_nonce("sln_rollback_nonce"); ?>';
            
            // Ensure ajaxurl is defined, fallback to admin-ajax.php
            var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url("admin-ajax.php"); ?>';
            
            // Fetch available versions from salonbookingsystem.com via EDD API
            // If the API is unavailable, a fallback list of recent versions will be used
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=sln_get_rollback_versions&nonce=' + encodeURIComponent(nonce)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayAvailableVersions(data.data.versions, data.data.current_version);
                } else {
                    versionsDiv.innerHTML = '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #dc3232; margin: 10px 0;">' +
                        '<h4>Error Loading Versions</h4>' +
                        '<p>Could not fetch versions from salonbookingsystem.com: ' + (data.data || 'Unknown error') + '</p>' +
                        '<p><strong>Possible reasons:</strong></p>' +
                        '<ul style="margin-left: 20px;">' +
                        '<li>Invalid or expired license key</li>' +
                        '<li>Server connection issue</li>' +
                        '<li>EDD API temporarily unavailable</li>' +
                        '</ul>' +
                        '<p><strong>What to do:</strong> Verify your license key is active on the License tab above, or contact support.</p>' +
                        '</div>';
                }
            })
            .catch(error => {
                console.error('Rollback fetch error:', error);
                versionsDiv.innerHTML = '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #dc3232; margin: 10px 0;">' +
                    '<h4>Network Error</h4>' +
                    '<p>Could not connect to salonbookingsystem.com: ' + error.message + '</p>' +
                    '<p>Please check your server\'s internet connection and try again.</p>' +
                    '<p><small>If ajaxurl was undefined, this has been fixed. Please refresh the page and try again.</small></p>' +
                    '</div>';
            });
        }
        
        function displayAvailableVersions(versions, currentVersion) {
            var versionsDiv = document.getElementById('sln-rollback-versions');
            
            if (versions.length === 0) {
                versionsDiv.innerHTML = '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #ffb900; margin: 10px 0;">' +
                    '<h4>No Previous Versions Available</h4>' +
                    '<p>No previous versions found for rollback.</p>' +
                    '<p><strong>Current Version:</strong> ' + currentVersion + '</p>' +
                    '<p><small>This may occur if you\'re running the latest version or if the version list needs updating.</small></p>' +
                    '</div>';
                return;
            }
            
            var html = '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;">' +
                '<h4>Available Rollback Versions</h4>' +
                '<p><strong>Current Version:</strong> ' + currentVersion + '</p>' +
                '<p style="color: #666; font-size: 12px; margin-top: 5px;">' +
                '<em>ℹ️ Showing ' + versions.length + ' version(s) available for rollback. ' +
                'These are fetched from salonbookingsystem.com.</em>' +
                '</p>' +
                '<div style="margin-top: 15px;">';
            
            versions.forEach(function(version) {
                html += '<div style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; background: white;">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                    '<div>' +
                    '<strong>Version ' + version.version + '</strong>' +
                    (version.date ? '<br><small style="color: #666;">Released: ' + version.date + '</small>' : '') +
                    '</div>' +
                    '<button type="button" class="button button-secondary" onclick="rollbackToVersion(\'' + version.version + '\', event)">' +
                    'Rollback to ' + version.version +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
            
            html += '</div></div>';
            versionsDiv.innerHTML = html;
        }
        
        function rollbackToVersion(version, event) {
            if (!confirm('⚠️ IMPORTANT: Before rolling back, make sure you have:\n\n1. Backed up your database\n2. Backed up your current plugin files\n\nAre you sure you want to rollback to version ' + version + '? This will replace the current version.')) {
                return;
            }
            
            var nonce = '<?php echo wp_create_nonce("sln_rollback_nonce"); ?>';
            
            // Ensure ajaxurl is defined, fallback to admin-ajax.php
            var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url("admin-ajax.php"); ?>';
            
            // Get button reference for loading state
            var button = null;
            var originalText = '';
            if (event && event.target) {
                button = event.target;
                originalText = button.textContent;
                button.disabled = true;
                button.textContent = 'Rolling back...';
            }
            
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=sln_rollback_to_version&version=' + encodeURIComponent(version) + '&nonce=' + encodeURIComponent(nonce)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    var successDiv = document.createElement('div');
                    successDiv.style.cssText = 'background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px;';
                    successDiv.innerHTML = '<strong>✅ Success!</strong> Successfully rolled back to version ' + version + '.<br><br>' +
                        '<strong>Important:</strong> A backup of your previous version has been saved to <code>wp-content/sln_backups/</code><br>' +
                        'The page will reload in 3 seconds...';
                    document.getElementById('sln-rollback-versions').appendChild(successDiv);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    // Show error message with helpful details
                    var errorDiv = document.createElement('div');
                    errorDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;';
                    errorDiv.innerHTML = '<strong>❌ Error:</strong> ' + (data.data || 'Unknown error') + '<br><br>' +
                        '<strong>Troubleshooting:</strong><br>' +
                        '• Check if your server has write permissions to the plugin directory<br>' +
                        '• Verify your license key is active<br>' +
                        '• Check the error log for detailed information<br>' +
                        '• If a backup was created, it\'s in <code>wp-content/sln_backups/</code>';
                    document.getElementById('sln-rollback-versions').appendChild(errorDiv);
                    
                    // Re-enable button if it exists
                    if (button) {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                }
            })
            .catch(error => {
                // Show network error message
                console.error('Rollback error:', error);
                var errorDiv = document.createElement('div');
                errorDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;';
                errorDiv.innerHTML = '<strong>Network Error:</strong> ' + error.message + '<br><br>' +
                    '<strong>Debugging Info:</strong><br>' +
                    '• AJAX URL used: ' + ajaxUrl + '<br>' +
                    '• ajaxurl variable: ' + (typeof ajaxurl !== 'undefined' ? 'defined' : 'undefined') + '<br>' +
                    '• Check browser console (F12) for more details';
                document.getElementById('sln-rollback-versions').appendChild(errorDiv);
                
                // Re-enable button if it exists
                if (button) {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        }
        </script>
        <?php
    }

    /**
     * Refresh license if status is empty or contains error
     */
    private function refreshLicenseIfNeeded()
    {
        $current_status = $this->updater->get('license_status');
        if (empty($current_status) || strpos($current_status, 'error') !== false || strpos($current_status, 'Error') !== false) {
            // Clear the error status first
            SLN_Func::updateOption($this->updater->get('slug').'_license_status', '', true);
            
            // Try to get fresh license data
            $license_key = $this->updater->get('license_key');
            if ($license_key) {
                $response = $this->updater->doCall('check_license');
                
                if (!is_wp_error($response) && isset($response->license)) {
                    SLN_Func::updateOption($this->updater->get('slug').'_license_status', $response->license, true);
                    SLN_Func::updateOption($this->updater->get('slug').'_license_data', $response, true);
                }
            }
        }
    }

    /**
     * Get product name from license data
     */
    private function getProductName($data)
    {
        if (!isset($data->price_id)) {
            return $this->getProductNameFallback($data);
        }

        $price_id_mapping = array(
            1 => 'Basic Plan',
            2 => 'Business Plan', 
            3 => 'Pro Plan',
            4 => 'Enterprise Plan',
        );
        
        return isset($price_id_mapping[$data->price_id]) 
            ? $price_id_mapping[$data->price_id] 
            : $this->getProductNameFallback($data);
    }

    /**
     * Fallback method for product name
     */
    private function getProductNameFallback($data)
    {
        $fields = ['item_name', 'license_name', 'product_name', 'download_name', 'name'];
        
        foreach ($fields as $field) {
            if (isset($data->$field) && !empty($data->$field)) {
                return $data->$field;
            }
        }
        
        return __('Unknown Product', 'salon-booking-system');
    }

}