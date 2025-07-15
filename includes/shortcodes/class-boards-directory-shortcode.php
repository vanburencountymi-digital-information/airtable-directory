<?php
/**
 * Boards Directory Shortcode
 */
class Airtable_Directory_Boards_Shortcode {
    
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
        add_shortcode('boards_directory', array($this, 'boards_directory_shortcode'));
    }
    
    /**
     * Boards directory shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function boards_directory_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'board' => '',
                'show'  => 'name,logo,contact_info,meeting_location,meeting_time,member_count',
                'view'  => 'table' // New attribute for view type
            ), $atts, 'boards_directory');

            // Determine which fields to show in the output.
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $view = strtolower($atts['view']);
            
            // These are the fields we want from the Boards & Committees table.
            $fields_to_fetch = array('Name', 'Logo', 'Contact Info', 'Meeting Location', 'Meeting Time', 'Board Members');

            $records = array();
            
            if (!empty($atts['board'])) {
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
                
                $records = array($board);
            } else {
                // If no board is specified, list all boards.
                $records = $this->api->get_all_boards();
            }

            if (!$records) {
                return '<p>No boards found.</p>';
            }

            // Add member count to each record
            foreach ($records as &$record) {
                $record['member_count'] = $this->api->get_board_member_count($record['id']);
            }

            // Table View
            if ($view === 'table') {
                $output = '<div class="boards-directory-table-container">';
                $output .= '<table class="boards-directory-table">';
                $output .= '<thead><tr>';
                if (in_array('logo', $visible_fields)) {
                    $output .= '<th>Logo</th>';
                }
                if (in_array('name', $visible_fields)) {
                    $output .= '<th>Name</th>';
                }
                if (in_array('contact_info', $visible_fields)) {
                    $output .= '<th>Contact Info</th>';
                }
                if (in_array('meeting_location', $visible_fields)) {
                    $output .= '<th>Meeting Location</th>';
                }
                if (in_array('meeting_time', $visible_fields)) {
                    $output .= '<th>Meeting Time</th>';
                }
                if (in_array('member_count', $visible_fields)) {
                    $output .= '<th>Members</th>';
                }
                $output .= '</tr></thead><tbody>';
                
                foreach ($records as $record) {
                    $fields = isset($record['fields']) ? $record['fields'] : [];
                    $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                    $contact_info = isset($fields['Contact Info']) ? esc_html($fields['Contact Info']) : '';
                    $meeting_location = isset($fields['Meeting Location']) ? esc_html($fields['Meeting Location']) : '';
                    $meeting_time = isset($fields['Meeting Time']) ? esc_html($fields['Meeting Time']) : '';
                    $member_count = isset($record['member_count']) ? $record['member_count'] : 0;
                    
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
                    
                    $output .= '<tr>';
                    if (in_array('logo', $visible_fields)) {
                        $output .= '<td>';
                        if (!empty($logo_url)) {
                            $output .= "<img src='$logo_url' alt='Logo of $name' class='board-logo-thumbnail'>";
                        } else {
                            $output .= "<div class='board-logo-thumbnail no-logo'><span>No Logo</span></div>";
                        }
                        $output .= '</td>';
                    }
                    if (in_array('name', $visible_fields)) {
                        $output .= "<td>$name</td>";
                    }
                    if (in_array('contact_info', $visible_fields)) {
                        $output .= "<td>" . nl2br($contact_info) . "</td>";
                    }
                    if (in_array('meeting_location', $visible_fields)) {
                        $output .= "<td>$meeting_location</td>";
                    }
                    if (in_array('meeting_time', $visible_fields)) {
                        $output .= "<td>$meeting_time</td>";
                    }
                    if (in_array('member_count', $visible_fields)) {
                        $output .= "<td>$member_count</td>";
                    }
                    $output .= '</tr>';
                }
                $output .= '</tbody></table></div>';
                return $output;
            }

            // Card View (default)
            $output = '<div class="boards-directory">';
            foreach ($records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : [];

                $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $contact_info = isset($fields['Contact Info']) ? esc_html($fields['Contact Info']) : '';
                $meeting_location = isset($fields['Meeting Location']) ? esc_html($fields['Meeting Location']) : '';
                $meeting_time = isset($fields['Meeting Time']) ? esc_html($fields['Meeting Time']) : '';
                $member_count = isset($record['member_count']) ? $record['member_count'] : 0;

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

                $output .= '<div class="board-card">';
                
                if (in_array('logo', $visible_fields) && !empty($logo_url)) {
                    $output .= '<div class="board-logo">';
                    $output .= "<img src='$logo_url' alt='Logo of $name'>";
                    $output .= '</div>';
                }
                
                $output .= '<div class="board-info">';
                
                if (in_array('name', $visible_fields)) {
                    $output .= "<h3 class='board-name'>$name</h3>";
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
                
                if (in_array('member_count', $visible_fields)) {
                    $output .= "<div class='board-member-count'><strong>Members:</strong> $member_count</div>";
                }
                
                $output .= '</div>'; // .board-info
                $output .= '</div>'; // .board-card
            }
            $output .= '</div>'; // .boards-directory
            
            return $output;
            
        } catch (Exception $e) {
            error_log('Boards directory shortcode error: ' . $e->getMessage());
            return '<p>Error loading boards directory.</p>';
        }
    }
} 