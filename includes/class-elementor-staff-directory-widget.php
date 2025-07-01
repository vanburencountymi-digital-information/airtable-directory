<?php
// Elementor widget for Airtable Staff Directory
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Airtable_Directory_Elementor_Staff_Directory_Widget extends Widget_Base {
    public function get_name() {
        return 'airtable_staff_directory';
    }
    public function get_title() {
        return __( 'Airtable Staff Directory', 'airtable-directory' );
    }
    public function get_icon() {
        return 'eicon-person';
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
                    'title' => 'Title',
                    'department' => 'Department',
                    'email' => 'Email',
                    'phone' => 'Phone',
                    'photo' => 'Photo',
                ],
                'default' => [ 'name', 'title', 'department', 'email', 'phone', 'photo' ],
            ]
        );
        $this->add_control(
            'view',
            [
                'label' => __( 'View Type', 'airtable-directory' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'table' => __( 'Table', 'airtable-directory' ),
                    'card' => __( 'Card', 'airtable-directory' ),
                ],
                'default' => 'table',
            ]
        );
        $this->end_controls_section();
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
        $department = $settings['department'];
        // Fallback to post meta if not set
        if (empty($department) && is_singular()) {
            $meta = get_post_meta(get_the_ID(), 'department_id', true);
            if (!empty($meta)) {
                $department = $meta;
            }
        }
        $atts = [
            'department' => $department,
            'show'       => is_array($settings['show']) ? implode(',', $settings['show']) : $settings['show'],
            'view'       => $settings['view'],
        ];
        // Call the shortcode handler directly for best compatibility
        if (class_exists('Airtable_Directory_Shortcodes')) {
            // Try to get the global API instance if available
            global $airtable_directory_api;
            if (!$airtable_directory_api) {
                $airtable_directory_api = new Airtable_Directory_API();
            }
            $shortcodes = new Airtable_Directory_Shortcodes($airtable_directory_api);
            echo $shortcodes->staff_directory_shortcode($atts);
        } else {
            // Fallback to do_shortcode
            echo do_shortcode('[staff_directory department="' . esc_attr($atts['department']) . '" show="' . esc_attr($atts['show']) . '" view="' . esc_attr($atts['view']) . '"]');
        }
    }
} 