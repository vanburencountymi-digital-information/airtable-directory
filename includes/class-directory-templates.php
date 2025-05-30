<?php
/**
 * Directory Templates Handler
 * Renders directory pages using existing shortcode functionality
 */
class Airtable_Directory_Templates {
    
    /**
     * API instance
     *
     * @var Airtable_Directory_API
     */
    private $api;
    
    /**
     * Routes instance
     *
     * @var Airtable_Directory_Routes
     */
    private $routes;
    
    /**
     * Shortcodes instance
     *
     * @var Airtable_Directory_Shortcodes
     */
    private $shortcodes;
    
    /**
     * Constructor
     *
     * @param Airtable_Directory_API $api API instance
     */
    public function __construct($api) {
        $this->api = $api;
        
        // Initialize routes handler
        require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-directory-routes.php';
        $this->routes = new Airtable_Directory_Routes($api);
        
        // Initialize shortcodes handler to reuse existing functionality
        require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-shortcodes.php';
        $this->shortcodes = new Airtable_Directory_Shortcodes($api);
    }
    
    /**
     * Render the main directory index page
     */
    public function render_directory_index() {
        // Set up the page
        $this->setup_directory_page('Directory', 'Browse our organizational directory');
        
        // Get all departments for the index
        $departments = $this->api->fetch_data(AIRTABLE_DEPARTMENT_TABLE);
        
        ob_start();
        ?>
        <div class="directory-index">
            <div class="directory-intro">
                <h1>Organization Directory</h1>
                <p>Browse departments and staff members in our organization.</p>
            </div>
            
            <?php if ($departments): ?>
                <div class="department-grid">
                    <?php 
                    // Group departments by parent/child relationship using Parent ID
                    $parent_departments = array();
                    $child_departments = array();
                    
                    foreach ($departments as $dept) {
                        $fields = isset($dept['fields']) ? $dept['fields'] : array();
                        $parent_id = isset($fields['Parent ID']) ? $fields['Parent ID'] : '';
                        
                        if (empty($parent_id)) {
                            $parent_departments[] = $dept;
                        } else {
                            if (!isset($child_departments[$parent_id])) {
                                $child_departments[$parent_id] = array();
                            }
                            $child_departments[$parent_id][] = $dept;
                        }
                    }
                    
                    // Display parent departments first
                    foreach ($parent_departments as $dept) {
                        $this->render_department_card($dept, $child_departments);
                    }
                    ?>
                </div>
            <?php else: ?>
                <p>No departments found.</p>
            <?php endif; ?>
        </div>
        <?php
        
        $content = ob_get_clean();
        $this->output_directory_page($content);
    }
    
    /**
     * Render a single page (department or employee)
     *
     * @param string $slug The slug to render
     */
    public function render_single_page($slug) {
        $resolved = $this->routes->resolve_slug($slug);
        
        if (!$resolved) {
            // Slug not found, show 404
            $this->render_404();
            return;
        }
        
        switch ($resolved['type']) {
            case 'department':
                $this->render_department_page($resolved['id'], $resolved['name']);
                break;
                
            case 'employee':
                $this->render_employee_page($resolved['id'], $resolved['name']);
                break;
                
            default:
                $this->render_404();
                break;
        }
    }
    
    /**
     * Render a department page with staff and child departments
     *
     * @param string|int $department_id Department ID number
     * @param string $department_name Department name
     */
    public function render_department_page($department_id, $department_name) {
        $this->setup_directory_page($department_name, "Department information and staff directory");
        
        ob_start();
        ?>
        <div class="directory-department-page">
            <div class="directory-breadcrumbs">
                <a href="<?php echo home_url('/directory/'); ?>">Directory</a> &raquo; 
                <span><?php echo esc_html($department_name); ?></span>
            </div>
            
            <?php
            error_log("Rendering department page for ID: " . $department_id . " Name: " . $department_name);
            
            // Get department data for details display
            $department_data = $this->api->get_department_by_id($department_id);
            if ($department_data) {
                $this->render_department_details($department_data);
            } else {
                echo '<p>Department details not found.</p>';
                error_log("Department data not found for ID: " . $department_id);
            }
            
            // Show staff members in this department
            echo '<h3>Staff Members</h3>';
            $staff_members = $this->api->get_staff_by_department($department_id);
            error_log("Found " . count($staff_members) . " staff members for department " . $department_id);
            
            if ($staff_members && count($staff_members) > 0) {
                $this->render_staff_grid($staff_members);
            } else {
                echo '<p>No staff members found for this department.</p>';
            }
            
            // Show child departments if any
            $child_departments = $this->api->get_child_departments($department_id);
            error_log("Found " . count($child_departments) . " child departments for department " . $department_id);
            
            if (!empty($child_departments)) {
                echo '<h3>Sub-Departments</h3>';
                
                foreach ($child_departments as $child_dept) {
                    $child_fields = isset($child_dept['fields']) ? $child_dept['fields'] : array();
                    $child_id = isset($child_fields['Department ID']) ? $child_fields['Department ID'] : '';
                    $child_name = isset($child_fields['Department Name']) ? $child_fields['Department Name'] : 'Unknown Department';
                    
                    if (!empty($child_id)) {
                        echo '<div class="child-department-section">';
                        
                        // Department details for child
                        $this->render_department_details($child_dept);
                        
                        // Staff in child department
                        echo '<h4>' . esc_html($child_name) . ' Staff</h4>';
                        $child_staff = $this->api->get_staff_by_department($child_id);
                        if ($child_staff && count($child_staff) > 0) {
                            $this->render_staff_grid($child_staff);
                        } else {
                            echo '<p>No staff members found for this sub-department.</p>';
                        }
                        
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
        <?php
        
        $content = ob_get_clean();
        $this->output_directory_page($content);
    }
    
    /**
     * Render an employee page
     *
     * @param string|int $employee_id Employee ID number
     * @param string $employee_name Employee name
     */
    public function render_employee_page($employee_id, $employee_name) {
        $this->setup_directory_page($employee_name, "Employee information");
        
        // Get employee details
        $employee_data = $this->api->get_employee_by_id($employee_id);
        
        if (!$employee_data) {
            $this->render_404();
            return;
        }
        
        ob_start();
        ?>
        <div class="directory-employee-page">
            <div class="directory-breadcrumbs">
                <a href="<?php echo home_url('/directory/'); ?>">Directory</a> &raquo; 
                <span><?php echo esc_html($employee_name); ?></span>
            </div>
            
            <div class="employee-profile">
                <?php $this->render_employee_profile($employee_data); ?>
            </div>
        </div>
        <?php
        
        $content = ob_get_clean();
        $this->output_directory_page($content);
    }
    
    /**
     * Render department details
     *
     * @param array $department_data Department data from Airtable
     */
    private function render_department_details($department_data) {
        $fields = isset($department_data['fields']) ? $department_data['fields'] : array();
        
        $name = isset($fields['Department Name']) ? esc_html($fields['Department Name']) : 'Unknown Department';
        $physical_address = isset($fields['Physical Address']) ? nl2br(esc_html($fields['Physical Address'])) : '';
        $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
        $url = isset($fields['URL']) ? esc_url($fields['URL']) : '';
        
        ?>
        <div class="department-details">
            <h2 class="department-name"><?php echo $name; ?></h2>
            
            <?php if (!empty($physical_address)): ?>
                <div class="department-addresses">
                    <div class="physical-address">
                        <h3>Address</h3>
                        <p><?php echo $physical_address; ?></p>
                        
                        <?php if (!empty($physical_address)): ?>
                            <?php
                            $raw_address = isset($fields['Physical Address']) ? $fields['Physical Address'] : '';
                            $map_address = urlencode($raw_address);
                            $is_mobile = wp_is_mobile();
                            
                            if ($is_mobile) {
                                $map_url = 'geo:0,0?q=' . $map_address;
                            } else {
                                $map_url = 'https://www.google.com/maps?q=' . $map_address;
                            }
                            ?>
                            <p class="map-link">
                                <a href="<?php echo esc_url($map_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-location"></span> View on Map
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="department-contact">
                <?php if (!empty($phone)): ?>
                    <p><strong>Phone:</strong> <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>"><?php echo $phone; ?></a></p>
                <?php endif; ?>
                
                <?php if (!empty($url)): ?>
                    <p><strong>Website:</strong> <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">Visit Department Website</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render staff grid using existing card styling
     *
     * @param array $staff_members Array of staff member records
     */
    private function render_staff_grid($staff_members) {
        if (empty($staff_members)) {
            echo '<p>No staff members found.</p>';
            return;
        }
        
        echo '<div class="staff-directory">';
        
        foreach ($staff_members as $employee) {
            $fields = isset($employee['fields']) ? $employee['fields'] : array();
            
            $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
            $title = isset($fields['Title']) ? esc_html($fields['Title']) : '';
            $dept = isset($fields['Department']) ? esc_html($fields['Department']) : '';
            
            // Generate employee URL
            $emp_slug = $this->routes->generate_slug($name);
            $emp_url = home_url('/directory/' . $emp_slug . '/');
            
            echo '<div class="staff-card">';
            
            // For now, no photos in your data, but we'll add placeholder
            echo '<div class="staff-photo-container">';
            echo '<div class="staff-photo no-photo"><span>No Photo</span></div>';
            echo '</div>';
            
            echo '<div class="staff-info">';
            echo '<strong><a href="' . esc_url($emp_url) . '">' . $name . '</a></strong><br>';
            if (!empty($title)) {
                echo '<span class="staff-title">' . $title . '</span><br>';
            }
            if (!empty($dept)) {
                echo '<span class="staff-department">' . $dept . '</span><br>';
            }
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render a department card for the index page
     *
     * @param array $department Department data from Airtable
     * @param array $child_departments Array of child departments
     */
    private function render_department_card($department, $child_departments = array()) {
        $fields = isset($department['fields']) ? $department['fields'] : array();
        $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : 'Unknown Department';
        $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
        
        // Skip departments without names or IDs
        if (empty($dept_name) || empty($dept_id)) {
            return;
        }
        
        // Generate department URL
        $dept_slug = $this->routes->generate_slug($dept_name);
        $dept_url = home_url('/directory/' . $dept_slug . '/');
        
        // Count staff in this department
        $staff_count = $this->api->get_department_staff_count($dept_id);
        
        // Count child departments
        $child_count = isset($child_departments[$dept_id]) ? count($child_departments[$dept_id]) : 0;
        
        ?>
        <div class="department-card">
            <h3><a href="<?php echo esc_url($dept_url); ?>"><?php echo esc_html($dept_name); ?></a></h3>
            
            <div class="department-stats">
                <?php if ($staff_count > 0): ?>
                    <span class="staff-count"><?php echo $staff_count; ?> staff member<?php echo $staff_count !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
                
                <?php if ($child_count > 0): ?>
                    <span class="child-count"><?php echo $child_count; ?> sub-department<?php echo $child_count !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($child_departments[$dept_id])): ?>
                <div class="child-departments">
                    <strong>Sub-departments:</strong>
                    <ul>
                        <?php foreach ($child_departments[$dept_id] as $child): ?>
                            <?php 
                            $child_fields = isset($child['fields']) ? $child['fields'] : array();
                            $child_name = isset($child_fields['Department Name']) ? $child_fields['Department Name'] : 'Unknown';
                            
                            // Skip if no name
                            if ($child_name === 'Unknown') continue;
                            
                            $child_slug = $this->routes->generate_slug($child_name);
                            $child_url = home_url('/directory/' . $child_slug . '/');
                            ?>
                            <li><a href="<?php echo esc_url($child_url); ?>"><?php echo esc_html($child_name); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render employee profile details
     *
     * @param array $employee_data Employee data from Airtable
     */
    private function render_employee_profile($employee_data) {
        $fields = isset($employee_data['fields']) ? $employee_data['fields'] : array();
        
        $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
        $title = isset($fields['Title']) ? esc_html($fields['Title']) : '';
        $dept = isset($fields['Department']) ? esc_html($fields['Department']) : '';
        $emp_id = isset($fields['Employee ID']) ? esc_html($fields['Employee ID']) : '';
        
        ?>
        <div class="employee-profile-content">
            <div class="employee-photo-large">
                <div class="employee-photo no-photo">
                    <span>No Photo Available</span>
                </div>
            </div>
            
            <div class="employee-details">
                <h1><?php echo $name; ?></h1>
                
                <?php if (!empty($title)): ?>
                    <p class="employee-title"><strong><?php echo $title; ?></strong></p>
                <?php endif; ?>
                
                <?php if (!empty($dept)): ?>
                    <p class="employee-department">Department: <?php echo $dept; ?></p>
                <?php endif; ?>
                
                <?php if (!empty($emp_id)): ?>
                    <p class="employee-id">Employee ID: <?php echo $emp_id; ?></p>
                <?php endif; ?>
                
                <div class="employee-contact">
                    <p><em>Contact information not available in current data structure.</em></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Set up directory page (modify WordPress query and headers)
     *
     * @param string $title Page title
     * @param string $description Page description for meta
     */
    private function setup_directory_page($title, $description = '') {
        // Set page title
        add_filter('wp_title', function($wp_title) use ($title) {
            return $title . ' | ' . get_bloginfo('name');
        });
        
        // Set document title
        add_filter('document_title_parts', function($title_parts) use ($title) {
            $title_parts['title'] = $title;
            return $title_parts;
        });
        
        // Set meta description if provided
        if (!empty($description)) {
            add_action('wp_head', function() use ($description) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            });
        }
        
        // Ensure styles are loaded
        do_action('wp_enqueue_scripts');
    }
    
    /**
     * Output the directory page with proper WordPress structure
     *
     * @param string $content Page content to display
     */
    private function output_directory_page($content) {
        // Get the current theme's header
        get_header();
        
        ?>
        <div class="airtable-directory-page">
            <div class="container">
                <?php echo $content; ?>
            </div>
        </div>
        <?php
        
        // Get the current theme's footer
        get_footer();
        
        // Important: Stop WordPress from continuing to process
        exit;
    }
    
    /**
     * Render 404 page for invalid directory URLs
     */
    private function render_404() {
        // Set 404 status
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        
        $this->setup_directory_page('Page Not Found', 'The requested directory page was not found.');
        
        ob_start();
        ?>
        <div class="directory-404">
            <h1>Page Not Found</h1>
            <p>The directory page you're looking for doesn't exist.</p>
            <p><a href="<?php echo home_url('/directory/'); ?>">Return to Directory</a></p>
        </div>
        <?php
        
        $content = ob_get_clean();
        $this->output_directory_page($content);
    }
}
