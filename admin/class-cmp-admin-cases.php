<?php
/**
 * Admin cases management page
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Admin_Cases {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Kendi menü oluşturma kodunu kaldır - ana admin sınıfı hallediyor
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_cmp_update_case_status', array($this, 'update_case_status'));
        add_action('wp_ajax_cmp_add_case_comment', array($this, 'add_case_comment'));
        add_action('wp_ajax_cmp_delete_case', array($this, 'delete_case'));
    }
    
    public function cases_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
        
        switch ($action) {
            case 'view':
                $this->render_case_view($case_id);
                break;
            case 'edit':
                $this->render_case_edit($case_id);
                break;
            default:
                $this->render_cases_list();
                break;
        }
    }
    
    private function render_cases_list() {
        $db = CMP_Database::get_instance();
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $cases = $db->get_cases(null, $status_filter, 20, 0);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Cases', 'case-manager-pro'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=cmp-add-case'); ?>" class="page-title-action"><?php _e('Add New', 'case-manager-pro'); ?></a>
            
            <div class="cmp-cases-filters">
                <form method="get">
                    <input type="hidden" name="page" value="cmp-cases">
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'case-manager-pro'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'case-manager-pro'); ?></option>
                        <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>><?php _e('In Progress', 'case-manager-pro'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'case-manager-pro'); ?></option>
                        <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'case-manager-pro'); ?></option>
                    </select>
                    
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search cases...', 'case-manager-pro'); ?>">
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'case-manager-pro'); ?>">
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'case-manager-pro'); ?></th>
                        <th><?php _e('Title', 'case-manager-pro'); ?></th>
                        <th><?php _e('Submitter', 'case-manager-pro'); ?></th>
                        <th><?php _e('Status', 'case-manager-pro'); ?></th>
                        <th><?php _e('Created', 'case-manager-pro'); ?></th>
                        <th><?php _e('Expires', 'case-manager-pro'); ?></th>
                        <th><?php _e('Actions', 'case-manager-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No cases found.', 'case-manager-pro'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cases as $case): ?>
                            <?php $user = get_user_by('id', $case->user_id); ?>
                            <tr>
                                <td>#<?php echo $case->id; ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=cmp-cases&action=view&case_id=' . $case->id); ?>">
                                            <?php echo esc_html($case->title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo $user ? $user->display_name : __('Unknown', 'case-manager-pro'); ?></td>
                                <td>
                                    <span class="cmp-status cmp-status-<?php echo $case->status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($case->created_at)); ?></td>
                                <td>
                                    <?php if ($case->expires_at): ?>
                                        <?php echo date_i18n(get_option('date_format'), strtotime($case->expires_at)); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cmp-cases&action=view&case_id=' . $case->id); ?>" class="button button-small"><?php _e('View', 'case-manager-pro'); ?></a>
                                    <?php if (current_user_can('cmp_edit_all_cases')): ?>
                                        <a href="<?php echo admin_url('admin.php?page=cmp-cases&action=edit&case_id=' . $case->id); ?>" class="button button-small"><?php _e('Edit', 'case-manager-pro'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .cmp-cases-filters {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cmp-cases-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .cmp-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .cmp-status-pending { background: #fff3cd; color: #856404; }
        .cmp-status-in_progress { background: #d1ecf1; color: #0c5460; }
        .cmp-status-completed { background: #d4edda; color: #155724; }
        .cmp-status-rejected { background: #f8d7da; color: #721c24; }
        .cmp-status-expired { background: #e2e3e5; color: #383d41; }
        </style>
        <?php
    }
    
    private function render_case_view($case_id) {
        $db = CMP_Database::get_instance();
        $case = $db->get_case($case_id);
        
        if (!$case) {
            echo '<div class="wrap"><h1>' . __('Case not found', 'case-manager-pro') . '</h1></div>';
            return;
        }
        
        $user = get_user_by('id', $case->user_id);
        $files = $db->get_case_files($case_id);
        $comments = $db->get_case_comments($case_id, true);
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Case #%d: %s', 'case-manager-pro'), $case->id, esc_html($case->title)); ?></h1>
            
            <div class="cmp-case-details">
                <div class="cmp-case-info">
                    <h2><?php _e('Case Information', 'case-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Submitter', 'case-manager-pro'); ?></th>
                            <td><?php echo $user ? $user->display_name . ' (' . $user->user_email . ')' : __('Unknown', 'case-manager-pro'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'case-manager-pro'); ?></th>
                            <td>
                                <select id="case-status" data-case-id="<?php echo $case->id; ?>">
                                    <option value="pending" <?php selected($case->status, 'pending'); ?>><?php _e('Pending', 'case-manager-pro'); ?></option>
                                    <option value="in_progress" <?php selected($case->status, 'in_progress'); ?>><?php _e('In Progress', 'case-manager-pro'); ?></option>
                                    <option value="completed" <?php selected($case->status, 'completed'); ?>><?php _e('Completed', 'case-manager-pro'); ?></option>
                                    <option value="rejected" <?php selected($case->status, 'rejected'); ?>><?php _e('Rejected', 'case-manager-pro'); ?></option>
                                </select>
                                <button type="button" id="update-status" class="button"><?php _e('Update', 'case-manager-pro'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Created', 'case-manager-pro'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Last Updated', 'case-manager-pro'); ?></th>
                            <td><?php echo $case->updated_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->updated_at)) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Expires', 'case-manager-pro'); ?></th>
                            <td><?php echo $case->expires_at ? date_i18n(get_option('date_format'), strtotime($case->expires_at)) : '-'; ?></td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Description', 'case-manager-pro'); ?></h3>
                    <div class="cmp-case-description">
                        <?php echo nl2br(esc_html($case->description)); ?>
                    </div>
                    
                    <?php if ($case->result): ?>
                        <h3><?php _e('Result', 'case-manager-pro'); ?></h3>
                        <div class="cmp-case-result">
                            <?php echo nl2br(esc_html($case->result)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="cmp-case-files">
                    <h2><?php _e('Attached Files', 'case-manager-pro'); ?></h2>
                    
                    <?php if (empty($files)): ?>
                        <p><?php _e('No files attached.', 'case-manager-pro'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Filename', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Size', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Uploaded', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Actions', 'case-manager-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td><?php echo esc_html($file->original_filename); ?></td>
                                        <td><?php echo size_format($file->file_size); ?></td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($file->uploaded_at)); ?></td>
                                        <td>
                                            <a href="#" class="button button-small cmp-download-file" data-file-id="<?php echo $file->id; ?>"><?php _e('Download', 'case-manager-pro'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="cmp-case-comments">
                    <h2><?php _e('Comments', 'case-manager-pro'); ?></h2>
                    
                    <div class="cmp-comments-list">
                        <?php if (empty($comments)): ?>
                            <p><?php _e('No comments yet.', 'case-manager-pro'); ?></p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="cmp-comment <?php echo $comment->is_private ? 'cmp-comment-private' : ''; ?>">
                                    <div class="cmp-comment-header">
                                        <strong><?php echo esc_html($comment->display_name); ?></strong>
                                        <span class="cmp-comment-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($comment->created_at)); ?></span>
                                        <?php if ($comment->is_private): ?>
                                            <span class="cmp-private-label"><?php _e('Private', 'case-manager-pro'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cmp-comment-content">
                                        <?php echo nl2br(esc_html($comment->comment)); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (current_user_can('cmp_comment_cases')): ?>
                        <div class="cmp-add-comment">
                            <h3><?php _e('Add Comment', 'case-manager-pro'); ?></h3>
                            <form id="cmp-comment-form">
                                <textarea id="comment-text" rows="4" placeholder="<?php _e('Enter your comment...', 'case-manager-pro'); ?>"></textarea>
                                <div class="cmp-comment-options">
                                    <label>
                                        <input type="checkbox" id="comment-private"> <?php _e('Private comment (only visible to reviewers)', 'case-manager-pro'); ?>
                                    </label>
                                </div>
                                <button type="submit" class="button button-primary"><?php _e('Add Comment', 'case-manager-pro'); ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .cmp-case-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .cmp-case-info, .cmp-case-files, .cmp-case-comments {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cmp-case-comments {
            grid-column: 1 / -1;
        }
        
        .cmp-case-description, .cmp-case-result {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .cmp-comment {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            padding: 15px;
        }
        
        .cmp-comment-private {
            background: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .cmp-comment-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .cmp-comment-date {
            color: #666;
            font-size: 12px;
        }
        
        .cmp-private-label {
            background: #856404;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
        }
        
        .cmp-add-comment textarea {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .cmp-comment-options {
            margin-bottom: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Update case status
            $('#update-status').on('click', function() {
                var status = $('#case-status').val();
                var caseId = $('#case-status').data('case-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmp_update_case_status',
                        case_id: caseId,
                        status: status,
                        nonce: '<?php echo wp_create_nonce('cmp_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Status updated successfully', 'case-manager-pro'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error updating status', 'case-manager-pro'); ?>');
                        }
                    }
                });
            });
            
            // Add comment
            $('#cmp-comment-form').on('submit', function(e) {
                e.preventDefault();
                
                var comment = $('#comment-text').val();
                var isPrivate = $('#comment-private').is(':checked');
                
                if (!comment.trim()) {
                    alert('<?php _e('Please enter a comment', 'case-manager-pro'); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmp_add_case_comment',
                        case_id: <?php echo $case->id; ?>,
                        comment: comment,
                        is_private: isPrivate ? 1 : 0,
                        nonce: '<?php echo wp_create_nonce('cmp_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php _e('Error adding comment', 'case-manager-pro'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function add_case_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Case', 'case-manager-pro'); ?></h1>
            <p><?php _e('Create a new case on behalf of a user.', 'case-manager-pro'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('cmp_add_case', 'cmp_add_case_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="case_user"><?php _e('User', 'case-manager-pro'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'case_user',
                                'id' => 'case_user',
                                'show_option_none' => __('Select a user', 'case-manager-pro'),
                                'option_none_value' => 0
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="case_title"><?php _e('Title', 'case-manager-pro'); ?></label></th>
                        <td><input type="text" id="case_title" name="case_title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="case_description"><?php _e('Description', 'case-manager-pro'); ?></label></th>
                        <td><textarea id="case_description" name="case_description" rows="6" class="large-text" required></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="case_status"><?php _e('Status', 'case-manager-pro'); ?></label></th>
                        <td>
                            <select id="case_status" name="case_status">
                                <option value="pending"><?php _e('Pending', 'case-manager-pro'); ?></option>
                                <option value="in_progress"><?php _e('In Progress', 'case-manager-pro'); ?></option>
                                <option value="completed"><?php _e('Completed', 'case-manager-pro'); ?></option>
                                <option value="rejected"><?php _e('Rejected', 'case-manager-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create Case', 'case-manager-pro')); ?>
            </form>
        </div>
        <?php
        
        // Handle form submission
        if (isset($_POST['cmp_add_case_nonce']) && wp_verify_nonce($_POST['cmp_add_case_nonce'], 'cmp_add_case')) {
            $user_id = intval($_POST['case_user']);
            $title = sanitize_text_field($_POST['case_title']);
            $description = sanitize_textarea_field($_POST['case_description']);
            $status = sanitize_text_field($_POST['case_status']);
            
            if ($user_id && $title && $description) {
                $db = CMP_Database::get_instance();
                $case_id = $db->create_case(array(
                    'user_id' => $user_id,
                    'title' => $title,
                    'description' => $description,
                    'status' => $status
                ));
                
                if ($case_id) {
                    echo '<div class="notice notice-success"><p>' . __('Case created successfully!', 'case-manager-pro') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error creating case.', 'case-manager-pro') . '</p></div>';
                }
            }
        }
    }
    
    public function analytics_page() {
        global $wpdb;
        
        // Get statistics
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $total_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases}");
        $pending_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'pending'");
        $completed_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'completed'");
        $rejected_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'rejected'");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Case Analytics', 'case-manager-pro'); ?></h1>
            
            <div class="cmp-analytics-stats">
                <div class="cmp-stat-box">
                    <h3><?php echo $total_cases; ?></h3>
                    <p><?php _e('Total Cases', 'case-manager-pro'); ?></p>
                </div>
                <div class="cmp-stat-box">
                    <h3><?php echo $pending_cases; ?></h3>
                    <p><?php _e('Pending Cases', 'case-manager-pro'); ?></p>
                </div>
                <div class="cmp-stat-box">
                    <h3><?php echo $completed_cases; ?></h3>
                    <p><?php _e('Completed Cases', 'case-manager-pro'); ?></p>
                </div>
                <div class="cmp-stat-box">
                    <h3><?php echo $rejected_cases; ?></h3>
                    <p><?php _e('Rejected Cases', 'case-manager-pro'); ?></p>
                </div>
            </div>
        </div>
        
        <style>
        .cmp-analytics-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .cmp-stat-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }
        
        .cmp-stat-box h3 {
            font-size: 2.5em;
            margin: 0;
            color: #0073aa;
        }
        
        .cmp-stat-box p {
            margin: 10px 0 0 0;
            color: #666;
        }
        </style>
        <?php
    }
    
    public function update_case_status() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('cmp_edit_all_cases')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $case_id = intval($_POST['case_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $db = CMP_Database::get_instance();
        $result = $db->update_case($case_id, array('status' => $status));
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public function add_case_comment() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('cmp_comment_cases')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $case_id = intval($_POST['case_id']);
        $comment = sanitize_textarea_field($_POST['comment']);
        $is_private = intval($_POST['is_private']);
        
        $db = CMP_Database::get_instance();
        $result = $db->add_case_comment($case_id, get_current_user_id(), $comment, $is_private);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public function delete_case() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('cmp_delete_all_cases')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $case_id = intval($_POST['case_id']);
        
        $db = CMP_Database::get_instance();
        $result = $db->delete_case($case_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
} 