<?php
/**
 * Directory Routes Handler
 * Manages URL routing for department and employee pages
 */
class Airtable_Directory_Routes {
    
    /**
     * API instance
     *
     * @var Airtable_Directory_API
     */
    private $api;
    
    /**
     * CF7 integration instance
     *
     * @var Airtable_Directory_CF7_Integration
     */
    private $cf7;
    
    /**
     * Constructor
     *
     * @param Airtable_Directory_API $api API instance
     * @param Airtable_Directory_CF7_Integration $cf7_integration CF7 integration instance (optional)
     */
    public function __construct($api, $cf7_integration = null) {
        $this->api = $api;
        $this->cf7 = $cf7_integration;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_directory_request'));
        
        // Check and warm caches on first visit to prevent cache misses
        add_action('wp', array($this, 'maybe_warm_caches'));
        
        // Flush rewrite rules on plugin activation (we'll handle this in main plugin file)
        register_activation_hook(AIRTABLE_DIRECTORY_PLUGIN_DIR . 'airtable-directory.php', array($this, 'flush_rewrite_rules'));
    }
    
    /**
     * Add rewrite rules for directory URLs
     */
    public function add_rewrite_rules() {
        // Main directory page
        add_rewrite_rule(
            '^directory/?$',
            'index.php?directory_page=index',
            'top'
        );
        
        // Individual department or employee pages
        add_rewrite_rule(
            '^directory/([^/]+)/?$',
            'index.php?directory_page=single&directory_slug=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add custom query variables
     *
     * @param array $query_vars Existing query variables
     * @return array Modified query variables
     */
    public function add_query_vars($query_vars) {
        $query_vars[] = 'directory_page';
        $query_vars[] = 'directory_slug';
        return $query_vars;
    }
    
    /**
     * Handle directory page requests
     */
    public function handle_directory_request() {
        $directory_page = get_query_var('directory_page');
        
        if (!$directory_page) {
            return; // Not a directory request
        }
        
        // Load the template handler
        require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-directory-templates.php';
        $template_handler = new Airtable_Directory_Templates($this->api, $this->cf7);
        
        switch ($directory_page) {
            case 'index':
                $template_handler->render_directory_index();
                break;
                
            case 'single':
                $slug = get_query_var('directory_slug');
                if ($slug) {
                    // Check for redirects before processing the slug
                    $redirect_slug = $this->check_redirects($slug);
                    if ($redirect_slug && $redirect_slug !== $slug) {
                        wp_redirect(home_url('/directory/' . $redirect_slug . '/'));
                        exit;
                    }
                    
                    $template_handler->render_single_page($slug);
                } else {
                    // Redirect to directory index if no slug provided
                    wp_redirect(home_url('/directory/'));
                    exit;
                }
                break;
                
            default:
                // Unknown directory page type, redirect to index
                wp_redirect(home_url('/directory/'));
                exit;
        }
    }
    
    /**
     * Check for redirects based on slug
     *
     * @param string $slug The slug to check for redirects
     * @return string|false The redirect slug if found, false otherwise
     */
    public function check_redirects($slug) {
        // Define redirect mappings
        $redirects = array(
            'sheriff' => 'sheriffs-office'
        );
        
        return isset($redirects[$slug]) ? $redirects[$slug] : false;
    }
    
    /**
     * Generate URL-friendly slug from name
     *
     * @param string $name Name to convert to slug
     * @return string URL-friendly slug
     */
    public function generate_slug($name) {
        if (empty($name)) {
            return '';
        }
        
        // Convert to lowercase and replace spaces with hyphens
        $slug = strtolower(trim($name));
        
        // Remove special characters and replace with hyphens
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        
        // Replace multiple spaces/hyphens with single hyphen
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Get all department slugs with their IDs (for slug-to-ID mapping)
     *
     * @return array Array of slug => ID mappings
     */
    public function get_department_slug_mappings() {
        $cache_key = 'airtable_department_slugs';
        $cached_slugs = get_transient($cache_key);
        
        if ($cached_slugs !== false) {
            return $cached_slugs;
        }
        
        // Fetch all departments
        $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE);
        $slug_mappings = array();

        // Build a set of excluded Department IDs for roots (Townships, Villages, Cities) and all their descendants
        $excluded_root_names = array('Townships', 'Villages', 'Cities');
        $excluded_ids = array();
        $id_map = array();

        // Build ID map for quick lookup
        foreach ((array)$departments as $dept) {
            $fields = isset($dept['fields']) ? $dept['fields'] : array();
            $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
            if (!empty($dept_id)) {
                $id_map[$dept_id] = $dept;
            }
        }

        // Seed queue with excluded root IDs (by name)
        $queue = array();
        foreach ((array)$departments as $dept) {
            $fields = isset($dept['fields']) ? $dept['fields'] : array();
            $name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
            $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
            if (!empty($name) && in_array($name, $excluded_root_names, true) && !empty($dept_id)) {
                $queue[] = $dept_id;
            }
        }

        // BFS to collect all descendants by Parent ID
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($excluded_ids[$current])) {
                continue;
            }
            $excluded_ids[$current] = true;

            // Enqueue children
            foreach ((array)$departments as $dept) {
                $fields = isset($dept['fields']) ? $dept['fields'] : array();
                $parent_id = isset($fields['Parent ID']) ? $fields['Parent ID'] : '';
                $child_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
                if (!empty($parent_id) && $parent_id === $current && !empty($child_id) && !isset($excluded_ids[$child_id])) {
                    $queue[] = $child_id;
                }
            }
        }
        
        if ($departments) {
            foreach ($departments as $department) {
                $fields = isset($department['fields']) ? $department['fields'] : array();
                $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                // Use Department ID instead of field ID
                $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
                
                // Skip excluded roots and their descendants
                if (!empty($dept_id) && isset($excluded_ids[$dept_id])) {
                    continue;
                }

                if (!empty($dept_name) && !empty($dept_id)) {
                    $slug = $this->generate_slug($dept_name);
                    if (!empty($slug)) {
                        // Handle duplicate slugs by appending department ID
                        $original_slug = $slug;
                        $counter = 1;
                        while (array_key_exists($slug, $slug_mappings)) {
                            $slug = $original_slug . '-' . $counter;
                            $counter++;
                        }
                        
                        $slug_mappings[$slug] = array(
                            'type' => 'department',
                            'id' => $dept_id,
                            'record_id' => $department['id'], // Keep record ID for API calls
                            'name' => $dept_name
                        );
                    }
                }
            }
        }
        
        // Cache for 12 hours
        set_transient($cache_key, $slug_mappings, 12 * HOUR_IN_SECONDS);
        
        return $slug_mappings;
    }
    
    /**
     * Get all employee slugs with their IDs (for slug-to-ID mapping)
     * Only includes public employees
     *
     * @return array Array of slug => ID mappings
     */
    public function get_employee_slug_mappings() {
        $cache_key = 'airtable_employee_slugs';
        $cached_slugs = get_transient($cache_key);
        
        if ($cached_slugs !== false) {
            return $cached_slugs;
        }
        
        // Fetch all employees including Public field
        $query_params = array(
            'fields' => array('Name', 'Employee ID', 'Public')
        );
        $employees = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $query_params);
        $slug_mappings = array();
        
        if ($employees) {
            foreach ($employees as $employee) {
                $fields = isset($employee['fields']) ? $employee['fields'] : array();
                $emp_name = isset($fields['Name']) ? $fields['Name'] : '';
                // Use Employee ID instead of field ID
                $emp_id = isset($fields['Employee ID']) ? $fields['Employee ID'] : '';
                
                // Only create routes for public employees
                if (!empty($emp_name) && !empty($emp_id) && $this->api->is_staff_public($employee)) {
                    $slug = $this->generate_slug($emp_name);
                    if (!empty($slug)) {
                        // Handle duplicate slugs by appending employee ID
                        $original_slug = $slug;
                        $counter = 1;
                        while (array_key_exists($slug, $slug_mappings)) {
                            $slug = $original_slug . '-' . $counter;
                            $counter++;
                        }
                        
                        $slug_mappings[$slug] = array(
                            'type' => 'employee',
                            'id' => $emp_id,
                            'record_id' => $employee['id'], // Keep record ID for API calls
                            'name' => $emp_name
                        );
                    }
                }
            }
        }
        
        // Cache for 12 hours
        set_transient($cache_key, $slug_mappings, 12 * HOUR_IN_SECONDS);
        
        return $slug_mappings;
    }
    
    /**
     * Find what a slug represents (department or employee)
     * Now includes data validation to prevent cache inconsistencies
     *
     * @param string $slug The slug to look up
     * @return array|false Array with type, id, and name, or false if not found
     */
    public function resolve_slug($slug) {
        if (empty($slug)) {
            return false;
        }
        
        // Check departments first
        $dept_mappings = $this->get_department_slug_mappings();
        if (isset($dept_mappings[$slug])) {
            $mapping = $dept_mappings[$slug];
            
            // Validate that the department actually exists in the data
            if ($this->validate_department_exists($mapping)) {
                return $mapping;
            } else {
                // Department mapping exists but data is missing - clear cache and retry
                $this->clear_slug_cache();
                return $this->resolve_slug($slug); // Recursive call after cache clear
            }
        }
        
        // Check employees
        $emp_mappings = $this->get_employee_slug_mappings();
        if (isset($emp_mappings[$slug])) {
            $mapping = $emp_mappings[$slug];
            
            // Validate that the employee actually exists in the data
            if ($this->validate_employee_exists($mapping)) {
                return $mapping;
            } else {
                // Employee mapping exists but data is missing - clear cache and retry
                // Employee mapping exists but data is missing - clear cache and retry
                $this->clear_slug_cache();
                return $this->resolve_slug($slug); // Recursive call after cache clear
            }
        }
        
        return false;
    }
    
    /**
     * Validate that a department mapping actually corresponds to existing data
     *
     * @param array $mapping The department mapping to validate
     * @return bool True if department exists in data, false otherwise
     */
    private function validate_department_exists($mapping) {
        if (!isset($mapping['type']) || $mapping['type'] !== 'department') {
            return false;
        }
        
        // Try to fetch the department data to ensure it exists
        $dept_data = $this->api->get_department_by_name($mapping['name']);
        return $dept_data !== false;
    }
    
    /**
     * Validate that an employee mapping actually corresponds to existing data
     *
     * @param array $mapping The employee mapping to validate
     * @return bool True if employee exists in data, false otherwise
     */
    private function validate_employee_exists($mapping) {
        if (!isset($mapping['type']) || $mapping['type'] !== 'employee') {
            return false;
        }
        
        // Try to fetch the employee data to ensure it exists
        $emp_data = $this->api->get_employee_by_id($mapping['id']);
        return $emp_data !== false;
    }
    
    /**
     * Clear slug caches (useful when data changes)
     */
    public function clear_slug_cache() {
        delete_transient('airtable_department_slugs');
        delete_transient('airtable_employee_slugs');
        // Warm up caches immediately after clearing to prevent cache misses
        $this->warm_up_caches();
    }
    
    /**
     * Warm up caches to prevent initial cache misses
     * This should be called after cache clears or plugin activation
     */
    public function warm_up_caches() {
        // Pre-fetch department data to ensure it's cached
        $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE);
        
        // Pre-fetch employee data to ensure it's cached
        $query_params = array(
            'fields' => array('Name', 'Employee ID', 'Public')
        );
        $employees = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $query_params);
        
        // Generate slug mappings (this will use the now-cached data)
        $dept_mappings = $this->get_department_slug_mappings();
        $emp_mappings = $this->get_employee_slug_mappings();
    }
    
    /**
     * Check if caches are warm and warm them up if needed
     * This prevents the initial "no information found" issue
     */
    public function maybe_warm_caches() {
        // Only check on directory pages
        if (!is_page() && !is_404()) {
            return;
        }
        
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/directory') === false) {
            return;
        }
        
        // Check if department cache exists
        $dept_cache_exists = get_transient('airtable_department_slugs') !== false;
        $emp_cache_exists = get_transient('airtable_employee_slugs') !== false;
        
        // If either cache is missing, warm up all caches
        if (!$dept_cache_exists || !$emp_cache_exists) {
            $this->warm_up_caches();
        }
    }
    
    /**
     * Flush rewrite rules (call on plugin activation)
     */
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        // Rewrite rules flushed successfully
    }
}
