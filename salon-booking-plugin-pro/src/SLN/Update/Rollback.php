<?php
/**
 * Plugin Rollback Handler
 * Handles rollback functionality for Salon Booking System
 */

class SLN_Update_Rollback
{
    private $updater;
    private $plugin_basename;
    private $plugin_dir;

    public function __construct(SLN_Update_Manager $updater)
    {
        $this->updater = $updater;
        $this->plugin_basename = SLN_PLUGIN_BASENAME;
        $this->plugin_dir = SLN_PLUGIN_DIR;
        
        add_action('wp_ajax_sln_rollback_plugin', array($this, 'handle_rollback'));
        add_action('admin_notices', array($this, 'show_rollback_notices'));
    }

    /**
     * Get available rollback versions from EDD API
     */
    public function get_available_versions()
    {
        $cache_key = 'sln_rollback_versions_' . md5($this->plugin_basename);
        $versions = get_transient($cache_key);
        
        if (false === $versions) {
            $versions = $this->fetch_versions_from_edd();
            if ($versions) {
                set_transient($cache_key, $versions, HOUR_IN_SECONDS);
            }
        }
        
        return $versions;
    }

    /**
     * Fetch available versions from EDD API
     */
    private function fetch_versions_from_edd()
    {
        $api_params = array(
            'edd_action' => 'get_version_history',
            'license' => $this->updater->get('license_key'),
            'item_name' => SLN_ITEM_NAME,
            'url' => home_url()
        );

        $response = wp_remote_post(SLN_STORE_URL, array(
            'timeout' => 15,
            'sslverify' => false,
            'body' => $api_params
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $version_data = json_decode(wp_remote_retrieve_body($response));
        
        if (isset($version_data->versions)) {
            return $version_data->versions;
        }
        
        return false;
    }

    /**
     * Handle AJAX rollback request
     */
    public function handle_rollback()
    {
        // Security checks
        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'salon-booking-system'));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'sln_rollback_nonce')) {
            wp_die(__('Security check failed.', 'salon-booking-system'));
        }

        $target_version = sanitize_text_field($_POST['version']);
        $current_version = SLN_VERSION;

        // Validate version
        if (version_compare($target_version, $current_version, '>=')) {
            wp_send_json_error(__('Cannot rollback to the same or newer version.', 'salon-booking-system'));
        }

        // Perform rollback
        $result = $this->perform_rollback($target_version);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully rolled back to version %s', 'salon-booking-system'), $target_version),
                'new_version' => $target_version
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Perform the actual rollback
     */
    private function perform_rollback($target_version)
    {
        try {
            // Create backup of current version
            $backup_result = $this->create_backup();
            if (!$backup_result['success']) {
                return $backup_result;
            }

            // Download target version
            $download_result = $this->download_version($target_version);
            if (!$download_result['success']) {
                return $download_result;
            }

            // Install target version
            $install_result = $this->install_version($download_result['file'], $target_version);
            if (!$install_result['success']) {
                return $install_result;
            }

            // Update database if needed
            $this->update_database_version($target_version);

            return array('success' => true);

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Create backup of current version
     */
    private function create_backup()
    {
        $backup_dir = WP_CONTENT_DIR . '/sln-backups/';
        
        if (!wp_mkdir_p($backup_dir)) {
            return array(
                'success' => false,
                'message' => __('Could not create backup directory.', 'salon-booking-system')
            );
        }

        $backup_file = $backup_dir . 'backup-' . SLN_VERSION . '-' . time() . '.zip';
        
        // Create zip of current plugin files
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
                $this->add_directory_to_zip($zip, $this->plugin_dir, '');
                $zip->close();
                
                return array('success' => true, 'backup_file' => $backup_file);
            }
        }

        return array(
            'success' => false,
            'message' => __('Could not create backup file.', 'salon-booking-system')
        );
    }

    /**
     * Download specific version from EDD
     */
    private function download_version($version)
    {
        $api_params = array(
            'edd_action' => 'get_download',
            'license' => $this->updater->get('license_key'),
            'item_name' => SLN_ITEM_NAME,
            'version' => $version,
            'url' => home_url()
        );

        $response = wp_remote_post(SLN_STORE_URL, array(
            'timeout' => 60,
            'sslverify' => false,
            'body' => $api_params
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Could not download version.', 'salon-booking-system')
            );
        }

        $download_url = wp_remote_retrieve_body($response);
        
        if (empty($download_url)) {
            return array(
                'success' => false,
                'message' => __('Invalid download URL received.', 'salon-booking-system')
            );
        }

        // Download the file
        $temp_file = download_url($download_url);
        
        if (is_wp_error($temp_file)) {
            return array(
                'success' => false,
                'message' => __('Could not download plugin file.', 'salon-booking-system')
            );
        }

        return array('success' => true, 'file' => $temp_file);
    }

    /**
     * Install the downloaded version
     */
    private function install_version($zip_file, $version)
    {
        // Deactivate plugin temporarily
        deactivate_plugins($this->plugin_basename);

        // Extract to temporary directory
        $temp_dir = WP_CONTENT_DIR . '/sln-temp-' . time() . '/';
        wp_mkdir_p($temp_dir);

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === TRUE) {
                $zip->extractTo($temp_dir);
                $zip->close();
            } else {
                return array(
                    'success' => false,
                    'message' => __('Could not extract plugin files.', 'salon-booking-system')
                );
            }
        }

        // Copy files to plugin directory
        $this->copy_directory($temp_dir, $this->plugin_dir);

        // Clean up
        $this->remove_directory($temp_dir);
        unlink($zip_file);

        // Reactivate plugin
        activate_plugin($this->plugin_basename);

        return array('success' => true);
    }

    /**
     * Update database version
     */
    private function update_database_version($version)
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        $settings->setDbVersion($version)->save();
    }

    /**
     * Show rollback notices
     */
    public function show_rollback_notices()
    {
        if (isset($_GET['sln_rollback_success'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Plugin successfully rolled back to previous version.', 'salon-booking-system') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Helper methods for file operations
     */
    private function add_directory_to_zip($zip, $dir, $zip_dir)
    {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                $zip->addEmptyDir($zip_dir . basename($file) . '/');
                $this->add_directory_to_zip($zip, $file, $zip_dir . basename($file) . '/');
            } else {
                $zip->addFile($file, $zip_dir . basename($file));
            }
        }
    }

    private function copy_directory($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copy_directory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function remove_directory($dir)
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->remove_directory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }
}
