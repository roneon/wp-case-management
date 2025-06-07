<?php
/**
 * Plugin Name: Case Manager Pro
 * Plugin URI: https://example.com/case-manager-pro
 * Description: Professional case management system with cloud storage integration (Amazon S3, Google Drive, Dropbox)
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: case-manager-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CMP_VERSION', '1.0.0');
define('CMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CMP_PLUGIN_FILE', __FILE__);
define('CMP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class CaseManagerPro {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        try {
            // Suppress PHP 8.1+ deprecated warnings for WordPress core functions
            if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
                add_action('init', function() {
                    // Only suppress specific deprecated warnings, not all errors
                    $error_level = error_reporting();
                    if ($error_level & E_DEPRECATED) {
                        error_reporting($error_level & ~E_DEPRECATED);
                    }
                }, 1);
            }
            
            // Check for activation errors
            $activation_error = get_option('cmp_activation_error');
            if ($activation_error && !get_option('cmp_activation_error_dismissed')) {
                add_action('admin_notices', function() use ($activation_error) {
                    echo '<div class="notice notice-error is-dismissible" data-notice="cmp-activation-error"><p><strong>Case Manager Pro Activation Error:</strong> ' . esc_html($activation_error) . '</p></div>';
                });
                return;
            }
            
            // Show success message if recently activated
            if (get_option('cmp_activated') && get_option('cmp_activation_time')) {
                $activation_time = get_option('cmp_activation_time');
                $time_diff = time() - strtotime($activation_time);
                
                // Show success message for 24 hours after activation, but allow dismissal
                if ($time_diff < 86400 && !get_option('cmp_activation_notice_dismissed')) {
                    add_action('admin_notices', function() use ($activation_time) {
                        echo '<div class="notice notice-success is-dismissible" data-notice="cmp-activation"><p><strong>Case Manager Pro</strong> successfully activated at: ' . esc_html($activation_time) . '</p></div>';
                    });
                }
            }
            
            // Load text domain for translations
            load_plugin_textdomain('case-manager-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
            
            // Include required files
            $this->includes();
            
            // Initialize components
            $this->init_hooks();
        } catch (Exception $e) {
            error_log('Case Manager Pro Init Error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>Case Manager Pro Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    private function includes() {
        // Core classes
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-database.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-settings.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-cloud-storage.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-user-roles.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-dashboard.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-notifications.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-analytics.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-file-manager.php';
        require_once CMP_PLUGIN_DIR . 'includes/class-cmp-dashboard-widget.php';
        
        // Admin classes
        if (is_admin()) {
            require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin.php';
            require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin-settings.php';
            require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin-cases.php';
            require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin-users.php';
            require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin-analytics.php';
            
            // Admin database fix tool
            if (file_exists(CMP_PLUGIN_DIR . 'admin-fix-database.php')) {
                require_once CMP_PLUGIN_DIR . 'admin-fix-database.php';
            }
        }
        
        // Frontend classes
        if (!is_admin()) {
            require_once CMP_PLUGIN_DIR . 'frontend/class-cmp-frontend.php';
        }
    }
    
    private function init_hooks() {
        try {
            // Initialize essential components only
            if (class_exists('CMP_Database')) {
                CMP_Database::get_instance();
                // Run migrations on every load to ensure database is up to date
                CMP_Database::run_migrations();
            }
            
            if (class_exists('CMP_Settings')) {
                CMP_Settings::get_instance();
            }
            
            if (class_exists('CMP_User_Roles')) {
                CMP_User_Roles::get_instance();
            }
            
            // Initialize other components after init
            add_action('init', array($this, 'init_additional_components'), 10);
            
            // Initialize file cleanup cron
            add_action('cmp_file_cleanup', array($this, 'cleanup_expired_files'));
            if (!wp_next_scheduled('cmp_file_cleanup')) {
                wp_schedule_event(time(), 'daily', 'cmp_file_cleanup');
            }
            
            // Admin notice dismiss functionality
            if (is_admin()) {
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
                add_action('wp_ajax_cmp_dismiss_notice', array($this, 'handle_dismiss_notice'));
                add_action('admin_notices', array($this, 'debug_admin_status'));
            }
        } catch (Exception $e) {
            error_log('Case Manager Pro Hook Init Error: ' . $e->getMessage());
        }
    }
    
    public function init_additional_components() {
        try {
            // Initialize cloud storage
            if (class_exists('CMP_Cloud_Storage')) {
                CMP_Cloud_Storage::get_instance();
            }
            
            // Initialize file manager
            if (class_exists('CMP_File_Manager')) {
                CMP_File_Manager::get_instance();
            }
            
            // Initialize notifications
            if (class_exists('CMP_Notifications')) {
                CMP_Notifications::get_instance();
            }
            
            // Initialize dashboard
            if (class_exists('CMP_Dashboard')) {
                $dashboard = CMP_Dashboard::get_instance();
                
                // Create dashboard page if it doesn't exist
                $dashboard_page_id = get_option('cmp_dashboard_page_id');
                if (!$dashboard_page_id || !get_post($dashboard_page_id)) {
                    // Dashboard page oluşturma işlemini burada yapalım
                    error_log('CMP: Creating dashboard page during init');
                }
            }
            
            // Initialize admin - MENÜLER İÇİN GEREKLİ
            if (is_admin()) {
                if (class_exists('CMP_Admin')) {
                    CMP_Admin::get_instance();
                }
                
                // Admin Settings sınıfını başlat
                if (class_exists('CMP_Admin_Settings')) {
                    CMP_Admin_Settings::get_instance();
                }
                
                // Admin Cases sınıfını başlat
                if (class_exists('CMP_Admin_Cases')) {
                    CMP_Admin_Cases::get_instance();
                }
                
                // Admin Users sınıfını başlat
                if (class_exists('CMP_Admin_Users')) {
                    CMP_Admin_Users::get_instance();
                }
                
                // Admin Analytics sınıfını başlat
                if (class_exists('CMP_Admin_Analytics')) {
                    CMP_Admin_Analytics::get_instance();
                }
                
                // Analytics sınıfını başlat (AJAX için gerekli)
                if (class_exists('CMP_Analytics')) {
                    CMP_Analytics::get_instance();
                }
                
                // Dashboard widget'ını başlat
                if (class_exists('CMP_Dashboard_Widget')) {
                    CMP_Dashboard_Widget::get_instance();
                }
            }
            
            // Force capabilities refresh for current user
            $current_user = wp_get_current_user();
            if ($current_user && in_array('administrator', $current_user->roles)) {
                // Refresh user capabilities
                $current_user->get_role_caps();
            }
            
            // Eğer kullanıcı administrator değilse ve hiç CMP rolü yoksa, case_submitter rolü ver
            if ($current_user && !in_array('administrator', $current_user->roles)) {
                $has_cmp_role = false;
                foreach ($current_user->roles as $role) {
                    if (in_array($role, array('case_submitter', 'case_reviewer', 'case_manager'))) {
                        $has_cmp_role = true;
                        break;
                    }
                }
                
                if (!$has_cmp_role) {
                    $current_user->add_role('case_submitter');
                }
            }
        } catch (Exception $e) {
            error_log('Case Manager Pro Additional Components Init Error: ' . $e->getMessage());
        }
    }
    
    public function activate() {
        try {
            // Create database tables
            CMP_Database::create_tables();
            
            // Upgrade existing tables if needed
            CMP_Database::upgrade_tables();
            
            // Create user roles
            CMP_User_Roles::create_roles();
            
            // Schedule cleanup cron job
            if (!wp_next_scheduled('cmp_cleanup_expired_files')) {
                wp_schedule_event(time(), 'daily', 'cmp_cleanup_expired_files');
            }
            
            // Create dashboard page if it doesn't exist
            $dashboard_page_id = get_option('cmp_dashboard_page_id');
            if (!$dashboard_page_id || !get_post($dashboard_page_id)) {
                error_log('CMP: Dashboard page will be created during init');
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Increase memory limit for activation
            if (function_exists('ini_set')) {
                ini_set('memory_limit', '256M');
            }
            
            // Load text domain for translations
            load_plugin_textdomain('case-manager-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
            
            // Include required files
            $this->includes();
            
            // Initialize components
            $this->init_hooks();
            
            // Set minimal default options
            $default_options = array(
                'cloud_provider' => 'none',
                'file_retention_days' => 30,
                'max_file_size' => 2048,
                'allowed_file_types' => 'pdf,doc,docx,jpg,jpeg,png,zip,rar',
                'enable_notifications' => true,
                'dashboard_page_id' => 0
            );
            
            foreach ($default_options as $key => $value) {
                if (get_option('cmp_' . $key) === false) {
                    add_option('cmp_' . $key, $value);
                }
            }
            
            // Set activation flag
            update_option('cmp_activated', true);
            update_option('cmp_activation_time', current_time('mysql'));
            
            // Force capabilities refresh for current user
            $current_user = wp_get_current_user();
            if ($current_user && in_array('administrator', $current_user->roles)) {
                // Refresh user capabilities
                $current_user->get_role_caps();
            }
            
            // Eğer kullanıcı administrator değilse ve hiç CMP rolü yoksa, case_submitter rolü ver
            if ($current_user && !in_array('administrator', $current_user->roles)) {
                $has_cmp_role = false;
                foreach ($current_user->roles as $role) {
                    if (in_array($role, array('case_submitter', 'case_reviewer', 'case_manager'))) {
                        $has_cmp_role = true;
                        break;
                    }
                }
                
                if (!$has_cmp_role) {
                    $current_user->add_role('case_submitter');
                }
            }
            
        } catch (Exception $e) {
            error_log('Case Manager Pro Activation Error: ' . $e->getMessage());
            // Don't use wp_die during activation as it can cause issues
            update_option('cmp_activation_error', $e->getMessage());
        }
    }
    
    public function deactivate() {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('cmp_file_cleanup');
            
            // Remove activation flags and errors
            delete_option('cmp_activated');
            delete_option('cmp_activation_time');
            delete_option('cmp_activation_error');
            
            // Don't flush rewrite rules during deactivation to avoid issues
            // flush_rewrite_rules();
            
        } catch (Exception $e) {
            error_log('Case Manager Pro Deactivation Error: ' . $e->getMessage());
        }
    }
    
    public function cleanup_expired_files() {
        try {
            $retention_days = get_option('cmp_file_retention_days', 30);
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'cmp_cases';
            
            $expired_cases = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE created_at < %s AND status != 'archived'",
                $cutoff_date
            ));
            
            foreach ($expired_cases as $case) {
                // Delete files from cloud storage
                if (class_exists('CMP_Cloud_Storage')) {
                    $cloud_storage = CMP_Cloud_Storage::get_instance();
                    $cloud_storage->delete_case_files($case->id);
                }
                
                // Update case status
                $wpdb->update(
                    $table_name,
                    array('status' => 'expired'),
                    array('id' => $case->id)
                );
            }
        } catch (Exception $e) {
            error_log('Case Manager Pro Cleanup Error: ' . $e->getMessage());
        }
    }
    
    public function debug_admin_status() {
        // Sadece administrator için debug mesajı göster
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Eğer debug bildirimi dismiss edilmişse gösterme
        if (get_option('cmp_debug_notice_dismissed')) {
            return;
        }
        
        // Sadece bir kez göster
        static $shown = false;
        if ($shown) {
            return;
        }
        $shown = true;
        
        $admin_class_loaded = class_exists('CMP_Admin') ? 'YES' : 'NO';
        $settings_class_loaded = class_exists('CMP_Admin_Settings') ? 'YES' : 'NO';
        $cases_class_loaded = class_exists('CMP_Admin_Cases') ? 'YES' : 'NO';
        $user_can_manage = current_user_can('manage_options') ? 'YES' : 'NO';
        
        echo '<div class="notice notice-info is-dismissible" data-notice="cmp-debug">';
        echo '<p><strong>Case Manager Pro Debug:</strong><br>';
        echo 'Admin Class: ' . $admin_class_loaded . ' | ';
        echo 'Settings Class: ' . $settings_class_loaded . ' | ';
        echo 'Cases Class: ' . $cases_class_loaded . ' | ';
        echo 'User Can Manage: ' . $user_can_manage . '</p>';
        echo '</div>';
    }
    
    /**
     * Enqueue admin scripts for notice dismissal
     */
    public function enqueue_admin_scripts() {
        // Inline script for notice dismissal
        $script = "
        jQuery(document).ready(function($) {
            $(document).on('click', '.notice[data-notice] .notice-dismiss', function() {
                var notice = $(this).closest('.notice');
                var noticeType = notice.data('notice');
                
                if (noticeType) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cmp_dismiss_notice',
                            notice_type: noticeType,
                            nonce: '" . wp_create_nonce('cmp_dismiss_notice') . "'
                        }
                    });
                }
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * Handle notice dismissal AJAX request
     */
    public function handle_dismiss_notice() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cmp_dismiss_notice')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $notice_type = sanitize_text_field($_POST['notice_type']);
        
        switch ($notice_type) {
            case 'cmp-activation':
                update_option('cmp_activation_notice_dismissed', true);
                break;
            case 'cmp-activation-error':
                update_option('cmp_activation_error_dismissed', true);
                // Also clear the error itself
                delete_option('cmp_activation_error');
                break;
            case 'cmp-debug':
                update_option('cmp_debug_notice_dismissed', true);
                break;
        }
        
        wp_die(); // This is required to terminate immediately and return a proper response
    }
    
    /**
     * Reset all dismissed notices (for development/testing)
     * Call this function to show notices again
     */
    public static function reset_dismissed_notices() {
        delete_option('cmp_activation_notice_dismissed');
        delete_option('cmp_activation_error_dismissed');
        delete_option('cmp_debug_notice_dismissed');
    }
}

// Initialize the plugin
function cmp_init() {
    return CaseManagerPro::get_instance();
}

/**
 * PHP 8.1+ compatibility helper functions
 */
if (!function_exists('cmp_safe_strpos')) {
    function cmp_safe_strpos($haystack, $needle, $offset = 0) {
        if ($haystack === null || $needle === null) {
            return false;
        }
        return strpos($haystack, $needle, $offset);
    }
}

if (!function_exists('cmp_safe_str_replace')) {
    function cmp_safe_str_replace($search, $replace, $subject, &$count = null) {
        if ($subject === null) {
            return '';
        }
        if ($search === null) {
            return $subject;
        }
        if ($replace === null) {
            $replace = '';
        }
        return str_replace($search, $replace, $subject, $count);
    }
}

// Start the plugin
cmp_init(); 