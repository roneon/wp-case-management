<?php
/**
 * Admin user management page
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Admin_Users {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_cmp_update_user_permissions', array($this, 'update_user_permissions'));
        add_action('wp_ajax_cmp_bulk_assign_role', array($this, 'bulk_assign_role'));
    }
    
    public function users_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        switch ($action) {
            case 'edit':
                $this->render_edit_user($user_id);
                break;
            default:
                $this->render_users_list();
                break;
        }
    }
    
    private function render_users_list() {
        // Get all users with CMP roles or capabilities
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'wp_capabilities',
                    'value' => 'case_submitter',
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wp_capabilities', 
                    'value' => 'case_reviewer',
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wp_capabilities',
                    'value' => 'case_manager', 
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wp_capabilities',
                    'value' => 'cmp_',
                    'compare' => 'LIKE'
                )
            )
        ));
        
        // If no CMP users found, show all users
        if (empty($users)) {
            $users = get_users(array('number' => 50));
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('User Permissions', 'case-manager-pro'); ?></h1>
            <a href="#" id="cmp-bulk-assign" class="page-title-action"><?php _e('Bulk Assign Roles', 'case-manager-pro'); ?></a>
            
            <div class="cmp-users-filters">
                <form method="get">
                    <input type="hidden" name="page" value="cmp-users">
                    
                    <select name="role_filter" id="role-filter">
                        <option value=""><?php _e('All Roles', 'case-manager-pro'); ?></option>
                        <option value="case_submitter"><?php _e('Case Submitter', 'case-manager-pro'); ?></option>
                        <option value="case_reviewer"><?php _e('Case Reviewer', 'case-manager-pro'); ?></option>
                        <option value="case_manager"><?php _e('Case Manager', 'case-manager-pro'); ?></option>
                        <option value="administrator"><?php _e('Administrator', 'case-manager-pro'); ?></option>
                    </select>
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'case-manager-pro'); ?>">
                </form>
            </div>
            
            <form id="cmp-users-form">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php _e('User', 'case-manager-pro'); ?></th>
                            <th><?php _e('Email', 'case-manager-pro'); ?></th>
                            <th><?php _e('Current Role', 'case-manager-pro'); ?></th>
                            <th><?php _e('CMP Permissions', 'case-manager-pro'); ?></th>
                            <th><?php _e('Actions', 'case-manager-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="users[]" value="<?php echo $user->ID; ?>">
                                </th>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <br><small><?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <?php
                                    $roles = $user->roles;
                                    $cmp_roles = array_intersect($roles, array('case_submitter', 'case_reviewer', 'case_manager'));
                                    if (!empty($cmp_roles)) {
                                        echo '<span class="cmp-role">' . implode(', ', $cmp_roles) . '</span>';
                                    } else {
                                        echo implode(', ', $roles);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $cmp_caps = array();
                                    $all_caps = array(
                                        'cmp_submit_case' => 'Submit',
                                        'cmp_view_own_cases' => 'View Own',
                                        'cmp_view_all_cases' => 'View All',
                                        'cmp_edit_all_cases' => 'Edit',
                                        'cmp_comment_cases' => 'Comment',
                                        'cmp_manage_settings' => 'Settings',
                                        'cmp_view_case_analytics' => 'Analytics'
                                    );
                                    
                                    foreach ($all_caps as $cap => $label) {
                                        if ($user->has_cap($cap)) {
                                            $cmp_caps[] = '<span class="cmp-cap">' . $label . '</span>';
                                        }
                                    }
                                    
                                    echo !empty($cmp_caps) ? implode(' ', $cmp_caps) : '<span class="cmp-no-caps">None</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cmp-users&action=edit&user_id=' . $user->ID); ?>" class="button button-small"><?php _e('Edit Permissions', 'case-manager-pro'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Bulk Actions Modal -->
            <div id="cmp-bulk-modal" style="display: none;">
                <div class="cmp-modal-content">
                    <h3><?php _e('Bulk Assign Role', 'case-manager-pro'); ?></h3>
                    <p><?php _e('Select a role to assign to selected users:', 'case-manager-pro'); ?></p>
                    
                    <select id="bulk-role">
                        <option value=""><?php _e('Select Role', 'case-manager-pro'); ?></option>
                        <option value="case_submitter"><?php _e('Case Submitter', 'case-manager-pro'); ?></option>
                        <option value="case_reviewer"><?php _e('Case Reviewer', 'case-manager-pro'); ?></option>
                        <option value="case_manager"><?php _e('Case Manager', 'case-manager-pro'); ?></option>
                    </select>
                    
                    <div class="cmp-modal-actions">
                        <button type="button" id="bulk-assign-confirm" class="button button-primary"><?php _e('Assign Role', 'case-manager-pro'); ?></button>
                        <button type="button" id="bulk-assign-cancel" class="button"><?php _e('Cancel', 'case-manager-pro'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .cmp-users-filters {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cmp-role {
            background: #0073aa;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .cmp-cap {
            background: #00a32a;
            color: #fff;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 10px;
            margin-right: 2px;
        }
        
        .cmp-no-caps {
            color: #666;
            font-style: italic;
        }
        
        #cmp-bulk-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
        }
        
        .cmp-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            min-width: 400px;
        }
        
        .cmp-modal-actions {
            margin-top: 15px;
            text-align: right;
        }
        
        .cmp-modal-actions button {
            margin-left: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox
            $('#cb-select-all').on('change', function() {
                $('input[name="users[]"]').prop('checked', this.checked);
            });
            
            // Bulk assign modal
            $('#cmp-bulk-assign').on('click', function(e) {
                e.preventDefault();
                var selected = $('input[name="users[]"]:checked').length;
                if (selected === 0) {
                    alert('<?php _e('Please select at least one user', 'case-manager-pro'); ?>');
                    return;
                }
                $('#cmp-bulk-modal').show();
            });
            
            $('#bulk-assign-cancel').on('click', function() {
                $('#cmp-bulk-modal').hide();
            });
            
            $('#bulk-assign-confirm').on('click', function() {
                var role = $('#bulk-role').val();
                var users = [];
                
                $('input[name="users[]"]:checked').each(function() {
                    users.push($(this).val());
                });
                
                if (!role) {
                    alert('<?php _e('Please select a role', 'case-manager-pro'); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmp_bulk_assign_role',
                        users: users,
                        role: role,
                        nonce: '<?php echo wp_create_nonce('cmp_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Roles assigned successfully', 'case-manager-pro'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error assigning roles', 'case-manager-pro'); ?>');
                        }
                    }
                });
                
                $('#cmp-bulk-modal').hide();
            });
        });
        </script>
        <?php
    }
    
    private function render_edit_user($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            echo '<div class="wrap"><h1>' . __('User not found', 'case-manager-pro') . '</h1></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Edit Permissions: %s', 'case-manager-pro'), esc_html($user->display_name)); ?></h1>
            
            <form id="cmp-user-permissions-form">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('User Info', 'case-manager-pro'); ?></th>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <?php echo esc_html($user->user_email); ?><br>
                            <small>User ID: <?php echo $user->ID; ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Current WordPress Role', 'case-manager-pro'); ?></th>
                        <td><?php echo implode(', ', $user->roles); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Case Manager Pro Role', 'case-manager-pro'); ?></th>
                        <td>
                            <select name="cmp_role">
                                <option value=""><?php _e('No CMP Role', 'case-manager-pro'); ?></option>
                                <option value="case_submitter" <?php selected(in_array('case_submitter', $user->roles)); ?>><?php _e('Case Submitter', 'case-manager-pro'); ?></option>
                                <option value="case_reviewer" <?php selected(in_array('case_reviewer', $user->roles)); ?>><?php _e('Case Reviewer', 'case-manager-pro'); ?></option>
                                <option value="case_manager" <?php selected(in_array('case_manager', $user->roles)); ?>><?php _e('Case Manager', 'case-manager-pro'); ?></option>
                            </select>
                            <p class="description"><?php _e('Assigning a CMP role will automatically grant the appropriate capabilities.', 'case-manager-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Individual Capabilities', 'case-manager-pro'); ?></th>
                        <td>
                            <?php
                            $cmp_caps = array(
                                'cmp_submit_case' => __('Submit Cases', 'case-manager-pro'),
                                'cmp_view_own_cases' => __('View Own Cases', 'case-manager-pro'),
                                'cmp_view_all_cases' => __('View All Cases', 'case-manager-pro'),
                                'cmp_edit_all_cases' => __('Edit All Cases', 'case-manager-pro'),
                                'cmp_comment_cases' => __('Comment on Cases', 'case-manager-pro'),
                                'cmp_manage_settings' => __('Manage Settings', 'case-manager-pro'),
                                'cmp_view_case_analytics' => __('View Analytics', 'case-manager-pro'),
                                'cmp_download_files' => __('Download Files', 'case-manager-pro'),
                                'cmp_delete_all_cases' => __('Delete Cases', 'case-manager-pro')
                            );
                            
                            foreach ($cmp_caps as $cap => $label) {
                                $checked = $user->has_cap($cap) ? 'checked' : '';
                                echo "<label style='display: block; margin-bottom: 5px;'>";
                                echo "<input type='checkbox' name='cmp_caps[]' value='$cap' $checked> $label";
                                echo "</label>";
                            }
                            ?>
                            <p class="description"><?php _e('You can grant individual capabilities in addition to or instead of a role.', 'case-manager-pro'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Update Permissions', 'case-manager-pro'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=cmp-users'); ?>" class="button"><?php _e('Back to Users', 'case-manager-pro'); ?></a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#cmp-user-permissions-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=cmp_update_user_permissions';
                formData += '&nonce=<?php echo wp_create_nonce('cmp_admin_nonce'); ?>';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Permissions updated successfully', 'case-manager-pro'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error updating permissions', 'case-manager-pro'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function update_user_permissions() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'case-manager-pro')));
        }
        
        // Remove all CMP roles first
        $user->remove_role('case_submitter');
        $user->remove_role('case_reviewer');
        $user->remove_role('case_manager');
        
        // Add new CMP role if selected
        $cmp_role = sanitize_text_field($_POST['cmp_role']);
        if ($cmp_role && in_array($cmp_role, array('case_submitter', 'case_reviewer', 'case_manager'))) {
            $user->add_role($cmp_role);
        }
        
        // Remove all CMP capabilities
        $all_cmp_caps = array(
            'cmp_submit_case', 'cmp_view_own_cases', 'cmp_view_all_cases',
            'cmp_edit_all_cases', 'cmp_comment_cases', 'cmp_manage_settings',
            'cmp_view_case_analytics', 'cmp_download_files', 'cmp_delete_all_cases'
        );
        
        foreach ($all_cmp_caps as $cap) {
            $user->remove_cap($cap);
        }
        
        // Add selected capabilities
        if (isset($_POST['cmp_caps']) && is_array($_POST['cmp_caps'])) {
            foreach ($_POST['cmp_caps'] as $cap) {
                if (in_array($cap, $all_cmp_caps)) {
                    $user->add_cap($cap);
                }
            }
        }
        
        wp_send_json_success(array('message' => __('Permissions updated successfully', 'case-manager-pro')));
    }
    
    public function bulk_assign_role() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $users = array_map('intval', $_POST['users']);
        $role = sanitize_text_field($_POST['role']);
        
        if (!in_array($role, array('case_submitter', 'case_reviewer', 'case_manager'))) {
            wp_send_json_error(array('message' => __('Invalid role', 'case-manager-pro')));
        }
        
        foreach ($users as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                // Remove existing CMP roles
                $user->remove_role('case_submitter');
                $user->remove_role('case_reviewer');
                $user->remove_role('case_manager');
                
                // Add new role
                $user->add_role($role);
            }
        }
        
        wp_send_json_success(array('message' => __('Roles assigned successfully', 'case-manager-pro')));
    }
} 