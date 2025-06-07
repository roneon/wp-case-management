<?php
/**
 * Frontend main class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Initialize shortcodes
        CMP_Shortcodes::get_instance();
    }
    
    public function enqueue_scripts() {
        // Only load on pages that need it
        if ($this->should_load_scripts()) {
            wp_enqueue_script(
                'cmp-frontend-js',
                CMP_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                CMP_VERSION,
                true
            );
            
            wp_enqueue_style(
                'cmp-frontend-css',
                CMP_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                CMP_VERSION
            );
            
            wp_localize_script('cmp-frontend-js', 'cmp_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cmp_frontend_nonce'),
                'user_logged_in' => is_user_logged_in(),
                'strings' => array(
                    'loading' => __('Loading...', 'case-manager-pro'),
                    'error' => __('An error occurred', 'case-manager-pro'),
                    'success' => __('Operation completed successfully', 'case-manager-pro'),
                    'confirm_delete' => __('Are you sure you want to delete this?', 'case-manager-pro'),
                    'file_too_large' => __('File is too large', 'case-manager-pro'),
                    'invalid_file_type' => __('Invalid file type', 'case-manager-pro')
                )
            ));
        }
    }
    
    private function should_load_scripts() {
        global $post;
        
        // Load on dashboard page
        $dashboard_page_id = get_option('cmp_dashboard_page_id');
        if ($dashboard_page_id && is_page($dashboard_page_id)) {
            return true;
        }
        
        // Load on pages with shortcodes
        if ($post && has_shortcode($post->post_content, 'cmp_dashboard')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'cmp_case_form')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'cmp_case_list')) {
            return true;
        }
        
        return false;
    }
} 