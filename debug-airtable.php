<?php
/**
 * Temporary debug script to examine Airtable data structure
 * Add this to your WordPress functions.php temporarily or run as a separate script
 */

// Add this function temporarily to debug the data
function debug_airtable_data() {
    if (current_user_can('manage_options') && isset($_GET['debug_airtable'])) {
        // Initialize API
        require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-airtable-api.php';
        $api = new Airtable_Directory_API();
        
        echo '<h2>Debugging Airtable Data Structure</h2>';
        
        // Debug Departments
        echo '<h3>Departments Table Sample:</h3>';
        $departments = $api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, array('maxRecords' => 3));
        
        if ($departments) {
            foreach ($departments as $i => $dept) {
                echo '<h4>Department ' . ($i + 1) . ':</h4>';
                echo '<pre>';
                print_r($dept);
                echo '</pre>';
            }
        } else {
            echo '<p>No departments found!</p>';
        }
        
        // Debug Staff
        echo '<h3>Staff Table Sample:</h3>';
        $staff = $api->fetch_data(AIRTABLE_STAFF_TABLE, array('maxRecords' => 3));
        
        if ($staff) {
            foreach ($staff as $i => $employee) {
                echo '<h4>Employee ' . ($i + 1) . ':</h4>';
                echo '<pre>';
                print_r($employee);
                echo '</pre>';
            }
        } else {
            echo '<p>No staff found!</p>';
        }
        
        exit;
    }
}
add_action('init', 'debug_airtable_data');
