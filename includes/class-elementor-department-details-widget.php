<?php
// Elementor widget for Airtable Department Details
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Airtable_Directory_Elementor_Department_Details_Widget extends Widget_Base {
    public function get_name() {
        return 'airtable_department_details';
    }
    public function get_title() {
        return __( 'Airtable Department Details', 'airtable-directory' );
    }
    public function get_icon() {
        return 'eicon-info-circle';
    }
    public function get_categories() {
        return [ 'general' ];
    }
    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'airtable-directory' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'department',
            [
                'label' => __( 'Department ID', 'airtable-directory' ),
                'type' => Controls_Manager::TEXT,
                'description' => __( 'Airtable Department Record ID (optional, will use post meta if empty)', 'airtable-directory' ),
            ]
        );
        $this->add_control(
            'show',
            [
                'label' => __( 'Fields to Show', 'airtable-directory' ),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'name' => 'Name',
                    'photo' => 'Photo',
                    'address' => 'Address',
                    'phone' => 'Phone',
                    'fax' => 'Fax',
                    'hours' => 'Hours',
                ],
                'default' => [ 'name', 'photo', 'address', 'phone', 'fax', 'hours' ],
            ]
        );
        $this->add_control(
            'show_map_link',
            [
                'label' => __( 'Show Map Link', 'airtable-directory' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Show', 'airtable-directory' ),
                'label_off' => __( 'Hide', 'airtable-directory' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        $this->add_control(
            'show_staff',
            [
                'label' => __( 'Show Staff', 'airtable-directory' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Show', 'airtable-directory' ),
                'label_off' => __( 'Hide', 'airtable-directory' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );
        $this->end_controls_section();
    }
    protected function render() {
        // Render the Elementor template using the shortcode, so all layout and scripts are handled by Elementor
        echo do_shortcode('[elementor-template id="24622"]');
    }
} 