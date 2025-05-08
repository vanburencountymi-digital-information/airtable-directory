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
     * Constructor
     *
     * @param Airtable_Directory_API $api API instance
     */
    public function __construct($api) {
        $this->api = $api;
        
        // Register shortcodes
        add_shortcode('staff_directory', array($this, 'staff_directory_shortcode'));
        add_shortcode('department_details', array($this, 'department_details_shortcode'));
    }
    
    /**
     * Staff directory shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function staff_directory_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'department' => '',
                'show'       => 'name,title,department,email,phone,photo'
            ), $atts, 'staff_directory');
    
            // Determine which fields to show in the output.
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            // These are the fields we want from the Staff table.
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Photo');
    
            $records = array();
            
            if (!empty($atts['department'])) {
                // First, fetch the department record from the Departments table using the field ID
                $department_id = trim($atts['department']);
                $department_query_params = array(
                    'filterByFormula' => "{fldwAR2a55bspWLPt} = '$department_id'"
                );
                
                $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $department_query_params);
                
                if (!$departments) {
                    return '<p>No department found.</p>';
                }
                
                $department_record = $departments[0];
                
                // Get employee IDs
                $employee_ids = array();
                if (isset($department_record['fields']['Employee IDs']) && is_array($department_record['fields']['Employee IDs'])) {
                    $employee_ids = $department_record['fields']['Employee IDs'];
                } else {
                    return '<p>No staff members found for this department.</p>';
                }
                
                if (empty($employee_ids)) {
                    return '<p>No staff members found for this department.</p>';
                }
                
                // Build filter formula
                $filter_clauses = array();
                foreach ($employee_ids as $emp_id) {
                    $filter_clauses[] = "{fldSsLnHmhFXPyJaj} = '" . $emp_id . "'";
                }
                $filter_formula = "OR(" . implode(',', $filter_clauses) . ")";
                
                $staff_query_params = array(
                    'filterByFormula' => $filter_formula,
                    'fields'          => $fields_to_fetch
                );
                
                // Fetch the staff records from the Staff table.
                $records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
            } else {
                // If no department is specified, list all staff records.
                $staff_query_params = array(
                    'fields' => $fields_to_fetch
                );
                $records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
            }
    
            if (!$records) {
                return '<p>No staff members found.</p>';
            }
    
            $output = '<div class="staff-directory">';
            foreach ($records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : [];
    
                // Add debug logging to see photo field structure
                if (isset($fields['Photo'])) {
                    error_log('Photo field structure: ' . print_r($fields['Photo'], true));
                }
    
                $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                $dept  = isset($fields['Department']) ? html_entity_decode($fields['Department']) : 'No Department';
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                
                // Updated photo URL extraction to handle different possible structures
                $photo_url = '';
                if (isset($fields['Photo'])) {
                    if (is_array($fields['Photo']) && !empty($fields['Photo'])) {
                        if (isset($fields['Photo'][0]['url'])) {
                            // Original expected format
                            $photo_url = esc_url($fields['Photo'][0]['url']);
                        } elseif (isset($fields['Photo'][0]['thumbnails']['large']['url'])) {
                            // Alternative format sometimes returned by Airtable
                            $photo_url = esc_url($fields['Photo'][0]['thumbnails']['large']['url']);
                        } elseif (isset($fields['Photo']['url'])) {
                            // Another possible format
                            $photo_url = esc_url($fields['Photo']['url']);
                        } elseif (is_string($fields['Photo'][0])) {
                            // Direct URL format
                            $photo_url = esc_url($fields['Photo'][0]);
                        }
                    } elseif (is_string($fields['Photo'])) {
                        // Direct URL string
                        $photo_url = esc_url($fields['Photo']);
                    }
                }
    
                $output .= "<div class='staff-card'>";
    
                // Photo section.
                if (in_array('photo', $visible_fields)) {
                    $output .= "<div class='staff-photo-container'>";
                    if (!empty($photo_url)) {
                        $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo'>";
                    } else {
                        $output .= "<div class='staff-photo no-photo'><span>No Photo</span></div>";
                    }
                    $output .= "</div>";
                }
    
                $output .= "<div class='staff-info'>";
                if (in_array('name', $visible_fields)) {
                    $output .= "<strong>$name</strong><br>";
                }
                if (in_array('title', $visible_fields)) {
                    $output .= "$title<br>";
                }
                if (in_array('department', $visible_fields)) {
                    $output .= "$dept<br>";
                }
                if (in_array('email', $visible_fields) && $email !== 'No Email') {
                    $output .= "Email: $email<br>";
                }
                if (in_array('phone', $visible_fields) && $phone !== 'No Phone') {
                    $output .= "Phone: $phone<br>";
                }
                $output .= "</div></div>";
            }
            $output .= '</div>';
    
            return $output;
        } catch (Exception $e) {
            error_log('Error in staff_directory_shortcode: ' . $e->getMessage());
            return '<p>An error occurred while retrieving the staff directory. Please try again later.</p>';
        }
    }
    
    /**
     * Department details shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function department_details_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'department' => '',
                'show' => 'name,address,phone,fax'  // Removed 'url' from default
            ), $atts, 'department_details');
    
            if (empty($atts['department'])) {
                return '<p>No department ID specified.</p>';
            }
    
            // Determine which fields to show in the output
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
    
            // Fetch department data
            $department_id = trim($atts['department']);
            $department_query_params = array(
                'filterByFormula' => "{fldwAR2a55bspWLPt} = '$department_id'"
            );
            
            error_log('Department details query for ID: ' . $department_id);
            $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $department_query_params);
            
            if (!$departments) {
                error_log('No department found for ID: ' . $department_id);
                return '<p>Department not found.</p>';
            }
            
            $department = $departments[0];
            $fields = isset($department['fields']) ? $department['fields'] : [];
            
            error_log('Department fields: ' . print_r($fields, true));
            
            // Extract department information
            $name = isset($fields['Department Name']) ? esc_html($fields['Department Name']) : 'Unknown Department';
            $physical_address = isset($fields['Physical Address']) ? nl2br(esc_html($fields['Physical Address'])) : 'No address available';
            $mailing_address = isset($fields['Mailing Address']) ? nl2br(esc_html($fields['Mailing Address'])) : 'No address available';
            $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No phone available';
            $fax = isset($fields['Fax']) ? esc_html($fields['Fax']) : 'No fax available';
            
            // Build the output
            $output = '<div class="department-details">';
            
            if (in_array('name', $visible_fields)) {
                $output .= '<h2 class="department-name">' . $name . '</h2>';
            }
            
            if (in_array('address', $visible_fields)) {
                $output .= '<div class="department-addresses">';
                if ($physical_address !== 'No address available') {
                    $output .= '<div class="physical-address">';
                    $output .= '<h3>Physical Address</h3>';
                    $output .= '<p>' . $physical_address . '</p>';
                    $output .= '</div>';
                }
                
                if ($mailing_address !== 'No address available' && $mailing_address !== $physical_address) {
                    $output .= '<div class="mailing-address">';
                    $output .= '<h3>Mailing Address</h3>';
                    $output .= '<p>' . $mailing_address . '</p>';
                    $output .= '</div>';
                }
                $output .= '</div>';
            }
            
            $output .= '<div class="department-contact">';
            if (in_array('phone', $visible_fields) && $phone !== 'No phone available') {
                $output .= '<p><strong>Phone:</strong> ' . $phone . '</p>';
            }
            
            if (in_array('fax', $visible_fields) && $fax !== 'No fax available') {
                $output .= '<p><strong>Fax:</strong> ' . $fax . '</p>';
            }
            $output .= '</div>';
            
            $output .= '</div>';
            
            return $output;
        } catch (Exception $e) {
            error_log('Error in department_details_shortcode: ' . $e->getMessage());
            return '<p>An error occurred while retrieving department details. Please try again later.</p>';
        }
    }
} 