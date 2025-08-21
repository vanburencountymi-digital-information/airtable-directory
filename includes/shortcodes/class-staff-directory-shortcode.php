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
            $fields_to_fetch = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Phone Extension', 'Show Email As', 'Photo', 'Public');
    
            $records = array();
            
            if (!empty($atts['department'])) {
                // Use the new structure - department parameter is now the department name
                $department_name = trim($atts['department']);
                
                // Get staff members for this department using the new method
                $records = $this->api->get_staff_by_department($department_name, true);
                
                if (empty($records)) {
                    return '<p>No staff members found for this department.</p>';
                }
                
                // Override visible fields based on department settings
                $department_show_fields = $this->api->get_department_show_fields($department_name);
                
                // If department show fields is empty (explicitly set to ['None']), hide all contact fields
                if (empty($department_show_fields)) {
                    $visible_fields = array_diff($visible_fields, array('phone', 'email'));
                } else {
                    if (in_array('Phone', $department_show_fields)) {
                        if (!in_array('phone', $visible_fields)) {
                            $visible_fields[] = 'phone';
                        }
                    } else {
                        $visible_fields = array_diff($visible_fields, array('phone'));
                    }
                    if (in_array('Email', $department_show_fields)) {
                        if (!in_array('email', $visible_fields)) {
                            $visible_fields[] = 'email';
                        }
                    } else {
                        $visible_fields = array_diff($visible_fields, array('email'));
                    }
                }
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
                    if ($base_phone !== 'No Phone' && $phone_ext !== '' && in_array('Phone Extension', $department_show_fields)) {
                        $display_phone .= ' Ext. ' . esc_html($phone_ext);
                    }
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
                        $output .= ($email !== 'No Email' ? "<td><a href='mailto:$email'>" . $email_text . "</a></td>" : '<td></td>');
                    }
                    if (in_array('phone', $visible_fields)) {
                        $output .= ($base_phone !== 'No Phone' ? "<td><a href='tel:" . preg_replace('/[^0-9+]/', '', $base_phone) . "'>" . $display_phone . "</a></td>" : '<td></td>');
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
    
                }
    
                $name  = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
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
                // Use department show fields if available, otherwise show all
                $show_phone_ext = true; // Default to showing extensions
                if (!empty($atts['department'])) {
                    $show_phone_ext = !empty($department_show_fields) && in_array('Phone Extension', $department_show_fields);
                }
                if ($base_phone !== 'No Phone' && $phone_ext !== '' && $show_phone_ext) {
                    $display_phone .= ' Ext. ' . esc_html($phone_ext);
                }
    
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
                    $output .= "<a href='mailto:$email'>" . $email_text . "</a><br>";
                }
                if (in_array('phone', $visible_fields) && $base_phone !== 'No Phone') {
                    $output .= "<a href='tel:" . preg_replace('/[^0-9+]/', '', $base_phone) . "'>" . $display_phone . "</a><br>";
                }
                $output .= "</div></div>";
            }
            $output .= '</div>';
    
            return $output;
        } catch (Exception $e) {
            return '<p>An error occurred while retrieving the staff directory. Please try again later.</p>';
        }
    }
} 