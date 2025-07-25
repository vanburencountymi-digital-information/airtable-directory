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
        error_log('fetch_data called for table: ' . $table . ' with query_params: ' . print_r($query_params, true));
        
        // Create a unique transient key for this query
        $transient_key = 'airtable_' . md5($table . serialize($query_params));
        error_log('fetch_data transient_key: ' . $transient_key);
        
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
            
            error_log('Airtable API response status: ' . wp_remote_retrieve_response_code($response));
            error_log('Airtable API response body: ' . substr($body, 0, 500) . '...');
            
            if (isset($data['records']) && is_array($data['records'])) {
                // Add this page of records to our collection
                $all_records = array_merge($all_records, $data['records']);
                
                // Log how many records we've fetched so far
                error_log('Fetched ' . count($data['records']) . ' records from Airtable, total: ' . count($all_records));
                
                // Check if there are more records to fetch
                $offset = isset($data['offset']) ? $data['offset'] : null;
            } else {
                error_log('No records found in API response or error occurred');
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
     * Get department by Department ID (number)
     *
     * @param string|int $department_id Department ID number
     * @return array|false Department data or false if not found
     */
    public function get_department_by_id($department_id) {
        $query_params = array(
            'filterByFormula' => "{Department ID} = " . intval($department_id),
            'maxRecords' => 1
        );
        
        $departments = $this->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $query_params);
        
        return !empty($departments) ? $departments[0] : false;
    }
    
    /**
     * Get employee by Employee ID (number)
     *
     * @param string|int $employee_id Employee ID number
     * @return array|false Employee data or false if not found
     */
    public function get_employee_by_id($employee_id) {
        $query_params = array(
            'filterByFormula' => "{Employee ID} = " . intval($employee_id),
            'maxRecords' => 1,
            'fields' => array('Name', 'Title', 'Department', 'Email', 'Phone', 'Photo', 'Public', 'Employee ID')
        );
        
        $employees = $this->fetch_data(AIRTABLE_STAFF_TABLE, $query_params);
        
        return !empty($employees) ? $employees[0] : false;
    }
    
    /**
     * Get child departments for a parent department using Parent ID
     *
     * @param string|int $parent_id Parent department ID number
     * @return array Array of child department records
     */
    public function get_child_departments($parent_id) {
        $query_params = array(
            'filterByFormula' => "{Parent ID} = " . intval($parent_id)
        );
        
        return $this->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $query_params);
    }
    
    /**
     * Get all parent departments (departments with no Parent ID)
     *
     * @return array Array of parent department records
     */
    public function get_parent_departments() {
        $query_params = array(
            'filterByFormula' => "{Parent ID} = BLANK()"
        );
        
        return $this->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $query_params);
    }
    
    /**
     * Check if a staff member is public based on the Public field
     *
     * @param array $staff_record Staff record from Airtable
     * @return bool True if staff member should be displayed publicly
     */
    public function is_staff_public($staff_record) {
        $fields = isset($staff_record['fields']) ? $staff_record['fields'] : array();
        
        // Debug logging to understand the Public field format
        if (isset($fields['Public'])) {
            error_log('Public field found for staff member. Value: ' . print_r($fields['Public'], true) . ' Type: ' . gettype($fields['Public']));
        } else {
            error_log('No Public field found for staff member: ' . (isset($fields['Name']) ? $fields['Name'] : 'Unknown'));
            return false;
        }
        
        // Check if Public field exists and evaluate its value
        if (isset($fields['Public'])) {
            // Airtable checkbox fields return an array with checked values
            // If checked, it returns an array like ['Yes'] or [true]
            // If unchecked, it may be empty array, null, or false
            if (is_array($fields['Public'])) {
                $is_public = !empty($fields['Public']);
                error_log('Array Public field - Is public: ' . ($is_public ? 'true' : 'false'));
                return $is_public;
            }
            // Handle boolean values
            elseif (is_bool($fields['Public'])) {
                error_log('Boolean Public field: ' . ($fields['Public'] ? 'true' : 'false'));
                return $fields['Public'];
            }
            // Handle string values like 'Yes', 'True', etc.
            elseif (is_string($fields['Public'])) {
                $value = strtolower(trim($fields['Public']));
                $is_public = in_array($value, array('yes', 'true', '1', 'on'));
                error_log('String Public field "' . $fields['Public'] . '" - Is public: ' . ($is_public ? 'true' : 'false'));
                return $is_public;
            }
            // Handle other types
            else {
                error_log('Unknown Public field type: ' . gettype($fields['Public']) . ' Value: ' . print_r($fields['Public'], true));
            }
        }
        
        // Default to false (not public) if Public field is missing or invalid
        return false;
    }

    /**
     * Filter staff records to only include public ones
     *
     * @param array $staff_records Array of staff records
     * @return array Array of public staff records
     */
    public function filter_public_staff($staff_records) {
        if (empty($staff_records)) {
            return array();
        }
        
        $public_staff = array();
        foreach ($staff_records as $staff_record) {
            if ($this->is_staff_public($staff_record)) {
                $public_staff[] = $staff_record;
            }
        }
        
        return $public_staff;
    }

    /**
     * Get staff members by department using Employee IDs array or Staff field
     *
     * @param string|int $department_id Department ID number
     * @param bool $public_only Whether to filter to only public staff members (default: true)
     * @return array Array of staff records
     */
    public function get_staff_by_department($department_id, $public_only = true) {
        // First, get the department to find employee IDs
        $department = $this->get_department_by_id($department_id);
        
        if (!$department) {
            error_log("Department not found for ID: " . $department_id);
            return array();
        }
        
        $fields = isset($department['fields']) ? $department['fields'] : array();
        $employee_ids = array();
        
        // Check for Employee IDs array first
        if (isset($fields['Employee IDs']) && is_array($fields['Employee IDs'])) {
            $employee_ids = $fields['Employee IDs'];
            error_log("Found Employee IDs array: " . print_r($employee_ids, true));
        }
        // Check for Staff field (comma-separated string)
        elseif (isset($fields['Staff']) && !empty($fields['Staff'])) {
            $staff_string = $fields['Staff'];
            $employee_ids = array_filter(array_map('trim', explode(',', $staff_string)));
            error_log("Found Staff field: " . $staff_string . " -> " . print_r($employee_ids, true));
        }
        
        if (empty($employee_ids)) {
            error_log("No employee IDs found for department " . $department_id);
            return array();
        }
        
        // Build filter formula for staff members using Employee ID numbers
        $filter_clauses = array();
        foreach ($employee_ids as $emp_id) {
            $filter_clauses[] = "{Employee ID} = " . intval($emp_id);
        }
        $filter_formula = "OR(" . implode(',', $filter_clauses) . ")";
        
        error_log("Staff lookup filter formula: " . $filter_formula);
        
        $staff_query_params = array(
            'filterByFormula' => $filter_formula
        );
        
        $staff_results = $this->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
        error_log("Staff lookup returned " . count($staff_results) . " results");
        
        // Filter to only public staff if requested
        if ($public_only) {
            $staff_results = $this->filter_public_staff($staff_results);
            error_log("After filtering for public staff: " . count($staff_results) . " results");
        }
        
        return $staff_results;
    }
    
    /**
     * Get staff count for a department using Employee IDs array or Staff field
     *
     * @param string|int $department_id Department ID number
     * @param bool $public_only Whether to count only public staff members (default: true)
     * @return int Number of staff members
     */
    public function get_department_staff_count($department_id, $public_only = true) {
        if ($public_only) {
            // Get actual staff records and count them after filtering
            $staff_members = $this->get_staff_by_department($department_id, true);
            return count($staff_members);
        }
        
        // Original logic for counting all staff (including non-public)
        $department = $this->get_department_by_id($department_id);
        
        if (!$department) {
            return 0;
        }
        
        $fields = isset($department['fields']) ? $department['fields'] : array();
        
        // Check for Employee IDs array first
        if (isset($fields['Employee IDs']) && is_array($fields['Employee IDs'])) {
            return count($fields['Employee IDs']);
        }
        
        // Check for Staff field (comma-separated string)
        if (isset($fields['Staff']) && !empty($fields['Staff'])) {
            $staff_ids = explode(',', $fields['Staff']);
            return count(array_filter(array_map('trim', $staff_ids)));
        }
        
        return 0;
    }
    
    /**
     * Get department by record ID (for internal use)
     *
     * @param string $record_id Airtable record ID
     * @return array|false Department data or false if not found
     */
    public function get_department_by_record_id($record_id) {
        $query_params = array(
            'filterByFormula' => "RECORD_ID() = '$record_id'",
            'maxRecords' => 1
        );
        
        $departments = $this->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $query_params);
        
        return !empty($departments) ? $departments[0] : false;
    }
    
    /**
     * Get employee by record ID (for internal use)
     *
     * @param string $record_id Airtable record ID
     * @return array|false Employee data or false if not found
     */
    public function get_employee_by_record_id($record_id) {
        $query_params = array(
            'filterByFormula' => "RECORD_ID() = '$record_id'",
            'maxRecords' => 1
        );
        
        $employees = $this->fetch_data(AIRTABLE_STAFF_TABLE, $query_params);
        
        return !empty($employees) ? $employees[0] : false;
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
    
    /**
     * Clear directory-specific caches (slug mappings, etc.)
     */
    public function clear_directory_cache() {
        // Clear slug mapping caches
        delete_transient('airtable_department_slugs');
        delete_transient('airtable_employee_slugs');
        delete_transient('airtable_board_slugs');
        delete_transient('airtable_board_member_slugs');
        
        // Clear general table caches
        $this->clear_cache();
        
        error_log('Cleared all directory-related caches');
    }
    
    /**
     * Get all boards and committees
     *
     * @return array Array of board records
     */
    public function get_all_boards() {
        $query_params = array(
            'fields' => array('Name', 'Logo', 'Contact Info', 'Meeting Location', 'Meeting Time', 'Board Members')
        );
        
        return $this->fetch_data(AIRTABLE_BOARDS_TABLE, $query_params);
    }
    
    /**
     * Get board by record ID
     *
     * @param string $record_id Airtable record ID
     * @return array|false Board data or false if not found
     */
    public function get_board_by_record_id($record_id) {
        $query_params = array(
            'filterByFormula' => "RECORD_ID() = '$record_id'",
            'maxRecords' => 1
        );
        
        $boards = $this->fetch_data(AIRTABLE_BOARDS_TABLE, $query_params);
        
        return !empty($boards) ? $boards[0] : false;
    }
    
    /**
     * Get board by ID field (auto-incrementing number)
     *
     * @param int $board_id Board ID number
     * @return array|false Board data or false if not found
     */
    public function get_board_by_id($board_id) {
        $query_params = array(
            'filterByFormula' => "{ID} = " . intval($board_id),
            'maxRecords' => 1
        );
        
        $boards = $this->fetch_data(AIRTABLE_BOARDS_TABLE, $query_params);
        
        return !empty($boards) ? $boards[0] : false;
    }
    
    /**
     * Get board members for a specific board
     *
     * @param string $board_record_id Board record ID
     * @return array Array of board member records
     */
    public function get_board_members($board_record_id) {
        // First get the board to find board member IDs
        $board = $this->get_board_by_record_id($board_record_id);
        
        if (!$board) {
            error_log("Board not found for record ID: " . $board_record_id);
            return array();
        }
        
        $fields = isset($board['fields']) ? $board['fields'] : array();
        $board_member_ids = array();
        
        // Check for Board Members array
        if (isset($fields['Board Members']) && is_array($fields['Board Members'])) {
            $board_member_ids = $fields['Board Members'];
            error_log("Found Board Members array: " . print_r($board_member_ids, true));
        }
        
        if (empty($board_member_ids)) {
            error_log("No board member IDs found for board " . $board_record_id);
            return array();
        }
        
        // Build filter formula for board members using record IDs
        $filter_clauses = array();
        foreach ($board_member_ids as $member_id) {
            $filter_clauses[] = "RECORD_ID() = '$member_id'";
        }
        $filter_formula = "OR(" . implode(',', $filter_clauses) . ")";
        
        error_log("Board members lookup filter formula: " . $filter_formula);
        
        $board_members_query_params = array(
            'filterByFormula' => $filter_formula,
            'fields' => array('Name', 'Role on Board', 'Representative Type', 'Notes', 'Display Order')
        );
        
        $board_members_results = $this->fetch_data(AIRTABLE_BOARD_MEMBERS_TABLE, $board_members_query_params);
        error_log("Board members lookup returned " . count($board_members_results) . " results");
        
        // Sort board members by display order
        $board_members_results = $this->sort_board_members_by_order($board_members_results);
        
        return $board_members_results;
    }
    
    /**
     * Get board member by record ID
     *
     * @param string $record_id Airtable record ID
     * @return array|false Board member data or false if not found
     */
    public function get_board_member_by_record_id($record_id) {
        $query_params = array(
            'filterByFormula' => "RECORD_ID() = '$record_id'",
            'maxRecords' => 1
        );
        
        $board_members = $this->fetch_data(AIRTABLE_BOARD_MEMBERS_TABLE, $query_params);
        
        return !empty($board_members) ? $board_members[0] : false;
    }
    
    /**
     * Get board member count for a board
     *
     * @param string $board_record_id Board record ID
     * @return int Number of board members
     */
    public function get_board_member_count($board_record_id) {
        $board = $this->get_board_by_record_id($board_record_id);
        
        if (!$board) {
            return 0;
        }
        
        $fields = isset($board['fields']) ? $board['fields'] : array();
        
        // Check for Board Members array
        if (isset($fields['Board Members']) && is_array($fields['Board Members'])) {
            return count($fields['Board Members']);
        }
        
        return 0;
    }
    
    /**
     * Sort board members by display order
     *
     * @param array $board_members Array of board member records
     * @return array Sorted array of board member records
     */
    public function sort_board_members_by_order($board_members) {
        if (empty($board_members)) {
            return $board_members;
        }
        
        usort($board_members, function($a, $b) {
            $a_fields = isset($a['fields']) ? $a['fields'] : array();
            $b_fields = isset($b['fields']) ? $b['fields'] : array();
            
            $a_order = isset($a_fields['Display Order']) ? intval($a_fields['Display Order']) : 999;
            $b_order = isset($b_fields['Display Order']) ? intval($b_fields['Display Order']) : 999;
            
            // If display order is the same, sort alphabetically by name
            if ($a_order === $b_order) {
                $a_name = isset($a_fields['Name']) ? $a_fields['Name'] : '';
                $b_name = isset($b_fields['Name']) ? $b_fields['Name'] : '';
                return strcasecmp($a_name, $b_name);
            }
            
            return $a_order - $b_order;
        });
        
        return $board_members;
    }
    
    /**
     * Clear board-specific caches
     */
    public function clear_board_cache() {
        // Clear board data caches
        $this->clear_cache(AIRTABLE_BOARDS_TABLE);
        $this->clear_cache(AIRTABLE_BOARD_MEMBERS_TABLE);
        
        // Clear board slug caches
        delete_transient('airtable_board_slugs');
        delete_transient('airtable_board_member_slugs');
        
        error_log('Cleared all board-related caches');
    }
} 
