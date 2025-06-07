<?php
/**
 * Cloud Storage management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Cloud_Storage {
    
    private static $instance = null;
    private $settings;
    private $provider;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = CMP_Settings::get_instance();
        $this->init_provider();
    }
    
    /**
     * Initialize cloud storage provider
     */
    private function init_provider() {
        $provider = $this->settings->get_option('cloud_provider', 'none');
        
        switch ($provider) {
            case 's3':
                if (class_exists('CMP_Amazon_S3')) {
                    $this->provider = CMP_Amazon_S3::get_instance();
                }
                break;
            case 'google_drive':
                if (class_exists('CMP_Google_Drive')) {
                    $this->provider = CMP_Google_Drive::get_instance();
                }
                break;
            case 'dropbox':
                if (class_exists('CMP_Dropbox')) {
                    $this->provider = CMP_Dropbox::get_instance();
                }
                break;
            default:
                $this->provider = null;
                break;
        }
    }
    
    /**
     * Get available cloud storage providers
     */
    public static function get_available_providers() {
        return array(
            'none' => __('None (Local Storage)', 'case-manager-pro'),
            's3' => __('Amazon S3', 'case-manager-pro'),
            'google_drive' => __('Google Drive', 'case-manager-pro'),
            'dropbox' => __('Dropbox', 'case-manager-pro')
        );
    }
    
    /**
     * Upload file to cloud storage
     */
    public function upload_file($file_path, $file_name, $case_id) {
        if (!$this->provider) {
            return $this->upload_local($file_path, $file_name, $case_id);
        }
        
        try {
            return $this->provider->upload_file($file_path, $file_name, $case_id);
        } catch (Exception $e) {
            error_log('Cloud Storage Upload Error: ' . $e->getMessage());
            // Fallback to local storage
            return $this->upload_local($file_path, $file_name, $case_id);
        }
    }
    
    /**
     * Download file from cloud storage
     */
    public function download_file($file_id) {
        if (!$this->provider) {
            return $this->download_local($file_id);
        }
        
        try {
            return $this->provider->download_file($file_id);
        } catch (Exception $e) {
            error_log('Cloud Storage Download Error: ' . $e->getMessage());
            return new WP_Error('download_failed', $e->getMessage());
        }
    }
    
    /**
     * Delete file from cloud storage
     */
    public function delete_file($file_id) {
        if (!$this->provider) {
            return $this->delete_local($file_id);
        }
        
        try {
            return $this->provider->delete_file($file_id);
        } catch (Exception $e) {
            error_log('Cloud Storage Delete Error: ' . $e->getMessage());
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Delete all files for a case
     */
    public function delete_case_files($case_id) {
        global $wpdb;
        
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_files WHERE case_id = %d",
            $case_id
        ));
        
        foreach ($files as $file) {
            $this->delete_file($file->id);
        }
        
        // Delete file records from database
        $wpdb->delete(
            $wpdb->prefix . 'cmp_files',
            array('case_id' => $case_id),
            array('%d')
        );
    }
    
    /**
     * Test cloud storage connection
     */
    public function test_connection() {
        if (!$this->provider) {
            return array(
                'success' => true,
                'message' => __('Local storage is working', 'case-manager-pro')
            );
        }
        
        try {
            return $this->provider->test_connection();
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Upload file to local storage (fallback)
     */
    private function upload_local($file_path, $file_name, $case_id) {
        // WordPress media library'ye yükle
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Temporary file array oluştur
        $file_array = array(
            'name' => $file_name,
            'tmp_name' => $file_path,
            'size' => filesize($file_path),
            'type' => wp_check_filetype($file_name)['type']
        );
        
        // WordPress upload handler kullan
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true
        );
        
        $uploaded_file = wp_handle_upload($file_array, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_failed', $uploaded_file['error']);
        }
        
        // WordPress attachment oluştur
        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Attachment metadata oluştur
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Case ile ilişkilendir - cmp_files tablosuna kaydet
        global $wpdb;
        
        $file_data = array(
            'case_id' => $case_id,
            'filename' => $file_name,
            'file_path' => $uploaded_file['file'],
            'file_url' => $uploaded_file['url'],
            'file_size' => filesize($uploaded_file['file']),
            'attachment_id' => $attachment_id,
            'storage_provider' => 'local',
            'uploaded_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'cmp_files',
            $file_data
        );
        
        if ($result === false) {
            // Attachment'ı sil eğer database insert başarısız olursa
            wp_delete_attachment($attachment_id, true);
            return new WP_Error('db_error', __('Failed to save file information', 'case-manager-pro'));
        }
        
        return array(
            'success' => true,
            'file_id' => $wpdb->insert_id,
            'attachment_id' => $attachment_id,
            'file_url' => $uploaded_file['url'],
            'message' => __('File uploaded successfully to WordPress media library', 'case-manager-pro')
        );
    }
    
    /**
     * Download file from local storage
     */
    private function download_local($file_id) {
        global $wpdb;
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_files WHERE id = %d",
            $file_id
        ));
        
        if (!$file || !file_exists($file->file_path)) {
            return new WP_Error('file_not_found', __('File not found', 'case-manager-pro'));
        }
        
        return $file->file_path;
    }
    
    /**
     * Delete file from local storage
     */
    private function delete_local($file_id) {
        global $wpdb;
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_files WHERE id = %d",
            $file_id
        ));
        
        if ($file && file_exists($file->file_path)) {
            unlink($file->file_path);
        }
        
        // Delete from database
        $wpdb->delete(
            $wpdb->prefix . 'cmp_files',
            array('id' => $file_id),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Get file info
     */
    public function get_file_info($file_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_files WHERE id = %d",
            $file_id
        ));
    }
    
    /**
     * Get files for a case
     */
    public function get_case_files($case_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cmp_files WHERE case_id = %d ORDER BY uploaded_at DESC",
            $case_id
        ));
    }
    
    /**
     * Get storage usage statistics
     */
    public function get_storage_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size
            FROM {$wpdb->prefix}cmp_files"
        );
        
        // Null değerleri kontrol et
        $total_files = $stats ? intval($stats->total_files) : 0;
        $total_size = $stats && $stats->total_size ? intval($stats->total_size) : 0;
        $avg_size = $stats && $stats->avg_size ? intval($stats->avg_size) : 0;
        
        return array(
            'total_files' => $total_files,
            'total_size' => $total_size,
            'avg_size' => $avg_size,
            'total_size_formatted' => size_format($total_size),
            'avg_size_formatted' => size_format($avg_size)
        );
    }
} 