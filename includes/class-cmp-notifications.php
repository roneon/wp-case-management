<?php
/**
 * Notifications management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Notifications {
    
    private static $instance = null;
    private $settings;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = CMP_Settings::get_instance();
        
        // Hook into case events
        add_action('cmp_case_submitted', array($this, 'on_case_submitted'), 10, 2);
        add_action('cmp_case_status_changed', array($this, 'on_case_status_changed'), 10, 3);
        add_action('cmp_case_comment_added', array($this, 'on_case_comment_added'), 10, 3);
        add_action('cmp_case_assigned', array($this, 'on_case_assigned'), 10, 3);
        add_action('cmp_file_uploaded', array($this, 'on_file_uploaded'), 10, 3);
        add_action('cmp_file_expiring', array($this, 'on_file_expiring'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_cmp_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_cmp_get_notifications', array($this, 'get_user_notifications_ajax'));
        add_action('wp_ajax_cmp_clear_all_notifications', array($this, 'clear_all_notifications'));
        add_action('wp_ajax_cmp_check_notifications', array($this, 'check_notifications'));
        
        // Admin notification settings
        add_action('wp_ajax_cmp_test_email', array($this, 'test_email_notification'));
    }
    
    /**
     * Send notification when case is submitted
     */
    public function on_case_submitted($case_id, $case_data) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        // Notify administrators and case managers
        $recipients = $this->get_notification_recipients('case_submitted');
        
        foreach ($recipients as $user_id) {
            // Create in-app notification
            $this->create_notification($user_id, array(
                'type' => 'case_submitted',
                'title' => __('New Case Submitted', 'case-manager-pro'),
                'message' => sprintf(__('Case #%d "%s" has been submitted by %s', 'case-manager-pro'), 
                    $case->id, $case->title, get_userdata($case->user_id)->display_name),
                'case_id' => $case_id,
                'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
            ));
            
            // Send email notification if enabled
            if ($this->should_send_email($user_id, 'case_submitted')) {
                $this->send_email_notification($user_id, 'case_submitted', array(
                    'case' => $case,
                    'submitter' => get_userdata($case->user_id)
                ));
            }
        }
        
        // Notify case submitter
        $this->create_notification($case->user_id, array(
            'type' => 'case_submitted_confirmation',
            'title' => __('Case Submitted Successfully', 'case-manager-pro'),
            'message' => sprintf(__('Your case #%d "%s" has been submitted successfully', 'case-manager-pro'), 
                $case->id, $case->title),
            'case_id' => $case_id,
            'url' => home_url('/?cmp_case=' . $case_id)
        ));
    }
    
    /**
     * Send notification when case status changes
     */
    public function on_case_status_changed($case_id, $old_status, $new_status) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        $status_labels = array(
            'pending' => __('Pending', 'case-manager-pro'),
            'in_progress' => __('In Progress', 'case-manager-pro'),
            'resolved' => __('Resolved', 'case-manager-pro'),
            'closed' => __('Closed', 'case-manager-pro')
        );
        
        // Notify case submitter
        $this->create_notification($case->user_id, array(
            'type' => 'case_status_changed',
            'title' => __('Case Status Updated', 'case-manager-pro'),
            'message' => sprintf(__('Case #%d status changed from %s to %s', 'case-manager-pro'), 
                $case->id, 
                $status_labels[$old_status] ?? $old_status, 
                $status_labels[$new_status] ?? $new_status),
            'case_id' => $case_id,
            'url' => home_url('/?cmp_case=' . $case_id)
        ));
        
        // Send email if enabled
        if ($this->should_send_email($case->user_id, 'case_status_changed')) {
            $this->send_email_notification($case->user_id, 'case_status_changed', array(
                'case' => $case,
                'old_status' => $status_labels[$old_status] ?? $old_status,
                'new_status' => $status_labels[$new_status] ?? $new_status
            ));
        }
        
        // Notify assigned users if any
        if ($case->assigned_to) {
            $this->create_notification($case->assigned_to, array(
                'type' => 'assigned_case_status_changed',
                'title' => __('Assigned Case Status Updated', 'case-manager-pro'),
                'message' => sprintf(__('Case #%d status changed to %s', 'case-manager-pro'), 
                    $case->id, $status_labels[$new_status] ?? $new_status),
                'case_id' => $case_id,
                'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
            ));
        }
    }
    
    /**
     * Create in-app notification
     */
    public function create_notification($user_id, $data) {
        global $wpdb;
        
        $notification_data = array(
            'user_id' => $user_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'case_id' => $data['case_id'] ?? null,
            'url' => $data['url'] ?? '',
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'cmp_notifications',
            $notification_data,
            array('%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result) {
            // Trigger action for real-time notifications
            do_action('cmp_notification_created', $wpdb->insert_id, $user_id, $data);
        }
        
        return $result;
    }
    
    /**
     * Send email notification
     */
    public function send_email_notification($user_id, $type, $data = array()) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        
        $template = $this->get_email_template($type);
        if (!$template) return false;
        
        $subject = $this->parse_template($template['subject'], $data);
        $message = $this->parse_template($template['message'], $data);
        
        // Add email header and footer
        $message = $this->wrap_email_content($message, $data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($type) {
        $templates = array(
            'case_submitted' => array(
                'subject' => __('New Case Submitted - #{case_id}', 'case-manager-pro'),
                'message' => __('A new case has been submitted:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Submitted by:</strong> {submitter_name}<br>
                    <strong>Priority:</strong> {case_priority}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            ),
            'case_status_changed' => array(
                'subject' => __('Case Status Updated - #{case_id}', 'case-manager-pro'),
                'message' => __('Your case status has been updated:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Previous Status:</strong> {old_status}<br>
                    <strong>New Status:</strong> {new_status}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            ),
            'case_comment_added' => array(
                'subject' => __('New Comment on Case #{case_id}', 'case-manager-pro'),
                'message' => __('A new comment has been added to your case:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Comment by:</strong> {commenter_name}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            ),
            'case_assigned' => array(
                'subject' => __('Case Assigned - #{case_id}', 'case-manager-pro'),
                'message' => __('A case has been assigned to you:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Assigned by:</strong> {assigner_name}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            ),
            'file_uploaded' => array(
                'subject' => __('New File Added to Case #{case_id}', 'case-manager-pro'),
                'message' => __('A new file has been uploaded to your case:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Uploaded by:</strong> {uploader_name}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            ),
            'file_expiring' => array(
                'subject' => __('Case Files Expiring Soon - #{case_id}', 'case-manager-pro'),
                'message' => __('Files for your case will expire soon:<br><br>
                    <strong>Case ID:</strong> #{case_id}<br>
                    <strong>Title:</strong> {case_title}<br>
                    <strong>Days Remaining:</strong> {days_remaining}<br><br>
                    <a href="{case_url}">View Case</a>', 'case-manager-pro')
            )
        );
        
        return $templates[$type] ?? null;
    }
    
    /**
     * Parse email template
     */
    private function parse_template($template, $data) {
        $replacements = array();
        
        if (isset($data['case'])) {
            $case = $data['case'];
            $replacements['{case_id}'] = $case->id;
            $replacements['{case_title}'] = $case->title;
            $replacements['{case_priority}'] = ucfirst($case->priority);
            $replacements['{case_url}'] = home_url('/?cmp_case=' . $case->id);
        }
        
        if (isset($data['submitter'])) {
            $replacements['{submitter_name}'] = $data['submitter']->display_name;
        }
        
        if (isset($data['old_status'])) {
            $replacements['{old_status}'] = $data['old_status'];
        }
        
        if (isset($data['new_status'])) {
            $replacements['{new_status}'] = $data['new_status'];
        }
        
        if (isset($data['commenter'])) {
            $replacements['{commenter_name}'] = $data['commenter']->display_name;
        }
        
        if (isset($data['assigner'])) {
            $replacements['{assigner_name}'] = $data['assigner']->display_name;
        }
        
        if (isset($data['uploader'])) {
            $replacements['{uploader_name}'] = $data['uploader']->display_name;
        }
        
        if (isset($data['days_remaining'])) {
            $replacements['{days_remaining}'] = $data['days_remaining'];
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Wrap email content with header and footer
     */
    private function wrap_email_content($content, $data = array()) {
        $site_name = get_option('blogname');
        $site_url = home_url();
        
        $header = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .email-header { background: #f8f9fa; padding: 20px; text-align: center; border-bottom: 3px solid #007cba; }
                .email-content { padding: 20px; background: #fff; }
                .email-footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                a { color: #007cba; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h2>{$site_name}</h2>
                    <p>" . __('Case Management System', 'case-manager-pro') . "</p>
                </div>
                <div class='email-content'>
        ";
        
        $footer = "
                </div>
                <div class='email-footer'>
                    <p>" . sprintf(__('This email was sent from %s', 'case-manager-pro'), "<a href='{$site_url}'>{$site_name}</a>") . "</p>
                    <p>" . __('Please do not reply to this email.', 'case-manager-pro') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $header . $content . $footer;
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read() {
        // Multiple nonce support for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cmp_nonce') || 
                wp_verify_nonce($_POST['nonce'], 'cmp_dashboard_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cmp_frontend_nonce')) {
                $nonce_valid = true;
            }
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'case-manager-pro'));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'cmp_notifications',
            array('is_read' => 1),
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to mark notification as read', 'case-manager-pro'));
        }
    }
    
    /**
     * Clear all notifications for user
     */
    public function clear_all_notifications() {
        // Multiple nonce support for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cmp_nonce') || 
                wp_verify_nonce($_POST['nonce'], 'cmp_dashboard_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cmp_frontend_nonce')) {
                $nonce_valid = true;
            }
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'case-manager-pro'));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'cmp_notifications',
            array('is_read' => 1),
            array('user_id' => $user_id, 'is_read' => 0),
            array('%d'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to mark all notifications as read', 'case-manager-pro'));
        }
    }
    
    /**
     * Check for new notifications (AJAX)
     */
    public function check_notifications() {
        // Multiple nonce support for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cmp_nonce') || 
                wp_verify_nonce($_POST['nonce'], 'cmp_dashboard_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cmp_frontend_nonce')) {
                $nonce_valid = true;
            }
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'case-manager-pro'));
        }
        
        $unread_count = $this->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'count' => $unread_count
        ));
    }
    
    /**
     * Get user notifications
     */
    public function get_user_notifications($user_id, $page = 1, $per_page = 10) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_notifications 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ));
    }
    
    /**
     * Get user notifications via AJAX
     */
    public function get_user_notifications_ajax() {
        // Multiple nonce support for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cmp_nonce') || 
                wp_verify_nonce($_POST['nonce'], 'cmp_dashboard_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cmp_frontend_nonce')) {
                $nonce_valid = true;
            }
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'case-manager-pro'));
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'case-manager-pro'));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);
        
        $notifications = $this->get_user_notifications($user_id, $page, $per_page);
        $unread_count = $this->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ));
    }
    
    /**
     * Get unread notification count
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cmp_notifications 
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    /**
     * Check if email should be sent
     */
    private function should_send_email($user_id, $type) {
        // Check global email settings
        if (!$this->settings->get('email_notifications_enabled', true)) {
            return false;
        }
        
        // Check user preferences
        $user_prefs = get_user_meta($user_id, 'cmp_email_preferences', true);
        if (is_array($user_prefs) && isset($user_prefs[$type])) {
            return $user_prefs[$type];
        }
        
        // Default to enabled
        return true;
    }
    
    /**
     * Get notification recipients for a type
     */
    private function get_notification_recipients($type) {
        $recipients = array();
        
        switch ($type) {
            case 'case_submitted':
                // Get all case managers and administrators
                $users = get_users(array(
                    'meta_query' => array(
                        'relation' => 'OR',
                        array(
                            'key' => 'wp_capabilities',
                            'value' => 'cmp_manage_cases',
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key' => 'wp_capabilities',
                            'value' => 'administrator',
                            'compare' => 'LIKE'
                        )
                    )
                ));
                
                foreach ($users as $user) {
                    $recipients[] = $user->ID;
                }
                break;
        }
        
        return array_unique($recipients);
    }
    
    /**
     * Get case data
     */
    private function get_case($case_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_cases WHERE id = %d",
            $case_id
        ));
    }
    
    /**
     * Test email notification (admin only)
     */
    public function test_email_notification() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Access denied', 'case-manager-pro'));
        }
        
        check_ajax_referer('cmp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        if (!$email) {
            wp_send_json_error(__('Invalid email address', 'case-manager-pro'));
        }
        
        $subject = __('Test Email - Case Manager Pro', 'case-manager-pro');
        $message = __('This is a test email from Case Manager Pro. If you received this, email notifications are working correctly.', 'case-manager-pro');
        $message = $this->wrap_email_content($message);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        $result = wp_mail($email, $subject, $message, $headers);
        
        if ($result) {
            wp_send_json_success(__('Test email sent successfully', 'case-manager-pro'));
        } else {
            wp_send_json_error(__('Failed to send test email', 'case-manager-pro'));
        }
    }
    
    /**
     * Send notification when comment is added
     */
    public function on_case_comment_added($case_id, $comment_id, $comment_data) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        $commenter = get_userdata($comment_data['user_id']);
        if (!$commenter) return;
        
        // Notify case submitter if they didn't add the comment
        if ($case->user_id != $comment_data['user_id']) {
            $this->create_notification($case->user_id, array(
                'type' => 'case_comment_added',
                'title' => __('New Comment on Your Case', 'case-manager-pro'),
                'message' => sprintf(__('%s added a comment to case #%d', 'case-manager-pro'), 
                    $commenter->display_name, $case->id),
                'case_id' => $case_id,
                'url' => home_url('/?cmp_case=' . $case_id)
            ));
        }
        
        // Notify assigned user if different from submitter and commenter
        if ($case->assigned_to && 
            $case->assigned_to != $case->user_id && 
            $case->assigned_to != $comment_data['user_id']) {
            $this->create_notification($case->assigned_to, array(
                'type' => 'assigned_case_comment_added',
                'title' => __('New Comment on Assigned Case', 'case-manager-pro'),
                'message' => sprintf(__('%s added a comment to case #%d', 'case-manager-pro'), 
                    $commenter->display_name, $case->id),
                'case_id' => $case_id,
                'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
            ));
        }
    }
    
    /**
     * Send notification when case is assigned
     */
    public function on_case_assigned($case_id, $assigned_to, $assigned_by) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        $assigner = get_userdata($assigned_by);
        
        // Notify assigned user
        $this->create_notification($assigned_to, array(
            'type' => 'case_assigned',
            'title' => __('Case Assigned to You', 'case-manager-pro'),
            'message' => sprintf(__('Case #%d "%s" has been assigned to you by %s', 'case-manager-pro'), 
                $case->id, $case->title, $assigner ? $assigner->display_name : 'System'),
            'case_id' => $case_id,
            'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
        ));
        
        // Notify case submitter
        if ($case->user_id != $assigned_to) {
            $assigned_user = get_userdata($assigned_to);
            $this->create_notification($case->user_id, array(
                'type' => 'case_assignment_notification',
                'title' => __('Your Case Has Been Assigned', 'case-manager-pro'),
                'message' => sprintf(__('Case #%d has been assigned to %s', 'case-manager-pro'), 
                    $case->id, $assigned_user ? $assigned_user->display_name : 'a team member'),
                'case_id' => $case_id,
                'url' => home_url('/?cmp_case=' . $case_id)
            ));
        }
    }
    
    /**
     * Send notification when file is uploaded
     */
    public function on_file_uploaded($case_id, $file_id, $file_data) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        $uploader = get_userdata($file_data['uploaded_by'] ?? get_current_user_id());
        
        // Notify case submitter if they didn't upload the file
        if ($case->user_id != ($file_data['uploaded_by'] ?? get_current_user_id())) {
            $this->create_notification($case->user_id, array(
                'type' => 'file_uploaded',
                'title' => __('New File Added to Your Case', 'case-manager-pro'),
                'message' => sprintf(__('%s uploaded a file to case #%d', 'case-manager-pro'), 
                    $uploader ? $uploader->display_name : 'Someone', $case->id),
                'case_id' => $case_id,
                'url' => home_url('/?cmp_case=' . $case_id)
            ));
        }
        
        // Notify assigned user if different
        if ($case->assigned_to && 
            $case->assigned_to != $case->user_id && 
            $case->assigned_to != ($file_data['uploaded_by'] ?? get_current_user_id())) {
            $this->create_notification($case->assigned_to, array(
                'type' => 'assigned_case_file_uploaded',
                'title' => __('New File Added to Assigned Case', 'case-manager-pro'),
                'message' => sprintf(__('%s uploaded a file to case #%d', 'case-manager-pro'), 
                    $uploader ? $uploader->display_name : 'Someone', $case->id),
                'case_id' => $case_id,
                'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
            ));
        }
    }
    
    /**
     * Send notification when file is expiring
     */
    public function on_file_expiring($case_id, $days_remaining) {
        $case = $this->get_case($case_id);
        if (!$case) return;
        
        // Notify case submitter
        $this->create_notification($case->user_id, array(
            'type' => 'file_expiring',
            'title' => __('Case Files Expiring Soon', 'case-manager-pro'),
            'message' => sprintf(__('Files for case #%d will expire in %d days', 'case-manager-pro'), 
                $case->id, $days_remaining),
            'case_id' => $case_id,
            'url' => home_url('/?cmp_case=' . $case_id)
        ));
        
        // Notify assigned user if different
        if ($case->assigned_to && $case->assigned_to != $case->user_id) {
            $this->create_notification($case->assigned_to, array(
                'type' => 'assigned_case_file_expiring',
                'title' => __('Assigned Case Files Expiring Soon', 'case-manager-pro'),
                'message' => sprintf(__('Files for assigned case #%d will expire in %d days', 'case-manager-pro'), 
                    $case->id, $days_remaining),
                'case_id' => $case_id,
                'url' => admin_url('admin.php?page=cmp-cases&case_id=' . $case_id)
            ));
        }
    }
} 