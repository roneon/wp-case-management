<?php
/**
 * Admin main class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        // Yetki kontrolü - administrator veya özel yetkiler
        $can_view_cases = current_user_can('manage_options') || current_user_can('cmp_view_all_cases');
        $can_manage_settings = current_user_can('manage_options') || current_user_can('cmp_manage_settings');
        $can_view_analytics = current_user_can('manage_options') || current_user_can('cmp_view_case_analytics');
        
        if (!$can_view_cases && !$can_manage_settings) {
            return; // Hiçbir yetkisi yoksa menü ekleme
        }
        
        // Ana menü
        add_menu_page(
            __('Case Manager Pro', 'case-manager-pro'),
            __('Cases', 'case-manager-pro'),
            'read', // Minimum yetki
            'cmp-cases',
            array($this, 'cases_page'),
            'dashicons-portfolio',
            30
        );
        
        // Alt menüler
        if ($can_view_cases) {
            add_submenu_page(
                'cmp-cases',
                __('All Cases', 'case-manager-pro'),
                __('All Cases', 'case-manager-pro'),
                'read',
                'cmp-cases',
                array($this, 'cases_page')
            );
        }
        
        if ($can_manage_settings) {
            add_submenu_page(
                'cmp-cases',
                __('Settings', 'case-manager-pro'),
                __('Settings', 'case-manager-pro'),
                'read',
                'cmp-settings',
                array($this, 'settings_page')
            );
        }
        
        // User Permissions menüsü (sadece administrator için)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'cmp-cases',
                __('User Permissions', 'case-manager-pro'),
                __('User Permissions', 'case-manager-pro'),
                'manage_options',
                'cmp-users',
                array($this, 'users_page')
            );
        }
        
        // Debug menüsü (sadece administrator için)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'cmp-cases',
                __('Debug Info', 'case-manager-pro'),
                __('Debug Info', 'case-manager-pro'),
                'manage_options',
                'cmp-debug',
                array($this, 'debug_page')
            );
        }
    }
    
    public function cases_page() {
        if (class_exists('CMP_Admin_Cases')) {
            $admin_cases = CMP_Admin_Cases::get_instance();
            $admin_cases->cases_page();
        } else {
            echo '<div class="wrap"><h1>Cases</h1><p>Cases management is loading...</p></div>';
        }
    }
    
    public function settings_page() {
        if (class_exists('CMP_Admin_Settings')) {
            $admin_settings = CMP_Admin_Settings::get_instance();
            $admin_settings->settings_page();
        } else {
            echo '<div class="wrap"><h1>Settings</h1><p>Settings page is loading...</p></div>';
        }
    }
    
    /*
    public function analytics_page() {
        if (class_exists('CMP_Admin_Analytics')) {
            $admin_analytics = CMP_Admin_Analytics::get_instance();
            $admin_analytics->analytics_page();
        } else {
            echo '<div class="wrap"><h1>Analytics</h1><p>Analytics page is loading...</p></div>';
        }
    }
    */
    
    public function users_page() {
        if (class_exists('CMP_Admin_Users')) {
            $admin_users = CMP_Admin_Users::get_instance();
            $admin_users->users_page();
        } else {
            echo '<div class="wrap"><h1>User Permissions</h1><p>User management is loading...</p></div>';
        }
    }
    
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Case Manager Pro - Debug Info', 'case-manager-pro'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Current User Capabilities', 'case-manager-pro'); ?></h2>
                <?php
                $current_user = wp_get_current_user();
                echo '<p><strong>User:</strong> ' . esc_html($current_user->user_login) . ' (ID: ' . $current_user->ID . ')</p>';
                echo '<p><strong>Roles:</strong> ' . implode(', ', $current_user->roles) . '</p>';
                
                $cmp_capabilities = array(
                    'cmp_submit_case',
                    'cmp_view_own_cases',
                    'cmp_view_all_cases',
                    'cmp_edit_all_cases',
                    'cmp_manage_settings',
                    'cmp_view_case_analytics',
                    'manage_options'
                );
                
                echo '<h3>CMP Capabilities:</h3><ul>';
                foreach ($cmp_capabilities as $cap) {
                    $has_cap = current_user_can($cap) ? 'YES' : 'NO';
                    $color = current_user_can($cap) ? 'green' : 'red';
                    echo '<li><strong>' . $cap . ':</strong> <span style="color: ' . $color . '">' . $has_cap . '</span></li>';
                }
                echo '</ul>';
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Plugin Status', 'case-manager-pro'); ?></h2>
                <?php
                echo '<p><strong>Plugin Version:</strong> ' . CMP_VERSION . '</p>';
                echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
                echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
                
                // Check database tables
                global $wpdb;
                $tables = array(
                    'cmp_cases',
                    'cmp_case_files',
                    'cmp_case_comments',
                    'cmp_notifications',
                    'cmp_activity_log'
                );
                
                echo '<h3>Database Tables:</h3><ul>';
                foreach ($tables as $table) {
                    $table_name = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
                    $status = $exists ? 'EXISTS' : 'MISSING';
                    $color = $exists ? 'green' : 'red';
                    echo '<li><strong>' . $table_name . ':</strong> <span style="color: ' . $color . '">' . $status . '</span></li>';
                }
                echo '</ul>';
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Loaded Classes', 'case-manager-pro'); ?></h2>
                <?php
                $classes = array(
                    'CMP_Database',
                    'CMP_Settings',
                    'CMP_User_Roles',
                    'CMP_Admin',
                    'CMP_Admin_Settings',
                    'CMP_Admin_Cases',
                    'CMP_File_Manager',
                    'CMP_Notifications',
                    'CMP_Dashboard',
                    'CMP_Analytics',
                    'CMP_Dashboard_Widget'
                );
                
                echo '<ul>';
                foreach ($classes as $class) {
                    $exists = class_exists($class);
                    $status = $exists ? 'LOADED' : 'NOT LOADED';
                    $color = $exists ? 'green' : 'red';
                    echo '<li><strong>' . $class . ':</strong> <span style="color: ' . $color . '">' . $status . '</span></li>';
                }
                echo '</ul>';
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Plugin Options', 'case-manager-pro'); ?></h2>
                <?php
                $options = array(
                    'cmp_activated',
                    'cmp_activation_time',
                    'cmp_cloud_provider',
                    'cmp_file_retention_days',
                    'cmp_max_file_size',
                    'cmp_allowed_file_types'
                );
                
                echo '<ul>';
                foreach ($options as $option) {
                    $value = get_option($option, 'NOT SET');
                    echo '<li><strong>' . $option . ':</strong> ' . esc_html($value) . '</li>';
                }
                echo '</ul>';
                ?>
            </div>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2, .card h3 {
            margin-top: 0;
        }
        .card ul {
            margin: 0;
            padding-left: 20px;
        }
        .card li {
            margin: 5px 0;
        }
        </style>
        <?php
    }
    
    public function init() {
        // Initialize admin settings
        if (class_exists('CMP_Admin_Settings')) {
            CMP_Admin_Settings::get_instance();
        }
        
        // Initialize admin cases if user has permission
        if (current_user_can('cmp_view_all_cases') || current_user_can('cmp_manage_settings')) {
            if (class_exists('CMP_Admin_Cases')) {
                CMP_Admin_Cases::get_instance();
            }
        }
    }
    
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'cmp') === false && strpos($hook, 'case-manager-pro') === false) {
            return;
        }
        
        // Admin JS dosyası yoksa frontend JS'i kullan
        $admin_js_file = CMP_PLUGIN_URL . 'assets/js/admin.js';
        $admin_js_path = CMP_PLUGIN_DIR . 'assets/js/admin.js';
        
        if (file_exists($admin_js_path)) {
            wp_enqueue_script(
                'cmp-admin-js',
                $admin_js_file,
                array('jquery'),
                CMP_VERSION,
                true
            );
        }
        
        wp_enqueue_style(
            'cmp-admin-css',
            CMP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CMP_VERSION
        );
        
        wp_localize_script('jquery', 'cmp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cmp_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this case?', 'case-manager-pro'),
                'loading' => __('Loading...', 'case-manager-pro'),
                'error' => __('An error occurred', 'case-manager-pro'),
                'success' => __('Operation completed successfully', 'case-manager-pro')
            )
        ));
    }
} 