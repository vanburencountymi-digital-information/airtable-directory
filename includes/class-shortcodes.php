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
        add_shortcode('department_footer', array($this, 'department_footer_shortcode'));
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
    
    /**
     * Department details shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function department_details_shortcode($atts) {
        try {
            // Add geo: to allowed protocols
            add_filter('kses_allowed_protocols', function($protocols) {
                $protocols[] = 'geo';
                return $protocols;
            });
            
            $atts = shortcode_atts(array(
                'department' => '',
                'show' => 'name,photo,address,phone,fax,hours',  // Added 'photo' to default
                'show_map_link' => 'yes',  // New attribute to control map link display
                'show_staff' => 'true' // New attribute to control staff display
            ), $atts, 'department_details');

            if (empty($atts['department'])) {
                return '<p>No department ID specified.</p>';
            }

            // Determine which fields to show in the output
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $show_map_link = strtolower($atts['show_map_link']) === 'yes';
            $show_staff = strtolower($atts['show_staff']) !== 'false';

            // Split comma-separated department IDs and clean them up
            $department_ids = array_map('trim', explode(',', $atts['department']));
            
            // Remove any empty values
            $department_ids = array_filter($department_ids);
            
            if (empty($department_ids)) {
                return '<p>No valid department IDs specified.</p>';
            }

            $output = '<div class="department-details-container">';
            
            // Loop through each department ID
            foreach ($department_ids as $department_id) {
                // Fetch department data
                $department_query_params = array(
                    'filterByFormula' => "{fldwAR2a55bspWLPt} = '$department_id'"
                );
                
                error_log('Department details query for ID: ' . $department_id);
                $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $department_query_params);
                
                if (!$departments) {
                    error_log('No department found for ID: ' . $department_id);
                    $output .= '<p>Department not found for ID: ' . esc_html($department_id) . '</p>';
                    continue; // Skip to next department ID
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
                $hours = isset($fields['Hours']) ? esc_html($fields['Hours']) : 'No hours listed';
                
                // Photo URL extraction logic (same as staff photos)
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
                
                // Build the output for this department
                $output .= '<div class="department-details">';
                
                if (in_array('name', $visible_fields)) {
                    $output .= '<h2 class="department-name">' . $name . '</h2>';
                }
                
                if (in_array('photo', $visible_fields) && !empty($photo_url)) {
                    $output .= '<div class="department-photo-container">';
                    $output .= '<img src="' . $photo_url . '" alt="Photo of ' . esc_attr($name) . ' building" class="department-photo">';
                    $output .= '</div>';
                }
                
                if (in_array('address', $visible_fields)) {
                    $output .= '<div class="department-addresses">';
                    if ($physical_address !== 'No address available') {
                        $output .= '<div class="physical-address">';
                        $output .= '<h3>Physical Address</h3>';
                        $output .= '<p>' . $physical_address . '</p>';
                        
                        // Add Google Maps link if enabled
                        if ($show_map_link) {
                            // Get clean address for map query
                            $raw_address = isset($fields['Physical Address']) ? $fields['Physical Address'] : '';
                            if (!empty($raw_address)) {
                                $map_address = urlencode($raw_address);
                                $is_mobile = wp_is_mobile();
                                
                                if ($is_mobile) {
                                    // For mobile devices, use geo: URI to potentially open native map app
                                    $map_url = 'geo:0,0?q=' . $map_address;
                                } else {
                                    // For desktop, use standard Google Maps URL
                                    $map_url = 'https://www.google.com/maps?q=' . $map_address;
                                }
                                
                                $output .= '<p class="map-link"><a href="' . esc_url($map_url) . '" target="_blank" rel="noopener noreferrer">';
                                $output .= '<span class="dashicons dashicons-location"></span> View on Map</a></p>';
                            }
                        }
                        
                        $output .= '</div>';
                    }
                    
                    if ($mailing_address !== 'No address available' && $mailing_address !== $physical_address) {
                        $output .= '<div class="mailing-address">';
                        $output .= '<h3>Mailing Address</h3>';
                        $output .= '<p>' . $mailing_address . '</p>';
                        
                        // Add Google Maps link for mailing address if different and enabled
                        if ($show_map_link) {
                            $raw_address = isset($fields['Mailing Address']) ? $fields['Mailing Address'] : '';
                            if (!empty($raw_address)) {
                                $map_address = urlencode($raw_address);
                                $is_mobile = wp_is_mobile();
                                
                                if ($is_mobile) {
                                    $map_url = 'geo:0,0?q=' . $map_address;
                                } else {
                                    $map_url = 'https://www.google.com/maps?q=' . $map_address;
                                }
                                
                                $output .= '<p class="map-link"><a href="' . esc_url($map_url) . '" target="_blank" rel="noopener noreferrer">';
                                $output .= '<span class="dashicons dashicons-location"></span> View on Map</a></p>';
                            }
                        }
                        
                        $output .= '</div>';
                    }

                    
                    $output .= '</div>';
                }
                
                $output .= '<div class="department-contact">';
                if (in_array('phone', $visible_fields) && $phone !== 'No phone available') {
                    $output .= '<p><strong>Phone:</strong> <a href="tel:' . preg_replace('/[^0-9+]/', '', $phone) . '">' . $phone . '</a></p>';
                }
                
                if (in_array('fax', $visible_fields) && $fax !== 'No fax available') {
                    $output .= '<p><strong>Fax:</strong> <a href="tel:' . preg_replace('/[^0-9+]/', '', $fax) . '">' . $fax . '</a></p>';
                }

                if (in_array('hours', $visible_fields) && $hours !== 'No hours listed') {
                    $output .= '<p><strong>Hours:</strong> ' . $hours . '</p>';
                }

                $output .= '</div>';

                // --- STAFF SECTION ---
                if ($show_staff) {
                    // Get employee IDs for this department
                    $employee_ids = array();
                    if (isset($fields['Employee IDs']) && is_array($fields['Employee IDs'])) {
                        $employee_ids = $fields['Employee IDs'];
                    }
                    if (!empty($employee_ids)) {
                        // Build filter formula for staff
                        $filter_clauses = array();
                        foreach ($employee_ids as $emp_id) {
                            $filter_clauses[] = "{fldSsLnHmhFXPyJaj} = '" . $emp_id . "'";
                        }
                        $filter_formula = "OR(" . implode(',', $filter_clauses) . ")";
                        $fields_to_fetch = array('Name', 'Title', 'Photo', 'Featured');
                        $staff_query_params = array(
                            'filterByFormula' => $filter_formula,
                            'fields' => $fields_to_fetch
                        );
                        $staff_records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
                        if ($staff_records) {
                            // Separate featured and regular staff
                            $featured = array();
                            $regular = array();
                            foreach ($staff_records as $record) {
                                $f = isset($record['fields']['Featured']) ? $record['fields']['Featured'] : false;
                                if ($f === true || $f === '1' || $f === 1 || $f === 'true' || $f === 'checked') {
                                    $featured[] = $record;
                                } else {
                                    $regular[] = $record;
                                }
                            }
                            // Featured staff as cards
                            if (!empty($featured)) {
                                $output .= '<div class="department-featured-staff">';
                                foreach ($featured as $record) {
                                    $fields = isset($record['fields']) ? $record['fields'] : [];
                                    $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                                    $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
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
                                    $output .= "<div class='staff-card'>";
                                    $output .= "<div class='staff-photo-container'>";
                                    if (!empty($photo_url)) {
                                        $output .= "<img src='$photo_url' alt='Photo of $name' class='staff-photo'>";
                                    } else {
                                        $output .= "<div class='staff-photo no-photo'><span>No Photo</span></div>";
                                    }
                                    $output .= "</div>";
                                    $output .= "<div class='staff-info'>";
                                    $output .= "<strong>$name</strong><br>";
                                    $output .= "$title<br>";
                                    $output .= "</div></div>";
                                }
                                $output .= '</div>';
                            }
                            // Regular staff as simple blocks
                            if (!empty($regular)) {
                                $output .= '<div class="department-regular-staff">';
                                $output .= '<h3>Staff</h3>';
                                $output .= '<div class="staff-divider"></div>';
                                $output .= '<div class="staff-block-list">';
                                foreach ($regular as $record) {
                                    $fields = isset($record['fields']) ? $record['fields'] : [];
                                    $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                                    $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                                    $output .= "<div class='staff-block'><strong class='staff-block-name'>$name</strong><br><span class='staff-block-title'>$title</span></div>";
                                }
                                $output .= '</div>';
                                $output .= '</div>';
                            }
                        }
                    }
                }
                // --- END STAFF SECTION ---
                
                $output .= '</div>'; // End individual department-details
                
                // Add some spacing between multiple departments if there are more than one
                if (count($department_ids) > 1 && $department_id !== end($department_ids)) {
                    $output .= '<hr class="department-separator">';
                }
            }
            
            $output .= '</div>'; // End department-details-container
            
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
            
            // Fields to fetch - include Public field
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Photo', 'Public');
            
            // Get all staff members
            $staff_query_params = array(
                'fields' => $fields_to_fetch
            );
            $records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
            
            if (!$records) {
                return '<p>No staff members found.</p>';
            }
            
            // Filter to only show public staff members
            $records = $this->api->filter_public_staff($records);
            
            if (empty($records)) {
                return '<p>No public staff members found.</p>';
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
                    $output .= "Email: <a href='mailto:$email'>$email</a><br>";
                }
                if (in_array('phone', $visible_fields) && $phone !== 'No Phone') {
                    $output .= "Phone: <a href='tel:" . preg_replace('/[^0-9+]/', '', $phone) . "'>$phone</a><br>";
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
                    $output .= "<td class='column-email' data-label='Email'>" . 
                        ($email !== 'No Email' ? "<a href='mailto:$email'>$email</a>" : '') . 
                        "</td>";
                }
                
                if (in_array('phone', $visible_fields)) {
                    $output .= "<td class='column-phone' data-label='Phone'>" . 
                        ($phone !== 'No Phone' ? "<a href='tel:" . preg_replace('/[^0-9+]/', '', $phone) . "'>$phone</a>" : '') . 
                        "</td>";
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

    /**
     * Department footer shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function department_footer_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'department' => '',
                'show' => 'name,address,phone,fax,hours',
                'show_map_link' => 'yes',
                'show_staff' => 'true',
                'default_department' => '1' // Default department ID for administration building
            ), $atts, 'department_footer');

            // Determine which fields to show in the output
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $show_map_link = strtolower($atts['show_map_link']) === 'yes';
            $show_staff = strtolower($atts['show_staff']) !== 'false';

            // Get department IDs - try from attributes first, then from page meta, then use default
            $department_ids = array();
            if (!empty($atts['department'])) {
                // Split comma-separated department IDs and clean them up
                $department_ids = array_map('trim', explode(',', $atts['department']));
                // Remove any empty values
                $department_ids = array_filter($department_ids);
            } else {
                // Try to get department_id from current page
                $post_id = get_the_ID();
                $page_dept_id = get_post_meta($post_id, 'department_id', true);
                if (!empty($page_dept_id)) {
                    // Split comma-separated department IDs and clean them up
                    $department_ids = array_map('trim', explode(',', $page_dept_id));
                    // Remove any empty values
                    $department_ids = array_filter($department_ids);
                } else {
                    // Use default department ID for administration building
                    $department_ids = array($atts['default_department']);
                }
            }
            
            if (empty($department_ids)) {
                return '<div class="department-footer"><p>Department information not available.</p></div>';
            }

            // Add geo: to allowed protocols
            add_filter('kses_allowed_protocols', function($protocols) {
                $protocols[] = 'geo';
                return $protocols;
            });

            $output = '<div class="department-footer">';
            
            // Loop through each department ID
            foreach ($department_ids as $department_id) {
                // Fetch department data
                $department_query_params = array(
                    'filterByFormula' => "{fldwAR2a55bspWLPt} = '$department_id'"
                );
                
                $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $department_query_params);
                
                if (!$departments) {
                    // If department not found, skip to next one
                    continue;
                }
                
                $department = $departments[0];
                $fields = isset($department['fields']) ? $department['fields'] : [];
                
                // Extract department information
                $name = isset($fields['Department Name']) ? esc_html($fields['Department Name']) : 'Unknown Department';
                $physical_address = isset($fields['Physical Address']) ? nl2br(esc_html($fields['Physical Address'])) : 'No address available';
                $mailing_address = isset($fields['Mailing Address']) ? nl2br(esc_html($fields['Mailing Address'])) : 'No address available';
                $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No phone available';
                $fax = isset($fields['Fax']) ? esc_html($fields['Fax']) : 'No fax available';
                $hours = isset($fields['Hours']) ? esc_html($fields['Hours']) : 'No hours listed';
                
                // Photo URL extraction logic (same as staff photos)
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

                // Create department footer section with horizontal layout
                $output .= '<div class="department-footer-section">';
                
                // Header with department name
                if (in_array('name', $visible_fields)) {
                    $output .= '<div class="department-footer-header">';
                    $output .= '<div class="department-footer-title-row">';
                    $output .= '<div class="department-footer-title">';
                    $output .= '<h2>Contact Us</h2>';
                    $output .= '<p class="department-name dekoline dekoline-small">' . $name . '</p>';
                    $output .= '</div>';
                    
                    // Generate directory link using the slug system
                    $slug = $this->generate_directory_slug($name);
                    if (!empty($slug)) {
                        $directory_url = home_url('/directory/' . $slug . '/');
                        $output .= '<div class="department-footer-button">';
                        $output .= '<a href="' . esc_url($directory_url) . '" class="directory-link-btn">View Directory</a>';
                        $output .= '</div>';
                    }
                    
                    $output .= '</div>'; // End department-footer-title-row
                    $output .= '</div>';
                }
                
                // Horizontal layout for contact information
                $output .= '<div class="department-footer-content">';
                
                // Location column
                if (in_array('address', $visible_fields) && $physical_address !== 'No address available') {
                    $output .= '<div class="department-footer-column">';
                    $output .= '<h3>Location</h3>';
                    $output .= '<p>' . $physical_address . '</p>';
                    
                    // Add Google Maps link if enabled
                    if ($show_map_link) {
                        $raw_address = isset($fields['Physical Address']) ? $fields['Physical Address'] : '';
                        if (!empty($raw_address)) {
                            $map_address = urlencode($raw_address);
                            $is_mobile = wp_is_mobile();
                            
                            if ($is_mobile) {
                                $map_url = 'geo:0,0?q=' . $map_address;
                            } else {
                                $map_url = 'https://www.google.com/maps?q=' . $map_address;
                            }
                            
                            $output .= '<p><a href="' . esc_url($map_url) . '" target="_blank" rel="noopener noreferrer">Directions</a></p>';
                        }
                    }
                    $output .= '</div>';
                }
                
                // Hours column
                if (in_array('hours', $visible_fields) && $hours !== 'No hours listed') {
                    $output .= '<div class="department-footer-column">';
                    $output .= '<h3>Hours</h3>';
                    $output .= '<div class="hours-container">';
                    $output .= $hours;
                    $output .= '</div>';
                    $output .= '</div>';
                }
                
                // Phone/Fax column
                $phone_fax_content = '';
                if (in_array('phone', $visible_fields) && $phone !== 'No phone available') {
                    $phone_fax_content .= '<div class="contact-item">';
                    $phone_fax_content .= '<h3>Phone</h3>';
                    $phone_fax_content .= '<p><a href="tel:' . preg_replace('/[^0-9+]/', '', $phone) . '">' . $phone . '</a></p>';
                    $phone_fax_content .= '</div>';
                }
                
                if (in_array('fax', $visible_fields) && $fax !== 'No fax available') {
                    $phone_fax_content .= '<div class="contact-item">';
                    $phone_fax_content .= '<h3>Fax</h3>';
                    $phone_fax_content .= '<p>' . $fax . '</p>';
                    $phone_fax_content .= '</div>';
                }
                
                if (!empty($phone_fax_content)) {
                    $output .= '<div class="department-footer-column">';
                    $output .= $phone_fax_content;
                    $output .= '</div>';
                }
                
                // Staff column (if enabled)
                if ($show_staff) {
                    // Get employee IDs for this department
                    $employee_ids = array();
                    if (isset($fields['Employee IDs']) && is_array($fields['Employee IDs'])) {
                        $employee_ids = $fields['Employee IDs'];
                    }
                    if (!empty($employee_ids)) {
                        // Build filter formula for staff
                        $filter_clauses = array();
                        foreach ($employee_ids as $emp_id) {
                            $filter_clauses[] = "{fldSsLnHmhFXPyJaj} = '" . $emp_id . "'";
                        }
                        $filter_formula = "OR(" . implode(',', $filter_clauses) . ")";
                        $fields_to_fetch = array('Name', 'Title', 'Photo', 'Featured');
                        $staff_query_params = array(
                            'filterByFormula' => $filter_formula,
                            'fields' => $fields_to_fetch
                        );
                        $staff_records = $this->api->fetch_data(AIRTABLE_STAFF_TABLE, $staff_query_params);
                        if ($staff_records) {
                            // Show featured staff as contact persons
                            $featured = array();
                            foreach ($staff_records as $record) {
                                $f = isset($record['fields']['Featured']) ? $record['fields']['Featured'] : false;
                                if ($f === true || $f === '1' || $f === 1 || $f === 'true' || $f === 'checked') {
                                    $featured[] = $record;
                                }
                            }
                            
                            if (!empty($featured)) {
                                $output .= '<div class="department-footer-column">';
                                foreach ($featured as $record) {
                                    $fields = isset($record['fields']) ? $record['fields'] : [];
                                    $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                                    $title = isset($fields['Title']) ? esc_html($fields['Title']) : 'No Title';
                                    
                                    $output .= '<div class="contact-person">';
                                    $output .= '<p>' . $name . '<br><em>' . $title . '</em></p>';
                                    $output .= '</div>';
                                }
                                $output .= '</div>';
                            }
                        }
                    }
                }
                
                $output .= '</div>'; // End department-footer-content
                $output .= '</div>'; // End department-footer-section
                
                // Add separator between multiple departments
                if (count($department_ids) > 1 && $department_id !== end($department_ids)) {
                    $output .= '<div class="department-footer-separator"></div>';
                }
            }
                
            $output .= '</div>'; // End department-footer
            
            return $output;
        } catch (Exception $e) {
            error_log('Error in department_footer_shortcode: ' . $e->getMessage());
            return '<p>An error occurred while retrieving department details. Please try again later.</p>';
        }
    }

    /**
     * Generates a slug for a department name.
     *
     * @param string $name The department name.
     * @return string The generated slug.
     */
    private function generate_directory_slug($name) {
        // Convert department name to lowercase and replace spaces with hyphens
        $slug = sanitize_title($name);
        
        // Ensure uniqueness if the slug already exists
        $original_slug = $slug;
        $counter = 1;
        while (get_page_by_path($slug, OBJECT, 'directory_page')) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}

// === GLOBAL DEPARTMENT SHORTCODES FOR ELEMENTOR TEMPLATES ===
if (!function_exists('airtable_directory_get_current_department_data')) {
    function airtable_directory_get_current_department_data() {
        $post_id = get_the_ID();
        $dept_id = get_post_meta($post_id, 'department_id', true);
        if (!$dept_id) return null;
        global $airtable_directory_api;
        if (!$airtable_directory_api) {
            $airtable_directory_api = new Airtable_Directory_API();
        }
        $department = $airtable_directory_api->get_department_by_id($dept_id);
        return ($department && !empty($department['fields'])) ? $department['fields'] : null;
    }
}

add_shortcode('department_email', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Email']) ? esc_html($fields['Email']) : '';
});
add_shortcode('department_address', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Physical Address']) ? nl2br(esc_html($fields['Physical Address'])) : '';
});
add_shortcode('department_phone', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
});
add_shortcode('department_fax', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Fax']) ? esc_html($fields['Fax']) : '';
});
add_shortcode('department_hours', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Hours']) ? esc_html($fields['Hours']) : '';
});
add_shortcode('department_name', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Department Name']) ? esc_html($fields['Department Name']) : '';
});
add_shortcode('department_mailing_address', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Mailing Address']) ? nl2br(esc_html($fields['Mailing Address'])) : '';
});
add_shortcode('department_website', function () {
    $fields = airtable_directory_get_current_department_data();
    return $fields && isset($fields['Website']) ? esc_url($fields['Website']) : '';
});
add_shortcode('department_photo', function () {
    $fields = airtable_directory_get_current_department_data();
    if ($fields && isset($fields['Photo'][0]['url'])) {
        return esc_url($fields['Photo'][0]['url']);
    }
    return '';
}); 