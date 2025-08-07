<?php
/*
Plugin Name: Airtable Directory
Plugin URI: https://yourwebsite.com
Description: Custom staff directory pulling data from Airtable using separate Departments and Staff tables.
Version: 2.2
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('AIRTABLE_DIRECTORY_VERSION', '2.5');
define('AIRTABLE_DIRECTORY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIRTABLE_DIRECTORY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Set up Airtable API constants
if (!defined('AIRTABLE_API_KEY')) {
    define('AIRTABLE_API_KEY', getenv('AIRTABLE_API_KEY'));
}
if (!defined('AIRTABLE_BASE_ID')) {
    define('AIRTABLE_BASE_ID', getenv('AIRTABLE_BASE_ID'));
}
// Table constants
define('AIRTABLE_DEPARTMENT_TABLE', 'Departments');
define('AIRTABLE_STAFF_TABLE', 'Staff');
define('AIRTABLE_BOARDS_TABLE', 'tbl9tauYmY6X4gtWL');
define('AIRTABLE_BOARD_MEMBERS_TABLE', 'tbl4b5yx3bgjXOudV');
define('AIRTABLE_STAFF_TABLE_ID', 'tblGUYaSR3ePqIDGK');

// Load required files
require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-airtable-api.php';
require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-airtable-directory.php';
require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-admin.php';
require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-directory-routes.php';

// Initialize the plugin
function airtable_directory_init() {
    // Initialize API
    $api = new Airtable_Directory_API();
    
    // Initialize main plugin class
    $plugin = new Airtable_Directory($api);
    
    // Initialize shortcodes
    $shortcodes = new Airtable_Directory_Shortcodes($api);
    
    // Initialize directory routes
    $routes = new Airtable_Directory_Routes($api);
    
    // Initialize admin (only if in admin area)
    if (is_admin()) {
        $admin = new Airtable_Directory_Admin($api);
    }
}
add_action('plugins_loaded', 'airtable_directory_init');

// Plugin activation hook
register_activation_hook(__FILE__, 'airtable_directory_activation');

/**
 * Plugin activation callback
 */
function airtable_directory_activation() {
    // Initialize API and routes for activation
    $api = new Airtable_Directory_API();
    $routes = new Airtable_Directory_Routes($api);
    
    // Flush rewrite rules
    $routes->flush_rewrite_rules();
    
    error_log('Airtable Directory plugin activated');
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'airtable_directory_deactivation');

/**
 * Plugin deactivation callback
 */
function airtable_directory_deactivation() {
    // Clean up rewrite rules
    flush_rewrite_rules();
    
    error_log('Airtable Directory plugin deactivated');
}


