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

// Register and enqueue plugin styles
function airtable_directory_enqueue_styles() {
    wp_register_style(
        'airtable-directory-styles', 
        plugins_url('css/airtable-directory.css', __FILE__),
        array(),
        '1.0.0'
    );
    wp_enqueue_style('airtable-directory-styles');
}
add_action('wp_enqueue_scripts', 'airtable_directory_enqueue_styles');

function display_staff_directory($atts) {
    $atts = shortcode_atts(array(
        'department' => '',  
        'show' => 'name,title,department,email,phone,photo'  
    ), $atts, 'staff_directory');

    $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
    $records = fetch_airtable_data(AIRTABLE_TABLE_NAME);
    if (!$records) {
        return '<p>No staff members found.</p>';
    }

    $output = '<div class="staff-directory">';

    foreach ($records as $record) {
        $fields = isset($record['fields']) ? $record['fields'] : [];

        $name = isset($fields['EmployeeName']) ? esc_html($fields['EmployeeName']) : 'Unknown';
        $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
        $dept = isset($fields['Department']) ? array_map('html_entity_decode', $fields['Department']) : ['No Department'];
        $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
        $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
        $photo_url = isset($fields['Photo'][0]['url']) ? esc_url($fields['Photo'][0]['url']) : '';

        // Normalize department comparison to prevent encoding issues
        if (!empty($atts['department'])) {
            $requested_department = html_entity_decode(trim($atts['department']));
            $matched = false;
            foreach ($dept as $department_name) {
                if (strcasecmp($requested_department, $department_name) === 0) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
        }

        $output .= "<div class='staff-card'>";
        
        // Photo section
        if (in_array('photo', $visible_fields)) {
            $output .= "<div class='staff-photo-container'>";
            if (!empty($photo_url)) {
                $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo'>";
            } else {
                // Placeholder for missing photos
                $output .= "<div class='staff-photo no-photo'><span>No Photo</span></div>";
            }
            $output .= "</div>";
        }

        $output .= "<div class='staff-info'>";
        if (in_array('name', $visible_fields)) {
            $output .= "<strong>$name</strong><br>";
        }
        if (in_array('title', $visible_fields)) {
            $output .= "Title: $title<br>";
        }
        if (in_array('department', $visible_fields)) {
            $output .= "Department: " . implode(', ', $dept) . "<br>";
        }
        if (in_array('email', $visible_fields) && $email !== 'No Email') {
            $output .= "Email: $email<br>";
        }
        if (in_array('phone', $visible_fields) && $phone !== 'No Phone') {
            $output .= "Phone: $phone<br>";
        }
        $output .= "</div></div>";
    }

    $output .= '</div>'; // Close staff-directory
    return $output;
}
add_shortcode('staff_directory', 'display_staff_directory');



