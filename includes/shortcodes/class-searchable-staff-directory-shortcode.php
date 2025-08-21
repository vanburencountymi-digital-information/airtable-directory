<?php
/**
 * Searchable Staff Directory Shortcode
 */
class Airtable_Directory_Searchable_Staff_Shortcode {
    
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
        add_shortcode('searchable_staff_directory', array($this, 'searchable_staff_directory_shortcode'));
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
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Phone Extension', 'Show Email As', 'Photo', 'Public');
            
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
                $dept = 'No Department';
                if (isset($fields['Departments']) && is_array($fields['Departments'])) {
                    $department_names = array();
                    foreach ($fields['Departments'] as $dept_record_id) {
                        $dept_record = $this->api->get_department_by_record_id($dept_record_id);
                        if ($dept_record && isset($dept_record['fields']['Department Name'])) {
                            $department_names[] = $dept_record['fields']['Department Name'];
                        }
                    }
                    $dept = !empty($department_names) ? implode(', ', $department_names) : 'No Department';
                }
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                $email_text = isset($fields['Show Email As']) && trim($fields['Show Email As']) !== '' ? esc_html($fields['Show Email As']) : $email;
                $base_phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                $phone_ext = isset($fields['Phone Extension']) ? trim((string)$fields['Phone Extension']) : '';
                $display_phone = $base_phone;
                if ($base_phone !== 'No Phone' && $phone_ext !== '') {
                    $display_phone .= ' Ext. ' . esc_html($phone_ext);
                }
                
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
                    $output .= "Email: <a href='mailto:$email'>" . $email_text . "</a><br>";
                }
                if (in_array('phone', $visible_fields) && $base_phone !== 'No Phone') {
                    $output .= "Phone: <a href='tel:" . preg_replace('/[^0-9+]/', '', $base_phone) . "'>" . $display_phone . "</a><br>";
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
                $dept = 'No Department';
                if (isset($fields['Departments']) && is_array($fields['Departments'])) {
                    $department_names = array();
                    foreach ($fields['Departments'] as $dept_record_id) {
                        $dept_record = $this->api->get_department_by_record_id($dept_record_id);
                        if ($dept_record && isset($dept_record['fields']['Department Name'])) {
                            $department_names[] = $dept_record['fields']['Department Name'];
                        }
                    }
                    $dept = !empty($department_names) ? implode(', ', $department_names) : 'No Department';
                }
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No Email';
                $email_text = isset($fields['Show Email As']) && trim($fields['Show Email As']) !== '' ? esc_html($fields['Show Email As']) : $email;
                $base_phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : 'No Phone';
                $phone_ext = isset($fields['Phone Extension']) ? trim((string)$fields['Phone Extension']) : '';
                $display_phone = $base_phone;
                if ($base_phone !== 'No Phone' && $phone_ext !== '') {
                    $display_phone .= ' Ext. ' . esc_html($phone_ext);
                }
                
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
                        ($email !== 'No Email' ? "<a href='mailto:$email'>" . $email_text . "</a>" : '') . 
                        "</td>";
                }
                
                if (in_array('phone', $visible_fields)) {
                    $output .= "<td class='column-phone' data-label='Phone'>" . 
                        ($base_phone !== 'No Phone' ? "<a href='tel:" . preg_replace('/[^0-9+]/', '', $base_phone) . "'>" . $display_phone . "</a>" : '') . 
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
            return '<p>An error occurred while retrieving the staff directory. Please try again later.</p>';
        }
    }
} 