<?php
/**
 * File manager class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_File_Manager {
    
    private static $instance = null;
    private $cloud_storage;
    private $settings;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->cloud_storage = CMP_Cloud_Storage::get_instance();
        $this->settings = CMP_Settings::get_instance();
        
        add_action('wp_ajax_cmp_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_nopriv_cmp_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_cmp_delete_file', array($this, 'handle_file_delete'));
        add_action('wp_ajax_cmp_download_file', array($this, 'handle_file_download'));
        add_action('wp_ajax_nopriv_cmp_download_file', array($this, 'handle_file_download'));
    }
    
    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        check_ajax_referer('cmp_nonce', 'nonce');
        
        if (!isset($_FILES['file']) || !isset($_POST['case_id'])) {
            wp_send_json_error(__('Missing required data', 'case-manager-pro'));
        }
        
        $case_id = intval($_POST['case_id']);
        $file = $_FILES['file'];
        
        // Validate case access
        if (!$this->can_access_case($case_id)) {
            wp_send_json_error(__('Access denied', 'case-manager-pro'));
        }
        
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        // Upload to cloud storage
        $upload_result = $this->upload_file($file, $case_id);
        if (is_wp_error($upload_result)) {
            wp_send_json_error($upload_result->get_error_message());
        }
        
        wp_send_json_success(array(
            'file_id' => $upload_result['file_id'],
            'filename' => $upload_result['filename'],
            'size' => $upload_result['size'],
            'upload_date' => $upload_result['upload_date']
        ));
    }
    
    /**
     * Handle file deletion via AJAX
     */
    public function handle_file_delete() {
        check_ajax_referer('cmp_nonce', 'nonce');
        
        if (!isset($_POST['file_id'])) {
            wp_send_json_error(__('Missing file ID', 'case-manager-pro'));
        }
        
        $file_id = intval($_POST['file_id']);
        
        // Get file info
        $file = $this->get_file($file_id);
        if (!$file) {
            wp_send_json_error(__('File not found', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!$this->can_delete_file($file)) {
            wp_send_json_error(__('Access denied', 'case-manager-pro'));
        }
        
        // Delete from cloud storage
        $delete_result = $this->delete_file($file_id);
        if (is_wp_error($delete_result)) {
            wp_send_json_error($delete_result->get_error_message());
        }
        
        wp_send_json_success(__('File deleted successfully', 'case-manager-pro'));
    }
    
    /**
     * Handle file download
     */
    public function handle_file_download() {
        if (!isset($_GET['file_id']) || !isset($_GET['nonce'])) {
            wp_die(__('Invalid request', 'case-manager-pro'));
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'cmp_download_' . $_GET['file_id'])) {
            wp_die(__('Security check failed', 'case-manager-pro'));
        }
        
        $file_id = intval($_GET['file_id']);
        
        // Get file info
        $file = $this->get_file($file_id);
        if (!$file) {
            wp_die(__('File not found', 'case-manager-pro'));
        }
        
        // Check permissions
        if (!$this->can_access_case($file->case_id)) {
            wp_die(__('Access denied', 'case-manager-pro'));
        }
        
        // Get download URL from cloud storage
        $download_url = $this->cloud_storage->get_download_url($file->cloud_path);
        if (is_wp_error($download_url)) {
            wp_die($download_url->get_error_message());
        }
        
        // Log download activity
        $this->log_file_activity($file_id, 'downloaded');
        
        // Redirect to download URL
        wp_redirect($download_url);
        exit;
    }
    
    /**
     * Upload file to cloud storage
     */
    public function upload_file($file, $case_id) {
        global $wpdb;
        
        // Generate unique filename
        $filename = $this->generate_unique_filename($file['name']);
        $cloud_path = $this->generate_cloud_path($case_id, $filename);
        
        // Upload to cloud
        $upload_result = $this->cloud_storage->upload_file($file['tmp_name'], $cloud_path, $file['type']);
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }
        
        // Calculate expiry date
        $retention_days = $this->settings->get('file_retention_days', 30);
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));
        
        // Save to database
        $file_data = array(
            'case_id' => $case_id,
            'filename' => sanitize_file_name($file['name']),
            'original_filename' => sanitize_file_name($file['name']),
            'file_size' => $file['size'],
            'file_type' => $file['type'],
            'cloud_path' => $cloud_path,
            'cloud_provider' => $this->settings->get('cloud_provider'),
            'uploaded_by' => get_current_user_id(),
            'upload_date' => current_time('mysql'),
            'expiry_date' => $expiry_date,
            'status' => 'active'
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'cmp_case_files',
            $file_data,
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            // Delete from cloud if database insert failed
            $this->cloud_storage->delete_file($cloud_path);
            return new WP_Error('db_error', __('Failed to save file information', 'case-manager-pro'));
        }
        
        $file_id = $wpdb->insert_id;
        
        // Log activity
        $this->log_file_activity($file_id, 'uploaded');
        
        return array(
            'file_id' => $file_id,
            'filename' => $file_data['filename'],
            'size' => $file_data['file_size'],
            'upload_date' => $file_data['upload_date']
        );
    }
    
    /**
     * Delete file from cloud storage and database
     */
    public function delete_file($file_id) {
        global $wpdb;
        
        $file = $this->get_file($file_id);
        if (!$file) {
            return new WP_Error('not_found', __('File not found', 'case-manager-pro'));
        }
        
        // Delete from cloud storage
        $delete_result = $this->cloud_storage->delete_file($file->cloud_path);
        if (is_wp_error($delete_result)) {
            return $delete_result;
        }
        
        // Update database status
        $result = $wpdb->update(
            $wpdb->prefix . 'cmp_case_files',
            array('status' => 'deleted', 'deleted_date' => current_time('mysql')),
            array('id' => $file_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update file status', 'case-manager-pro'));
        }
        
        // Log activity
        $this->log_file_activity($file_id, 'deleted');
        
        return true;
    }
    
    /**
     * Get file information
     */
    public function get_file($file_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_case_files WHERE id = %d AND status = 'active'",
            $file_id
        ));
    }
    
    /**
     * Get files for a case
     */
    public function get_case_files($case_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_case_files WHERE case_id = %d AND status = 'active' ORDER BY upload_date DESC",
            $case_id
        ));
    }
    
    /**
     * Validate uploaded file
     */
    public function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }
        
        // Check file size
        $max_size = $this->settings->get('max_file_size', 2147483648); // 2GB default
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', sprintf(
                __('File size exceeds maximum allowed size of %s', 'case-manager-pro'),
                size_format($max_size)
            ));
        }
        
        // Check file type
        $allowed_types = $this->settings->get('allowed_file_types', array());
        if (!empty($allowed_types)) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_types)) {
                return new WP_Error('invalid_file_type', sprintf(
                    __('File type "%s" is not allowed', 'case-manager-pro'),
                    $file_extension
                ));
            }
        }
        
        // Check for malicious files
        if ($this->is_malicious_file($file)) {
            return new WP_Error('malicious_file', __('File appears to be malicious', 'case-manager-pro'));
        }
        
        return true;
    }
    
    /**
     * Check if user can access case
     */
    private function can_access_case($case_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        global $wpdb;
        $case = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_cases WHERE id = %d",
            $case_id
        ));
        
        if (!$case) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        
        // Case submitter can access their own cases
        if ($case->submitted_by == $current_user_id) {
            return true;
        }
        
        // Case reviewers and managers can access assigned cases
        if (current_user_can('cmp_review_cases') || current_user_can('cmp_manage_cases')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can delete file
     */
    private function can_delete_file($file) {
        if (current_user_can('manage_options') || current_user_can('cmp_manage_cases')) {
            return true;
        }
        
        // File uploader can delete their own files
        if ($file->uploaded_by == get_current_user_id()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate unique filename
     */
    private function generate_unique_filename($original_name) {
        $info = pathinfo($original_name);
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
        $basename = sanitize_file_name($info['filename']);
        
        return $basename . '_' . uniqid() . $extension;
    }
    
    /**
     * Generate cloud storage path
     */
    private function generate_cloud_path($case_id, $filename) {
        $year = date('Y');
        $month = date('m');
        
        return "case-files/{$year}/{$month}/case-{$case_id}/{$filename}";
    }
    
    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('File is too large', 'case-manager-pro');
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded', 'case-manager-pro');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'case-manager-pro');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary folder', 'case-manager-pro');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'case-manager-pro');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension', 'case-manager-pro');
            default:
                return __('Unknown upload error', 'case-manager-pro');
        }
    }
    
    /**
     * Check for malicious files
     */
    private function is_malicious_file($file) {
        // Check file content for malicious patterns
        $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        
        $malicious_patterns = array(
            '/<\?php/',
            '/<script/',
            '/eval\s*\(/',
            '/exec\s*\(/',
            '/system\s*\(/',
            '/shell_exec\s*\(/',
            '/passthru\s*\(/',
            '/base64_decode\s*\(/'
        );
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log file activity
     */
    private function log_file_activity($file_id, $action) {
        global $wpdb;
        
        $file = $this->get_file($file_id);
        if (!$file) {
            return;
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'cmp_activity_log',
            array(
                'case_id' => $file->case_id,
                'user_id' => get_current_user_id(),
                'action' => 'file_' . $action,
                'description' => sprintf(__('File "%s" was %s', 'case-manager-pro'), $file->filename, $action),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Clean up expired files (called by cron)
     */
    public function cleanup_expired_files() {
        global $wpdb;
        
        $expired_files = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}cmp_case_files 
             WHERE status = 'active' AND expiry_date < NOW()"
        );
        
        foreach ($expired_files as $file) {
            $this->delete_file($file->id);
        }
        
        return count($expired_files);
    }
    
    /**
     * Get file download URL with nonce
     */
    public function get_download_url($file_id) {
        return wp_nonce_url(
            admin_url('admin-ajax.php?action=cmp_download_file&file_id=' . $file_id),
            'cmp_download_' . $file_id,
            'nonce'
        );
    }
    
    /**
     * Get file statistics
     */
    public function get_file_stats($case_id = null) {
        global $wpdb;
        
        $where = "WHERE status = 'active'";
        $params = array();
        
        if ($case_id) {
            $where .= " AND case_id = %d";
            $params[] = $case_id;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size
             FROM {$wpdb->prefix}cmp_case_files {$where}",
            $params
        ));
        
        return array(
            'total_files' => intval($stats->total_files),
            'total_size' => intval($stats->total_size),
            'avg_size' => floatval($stats->avg_size),
            'total_size_formatted' => size_format($stats->total_size),
            'avg_size_formatted' => size_format($stats->avg_size)
        );
    }
} 