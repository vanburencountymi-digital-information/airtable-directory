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
        $csv_header = array();
        if ($selected_csv && in_array($selected_csv, $csv_files)) {
            $csv_path = $data_dir . $selected_csv;
            if (($handle = fopen($csv_path, 'r')) !== false) {
                $row = 0;
                while (($data = fgetcsv($handle, 10000, ',')) !== false && $row < 21) {
                    if ($row === 0) {
                        $csv_header = $data;
                    } else {
                        $csv_preview[] = $data;
                    }
                    $row++;
                }
                fclose($handle);
            }
        }
        ?>
        <div class="wrap">
            <h1>CSV Staff Import/Update</h1>
            <p>Select a CSV file from the <code>data</code> directory to preview and process staff updates.</p>
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
            <?php endif; ?>
        </div>
        <?php
    }
} 
