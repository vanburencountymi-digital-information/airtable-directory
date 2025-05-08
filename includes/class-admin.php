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
        ?>
        <div class="wrap">
            <h1>Airtable Directory Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('airtable_directory_clear_cache'); ?>
                <h2>Clear Cache</h2>
                <p>Use this option to clear the cached Airtable data.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Table to clear</th>
                        <td>
                            <select name="table">
                                <option value="">All Tables</option>
                                <option value="<?php echo AIRTABLE_DEPARTMENT_TABLE; ?>">Departments</option>
                                <option value="<?php echo AIRTABLE_STAFF_TABLE; ?>">Staff</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="clear_cache" class="button button-primary" value="Clear Cache">
                </p>
            </form>
        </div>
        <?php
    }
} 