<?php
/**
 * Board Details Shortcode
 */
class Airtable_Directory_Board_Details_Shortcode {
    
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
        add_shortcode('board_details', array($this, 'board_details_shortcode'));
    }
    
    /**
     * Board details shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function board_details_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'board' => '',
                'show'  => 'logo,contact_info,meeting_location,meeting_time,members',
                'view'  => 'card',
                'show_members' => 'true'
            ), $atts, 'board_details');

            // Determine which fields to show in the output.
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $view = strtolower($atts['view']);
            $show_members = filter_var($atts['show_members'], FILTER_VALIDATE_BOOLEAN);
            
            if (empty($atts['board'])) {
                return '<p>Please specify a board using the "board" parameter.</p>';
            }
            
            // Check if it's a numeric ID or record ID
            $board_identifier = trim($atts['board']);
            
            // If it's numeric, treat as ID field
            if (is_numeric($board_identifier)) {
                $board = $this->api->get_board_by_id(intval($board_identifier));
            } else {
                // Otherwise treat as record ID
                $board = $this->api->get_board_by_record_id($board_identifier);
            }
            
            if (!$board) {
                return '<p>No board found.</p>';
            }
            
            $fields = isset($board['fields']) ? $board['fields'] : [];
            $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
            $contact_info = isset($fields['Contact Info']) ? esc_html($fields['Contact Info']) : '';
            $meeting_location = isset($fields['Meeting Location']) ? esc_html($fields['Meeting Location']) : '';
            $meeting_time = isset($fields['Meeting Time']) ? esc_html($fields['Meeting Time']) : '';
            
            // Handle logo URL extraction
            $logo_url = '';
            if (isset($fields['Logo'])) {
                if (is_array($fields['Logo']) && !empty($fields['Logo'])) {
                    if (isset($fields['Logo'][0]['url'])) {
                        $logo_url = esc_url($fields['Logo'][0]['url']);
                    } elseif (isset($fields['Logo'][0]['thumbnails']['large']['url'])) {
                        $logo_url = esc_url($fields['Logo'][0]['thumbnails']['large']['url']);
                    }
                } elseif (is_string($fields['Logo'])) {
                    $logo_url = esc_url($fields['Logo']);
                }
            }
            
            // Get board members if requested
            $board_members = array();
            if ($show_members && in_array('members', $visible_fields)) {
                $board_members = $this->api->get_board_members($board['id']);
            }
            
            // Card View
            if ($view === 'card') {
                $output = '<div class="board-details-card">';
                
                if (in_array('logo', $visible_fields) && !empty($logo_url)) {
                    $output .= '<div class="board-logo">';
                    $output .= "<img src='$logo_url' alt='Logo of $name'>";
                    $output .= '</div>';
                }
                
                $output .= '<div class="board-info">';
                
                if (in_array('name', $visible_fields)) {
                    $output .= "<h2 class='board-name'>$name</h2>";
                }
                
                if (in_array('contact_info', $visible_fields) && !empty($contact_info)) {
                    $output .= "<div class='board-contact-info'>" . nl2br($contact_info) . "</div>";
                }
                
                if (in_array('meeting_location', $visible_fields) && !empty($meeting_location)) {
                    $output .= "<div class='board-meeting-location'><strong>Meeting Location:</strong> $meeting_location</div>";
                }
                
                if (in_array('meeting_time', $visible_fields) && !empty($meeting_time)) {
                    $output .= "<div class='board-meeting-time'><strong>Meeting Time:</strong> $meeting_time</div>";
                }
                
                // Display board members
                if ($show_members && in_array('members', $visible_fields) && !empty($board_members)) {
                    $output .= '<div class="board-members-section">';
                    $output .= '<h3>Board Members</h3>';
                    $output .= '<div class="board-members-grid">';
                    
                    foreach ($board_members as $member) {
                        $member_fields = isset($member['fields']) ? $member['fields'] : [];
                        $member_name = isset($member_fields['Name']) ? esc_html($member_fields['Name']) : 'Unknown';
                        $member_role = isset($member_fields['Role on Board']) ? esc_html($member_fields['Role on Board']) : 'No Role';
                        $representative_type = isset($member_fields['Representative Type']) ? esc_html($member_fields['Representative Type']) : '';
                        $notes = isset($member_fields['Notes']) ? esc_html($member_fields['Notes']) : '';
                        
                        $output .= '<div class="board-member-item">';
                        
                        $output .= '<div class="board-member-details">';
                        $output .= "<h4 class='board-member-name'>$member_name</h4>";
                        $output .= "<div class='board-member-role'>$member_role</div>";
                        
                        if (!empty($representative_type)) {
                            $output .= "<div class='board-member-representative-type'>$representative_type</div>";
                        }
                        
                        if (!empty($notes)) {
                            $output .= "<div class='board-member-notes'>" . nl2br($notes) . "</div>";
                        }
                        
                        $output .= '</div>'; // .board-member-details
                        $output .= '</div>'; // .board-member-item
                    }
                    
                    $output .= '</div>'; // .board-members-grid
                    $output .= '</div>'; // .board-members-section
                }
                
                $output .= '</div>'; // .board-info
                $output .= '</div>'; // .board-details-card
                
                return $output;
            }
            
            // Table View
            $output = '<div class="board-details-table">';
            if (in_array('logo', $visible_fields) && !empty($logo_url)) {
                $output .= '<div class="board-logo">';
                $output .= "<img src='$logo_url' alt='Logo of $name'>";
                $output .= '</div>';
            }
            $output .= '<table class="board-details-table-inner">';
            $output .= '<tbody>';
            
            if (in_array('name', $visible_fields)) {
                $output .= '<tr><th>Name:</th><td>' . $name . '</td></tr>';
            }
            
            if (in_array('contact_info', $visible_fields) && !empty($contact_info)) {
                $output .= '<tr><th>Contact Info:</th><td>' . nl2br($contact_info) . '</td></tr>';
            }
            
            if (in_array('meeting_location', $visible_fields) && !empty($meeting_location)) {
                $output .= '<tr><th>Meeting Location:</th><td>' . $meeting_location . '</td></tr>';
            }
            
            if (in_array('meeting_time', $visible_fields) && !empty($meeting_time)) {
                $output .= '<tr><th>Meeting Time:</th><td>' . $meeting_time . '</td></tr>';
            }
            
            if ($show_members && in_array('members', $visible_fields) && !empty($board_members)) {
                $output .= '<tr><th>Board Members:</th><td>';
                $output .= '<ul class="board-members-list">';
                foreach ($board_members as $member) {
                    $member_fields = isset($member['fields']) ? $member['fields'] : [];
                    $member_name = isset($member_fields['Name']) ? esc_html($member_fields['Name']) : 'Unknown';
                    $member_role = isset($member_fields['Role on Board']) ? esc_html($member_fields['Role on Board']) : 'No Role';
                    $output .= "<li><strong>$member_name</strong> - $member_role</li>";
                }
                $output .= '</ul>';
                $output .= '</td></tr>';
            }
            
            $output .= '</tbody></table></div>';
            
            return $output;
            
        } catch (Exception $e) {
            error_log('Board details shortcode error: ' . $e->getMessage());
            return '<p>Error loading board details.</p>';
        }
    }
} 