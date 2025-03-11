<?php
/*
Plugin Name: Airtable Directory
Plugin URI: https://yourwebsite.com
Description: Custom staff directory pulling data from Airtable.
Version: 1.0
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Use constants instead of variables
define('AIRTABLE_API_KEY', defined('AIRTABLE_API_KEY') ? AIRTABLE_API_KEY : getenv('AIRTABLE_API_KEY'));
define('AIRTABLE_BASE_ID', defined('AIRTABLE_BASE_ID') ? AIRTABLE_BASE_ID : getenv('AIRTABLE_BASE_ID'));
define('AIRTABLE_TABLE_NAME', 'Employees');
function fetch_airtable_data($table) {
    // Ensure global constants are used
    $api_key = AIRTABLE_API_KEY;
    $base_id = AIRTABLE_BASE_ID;

    $url = "https://api.airtable.com/v0/" . $base_id . "/" . urlencode($table);
    
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        )
    );

    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['records']) ? $data['records'] : [];
}

function display_staff_directory($atts) {
    $atts = shortcode_atts(array(
        'department' => '' // Default: show all
    ), $atts, 'staff_directory');

    $records = fetch_airtable_data(AIRTABLE_TABLE_NAME);
    if (!$records) {
        return '<p>No staff members found.</p>';
    }

    $output = '<div class="staff-directory"><ul class="staff-list">';

    foreach ($records as $record) {
        $fields = isset($record['fields']) ? $record['fields'] : [];
        
        // Update field names to match Airtable
        $name = isset($fields['EmployeeName']) ? esc_html($fields['EmployeeName']) : 'Unknown';
        $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
        $dept = isset($fields['Department']) ? esc_html($fields['Department']) : 'No Department';
        $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
        $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';

        // Filter by department if specified
        if (!empty($atts['department']) && strtolower($dept) !== strtolower($atts['department'])) {
            continue;
        }

        $output .= "<li><strong>$name</strong><br>Title: $title<br>Department: $dept";
        
        // Only show Email/Phone if they exist
        if ($email !== 'No Email') {
            $output .= "<br>Email: $email";
        }
        if ($phone !== 'No Phone') {
            $output .= "<br>Phone: $phone";
        }

        $output .= "</li>";
    }

    $output .= '</ul></div>';

    return $output;
}
add_shortcode('staff_directory', 'display_staff_directory');

