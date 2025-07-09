<?php
/**
 * Staff Directory Shortcode
 */
class Airtable_Directory_Staff_Shortcode {
    
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
        add_shortcode('staff_directory', array($this, 'staff_directory_shortcode'));
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
                'show'       => 'name,title,department,email,phone,photo',
                'view'       => 'table' // New attribute for view type
            ), $atts, 'staff_directory');
    
            // Determine which fields to show in the output.
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $view = strtolower($atts['view']);
            // These are the fields we want from the Staff table.
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Photo', 'Public');
    
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
    
            // Filter to only show public staff members
            $records = $this->api->filter_public_staff($records);
            
            if (empty($records)) {
                return '<p>No public staff members found.</p>';
            }

            // Minimal Table View
            if ($view === 'table') {
                $output = '<div class="staff-directory-table-container">';
                $output .= '<table class="staff-directory-table">';
                $output .= '<thead><tr>';
                if (in_array('photo', $visible_fields)) {
                    $output .= '<th>Photo</th>';
                }
                if (in_array('name', $visible_fields)) {
                    $output .= '<th>Name</th>';
                }
                if (in_array('title', $visible_fields)) {
                    $output .= '<th>Title</th>';
                }
                if (in_array('department', $visible_fields)) {
                    $output .= '<th>Department</th>';
                }
                if (in_array('email', $visible_fields)) {
                    $output .= '<th>Email</th>';
                }
                if (in_array('phone', $visible_fields)) {
                    $output .= '<th>Phone</th>';
                }
                $output .= '</tr></thead><tbody>';
                foreach ($records as $record) {
                    $fields = isset($record['fields']) ? $record['fields'] : [];
                    $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                    $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                    $dept  = isset($fields['Department']) ? html_entity_decode($fields['Department']) : 'No Department';
                    $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                    $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                    $photo_url = '';
                    if (isset($fields['Photo'])) {
                        if (is_array($fields['Photo']) && !empty($fields['Photo'])) {
                            if (isset($fields['Photo'][0]['url'])) {
                                $photo_url = esc_url($fields['Photo'][0]['url']);
                            } elseif (isset($fields['Photo'][0]['thumbnails']['large']['url'])) {
                                $photo_url = esc_url($fields['Photo'][0]['thumbnails']['large']['url']);
                            } elseif (isset($fields['Photo']['url'])) {
                                $photo_url = esc_url($fields['Photo']['url']);
                            } elseif (is_string($fields['Photo'][0])) {
                                $photo_url = esc_url($fields['Photo'][0]);
                            }
                        } elseif (is_string($fields['Photo'])) {
                            $photo_url = esc_url($fields['Photo']);
                        }
                    }
                    $output .= '<tr>';
                    if (in_array('photo', $visible_fields)) {
                        $output .= '<td>';
                        if (!empty($photo_url)) {
                            $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo-thumbnail'>";
                        } else {
                            $output .= "<div class='staff-photo-thumbnail no-photo'><span>No Photo</span></div>";
                        }
                        $output .= '</td>';
                    }
                    if (in_array('name', $visible_fields)) {
                        $output .= "<td>$name</td>";
                    }
                    if (in_array('title', $visible_fields)) {
                        $output .= "<td>$title</td>";
                    }
                    if (in_array('department', $visible_fields)) {
                        $output .= "<td>$dept</td>";
                    }
                    if (in_array('email', $visible_fields)) {
                        $output .= ($email !== 'No Email' ? "<td><a href='mailto:$email'>$email</a></td>" : '<td></td>');
                    }
                    if (in_array('phone', $visible_fields)) {
                        $output .= ($phone !== 'No Phone' ? "<td><a href='tel:" . preg_replace('/[^0-9+]/', '', $phone) . "'>$phone</a></td>" : '<td></td>');
                    }
                    $output .= '</tr>';
                }
                $output .= '</tbody></table></div>';
                return $output;
            }

            // Card View (legacy, if view is not table)
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
                    $output .= "<a href='mailto:$email'>$email</a><br>";
                }
                if (in_array('phone', $visible_fields) && $phone !== 'No Phone') {
                    $output .= "<a href='tel:" . preg_replace('/[^0-9+]/', '', $phone) . "'>$phone</a><br>";
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
} 