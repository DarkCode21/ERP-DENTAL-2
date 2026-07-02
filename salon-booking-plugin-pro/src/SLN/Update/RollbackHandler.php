<?php

namespace SLN\Update;

class RollbackHandler
{
    public function __construct()
    {
        \add_action('wp_ajax_sln_get_rollback_versions', [$this, 'getRollbackVersions']);
        \add_action('wp_ajax_sln_rollback_to_version', [$this, 'rollbackToVersion']);
    }
    
    /**
     * AJAX handler to get available rollback versions
     */
    public function getRollbackVersions()
    {
        // Check nonce for security
        if (!\wp_verify_nonce($_POST['nonce'], 'sln_rollback_nonce')) {
            \wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check user capabilities
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $license_key = \get_option(SLN_ITEM_SLUG . '_license_key', '');
        if (empty($license_key)) {
            \wp_send_json_error('No license key found');
            return;
        }
        
        // Get license data to determine edition
        $license_data = \get_option(SLN_ITEM_SLUG . '_license_data');
        if (!$license_data) {
            \SLN_Plugin::addLog('SLN Rollback: No license data found, attempting to fetch from EDD API');
            // Try to get license data from EDD API
            $updater = new \SLN_Update_Manager(array(
                'name' => 'Salon Booking System',
                'slug' => SLN_ITEM_SLUG,
                'store' => SLN_STORE_URL
            ));
            $license_response = $updater->doCall('check_license');
            if (!\is_wp_error($license_response) && isset($license_response->license)) {
                $license_data = $license_response;
                \update_option(SLN_ITEM_SLUG . '_license_data', $license_data);
            }
        }
        
        $rollback = new \SLN\Update\EDDRollback(SLN_ITEM_ID, $license_key, $license_data);
        $edition = $rollback->getEdition();
        \SLN_Plugin::addLog('SLN Rollback: User license edition determined as: ' . $edition);
        
        // Clear cache if requested (for debugging/testing)
        if (isset($_POST['clear_cache']) && $_POST['clear_cache'] === '1') {
            $rollback->clearVersionCache();
            \SLN_Plugin::addLog('SLN Rollback: Cache cleared by user request');
        }
        
        $versions = $rollback->getAvailableVersions();
        
        if (!$versions || empty($versions)) {
            \SLN_Plugin::addLog('SLN Rollback: getAvailableVersions() returned empty or false. This could mean:');
            \SLN_Plugin::addLog('  - EDD API returned no data and fallback list also returned empty');
            \SLN_Plugin::addLog('  - All versions were filtered out (edition mismatch, download verification failed, etc.)');
            \SLN_Plugin::addLog('  - Current version is very old and no older versions exist');
            
            // Provide more helpful error message
            $error_message = 'Unable to fetch versions from EDD API. ';
            $error_message .= 'The fallback list was also checked, but no valid versions were found. ';
            $error_message .= 'This could mean all versions failed download verification or edition filtering. ';
            $error_message .= 'Please check the error logs for details.';
            
            \wp_send_json_error($error_message);
            return;
        }
        
        // Filter out current version (additional safety check)
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $available_versions = array_filter($versions, function($version) use ($current_version) {
            $result = version_compare($version['version'], $current_version, '<');
            if (!$result) {
                \SLN_Plugin::addLog('SLN Rollback: Filtered out version ' . $version['version'] . ' (not older than current ' . $current_version . ')');
            }
            return $result;
        });
        
        // Re-index array after filtering
        $available_versions = array_values($available_versions);
        
        // Log final versions for debugging
        \SLN_Plugin::addLog('SLN Rollback: Returning ' . count($available_versions) . ' available versions: ' . implode(', ', array_column($available_versions, 'version')));
        
        \wp_send_json_success([
            'versions' => $available_versions,
            'current_version' => $current_version
        ]);
    }
    
    /**
     * AJAX handler to rollback to a specific version
     */
    public function rollbackToVersion()
    {
        // Check nonce for security
        if (!\wp_verify_nonce($_POST['nonce'], 'sln_rollback_nonce')) {
            \wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }
        
        // Check user capabilities
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('You do not have permission to perform this action.');
            return;
        }
        
        $version = \sanitize_text_field($_POST['version']);
        if (empty($version)) {
            \wp_send_json_error('No version specified.');
            return;
        }
        
        $license_key = \get_option(SLN_ITEM_SLUG . '_license_key', '');
        if (empty($license_key)) {
            \wp_send_json_error('No license key found. Please enter your license key on the License tab.');
            return;
        }
        
        // Get license data to determine edition
        $license_data = \get_option(SLN_ITEM_SLUG . '_license_data');
        
        // Log the rollback attempt
        \SLN_Plugin::addLog('SLN Rollback: Starting rollback to version ' . $version);
        \SLN_Plugin::addLog('SLN Rollback: Current version: ' . (defined('SLN_VERSION') ? SLN_VERSION : 'unknown'));
        \SLN_Plugin::addLog('SLN Rollback: User: ' . \wp_get_current_user()->user_login);
        
        try {
            $rollback = new \SLN\Update\EDDRollback(SLN_ITEM_ID, $license_key, $license_data);
            $edition = $rollback->getEdition();
            \SLN_Plugin::addLog('SLN Rollback: User license edition: ' . $edition);
            $result = $rollback->rollbackToVersion($version);
            
            if ($result) {
                \wp_send_json_success([
                    'message' => \sprintf('Successfully rolled back to version %s. The page will reload.', $version),
                    'version' => $version
                ]);
            } else {
                \SLN_Plugin::addLog('SLN Rollback: Rollback failed - check previous error logs for details');
                \wp_send_json_error('Failed to rollback to version ' . $version . '. Please check the error log for details, or restore from the backup in wp-content/sln_backups/');
            }
        } catch (\Exception $e) {
            \SLN_Plugin::addLog('SLN Rollback: Exception during rollback: ' . $e->getMessage());
            \wp_send_json_error('Error during rollback: ' . $e->getMessage());
        }
    }
}