<?php
/**
 * Customer Role Admin Class
 * Manages customer list role permissions using WordPress roles
 * 
 * @package GibbsReactBooking
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Customer Role List Table
 */
class Customer_Role_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'role',
            'plural' => 'roles',
            'ajax' => false
        ));
    }

    /**
     * Get columns
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'role_name' => __('Role Name', 'gibbs-react-booking'),
            'display_name' => __('Display Name', 'gibbs-react-booking'),
            'column_permissions' => __('Column Access', 'gibbs-react-booking'),
            'action_permissions' => __('Action Permissions', 'gibbs-react-booking'),
            'users_count' => __('Users', 'gibbs-react-booking')
        );
        return $columns;
    }

    /**
     * Get sortable columns
     */
    protected function get_sortable_columns() {
        return array(
            'role_name' => array('role_name', false),
            'display_name' => array('display_name', false)
        );
    }

    /**
     * Column default
     */
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? $item[$column_name] : '';
    }

    /**
     * Column checkbox
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="role[]" value="%s" />',
            esc_attr($item['role_name'])
        );
    }

    /**
     * Column role name
     */
    protected function column_role_name($item) {
        $edit_url = add_query_arg(array(
            'page' => 'gibbs-customer-roles',
            'action' => 'edit',
            'role' => $item['role_name']
        ), admin_url('admin.php'));

        

        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'gibbs-react-booking')),
            
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url($edit_url),
            esc_html($item['role_name']),
            $this->row_actions($actions)
        );
    }

    /**
     * Column column permissions
     */
    protected function column_column_permissions($item) {
        $permissions = get_option('gibbs_role_column_permissions_' . $item['role_name'], array());
        $count = count($permissions);
        return sprintf(
            '<span class="permission-count">%d %s</span>',
            $count,
            _n('column', 'columns', $count, 'gibbs-react-booking')
        );
    }

    /**
     * Column action permissions
     */
    protected function column_action_permissions($item) {
        $role = get_role($item['role_name']);
        if (!$role) {
            return __('N/A', 'gibbs-react-booking');
        }

        $permissions = array();
        $all_actions = Customer_Actions::get_all_actions();
        
        foreach ($all_actions as $action_key => $action_label) {
            $capability = Customer_Actions::get_capability_name($action_key);
            if (isset($role->capabilities[$capability])) {
                $permissions[] = $action_label;
            }
        }

        return !empty($permissions) ? implode(', ', $permissions) : __('None', 'gibbs-react-booking');
    }

    /**
     * Column users count
     */
    protected function column_users_count($item) {
        $users = count_users();
        $count = isset($users['avail_roles'][$item['role_name']]) ? $users['avail_roles'][$item['role_name']] : 0;
        return number_format_i18n($count);
    }

    /**
     * Get bulk actions
     */
    protected function get_bulk_actions() {
        return array();
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wp_roles;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Get all roles
        $roles = $wp_roles->get_names();
        $data = array();

        foreach ($roles as $role_name => $display_name) {
            $role = get_role($role_name);
            if ($role) {
                $data[] = array(
                    'role_name' => $role_name,
                    'display_name' => $display_name
                );
            }
        }

        // Sort data
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'role_name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';

        usort($data, function($a, $b) use ($orderby, $order) {
            $result = strcmp($a[$orderby], $b[$orderby]);
            return $order === 'asc' ? $result : -$result;
        });

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($data);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->items = $data;
    }

    /**
     * No items message
     */
    public function no_items() {
        _e('No roles found.', 'gibbs-react-booking');
    }
}

/**
 * Customer Role Admin Class
 */
class Customer_Role_Admin {

    /**
     * Get available columns from global definition
     * 
     * @return array Available columns
     */
    private function get_available_columns() {
        return Customer_Columns::get_all_columns();
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'ensure_sales_rep_role'));
        
        // Add country permissions to user profile edit page
        add_action('show_user_profile', array($this, 'add_user_country_permissions_fields'));
        add_action('edit_user_profile', array($this, 'add_user_country_permissions_fields'));
        add_action('personal_options_update', array($this, 'save_user_country_permissions'));
        add_action('edit_user_profile_update', array($this, 'save_user_country_permissions'));
    }

    /**
     * Ensure sales_rep role exists
     * Creates the role if it doesn't exist
     */
    public function ensure_sales_rep_role() {
        $role_name = 'sales_rep';
        $display_name = __('Sales Rep', 'gibbs-react-booking');
        
        // Check if role already exists
        $role = get_role($role_name);
        
        if (!$role) {
            // Create the role with basic read capability
            $capabilities = array('read' => true);
            add_role($role_name, $display_name, $capabilities);
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Customer List Roles', 'gibbs-react-booking'),
            __('Customer Roles', 'gibbs-react-booking'),
            'manage_options',
            'gibbs-customer-roles',
            array($this, 'render_admin_page'),
            'dashicons-groups',
            30
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_gibbs-customer-roles') {
            return;
        }

        wp_enqueue_style(
            'gibbs-customer-role-admin',
            RMP_PLUGIN_URL . 'assets/admin/admin-customer-roles.css',
            array(),
            RMP_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'gibbs-customer-role-admin',
            RMP_PLUGIN_URL . 'assets/admin/admin-customer-roles.js',
            array('jquery'),
            RMP_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle role deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['role'])) {
            $role_name = sanitize_text_field($_GET['role']);
            $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

            if (wp_verify_nonce($nonce, 'delete_role_' . $role_name)) {
                // Don't delete WordPress default roles
                $default_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
                if (!in_array($role_name, $default_roles)) {
                    remove_role($role_name);
                    delete_option('gibbs_role_column_permissions_' . $role_name);
                }
                wp_redirect(add_query_arg(array('page' => 'gibbs-customer-roles', 'deleted' => '1'), admin_url('admin.php')));
                exit;
            }
        }

        // Handle role save
        if (isset($_POST['save_role']) && check_admin_referer('gibbs_save_role', 'gibbs_role_nonce')) {
            $this->save_role();
        }

        // Handle bulk delete
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['role'])) {
            check_admin_referer('bulk-roles');
            $roles = array_map('sanitize_text_field', $_POST['role']);
            $default_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
            
            foreach ($roles as $role_name) {
                if (!in_array($role_name, $default_roles)) {
                    remove_role($role_name);
                    delete_option('gibbs_role_column_permissions_' . $role_name);
                }
            }
            wp_redirect(add_query_arg(array('page' => 'gibbs-customer-roles', 'deleted' => '1'), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Save role
     */
    private function save_role() {
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $existing_role_name = isset($_POST['existing_role_name']) ? sanitize_text_field($_POST['existing_role_name']) : '';

        if (empty($role_name) || empty($display_name)) {
            wp_redirect(add_query_arg(array('page' => 'gibbs-customer-roles', 'error' => '1'), admin_url('admin.php')));
            exit;
        }

        // Get permissions
        $column_permissions = isset($_POST['column_permissions']) ? array_map('sanitize_text_field', $_POST['column_permissions']) : array();
        $action_permissions = isset($_POST['action_permissions']) ? array_map('sanitize_text_field', $_POST['action_permissions']) : array();

        // Create or update role
        $capabilities = array('read' => true);
        
        // Add action capabilities based on selected permissions
        foreach ($action_permissions as $action_key) {
            if (Customer_Actions::action_exists($action_key)) {
                $capability = Customer_Actions::get_capability_name($action_key);
                $capabilities[$capability] = true;
            }
        }

        // If editing existing role, remove old role first (if name changed)
        if (!empty($existing_role_name) && $existing_role_name !== $role_name) {
            $default_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
            if (!in_array($existing_role_name, $default_roles)) {
                remove_role($existing_role_name);
                delete_option('gibbs_role_column_permissions_' . $existing_role_name);
            }
        }

        // Check if role already exists
        $existing_role = get_role($role_name);
        
        if ($existing_role) {
            // Role exists - update it by removing and re-adding
            // First, get all existing capabilities to preserve them
            $existing_caps = $existing_role->capabilities;
            
            // Remove the role
            remove_role($role_name);
            
            // Merge existing capabilities with new ones (preserve non-Gibbs capabilities)
            foreach ($existing_caps as $cap => $value) {
                if (strpos($cap, 'gibbs_customer_') !== 0 && !isset($capabilities[$cap])) {
                    $capabilities[$cap] = $value;
                }
            }
            
            // Add role back with updated capabilities
            add_role($role_name, $display_name, $capabilities);
        } else {
            // Role doesn't exist - create new one
            add_role($role_name, $display_name, $capabilities);
        }

        // Save column permissions
        update_option('gibbs_role_column_permissions_' . $role_name, $column_permissions);

        // Redirect back to the same page (edit/add form) after saving
        $redirect_args = array(
            'page' => 'gibbs-customer-roles',
            'action' => 'edit',
            'role' => $role_name,
            'saved' => '1'
        );
        
        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gibbs-react-booking'));
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $role_name = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Customer List Roles', 'gibbs-react-booking'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg(array('page' => 'gibbs-customer-roles', 'action' => 'add'), admin_url('admin.php'))); ?>" class="page-title-action"><?php _e('Add New Role', 'gibbs-react-booking'); ?></a>
            <hr class="wp-header-end">

            <?php
            // Show notices
            if (isset($_GET['saved']) && $_GET['saved'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Role saved successfully.', 'gibbs-react-booking') . '</p></div>';
            }
            if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Role(s) deleted successfully.', 'gibbs-react-booking') . '</p></div>';
            }
            if (isset($_GET['error']) && $_GET['error'] == '1') {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error saving role. Please check all required fields.', 'gibbs-react-booking') . '</p></div>';
            }

            if ($action === 'add' || $action === 'edit') {
                $this->render_role_form($role_name);
            } else {
                $this->render_roles_list();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render roles list
     */
    private function render_roles_list() {
        $table = new Customer_Role_List_Table();
        $table->prepare_items();
        ?>
        <form method="post">
            <input type="hidden" name="page" value="gibbs-customer-roles" />
            <?php
            wp_nonce_field('bulk-roles');
            $table->display();
            ?>
        </form>
        <?php
    }

    /**
     * Render role form
     */
    private function render_role_form($role_name = '') {
        $role = null;
        $display_name = '';
        $column_permissions = array();
        $action_permissions = array();
        $country_permissions = array();

        if (!empty($role_name)) {
            global $wp_roles;
            $roles = $wp_roles->get_names();
            if (isset($roles[$role_name])) {
                $display_name = $roles[$role_name];
                $role = get_role($role_name);
                if ($role) {
                    // Get all action permissions from role capabilities
                    $all_actions = Customer_Actions::get_all_actions();
                    foreach ($all_actions as $action_key => $action_label) {
                        $capability = Customer_Actions::get_capability_name($action_key);
                        if (isset($role->capabilities[$capability])) {
                            $action_permissions[] = $action_key;
                        }
                    }
                }
                $column_permissions = get_option('gibbs_role_column_permissions_' . $role_name, array());
            }
        }

        $is_edit = !empty($role_name);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('gibbs_save_role', 'gibbs_role_nonce'); ?>
            <input type="hidden" name="existing_role_name" value="<?php echo esc_attr($role_name); ?>" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="role_name"><?php _e('Role Name', 'gibbs-react-booking'); ?> <span class="description"><?php _e('(required)', 'gibbs-react-booking'); ?></span></label>
                        </th>
                        <td>
                            <?php if ($is_edit): ?>
                                <input type="text" id="role_name" name="role_name" value="<?php echo esc_attr($role_name); ?>" class="regular-text" readonly />
                                <p class="description"><?php _e('Role name cannot be changed when editing.', 'gibbs-react-booking'); ?></p>
                            <?php else: ?>
                                <input type="text" id="role_name" name="role_name" value="<?php echo esc_attr($role_name); ?>" class="regular-text" required />
                                <p class="description"><?php _e('Lowercase letters, numbers, and underscores only. Example: customer_manager', 'gibbs-react-booking'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_name"><?php _e('Display Name', 'gibbs-react-booking'); ?> <span class="description"><?php _e('(required)', 'gibbs-react-booking'); ?></span></label>
                        </th>
                        <td>
                            <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($display_name); ?>" class="regular-text" required />
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php _e('Column Access', 'gibbs-react-booking'); ?></h2>
            <p class="description"><?php _e('Select which columns users with this role can access in the customer list.', 'gibbs-react-booking'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Available Columns', 'gibbs-react-booking'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Column Permissions', 'gibbs-react-booking'); ?></span></legend>
                                <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                    <input type="checkbox" id="select-all-columns" />
                                    <?php _e('Select All', 'gibbs-react-booking'); ?>
                                </label>
                                <hr style="margin: 10px 0;">
                                <?php foreach ($this->get_available_columns() as $key => $label): ?>
                                    <label>
                                        <input type="checkbox" name="column_permissions[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $column_permissions)); ?> />
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php _e('Action Permissions', 'gibbs-react-booking'); ?></h2>
            <p class="description"><?php _e('Select which actions users with this role can perform.', 'gibbs-react-booking'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Actions', 'gibbs-react-booking'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Action Permissions', 'gibbs-react-booking'); ?></span></legend>
                                <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                    <input type="checkbox" id="select-all-actions" />
                                    <?php _e('Select All', 'gibbs-react-booking'); ?>
                                </label>
                                <hr style="margin: 10px 0;">
                                <?php foreach (Customer_Actions::get_all_actions() as $action_key => $action_label): ?>
                                    <label>
                                        <input type="checkbox" name="action_permissions[]" value="<?php echo esc_attr($action_key); ?>" <?php checked(in_array($action_key, $action_permissions)); ?> />
                                        <?php echo esc_html($action_label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>


            <p class="submit" style="display: flex; gap: 10px;">
                <input type="submit" name="save_role" class="button button-primary" value="<?php echo $is_edit ? esc_attr__('Update Role', 'gibbs-react-booking') : esc_attr__('Add Role', 'gibbs-react-booking'); ?>" />
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'gibbs-customer-roles'), admin_url('admin.php'))); ?>" class="button"><?php _e('Cancel', 'gibbs-react-booking'); ?></a>
            </p>
        </form>
        <?php
    }

    /**
     * Get role column permissions
     */
    public static function get_role_column_permissions($role_name) {
        return get_option('gibbs_role_column_permissions_' . $role_name, array());
    }

    /**
     * Check if role can access column
     */
    public static function can_access_column($role_name, $column_key) {
        $permissions = self::get_role_column_permissions($role_name);
        return in_array($column_key, $permissions);
    }

    /**
     * Check if role has action permission
     * 
     * @param string $role_name Role name
     * @param string $action_key Action key (e.g., 'edit_customer', 'create_customer')
     * @return bool True if role has permission
     */
    public static function can_perform_action($role_name, $action_key) {
        $role = get_role($role_name);
        if (!$role) {
            return false;
        }

        if (!Customer_Actions::action_exists($action_key)) {
            return false;
        }

        $capability = Customer_Actions::get_capability_name($action_key);
        return isset($role->capabilities[$capability]);
    }

    /**
     * Get all action permissions for a role
     * 
     * @param string $role_name Role name
     * @return array Array of action keys that the role has permission for
     */
    public static function get_role_action_permissions($role_name) {
        $role = get_role($role_name);
        if (!$role) {
            return array();
        }

        $permissions = array();
        $all_actions = Customer_Actions::get_all_actions();
        
        foreach ($all_actions as $action_key => $action_label) {
            $capability = Customer_Actions::get_capability_name($action_key);
            if (isset($role->capabilities[$capability])) {
                $permissions[] = $action_key;
            }
        }

        return $permissions;
    }

    /**
     * Add country permissions fields to user profile edit page
     * 
     * @param WP_User $user The user object
     */
    public function add_user_country_permissions_fields($user) {
        // Only show for sales_rep role
        if (!in_array('sales_rep', $user->roles)) {
            return;
        }

        // Get countries list
        $countries = array();
        if (function_exists('get_countries')) {
            $countries = get_countries();
        } elseif (file_exists(get_stylesheet_directory() . '/countries.json')) {
            $countries = json_decode(file_get_contents(get_stylesheet_directory() . '/countries.json'), true);
        }

        // Get current user country permissions
        $country_permissions = get_user_meta($user->ID, 'gibbs_user_country_permissions', true);
        if (!is_array($country_permissions)) {
            $country_permissions = array();
        }

        ?>
        <h2><?php _e('Country Permissions', 'gibbs-react-booking'); ?></h2>
        <p class="description"><?php _e('Select which countries this user can access. Leave empty to allow access to all countries.', 'gibbs-react-booking'); ?></p>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Allowed Countries', 'gibbs-react-booking'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Country Permissions', 'gibbs-react-booking'); ?></span></legend>
                            <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                <input type="checkbox" id="select-all-countries-user" />
                                <?php _e('Select All', 'gibbs-react-booking'); ?>
                            </label>
                            <hr style="margin: 10px 0;">
                            <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php if (!empty($countries)): ?>
                                    <?php foreach ($countries as $country): ?>
                                        <?php 
                                        $country_code = isset($country['code']) ? $country['code'] : '';
                                        $country_name = isset($country['name']) ? $country['name'] : '';
                                        if (empty($country_code) || empty($country_name)) continue;
                                        ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="gibbs_user_country_permissions[]" value="<?php echo esc_attr($country_code); ?>" <?php checked(in_array($country_code, $country_permissions)); ?> />
                                            <?php echo esc_html($country_name); ?> (<?php echo esc_html($country_code); ?>)
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p><?php _e('No countries available.', 'gibbs-react-booking'); ?></p>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#select-all-countries-user').on('change', function() {
                var checked = $(this).is(':checked');
                $('input[name="gibbs_user_country_permissions[]"]').prop('checked', checked);
            });
        });
        </script>
        <?php
    }

    /**
     * Save country permissions from user profile
     * 
     * @param int $user_id The user ID
     */
    public function save_user_country_permissions($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Only save for sales_rep role
        $user = get_userdata($user_id);
        if (!in_array('sales_rep', $user->roles)) {
            // Remove country permissions if user is not sales_rep
            delete_user_meta($user_id, 'gibbs_user_country_permissions');
            return;
        }

        // Save country permissions
        if (isset($_POST['gibbs_user_country_permissions'])) {
            $country_permissions = array_map('sanitize_text_field', $_POST['gibbs_user_country_permissions']);
            update_user_meta($user_id, 'gibbs_user_country_permissions', $country_permissions);
        } else {
            // If no countries selected, save empty array
            update_user_meta($user_id, 'gibbs_user_country_permissions', array());
        }
    }

    /**
     * Get user country permissions
     * 
     * @param int $user_id User ID
     * @return array Array of country codes that the user has permission for
     */
    public static function get_user_country_permissions($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('sales_rep', $user->roles)) {
            return array();
        }
        return get_user_meta($user_id, 'gibbs_user_country_permissions', true) ?: array();
    }

    /**
     * Get role country permissions (deprecated - now gets from user meta)
     * Kept for backward compatibility but now gets from current user
     * 
     * @param string $role_name Role name (ignored, kept for compatibility)
     * @return array Array of country codes that the current user has permission for
     */
    public static function get_role_country_permissions($role_name) {
        $current_user = wp_get_current_user();
        if (!in_array('sales_rep', $current_user->roles)) {
            return array();
        }
        return self::get_user_country_permissions($current_user->ID);
    }

    /**
     * Check if role can access country
     * 
     * @param string $role_name Role name (ignored, kept for compatibility)
     * @param string $country_code Country code (e.g., 'NO', 'US')
     * @return bool True if role can access country, false otherwise. Returns true if no country restrictions are set.
     */
    public static function can_access_country($role_name, $country_code) {
        $current_user = wp_get_current_user();
        
        // Only sales_rep role has country restrictions
        if (!in_array('sales_rep', $current_user->roles)) {
            return true;
        }

        $country_permissions = self::get_user_country_permissions($current_user->ID);
        
        // If no countries are selected, allow access to all countries
        if (empty($country_permissions)) {
            return true;
        }

        // Check if country code is in the allowed list
        return in_array($country_code, $country_permissions);
    }
}

