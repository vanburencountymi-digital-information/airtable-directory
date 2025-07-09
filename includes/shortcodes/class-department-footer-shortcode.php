<?php
/**
 * Department Footer Shortcode
 */
class Airtable_Directory_Department_Footer_Shortcode {
    
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
        add_shortcode('department_footer', array($this, 'department_footer_shortcode'));
    }
    
    /**
     * Get the department ID value by walking up the page hierarchy
     * 
     * This function walks up the page hierarchy until it finds a page that has
     * a department_id meta field, then returns the department_id value.
     * 
     * @param int $post_id Optional. The post ID to start from. Defaults to current post.
     * @return string|null The department_id value if found, null otherwise
     */
    function get_department_root_id($post_id = null) {
        // If no post_id provided, use current post
        if ($post_id === null) {
            global $post;
            if (!$post) {
                return null;
            }
            $post_id = $post->ID;
        }
        
        // Start with the current page
        $current_id = $post_id;
        
        // Walk up the hierarchy until we find a department_id or reach the top
        while ($current_id > 0) {
            // Check if current page has department_id
            $department_id = get_post_meta($current_id, 'department_id', true);
            if (!empty($department_id)) {
                return $department_id; // Return the department_id value
            }
            
            // Get the parent page
            $parent_id = wp_get_post_parent_id($current_id);
            if ($parent_id === 0) {
                // We've reached the top of the hierarchy
                break;
            }
            
            $current_id = $parent_id;
        }
        
        // No department_id found in the entire hierarchy
        return null;
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
                'show' => 'name,address,phone,fax,email,hours',
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
                // Try to get department_id by walking up the page hierarchy
                $post_id = get_the_ID();
                $page_dept_id = $this->get_department_root_id($post_id);
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
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : 'No email available';
                
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
                    $output .= '<h2 id="contact">Contact Us</h2>';
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
                
                // Phone/Fax/Email column
                $phone_fax_email_content = '';
                if (in_array('phone', $visible_fields) && $phone !== 'No phone available') {
                    $phone_fax_email_content .= '<div class="contact-item">';
                    $phone_fax_email_content .= '<h3>Phone</h3>';
                    $phone_fax_email_content .= '<p><a href="tel:' . preg_replace('/[^0-9+]/', '', $phone) . '">' . $phone . '</a></p>';
                    $phone_fax_email_content .= '</div>';
                }
                
                if (in_array('fax', $visible_fields) && $fax !== 'No fax available') {
                    $phone_fax_email_content .= '<div class="contact-item">';
                    $phone_fax_email_content .= '<h3>Fax</h3>';
                    $phone_fax_email_content .= '<p>' . $fax . '</p>';
                    $phone_fax_email_content .= '</div>';
                }

                if (in_array('email', $visible_fields) && $email !== 'No email available') {
                    $phone_fax_email_content .= '<div class="contact-item">';
                    $phone_fax_email_content .= '<h3>Email</h3>';
                    $phone_fax_email_content .= '<p><a href="mailto:' . $email . '">' . $email . '</a></p>';
                    $phone_fax_email_content .= '</div>';
                }
                
                if (!empty($phone_fax_email_content)) {
                    $output .= '<div class="department-footer-column">';
                    $output .= $phone_fax_email_content;
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