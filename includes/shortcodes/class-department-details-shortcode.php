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
                
        
                $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE, $department_query_params);
                
                if (!$departments) {
                    $output .= '<p>Department not found for ID: ' . esc_html($department_id) . '</p>';
                    continue; // Skip to next department ID
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
                $additional_info_raw = isset($fields['Additional Information']) ? $fields['Additional Information'] : '';
                $additional_info = '';
                if (!empty($additional_info_raw)) {
                    // Escape first, then convert markdown links, then basic markdown, then linkify emails/phones
                    $escaped = esc_html($additional_info_raw);
                    $markdown_links = $this->convert_markdown_links($escaped);
                    // Convert basic markdown (bold/italic) after escaping, then linkify
                    if (method_exists($this, 'format_basic_markdown')) {
                        $markdown_links = $this->format_basic_markdown($markdown_links);
                    }
                    $additional_info = nl2br($this->linkify_contact_info($markdown_links));
                }
                
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

                // Additional Information section (if present)
                if (!empty($additional_info)) {
                    $output .= '<div class="department-additional-information">';
                    $output .= '<div class="additional-info">' . wp_kses_post($additional_info) . '</div>';
                    $output .= '</div>';
                }

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
            return '<p>An error occurred while retrieving department details. Please try again later.</p>';
        }
    }
    
    /**
     * Linkify emails and phone numbers in a block of text.
     * Assumes text is already escaped.
     *
     * @param string $text
     * @return string
     */
    private function linkify_contact_info($text) {
        // Linkify emails
        $text = preg_replace_callback(
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,})/',
            function ($m) {
                $email = $m[1];
                return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            },
            $text
        );

        // Linkify US-style phone numbers with optional country code and extensions
        $phone_pattern = '/\b(?:\+?1[\s\.-]?)?\(?\d{3}\)?[\s\.-]?\d{3}[\s\.-]?\d{4}\b(?:\s*(?:x|ext\.?|extension)\s*\d{1,5})?/i';
        $text = preg_replace_callback(
            $phone_pattern,
            function ($m) {
                $display = $m[0];
                $tel = preg_replace('/[^0-9+]/', '', $display);
                return '<a href="tel:' . esc_attr($tel) . '">' . esc_html($display) . '</a>';
            },
            $text
        );

        return $text;
    }

    /**
     * Convert markdown-style links [text](url) to HTML links.
     * This should be called after escaping to work with escaped text.
     *
     * @param string $text
     * @return string
     */
    private function convert_markdown_links($text) {
        // Convert markdown links [text](url) to HTML links
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function ($matches) {
                $link_text = $matches[1];
                $url = $matches[2];
                
                // Basic URL validation and sanitization
                if (filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'http') === 0 || strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) {
                    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
                } else {
                    // If URL doesn't start with http/https/mailto/tel, assume it's relative or add https
                    if (strpos($url, '/') === 0) {
                        // Relative URL
                        return '<a href="' . esc_url($url) . '">' . $link_text . '</a>';
                    } else {
                        // Assume external URL and add https
                        return '<a href="' . esc_url('https://' . $url) . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
                    }
                }
            },
            $text
        );
        
        return $text;
    }

    /**
     * Very basic markdown formatter for bold and italics after escaping.
     * Supports **bold**, __bold__, *italic*, _italic_.
     */
    private function format_basic_markdown($text) {
        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
        // Italic: *text* or _text_
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<em>$1</em>', $text);
        return $text;
    }
} 