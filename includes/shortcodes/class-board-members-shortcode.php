<?php
/**
 * Board Members Shortcode
 */
class Airtable_Directory_Board_Members_Shortcode {
    
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
        add_shortcode('board_members', array($this, 'board_members_shortcode'));
    }
    
    /**
     * Board members shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function board_members_shortcode($atts) {
        try {
            $atts = shortcode_atts(array(
                'board' => '',
                'show'  => 'name,role,representative_type,notes',
                'view'  => 'table' // New attribute for view type
            ), $atts, 'board_members');

            // Determine which fields to show in the output.
            $visible_fields = array_map('trim', explode(',', strtolower($atts['show'])));
            $view = strtolower($atts['view']);
            
            // These are the fields we want from the Board Members table.
            $fields_to_fetch = array('Name', 'Role on Board', 'Representative Type', 'Notes', 'Display Order');

            $records = array();
            
            if (!empty($atts['board'])) {
                // Check if it's a numeric ID or record ID
                $board_identifier = trim($atts['board']);
                
                // If it's numeric, treat as ID field
                if (is_numeric($board_identifier)) {
                    $board = $this->api->get_board_by_id(intval($board_identifier));
                    if ($board) {
                        $records = $this->api->get_board_members($board['id']);
                    } else {
                        return '<p>No board found.</p>';
                    }
                } else {
                    // Otherwise treat as record ID
                    $records = $this->api->get_board_members($board_identifier);
                }
            } else {
                // If no board is specified, return error
                return '<p>Please specify a board using the "board" parameter.</p>';
            }

            if (!$records) {
                return '<p>No board members found.</p>';
            }

            // Table View
            if ($view === 'table') {
                $output = '<div class="board-members-table-container">';
                $output .= '<table class="board-members-table">';
                $output .= '<thead><tr>';
                if (in_array('name', $visible_fields)) {
                    $output .= '<th>Name</th>';
                }
                if (in_array('role', $visible_fields)) {
                    $output .= '<th>Role</th>';
                }
                if (in_array('representative_type', $visible_fields)) {
                    $output .= '<th>Representative Type</th>';
                }
                if (in_array('notes', $visible_fields)) {
                    $output .= '<th>Notes</th>';
                }
                $output .= '</tr></thead><tbody>';
                
                foreach ($records as $record) {
                    $fields = isset($record['fields']) ? $record['fields'] : [];
                    $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                    $role = isset($fields['Role on Board']) ? esc_html($fields['Role on Board']) : 'No Role';
                    $representative_type = isset($fields['Representative Type']) ? esc_html($fields['Representative Type']) : '';
                    $notes = isset($fields['Notes']) ? esc_html($fields['Notes']) : '';
                    
                    $output .= '<tr>';
                    if (in_array('name', $visible_fields)) {
                        $output .= "<td>$name</td>";
                    }
                    if (in_array('role', $visible_fields)) {
                        $output .= "<td>$role</td>";
                    }
                    if (in_array('representative_type', $visible_fields)) {
                        $output .= "<td>$representative_type</td>";
                    }
                    if (in_array('notes', $visible_fields)) {
                        $output .= "<td>" . nl2br($notes) . "</td>";
                    }
                    $output .= '</tr>';
                }
                $output .= '</tbody></table></div>';
                return $output;
            }

            // Card View (default)
            $output = '<div class="board-members-directory">';
            foreach ($records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : [];

                $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $role = isset($fields['Role on Board']) ? esc_html($fields['Role on Board']) : 'No Role';
                $representative_type = isset($fields['Representative Type']) ? esc_html($fields['Representative Type']) : '';
                $notes = isset($fields['Notes']) ? esc_html($fields['Notes']) : '';

                $output .= '<div class="board-member-card">';
                
                $output .= '<div class="board-member-info">';
                
                if (in_array('name', $visible_fields)) {
                    $output .= "<h3 class='board-member-name'>$name</h3>";
                }
                
                if (in_array('role', $visible_fields)) {
                    $output .= "<div class='board-member-role'><strong>Role:</strong> $role</div>";
                }
                
                if (in_array('representative_type', $visible_fields) && !empty($representative_type)) {
                    $output .= "<div class='board-member-representative-type'><strong>Representative Type:</strong> $representative_type</div>";
                }
                
                if (in_array('notes', $visible_fields) && !empty($notes)) {
                    $output .= "<div class='board-member-notes'><strong>Notes:</strong> " . nl2br($notes) . "</div>";
                }
                
                $output .= '</div>'; // .board-member-info
                $output .= '</div>'; // .board-member-card
            }
            $output .= '</div>'; // .board-members-directory
            
            return $output;
            
        } catch (Exception $e) {
            error_log('Board members shortcode error: ' . $e->getMessage());
            return '<p>Error loading board members.</p>';
        }
    }
} 