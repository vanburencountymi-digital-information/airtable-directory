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
        add_shortcode('searchable_staff_directory', array($this, 'searchable_staff_directory_shortcode'));
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

    /**
     * Searchable staff directory shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function searchable_staff_directory_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'show' => 'name,title,department,email,phone,photo',
                'per_page' => 20,
                'default_view' => 'card' // New attribute for default view (card or table)
            ), $atts, 'searchable_staff_directory');

            // Determine which fields to show in the output
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $per_page = intval($atts['per_page']);
            $default_view = in_array($atts['default_view'], array('card', 'table')) ? $atts['default_view'] : 'card';
            
            // Fields to fetch
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Photo');
            
            // Get all staff members
            $staff_query_params = array(
                'fields' => $fields_to_fetch
            );
            $records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
            
            if (!$records) {
                return '<p>No staff members found.</p>';
            }
            
            // Create unique ID for this instance
            $directory_id = 'staff-directory-' . uniqid();
            
            // Search form and view toggle
            $output = '<div class="searchable-staff-directory" id="' . $directory_id . '" data-default-view="' . $default_view . '" data-per-page="' . $per_page . '">';
            
            // Control bar with search and view toggle
            $output .= '<div class="directory-control-bar">';
            
            // Search container
            $output .= '<div class="search-container">';
            $output .= '<input type="text" id="search-' . $directory_id . '" class="staff-search" placeholder="Search by name, title, or department...">';
            $output .= '<div class="search-filters">';
            $output .= '<label><input type="checkbox" class="filter-checkbox" data-filter="name" checked> Name</label>';
            $output .= '<label><input type="checkbox" class="filter-checkbox" data-filter="title" checked> Title</label>';
            $output .= '<label><input type="checkbox" class="filter-checkbox" data-filter="department" checked> Department</label>';
            $output .= '</div>';
            $output .= '</div>';
            
            // View toggle buttons
            $output .= '<div class="view-toggle-container">';
            $output .= '<span>View: </span>';
            $output .= '<button class="view-toggle-btn card-view-btn ' . ($default_view == 'card' ? 'active' : '') . '" data-view="card" title="Card View"><span class="dashicons dashicons-grid-view"></span> Cards</button>';
            $output .= '<button class="view-toggle-btn table-view-btn ' . ($default_view == 'table' ? 'active' : '') . '" data-view="table" title="Table View"><span class="dashicons dashicons-list-view"></span> Table</button>';
            $output .= '</div>';
            
            $output .= '</div>'; // End directory-control-bar
            
            // Card view container
            $output .= '<div class="card-view-container' . ($default_view == 'card' ? ' active' : '') . '">';
            $output .= '<div class="staff-directory-results card-layout">';
            
            // Process the staff data for card view
            foreach ($records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : [];
                
                $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                $dept = isset($fields['Department']) ? html_entity_decode($fields['Department']) : 'No Department';
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                
                // Photo URL extraction (using your existing code)
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
                
                // Create the staff card with data attributes for searching
                $output .= "<div class='staff-card' data-name='" . strtolower($name) . "' data-title='" . strtolower($title) . "' data-department='" . strtolower($dept) . "'>";
                
                // Photo section
                if (in_array('photo', $visible_fields)) {
                    $output .= "<div class='staff-photo-container'>";
                    if (!empty($photo_url)) {
                        $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo'>";
                    } else {
                        $output .= "<div class='staff-photo no-photo'><span>No Photo</span></div>";
                    }
                    $output .= "</div>";
                }
                
                // Staff info
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
            
            $output .= '</div>'; // End staff-directory-results
            $output .= '</div>'; // End card-view-container
            
            // Table view container
            $output .= '<div class="table-view-container' . ($default_view == 'table' ? ' active' : '') . '">';
            $output .= '<table class="staff-directory-table">';
            
            // Table headers
            $output .= '<thead><tr>';
            
            if (in_array('photo', $visible_fields)) {
                $output .= '<th class="column-photo">Photo</th>';
            }
            if (in_array('name', $visible_fields)) {
                $output .= '<th class="column-name">Name</th>';
            }
            if (in_array('title', $visible_fields)) {
                $output .= '<th class="column-title">Title</th>';
            }
            if (in_array('department', $visible_fields)) {
                $output .= '<th class="column-department">Department</th>';
            }
            if (in_array('email', $visible_fields)) {
                $output .= '<th class="column-email">Email</th>';
            }
            if (in_array('phone', $visible_fields)) {
                $output .= '<th class="column-phone">Phone</th>';
            }
            
            $output .= '</tr></thead>';
            $output .= '<tbody>';
            
            // Table rows
            foreach ($records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : [];
                
                $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                $dept = isset($fields['Department']) ? html_entity_decode($fields['Department']) : 'No Department';
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                
                // Photo URL extraction
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
                
                // Create the table row with data attributes for searching
                $output .= "<tr class='staff-row' data-name='" . strtolower($name) . "' data-title='" . strtolower($title) . "' data-department='" . strtolower($dept) . "'>";
                
                if (in_array('photo', $visible_fields)) {
                    $output .= "<td class='column-photo' data-label='Photo'>";
                    if (!empty($photo_url)) {
                        $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo-thumbnail'>";
                    } else {
                        $output .= "<div class='staff-photo-thumbnail no-photo'><span>No Photo</span></div>";
                    }
                    $output .= "</td>";
                }
                
                if (in_array('name', $visible_fields)) {
                    $output .= "<td class='column-name' data-label='Name'>$name</td>";
                }
                
                if (in_array('title', $visible_fields)) {
                    $output .= "<td class='column-title' data-label='Title'>$title</td>";
                }
                
                if (in_array('department', $visible_fields)) {
                    $output .= "<td class='column-department' data-label='Department'>$dept</td>";
                }
                
                if (in_array('email', $visible_fields)) {
                    $output .= "<td class='column-email' data-label='Email'>" . ($email !== 'No Email' ? $email : '') . "</td>";
                }
                
                if (in_array('phone', $visible_fields)) {
                    $output .= "<td class='column-phone' data-label='Phone'>" . ($phone !== 'No Phone' ? $phone : '') . "</td>";
                }
                
                $output .= "</tr>";
            }
            
            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</div>'; // End table-view-container
            
            // Pagination controls
            $output .= '<div class="pagination-controls">';
            $output .= '<div class="pagination-info">Showing <span class="showing-count">0</span> of <span class="total-count">0</span> staff members</div>';
            
            $output .= '<div class="pagination-buttons">';
            $output .= '<button class="prev-page">&laquo; Previous</button>';
            $output .= '<div class="page-info">Page <span class="current-page">1</span> of <span class="total-pages">1</span></div>';
            $output .= '<button class="next-page">Next &raquo;</button>';
            $output .= '</div>';
            
            $output .= '</div>'; // End pagination-controls
            
            $output .= '</div>'; // End searchable-staff-directory

            return $output;
        } catch (Exception $e) {
            error_log('Error in searchable_staff_directory_shortcode: ' . $e->getMessage());
            return '<p>An error occurred while retrieving the staff directory. Please try again later.</p>';
        }
    }
} 