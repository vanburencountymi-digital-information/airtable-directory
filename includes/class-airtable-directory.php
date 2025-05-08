<?php
/**
 * Main plugin class
 */
class Airtable_Directory {
    
    /**
     * API instance
     *
     * @var Airtable_Directory_API
     */
    private $api;
    
    /**
     * Constructor
     *
     * @param Airtable_Directory_API $api API instance
     */
    public function __construct($api) {
        $this->api = $api;
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    /**
     * Enqueue plugin styles.
     */
    public function enqueue_styles() {
        wp_register_style(
            'airtable-directory-styles', 
            AIRTABLE_DIRECTORY_PLUGIN_URL . 'assets/css/airtable-directory.css',
            array(),
            AIRTABLE_DIRECTORY_VERSION
        );
        wp_enqueue_style('airtable-directory-styles');
    }
} 