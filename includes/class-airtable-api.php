<?php
/**
 * Airtable API class
 */
class Airtable_Directory_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Any initialization code
    }
    
    /**
     * Fetch data from Airtable.
     *
     * @param string $table Name of the table.
     * @param array  $query_params Query parameters for the API call.
     *
     * @return array
     */
    public function fetch_data($table, $query_params = array()) {
        $transient_key = 'airtable_' . md5($table . serialize($query_params));
        
        // Check if we have cached data
        $cached_data = get_transient($transient_key);
        if ($cached_data !== false) {
            error_log('Using cached data for ' . $table);
            return $cached_data;
        }
    
        $api_key = AIRTABLE_API_KEY;
        $base_id = AIRTABLE_BASE_ID;
        $url = "https://api.airtable.com/v0/" . $base_id . "/" . urlencode($table);
    
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
    
        // Log the URL for debugging (masking sensitive info)
        error_log('Airtable API URL: ' . preg_replace('/Bearer\s+[a-zA-Z0-9]+/', 'Bearer XXXXX', $url));
    
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            )
        );
    
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            error_log('Airtable API Error: ' . $response->get_error_message());
            return [];
        }
    
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
    
        if (isset($data['records'])) {
            // Cache the data for 12 hours
            set_transient($transient_key, $data['records'], 12 * HOUR_IN_SECONDS);
            return $data['records'];
        }
    
        return [];
    }
    
    /**
     * Clear Airtable cache.
     * 
     * @param string $table Optional. Table name to clear cache for. If empty, clears all Airtable caches.
     * @param array $query_params Optional. Specific query parameters to clear cache for.
     */
    public function clear_cache($table = '', $query_params = array()) {
        global $wpdb;
        
        if (empty($table)) {
            // Clear all Airtable caches
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_airtable_%' OR option_name LIKE '_transient_timeout_airtable_%'");
            error_log('Cleared all Airtable cache entries');
        } else {
            // Clear cache for specific table
            if (empty($query_params)) {
                // Clear all caches for this table
                $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE (option_name LIKE %s OR option_name LIKE %s)", 
                    '_transient_airtable_' . md5($table) . '%',
                    '_transient_timeout_airtable_' . md5($table) . '%'
                ));
                error_log('Cleared cache for table: ' . $table);
            } else {
                // Clear cache for specific query
                $transient_key = 'airtable_' . md5($table . serialize($query_params));
                delete_transient($transient_key);
                error_log('Cleared cache for specific query: ' . $transient_key);
            }
        }
    }
} 