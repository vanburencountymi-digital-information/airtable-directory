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
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Airtable Directory Settings',
            'Airtable Directory',
            'manage_options',
            'airtable-directory-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Settings page content
     */
    public function settings_page() {
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
                
                <!-- Usage Information Section -->
                <div class="admin-section">
                    <h2>Usage Information</h2>
                    
                    <h3>Shortcodes</h3>
                    <p>You can still use the existing shortcodes on any page or post:</p>
                    <ul>
                        <li><code>[staff_directory]</code> - Display all staff members</li>
                        <li><code>[staff_directory department="recXXXXXXXXXXXX"]</code> - Display staff from specific department</li>
                        <li><code>[department_details department="recXXXXXXXXXXXX"]</code> - Display department information</li>
                        <li><code>[searchable_staff_directory]</code> - Display searchable staff directory with filters</li>
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
} 
