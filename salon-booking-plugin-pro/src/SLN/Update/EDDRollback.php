<?php

namespace SLN\Update;

class EDDRollback
{
    private $api_url = 'https://salonbookingsystem.com';
    private $item_id;
    private $license_key;
    private $license_edition; // 'pay' (pro) or 'se' (special edition)
    
    public function __construct($item_id, $license_key, $license_data = null)
    {
        $this->item_id = $item_id;
        $this->license_key = $license_key;
        $this->license_edition = $this->determineEdition($license_data);
    }
    
    /**
     * Determine license edition from license data
     * Returns 'pay' for pro/business/enterprise plans, 'se' for special edition/basic branded
     * 
     * @param object|array|null $license_data License data from EDD API
     * @return string Edition: 'pay' or 'se'
     */
    private function determineEdition($license_data)
    {
        if (!$license_data) {
            // Try to get license data from options
            $license_data = \get_option(SLN_ITEM_SLUG . '_license_data');
        }
        
        if (!$license_data) {
            \SLN_Plugin::addLog('SLN Rollback: No license data available, defaulting to pay edition');
            return 'pay'; // Default to pay if no data
        }
        
        // Convert to array if object
        if (is_object($license_data)) {
            $license_data = (array) $license_data;
        }
        
        // Check item_name first
        $item_name = isset($license_data['item_name']) ? strtolower($license_data['item_name']) : '';
        
        // Special Edition / Basic Branded version indicators
        if (strpos($item_name, 'basic') !== false || 
            strpos($item_name, 'branded') !== false || 
            strpos($item_name, 'special edition') !== false ||
            strpos($item_name, 'se') !== false) {
            \SLN_Plugin::addLog('SLN Rollback: Detected SE edition from item_name: ' . $item_name);
            return 'se';
        }
        
        // Check price_id - price_id 1 is Basic Plan (SE), others are pay
        if (isset($license_data['price_id'])) {
            if ($license_data['price_id'] == 1) {
                \SLN_Plugin::addLog('SLN Rollback: Detected SE edition from price_id: 1 (Basic Plan)');
                return 'se';
            }
            \SLN_Plugin::addLog('SLN Rollback: Detected PAY edition from price_id: ' . $license_data['price_id']);
            return 'pay';
        }
        
        // Default to pay (pro) if we can't determine
        \SLN_Plugin::addLog('SLN Rollback: Could not determine edition, defaulting to pay. item_name: ' . $item_name);
        return 'pay';
    }
    
    /**
     * Get the license edition
     * @return string 'pay' or 'se'
     */
    public function getEdition()
    {
        return $this->license_edition;
    }
    
    /**
     * Clear cached rollback versions
     * Useful after license updates or for debugging
     */
    public function clearCache()
    {
        $cache_key = 'sln_rollback_versions_' . $this->license_edition;
        \delete_transient($cache_key);
        \SLN_Plugin::addLog('SLN Rollback: Cache cleared for edition: ' . $this->license_edition);
    }
    
    /**
     * Get available versions
     * 
     * NEW APPROACH (EDD Secure Downloads):
     * 1. Query salonbookingsystem.com REST API with license key
     * 2. API validates license and queries remote Media Library for ZIP files
     * 3. API returns EDD secure download URLs (requires valid license)
     * 4. Cache results for 1 hour
     * 5. Fallback to hardcoded list if API unavailable
     * 
     * @return array Array of available versions with secure download URLs
     */
    public function getAvailableVersions()
    {
        \SLN_Plugin::addLog('SLN Rollback: Starting getAvailableVersions()');
        \SLN_Plugin::addLog('SLN Rollback: License edition: ' . $this->license_edition);
        
        // Check for cached results (1 hour cache)
        $cache_key = 'sln_rollback_versions_' . $this->license_edition;
        $cached_versions = \get_transient($cache_key);
        
        if ($cached_versions !== false && is_array($cached_versions)) {
            \SLN_Plugin::addLog('SLN Rollback: Using cached versions (' . count($cached_versions) . ' versions)');
            return $cached_versions;
        }
        
        // Priority 1: Try API (queries remote Media Library with license validation)
        $versions = $this->getVersionsFromAPI();
        
        // Handle invalid license (API returned empty array)
        if (is_array($versions) && empty($versions)) {
            \SLN_Plugin::addLog('SLN Rollback: License validation failed - no versions available');
            return []; // Don't use fallback for invalid licenses
        }
        
        // API success - cache and return
        if (is_array($versions) && !empty($versions)) {
            \SLN_Plugin::addLog('SLN Rollback: Using versions from API (' . count($versions) . ' versions)');
            \set_transient($cache_key, $versions, HOUR_IN_SECONDS);
            return $versions;
        }
        
        \SLN_Plugin::addLog('SLN Rollback: API failed or unavailable, using hardcoded fallback');
        
        // Priority 2: Use hardcoded fallback (API temporarily unavailable)
        $fallback_versions = $this->getHardcodedFallbackVersions();
        
        // Cache fallback for 5 minutes (shorter cache for fallback)
        if (!empty($fallback_versions)) {
            \set_transient($cache_key, $fallback_versions, 5 * MINUTE_IN_SECONDS);
        }
        
        return $fallback_versions;
    }
    
    /**
     * Query salonbookingsystem.com API for available rollback versions
     * 
     * This queries the remote Media Library and validates the license
     * before returning available versions with EDD secure download URLs.
     * 
     * @return array|false Array of version data, empty array if license invalid, false on API error
     */
    private function getVersionsFromAPI()
    {
        \SLN_Plugin::addLog('SLN Rollback: Fetching versions from salonbookingsystem.com API');
        
        // Build API URL with parameters
        $api_url = add_query_arg([
            'license' => $this->license_key,
            'edition' => $this->license_edition,
            'url' => \home_url(),
            'item_id' => $this->item_id, // Send item_id so API can determine correct product
        ], 'https://www.salonbookingsystem.com/wp-json/salon/v1/rollback-versions');
        
        // Make API request
        $response = \wp_remote_get($api_url, [
            'timeout' => 15,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        
        // Check for request errors
        if (\is_wp_error($response)) {
            \SLN_Plugin::addLog('SLN Rollback: API request failed - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = \wp_remote_retrieve_response_code($response);
        $body = \wp_remote_retrieve_body($response);
        
        // Handle license validation failure (403 Forbidden)
        if ($status_code === 403) {
            \SLN_Plugin::addLog('SLN Rollback: License validation failed (403) - invalid or expired license');
            return []; // Return empty array for invalid licenses (no fallback)
        }
        
        // Handle other errors
        if ($status_code !== 200) {
            \SLN_Plugin::addLog("SLN Rollback: API returned error status {$status_code}");
            \SLN_Plugin::addLog("SLN Rollback: Response body: " . substr($body, 0, 500));
            return false;
        }
        
        // Parse JSON response
        $data = json_decode($body, true);
        
        if (!is_array($data) || !isset($data['success']) || !$data['success']) {
            \SLN_Plugin::addLog('SLN Rollback: API returned invalid data structure');
            return false;
        }
        
        if (!isset($data['versions']) || !is_array($data['versions'])) {
            \SLN_Plugin::addLog('SLN Rollback: API response missing versions array');
            return false;
        }
        
        $api_versions = $data['versions'];
        \SLN_Plugin::addLog('SLN Rollback: API returned ' . count($api_versions) . ' versions for edition: ' . $this->license_edition);
        
        // Transform API response to our internal format
        $available_versions = [];
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        
        foreach ($api_versions as $version_data) {
            $version = $version_data['version'];
            
            // Skip current and future versions
            if (version_compare($version, $current_version, '>=')) {
                \SLN_Plugin::addLog("SLN Rollback: Skipping version {$version} (current or newer than {$current_version})");
                continue;
            }
            
            $available_versions[] = [
                'version' => $version,
                'file' => $version_data['file'], // EDD secure download URL
                'date' => $version_data['date'] ?? '',
                'size' => $version_data['size'] ?? '',
                'changelog' => 'Version ' . $version,
            ];
            
            \SLN_Plugin::addLog("SLN Rollback: Added version {$version} with secure download URL");
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Returning ' . count($available_versions) . ' available versions from API');
        
        return $available_versions;
    }
    
    /**
     * Fetch versions from salonbookingsystem.com changelog category
     * Each post title is a version number
     */
    private function getVersionsFromChangelogWebsite()
    {
        $cache_key = 'sln_changelog_versions';
        $cached = \get_transient($cache_key);
        
        if ($cached !== false) {
            \SLN_Plugin::addLog('SLN Rollback: Using cached changelog versions (' . count($cached) . ' versions)');
            return $cached;
        }
        
        $versions = [];
        $changelog_url = 'https://www.salonbookingsystem.com/category/changelog/';
        
        \SLN_Plugin::addLog('SLN Rollback: Fetching changelog from ' . $changelog_url);
        
        $response = \wp_remote_get($changelog_url, array(
            'timeout' => 15,
            'sslverify' => true,
            'user-agent' => 'WordPress/' . \get_bloginfo('version') . '; ' . \home_url()
        ));
        
        if (\is_wp_error($response)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to fetch changelog: ' . $response->get_error_message());
            return [];
        }
        
        $body = \wp_remote_retrieve_body($response);
        
        // Parse HTML to extract version numbers from post titles
        // Look for patterns like: <h3>10.30.3</h3> or <h2>10.29.6</h2>
        // Also look for dates in format: "November 17, 2025"
        if (preg_match_all('/<h[2-4][^>]*>(\d+\.\d+(?:\.\d+)?)<\/h[2-4]>/i', $body, $version_matches, PREG_SET_ORDER)) {
            foreach ($version_matches as $match) {
                $version = trim($match[1]);
                
                // Normalize version format
                $version_parts = explode('.', $version);
                if (count($version_parts) === 2) {
                    $version = $version . '.0';
                }
                
                // Try to extract date from surrounding content
                $date = '';
                $date_patterns = array(
                    '/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),\s+(\d{4})/i',
                    '/(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i',
                );
                
                // Look for date near the version in the HTML
                $context_start = max(0, strpos($body, $match[0]) - 500);
                $context_end = min(strlen($body), strpos($body, $match[0]) + 500);
                $context = substr($body, $context_start, $context_end - $context_start);
                
                foreach ($date_patterns as $pattern) {
                    if (preg_match($pattern, $context, $date_match)) {
                        if (isset($date_match[0])) {
                            $date = date('Y-m-d', strtotime($date_match[0]));
                            break;
                        }
                    }
                }
                
                if (empty($date)) {
                    $date = date('Y-m-d'); // Fallback to today if no date found
                }
                
                $versions[$version] = $date;
                \SLN_Plugin::addLog('SLN Rollback: Found version ' . $version . ' from changelog (date: ' . $date . ')');
            }
        }
        
        // Cache for 1 hour
        \set_transient($cache_key, $versions, HOUR_IN_SECONDS);
        
        \SLN_Plugin::addLog('SLN Rollback: Extracted ' . count($versions) . ' versions from changelog website');
        
        return $versions;
    }
    
    /**
     * Match changelog versions with Media Library ZIP files
     * Filter by edition based on filename pattern
     */
    private function matchVersionsWithMediaFiles($changelog_versions)
    {
        \SLN_Plugin::addLog('SLN Rollback: Building EDD download URLs for ' . count($changelog_versions) . ' changelog versions');
        
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $available_versions = [];
        $count = 0;
        
        // Hardcoded version-to-path mapping
        // UPDATE THIS with each new release - add the actual upload path from salonbookingsystem.com
        $version_paths = $this->getKnownVersionPaths();
        
        foreach ($changelog_versions as $version => $date) {
            // Filter out current and future versions
            if (version_compare($version, $current_version, '>=')) {
                \SLN_Plugin::addLog('SLN Rollback: Skipping version ' . $version . ' (current or newer than ' . $current_version . ')');
                continue;
            }
            
            // Check if we have a known path for this version and edition
            if (!isset($version_paths[$version]) || !isset($version_paths[$version][$this->license_edition])) {
                \SLN_Plugin::addLog('SLN Rollback: Version ' . $version . ' not found in known paths for edition ' . $this->license_edition);
                continue;
            }
            
            $date_path = $version_paths[$version][$this->license_edition];
            $edition_suffix = $this->license_edition === 'se' ? 'se' : 'pay';
            $filename = 'salon-booking-plugin-pro-' . $edition_suffix . '-' . $version . '.zip';
            
            // Build full URL with known date path
            $file_url = 'https://www.salonbookingsystem.com/wp-content/uploads/edd/' . $date_path . '/' . $filename;
            
            $available_versions[] = [
                'version' => $version,
                'file' => $file_url,
                'date' => $date,
                'changelog' => 'Version ' . $version,
            ];
            
            \SLN_Plugin::addLog('SLN Rollback: Built EDD download URL for version ' . $version . ': ' . $file_url);
            
            $count++;
            if ($count >= 6) {
                \SLN_Plugin::addLog('SLN Rollback: Reached limit of 6 versions');
                break;
            }
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Built ' . count($available_versions) . ' EDD download URLs: ' . implode(', ', array_column($available_versions, 'version')));
        
        return $available_versions;
    }
    
    /**
     * Get known versions from readme.txt changelog
     * This is the source of truth for released versions
     */
    private function getKnownVersionsFromChangelog()
    {
        $versions = [];
        $readme_path = SLN_PLUGIN_DIR . '/readme.txt';
        
        if (!file_exists($readme_path)) {
            \SLN_Plugin::addLog('SLN Rollback: readme.txt not found at ' . $readme_path);
            return [];
        }
        
        $readme_content = file_get_contents($readme_path);
        
        // Parse changelog section - look for version entries
        // Format examples:
        // "13-11.2025 - 10.30.2"
        // "08-11-2025 10.29.8"
        // "31.10.2025 - 10.29.6"
        // "27.10.2025 - 10.29.5"
        if (preg_match_all('/(\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4})\s*(?:-)?\s*(\d+\.\d+(?:\.\d+)?)/i', $readme_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $date_str = trim($match[1]);
                $version = trim($match[2]);
                
                // Normalize version format (e.g., 10.28 -> 10.28.0)
                $version_parts = explode('.', $version);
                if (count($version_parts) === 2) {
                    $version = $version . '.0';
                }
                
                // Normalize date format - handle DD-MM-YYYY, DD.MM.YYYY, DD/MM/YYYY
                $date_parts = preg_split('/[.\/-]/', $date_str);
                if (count($date_parts) === 3) {
                    $day = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                    $year = $date_parts[2];
                    $normalized_date = $year . '-' . $month . '-' . $day;
                    $versions[$version] = $normalized_date;
                }
            }
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Extracted ' . count($versions) . ' versions from readme.txt changelog');
        
        return $versions;
    }
    
    /**
     * Check if a version is downloadable via EDD API
     * Uses get_download action to verify the version exists
     * This is the same approach Elementor uses
     */
    private function isVersionDownloadable($download_url, $version)
    {
        if (empty($download_url)) {
            \SLN_Plugin::addLog('SLN Rollback: Version ' . $version . ' - No download URL generated');
            return false;
        }
        
        // Do a HEAD request to check if download URL is accessible
        // Use shorter timeout since we're checking many versions
        $check = \wp_remote_head($download_url, array(
            'timeout' => 5,
            'sslverify' => true,
            'redirection' => 2 // Don't follow too many redirects
        ));
        
        if (\is_wp_error($check)) {
            \SLN_Plugin::addLog('SLN Rollback: Version ' . $version . ' - Download check error: ' . $check->get_error_message());
            return false;
        }
        
        $response_code = \wp_remote_retrieve_response_code($check);
        
        // Accept 200 (OK) or redirects (301, 302) as valid
        // EDD often uses redirects for download URLs
        $is_valid = in_array($response_code, array(200, 301, 302, 307, 308));
        
        if (!$is_valid) {
            \SLN_Plugin::addLog('SLN Rollback: Version ' . $version . ' - Invalid response code: ' . $response_code);
        }
        
        return $is_valid;
    }
    
    /**
     * OLD METHOD - Keep for reference but not used
     * SECONDARY SOURCE: EDD API on salonbookingsystem.com
        $request = array(
            'edd_action' => 'get_version_history',
            'license' => $this->license_key,
            'item_name' => SLN_ITEM_NAME,
            'url' => \home_url(),
        );
        
        $response = \wp_remote_get(
            \add_query_arg($request, $this->api_url),
            array('timeout' => 30, 'sslverify' => true)
        );
        
        // Only use fallback if EDD API completely fails (network error, no response)
        if (\is_wp_error($response)) {
            \SLN_Plugin::addLog('SLN Rollback: EDD API network error - ' . $response->get_error_message() . '. Using hardcoded fallback list.');
            return $this->getHardcodedFallbackVersions();
        }
        
        $body = \wp_remote_retrieve_body($response);
        $response_code = \wp_remote_retrieve_response_code($response);
        
        // Log raw response for debugging
        \SLN_Plugin::addLog('SLN Rollback: EDD API HTTP response code: ' . $response_code);
        \SLN_Plugin::addLog('SLN Rollback: EDD API raw body (first 500 chars): ' . substr($body, 0, 500));
        
        $data = json_decode($body, true);
        
        // Log parsed response
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            \SLN_Plugin::addLog('SLN Rollback: EDD API JSON decode error: ' . json_last_error_msg());
        }
        \SLN_Plugin::addLog('SLN Rollback: EDD API parsed response: ' . print_r($data, true));
        
        // Only use fallback if EDD API returns no data structure at all
        if (!$data || !isset($data['versions']) || !is_array($data['versions'])) {
            \SLN_Plugin::addLog('SLN Rollback: EDD API returned no data structure. Using hardcoded fallback list.');
            return $this->getHardcodedFallbackVersions();
        }
        
        // EDD API returned data - verify each version is actually downloadable
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $versions = [];
        
        \SLN_Plugin::addLog('SLN Rollback: EDD API returned ' . count($data['versions']) . ' version entries to process');
        
        foreach ($data['versions'] as $version) {
            // Basic validation: must have version string and download URL
            if (!isset($version['version']) || !isset($version['download_url'])) {
                \SLN_Plugin::addLog('SLN Rollback: Skipping version entry missing required fields: ' . print_r($version, true));
                continue;
            }
            
            $version_string = trim($version['version']);
            $download_url = trim($version['download_url']);
            
            // Basic format check (must have at least one dot, be non-empty)
            if (empty($version_string) || strpos($version_string, '.') === false) {
                \SLN_Plugin::addLog('SLN Rollback: Skipping invalid version format from EDD: ' . $version_string);
                continue;
            }
            
            // Filter out current version and future versions (only show older versions for rollback)
            if (version_compare($version_string, $current_version, '>=')) {
                \SLN_Plugin::addLog('SLN Rollback: Skipping version ' . $version_string . ' (current or newer than ' . $current_version . ')');
                continue;
            }
            
            // Filter by edition - check if version matches user's license edition
            // First check if EDD API provides edition info directly
            $version_edition = null;
            if (isset($version['edition'])) {
                $version_edition = strtolower($version['edition']);
                // Normalize: 'pro', 'pay' -> 'pay'; 'se', 'basic', 'branded' -> 'se'
                if (in_array($version_edition, array('pro', 'pay', 'business', 'enterprise'))) {
                    $version_edition = 'pay';
                } elseif (in_array($version_edition, array('se', 'basic', 'branded', 'special-edition'))) {
                    $version_edition = 'se';
                }
            } else {
                // Fallback: infer from download URL
                $version_edition = $this->getVersionEdition($version_string, $download_url);
            }
            
            if ($version_edition !== $this->license_edition) {
                \SLN_Plugin::addLog('SLN Rollback: Skipping version ' . $version_string . ' - edition mismatch. Version edition: ' . $version_edition . ', User edition: ' . $this->license_edition);
                continue;
            }
            
            // Verify the download URL is valid and points to an actual ZIP file
            // Do a HEAD request first to check headers without downloading the full file
            $download_check = \wp_remote_head($download_url, array(
                'timeout' => 10,
                'sslverify' => true,
                'redirection' => 5 // Follow redirects (EDD often uses redirects)
            ));
            
            if (\is_wp_error($download_check)) {
                \SLN_Plugin::addLog('SLN Rollback: Version ' . $version_string . ' download URL is not accessible: ' . $download_check->get_error_message() . ' (URL: ' . $download_url . ')');
                continue;
            }
            
            $response_code = \wp_remote_retrieve_response_code($download_check);
            // Only accept 200 (OK) as valid - redirects might point to error pages
            if ($response_code !== 200) {
                \SLN_Plugin::addLog('SLN Rollback: Version ' . $version_string . ' download URL returned non-200 response code: ' . $response_code . ' (URL: ' . $download_url . ')');
                continue;
            }
            
            // Check Content-Type to ensure it's a ZIP file
            $content_type = \wp_remote_retrieve_header($download_check, 'content-type');
            if ($content_type && strpos(strtolower($content_type), 'zip') === false && strpos(strtolower($content_type), 'octet-stream') === false) {
                \SLN_Plugin::addLog('SLN Rollback: Version ' . $version_string . ' download URL does not return a ZIP file. Content-Type: ' . $content_type . ' (URL: ' . $download_url . ')');
                continue;
            }
            
            // Verify by downloading first few bytes to check ZIP file signature (PK\x03\x04)
            $partial_download = \wp_remote_get($download_url, array(
                'timeout' => 10,
                'sslverify' => true,
                'headers' => array('Range' => 'bytes=0-3'), // Download only first 4 bytes
                'redirection' => 5
            ));
            
            $zip_valid = false;
            if (!\is_wp_error($partial_download)) {
                $partial_code = \wp_remote_retrieve_response_code($partial_download);
                $zip_signature = \wp_remote_retrieve_body($partial_download);
                
                // Check if we got a partial content response (206) or full response (200)
                if ($partial_code === 206 || $partial_code === 200) {
                    // ZIP files start with PK\x03\x04 (50 4B 03 04 in hex)
                    if (strlen($zip_signature) >= 2 && substr($zip_signature, 0, 2) === 'PK') {
                        $zip_valid = true;
                    }
                }
            }
            
            if (!$zip_valid) {
                \SLN_Plugin::addLog('SLN Rollback: Version ' . $version_string . ' download URL does not return a valid ZIP file (invalid signature or unsupported Range). URL: ' . $download_url);
                continue;
            }
            
            // Version passed all checks - include it
            \SLN_Plugin::addLog('SLN Rollback: Version ' . $version_string . ' verified and added (edition: ' . $version_edition . ', download URL accessible, response code: ' . $response_code . ')');
                $versions[] = [
                'version' => $version_string,
                'file' => $download_url,
                    'date' => isset($version['date']) ? $version['date'] : '',
                    'changelog' => isset($version['changelog']) ? $version['changelog'] : ''
                ];
            }
        
        // If EDD API returned versions but they were all filtered out (e.g., all are current/future),
        // return empty array rather than falling back to hardcoded list
        // This ensures EDD remains the source of truth
        if (empty($versions)) {
            \SLN_Plugin::addLog('SLN Rollback: EDD API returned ' . count($data['versions']) . ' versions, but all were filtered out (current/future versions). Returning empty list.');
            return [];
        }
        
        // Sort versions by version number (newest first)
        usort($versions, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });
        
        // Log what versions were found from EDD API
        \SLN_Plugin::addLog('SLN Rollback: Successfully retrieved ' . count($versions) . ' versions from EDD API: ' . implode(', ', array_column($versions, 'version')));
        
        return $versions;
    }
    
    /**
     * Determine edition of a version from its download URL
     * Checks URL for indicators of edition (pay/pro vs se)
     * 
     * @param string $version Version string
     * @param string $download_url Download URL
     * @return string Edition: 'pay' or 'se'
     */
    private function getVersionEdition($version, $download_url)
    {
        // Check download URL for edition indicators
        $url_lower = strtolower($download_url);
        
        // SE/Basic Branded indicators in URL
        if (strpos($url_lower, 'basic') !== false || 
            strpos($url_lower, 'branded') !== false || 
            strpos($url_lower, 'special-edition') !== false ||
            strpos($url_lower, '-se') !== false ||
            strpos($url_lower, 'basic-branded') !== false) {
            return 'se';
        }
        
        // Pro/Pay indicators in URL
        if (strpos($url_lower, 'pro') !== false || 
            strpos($url_lower, 'salon-booking-plugin-pro') !== false ||
            strpos($url_lower, 'business') !== false ||
            strpos($url_lower, 'enterprise') !== false) {
            return 'pay';
        }
        
        // Default: assume pay (pro) if URL doesn't indicate SE
        // EDD API should provide correct URLs, but we default to pay for safety
        return 'pay';
    }
    
    /**
     * Validate version format
     * Valid formats: X.Y.Z (e.g., 10.30.5, 10.29.8)
     * 
     * NOTE: This method is kept for potential future use, but we now trust EDD API
     * as the source of truth and only do basic validation.
     * 
     * @param string $version Version string to validate
     * @return bool True if valid format
     */
    private function isValidVersionFormat($version)
    {
        // Pattern: major.minor.patch (e.g., 10.30.5)
        // Must start with digit, have 2 dots, and end with digit
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return false;
        }
        
        // Additional check: major version should be reasonable (e.g., 1-99)
        $parts = explode('.', $version);
        if (count($parts) !== 3) {
            return false;
        }
        
        // Ensure all parts are numeric
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                return false;
            }
        }
        
        // Check if version matches expected pattern (10.XX.X)
        // This can be adjusted if versioning scheme changes
        $major = (int)$parts[0];
        if ($major < 1 || $major > 99) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get versions by scanning file system AND Media Library attachments
     * This is the most reliable source - checks both actual files and WordPress attachments
     * Uses caching to avoid repeated scans
     */
    private function getVersionsFromFileSystem()
    {
        $cache_key = 'sln_rollback_versions_' . $this->license_edition;
        $cached = \get_transient($cache_key);
        
        // Only use cache if it has results (don't cache empty results)
        if ($cached !== false && !empty($cached)) {
            \SLN_Plugin::addLog('SLN Rollback: Using cached file system scan results (' . count($cached) . ' versions)');
            return $cached;
        }
        
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $versions = [];
        $files = [];
        
        // METHOD 1: Get files from Media Library attachments (most reliable for WordPress)
        // Query ALL ZIP attachments and filter by filename pattern (more reliable than search)
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'application/zip',
            'posts_per_page' => -1,
            'post_status' => 'inherit',
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $all_attachments = \get_posts($args);
        \SLN_Plugin::addLog('SLN Rollback: Media Library query found ' . count($all_attachments) . ' total ZIP attachments');
        
        // Filter by filename pattern in PHP (more reliable than WordPress search)
        $edition_suffix = $this->license_edition === 'se' ? '-se' : '-pay';
        $patterns = array(
            'salon-booking-plugin-pro' . $edition_suffix,
            'salon-booking-plugin-pro-pay',
            'salon-booking-plugin-pro-se',
        );
        
        foreach ($all_attachments as $attachment) {
            $file_path = \get_attached_file($attachment->ID);
            if (!$file_path || !file_exists($file_path)) {
                \SLN_Plugin::addLog('SLN Rollback: Media Library - Skipping attachment ID ' . $attachment->ID . ' (no file path or file not found)');
                continue;
            }
            
            $filename = basename($file_path);
            $basename = basename($file_path, '.zip');
            
            // Log all ZIP files found for debugging
            \SLN_Plugin::addLog('SLN Rollback: Media Library - Checking file: ' . $basename);
            
            // Check if filename matches any of our patterns
            $matches = false;
            foreach ($patterns as $pattern) {
                if (strpos($basename, $pattern) !== false) {
                    $matches = true;
                    \SLN_Plugin::addLog('SLN Rollback: Media Library - File matches pattern "' . $pattern . '": ' . $basename);
                    break;
                }
            }
            
            if ($matches) {
                $files[$file_path] = $attachment;
                \SLN_Plugin::addLog('SLN Rollback: Media Library - Added matching file: ' . $filename);
            } else {
                \SLN_Plugin::addLog('SLN Rollback: Media Library - File does not match any pattern: ' . $basename);
            }
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Media Library query found ' . count($files) . ' matching ZIP file attachments');
        
        // METHOD 2: Also scan file system directly (in case files aren't in Media Library)
        $upload_dir = \wp_upload_dir();
        if (!isset($upload_dir['error']) || $upload_dir['error'] === false) {
            $base_dir = $upload_dir['basedir'];
            $edition_pattern = $this->license_edition === 'se' ? '-se-' : '-pay-';
            $file_system_files = [];
            $this->globRecursive($base_dir, 'salon-booking-plugin-pro' . $edition_pattern . '*.zip', $file_system_files);
            
            // Add file system files to array (avoid duplicates)
            foreach ($file_system_files as $file_path => $null) {
                if (!isset($files[$file_path])) {
                    $files[$file_path] = null; // null indicates direct file system file
                }
            }
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Total files found (Media Library + file system): ' . count($files));
        
        foreach ($files as $file_path => $attachment_or_null) {
            if (!file_exists($file_path) || !is_readable($file_path)) {
                continue;
            }
            
            $basename = basename($file_path, '.zip');
            
            // Get file info - prefer attachment metadata if available
            $file_date = null;
            $file_url = null;
            
            if (is_object($attachment_or_null) && isset($attachment_or_null->ID)) {
                // This is a Media Library attachment
                $file_date = \get_the_date('Y-m-d', $attachment_or_null->ID);
                $file_url = \wp_get_attachment_url($attachment_or_null->ID);
            } else {
                // This is a direct file system file
                $file_date = date('Y-m-d', filemtime($file_path));
                $upload_dir = \wp_upload_dir();
                $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
            }
            
            // Extract version from filename
            // Pattern: salon-booking-plugin-pro-pay-10.30.3 or salon-booking-plugin-pro-pay-10.28
            $file_edition = null;
            $version_string = null;
            
            if (preg_match('/-(pay|se)-(\d+\.\d+(?:\.\d+)?)$/', $basename, $matches)) {
                $file_edition = $matches[1] === 'se' ? 'se' : 'pay';
                $version_string = $matches[2];
            } elseif (preg_match('/-(\d+\.\d+(?:\.\d+)?)$/', $basename, $matches)) {
                // Fallback: if no edition in filename, assume pay (pro)
                $file_edition = 'pay';
                $version_string = $matches[1];
            } else {
                \SLN_Plugin::addLog('SLN Rollback: File system - Could not extract version from filename: ' . $basename);
                continue;
            }
            
            // Filter by edition
            if ($file_edition !== $this->license_edition) {
                \SLN_Plugin::addLog('SLN Rollback: File system - Skipping version ' . $version_string . ' - edition mismatch. File edition: ' . $file_edition . ', User edition: ' . $this->license_edition);
                continue;
            }
            
            // Normalize version format (e.g., 10.28 -> 10.28.0)
            $version_parts = explode('.', $version_string);
            if (count($version_parts) === 2) {
                $version_string = $version_string . '.0';
            }
            
            // Filter out current and future versions
            if (version_compare($version_string, $current_version, '>=')) {
                \SLN_Plugin::addLog('SLN Rollback: File system - Skipping version ' . $version_string . ' (current or newer than ' . $current_version . ')');
                continue;
            }
            
            // Verify file is valid ZIP by checking signature
            $zip_valid = false;
            $handle = @fopen($file_path, 'rb');
            if ($handle) {
                $signature = fread($handle, 2);
                fclose($handle);
                if ($signature === 'PK') {
                    $zip_valid = true;
                }
            }
            
            if (!$zip_valid) {
                \SLN_Plugin::addLog('SLN Rollback: File system - Skipping invalid ZIP file: ' . $basename);
                continue;
            }
            
            $versions[] = [
                'version' => $version_string,
                'file' => $file_url,
                'date' => $file_date,
                'changelog' => 'Version from file system'
            ];
            
            \SLN_Plugin::addLog('SLN Rollback: File system - Added version ' . $version_string . ' (edition: ' . $file_edition . ', file: ' . $basename . ')');
        }
        
        // Sort versions by version number (newest first)
        usort($versions, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });
        
        // Only cache if we found versions (don't cache empty results)
        if (!empty($versions)) {
            \set_transient($cache_key, $versions, 15 * MINUTE_IN_SECONDS);
        } else {
            // Clear cache if empty to force re-scan next time
            \delete_transient($cache_key);
        }
        
        \SLN_Plugin::addLog('SLN Rollback: File system - Returning ' . count($versions) . ' versions: ' . implode(', ', array_column($versions, 'version')));
        
        return $versions;
    }
    
    /**
     * Recursively search for files matching pattern
     * Adds files to array with file path as key to avoid duplicates
     */
    private function globRecursive($base, $pattern, &$files = array())
    {
        $directories = glob($base . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
        $current_files = glob($base . '/' . $pattern, GLOB_NOSORT);
        
        if ($current_files !== false) {
            foreach ($current_files as $file) {
                // Use file path as key to avoid duplicates
                if (!isset($files[$file])) {
                    $files[$file] = null; // null indicates direct file system file (not attachment)
                }
            }
        }
        
        if ($directories !== false) {
            foreach ($directories as $dir) {
                $this->globRecursive($dir, $pattern, $files);
            }
        }
    }
    
    /**
     * Get versions from WordPress Media Library (DEPRECATED - kept as fallback)
     * This method is less reliable than file system scan
     */
    private function getVersionsFromMediaLibrary()
    {
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $versions = [];
        
        // Query Media Library for ZIP files matching plugin name pattern
        // Try multiple search patterns to catch all variations
        $edition_suffix = $this->license_edition === 'se' ? '-se' : '-pay';
        $search_patterns = array(
            'salon-booking-plugin-pro' . $edition_suffix,
            'salon-booking-plugin-pro-pay', // Also search for pay explicitly
            'salon-booking-plugin-pro-se',   // Also search for se explicitly
        );
        
        $all_attachments = array();
        foreach ($search_patterns as $pattern) {
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => 'application/zip',
                'posts_per_page' => -1,
                'post_status' => 'inherit',
                's' => $pattern,
            );
            
            $attachments = \get_posts($args);
            foreach ($attachments as $attachment) {
                // Avoid duplicates
                if (!isset($all_attachments[$attachment->ID])) {
                    $all_attachments[$attachment->ID] = $attachment;
                }
            }
        }
        
        $attachments = array_values($all_attachments);
        
        \SLN_Plugin::addLog('SLN Rollback: Media Library query found ' . count($attachments) . ' ZIP files matching plugin patterns');
        
        foreach ($attachments as $attachment) {
            $filename = \get_attached_file($attachment->ID);
            if (!$filename) {
                continue;
            }
            
            $basename = basename($filename, '.zip');
            
            // Extract version and edition from filename
            // Pattern: salon-booking-plugin-pro-pay-10.30.3 or salon-booking-plugin-pro-se-10.28
            // Match edition and version: -pay-10.30.3 or -se-10.28
            $file_edition = null;
            if (preg_match('/-(pay|se)-(\d+\.\d+(?:\.\d+)?)$/', $basename, $matches)) {
                $file_edition = $matches[1] === 'se' ? 'se' : 'pay';
                $version_string = $matches[2];
            } elseif (preg_match('/-(\d+\.\d+(?:\.\d+)?)$/', $basename, $matches)) {
                // Fallback: if no edition in filename, assume pay (pro)
                $file_edition = 'pay';
                $version_string = $matches[1];
            } else {
                \SLN_Plugin::addLog('SLN Rollback: Media Library - Could not extract version from filename: ' . $basename);
                continue;
            }
            
            // Filter by edition - only include files matching user's license edition
            if ($file_edition !== $this->license_edition) {
                \SLN_Plugin::addLog('SLN Rollback: Media Library - Skipping version ' . $version_string . ' - edition mismatch. File edition: ' . $file_edition . ', User edition: ' . $this->license_edition . ' (file: ' . $basename . ')');
                continue;
            }
            
            \SLN_Plugin::addLog('SLN Rollback: Media Library - Found version ' . $version_string . ' (edition: ' . $file_edition . ') in file: ' . $basename);
            
            // Normalize version format (e.g., 10.28 -> 10.28.0)
            $version_parts = explode('.', $version_string);
            if (count($version_parts) === 2) {
                $version_string = $version_string . '.0';
            }
            
            // Filter out current and future versions
            if (version_compare($version_string, $current_version, '>=')) {
                \SLN_Plugin::addLog('SLN Rollback: Media Library - Skipping version ' . $version_string . ' (current or newer than ' . $current_version . ')');
                continue;
            }
            
            // Get upload date
            $upload_date = \get_the_date('Y-m-d', $attachment->ID);
            
            // Get download URL (attachment URL)
            $download_url = \wp_get_attachment_url($attachment->ID);
            
            $versions[] = [
                'version' => $version_string,
                'file' => $download_url,
                'date' => $upload_date,
                'changelog' => 'Version from Media Library'
            ];
            
            \SLN_Plugin::addLog('SLN Rollback: Media Library - Added version ' . $version_string . ' (edition: ' . $file_edition . ', uploaded: ' . $upload_date . ')');
        }
        
        // Sort versions by version number (newest first)
        usort($versions, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });
        
        \SLN_Plugin::addLog('SLN Rollback: Media Library - Returning ' . count($versions) . ' versions: ' . implode(', ', array_column($versions, 'version')));
        
        return $versions;
    }
    
    /**
     * Clear cached version list
     * Useful when new ZIP files are uploaded
     */
    public function clearVersionCache()
    {
        \delete_transient('sln_rollback_versions_pay');
        \delete_transient('sln_rollback_versions_se');
        \SLN_Plugin::addLog('SLN Rollback: Cleared version cache');
    }
    
    /**
     * Get known version paths - centralized mapping
     * 
     * ⚠️ IMPORTANT: Only include STABLE, TESTED versions here!
     * Do NOT include beta, RC, or broken versions.
     * 
     * UPDATE THIS with each STABLE release - add the actual upload path
     * 
     * Format:
     * 'VERSION' => [
     *     'pay' => 'YYYY/MM',      // Path for PAY edition
     *     'se' => 'YYYY/MM',       // Path for SE edition (if exists)
     *     'date' => 'YYYY-MM-DD'   // Release date
     * ]
     */
    private function getKnownVersionPaths()
    {
        return [
            '10.30.3' => ['pay' => '2024/11', 'date' => '2024-11-14'],
            '10.29.6' => ['pay' => '2025/11', 'date' => '2024-10-31'],
            // 10.29.5 EXCLUDED - Missing critical files (SLB_Discount/Repository/DiscountRepository.php)
            '10.28.0' => ['pay' => '2024/10', 'date' => '2024-10-14'],
            '10.26.0' => ['pay' => '2024/09', 'date' => '2024-09-30'],
            '10.24.0' => ['pay' => '2024/09', 'date' => '2024-09-15'],
        ];
    }
    
    /**
     * Hardcoded fallback list - LAST RESORT ONLY
     * Only used when API is unavailable
     * Uses getKnownVersionPaths() for version data
     */
    
    private function getHardcodedFallbackVersions()
    {
        \SLN_Plugin::addLog('SLN Rollback: Using hardcoded fallback with known versions');
        
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        $edition_suffix = $this->license_edition === 'se' ? 'se' : 'pay';
        
        // Get known version paths
        $known_versions = $this->getKnownVersionPaths();
        
        $versions = [];
        $count = 0;
        
        foreach ($known_versions as $version => $version_data) {
            // Filter out current and future versions
            if (version_compare($version, $current_version, '>=')) {
                \SLN_Plugin::addLog('SLN Rollback: Hardcoded fallback - Skipping version ' . $version . ' (current or newer)');
                continue;
            }
            
            // Check if this edition exists for this version
            if (!isset($version_data[$this->license_edition])) {
                continue;
            }
            
            $date_path = $version_data[$this->license_edition];
            $date = $version_data['date'];
            $filename = 'salon-booking-plugin-pro-' . $edition_suffix . '-' . $version . '.zip';
            
            // Build full URL with date path
            $file_url = 'https://www.salonbookingsystem.com/wp-content/uploads/edd/' . $date_path . '/' . $filename;
            
            $versions[] = [
                'version' => $version,
                'file' => $file_url,
                'date' => $date,
                'changelog' => 'Version ' . $version,
            ];
            
            \SLN_Plugin::addLog('SLN Rollback: Hardcoded fallback - Added version ' . $version . ': ' . $file_url);
            
            $count++;
            if ($count >= 6) {
                \SLN_Plugin::addLog('SLN Rollback: Reached limit of 6 versions');
                break;
            }
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Hardcoded fallback - Returning ' . count($versions) . ' versions');
        
        return $versions;
    }
    
    /**
     * Get download URL for a specific version
     */
    private function getDownloadUrl($version)
    {
        $request = array(
            'edd_action' => 'get_download',
            'license' => $this->license_key,
            'item_name' => SLN_ITEM_NAME,
            'version' => $version,
            'url' => \home_url(),
        );
        
        return \add_query_arg($request, $this->api_url);
    }
    
    /**
     * Get version changelog
     */
    public function getVersionChangelog($version)
    {
        $response = \wp_remote_get($this->api_url . '/edd-api/v2/products/' . $this->item_id . '/changelog/' . $version, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->license_key
            ]
        ]);
        
        if (\is_wp_error($response)) {
            return '';
        }
        
        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['changelog']) ? $data['changelog'] : '';
    }
    
    /**
     * Download and install a specific version
     */
    public function rollbackToVersion($version)
    {
        \SLN_Plugin::addLog('SLN Rollback: rollbackToVersion called for version ' . $version);
        
        $versions = $this->getAvailableVersions();
        if (!$versions) {
            \SLN_Plugin::addLog('SLN Rollback: No versions available');
            return false;
        }
        
        $target_version = null;
        foreach ($versions as $v) {
            if ($v['version'] === $version) {
                $target_version = $v;
                break;
            }
        }
        
        if (!$target_version) {
            \SLN_Plugin::addLog('SLN Rollback: Target version ' . $version . ' not found in available versions');
            return false;
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Found target version with file URL: ' . $target_version['file']);
        
        // Use the Media Library file directly (faster and more reliable than EDD download)
        // The file URL is already a Media Library attachment URL
        $file_url = $target_version['file'];
        
        // Download from Media Library (local file, very fast)
        \SLN_Plugin::addLog('SLN Rollback: Downloading from: ' . $file_url);
        
        // Use WordPress download_url function for reliable downloads
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $temp_file = \download_url($file_url);
        
        if (\is_wp_error($temp_file)) {
            \SLN_Plugin::addLog('SLN Rollback: Download failed: ' . $temp_file->get_error_message());
            return false;
        }
        
        \SLN_Plugin::addLog('SLN Rollback: Downloaded to temp file: ' . $temp_file);
        
        // Extract and install
        $result = $this->installVersion($temp_file, $version);
        
        // Clean up
        if (file_exists($temp_file)) {
        unlink($temp_file);
            \SLN_Plugin::addLog('SLN Rollback: Cleaned up temp file');
        }
        
        return $result;
    }
    
    /**
     * Install a version from zip file
     */
    private function installVersion($zip_file, $version)
    {
        if (!class_exists('ZipArchive')) {
            \SLN_Plugin::addLog('SLN Rollback: ZipArchive class not available');
            return false;
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_file) !== TRUE) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to open zip file: ' . $zip_file);
            return false;
        }
        
        // Use actual plugin directory (supports all editions: free, pro, cc, se)
        $plugin_dir = defined('SLN_PLUGIN_DIR') ? SLN_PLUGIN_DIR : \plugin_dir_path(dirname(dirname(__DIR__)));
        $plugin_dir = \trailingslashit($plugin_dir);
        
        // Backup current version
        $backup_dir = \trailingslashit(WP_CONTENT_DIR) . 'sln_backups/' . date('Y-m-d_H-i-s') . '/';
        if (!\wp_mkdir_p($backup_dir)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to create backup directory: ' . $backup_dir);
            $zip->close();
            return false;
        }
        
        // Copy current files to backup
        \SLN_Plugin::addLog('SLN Rollback: Backing up current version from ' . $plugin_dir . ' to ' . $backup_dir);
        if (!$this->copyDirectory($plugin_dir, $backup_dir)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to backup current version');
            $zip->close();
            return false;
        }
        
        // Get the first folder name in the zip (this is the plugin folder name)
        $zip_root_folder = $zip->getNameIndex(0);
        if ($zip_root_folder === false) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to read zip contents');
            $zip->close();
            return false;
        }
        
        // Extract to a temporary directory first
        $temp_extract_dir = \trailingslashit(WP_CONTENT_DIR) . 'sln_temp_' . time() . '/';
        if (!\wp_mkdir_p($temp_extract_dir)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to create temp extract directory');
            $zip->close();
            return false;
        }
        
        // Extract the zip
        if (!$zip->extractTo($temp_extract_dir)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to extract zip to temp directory');
            $zip->close();
            $this->removeDirectory($temp_extract_dir);
            return false;
        }
        $zip->close();
        
        // Find the extracted plugin folder
        $extracted_folder = $temp_extract_dir . trim($zip_root_folder, '/');
        if (!is_dir($extracted_folder)) {
            \SLN_Plugin::addLog('SLN Rollback: Extracted folder not found: ' . $extracted_folder);
            $this->removeDirectory($temp_extract_dir);
            return false;
        }
        
        // Remove current plugin files (except backups)
        \SLN_Plugin::addLog('SLN Rollback: Removing current plugin files from ' . $plugin_dir);
        $this->removeDirectory($plugin_dir, false); // Don't remove the parent directory
        
        // Copy extracted files to plugin directory
        \SLN_Plugin::addLog('SLN Rollback: Copying new version from ' . $extracted_folder . ' to ' . $plugin_dir);
        if (!$this->copyDirectory($extracted_folder, $plugin_dir)) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to copy new version to plugin directory');
            // Attempt to restore backup
            \SLN_Plugin::addLog('SLN Rollback: Attempting to restore from backup');
            $this->copyDirectory($backup_dir, $plugin_dir);
            $this->removeDirectory($temp_extract_dir);
            return false;
        }
        
        // Clean up temp directory
        $this->removeDirectory($temp_extract_dir);
        
        \SLN_Plugin::addLog('SLN Rollback: Successfully rolled back to version ' . $version);
        \SLN_Plugin::addLog('SLN Rollback: Backup saved to ' . $backup_dir);
        
        return true;
    }
    
    /**
     * Copy directory recursively
     * @return bool Success status
     */
    private function copyDirectory($src, $dst)
    {
        $src = \trailingslashit($src);
        $dst = \trailingslashit($dst);
        
        if (!is_dir($src)) {
            \SLN_Plugin::addLog('SLN Rollback: Source directory does not exist: ' . $src);
            return false;
        }
        
        if (!is_dir($dst)) {
            if (!\wp_mkdir_p($dst)) {
                \SLN_Plugin::addLog('SLN Rollback: Failed to create destination directory: ' . $dst);
                return false;
            }
        }
        
        $dir = @opendir($src);
        if ($dir === false) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to open source directory: ' . $src);
            return false;
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $src_file = $src . $file;
                $dst_file = $dst . $file;
                
                if (is_dir($src_file)) {
                    if (!$this->copyDirectory($src_file, $dst_file)) {
                        closedir($dir);
                        return false;
                    }
                } else {
                    if (!@copy($src_file, $dst_file)) {
                        \SLN_Plugin::addLog('SLN Rollback: Failed to copy file: ' . $src_file . ' to ' . $dst_file);
                        closedir($dir);
                        return false;
                    }
                }
            }
        }
        closedir($dir);
        return true;
    }
    
    /**
     * Remove directory recursively
     * @param string $dir Directory to remove
     * @param bool $remove_self Whether to remove the directory itself (default true)
     * @return bool Success status
     */
    private function removeDirectory($dir, $remove_self = true)
    {
        if (!is_dir($dir)) {
            return true;
        }
        
        $dir = \trailingslashit($dir);
        $files = @scandir($dir);
        
        if ($files === false) {
            \SLN_Plugin::addLog('SLN Rollback: Failed to scan directory: ' . $dir);
            return false;
        }
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . $file;
                if (is_dir($path)) {
                    if (!$this->removeDirectory($path, true)) {
                        return false;
                    }
                } else {
                    if (!@unlink($path)) {
                        \SLN_Plugin::addLog('SLN Rollback: Failed to delete file: ' . $path);
                        return false;
                    }
                }
            }
        }
        
        if ($remove_self) {
            if (!@rmdir($dir)) {
                \SLN_Plugin::addLog('SLN Rollback: Failed to remove directory: ' . $dir);
                return false;
            }
        }
        
        return true;
    }
}
