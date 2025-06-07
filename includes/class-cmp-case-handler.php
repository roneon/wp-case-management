<?php
/**
 * Case handling functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Case_Handler {
    
    private static $instance = null;
    private $db;
    private $cloud_storage;
    private $file_manager;
    private $notifications;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CMP_Database::get_instance();
        $this->cloud_storage = CMP_Cloud_Storage::get_instance();
        $this->file_manager = CMP_File_Manager::get_instance();
        $this->notifications = CMP_Notifications::get_instance();
        
        add_action('wp_ajax_cmp_update_case_status', array($this, 'handle_status_update'));
        add_action('wp_ajax_cmp_add_case_comment', array($this, 'handle_comment_submission'));
        add_action('wp_ajax_cmp_download_file', array($this, 'handle_file_download'));
        add_action('wp_ajax_cmp_search_cases', array($this, 'handle_case_search'));
        add_action('wp_ajax_cmp_filter_cases', array($this, 'handle_case_filter'));
        add_action('wp_ajax_cmp_load_page', array($this, 'handle_pagination'));
    }
    
    public function handle_status_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!current_user_can('cmp_manage_cases')) {
            wp_send_json_error(__('You do not have permission to update case status', 'case-manager-pro'));
        }
        
        $case_id = intval($_POST['case_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if (!$case_id || !$new_status) {
            wp_send_json_error(__('Case ID and status are required', 'case-manager-pro'));
        }
        
        // Validate status
        $valid_statuses = array('pending', 'in_progress', 'completed', 'rejected', 'expired');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(__('Invalid status', 'case-manager-pro'));
        }
        
        // Get current case
        $case = $this->db->get_case($case_id);
        if (!$case) {
            wp_send_json_error(__('Case not found', 'case-manager-pro'));
        }
        
        // Update status
        $updated = $this->db->update_case($case_id, array(
            'status' => $new_status,
            'updated_at' => current_time('mysql')
        ));
        
        if (!$updated) {
            wp_send_json_error(__('Failed to update case status', 'case-manager-pro'));
        }
        
        // Log activity
        $this->db->log_activity(array(
            'case_id' => $case_id,
            'user_id' => get_current_user_id(),
            'action' => 'status_updated',
            'description' => sprintf(
                __('Case status changed from %s to %s', 'case-manager-pro'),
                $case->status,
                $new_status
            )
        ));
        
        // Send notifications
        $this->notifications->send_status_update_notification($case_id, $new_status);
        
        wp_send_json_success(array(
            'message' => __('Case status updated successfully', 'case-manager-pro')
        ));
    }
    
    public function handle_comment_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to add comments', 'case-manager-pro'));
        }
        
        if (!current_user_can('cmp_comment_case')) {
            wp_send_json_error(__('You do not have permission to add comments', 'case-manager-pro'));
        }
        
        $case_id = intval($_POST['case_id']);
        $comment = sanitize_textarea_field($_POST['comment']);
        $is_private = intval($_POST['is_private']);
        
        if (!$case_id || empty($comment)) {
            wp_send_json_error(__('Case ID and comment are required', 'case-manager-pro'));
        }
        
        // Check if case exists
        $case = $this->db->get_case($case_id);
        if (!$case) {
            wp_send_json_error(__('Case not found', 'case-manager-pro'));
        }
        
        // Check if user can view this case
        $user_roles = CMP_User_Roles::get_instance();
        if (!$user_roles->user_can_view_case(get_current_user_id(), $case)) {
            wp_send_json_error(__('You do not have permission to comment on this case', 'case-manager-pro'));
        }
        
        // Add comment
        $comment_data = array(
            'case_id' => $case_id,
            'user_id' => get_current_user_id(),
            'comment' => $comment,
            'is_private' => $is_private,
            'created_at' => current_time('mysql')
        );
        
        $comment_id = $this->db->add_case_comment($comment_data);
        
        if (!$comment_id) {
            wp_send_json_error(__('Failed to add comment', 'case-manager-pro'));
        }
        
        // Log activity
        $this->db->log_activity(array(
            'case_id' => $case_id,
            'user_id' => get_current_user_id(),
            'action' => 'comment_added',
            'description' => sprintf(
                __('Comment added to case #%d%s', 'case-manager-pro'),
                $case_id,
                $is_private ? ' (private)' : ''
            )
        ));
        
        // Send notifications
        $this->notifications->send_comment_notification($case_id, $comment_id);
        
        wp_send_json_success(array(
            'message' => __('Comment added successfully', 'case-manager-pro')
        ));
    }
    
    public function handle_file_download() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to download files', 'case-manager-pro'));
        }
        
        if (!current_user_can('cmp_download_files')) {
            wp_send_json_error(__('You do not have permission to download files', 'case-manager-pro'));
        }
        
        $file_id = intval($_POST['file_id']);
        
        if (!$file_id) {
            wp_send_json_error(__('File ID is required', 'case-manager-pro'));
        }
        
        // Get file info
        $file = $this->db->get_case_file($file_id);
        if (!$file) {
            wp_send_json_error(__('File not found', 'case-manager-pro'));
        }
        
        // Check if user can access this case
        $case = $this->db->get_case($file->case_id);
        if (!$case) {
            wp_send_json_error(__('Case not found', 'case-manager-pro'));
        }
        
        $user_roles = CMP_User_Roles::get_instance();
        if (!$user_roles->user_can_view_case(get_current_user_id(), $case)) {
            wp_send_json_error(__('You do not have permission to access this file', 'case-manager-pro'));
        }
        
        // Generate download URL
        $download_url = $this->file_manager->get_download_url($file);
        
        if (is_wp_error($download_url)) {
            wp_send_json_error($download_url->get_error_message());
        }
        
        // Log activity
        $this->db->log_activity(array(
            'case_id' => $file->case_id,
            'user_id' => get_current_user_id(),
            'action' => 'file_downloaded',
            'description' => sprintf(
                __('File downloaded: %s', 'case-manager-pro'),
                $file->original_filename
            )
        ));
        
        wp_send_json_success(array(
            'download_url' => $download_url,
            'filename' => $file->original_filename
        ));
    }
    
    public function handle_case_search() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to search cases', 'case-manager-pro'));
        }
        
        $query = sanitize_text_field($_POST['query']);
        $user_id = current_user_can('cmp_view_all_cases') ? null : get_current_user_id();
        
        $cases = $this->db->search_cases($query, $user_id);
        
        ob_start();
        if (empty($cases)) {
            echo '<p>' . __('No cases found matching your search.', 'case-manager-pro') . '</p>';
        } else {
            foreach ($cases as $case) {
                $this->render_case_card($case);
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function handle_case_filter() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to filter cases', 'case-manager-pro'));
        }
        
        $filter_type = sanitize_text_field($_POST['filter_type']);
        $filter_value = sanitize_text_field($_POST['filter_value']);
        $user_id = current_user_can('cmp_view_all_cases') ? null : get_current_user_id();
        
        $cases = $this->db->filter_cases($filter_type, $filter_value, $user_id);
        
        ob_start();
        if (empty($cases)) {
            echo '<p>' . __('No cases found matching your filter.', 'case-manager-pro') . '</p>';
        } else {
            foreach ($cases as $case) {
                $this->render_case_card($case);
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function handle_pagination() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], wp_create_nonce('cmp_nonce'))) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to view cases', 'case-manager-pro'));
        }
        
        $page = intval($_POST['page']);
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        $user_id = current_user_can('cmp_view_all_cases') ? null : get_current_user_id();
        
        $cases = $this->db->get_cases($user_id, '', $per_page, $offset);
        
        ob_start();
        if (empty($cases)) {
            echo '<p>' . __('No cases found.', 'case-manager-pro') . '</p>';
        } else {
            foreach ($cases as $case) {
                $this->render_case_card($case);
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    private function render_case_card($case) {
        $user = get_user_by('id', $case->user_id);
        ?>
        <div class="cmp-case-card">
            <div class="cmp-case-header">
                <h3 class="cmp-case-title">
                    <a href="?case_id=<?php echo $case->id; ?>">
                        Case #<?php echo $case->id; ?>: <?php echo esc_html($case->title); ?>
                    </a>
                </h3>
                <span class="cmp-case-status cmp-status-<?php echo $case->status; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                </span>
            </div>
            <div class="cmp-case-content">
                <p class="cmp-case-description">
                    <?php echo esc_html(wp_trim_words($case->description, 20)); ?>
                </p>
                <div class="cmp-case-meta">
                    <span class="cmp-case-submitter">
                        <?php _e('Submitter:', 'case-manager-pro'); ?> 
                        <?php echo $user ? $user->display_name : __('Unknown', 'case-manager-pro'); ?>
                    </span>
                    <span class="cmp-case-date">
                        <?php _e('Created:', 'case-manager-pro'); ?> 
                        <?php echo date_i18n(get_option('date_format'), strtotime($case->created_at)); ?>
                    </span>
                    <?php if ($case->expires_at): ?>
                        <span class="cmp-case-expires">
                            <?php _e('Expires:', 'case-manager-pro'); ?> 
                            <?php echo date_i18n(get_option('date_format'), strtotime($case->expires_at)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="cmp-case-actions">
                <a href="?case_id=<?php echo $case->id; ?>" class="cmp-btn cmp-btn-primary">
                    <?php _e('View Details', 'case-manager-pro'); ?>
                </a>
                <?php if (current_user_can('cmp_manage_cases')): ?>
                    <a href="?case_id=<?php echo $case->id; ?>&action=edit" class="cmp-btn cmp-btn-secondary">
                        <?php _e('Edit', 'case-manager-pro'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function calculate_expiry_date() {
        $retention_days = get_option('cmp_file_retention_days', 30);
        return date('Y-m-d H:i:s', strtotime('+' . $retention_days . ' days'));
    }
    
    public function get_case_statistics($user_id = null) {
        global $wpdb;
        $table_cases = $wpdb->prefix . 'cmp_cases';
        
        $where_clause = $user_id ? $wpdb->prepare("WHERE user_id = %d", $user_id) : "";
        
        $stats = array();
        
        // Total cases
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} {$where_clause}");
        
        // Cases by status
        $statuses = array('pending', 'in_progress', 'completed', 'rejected', 'expired');
        foreach ($statuses as $status) {
            $where_status = $user_id ? 
                $wpdb->prepare("WHERE user_id = %d AND status = %s", $user_id, $status) :
                $wpdb->prepare("WHERE status = %s", $status);
            
            $stats[$status] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} {$where_status}");
        }
        
        // Cases this month
        $month_start = date('Y-m-01 00:00:00');
        $where_month = $user_id ?
            $wpdb->prepare("WHERE user_id = %d AND created_at >= %s", $user_id, $month_start) :
            $wpdb->prepare("WHERE created_at >= %s", $month_start);
        
        $stats['this_month'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} {$where_month}");
        
        return $stats;
    }
    
    public function cleanup_expired_cases() {
        $expired_cases = $this->db->get_expired_cases();
        
        foreach ($expired_cases as $case) {
            // Delete files from cloud storage
            $files = $this->db->get_case_files($case->id);
            foreach ($files as $file) {
                $this->file_manager->delete_file($file);
            }
            
            // Update case status
            $this->db->update_case($case->id, array(
                'status' => 'expired',
                'updated_at' => current_time('mysql')
            ));
            
            // Log activity
            $this->db->log_activity(array(
                'case_id' => $case->id,
                'user_id' => 0, // System action
                'action' => 'case_expired',
                'description' => sprintf(__('Case #%d expired and files deleted', 'case-manager-pro'), $case->id)
            ));
        }
        
        return count($expired_cases);
    }
} 