<?php
/**
 * Admin functionality
 */
class Airtable_Directory_Admin {
    
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
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_display_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Top-level menu
        add_menu_page(
            'Airtable Directory',
            'Airtable Directory',
            'manage_options',
            'airtable-directory',
            array($this, 'main_page'),
            'dashicons-groups',
            30
        );

        // Cache submenu
        add_submenu_page(
            'airtable-directory',
            'Cache Management',
            'Cache',
            'manage_options',
            'airtable-directory-cache',
            array($this, 'cache_page')
        );

        // Display Settings submenu
        add_submenu_page(
            'airtable-directory',
            'Display Settings',
            'Display Settings',
            'manage_options',
            'airtable-directory-display-settings',
            array($this, 'display_settings_page')
        );

        // CSV Staff Import/Update submenu
        add_submenu_page(
            'airtable-directory',
            'CSV Staff Import/Update',
            'CSV Staff Import/Update',
            'manage_options',
            'airtable-directory-csv-import',
            array($this, 'csv_import_page')
        );
    }
    
    /**
     * Main page (overview)
     */
    public function main_page() {
        ?>
        <div class="wrap">
            <h1>Airtable Directory</h1>
            <p>Welcome to the Airtable Directory plugin! Use the submenus to manage cache and display settings.</p>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=airtable-directory-cache'); ?>">Cache Management</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=airtable-directory-display-settings'); ?>">Display Settings</a></li>
            </ul>
            <!-- Usage Information Section -->
            <div class="admin-section">
                <h2>Usage Information</h2>
                
                <h3>Shortcodes</h3>
                <p>You can use these shortcodes on any page or post:</p>
                <ul>
                    <li><code>[staff_directory]</code> - Display all staff members</li>
                    <li><code>[staff_directory department="recXXXXXXXXXXXX"]</code> - Display staff from specific department</li>
                    <li><code>[department_details department="recXXXXXXXXXXXX"]</code> - Display department information</li>
                    <li><code>[searchable_staff_directory]</code> - Display searchable staff directory with filters</li>
                    <li><code>[boards_directory]</code> - Display all boards and committees</li>
                    <li><code>[board_members board="1"]</code> - Display board members for specific board</li>
                    <li><code>[board_details board="1"]</code> - Display detailed board information</li>
                </ul>
                
                <h3>Directory Pages</h3>
                <p>The plugin now automatically creates directory pages:</p>
                <ul>
                    <li><strong>Browse All Departments:</strong> Visit <a href="<?php echo home_url('/directory/'); ?>" target="_blank"><?php echo home_url('/directory/'); ?></a></li>
                    <li><strong>Individual Department Pages:</strong> Each department gets its own page showing staff and sub-departments</li>
                    <li><strong>Individual Employee Pages:</strong> Each employee gets their own detailed profile page</li>
                </ul>
                
                <h3>Hierarchy Support</h3>
                <p>The directory automatically handles department hierarchies:</p>
                <ul>
                    <li>Parent departments show their child departments</li>
                    <li>Each child department displays its own staff table</li>
                    <li>Department details are shown above each staff listing</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Cache management page (moved from settings_page)
     */
    public function cache_page() {
        // Check if form was submitted
        if (isset($_POST['clear_cache']) && check_admin_referer('airtable_directory_clear_cache')) {
            $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
            $this->api->clear_cache($table);
            echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
        }
        
        // Check if directory cache was cleared
        if (isset($_POST['clear_directory_cache']) && check_admin_referer('airtable_directory_clear_directory_cache')) {
            $this->api->clear_directory_cache();
            echo '<div class="notice notice-success"><p>Directory cache cleared successfully!</p></div>';
        }
        
        // Check if cache warming was requested
        if (isset($_POST['warm_caches']) && check_admin_referer('airtable_directory_warm_caches')) {
            // Initialize routes to access warm_up_caches method
            require_once AIRTABLE_DIRECTORY_PLUGIN_DIR . 'includes/class-directory-routes.php';
            $routes = new Airtable_Directory_Routes($this->api);
            $routes->warm_up_caches();
            echo '<div class="notice notice-success"><p>Caches warmed up successfully!</p></div>';
        }
        
        // Check if board cache was cleared
        if (isset($_POST['clear_board_cache']) && check_admin_referer('airtable_directory_clear_board_cache')) {
            $this->api->clear_board_cache();
            echo '<div class="notice notice-success"><p>Board cache cleared successfully!</p></div>';
        }
        
        // Check if rewrite rules were flushed
        if (isset($_POST['flush_rewrite_rules']) && check_admin_referer('airtable_directory_flush_rewrite')) {
            flush_rewrite_rules();
            echo '<div class="notice notice-success"><p>URL rewrite rules refreshed successfully!</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Airtable Directory Settings</h1>
            
            <div class="airtable-directory-admin-sections">
                <!-- Cache Management Section -->
                <div class="admin-section">
                    <h2>Cache Management</h2>
                    <p>Clear cached data from Airtable to force fresh data retrieval.</p>
                    
                    <form method="post" action="" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('airtable_directory_clear_cache'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Table to clear</th>
                                <td>
                                    <select name="table">
                                        <option value="">All Tables</option>
                                        <option value="<?php echo AIRTABLE_DEPARTMENT_TABLE; ?>">Departments</option>
                                        <option value="<?php echo AIRTABLE_STAFF_TABLE; ?>">Staff</option>
                                        <option value="<?php echo AIRTABLE_BOARDS_TABLE; ?>">Boards & Committees</option>
                                        <option value="<?php echo AIRTABLE_BOARD_MEMBERS_TABLE; ?>">Board Members</option>
                                    </select>
                                    <p class="description">Select which table's cache to clear, or leave blank to clear all.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="clear_cache" class="button button-primary" value="Clear Data Cache">
                        </p>
                    </form>
                </div>
                
                <!-- Directory-Specific Cache Section -->
                <div class="admin-section">
                    <h2>Directory Cache Management</h2>
                    <p>Clear directory-specific caches including URL slug mappings and hierarchy data.</p>
                    
                    <form method="post" action="" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('airtable_directory_clear_directory_cache'); ?>
                        <p class="submit">
                            <input type="submit" name="clear_directory_cache" class="button button-secondary" value="Clear Directory Cache">
                        </p>
                        <p class="description">This clears slug mappings and forces regeneration of directory URLs.</p>
                    </form>
                    
                    <form method="post" action="" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('airtable_directory_warm_caches'); ?>
                        <p class="submit">
                            <input type="submit" name="warm_caches" class="button button-primary" value="Warm Up Caches">
                        </p>
                        <p class="description">This pre-loads all directory data and slug mappings to prevent cache misses.</p>
                    </form>
                </div>
                
                <!-- Board-Specific Cache Section -->
                <div class="admin-section">
                    <h2>Board Cache Management</h2>
                    <p>Clear board and committee related caches including board data and member information.</p>
                    
                    <form method="post" action="" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('airtable_directory_clear_board_cache'); ?>
                        <p class="submit">
                            <input type="submit" name="clear_board_cache" class="button button-secondary" value="Clear Board Cache">
                        </p>
                        <p class="description">This clears all board and board member data caches.</p>
                    </form>
                </div>
                
                <!-- URL Management Section -->
                <div class="admin-section">
                    <h2>URL Management</h2>
                    <p>Manage directory URL structure and rewrite rules.</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('airtable_directory_flush_rewrite'); ?>
                        <p class="submit">
                            <input type="submit" name="flush_rewrite_rules" class="button button-secondary" value="Refresh URL Rules">
                        </p>
                        <p class="description">Use this if directory URLs are not working properly.</p>
                    </form>
                    
                    <div class="directory-url-info">
                        <h3>Directory URL Structure</h3>
                        <ul>
                            <li><strong>Main Directory:</strong> <code><?php echo home_url('/directory/'); ?></code></li>
                            <li><strong>Department Pages:</strong> <code><?php echo home_url('/directory/{department-name}/'); ?></code></li>
                            <li><strong>Employee Pages:</strong> <code><?php echo home_url('/directory/{employee-name}/'); ?></code></li>
                        </ul>
                        <p class="description">Department and employee names are automatically converted to URL-friendly slugs.</p>
                    </div>
                </div>
                
                
            </div>
        </div>
        
        <style>
        .airtable-directory-admin-sections .admin-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .airtable-directory-admin-sections .admin-section h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .directory-url-info {
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin-top: 15px;
        }
        
        .directory-url-info h3 {
            margin-top: 0;
        }
        
        .directory-url-info ul {
            margin: 10px 0;
        }
        
        .directory-url-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        </style>
        <?php
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Display Settings</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('airtable-directory-display-settings');
                do_settings_sections('airtable-directory-display-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register display settings
     */
    public function register_display_settings() {
        register_setting('airtable-directory-display-settings', 'airtable_directory_display_fields');
        
        // CF7 Integration Settings
        register_setting('airtable-directory-display-settings', Airtable_Directory_CF7_Integration::OPT_CF7_FORM_ID);
        register_setting('airtable-directory-display-settings', Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_MAX);
        register_setting('airtable-directory-display-settings', Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_SECONDS);

        add_settings_section(
            'airtable_directory_display_section',
            'Fields to Display',
            function() {
                echo '<p>Select which fields to show in the directory tables and cards.</p>';
            },
            'airtable-directory-display-settings'
        );

        add_settings_field(
            'airtable_directory_display_fields',
            'Fields',
            array($this, 'display_fields_callback'),
            'airtable-directory-display-settings',
            'airtable_directory_display_section'
        );
        
        // CF7 Integration Section
        add_settings_section(
            'airtable_directory_cf7_section',
            'Contact Forms 7 Integration',
            function() {
                echo '<p>Configure Contact Forms 7 integration for employee and department contact forms.</p>';
            },
            'airtable-directory-display-settings'
        );
        
        add_settings_field(
            'airdir_cf7_form_id',
            'CF7 Form ID',
            array($this, 'cf7_form_id_callback'),
            'airtable-directory-display-settings',
            'airtable_directory_cf7_section'
        );
        
        add_settings_field(
            'airdir_cf7_rate_limit',
            'Rate Limit',
            array($this, 'cf7_rate_limit_callback'),
            'airtable-directory-display-settings',
            'airtable_directory_cf7_section'
        );
    }

    /**
     * Callback for the display fields setting
     */
    public function display_fields_callback() {
        $fields = get_option('airtable_directory_display_fields', array(
            'photo' => 1,
            'name' => 1,
            'title' => 1,
            'phone' => 1,
            'email' => 1,
            'department' => 1,
        ));
        $all_fields = array(
            'photo' => 'Photo',
            'name' => 'Name',
            'title' => 'Title',
            'phone' => 'Phone',
            'email' => 'Email',
            'department' => 'Department',
        );
        foreach ($all_fields as $key => $label) {
            ?>
            <label>
                <input type="checkbox" name="airtable_directory_display_fields[<?php echo esc_attr($key); ?>]" value="1" <?php checked(isset($fields[$key]) && $fields[$key]); ?> />
                <?php echo esc_html($label); ?>
            </label><br>
            <?php
        }
    }
    
    /**
     * Callback for the CF7 form ID setting
     */
    public function cf7_form_id_callback() {
        $form_id = get_option(Airtable_Directory_CF7_Integration::OPT_CF7_FORM_ID, '');
        echo '<input type="text" name="' . esc_attr(Airtable_Directory_CF7_Integration::OPT_CF7_FORM_ID) . '" value="' . esc_attr($form_id) . '" placeholder="e.g., a46061f" style="width: 200px;" />';
        echo '<p class="description">Enter the Contact Form 7 form ID (e.g., a46061f) to use for employee and department contact forms. You can find this ID in the Contact Forms 7 admin interface.</p>';
    }
    
    /**
     * Callback for the CF7 rate limit setting
     */
    public function cf7_rate_limit_callback() {
        $max = (int) get_option(Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_MAX, 40);
        $win = (int) get_option(Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_SECONDS, 600);
        echo 'Max <input type="number" min="1" name="' . esc_attr(Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_MAX) . '" value="' . esc_attr($max) . '" style="width:90px" /> in ';
        echo '<input type="number" min="60" step="60" name="' . esc_attr(Airtable_Directory_CF7_Integration::OPT_RATE_LIMIT_SECONDS) . '" value="' . esc_attr($win) . '" style="width:90px" /> seconds';
        echo '<p class="description">Rate limit for contact form submissions per IP address.</p>';
    }

    /**
     * CSV Staff Import/Update page
     */
    public function csv_import_page() {
        $data_dir = plugin_dir_path(__DIR__) . 'data/';
        $data_url = plugin_dir_url(__DIR__) . 'data/';
        $csv_files = array();
        if (is_dir($data_dir)) {
            $files = scandir($data_dir);
            foreach ($files as $file) {
                if (preg_match('/\.csv$/i', $file)) {
                    $csv_files[] = $file;
                }
            }
        }
        $selected_csv = isset($_POST['selected_csv']) ? sanitize_text_field($_POST['selected_csv']) : '';
        $csv_preview = array();
        $csv_all_rows = array();
        $csv_header = array();
        if ($selected_csv && in_array($selected_csv, $csv_files)) {
            $csv_path = $data_dir . $selected_csv;
            if (($handle = fopen($csv_path, 'r')) !== false) {
                $row = 0;
                while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                    if ($row === 0) {
                        $csv_header = $data;
                    } else {
                        $csv_all_rows[] = $data;
                        if ($row <= 20) {
                            $csv_preview[] = $data;
                        }
                    }
                    $row++;
                }
                fclose($handle);
            }
        }
        $mapping_submitted = isset($_POST['field_mapping_submit']);
        $add_new_submitted = isset($_POST['add_new_submit']);
        $field_mapping = isset($_POST['field_mapping']) && is_array($_POST['field_mapping']) ? $_POST['field_mapping'] : array();
        
        // Hard-coded Staff table schema (field name => field id)
        $staff_table_id = 'tblGUYaSR3ePqIDGK';
        $airtable_fields = array(
            'Employee ID'      => 'fldSsLnHmhFXPyJaj',
            'Name'             => 'fldQXuUUco3ZRRol2',
            'Departments'      => 'fldGqQLnTPWI5HsiL',
            'Title'            => 'fldTlb2WFahG926a2',
            'Phone'            => 'fldsraQiKHMGm6zGP',
            'Phone Extension'  => 'fld583p2zMLHb9ECi',
            'Email'            => 'fldzYbZbb9EzARUZi',
            'Show Email As'    => 'fldolyVdgnp60Zyd9',
            'Photo'            => 'fld6ZzaPzRHy0vCr1',
            'Public'           => 'fld3lSnwtCrvtL4W5',
            'Featured'         => 'fldhMTtMGzz71uXBx',
            'Link'             => 'fldH7q4B4mdULfruM',
            'Link Text'        => 'fldRG5MwZj0pFCBA4',
            'Biography'        => 'fldNBRNOYGw95nBe9',
        );
        $airtable_field_labels = array_keys($airtable_fields);
        $name_field_name = 'Name';
        $name_field_id = $airtable_fields[$name_field_name];
        
        // Helper for normalization
        function normalize_name($name) {
            $name = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $name);
            $name = trim($name);
            $name = strtolower($name);
            return $name;
        }
        
        // Simple department cache to avoid repeated API calls
        $department_cache = array();
        
        // Decode field mapping from POST (if present)
        $decoded_field_mapping = array();
        if (!empty($_POST['field_mapping']) && is_array($_POST['field_mapping'])) {
            $raw_mapping = wp_unslash($_POST['field_mapping']);
            foreach ($raw_mapping as $i => $field_name) {
                if (!empty($field_name) && isset($airtable_fields[$field_name])) {
                    $decoded_field_mapping[$i] = array(
                        'name' => sanitize_text_field($field_name),
                        'id'   => sanitize_text_field($airtable_fields[$field_name]),
                    );
                }
            }
            error_log('[Airtable Directory] Final decoded field mapping: ' . print_r($decoded_field_mapping, true));
        } else {
            error_log('[Airtable Directory] field_mapping is empty');
        }
        // Fetch staff records for matching if mapping submitted
        $staff_lookup = array();
        if ($mapping_submitted && !empty($decoded_field_mapping)) {
            $staff_records = $this->api->fetch_data($staff_table_id, array('fields' => array_values($airtable_fields)));
            error_log('[Airtable Directory] Number of Airtable staff records: ' . count($staff_records));
            if (!empty($staff_records)) {
                error_log('[Airtable Directory] Example Airtable record: ' . print_r($staff_records[0], true));
            }
            $debug_airtable_names = array();
            foreach ($staff_records as $record) {
                $fields = isset($record['fields']) ? $record['fields'] : array();
                if (!array_key_exists($name_field_name, $fields)) {
                    error_log('[Airtable Directory] Name field "' . $name_field_name . '" missing in record: ' . print_r($fields, true));
                }
                if (!empty($fields[$name_field_name])) {
                    $norm_name = normalize_name($fields[$name_field_name]);
                    error_log('[Airtable Directory] Airtable staff name: "' . $fields[$name_field_name] . '" | Normalized: "' . $norm_name . '"');
                    $staff_lookup[$norm_name] = $fields;
                    $debug_airtable_names[] = $norm_name;
                }
            }
            // error_log('[Airtable Directory] All normalized Airtable names: ' . print_r($debug_airtable_names, true));
        }
        // Process add new records if submitted
        if ($add_new_submitted && check_admin_referer('airtable_directory_csv_addnew')) {
            $add_new_selected = isset($_POST['add_new']) && is_array($_POST['add_new']) ? $_POST['add_new'] : array();
            $processed_count = 0;
            $errors = array();
            
            // Use the decoded mapping directly
            $reconstructed_mapping = $decoded_field_mapping;
            error_log('[Airtable Directory] ADD: Starting add process with mapping: ' . count($reconstructed_mapping) . ' fields');
            
            if (!empty($add_new_selected) && !empty($reconstructed_mapping)) {
                // Re-read the CSV to get all rows
                $csv_path = $data_dir . $selected_csv;
                if (($handle = fopen($csv_path, 'r')) !== false) {
                    $row = 0;
                    $csv_all_rows = array();
                    while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                        if ($row > 0) { // Skip header
                            $csv_all_rows[] = $data;
                        }
                        $row++;
                    }
                    fclose($handle);
                    
                    // Process each selected row
                    foreach ($csv_all_rows as $row_index => $row) {
                        $csv_name = '';
                        foreach ($reconstructed_mapping as $i => $map) {
                            if (isset($map['name']) && $map['name'] === $name_field_name) {
                                $csv_name = isset($row[$i]) ? $row[$i] : '';
                                break;
                            }
                        }
                        
                        // Check if this row was selected for adding
                        if (isset($add_new_selected[$csv_name])) {
                            // Build field data for Airtable
                            $airtable_fields_data = array();
                            foreach ($reconstructed_mapping as $csv_index => $map) {
                                if (isset($map['name']) && !empty($map['name']) && isset($row[$csv_index])) {
                                    $field_value = trim($row[$csv_index]);
                                    if (!empty($field_value)) {
                                        // Special handling for Departments field - convert department names to record IDs
                                        if ($map['name'] === 'Departments') {
                                            // Split department names if multiple (comma-separated)
                                            $department_names = array_map('trim', explode(',', $field_value));
                                            $department_record_ids = array();
                                            
                                            foreach ($department_names as $dept_name) {
                                                if (!empty($dept_name)) {
                                                    // Check cache first
                                                    if (isset($department_cache[$dept_name])) {
                                                        $department_record_ids[] = $department_cache[$dept_name];
                                                        error_log('[Airtable Directory] Using cached department record ID for "' . $dept_name . '": ' . $department_cache[$dept_name]);
                                                    } else {
                                                        // Look up department by name to get record ID
                                                        $department_record = $this->api->get_department_by_name($dept_name);
                                                        if ($department_record && isset($department_record['id'])) {
                                                            $department_record_ids[] = $department_record['id'];
                                                            $department_cache[$dept_name] = $department_record['id']; // Cache it
                                                            error_log('[Airtable Directory] Found department record ID for "' . $dept_name . '": ' . $department_record['id']);
                                                        } else {
                                                            error_log('[Airtable Directory] Department not found: "' . $dept_name . '"');
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($department_record_ids)) {
                                                $airtable_fields_data[$map['name']] = $department_record_ids;
                                                error_log('[Airtable Directory] Set Departments field to: ' . print_r($department_record_ids, true));
                                            }
                                        } else {
                                            $airtable_fields_data[$map['name']] = $field_value;
                                        }
                                    }
                                }
                            }
                            
                            error_log('[Airtable Directory] ADD: Processing "' . $csv_name . '" with ' . count($airtable_fields_data) . ' fields');
                            
                            // Add the record
                            if (!empty($airtable_fields_data)) {
                                $result = $this->api->add_record($staff_table_id, $airtable_fields_data);
                                if ($result) {
                                    $processed_count++;
                                    error_log('[Airtable Directory] ADD: Success for "' . $csv_name . '"');
                                } else {
                                    $errors[] = 'Failed to add record for: ' . $csv_name;
                                    error_log('[Airtable Directory] ADD: Failed for "' . $csv_name . '"');
                                }
                            } else {
                                $errors[] = 'No valid data for: ' . $csv_name;
                                error_log('[Airtable Directory] No valid data for: ' . $csv_name);
                            }
                        }
                    }
                    
                    // Clear cache after processing
                    $this->api->clear_table_cache($staff_table_id);
                    
                    // Show results
                    if ($processed_count > 0) {
                        echo '<div class="notice notice-success"><p>Successfully added ' . $processed_count . ' new records to Airtable!</p></div>';
                    }
                    if (!empty($errors)) {
                        echo '<div class="notice notice-error"><p>Errors occurred:</p><ul>';
                        foreach ($errors as $error) {
                            echo '<li>' . esc_html($error) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                }
            } else {
                echo '<div class="notice notice-error"><p>No records selected or mapping data missing.</p></div>';
            }
        }
        
        // Process test update (no API call)
        if (isset($_POST['test_update_submit'])) {
            echo '<div class="notice notice-info"><p><strong>Debug:</strong> Test update button was clicked!</p></div>';
            error_log('[Airtable Directory] TEST UPDATE: Test update button clicked');
            error_log('[Airtable Directory] TEST UPDATE: POST data: ' . print_r($_POST, true));
            if (check_admin_referer('airtable_directory_csv_update')) {
                error_log('[Airtable Directory] TEST UPDATE: Nonce verification passed');
                echo '<div class="notice notice-info"><p><strong>Debug:</strong> Nonce verification passed</p></div>';
                $processed_count = 0;
                $errors = array();
                $test_results = array();
                
                // Use the decoded mapping directly
                $reconstructed_mapping = $decoded_field_mapping;
                error_log('[Airtable Directory] TEST UPDATE: Starting test with mapping: ' . count($reconstructed_mapping) . ' fields');
                echo '<div class="notice notice-info"><p><strong>Debug:</strong> Mapping count: ' . count($reconstructed_mapping) . '</p></div>';
                
                if (!empty($reconstructed_mapping)) {
                    echo '<div class="notice notice-info"><p><strong>Debug:</strong> Mapping data is available</p></div>';
                    // Re-read the CSV to get all rows
                    $csv_path = $data_dir . $selected_csv;
                    if (($handle = fopen($csv_path, 'r')) !== false) {
                        $row = 0;
                        $csv_all_rows = array();
                        while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                            if ($row > 0) { // Skip header
                                $csv_all_rows[] = $data;
                            }
                            $row++;
                        }
                        fclose($handle);
                        
                        echo '<div class="notice notice-info"><p><strong>Debug:</strong> CSV has ' . count($csv_all_rows) . ' rows to process</p></div>';
                        error_log('[Airtable Directory] TEST UPDATE: Processing ' . count($csv_all_rows) . ' CSV rows');
                        
                        // Process each row for test updates
                        foreach ($csv_all_rows as $row_index => $row) {
                            $csv_name = '';
                            foreach ($reconstructed_mapping as $i => $map) {
                                if (isset($map['name']) && $map['name'] === $name_field_name) {
                                    $csv_name = isset($row[$i]) ? $row[$i] : '';
                                    break;
                                }
                            }
                            
                            $norm_csv_name = normalize_name($csv_name);
                            error_log('[Airtable Directory] TEST UPDATE: Row ' . $row_index . ' - CSV name: "' . $csv_name . '", Normalized: "' . $norm_csv_name . '"');
                            
                            // Check if this name matches an existing record
                            if ($norm_csv_name && isset($staff_lookup[$norm_csv_name])) {
                                $existing_record = $staff_lookup[$norm_csv_name];
                                $record_id = $existing_record['id'];
                                error_log('[Airtable Directory] TEST UPDATE: Processing record for "' . $csv_name . '" (ID: ' . $record_id . ')');
                                echo '<div class="notice notice-info"><p><strong>Debug:</strong> Found match for "' . $csv_name . '" (ID: ' . $record_id . ')</p></div>';
                                
                                // Build field data for Airtable update (same logic as real update)
                                $airtable_fields_data = array();
                                foreach ($reconstructed_mapping as $csv_index => $map) {
                                    if (isset($map['name']) && !empty($map['name']) && isset($row[$csv_index])) {
                                        $field_value = trim($row[$csv_index]);
                                        if (!empty($field_value)) {
                                            // Special handling for Departments field - convert department names to record IDs
                                            if ($map['name'] === 'Departments') {
                                                // Split department names if multiple (comma-separated)
                                                $department_names = array_map('trim', explode(',', $field_value));
                                                $department_record_ids = array();
                                                
                                                foreach ($department_names as $dept_name) {
                                                    if (!empty($dept_name)) {
                                                        // Check cache first
                                                        if (isset($department_cache[$dept_name])) {
                                                            $department_record_ids[] = $department_cache[$dept_name];
                                                            error_log('[Airtable Directory] TEST UPDATE: Using cached department record ID for "' . $dept_name . '": ' . $department_cache[$dept_name]);
                                                        } else {
                                                            // Look up department by name to get record ID
                                                            $department_record = $this->api->get_department_by_name($dept_name);
                                                            if ($department_record && isset($department_record['id'])) {
                                                                $department_record_ids[] = $department_record['id'];
                                                                $department_cache[$dept_name] = $department_record['id']; // Cache it
                                                                error_log('[Airtable Directory] TEST UPDATE: Found department record ID for "' . $dept_name . '": ' . $department_record['id']);
                                                            } else {
                                                                error_log('[Airtable Directory] TEST UPDATE: Department not found: "' . $dept_name . '"');
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                if (!empty($department_record_ids)) {
                                                    $airtable_fields_data[$map['name']] = $department_record_ids;
                                                    error_log('[Airtable Directory] TEST UPDATE: Set Departments field to: ' . print_r($department_record_ids, true));
                                                }
                                            } else {
                                                $airtable_fields_data[$map['name']] = $field_value;
                                            }
                                        }
                                    }
                                }
                                
                                error_log('[Airtable Directory] TEST UPDATE: Would send data for "' . $csv_name . '": ' . count($airtable_fields_data) . ' fields');
                                
                                // Store test results instead of making API call
                                if (!empty($airtable_fields_data)) {
                                    $processed_count++;
                                    $test_results[] = array(
                                        'name' => $csv_name,
                                        'record_id' => $record_id,
                                        'fields' => $airtable_fields_data
                                    );
                                    error_log('[Airtable Directory] TEST UPDATE: Success simulation for "' . $csv_name . '"');
                                } else {
                                    $errors[] = 'No valid data for test update: ' . $csv_name;
                                    error_log('[Airtable Directory] TEST UPDATE: No valid data for "' . $csv_name . '"');
                                }
                            }
                        }
                        
                        // Show test results
                        if ($processed_count > 0) {
                            echo '<div class="notice notice-info"><p><strong>Test Update Results:</strong> Would update ' . $processed_count . ' records (no actual changes made)</p>';
                            
                            // Show field mapping summary
                            echo '<div style="background:#f0f8ff; border:1px solid #0073aa; padding:15px; margin:15px 0;">';
                            echo '<h4>Field Mapping Summary</h4>';
                            echo '<table class="widefat" style="margin-top:10px;">';
                            echo '<thead><tr><th>CSV Column</th><th>Airtable Field</th><th>Field ID</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($reconstructed_mapping as $csv_index => $map) {
                                if (isset($map['name']) && !empty($map['name'])) {
                                    echo '<tr>';
                                    echo '<td>Column ' . ($csv_index + 1) . '</td>';
                                    echo '<td><strong>' . esc_html($map['name']) . '</strong></td>';
                                    echo '<td>' . esc_html($map['id']) . '</td>';
                                    echo '</tr>';
                                }
                            }
                            echo '</tbody></table>';
                            echo '</div>';
                            
                            // Show sample data from first 3 records
                            echo '<div style="background:#fff3cd; border:1px solid #ffc107; padding:15px; margin:15px 0;">';
                            echo '<h4>Sample Data (First 3 Records)</h4>';
                            echo '<p><em>This shows exactly what data would be sent to Airtable for the first 3 matched records:</em></p>';
                            
                            $sample_count = 0;
                            foreach ($test_results as $result) {
                                if ($sample_count >= 3) break;
                                
                                echo '<div style="background:#fff; border:1px solid #ddd; padding:15px; margin:10px 0;">';
                                echo '<h5 style="margin-top:0; color:#0073aa;">' . esc_html($result['name']) . '</h5>';
                                echo '<p><strong>Record ID:</strong> ' . esc_html($result['record_id']) . '</p>';
                                echo '<p><strong>Fields to Update:</strong></p>';
                                echo '<table class="widefat" style="margin-top:10px;">';
                                echo '<thead><tr><th>Field Name</th><th>New Value</th></tr></thead>';
                                echo '<tbody>';
                                foreach ($result['fields'] as $field_name => $field_value) {
                                    echo '<tr>';
                                    echo '<td><strong>' . esc_html($field_name) . '</strong></td>';
                                    echo '<td>' . esc_html(is_array($field_value) ? implode(', ', $field_value) : $field_value) . '</td>';
                                    echo '</tr>';
                                }
                                echo '</tbody></table>';
                                echo '</div>';
                                
                                $sample_count++;
                            }
                            
                            if (count($test_results) > 3) {
                                echo '<p><em>... and ' . (count($test_results) - 3) . ' more records would be updated.</em></p>';
                            }
                            echo '</div>';
                            
                            // Show full results in collapsible section
                            echo '<details><summary>Click to see ALL records that would be updated</summary>';
                            echo '<div style="background:#f9f9f9; padding:15px; margin-top:10px; max-height:400px; overflow-y:auto;">';
                            foreach ($test_results as $result) {
                                echo '<div style="border-bottom:1px solid #ddd; padding:10px 0;">';
                                echo '<strong>' . esc_html($result['name']) . '</strong> (ID: ' . esc_html($result['record_id']) . ')<br>';
                                echo '<small>Fields to update:</small><br>';
                                echo '<pre style="background:#fff; padding:5px; margin:5px 0; font-size:12px;">' . esc_html(print_r($result['fields'], true)) . '</pre>';
                                echo '</div>';
                            }
                            echo '</div></details>';
                            
                            echo '<div style="background:#d4edda; border:1px solid #c3e6cb; padding:15px; margin:15px 0;">';
                            echo '<h4>Ready to Update?</h4>';
                            echo '<p>If the field mappings and sample data look correct above, you can proceed with the actual update.</p>';
                            echo '<p><strong>Note:</strong> The test shows ' . $processed_count . ' records would be updated. Make sure this number matches your expectations.</p>';
                            echo '</div>';
                        }
                        if (!empty($errors)) {
                            echo '<div class="notice notice-error"><p>Test errors occurred:</p><ul>';
                            foreach ($errors as $error) {
                                echo '<li>' . esc_html($error) . '</li>';
                            }
                            echo '</ul></div>';
                        }
                    }
                } else {
                    echo '<div class="notice notice-error"><p>Mapping data missing for test updates.</p></div>';
                }
            } else {
                error_log('[Airtable Directory] TEST UPDATE: Nonce verification failed');
                echo '<div class="notice notice-error"><p><strong>Debug:</strong> Nonce verification failed!</p></div>';
            }
        }
        
        // Process update existing records if submitted
        if (isset($_POST['update_existing_submit']) && check_admin_referer('airtable_directory_csv_update')) {
            error_log('[Airtable Directory] UPDATE: Update form submitted');
            $processed_count = 0;
            $errors = array();
            
            // Use the decoded mapping directly
            $reconstructed_mapping = $decoded_field_mapping;
            error_log('[Airtable Directory] UPDATE: Starting update process with mapping: ' . count($reconstructed_mapping) . ' fields');
            
            if (!empty($reconstructed_mapping)) {
                // Re-read the CSV to get all rows
                $csv_path = $data_dir . $selected_csv;
                if (($handle = fopen($csv_path, 'r')) !== false) {
                    $row = 0;
                    $csv_all_rows = array();
                    while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                        if ($row > 0) { // Skip header
                            $csv_all_rows[] = $data;
                        }
                        $row++;
                    }
                    fclose($handle);
                    
                    // Process each row for updates
                    foreach ($csv_all_rows as $row_index => $row) {
                        $csv_name = '';
                        foreach ($reconstructed_mapping as $i => $map) {
                            if (isset($map['name']) && $map['name'] === $name_field_name) {
                                $csv_name = isset($row[$i]) ? $row[$i] : '';
                                break;
                            }
                        }
                        
                        $norm_csv_name = normalize_name($csv_name);
                        
                        // Check if this name matches an existing record
                        if ($norm_csv_name && isset($staff_lookup[$norm_csv_name])) {
                            $existing_record = $staff_lookup[$norm_csv_name];
                            $record_id = $existing_record['id'];
                            error_log('[Airtable Directory] UPDATE: Processing record for "' . $csv_name . '" (ID: ' . $record_id . ')');
                            
                            // Build field data for Airtable update
                            $airtable_fields_data = array();
                            foreach ($reconstructed_mapping as $csv_index => $map) {
                                if (isset($map['name']) && !empty($map['name']) && isset($row[$csv_index])) {
                                    $field_value = trim($row[$csv_index]);
                                    if (!empty($field_value)) {
                                        // Special handling for Departments field - convert department names to record IDs
                                        if ($map['name'] === 'Departments') {
                                            // Split department names if multiple (comma-separated)
                                            $department_names = array_map('trim', explode(',', $field_value));
                                            $department_record_ids = array();
                                            
                                            foreach ($department_names as $dept_name) {
                                                if (!empty($dept_name)) {
                                                    // Check cache first
                                                    if (isset($department_cache[$dept_name])) {
                                                        $department_record_ids[] = $department_cache[$dept_name];
                                                        error_log('[Airtable Directory] UPDATE: Using cached department record ID for "' . $dept_name . '": ' . $department_cache[$dept_name]);
                                                    } else {
                                                        // Look up department by name to get record ID
                                                        $department_record = $this->api->get_department_by_name($dept_name);
                                                        if ($department_record && isset($department_record['id'])) {
                                                            $department_record_ids[] = $department_record['id'];
                                                            $department_cache[$dept_name] = $department_record['id']; // Cache it
                                                            error_log('[Airtable Directory] UPDATE: Found department record ID for "' . $dept_name . '": ' . $department_record['id']);
                                                        } else {
                                                            error_log('[Airtable Directory] UPDATE: Department not found: "' . $dept_name . '"');
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($department_record_ids)) {
                                                $airtable_fields_data[$map['name']] = $department_record_ids;
                                                error_log('[Airtable Directory] UPDATE: Set Departments field to: ' . print_r($department_record_ids, true));
                                            }
                                        } else {
                                            $airtable_fields_data[$map['name']] = $field_value;
                                        }
                                    }
                                }
                            }
                            
                            error_log('[Airtable Directory] UPDATE: Sending data for "' . $csv_name . '": ' . count($airtable_fields_data) . ' fields');
                            
                            // Update the record
                            if (!empty($airtable_fields_data)) {
                                $result = $this->api->update_record($staff_table_id, $record_id, $airtable_fields_data);
                                if ($result) {
                                    $processed_count++;
                                    error_log('[Airtable Directory] UPDATE: Success for "' . $csv_name . '"');
                                } else {
                                    $errors[] = 'Failed to update record for: ' . $csv_name;
                                    error_log('[Airtable Directory] UPDATE: Failed for "' . $csv_name . '"');
                                }
                            } else {
                                $errors[] = 'No valid data for update: ' . $csv_name;
                                error_log('[Airtable Directory] No valid data for update: ' . $csv_name);
                            }
                        }
                    }
                    
                    // Clear cache after processing
                    $this->api->clear_table_cache($staff_table_id);
                    
                    // Show results
                    if ($processed_count > 0) {
                        echo '<div class="notice notice-success"><p>Successfully updated ' . $processed_count . ' existing records in Airtable!</p></div>';
                    }
                    if (!empty($errors)) {
                        echo '<div class="notice notice-error"><p>Errors occurred:</p><ul>';
                        foreach ($errors as $error) {
                            echo '<li>' . esc_html($error) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                }
            } else {
                echo '<div class="notice notice-error"><p>Mapping data missing for updates.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>CSV Staff Import/Update</h1>
            <p>Select a CSV file from the <code>data</code> directory to preview and process staff updates.</p>
            <p><em><strong>Note:</strong> Staff records are cached for 12 hours to minimize API calls. Department lookups may make additional API calls for new departments.</em></p>
            <form method="post">
                <?php wp_nonce_field('airtable_directory_csv_preview'); ?>
                <label for="selected_csv"><strong>CSV File:</strong></label>
                <select name="selected_csv" id="selected_csv">
                    <option value="">-- Select a CSV file --</option>
                    <?php foreach ($csv_files as $file): ?>
                        <option value="<?php echo esc_attr($file); ?>" <?php selected($selected_csv, $file); ?>><?php echo esc_html($file); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button button-primary" value="Preview">
            </form>
            <?php if ($selected_csv && !empty($csv_header)): ?>
                <h2>Preview: <?php echo esc_html($selected_csv); ?></h2>
                <div style="overflow-x:auto; max-width:100%;">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <?php foreach ($csv_header as $col): ?>
                                <th><?php echo esc_html($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($csv_preview as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p><em>Showing first <?php echo count($csv_preview); ?> rows. Only a preview; no changes have been made.</em></p>
                <?php if (!$mapping_submitted): ?>
                    <form method="post" style="margin-top:2em;">
                        <?php wp_nonce_field('airtable_directory_csv_mapping'); ?>
                        <input type="hidden" name="selected_csv" value="<?php echo esc_attr($selected_csv); ?>">
                        <h3>Field Mapping</h3>
                        <p><strong>Note:</strong> The "Departments" field expects department names (comma-separated if multiple). These will be automatically converted to department record IDs when imported.</p>
                        <table class="form-table">
                            <thead>
                                <tr>
                                    <th>CSV Column</th>
                                    <th>Map to Airtable Field</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($csv_header as $i => $col): ?>
                                    <tr>
                                        <td><?php echo esc_html($col); ?></td>
                                        <td>
                                            <select name="field_mapping[<?php echo esc_attr($i); ?>]">
                                                <option value="">-- Ignore --</option>
                                                <?php foreach ($airtable_field_labels as $af_label): ?>
                                                    <option value="<?php echo esc_attr($af_label); ?>" <?php if (strtolower($col) === strtolower($af_label)) echo 'selected'; ?>><?php echo esc_html($af_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <input type="submit" name="field_mapping_submit" class="button button-primary" value="Preview Matches">
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($mapping_submitted && !empty($decoded_field_mapping) && !empty($csv_header)):
                // Build match stats
                $matched = 0;
                $unmatched = 0;
                $unmatched_names = array();
                $unmatched_rows = array();
                foreach ($csv_all_rows as $row) {
                    $csv_name = '';
                    $csv_name_field_index = null;
                    foreach ($decoded_field_mapping as $i => $map) {
                        if (isset($map['name']) && $map['name'] === $name_field_name) {
                            $csv_name = isset($row[$i]) ? $row[$i] : '';
                            $csv_name_field_index = $i;
                            break;
                        }
                    }
                    $norm_csv_name = normalize_name($csv_name);
                    // error_log('[Airtable Directory] CSV name: "' . $csv_name . '" | Normalized: "' . $norm_csv_name . '"');
                    if ($norm_csv_name && isset($staff_lookup[$norm_csv_name])) {
                        $matched++;
                    } else {
                        $unmatched++;
                        $unmatched_names[] = $csv_name;
                        $unmatched_rows[] = $row;
                    }
                }
            ?>
                <h3>Mapping Report</h3>
                <ul>
                    <li><strong>Total rows in CSV:</strong> <?php echo count($csv_all_rows); ?></li>
                    <li><strong>Matched names:</strong> <?php echo $matched; ?></li>
                    <li><strong>Unmatched names:</strong> <?php echo $unmatched; ?></li>
                </ul>
                <?php if ($unmatched > 0): ?>
                    <div style="margin-bottom:1em;"><strong>Unmatched Names:</strong> <?php echo implode(', ', array_map('esc_html', $unmatched_names)); ?></div>
                <?php endif; ?>
                <p><em>Unmatched names will be added as new records if you proceed. If you want to correct a name in the CSV or Airtable so it matches, please do so and re-upload before continuing.</em></p>
                <?php if ($unmatched > 0): ?>
                    <form method="post" style="margin-top:2em;">
                        <?php wp_nonce_field('airtable_directory_csv_addnew'); ?>
                        <input type="hidden" name="selected_csv" value="<?php echo esc_attr($selected_csv); ?>">
                        <?php foreach ($decoded_field_mapping as $i => $map): ?>
                            <input type="hidden" name="field_mapping[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($map['name']); ?>">
                        <?php endforeach; ?>
                        <h3>Review Unmatched Names</h3>
                        
                        <!-- Debug Section -->
                        <div style="background:#f9f9f9; border:1px solid #ddd; padding:15px; margin-bottom:20px;">
                            <h4>Debug: POST Payload Preview</h4>
                            <p><strong>Selected CSV:</strong> <?php echo esc_html($selected_csv); ?></p>
                            <p><strong>Field Mapping:</strong></p>
                            <pre style="background:#fff; padding:10px; overflow-x:auto;"><?php 
                                echo esc_html(print_r($decoded_field_mapping, true)); 
                            ?></pre>
                            <p><strong>Unmatched Names to Process:</strong> <?php echo count($unmatched_names); ?></p>
                            <p><strong>Sample Unmatched Names:</strong> <?php echo esc_html(implode(', ', array_slice($unmatched_names, 0, 5))); ?><?php if (count($unmatched_names) > 5) echo '...'; ?></p>
                        </div>
                        
                        <div style="overflow-x:auto; max-width:100%;">
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <?php foreach ($csv_header as $col): ?>
                                        <th><?php echo esc_html($col); ?></th>
                                    <?php endforeach; ?>
                                    <th>Add as new?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unmatched_rows as $row):
                                    $csv_name = '';
                                    foreach ($decoded_field_mapping as $i => $map) {
                                        if (isset($map['name']) && $map['name'] === $name_field_name) {
                                            $csv_name = isset($row[$i]) ? $row[$i] : '';
                                            break;
                                        }
                                    }
                                ?>
                                <tr style="background:#ffeaea">
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo esc_html($cell); ?></td>
                                    <?php endforeach; ?>
                                    <td><input type="checkbox" name="add_new[<?php echo esc_attr($csv_name); ?>]" checked></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <input type="submit" name="add_new_submit" class="button button-primary" value="Add Selected New Records">
                    </form>
                <?php else: ?>
                    <p><strong>All names in the CSV matched existing Airtable records. No new records will be added.</strong></p>
                <?php endif; ?>
                
                <?php if ($matched > 0): ?>
                    <form method="post" style="margin-top:2em;">
                        <?php wp_nonce_field('airtable_directory_csv_update'); ?>
                        <input type="hidden" name="selected_csv" value="<?php echo esc_attr($selected_csv); ?>">
                        <?php foreach ($decoded_field_mapping as $i => $map): ?>
                            <input type="hidden" name="field_mapping[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($map['name']); ?>">
                        <?php endforeach; ?>
                        <h3>Update Existing Records</h3>
                        <p><em>Matched records will be updated with CSV data. Only mapped fields will be updated.</em></p>
                        <p><strong>Debug:</strong> Matched count: <?php echo $matched; ?>, Mapping count: <?php echo count($decoded_field_mapping); ?></p>
                        <input type="submit" name="test_update_submit" class="button button-secondary" value="Test Update (No API Call)" style="margin-right:10px;">
                        <input type="submit" name="update_existing_submit" class="button button-primary" value="Update Matched Records">
                    </form>
                <?php else: ?>
                    <p><strong>Debug:</strong> No matched records found. Matched count: <?php echo $matched; ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
} 
