<?php
/**
 * Department Details Shortcode
 */
class Airtable_Directory_Department_Details_Shortcode {
    
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
        add_shortcode('department_details', array($this, 'department_details_shortcode'));
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
                'show_staff' => 'true', // New attribute to control staff display
                'view' => 'table' // New attribute for view type
            ), $atts, 'department_details');

            if (empty($atts['department'])) {
                return '<p>No department ID specified.</p>';
            }

            // Determine which fields to show in the output
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $show_map_link = strtolower($atts['show_map_link']) === 'yes';
            $show_staff = strtolower($atts['show_staff']) !== 'false';
            $view = strtolower($atts['view']) === 'card' ? 'card' : ''; 

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
                $output .= '<div class="department-details ' . $view . '">';
                
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
                    // Get department name for staff lookup
                    $department_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                    if (!empty($department_name)) {
                        // Use the new method to get staff for this department
                        $staff_records = $this->api->get_staff_by_department($department_name, true);
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
} 