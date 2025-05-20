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
        // Create a unique transient key for this query
        $transient_key = 'airtable_' . md5($table . serialize($query_params));
        
        // Check if we have cached data
        $cached_data = get_transient($transient_key);
        if ($cached_data !== false) {
            error_log('Using cached data for ' . $table);
            return $cached_data;
        }
    
        $api_key = AIRTABLE_API_KEY;
        $base_id = AIRTABLE_BASE_ID;
        
        // Initialize records array to store all records
        $all_records = array();
        
        // Initialize offset for pagination
        $offset = null;
        
        do {
            // Add offset to query params if we have one
            $current_params = $query_params;
            if ($offset) {
                $current_params['offset'] = $offset;
            }
            
            $url = "https://api.airtable.com/v0/" . $base_id . "/" . urlencode($table);
            
            if (!empty($current_params)) {
                $url .= '?' . http_build_query($current_params);
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
                return $all_records; // Return whatever we have so far
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['records']) && is_array($data['records'])) {
                // Add this page of records to our collection
                $all_records = array_merge($all_records, $data['records']);
                
                // Log how many records we've fetched so far
                error_log('Fetched ' . count($data['records']) . ' records from Airtable, total: ' . count($all_records));
                
                // Check if there are more records to fetch
                $offset = isset($data['offset']) ? $data['offset'] : null;
            } else {
                $offset = null; // No more records or error
            }
            
            // Small delay to prevent hitting API rate limits
            if ($offset) {
                usleep(200000); // 200ms delay
            }
            
        } while ($offset); // Continue until no more offset is returned
        
        // Log the total number of records fetched
        error_log('Total records fetched from ' . $table . ': ' . count($all_records));
        
        // Cache the data for 12 hours
        if (!empty($all_records)) {
            set_transient($transient_key, $all_records, 12 * HOUR_IN_SECONDS);
        }
        
        return $all_records;
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
            // Clear all Airtable caches - ensure we're matching the correct pattern with "airtable_"
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_airtable_%' OR option_name LIKE '_transient_timeout_airtable_%'");
            // Force WordPress to update its internal cache
            wp_cache_flush();
            error_log('Cleared all Airtable cache entries');
        } else {
            // Clear cache for specific table
            if (empty($query_params)) {
                // Clear all caches for this table - add the "airtable_" prefix to match the actual keys
                $prefix = 'airtable_' . md5($table);
                $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE (option_name LIKE %s OR option_name LIKE %s)", 
                    '_transient_' . $prefix . '%',
                    '_transient_timeout_' . $prefix . '%'
                ));
                wp_cache_flush();
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