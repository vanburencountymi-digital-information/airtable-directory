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
        <div class="directory-archive-container">
            <div class="directory-archive-header">
                <h1>Organization Directory</h1>
                <div class="archive-description">
                    Browse departments and staff members in our organization.
                </div>
            </div>
            
            <?php if ($departments): ?>
                <?php 
                // DEBUG: Log all departments and their parent relationships
                error_log("=== DIRECTORY DEBUG START ===");
                error_log("Total departments fetched: " . count($departments));
                
                // First, let's see what fields are available in the first department
                if (!empty($departments)) {
                    $first_dept = $departments[0];
                    $fields = isset($first_dept['fields']) ? $first_dept['fields'] : array();
                    error_log("Available fields in first department: " . implode(', ', array_keys($fields)));
                    
                    // Log a sample department's complete data
                    error_log("Sample department data: " . print_r($first_dept, true));
                }
                
                // Group departments by parent/child relationship
                $parent_departments = array();
                $child_departments = array();
                $all_departments_by_name = array();
                $all_departments_by_id = array();
                
                // First pass: organize all departments and build lookup
                foreach ($departments as $dept) {
                    $fields = isset($dept['fields']) ? $dept['fields'] : array();
                    $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                    $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
                    
                    // Check for Parent ID field (the correct field name)
                    $parent_id = isset($fields['Parent ID']) ? $fields['Parent ID'] : '';
                    
                    error_log("Department: '$dept_name', Parent ID: '$parent_id'");
                    
                    if (!empty($dept_name)) {
                        $all_departments_by_name[$dept_name] = $dept;
                        if (!empty($dept_id)) {
                            $all_departments_by_id[$dept_id] = $dept;
                        }
                        
                        if (empty($parent_id)) {
                            // This is a top-level department (no parent)
                            $parent_departments[] = $dept;
                            error_log("  -> Added to parent_departments (top-level)");
                        } else {
                            // This is a child department
                            if (!isset($child_departments[$parent_id])) {
                                $child_departments[$parent_id] = array();
                            }
                            $child_departments[$parent_id][] = $dept;
                            error_log("  -> Added to child_departments under Parent ID '$parent_id'");
                        }
                    }
                }
                
                error_log("Parent departments found: " . count($parent_departments));
                error_log("Child department groups: " . count($child_departments));
                
                // NEW LOGIC: Start with all departments as potential categories, then filter
                $all_categories = array();
                foreach ($departments as $dept) {
                    $fields = isset($dept['fields']) ? $dept['fields'] : array();
                    $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                    
                    if (!empty($dept_name)) {
                        $all_categories[] = array(
                            'name' => $dept_name,
                            'slug' => $this->routes->generate_slug($dept_name),
                            'department' => $dept
                        );
                    }
                }
                
                error_log("All potential categories created: " . count($all_categories));
                
                // Now filter out departments that have a parent
                $categories = array();
                foreach ($all_categories as $category) {
                    $dept = $category['department'];
                    $fields = isset($dept['fields']) ? $dept['fields'] : array();
                    $dept_name = $category['name'];
                    
                    // Check for Parent ID field
                    $has_parent = isset($fields['Parent ID']) && !empty($fields['Parent ID']);
                    
                    if ($has_parent) {
                        error_log("Filtering out '$dept_name' - has Parent ID: '" . $fields['Parent ID'] . "'");
                    } else {
                        // This department has no parent, so it becomes a category
                        // (even if it's excluded from display, it can still serve as a category container)
                        $categories[] = $category;
                        error_log("Keeping '$dept_name' as category - no Parent ID found");
                    }
                }
                
                error_log("Categories after filtering: " . count($categories));
                
                // Create categories from top-level departments only
                foreach ($categories as &$category) {
                    $dept_name = $category['name'];
                    $dept_id = isset($category['department']['fields']['Department ID']) ? $category['department']['fields']['Department ID'] : '';
                    
                    error_log("Processing category: '$dept_name' (Department ID: '$dept_id')");
                    
                    // Get all departments that belong to this category (parent + children)
                    $category_departments = array();
                    
                    // Add the parent department itself (only if not excluded)
                    if (!$this->is_department_excluded($dept_name)) {
                        $category_departments[] = $category['department'];
                        error_log("  -> Added parent department to category");
                    } else {
                        error_log("  -> Excluded parent department '$dept_name' from display (category only)");
                    }
                    
                    // Add all child departments recursively
                    if (!empty($dept_id)) {
                        $this->add_child_departments_recursive($dept_id, $child_departments, $all_departments_by_id, $category_departments);
                    }
                    error_log("  -> After adding children, category has " . count($category_departments) . " departments");
                    
                    $category['departments'] = $category_departments;
                }
                
                error_log("Categories created: " . count($categories));
                
                // Find truly uncategorized departments (those with no parent but not used as categories)
                $uncategorized_departments = array();
                foreach ($departments as $dept) {
                    $fields = isset($dept['fields']) ? $dept['fields'] : array();
                    $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                    
                    // Skip if no name or if department is excluded
                    if (empty($dept_name) || $this->is_department_excluded($dept_name)) {
                        continue;
                    }
                    
                    // Check if this department is already included in any category
                    $is_in_category = false;
                    foreach ($categories as $category) {
                        foreach ($category['departments'] as $cat_dept) {
                            $cat_fields = isset($cat_dept['fields']) ? $cat_dept['fields'] : array();
                            $cat_name = isset($cat_fields['Department Name']) ? $cat_fields['Department Name'] : '';
                            if ($cat_name === $dept_name) {
                                $is_in_category = true;
                                break 2;
                            }
                        }
                    }
                    
                    // Check if this department has a parent
                    $has_parent = isset($fields['Parent ID']) && !empty($fields['Parent ID']);
                    
                    // If not in any category and has no parent, it's uncategorized
                    if (!$is_in_category && !$has_parent) {
                        $uncategorized_departments[] = $dept;
                        error_log("Uncategorized department: '$dept_name'");
                    }
                }
                
                error_log("Uncategorized departments: " . count($uncategorized_departments));
                error_log("=== DIRECTORY DEBUG END ===");
                ?>

                <!-- Category Navigation -->
                <div class="category-navigation">
                    <h2>Browse by Department</h2>
                    <div class="category-links">
                        <?php foreach ($categories as $category) : ?>
                            <a href="#category-<?php echo esc_attr($category['slug']); ?>" class="category-link">
                                <?php echo esc_html($category['name']); ?>
                                <span class="department-count">(<?php echo count($category['departments']); ?>)</span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!empty($uncategorized_departments)) : ?>
                            <a href="#uncategorized" class="category-link">
                                Other Departments
                                <span class="department-count">(<?php echo count($uncategorized_departments); ?>)</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="directory-search-container">
                    <div class="search-wrapper">
                        <input type="text" id="department-search" placeholder="Search departments..." class="search-input">
                        <button type="button" id="clear-search" class="clear-search-btn" style="display: none;">Clear</button>
                    </div>
                    <div class="search-results-info">
                        <span id="search-results-count"></span>
                    </div>
                </div>

                <!-- Departments by Category -->
                <?php foreach ($categories as $category) : ?>
                    <div id="category-<?php echo esc_attr($category['slug']); ?>" class="category-section">
                        <h2 class="category-title">
                            <?php echo esc_html($category['name']); ?>
                            <span class="category-count">(<?php echo count($category['departments']); ?> departments)</span>
                        </h2>
                        
                        <div class="departments-grid">
                            <?php foreach ($category['departments'] as $dept) : ?>
                                <?php 
                                $fields = isset($dept['fields']) ? $dept['fields'] : array();
                                $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                                $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
                                
                                // Get child departments for this specific department
                                $dept_children = isset($child_departments[$dept_id]) ? $child_departments[$dept_id] : array();
                                
                                // Check if this is the parent department (no parent field)
                                $is_parent = empty($fields['Parent ID']);
                                
                                // Render the department card
                                $this->render_archive_department_card($dept, $dept_children);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Uncategorized Departments -->
                <?php if (!empty($uncategorized_departments)) : ?>
                    <div id="uncategorized" class="category-section">
                        <h2 class="category-title">
                            Other Departments
                            <span class="category-count">(<?php echo count($uncategorized_departments); ?> departments)</span>
                        </h2>
                        
                        <div class="departments-grid">
                            <?php foreach ($uncategorized_departments as $dept) : ?>
                                <?php 
                                $fields = isset($dept['fields']) ? $dept['fields'] : array();
                                $dept_name = isset($fields['Department Name']) ? $fields['Department Name'] : '';
                                $dept_id = isset($fields['Department ID']) ? $fields['Department ID'] : '';
                                $dept_children = isset($child_departments[$dept_id]) ? $child_departments[$dept_id] : array();
                                ?>
                                <?php $this->render_archive_department_card($dept, $dept_children); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <div class="no-departments-found">
                    <h2>No Departments Found</h2>
                    <p>No departments have been published yet.</p>
                </div>
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
                $this->render_department_page($resolved['name']);
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
    public function render_department_page($department_name) {
        $this->setup_directory_page($department_name, "Department information and staff directory");
        
        ob_start();
        ?>
        <div class="directory-department-page">
            <div class="directory-breadcrumbs">
                <a href="<?php echo home_url('/directory/'); ?>">Directory</a> &raquo; 
                <span><?php echo esc_html($department_name); ?></span>
            </div>
            
            <?php
            error_log("Rendering department page for Name: " . $department_name);
            
            // Get department data for details display
            $department_data = $this->api->get_department_by_name($department_name);
            if ($department_data) {
                $this->render_department_details($department_data);
            } else {
                echo '<p>Department details not found.</p>';
                error_log("Department data not found for Name: " . $department_name);
            }
            
            // Show staff members in this department
            echo '<h3>Staff Members</h3>';
            $staff_members = $this->api->get_staff_by_department($department_name);
            error_log("Found " . count($staff_members) . " staff members for department " . $department_name);
            
            if ($staff_members && count($staff_members) > 0) {
                $this->render_staff_grid($staff_members);
            } else {
                echo '<p>No staff members found for this department.</p>';
            }
            
            // Show child departments if any
            $child_departments = $this->api->get_child_departments($department_name);
            error_log("Found " . count($child_departments) . " child departments for department " . $department_name);
            
            if (!empty($child_departments)) {
                echo '<h3>Sub-Departments</h3>';
                
                foreach ($child_departments as $child_dept) {
                    $child_fields = isset($child_dept['fields']) ? $child_dept['fields'] : array();
                    $child_name = isset($child_fields['Department Name']) ? $child_fields['Department Name'] : 'Unknown Department';
                    
                    if (!empty($child_name)) {
                        echo '<div class="child-department-section">';
                        
                        // Department details for child
                        $this->render_department_details($child_dept);
                        
                        // Staff in child department
                        echo '<h4>' . esc_html($child_name) . ' Staff</h4>';
                        $child_staff = $this->api->get_staff_by_department($child_name);
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
        
        // Check if the employee is public before showing their page
        if (!$this->api->is_staff_public($employee_data)) {
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
        
        // Photo URL extraction logic (same as staff photos)
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
        
        ?>
        <div class="department-details card">
            <div>
                <?php if (!empty($photo_url)): ?>
                    <div class="department-photo-container">
                        <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo $name; ?> building" class="department-photo">
                    </div>
                <?php endif; ?>
                
                <div class="department-info">
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

        $directory_id = 'staff-directory-' . uniqid();

        // Get display fields from settings
        $display_fields = get_option('airtable_directory_display_fields', array(
            'photo' => 1,
            'name' => 1,
            'title' => 1,
            'phone' => 1,
            'email' => 1,
            'department' => 1,
        ));
?>
<div class="staff-directory-toggle-container" id="<?php echo $directory_id; ?>">
    <div class="directory-control-bar">
        <span>View: </span>
        <button class="view-toggle-btn card-view-btn" data-view="card" title="Card View">
            <span class="dashicons dashicons-grid-view"></span> Cards
        </button>
        <button class="view-toggle-btn table-view-btn active" data-view="table" title="Table View">
            <span class="dashicons dashicons-list-view"></span> Table
        </button>
    </div>

    <!-- Card View -->
    <div class="card-view-container" style="display:none;">
        <div class="staff-directory card-layout">
            <?php foreach ($staff_members as $employee): 
                $fields = isset($employee['fields']) ? $employee['fields'] : array();
                $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                $title = isset($fields['Title']) ? esc_html($fields['Title']) : '';
                $dept = '';
                if (isset($fields['Departments']) && is_array($fields['Departments'])) {
                    $department_names = array();
                    foreach ($fields['Departments'] as $dept_record_id) {
                        $dept_record = $this->api->get_department_by_record_id($dept_record_id);
                        if ($dept_record && isset($dept_record['fields']['Department Name'])) {
                            $department_names[] = $dept_record['fields']['Department Name'];
                        }
                    }
                    $dept = !empty($department_names) ? implode(', ', $department_names) : '';
                }
                $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
                $phone_extension = isset($fields['Phone Extension']) ? esc_html($fields['Phone Extension']) : '';
                $email = isset($fields['Email']) ? esc_html($fields['Email']) : '';
                $email_text = isset($fields['Show Email As']) ? esc_html($fields['Show Email As']) : '';
                $emp_slug = $this->routes->generate_slug($name);
                $emp_url = home_url('/directory/' . $emp_slug . '/');

                // Photo extraction (reuse logic)
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
            ?>
            <div class="staff-card">
                <?php if (!empty($display_fields['photo'])): ?>
                <div class="staff-photo-container">
                    <?php if (!empty($photo_url)): ?>
                        <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo $name; ?>" class="staff-photo">
                    <?php else: ?>
                        <div class="staff-photo no-photo"><span>No Photo</span></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="staff-info">
                    <?php if (!empty($display_fields['name'])): ?>
                        <strong><a href="<?php echo esc_url($emp_url); ?>"><?php echo $name; ?></a></strong><br>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['title']) && !empty($title)): ?>
                        <span class="staff-title"><?php echo $title; ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['department']) && !empty($dept)): ?>
                        <span class="staff-department"><?php echo $dept; ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['phone']) && !empty($phone)): ?>
                        <span class="staff-phone"><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>"><?php echo $phone; ?><?php if (!empty($phone_extension)): ?> Ext. <?php echo $phone_extension; ?><?php endif; ?></a></span><br>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['email']) && !empty($email)): ?>
                        <span class="staff-email"><a href="mailto:<?php echo antispambot($email); ?>"><?php echo !empty($email_text) ? $email_text : antispambot($email); ?></a></span><br>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table View -->
    <div class="table-view-container tbl-alternate-rows" style="display:block;">
        <table class="staff-directory-table">
            <thead>
                <tr>
                    <?php if (!empty($display_fields['photo'])): ?><th class="column-photo">Photo</th><?php endif; ?>
                    <?php if (!empty($display_fields['name'])): ?><th class="column-name">Name</th><?php endif; ?>
                    <?php if (!empty($display_fields['title'])): ?><th class="column-title">Title</th><?php endif; ?>
                    <?php if (!empty($display_fields['phone'])): ?><th class="column-phone">Phone</th><?php endif; ?>
                    <?php if (!empty($display_fields['email'])): ?><th class="column-email">Email</th><?php endif; ?>
                    <?php if (!empty($display_fields['department'])): ?><th class="column-department">Department</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_members as $employee): 
                    $fields = isset($employee['fields']) ? $employee['fields'] : array();
                    $name = isset($fields['Name']) ? esc_html($fields['Name']) : 'Unknown';
                    $title = isset($fields['Title']) ? esc_html($fields['Title']) : '';
                    $dept = '';
                    if (isset($fields['Departments']) && is_array($fields['Departments'])) {
                        $department_names = array();
                        foreach ($fields['Departments'] as $dept_record_id) {
                            $dept_record = $this->api->get_department_by_record_id($dept_record_id);
                            if ($dept_record && isset($dept_record['fields']['Department Name'])) {
                                $department_names[] = $dept_record['fields']['Department Name'];
                            }
                        }
                        $dept = !empty($department_names) ? implode(', ', $department_names) : '';
                    }
                    $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
                    $phone_extension = isset($fields['Phone Extension']) ? esc_html($fields['Phone Extension']) : '';
                    $email = isset($fields['Email']) ? esc_html($fields['Email']) : '';
                    $email_text = isset($fields['Show Email As']) ? esc_html($fields['Show Email As']) : '';
                    $emp_slug = $this->routes->generate_slug($name);
                    $emp_url = home_url('/directory/' . $emp_slug . '/');

                    // Photo extraction (reuse logic)
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

                    $phone_link = '';
                    if (!empty($phone)) {
                        $tel = preg_replace('/[^0-9+]/', '', $phone);
                        $phone_link = '<a href="tel:' . esc_attr($tel) . '">' . $phone;
                        if (!empty($phone_extension)) {
                            $phone_link .= ' Ext. ' . $phone_extension;
                        }
                        $phone_link .= '</a>';
                    }

                    $email_link = '';
                    if (!empty($email)) {
                        $email_link = '<a href="mailto:' . antispambot($email) . '">' . (!empty($email_text) ? $email_text : antispambot($email)) . '</a>';
                    }
                ?>
                <tr>
                    <?php if (!empty($display_fields['photo'])): ?>
                        <td class="column-photo">
                            <?php if (!empty($photo_url)): ?>
                                <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo $name; ?>" class="staff-photo-thumbnail">
                            <?php else: ?>
                                <div class="staff-photo-thumbnail no-photo"><span>No Photo</span></div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['name'])): ?>
                        <td class="column-name"><a href="<?php echo esc_url($emp_url); ?>"><?php echo $name; ?></a></td>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['title'])): ?>
                        <td class="column-title"><?php echo $title; ?></td>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['phone'])): ?>
                        <td class="column-phone"><?php echo $phone_link ?: $phone; ?></td>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['email'])): ?>
                        <td class="column-email"><?php echo $email_link ?: $email; ?></td>
                    <?php endif; ?>
                    <?php if (!empty($display_fields['department'])): ?>
                        <td class="column-department"><?php echo $dept; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
// Simple toggle logic (can be moved to a JS file)
(function(){
    var container = document.getElementById('<?php echo $directory_id; ?>');
    if (!container) return;
    var cardBtn = container.querySelector('.card-view-btn');
    var tableBtn = container.querySelector('.table-view-btn');
    var cardView = container.querySelector('.card-view-container');
    var tableView = container.querySelector('.table-view-container');
    cardBtn.addEventListener('click', function() {
        cardBtn.classList.add('active');
        tableBtn.classList.remove('active');
        cardView.style.display = 'block';
        tableView.style.display = 'none';
    });
    tableBtn.addEventListener('click', function() {
        tableBtn.classList.add('active');
        cardBtn.classList.remove('active');
        tableView.style.display = 'block';
        cardView.style.display = 'none';
    });
})();
</script>
<?php
    }
    
    /**
     * Render a department card for the archive page
     *
     * @param array $department Department data from Airtable
     * @param array $child_departments Array of child departments
     */
    private function render_archive_department_card($department, $child_departments = array()) {
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
        $staff_count = $this->api->get_department_staff_count($dept_name);
        
        // Count child departments
        $child_count = isset($child_departments) ? count($child_departments) : 0;
        
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
        
        // Extract contact information
        $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
        $url = isset($fields['URL']) ? esc_url($fields['URL']) : '';
        $physical_address = isset($fields['Physical Address']) ? esc_html($fields['Physical Address']) : '';
        
        ?>
        <article class="department-card">
            <div class="department-card-header">
                <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo esc_attr($dept_name); ?> building" class="department-photo">
                <?php else: ?>
                    <div class="department-photo no-photo">
                        <span>No Photo</span>
                    </div>
                <?php endif; ?>
                
                <h3 class="department-title">
                    <a href="<?php echo esc_url($dept_url); ?>"><?php echo esc_html($dept_name); ?></a>
                </h3>
            </div>
            
            <div class="department-card-content">
                <div class="department-stats">
                    <?php if ($staff_count > 0): ?>
                        <span class="staff-count"><?php echo $staff_count; ?> staff member<?php echo $staff_count !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    
                    <?php if ($child_count > 0): ?>
                        <span class="child-count"><?php echo $child_count; ?> sub-department<?php echo $child_count !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($phone) || !empty($url) || !empty($physical_address)): ?>
                    <div class="department-contact-preview">
                        <?php if (!empty($phone)): ?>
                            <strong>Phone:</strong> <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>"><?php echo $phone; ?></a><br>
                        <?php endif; ?>
                        
                        <?php if (!empty($url)): ?>
                            <strong>Website:</strong> <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">Visit Website</a><br>
                        <?php endif; ?>
                        
                        <?php if (!empty($physical_address)): ?>
                            <strong>Address:</strong> <?php echo $physical_address; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($child_departments)): ?>
                    <div class="child-departments">
                        <strong>Sub-departments:</strong>
                        <ul>
                            <?php foreach ($child_departments as $child): ?>
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
            
            <div class="department-card-footer">
                <a href="<?php echo esc_url($dept_url); ?>" class="view-department-btn">
                    View Department
                </a>
            </div>
        </article>
        <?php
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
        $staff_count = $this->api->get_department_staff_count($dept_name);
        
        // Count child departments
        $child_count = isset($child_departments[$dept_name]) ? count($child_departments[$dept_name]) : 0;
        
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
        
        ?>
        <div class="department-card">
            <?php if (!empty($photo_url)): ?>
                <div class="department-card-photo">
                    <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo esc_attr($dept_name); ?> building" class="department-card-image">
                </div>
            <?php endif; ?>
            
            <div class="department-card-content">
                <h3><a href="<?php echo esc_url($dept_url); ?>"><?php echo esc_html($dept_name); ?></a></h3>
                
                <div class="department-stats">
                    <?php if ($staff_count > 0): ?>
                        <span class="staff-count"><?php echo $staff_count; ?> staff member<?php echo $staff_count !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    
                    <?php if ($child_count > 0): ?>
                        <span class="child-count"><?php echo $child_count; ?> sub-department<?php echo $child_count !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($child_departments[$dept_name])): ?>
                    <div class="child-departments">
                        <strong>Sub-departments:</strong>
                        <ul>
                            <?php foreach ($child_departments[$dept_name] as $child): ?>
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
        $dept = '';
        if (isset($fields['Departments']) && is_array($fields['Departments'])) {
            $department_names = array();
            foreach ($fields['Departments'] as $dept_record_id) {
                $dept_record = $this->api->get_department_by_record_id($dept_record_id);
                if ($dept_record && isset($dept_record['fields']['Department Name'])) {
                    $department_names[] = $dept_record['fields']['Department Name'];
                }
            }
            $dept = !empty($department_names) ? implode(', ', $department_names) : '';
        }
        // $emp_id = isset($fields['Employee ID']) ? esc_html($fields['Employee ID']) : '';

        // Add these lines to extract phone and email
        $phone = isset($fields['Phone']) ? esc_html($fields['Phone']) : '';
        $phone_extension = isset($fields['Phone Extension']) ? esc_html($fields['Phone Extension']) : '';
        $email = isset($fields['Email']) ? esc_html($fields['Email']) : '';
        $email_text = isset($fields['Show Email As']) ? esc_html($fields['Show Email As']) : '';

        // Photo URL extraction logic (copied from class-shortcodes.php)
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
        ?>
        <div class="employee-profile-content">
            <div class="employee-photo-large">
                <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo $photo_url; ?>" alt="Photo of <?php echo $name; ?>" class="employee-photo">
                <?php else: ?>
                    <div class="employee-photo no-photo">
                        <span>No Photo Available</span>
                    </div>
                <?php endif; ?>
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
                    <?php if (!empty($phone)): ?>
                        <p><strong>Phone:</strong> <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>"><?php echo $phone; ?><?php if (!empty($phone_extension)): ?> Ext. <?php echo $phone_extension; ?><?php endif; ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($email)): ?>
                        <p><strong>Email:</strong> <a href="mailto:<?php echo antispambot($email); ?>"><?php echo !empty($email_text) ? $email_text : antispambot($email); ?></a></p>
                    <?php endif; ?>
                    <?php if (empty($phone) && empty($email)): ?>
                        <p><em>No contact info found.</em></p>
                    <?php endif; ?>
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

    /**
     * Recursively adds child departments to a category's department list.
     *
     * @param string $parent_id The ID of the parent department
     * @param array $child_departments_lookup Array mapping parent IDs to child departments
     * @param array $all_departments_by_id Array of all departments indexed by ID
     * @param array $category_departments Reference to the array to which child departments will be added
     */
    private function add_child_departments_recursive($parent_id, $child_departments_lookup, $all_departments_by_id, &$category_departments) {
        error_log("  Recursive call for parent ID: '$parent_id'");
        
        if (isset($child_departments_lookup[$parent_id])) {
            error_log("    Found " . count($child_departments_lookup[$parent_id]) . " children for Parent ID '$parent_id'");
            
            foreach ($child_departments_lookup[$parent_id] as $child_dept) {
                $child_fields = isset($child_dept['fields']) ? $child_dept['fields'] : array();
                $child_name = isset($child_fields['Department Name']) ? $child_fields['Department Name'] : '';
                
                error_log("    Processing child: '$child_name'");
                
                if (!empty($child_name)) {
                    // Check if this child department should be excluded from display
                    if (!$this->is_department_excluded($child_name)) {
                        // Add this child department to the category
                        $category_departments[] = $child_dept;
                        error_log("      -> Added '$child_name' to category");
                    } else {
                        error_log("      -> Excluded child department '$child_name' from display (category only)");
                    }
                    
                    // Recursively add this child's children (regardless of whether this child is displayed)
                    $child_id = isset($child_fields['Department ID']) ? $child_fields['Department ID'] : '';
                    if (!empty($child_id)) {
                        $this->add_child_departments_recursive($child_id, $child_departments_lookup, $all_departments_by_id, $category_departments);
                    }
                }
            }
        } else {
            error_log("    No children found for Parent ID '$parent_id'");
        }
    }

    /**
     * Get departments that should be excluded from display (used as categories only)
     *
     * @return array Array of department names to exclude from display
     */
    private function get_excluded_departments() {
        return array(
            'Townships',
            'Villages', 
            'Cities'
        );
    }
    
    /**
     * Check if a department should be excluded from display
     *
     * @param string $department_name The department name to check
     * @return bool True if department should be excluded, false otherwise
     */
    private function is_department_excluded($department_name) {
        $excluded_departments = $this->get_excluded_departments();
        return in_array($department_name, $excluded_departments);
    }
}
