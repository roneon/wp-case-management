<?php
/**
 * Settings management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Settings {
    
    private static $instance = null;
    private $options = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_options();
    }
    
    /**
     * Load all plugin options
     */
    private function load_options() {
        $this->options = array(
            'cloud_provider' => get_option('cmp_cloud_provider', 'none'),
            'file_retention_days' => get_option('cmp_file_retention_days', 30),
            'max_file_size' => get_option('cmp_max_file_size', 2048),
            'allowed_file_types' => get_option('cmp_allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar'),
            'enable_notifications' => get_option('cmp_enable_notifications', true),
            'dashboard_page_id' => get_option('cmp_dashboard_page_id', 0),
            
            // Amazon S3 settings
            's3_access_key' => get_option('cmp_s3_access_key', ''),
            's3_secret_key' => get_option('cmp_s3_secret_key', ''),
            's3_bucket' => get_option('cmp_s3_bucket', ''),
            's3_region' => get_option('cmp_s3_region', 'us-east-1'),
            
            // Google Drive settings
            'gdrive_client_id' => get_option('cmp_gdrive_client_id', ''),
            'gdrive_client_secret' => get_option('cmp_gdrive_client_secret', ''),
            'gdrive_folder_id' => get_option('cmp_gdrive_folder_id', ''),
            'gdrive_access_token' => get_option('cmp_gdrive_access_token', ''),
            'gdrive_refresh_token' => get_option('cmp_gdrive_refresh_token', ''),
            
            // Dropbox settings
            'dropbox_access_token' => get_option('cmp_dropbox_access_token', ''),
            'dropbox_app_key' => get_option('cmp_dropbox_app_key', ''),
            'dropbox_app_secret' => get_option('cmp_dropbox_app_secret', ''),
            
            // Email settings
            'email_from_name' => get_option('cmp_email_from_name', get_bloginfo('name')),
            'email_from_address' => get_option('cmp_email_from_address', get_option('admin_email')),
            'email_notifications_admin' => get_option('cmp_email_notifications_admin', true),
            'email_notifications_user' => get_option('cmp_email_notifications_user', true),
        );
    }
    
    public function get_option($key, $default = '') {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return get_option('cmp_' . $key, $default);
    }
    
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        return update_option('cmp_' . $key, $value);
    }
    
    public function delete_option($key) {
        unset($this->options[$key]);
        return delete_option('cmp_' . $key);
    }
    
    public function get_cloud_provider() {
        return $this->get_option('cloud_provider', 'none');
    }
    
    public function get_file_retention_days() {
        return intval($this->get_option('file_retention_days', 30));
    }
    
    public function get_max_file_size() {
        return intval($this->get_option('max_file_size', 2048));
    }
    
    public function get_allowed_file_types() {
        $types = $this->get_option('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar');
        return array_map('trim', explode(',', $types));
    }
    
    public function is_notifications_enabled() {
        return (bool) $this->get_option('enable_notifications', true);
    }
    
    public function get_dashboard_page_id() {
        return intval($this->get_option('dashboard_page_id', 0));
    }
    
    /**
     * Get S3 settings
     */
    public function get_s3_settings() {
        return array(
            'access_key' => $this->get_option('s3_access_key'),
            'secret_key' => $this->get_option('s3_secret_key'),
            'bucket' => $this->get_option('s3_bucket'),
            'region' => $this->get_option('s3_region', 'us-east-1')
        );
    }
    
    /**
     * Get Google Drive settings
     */
    public function get_gdrive_settings() {
        return array(
            'client_id' => $this->get_option('gdrive_client_id'),
            'client_secret' => $this->get_option('gdrive_client_secret'),
            'folder_id' => $this->get_option('gdrive_folder_id'),
            'access_token' => $this->get_option('gdrive_access_token'),
            'refresh_token' => $this->get_option('gdrive_refresh_token')
        );
    }
    
    /**
     * Get Dropbox settings
     */
    public function get_dropbox_settings() {
        return array(
            'access_token' => $this->get_option('dropbox_access_token'),
            'app_key' => $this->get_option('dropbox_app_key'),
            'app_secret' => $this->get_option('dropbox_app_secret')
        );
    }
    
    /**
     * Get email settings
     */
    public function get_email_settings() {
        return array(
            'from_name' => $this->get_option('email_from_name', get_bloginfo('name')),
            'from_address' => $this->get_option('email_from_address', get_option('admin_email')),
            'notifications_admin' => $this->get_option('email_notifications_admin', true),
            'notifications_user' => $this->get_option('email_notifications_user', true)
        );
    }
    
    /**
     * Check if cloud provider is properly configured
     */
    public function is_cloud_provider_configured() {
        $provider = $this->get_cloud_provider();
        
        switch ($provider) {
            case 's3':
                $settings = $this->get_s3_settings();
                return !empty($settings['access_key']) && !empty($settings['secret_key']) && !empty($settings['bucket']);
                
            case 'gdrive':
                $settings = $this->get_gdrive_settings();
                return !empty($settings['client_id']) && !empty($settings['client_secret']);
                
            case 'dropbox':
                $settings = $this->get_dropbox_settings();
                return !empty($settings['access_token']);
                
            case 'none':
            default:
                return true;
        }
    }
    
    /**
     * Get all options as array
     */
    public function get_all_options() {
        return $this->options;
    }
    
    /**
     * Reset to default settings
     */
    public function reset_to_defaults() {
        $defaults = array(
            'cloud_provider' => 'none',
            'file_retention_days' => 30,
            'max_file_size' => 2048,
            'allowed_file_types' => 'pdf,doc,docx,jpg,jpeg,png,zip,rar',
            'enable_notifications' => true,
            'dashboard_page_id' => 0
        );
        
        foreach ($defaults as $key => $value) {
            $this->update_option($key, $value);
        }
        
        $this->load_options();
    }
} 