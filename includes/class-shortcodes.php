<?php
/**
 * Shortcodes implementation
 */
class Airtable_Directory_Shortcodes {
    
    /**
     * API instance
     *
     * @var Airtable_Directory_API
     */
    private $api;
    
    /**
     * Shortcode instances
     *
     * @var array
     */
    private $shortcodes = array();
    
    /**
     * Constructor
     *
     * @param Airtable_Directory_API $api API instance
     */
    public function __construct($api) {
        $this->api = $api;
        $this->load_shortcodes();
    }
    
    /**
     * Load all shortcode classes
     */
    private function load_shortcodes() {
        // Define shortcode files
        $shortcode_files = array(
            'class-staff-directory-shortcode.php',
            'class-department-details-shortcode.php',
            'class-searchable-staff-directory-shortcode.php',
            'class-department-footer-shortcode.php'
        );
        
        // Load each shortcode file
        foreach ($shortcode_files as $file) {
            $file_path = plugin_dir_path(__FILE__) . 'shortcodes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Initialize shortcode instances
        $this->shortcodes['staff_directory'] = new Airtable_Directory_Staff_Shortcode($this->api);
        $this->shortcodes['department_details'] = new Airtable_Directory_Department_Details_Shortcode($this->api);
        $this->shortcodes['searchable_staff_directory'] = new Airtable_Directory_Searchable_Staff_Shortcode($this->api);
        $this->shortcodes['department_footer'] = new Airtable_Directory_Department_Footer_Shortcode($this->api);
    }
    
    /**
     * Get shortcode instance
     *
     * @param string $shortcode_name
     * @return object|null
     */
    public function get_shortcode($shortcode_name) {
        return isset($this->shortcodes[$shortcode_name]) ? $this->shortcodes[$shortcode_name] : null;
    }
    
    /**
     * Get all shortcode instances
     *
     * @return array
     */
    public function get_shortcodes() {
        return $this->shortcodes;
    }
} 