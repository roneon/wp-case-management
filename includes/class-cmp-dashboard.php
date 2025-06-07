<?php
/**
 * Frontend dashboard management
 *
 * @package CaseManagerPro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('cmp_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('cmp_case_form', array($this, 'case_form_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_cmp_submit_case', array($this, 'handle_case_submission'));
        add_action('wp_ajax_cmp_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_cmp_download_file', array($this, 'handle_file_download'));
        add_action('wp_ajax_cmp_upload_file_ajax', array($this, 'handle_ajax_file_upload'));
        add_action('wp_ajax_cmp_delete_temp_file', array($this, 'handle_delete_temp_file'));
    }
    
    public function init() {
        // Register dashboard page if not exists
        $dashboard_page_id = get_option('cmp_dashboard_page_id');
        if (!$dashboard_page_id || !get_post($dashboard_page_id)) {
            $this->create_dashboard_page();
        }
    }
    
    public function enqueue_scripts() {
        if (is_page(get_option('cmp_dashboard_page_id'))) {
            wp_enqueue_script('cmp-dashboard', plugin_dir_url(dirname(__FILE__)) . 'assets/js/dashboard.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('cmp-dashboard', plugin_dir_url(dirname(__FILE__)) . 'assets/css/dashboard.css', array(), '1.0.0');
            
            // Bootstrap CSS
            wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
            wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
            
            // Font Awesome
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            
            wp_localize_script('cmp-dashboard', 'cmp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cmp_dashboard_nonce'),
                'user_logged_in' => is_user_logged_in(),
                'strings' => array(
                    'uploading' => __('Uploading...', 'case-manager-pro'),
                    'upload_success' => __('File uploaded successfully!', 'case-manager-pro'),
                    'upload_error' => __('Upload failed. Please try again.', 'case-manager-pro'),
                    'confirm_delete' => __('Are you sure you want to delete this case?', 'case-manager-pro'),
                    'loading' => __('Loading...', 'case-manager-pro'),
                    'no_notifications' => __('No new notifications', 'case-manager-pro'),
                    'mark_read' => __('Mark as Read', 'case-manager-pro'),
                    'mark_unread' => __('Mark as Unread', 'case-manager-pro'),
                    'clear_all' => __('Clear All', 'case-manager-pro'),
                    'refresh' => __('Refresh', 'case-manager-pro')
                )
            ));
            
            // Add dynamic CSS variables
            $this->add_dynamic_styles();
        }
    }
    
    private function add_dynamic_styles() {
        $primary_color = get_option('cmp_dashboard_primary_color', '#0073aa');
        $secondary_color = get_option('cmp_dashboard_secondary_color', '#6c757d');
        $accent_color = get_option('cmp_dashboard_accent_color', '#28a745');
        $background_color = get_option('cmp_dashboard_background_color', '#f8f9fa');
        $text_color = get_option('cmp_dashboard_text_color', '#333333');
        $border_radius = get_option('cmp_dashboard_border_radius', '8');
        $font_family = get_option('cmp_dashboard_font_family', 'Inter');
        $button_style = get_option('cmp_dashboard_button_style', 'rounded');
        $animation_speed = get_option('cmp_dashboard_animation_speed', 'normal');
        
        $custom_css = "
        <style id='cmp-dynamic-styles'>
        :root {
            --cmp-primary-color: {$primary_color};
            --cmp-secondary-color: {$secondary_color};
            --cmp-accent-color: {$accent_color};
            --cmp-background-color: {$background_color};
            --cmp-text-color: {$text_color};
            --cmp-border-radius: {$border_radius}px;
            --cmp-font-family: '{$font_family}', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --cmp-transition: all " . ($animation_speed === 'fast' ? '0.15s' : ($animation_speed === 'slow' ? '0.5s' : '0.3s')) . " ease;
        }
        
        .cmp-dashboard {
            font-family: var(--cmp-font-family);
        }
        
        .cmp-btn-rounded {
            border-radius: 25px;
        }
        
        .cmp-btn-square {
            border-radius: 4px;
        }
        
        .cmp-btn-pill {
            border-radius: 50px;
        }
        </style>";
        
        echo $custom_css;
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access the dashboard.', 'case-manager-pro') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';
        
        ob_start();
        
        $this->render_dashboard();
        
        return ob_get_clean();
    }
    
    public function case_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to submit a case.', 'case-manager-pro') . '</p>';
        }
        
        ob_start();
        $this->render_new_case_form();
        return ob_get_clean();
    }
    
    public function render_dashboard() {
        $user_id = get_current_user_id();
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';
        
        ?>
        <div class="cmp-dashboard bg-light min-vh-100">
            <div class="container-fluid py-4">
                <!-- Dashboard Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h1 class="h3 mb-1 text-dark">
                                            <i class="fas fa-tachometer-alt text-primary me-2"></i>
                                            <?php _e('Case Management Dashboard', 'case-manager-pro'); ?>
                                        </h1>
                                        <p class="text-muted mb-0">
                                            <?php printf(__('Welcome back, %s', 'case-manager-pro'), wp_get_current_user()->display_name); ?>
                                        </p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                            <i class="fas fa-sync-alt me-2"></i><?php _e('Refresh', 'case-manager-pro'); ?>
                                        </button>
                                        <a href="<?php echo add_query_arg('view', 'submit'); ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-2"></i><?php _e('New Case', 'case-manager-pro'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <nav class="navbar navbar-expand-lg navbar-light bg-white">
                                    <div class="container-fluid">
                                        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardNav">
                                            <span class="navbar-toggler-icon"></span>
                                        </button>
                                        <div class="collapse navbar-collapse" id="dashboardNav">
                                            <ul class="navbar-nav me-auto">
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo $view === 'overview' ? 'active' : ''; ?>" 
                                                       href="<?php echo add_query_arg('view', 'overview'); ?>">
                                                        <i class="fas fa-tachometer-alt me-2"></i>
                                                        <?php _e('Overview', 'case-manager-pro'); ?>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo $view === 'submit' ? 'active' : ''; ?>" 
                                                       href="<?php echo add_query_arg('view', 'submit'); ?>">
                                                        <i class="fas fa-plus-circle me-2"></i>
                                                        <?php _e('Submit Case', 'case-manager-pro'); ?>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo $view === 'cases' ? 'active' : ''; ?>" 
                                                       href="<?php echo add_query_arg('view', 'cases'); ?>">
                                                        <i class="fas fa-folder-open me-2"></i>
                                                        <?php _e('My Cases', 'case-manager-pro'); ?>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo $view === 'notifications' ? 'active' : ''; ?>" 
                                                       href="<?php echo add_query_arg('view', 'notifications'); ?>">
                                                        <i class="fas fa-bell me-2"></i>
                                                        <?php _e('Notifications', 'case-manager-pro'); ?>
                                                        <?php 
                                                        $unread_count = $this->get_unread_notifications_count($user_id);
                                                        if ($unread_count > 0) {
                                                            echo '<span class="badge bg-danger rounded-pill ms-1">' . $unread_count . '</span>';
                                                        }
                                                        ?>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo $view === 'profile' ? 'active' : ''; ?>" 
                                                       href="<?php echo add_query_arg('view', 'profile'); ?>">
                                                        <i class="fas fa-user-circle me-2"></i>
                                                        <?php _e('Profile', 'case-manager-pro'); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="cmp-dashboard-content">
                    <?php
                    switch ($view) {
                        case 'submit':
                            $this->render_new_case_form();
                            break;
                        case 'cases':
                            $this->render_cases_view($user_id);
                            break;
                        case 'notifications':
                            $this->render_notifications_view($user_id);
                            break;
                        case 'profile':
                            $this->render_user_profile($user_id);
                            break;
                        case 'case':
                            if (isset($_GET['case_id'])) {
                                $this->render_case_details(intval($_GET['case_id']), $user_id);
                            } else {
                                $this->render_overview($user_id);
                            }
                            break;
                        default:
                            $this->render_overview($user_id);
                            break;
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Toast Container -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
            <!-- Toasts will be dynamically added here -->
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-refresh notifications every 30 seconds
            setInterval(function() {
                if (cmp_ajax.user_logged_in) {
                    $.ajax({
                        url: cmp_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'cmp_check_notifications',
                            nonce: cmp_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.count > 0) {
                                $('.badge.bg-danger').text(response.data.count).show();
                                
                                // Show toast notification for new notifications
                                if (response.data.new_notifications) {
                                    showToast('info', response.data.count + ' new notifications');
                                }
                            } else {
                                $('.badge.bg-danger').hide();
                            }
                        }
                    });
                }
            }, 30000);
            
            // Toast notification function
            function showToast(type, message) {
                var toastId = 'toast-' + Date.now();
                var iconClass = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
                var bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
                
                var toastHtml = `
                    <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-${iconClass} me-2"></i>${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                $('.toast-container').append(toastHtml);
                var toast = new bootstrap.Toast(document.getElementById(toastId));
                toast.show();
                
                // Remove toast element after it's hidden
                document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                    this.remove();
                });
            }
            
            // Make showToast globally available
            window.showToast = showToast;
        });
        </script>
        <?php
    }
    
    private function render_cases_view($user_id) {
        $db = CMP_Database::get_instance();
        $cases = $db->get_user_cases($user_id);
        
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-folder-open text-primary me-2"></i>
                                <?php _e('My Cases', 'case-manager-pro'); ?>
                            </h5>
                            <a href="?view=new_case" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>
                                <?php _e('New Case', 'case-manager-pro'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cases)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open text-muted mb-3" style="font-size: 3rem;"></i>
                                <h5 class="text-muted"><?php _e('No Cases Found', 'case-manager-pro'); ?></h5>
                                <p class="text-muted mb-4"><?php _e('You haven\'t submitted any cases yet. Click the button below to create your first case.', 'case-manager-pro'); ?></p>
                                <a href="?view=new_case" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    <?php _e('Submit Your First Case', 'case-manager-pro'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Cases List -->
                            <div class="cmp-cases-list">
                                <?php foreach ($cases as $case): ?>
                                    <div class="cmp-case-item">
                                        <div class="cmp-case-header">
                                            <div class="cmp-case-id">
                                                <span class="badge bg-light text-dark">#<?php echo $case->id; ?></span>
                                            </div>
                                            <div class="cmp-case-status">
                                                <span class="badge bg-<?php echo $this->get_status_bootstrap_color($case->status); ?>">
                                                    <i class="fas fa-<?php echo $this->get_status_icon($case->status); ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="cmp-case-content">
                                            <h5 class="cmp-case-title"><?php echo esc_html($case->title); ?></h5>
                                            <p class="cmp-case-description"><?php echo esc_html(wp_trim_words($case->description, 20)); ?></p>
                                            
                                            <div class="cmp-case-meta">
                                                <div class="cmp-meta-item">
                                                    <i class="fas fa-calendar text-muted me-1"></i>
                                                    <span><?php echo date_i18n('M j, Y', strtotime($case->created_at)); ?></span>
                                                </div>
                                                
                                                <?php if ($case->priority): ?>
                                                    <div class="cmp-meta-item">
                                                        <i class="fas fa-flag text-muted me-1"></i>
                                                        <span class="priority-<?php echo $case->priority; ?>">
                                                            <?php echo ucfirst($case->priority); ?> Priority
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($case->updated_at && $case->updated_at !== $case->created_at): ?>
                                                    <div class="cmp-meta-item">
                                                        <i class="fas fa-edit text-muted me-1"></i>
                                                        <span>Updated <?php echo human_time_diff(strtotime($case->updated_at)); ?> ago</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="cmp-case-actions">
                                            <a href="?view=case&id=<?php echo $case->id; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>
                                                <?php _e('View Details', 'case-manager-pro'); ?>
                                            </a>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="?view=case&id=<?php echo $case->id; ?>">
                                                            <i class="fas fa-eye me-2"></i><?php _e('View Details', 'case-manager-pro'); ?>
                                                        </a>
                                                    </li>
                                                    <?php if ($case->status !== 'closed'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="?view=case&id=<?php echo $case->id; ?>&action=edit">
                                                                <i class="fas fa-edit me-2"></i><?php _e('Edit Case', 'case-manager-pro'); ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-muted" href="#" onclick="copyToClipboard('<?php echo home_url('?case_id=' . $case->id); ?>')">
                                                            <i class="fas fa-link me-2"></i><?php _e('Copy Link', 'case-manager-pro'); ?>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show toast notification
                var toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
                toast.setAttribute('role', 'alert');
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check me-2"></i>Link copied to clipboard!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                var bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                // Remove toast after it's hidden
                toast.addEventListener('hidden.bs.toast', function() {
                    document.body.removeChild(toast);
                });
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        </script>
        <?php
    }
    
    private function render_new_case_form() {
        // Daha esnek yetki kontrolü
        $can_submit = current_user_can('cmp_submit_case') || 
                     current_user_can('manage_options') || 
                     in_array('case_submitter', wp_get_current_user()->roles) ||
                     in_array('administrator', wp_get_current_user()->roles);
        
        if (!$can_submit) {
            echo '<div class="alert alert-warning" role="alert">';
            echo '<h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>' . __('Permission Required', 'case-manager-pro') . '</h5>';
            echo '<p class="mb-3">' . __('You do not have permission to submit cases.', 'case-manager-pro') . '</p>';
            echo '<hr>';
            echo '<h6>Debug Info:</h6>';
            echo '<ul class="mb-0">';
            echo '<li>User ID: ' . get_current_user_id() . '</li>';
            echo '<li>User Roles: ' . implode(', ', wp_get_current_user()->roles) . '</li>';
            echo '<li>Has cmp_submit_case: ' . (current_user_can('cmp_submit_case') ? 'Yes' : 'No') . '</li>';
            echo '<li>Has manage_options: ' . (current_user_can('manage_options') ? 'Yes' : 'No') . '</li>';
            echo '</ul>';
            echo '</div>';
            return;
        }
        
        ?>
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus-circle text-primary me-2"></i>
                            <?php _e('Submit New Case', 'case-manager-pro'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Case Form -->
                            <div class="col-lg-7">
                                <form id="cmp-case-form" class="needs-validation" novalidate>
                                    <?php wp_nonce_field('cmp_submit_case', 'cmp_case_nonce'); ?>
                                    
                                    <div class="mb-3">
                                        <label for="case_title" class="form-label">
                                            <i class="fas fa-heading text-primary me-2"></i>
                                            <?php _e('Case Title', 'case-manager-pro'); ?> <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="case_title" name="case_title" required>
                                        <div class="invalid-feedback">
                                            <?php _e('Please provide a case title.', 'case-manager-pro'); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="case_description" class="form-label">
                                            <i class="fas fa-align-left text-primary me-2"></i>
                                            <?php _e('Case Description', 'case-manager-pro'); ?> <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" id="case_description" name="case_description" rows="6" required></textarea>
                                        <div class="invalid-feedback">
                                            <?php _e('Please provide a case description.', 'case-manager-pro'); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="case_priority" class="form-label">
                                            <i class="fas fa-exclamation-circle text-primary me-2"></i>
                                            <?php _e('Priority', 'case-manager-pro'); ?>
                                        </label>
                                        <select class="form-select" id="case_priority" name="case_priority">
                                            <option value="low"><?php _e('Low', 'case-manager-pro'); ?></option>
                                            <option value="medium" selected><?php _e('Medium', 'case-manager-pro'); ?></option>
                                            <option value="high"><?php _e('High', 'case-manager-pro'); ?></option>
                                            <option value="urgent"><?php _e('Urgent', 'case-manager-pro'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <!-- Terms and Conditions -->
                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                                            <label class="form-check-label" for="terms_accepted">
                                                <i class="fas fa-file-contract text-primary me-2"></i>
                                                <?php _e('I agree with the terms and conditions.', 'case-manager-pro'); ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="invalid-feedback">
                                                <?php _e('You must accept the terms and conditions to proceed.', 'case-manager-pro'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden field for uploaded file IDs -->
                                    <input type="hidden" id="uploaded_file_ids" name="uploaded_file_ids" value="">
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" id="submit-case-btn" class="btn btn-primary" disabled>
                                            <i class="fas fa-paper-plane me-2"></i>
                                            <?php _e('Submit Case', 'case-manager-pro'); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            <?php _e('Cancel', 'case-manager-pro'); ?>
                                        </button>
                                    </div>
                                    
                                    <div id="cmp-form-messages" class="mt-3"></div>
                                </form>
                            </div>
                            
                            <!-- File Upload Section -->
                            <div class="col-lg-5">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-header bg-transparent border-bottom">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-paperclip text-primary me-2"></i>
                                            <?php _e('File Attachments', 'case-manager-pro'); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- File Upload Area with Dashed Border -->
                                        <div class="cmp-file-upload-zone p-4 text-center mb-3 position-relative" 
                                             style="min-height: 120px; cursor: pointer; border: 2px dashed #007bff; border-radius: 8px; background: rgba(0,123,255,0.05);" 
                                             onclick="document.getElementById('file-input').click()">
                                            <i class="fas fa-cloud-upload-alt text-primary mb-2" style="font-size: 2rem;"></i>
                                            <p class="mb-2 text-muted">
                                                <strong><?php _e('Drag drop files here', 'case-manager-pro'); ?></strong><br>
                                                <?php _e('or', 'case-manager-pro'); ?>
                                            </p>
                                            <div class="d-flex gap-2 justify-content-center mb-3">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('file-input').click(); event.stopPropagation();">
                                                    <i class="fas fa-folder-open me-1"></i>
                                                    <?php _e('Browse Files', 'case-manager-pro'); ?>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="$('#file-url-input').toggle(); event.stopPropagation();">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?php _e('Get File From URL', 'case-manager-pro'); ?>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block">
                                                <?php 
                                                $max_size = get_option('cmp_max_file_size', 2048);
                                                $allowed_types = get_option('cmp_allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar');
                                                printf(__('Max Upload Files: %dMB', 'case-manager-pro'), $max_size);
                                                ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <?php printf(__('File Types: %s', 'case-manager-pro'), $allowed_types); ?>
                                            </small>
                                            <input type="file" id="file-input" multiple style="display: none;" 
                                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar">
                                        </div>
                                        
                                        <!-- URL Input Field -->
                                        <div id="file-url-input" class="mb-3" style="display: none;">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-link text-primary"></i>
                                                </span>
                                                <input type="url" class="form-control" id="file-url" placeholder="<?php _e('Enter file URL (e.g., https://example.com/file.pdf)', 'case-manager-pro'); ?>">
                                                <button class="btn btn-outline-primary" type="button" id="add-url-file">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Upload Progress -->
                                        <div id="upload-progress" class="mb-3" style="display: none;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">Uploading...</small>
                                                <small class="text-muted"><span id="progress-percent">0</span>%</small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Uploaded Files List -->
                                        <div id="uploaded-files-list">
                                            <h6 class="text-muted mb-3">
                                                <i class="fas fa-list me-2"></i>
                                                <?php _e('Uploaded Files', 'case-manager-pro'); ?>
                                                <span class="badge bg-secondary ms-2" id="file-count">0</span>
                                            </h6>
                                            <div id="files-container">
                                                <div class="text-center text-muted py-3">
                                                    <i class="fas fa-folder-open mb-2" style="font-size: 1.5rem;"></i>
                                                    <p class="mb-0 small"><?php _e('No files uploaded yet', 'case-manager-pro'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var uploadedFiles = [];
            var isUploading = false;
            
            // Terms checkbox kontrolü
            $('#terms_accepted').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('#submit-case-btn').prop('disabled', !isChecked);
                
                if (isChecked) {
                    $('#submit-case-btn').removeClass('btn-secondary').addClass('btn-primary');
                } else {
                    $('#submit-case-btn').removeClass('btn-primary').addClass('btn-secondary');
                }
            });
            
            // URL ile dosya ekleme
            $('#add-url-file').on('click', function() {
                var url = $('#file-url').val().trim();
                if (!url) {
                    showToast('Lütfen geçerli bir URL girin.', 'warning');
                    return;
                }
                
                // URL validasyonu
                try {
                    new URL(url);
                } catch(e) {
                    showToast('Geçersiz URL formatı!', 'error');
                    return;
                }
                
                // Dosya adını URL'den çıkar
                var fileName = url.split('/').pop().split('?')[0] || 'external-file';
                var fileExtension = fileName.split('.').pop().toLowerCase();
                
                // Desteklenen dosya tiplerini kontrol et
                var allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                if (!allowedTypes.includes(fileExtension)) {
                    showToast('Desteklenmeyen dosya tipi: ' + fileExtension, 'error');
                    return;
                }
                
                // Geçici dosya ID'si oluştur
                var tempFileId = 'url_' + Date.now();
                
                // Dosya listesine ekle
                var fileItem = {
                    id: tempFileId,
                    name: fileName,
                    size: 0,
                    type: 'url',
                    url: url,
                    storage_provider: 'external'
                };
                
                uploadedFiles.push(fileItem);
                addFileToList(fileItem);
                updateFileCount();
                
                // URL input'unu temizle ve gizle
                $('#file-url').val('');
                $('#file-url-input').hide();
                
                showToast('URL dosyası başarıyla eklendi!', 'success');
            });
            
            // Bootstrap form validation
            (function() {
                'use strict';
                window.addEventListener('load', function() {
                    var forms = document.getElementsByClassName('needs-validation');
                    var validation = Array.prototype.filter.call(forms, function(form) {
                        form.addEventListener('submit', function(event) {
                            if (form.checkValidity() === false) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                            form.classList.add('was-validated');
                        }, false);
                    });
                }, false);
            })();
            
            // Drag and drop functionality
            var $uploadZone = $('.cmp-file-upload-zone');
            
            $uploadZone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css({
                    'border-color': '#28a745',
                    'background': 'rgba(40, 167, 69, 0.1)'
                });
            });
            
            $uploadZone.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css({
                    'border-color': '#007bff',
                    'background': 'rgba(0,123,255,0.05)'
                });
            });
            
            $uploadZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css({
                    'border-color': '#007bff',
                    'background': 'rgba(0,123,255,0.05)'
                });
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    handleFileUpload(files);
                }
            });
            
            // File input change
            $('#file-input').on('change', function() {
                var files = this.files;
                if (files.length > 0) {
                    handleFileUpload(files);
                }
            });
            
            // File upload handler
            function handleFileUpload(files) {
                if (isUploading) {
                    showToast('Bir dosya yükleme işlemi devam ediyor. Lütfen bekleyin.', 'warning');
                    return;
                }
                
                Array.from(files).forEach(function(file) {
                    uploadSingleFile(file);
                });
            }
            
            function uploadSingleFile(file) {
                // Dosya boyutu kontrolü
                var maxSize = <?php echo get_option('cmp_max_file_size', 2048); ?> * 1024 * 1024; // MB to bytes
                if (file.size > maxSize) {
                    showToast('Dosya boyutu çok büyük: ' + file.name, 'error');
                    return;
                }
                
                // Dosya tipi kontrolü
                var allowedTypes = '<?php echo get_option('cmp_allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar'); ?>'.split(',');
                var fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    showToast('Desteklenmeyen dosya tipi: ' + file.name, 'error');
                    return;
                }
                
                isUploading = true;
                var formData = new FormData();
                formData.append('action', 'cmp_upload_file');
                formData.append('file', file);
                formData.append('nonce', '<?php echo wp_create_nonce('cmp_upload_file'); ?>');
                
                // Progress bar göster
                $('#upload-progress').show();
                $('.progress-bar').css('width', '0%');
                $('#progress-percent').text('0');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                $('.progress-bar').css('width', percentComplete + '%');
                                $('#progress-percent').text(percentComplete);
                                
                                // Storage provider progress messages
                                if (percentComplete < 50) {
                                    $('.progress-bar').removeClass().addClass('progress-bar progress-bar-striped progress-bar-animated bg-info');
                                } else if (percentComplete < 90) {
                                    $('.progress-bar').removeClass().addClass('progress-bar progress-bar-striped progress-bar-animated bg-warning');
                                } else {
                                    $('.progress-bar').removeClass().addClass('progress-bar progress-bar-striped progress-bar-animated bg-success');
                                }
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        isUploading = false;
                        $('#upload-progress').hide();
                        
                        if (response.success) {
                            uploadedFiles.push(response.data);
                            addFileToList(response.data);
                            updateFileCount();
                            updateUploadedFileIds();
                            showToast('Dosya başarıyla yüklendi: ' + file.name, 'success');
                        } else {
                            showToast('Dosya yükleme hatası: ' + (response.data || 'Bilinmeyen hata'), 'error');
                        }
                    },
                    error: function() {
                        isUploading = false;
                        $('#upload-progress').hide();
                        showToast('Dosya yükleme sırasında bağlantı hatası oluştu.', 'error');
                    }
                });
            }
            
            function addFileToList(fileData) {
                if ($('#files-container .text-center').length) {
                    $('#files-container').empty();
                }
                
                var storageIcon = getStorageIcon(fileData.storage_provider);
                var storageColor = getStorageColor(fileData.storage_provider);
                var fileSize = fileData.size ? formatFileSize(fileData.size) : 'External';
                
                var fileHtml = `
                    <div class="file-item mb-2 p-2 border rounded bg-white" data-file-id="${fileData.id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-alt text-primary me-2"></i>
                                <div>
                                    <div class="fw-bold small">${fileData.name}</div>
                                    <div class="text-muted small d-flex align-items-center">
                                        <i class="${storageIcon} me-1" style="color: ${storageColor}"></i>
                                        <span>${fileData.storage_provider}</span>
                                        <span class="ms-2">${fileSize}</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-file" data-file-id="${fileData.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                $('#files-container').append(fileHtml);
            }
            
            function getStorageIcon(provider) {
                switch(provider) {
                    case 'amazon_s3': return 'fab fa-aws';
                    case 'google_drive': return 'fab fa-google-drive';
                    case 'dropbox': return 'fab fa-dropbox';
                    case 'external': return 'fas fa-external-link-alt';
                    default: return 'fas fa-hdd';
                }
            }
            
            function getStorageColor(provider) {
                switch(provider) {
                    case 'amazon_s3': return '#FF9900';
                    case 'google_drive': return '#4285F4';
                    case 'dropbox': return '#0061FF';
                    case 'external': return '#6c757d';
                    default: return '#007bff';
                }
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function updateFileCount() {
                $('#file-count').text(uploadedFiles.length);
            }
            
            function updateUploadedFileIds() {
                var fileIds = uploadedFiles.map(function(file) {
                    return file.id;
                }).join(',');
                $('#uploaded_file_ids').val(fileIds);
            }
            
            // Delete file
            $(document).on('click', '.delete-file', function() {
                var fileId = $(this).data('file-id');
                var fileItem = $(this).closest('.file-item');
                
                if (fileId.toString().startsWith('url_')) {
                    // URL dosyası - sadece listeden kaldır
                    uploadedFiles = uploadedFiles.filter(function(file) {
                        return file.id !== fileId;
                    });
                    fileItem.remove();
                    updateFileCount();
                    updateUploadedFileIds();
                    
                    if (uploadedFiles.length === 0) {
                        $('#files-container').html(`
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-folder-open mb-2" style="font-size: 1.5rem;"></i>
                                <p class="mb-0 small"><?php _e('No files uploaded yet', 'case-manager-pro'); ?></p>
                            </div>
                        `);
                    }
                    
                    showToast('Dosya listeden kaldırıldı.', 'info');
                    return;
                }
                
                // Normal dosya - sunucudan sil
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'cmp_delete_temp_file',
                        file_id: fileId,
                        nonce: '<?php echo wp_create_nonce('cmp_delete_temp_file'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            uploadedFiles = uploadedFiles.filter(function(file) {
                                return file.id !== fileId;
                            });
                            fileItem.remove();
                            updateFileCount();
                            updateUploadedFileIds();
                            
                            if (uploadedFiles.length === 0) {
                                $('#files-container').html(`
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-folder-open mb-2" style="font-size: 1.5rem;"></i>
                                        <p class="mb-0 small"><?php _e('No files uploaded yet', 'case-manager-pro'); ?></p>
                                    </div>
                                `);
                            }
                            
                            showToast('Dosya başarıyla silindi.', 'success');
                        } else {
                            showToast('Dosya silme hatası: ' + (response.data || 'Bilinmeyen hata'), 'error');
                        }
                    },
                    error: function() {
                        showToast('Dosya silme sırasında bağlantı hatası oluştu.', 'error');
                    }
                });
            });
            
            // Toast notification function
            function showToast(message, type = 'info') {
                var toastClass = 'bg-info';
                var icon = 'fas fa-info-circle';
                
                switch(type) {
                    case 'success':
                        toastClass = 'bg-success';
                        icon = 'fas fa-check-circle';
                        break;
                    case 'error':
                        toastClass = 'bg-danger';
                        icon = 'fas fa-exclamation-circle';
                        break;
                    case 'warning':
                        toastClass = 'bg-warning text-dark';
                        icon = 'fas fa-exclamation-triangle';
                        break;
                }
                
                var toastId = 'toast-' + Date.now();
                var toastHtml = `
                    <div id="${toastId}" class="toast ${toastClass} text-white" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                        <div class="toast-body">
                            <i class="${icon} me-2"></i>
                            ${message}
                        </div>
                    </div>
                `;
                
                $('body').append(toastHtml);
                $('#' + toastId).toast({ delay: 3000 }).toast('show');
                
                $('#' + toastId).on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }
            
            // Form submission
            $('#cmp-case-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!$('#terms_accepted').is(':checked')) {
                    showToast('Lütfen şartlar ve koşulları kabul edin.', 'warning');
                    return;
                }
                
                var formData = new FormData(this);
                formData.append('action', 'cmp_submit_case');
                
                $('#submit-case-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Gönderiliyor...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showToast('Case başarıyla gönderildi!', 'success');
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showToast('Case gönderme hatası: ' + (response.data || 'Bilinmeyen hata'), 'error');
                            $('#submit-case-btn').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i><?php _e('Submit Case', 'case-manager-pro'); ?>');
                        }
                    },
                    error: function() {
                        showToast('Case gönderme sırasında bağlantı hatası oluştu.', 'error');
                        $('#submit-case-btn').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i><?php _e('Submit Case', 'case-manager-pro'); ?>');
                    }
                });
            });
        });
        </script>
        
        <style>
        .cmp-file-upload-zone:hover {
            border-color: #0056b3 !important;
            background: rgba(0,123,255,0.1) !important;
        }
        
        .file-item {
            transition: all 0.2s ease;
        }
        
        .file-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .toast {
            min-width: 300px;
        }
        
        #submit-case-btn:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        </style>
        <?php
    }
    
    public function handle_case_submission() {
        // Nonce kontrolü
        if (!check_ajax_referer('cmp_submit_case', 'cmp_case_nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'case-manager-pro'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to submit a case.', 'case-manager-pro'));
        }
        
        $user_id = get_current_user_id();
        
        // Yetki kontrolü
        $can_submit = current_user_can('cmp_submit_case') || 
                     current_user_can('manage_options') || 
                     in_array('case_submitter', wp_get_current_user()->roles) ||
                     in_array('administrator', wp_get_current_user()->roles);
        
        if (!$can_submit) {
            wp_send_json_error(__('You do not have permission to submit cases.', 'case-manager-pro'));
        }
        
        // Form verilerini al
        $title = sanitize_text_field($_POST['case_title'] ?? '');
        $description = sanitize_textarea_field($_POST['case_description'] ?? '');
        $priority = sanitize_text_field($_POST['case_priority'] ?? 'medium');
        
        if (empty($title) || empty($description)) {
            wp_send_json_error(__('Title and description are required.', 'case-manager-pro'));
        }
        
        // Güçlendirilmiş çift gönderim kontrolü
        $submission_key = 'cmp_submission_' . $user_id;
        $current_time = time();
        $current_hash = md5($title . $description . $priority . $user_id);
        
        // Son gönderim bilgisini al
        $last_submission = get_transient($submission_key);
        
        if ($last_submission) {
            $last_data = json_decode($last_submission, true);
            if ($last_data && isset($last_data['hash']) && isset($last_data['time'])) {
                // Aynı hash ve 30 saniye içinde gönderim varsa reddet
                if ($last_data['hash'] === $current_hash && ($current_time - $last_data['time']) < 30) {
                    error_log('CMP: Duplicate submission blocked for user ' . $user_id . ' - Hash: ' . $current_hash);
                    wp_send_json_error(__('This case has already been submitted. Please wait 30 seconds before submitting again.', 'case-manager-pro'));
                }
            }
        }
        
        // Yeni gönderim bilgisini kaydet (5 dakika boyunca)
        $submission_data = json_encode(array(
            'hash' => $current_hash,
            'time' => $current_time,
            'title' => $title
        ));
        set_transient($submission_key, $submission_data, 300); // 5 dakika
        
        error_log('CMP: Case submission started for user ' . $user_id . ' - Hash: ' . $current_hash);
        
        // Case'i oluştur
        $db = CMP_Database::get_instance();
        $case_id = $db->create_case(array(
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'user_id' => $user_id,
            'status' => 'pending'
        ));
        
        if (!$case_id) {
            error_log('CMP: Failed to create case for user ' . $user_id);
            wp_send_json_error(__('Failed to create case.', 'case-manager-pro'));
        }
        
        error_log('CMP: Case created successfully - ID: ' . $case_id . ' for user ' . $user_id);
        
        // Handle pre-uploaded files
        $uploaded_file_ids = sanitize_text_field($_POST['uploaded_file_ids'] ?? '');
        $uploaded_files = array();
        
        if (!empty($uploaded_file_ids)) {
            $file_ids = array_filter(array_map('intval', explode(',', $uploaded_file_ids)));
            
            if (!empty($file_ids)) {
                global $wpdb;
                $table_files = $wpdb->prefix . 'cmp_files';
                
                // Update temporary files to associate with the case
                foreach ($file_ids as $file_id) {
                    $updated = $wpdb->update(
                        $table_files,
                        array(
                            'case_id' => $case_id,
                            'is_temporary' => 0
                        ),
                        array(
                            'id' => $file_id,
                            'uploaded_by' => $user_id,
                            'is_temporary' => 1
                        ),
                        array('%d', '%d'),
                        array('%d', '%d', '%d')
                    );
                    
                    if ($updated) {
                        $file_info = $wpdb->get_row($wpdb->prepare(
                            "SELECT filename, file_size FROM $table_files WHERE id = %d",
                            $file_id
                        ));
                        
                        if ($file_info) {
                            $uploaded_files[] = array(
                                'name' => $file_info->filename,
                                'size' => size_format($file_info->file_size)
                            );
                        }
                        
                        error_log('CMP: File attached to case - File ID: ' . $file_id . ' for case ' . $case_id);
                    }
                }
            }
        }
        
        // Handle file uploads if any (legacy support)
        if (!empty($_FILES['case_files']['name'][0])) {
            error_log('CMP: Processing legacy file uploads for case ' . $case_id);
            
            $files = $_FILES['case_files'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_data = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );
                    
                    // WordPress media library'ye yükle
                    $upload_overrides = array('test_form' => false);
                    $movefile = wp_handle_upload($file_data, $upload_overrides);
                    
                    if ($movefile && !isset($movefile['error'])) {
                        // WordPress attachment olarak kaydet
                        $attachment = array(
                            'guid' => $movefile['url'],
                            'post_mime_type' => $movefile['type'],
                            'post_title' => sanitize_file_name($file_data['name']),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );
                        
                        $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
                        
                        if ($attachment_id) {
                            // Dosya bilgisini veritabanına kaydet
                            global $wpdb;
                            $table_files = $wpdb->prefix . 'cmp_files';
                            
                            $wpdb->insert($table_files, array(
                                'case_id' => $case_id,
                                'original_filename' => $file_data['name'],
                                'stored_filename' => basename($movefile['file']),
                                'filename' => $file_data['name'],
                                'file_path' => $movefile['file'],
                                'file_url' => $movefile['url'],
                                'file_size' => $file_data['size'],
                                'mime_type' => $movefile['type'],
                                'attachment_id' => $attachment_id,
                                'storage_provider' => 'local',
                                'uploaded_at' => current_time('mysql'),
                                'uploaded_by' => $user_id
                            ));
                            
                            $uploaded_files[] = array(
                                'name' => $file_data['name'],
                                'size' => size_format($file_data['size'])
                            );
                            error_log('CMP: Legacy file uploaded successfully - ' . $file_data['name'] . ' for case ' . $case_id);
                        }
                    } else {
                        error_log('CMP: Legacy file upload failed for case ' . $case_id . ': ' . ($movefile['error'] ?? 'Unknown error'));
                    }
                }
            }
        }
        
        // Trigger notification event - sadece bir kez
        do_action('cmp_case_submitted', $case_id, array(
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'user_id' => $user_id,
            'status' => 'pending'
        ));
        
        $message = __('Case submitted successfully.', 'case-manager-pro');
        if (!empty($uploaded_files)) {
            $message .= ' ' . sprintf(__('%d file(s) uploaded.', 'case-manager-pro'), count($uploaded_files));
        }
        
        error_log('CMP: Case submission completed successfully - Case ID: ' . $case_id);
        
        wp_send_json_success(array(
            'message' => $message,
            'case_id' => $case_id,
            'uploaded_files' => $uploaded_files,
            'reset_form' => true
        ));
    }
    
    public function handle_file_upload() {
        check_ajax_referer('cmp_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 401);
        }
        
        $case_id = intval($_POST['case_id']);
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded.', 'case-manager-pro'));
        }
        
        $file = $_FILES['file'];
        $cloud_storage = CMP_Cloud_Storage::get_instance();
        
        $result = $cloud_storage->upload_file($file['tmp_name'], $file['name'], $case_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function handle_file_download() {
        check_ajax_referer('cmp_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 401);
        }
        
        $file_id = intval($_POST['file_id']);
        $cloud_storage = CMP_Cloud_Storage::get_instance();
        
        $download_url = $cloud_storage->download_file($file_id);
        
        if (is_wp_error($download_url)) {
            wp_send_json_error($download_url->get_error_message());
        }
        
        wp_send_json_success(array('download_url' => $download_url));
    }
    
    /**
     * Handle AJAX file upload (individual files)
     */
    public function handle_ajax_file_upload() {
        // Nonce kontrolü
        if (!check_ajax_referer('cmp_upload_file', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'case-manager-pro'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to upload files.', 'case-manager-pro'));
        }
        
        $user_id = get_current_user_id();
        
        // Yetki kontrolü
        $can_upload = current_user_can('cmp_upload_files') || 
                     current_user_can('cmp_submit_case') || 
                     current_user_can('manage_options') || 
                     in_array('case_submitter', wp_get_current_user()->roles) ||
                     in_array('administrator', wp_get_current_user()->roles);
        
        if (!$can_upload) {
            wp_send_json_error(__('You do not have permission to upload files.', 'case-manager-pro'));
        }
        
        // Dosya kontrolü
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded.', 'case-manager-pro'));
        }
        
        $file = $_FILES['file'];
        
        // Dosya validasyonu
        $max_size = get_option('cmp_max_file_size', 2048) * 1024 * 1024; // MB to bytes
        $allowed_types = explode(',', get_option('cmp_allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar'));
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(sprintf(__('File is too large. Maximum size is %dMB.', 'case-manager-pro'), $max_size / 1024 / 1024));
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error(sprintf(__('Invalid file type. Allowed types: %s', 'case-manager-pro'), implode(', ', $allowed_types)));
        }
        
        // Cloud storage provider'ı kontrol et
        $cloud_storage = CMP_Cloud_Storage::get_instance();
        $storage_provider = get_option('cmp_cloud_provider', 'none');
        
        // WordPress dosya yükleme fonksiyonlarını yükle
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Dosyayı geçici olarak WordPress'e yükle
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $file_id = null;
            $attachment_id = null;
            $final_url = $movefile['url'];
            $final_path = $movefile['file'];
            
            // Eğer bulut depolama aktifse, dosyayı buluta yükle
            if ($storage_provider !== 'none') {
                try {
                    // Bulut depolamaya yükle (geçici case_id = 0 ile)
                    $cloud_result = $cloud_storage->upload_file($movefile['file'], $file['name'], 0);
                    
                    if (is_wp_error($cloud_result)) {
                        // Bulut yükleme başarısız, local'de kalsın
                        error_log('CMP: Cloud upload failed, falling back to local: ' . $cloud_result->get_error_message());
                        $storage_provider = 'local';
                    } else {
                        // Bulut yükleme başarılı, local dosyayı sil
                        if (file_exists($movefile['file'])) {
                            unlink($movefile['file']);
                        }
                        $final_url = $cloud_result['download_url'] ?? $movefile['url'];
                        $final_path = $cloud_result['path'] ?? $movefile['file'];
                    }
                } catch (Exception $e) {
                    error_log('CMP: Cloud upload exception: ' . $e->getMessage());
                    $storage_provider = 'local';
                }
            }
            
            // Local storage için WordPress attachment oluştur
            if ($storage_provider === 'local' || $storage_provider === 'none') {
                $attachment = array(
                    'guid' => $movefile['url'],
                    'post_mime_type' => $movefile['type'],
                    'post_title' => sanitize_file_name($file['name']),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                
                $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
                
                if (!$attachment_id) {
                    wp_send_json_error(__('Failed to create attachment.', 'case-manager-pro'));
                }
            }
            
            // Geçici dosya kaydı oluştur
            global $wpdb;
            $table_files = $wpdb->prefix . 'cmp_files';
            
            $file_data = array(
                'case_id' => 0, // Geçici olarak 0
                'original_filename' => $file['name'],
                'stored_filename' => basename($final_path),
                'filename' => $file['name'],
                'file_path' => $final_path,
                'file_url' => $final_url,
                'file_size' => $file['size'],
                'mime_type' => $movefile['type'],
                'attachment_id' => $attachment_id,
                'storage_provider' => $storage_provider === 'none' ? 'local' : $storage_provider,
                'uploaded_at' => current_time('mysql'),
                'uploaded_by' => $user_id,
                'is_temporary' => 1 // Geçici dosya işareti
            );
            
            $result = $wpdb->insert($table_files, $file_data);
            
            if ($result) {
                wp_send_json_success(array(
                    'file_id' => $wpdb->insert_id,
                    'filename' => $file['name'],
                    'file_size' => size_format($file['size']),
                    'file_url' => $final_url,
                    'attachment_id' => $attachment_id,
                    'storage_provider' => $storage_provider === 'none' ? 'local' : $storage_provider,
                    'message' => sprintf(__('File uploaded successfully to %s', 'case-manager-pro'), 
                                       $storage_provider === 'none' ? 'local storage' : $storage_provider)
                ));
            } else {
                // Cleanup on database error
                if ($attachment_id) {
                    wp_delete_attachment($attachment_id, true);
                }
                if ($storage_provider !== 'local' && isset($cloud_result['path'])) {
                    $cloud_storage->delete_file($cloud_result['path']);
                }
                wp_send_json_error(__('Failed to save file record.', 'case-manager-pro'));
            }
        } else {
            wp_send_json_error($movefile['error'] ?? __('File upload failed.', 'case-manager-pro'));
        }
    }
    
    /**
     * Handle temporary file deletion
     */
    public function handle_delete_temp_file() {
        // Nonce kontrolü
        if (!check_ajax_referer('cmp_delete_file', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'case-manager-pro'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'case-manager-pro'));
        }
        
        $file_id = intval($_POST['file_id']);
        $user_id = get_current_user_id();
        
        if (!$file_id) {
            wp_send_json_error(__('Invalid file ID.', 'case-manager-pro'));
        }
        
        global $wpdb;
        $table_files = $wpdb->prefix . 'cmp_files';
        
        // Dosya bilgisini al
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_files WHERE id = %d AND uploaded_by = %d AND is_temporary = 1",
            $file_id,
            $user_id
        ));
        
        if (!$file) {
            wp_send_json_error(__('File not found or access denied.', 'case-manager-pro'));
        }
        
        // WordPress attachment'ı sil
        if ($file->attachment_id) {
            wp_delete_attachment($file->attachment_id, true);
        }
        
        // Dosya kaydını sil
        $deleted = $wpdb->delete($table_files, array('id' => $file_id), array('%d'));
        
        if ($deleted) {
            wp_send_json_success(__('File deleted successfully.', 'case-manager-pro'));
        } else {
            wp_send_json_error(__('Failed to delete file.', 'case-manager-pro'));
        }
    }
    
    private function create_dashboard_page() {
        $page_data = array(
            'post_title' => __('Case Management Dashboard', 'case-manager-pro'),
            'post_content' => '[cmp_dashboard]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            update_option('cmp_dashboard_page_id', $page_id);
        }
        
        return $page_id;
    }
    
    private function render_case_details($case_id, $user_id) {
        $db = CMP_Database::get_instance();
        $case = $db->get_case($case_id);
        
        if (!$case) {
            echo '<div class="cmp-error">Case not found.</div>';
            return;
        }
        
        // Check permissions
        $user_roles = CMP_User_Roles::get_instance();
        if (!$user_roles->user_can_view_case($user_id, $case)) {
            echo '<div class="cmp-error">You do not have permission to view this case.</div>';
            return;
        }
        
        $submitter = get_userdata($case->user_id);
        $files = $db->get_case_files($case_id);
        $comments = $db->get_case_comments($case_id);
        
        ?>
        <div class="cmp-dashboard">
            <div class="cmp-dashboard-header">
                <h2><?php printf(__('Case #%d: %s', 'case-manager-pro'), $case->id, esc_html($case->title)); ?></h2>
                <div class="cmp-dashboard-nav">
                    <a href="?view=dashboard" class="cmp-nav-link"><?php _e('Overview', 'case-manager-pro'); ?></a>
                    <a href="?view=cases" class="cmp-nav-link"><?php _e('My Cases', 'case-manager-pro'); ?></a>
                    <a href="?view=new_case" class="cmp-nav-link"><?php _e('New Case', 'case-manager-pro'); ?></a>
                    <a href="?view=notifications" class="cmp-nav-link"><?php _e('Notifications', 'case-manager-pro'); ?></a>
                </div>
            </div>
            
            <div class="cmp-case-details">
                <div class="cmp-case-info">
                    <div class="cmp-case-header">
                        <div class="cmp-case-status">
                            <span class="cmp-status cmp-status-<?php echo $case->status; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                            </span>
                        </div>
                        <div class="cmp-case-meta">
                            <p><strong><?php _e('Submitted by:', 'case-manager-pro'); ?></strong> <?php echo $submitter ? $submitter->display_name : 'Unknown'; ?></p>
                            <p><strong><?php _e('Priority:', 'case-manager-pro'); ?></strong> <?php echo ucfirst($case->priority); ?></p>
                            <p><strong><?php _e('Created:', 'case-manager-pro'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->created_at)); ?></p>
                            <?php if ($case->updated_at): ?>
                                <p><strong><?php _e('Last updated:', 'case-manager-pro'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->updated_at)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="cmp-case-description">
                        <h3><?php _e('Description', 'case-manager-pro'); ?></h3>
                        <div class="cmp-description-content">
                            <?php echo nl2br(esc_html($case->description)); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($files)): ?>
                        <div class="cmp-case-files">
                            <h3><?php _e('Attached Files', 'case-manager-pro'); ?></h3>
                            <div class="cmp-files-list">
                                <?php foreach ($files as $file): ?>
                                    <div class="cmp-file-item">
                                        <div class="cmp-file-icon">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div class="cmp-file-info">
                                            <div class="cmp-file-name"><?php echo esc_html($file->original_filename); ?></div>
                                            <div class="cmp-file-meta">
                                                <span class="cmp-file-size"><?php echo size_format($file->file_size); ?></span>
                                                <span class="cmp-file-separator">•</span>
                                                <span class="cmp-file-date"><?php echo date_i18n(get_option('date_format'), strtotime($file->uploaded_at)); ?></span>
                                                <?php if (isset($file->storage_provider)): ?>
                                                    <span class="cmp-file-separator">•</span>
                                                    <span class="cmp-file-provider"><?php echo ucfirst($file->storage_provider); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="cmp-file-actions">
                                            <?php if (isset($file->file_url) && !empty($file->file_url)): ?>
                                                <a href="<?php echo esc_url($file->file_url); ?>" target="_blank" class="cmp-download-file btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download me-1"></i><?php _e('Download', 'case-manager-pro'); ?>
                                                </a>
                                            <?php elseif (isset($file->attachment_id)): ?>
                                                <a href="<?php echo esc_url(wp_get_attachment_url($file->attachment_id)); ?>" target="_blank" class="cmp-download-file btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download me-1"></i><?php _e('Download', 'case-manager-pro'); ?>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fas fa-exclamation-triangle me-1"></i><?php _e('File not available', 'case-manager-pro'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($comments)): ?>
                        <div class="cmp-case-comments">
                            <h3><?php _e('Comments', 'case-manager-pro'); ?></h3>
                            <div class="cmp-comments-list">
                                <?php foreach ($comments as $comment): ?>
                                    <?php $comment_user = get_userdata($comment->user_id); ?>
                                    <div class="cmp-comment-item">
                                        <div class="cmp-comment-header">
                                            <strong><?php echo $comment_user ? $comment_user->display_name : 'Unknown'; ?></strong>
                                            <span class="cmp-comment-date"><?php echo human_time_diff(strtotime($comment->created_at)); ?> <?php _e('ago', 'case-manager-pro'); ?></span>
                                        </div>
                                        <div class="cmp-comment-content">
                                            <?php echo nl2br(esc_html($comment->comment)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .cmp-case-details {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .cmp-case-info {
            padding: 30px;
        }
        
        .cmp-case-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .cmp-case-meta p {
            margin: 5px 0;
            color: #666;
        }
        
        .cmp-case-description {
            margin-bottom: 30px;
        }
        
        .cmp-case-description h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }
        
        .cmp-description-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            line-height: 1.6;
            color: #555;
        }
        
        .cmp-case-files, .cmp-case-comments {
            margin-bottom: 30px;
        }
        
        .cmp-case-files h3, .cmp-case-comments h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }
        
        .cmp-files-list {
            display: grid;
            gap: 15px;
        }
        
        .cmp-file-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            transition: all 0.2s ease;
        }
        
        .cmp-file-item:hover {
            background: #e9ecef;
            border-color: #0073aa;
        }
        
        .cmp-file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #0073aa;
            color: white;
            border-radius: 6px;
            font-size: 18px;
        }
        
        .cmp-file-info {
            flex: 1;
        }
        
        .cmp-file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .cmp-file-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .cmp-file-separator {
            color: #ccc;
        }
        
        .cmp-file-provider {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .cmp-file-actions {
            display: flex;
            gap: 10px;
        }
        
        .cmp-download-file {
            background: #0073aa;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #0073aa;
            transition: all 0.2s ease;
        }
        
        .cmp-download-file:hover {
            background: #005a87;
            color: white;
            text-decoration: none;
            border-color: #005a87;
        }
        
        .cmp-comments-list {
            display: grid;
            gap: 15px;
        }
        
        .cmp-comment-item {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            padding: 15px;
        }
        
        .cmp-comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .cmp-comment-header strong {
            color: #333;
        }
        
        .cmp-comment-date {
            font-size: 13px;
            color: #666;
        }
        
        .cmp-comment-content {
            line-height: 1.6;
            color: #555;
        }
        
        @media (max-width: 768px) {
            .cmp-case-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .cmp-file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .cmp-comment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
        
        /* My Cases List Styles */
        .cmp-cases-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .cmp-case-item {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .cmp-case-item:hover {
            border-color: #0073aa;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .cmp-case-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cmp-case-id .badge {
            font-size: 14px;
            padding: 6px 12px;
        }
        
        .cmp-case-content {
            margin-bottom: 20px;
        }
        
        .cmp-case-title {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .cmp-case-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .cmp-case-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }
        
        .cmp-meta-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #666;
        }
        
        .priority-urgent {
            color: #dc3545;
            font-weight: 600;
        }
        
        .priority-high {
            color: #fd7e14;
            font-weight: 600;
        }
        
        .priority-medium {
            color: #0dcaf0;
            font-weight: 600;
        }
        
        .priority-low {
            color: #6c757d;
            font-weight: 600;
        }
        
        .cmp-case-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .cmp-case-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .cmp-case-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .cmp-case-actions {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }
        
        /* Case Details Styles */
        </style>
        <?php
    }
    
    private function render_notifications_view($user_id) {
        $notifications = CMP_Notifications::get_instance();
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 20;
        
        $user_notifications = $notifications->get_user_notifications($user_id, $page, $per_page);
        $unread_count = $notifications->get_unread_count($user_id);
        
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bell text-warning me-2"></i>
                                <?php _e('Notifications', 'case-manager-pro'); ?>
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2">
                                <?php if ($unread_count > 0): ?>
                                    <button id="cmp-mark-all-read" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-check-double me-2"></i>
                                        <?php printf(__('Mark all %d as read', 'case-manager-pro'), $unread_count); ?>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    <?php _e('Refresh', 'case-manager-pro'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($user_notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash text-muted mb-3" style="font-size: 4rem;"></i>
                                <h4 class="text-muted"><?php _e('No Notifications', 'case-manager-pro'); ?></h4>
                                <p class="text-muted mb-4"><?php _e('You\'ll see notifications here when there are updates to your cases.', 'case-manager-pro'); ?></p>
                                <a href="<?php echo add_query_arg('view', 'submit'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i><?php _e('Submit Your First Case', 'case-manager-pro'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($user_notifications as $notification): ?>
                                    <div class="list-group-item border-0 cmp-notification-item <?php echo $notification->is_read ? '' : 'bg-light'; ?>" 
                                         data-notification-id="<?php echo $notification->id; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="me-3">
                                                        <div class="rounded-circle bg-<?php echo $notification->is_read ? 'secondary' : 'primary'; ?> d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-<?php echo $this->get_notification_icon($notification->type); ?> text-white"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-bold">
                                                            <?php echo esc_html($notification->title); ?>
                                                            <?php if (!$notification->is_read): ?>
                                                                <span class="badge bg-primary rounded-pill ms-2">New</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo human_time_diff(strtotime($notification->created_at)); ?> <?php _e('ago', 'case-manager-pro'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <p class="mb-2 text-muted"><?php echo esc_html($notification->message); ?></p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex gap-2">
                                                        <?php if ($notification->case_id): ?>
                                                            <a href="<?php echo add_query_arg(array('view' => 'case', 'case_id' => $notification->case_id)); ?>" 
                                                               class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye me-1"></i><?php _e('View Case', 'case-manager-pro'); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (!$notification->is_read): ?>
                                                            <button class="btn btn-outline-success btn-sm cmp-mark-read" 
                                                                    data-id="<?php echo $notification->id; ?>">
                                                                <i class="fas fa-check me-1"></i><?php _e('Mark as Read', 'case-manager-pro'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                                                type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <?php if (!$notification->is_read): ?>
                                                                <li>
                                                                    <a class="dropdown-item cmp-mark-read" href="#" data-id="<?php echo $notification->id; ?>">
                                                                        <i class="fas fa-check me-2"></i><?php _e('Mark as Read', 'case-manager-pro'); ?>
                                                                    </a>
                                                                </li>
                                                            <?php else: ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="markAsUnread(<?php echo $notification->id; ?>)">
                                                                        <i class="fas fa-undo me-2"></i><?php _e('Mark as Unread', 'case-manager-pro'); ?>
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteNotification(<?php echo $notification->id; ?>)">
                                                                    <i class="fas fa-trash me-2"></i><?php _e('Delete', 'case-manager-pro'); ?>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if (count($user_notifications) == $per_page): ?>
                                <div class="card-footer bg-white border-top text-center">
                                    <a href="<?php echo add_query_arg(array('view' => 'notifications', 'page' => $page + 1)); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-chevron-down me-2"></i><?php _e('Load More Notifications', 'case-manager-pro'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mark single notification as read
            $('.cmp-mark-read').on('click', function(e) {
                e.preventDefault();
                var notificationId = $(this).data('id');
                var $notification = $(this).closest('.cmp-notification-item');
                var $button = $(this);
                
                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Marking...');
                
                $.ajax({
                    url: cmp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cmp_mark_notification_read',
                        notification_id: notificationId,
                        nonce: cmp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $notification.removeClass('bg-light');
                            $notification.find('.bg-primary').removeClass('bg-primary').addClass('bg-secondary');
                            $notification.find('.badge.bg-primary').remove();
                            $button.remove();
                            
                            // Update badge count
                            var $badge = $('.badge.bg-danger');
                            var count = parseInt($badge.text()) - 1;
                            if (count > 0) {
                                $badge.text(count);
                                $('#cmp-mark-all-read').html('<i class="fas fa-check-double me-2"></i>Mark all ' + count + ' as read');
                            } else {
                                $badge.remove();
                                $('#cmp-mark-all-read').remove();
                            }
                            
                            showToast('success', 'Notification marked as read');
                        } else {
                            showToast('error', 'Failed to mark notification as read');
                        }
                    },
                    error: function() {
                        showToast('error', 'An error occurred');
                    },
                    complete: function() {
                        $button.prop('disabled', false).html('<i class="fas fa-check me-1"></i><?php _e('Mark as Read', 'case-manager-pro'); ?>');
                    }
                });
            });
            
            // Mark all notifications as read
            $('#cmp-mark-all-read').on('click', function() {
                var $button = $(this);
                var originalText = $button.html();
                
                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Marking all...');
                
                $.ajax({
                    url: cmp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cmp_clear_all_notifications',
                        nonce: cmp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.cmp-notification-item').removeClass('bg-light');
                            $('.bg-primary').removeClass('bg-primary').addClass('bg-secondary');
                            $('.badge.bg-primary, .badge.bg-danger').remove();
                            $button.remove();
                            
                            showToast('success', 'All notifications marked as read');
                        } else {
                            showToast('error', 'Failed to mark all notifications as read');
                        }
                    },
                    error: function() {
                        showToast('error', 'An error occurred');
                    },
                    complete: function() {
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
        
        function markAsUnread(notificationId) {
            // Implementation for marking as unread
            showToast('info', 'Mark as unread feature coming soon');
        }
        
        function deleteNotification(notificationId) {
            if (confirm('Are you sure you want to delete this notification?')) {
                // Implementation for deleting notification
                showToast('info', 'Delete notification feature coming soon');
            }
        }
        </script>
        <?php
    }
    
    private function render_overview($user_id) {
        $db = CMP_Database::get_instance();
        $notifications = CMP_Notifications::get_instance();
        
        // Get user statistics
        $total_cases = $db->get_user_case_count($user_id);
        $pending_cases = $db->get_user_case_count($user_id, 'pending');
        $in_progress_cases = $db->get_user_case_count($user_id, 'in_progress');
        $resolved_cases = $db->get_user_case_count($user_id, 'resolved');
        
        // Get recent cases
        $recent_cases = $db->get_user_cases($user_id, 1, 5);
        
        // Get notifications
        $user_notifications = $notifications->get_user_notifications($user_id, 1, 5);
        $unread_count = $notifications->get_unread_count($user_id);
        
        ?>
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100 cmp-stat-card cmp-stat-total">
                    <div class="card-body d-flex align-items-center">
                        <div class="cmp-stat-icon me-3">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="cmp-stat-content">
                            <h3 class="mb-1"><?php echo $total_cases; ?></h3>
                            <p class="mb-0 text-muted small"><?php _e('Total Cases', 'case-manager-pro'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100 cmp-stat-card cmp-stat-pending">
                    <div class="card-body d-flex align-items-center">
                        <div class="cmp-stat-icon me-3">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="cmp-stat-content">
                            <h3 class="mb-1"><?php echo $pending_cases; ?></h3>
                            <p class="mb-0 text-muted small"><?php _e('Pending', 'case-manager-pro'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100 cmp-stat-card cmp-stat-progress">
                    <div class="card-body d-flex align-items-center">
                        <div class="cmp-stat-icon me-3">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="cmp-stat-content">
                            <h3 class="mb-1"><?php echo $in_progress_cases; ?></h3>
                            <p class="mb-0 text-muted small"><?php _e('In Progress', 'case-manager-pro'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100 cmp-stat-card cmp-stat-resolved">
                    <div class="card-body d-flex align-items-center">
                        <div class="cmp-stat-icon me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="cmp-stat-content">
                            <h3 class="mb-1"><?php echo $resolved_cases; ?></h3>
                            <p class="mb-0 text-muted small"><?php _e('Resolved', 'case-manager-pro'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="row g-4">
            <!-- Recent Cases -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            <?php _e('Recent Cases', 'case-manager-pro'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_cases)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open text-muted mb-3" style="font-size: 3rem;"></i>
                                <h5 class="text-muted"><?php _e('No Cases Yet', 'case-manager-pro'); ?></h5>
                                <p class="text-muted mb-3"><?php _e('You haven\'t submitted any cases yet. Click the button below to get started.', 'case-manager-pro'); ?></p>
                                <a href="<?php echo add_query_arg('view', 'submit'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i><?php _e('Submit Your First Case', 'case-manager-pro'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_cases as $case): ?>
                                    <div class="list-group-item border-0 px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                                    Case #<?php echo $case->id; ?>: <?php echo esc_html($case->title); ?>
                                                </h6>
                                                <div class="d-flex align-items-center gap-3 mt-2">
                                                    <span class="badge bg-<?php echo $this->get_status_bootstrap_color($case->status); ?> rounded-pill">
                                                        <i class="fas fa-<?php echo $this->get_status_icon($case->status); ?> me-1"></i>
                                                        <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date_i18n('M j', strtotime($case->created_at)); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <a href="<?php echo add_query_arg(array('view' => 'case', 'case_id' => $case->id)); ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i><?php _e('View', 'case-manager-pro'); ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-footer bg-white border-top text-center">
                                <a href="<?php echo add_query_arg('view', 'cases'); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-list me-2"></i><?php _e('View All Cases', 'case-manager-pro'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bell text-warning me-2"></i>
                            <?php _e('Recent Notifications', 'case-manager-pro'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($user_notifications)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bell-slash text-muted mb-3" style="font-size: 2rem;"></i>
                                <h6 class="text-muted"><?php _e('No Notifications', 'case-manager-pro'); ?></h6>
                                <p class="text-muted small mb-0"><?php _e('You\'ll see notifications here when there are updates on your cases.', 'case-manager-pro'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($user_notifications as $notification): ?>
                                    <div class="list-group-item border-0 px-0 py-2 cmp-notification-item <?php echo $notification->is_read ? '' : 'bg-light'; ?>" 
                                         data-notification-id="<?php echo $notification->id; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 cmp-notification-title" 
                                                    style="cursor: pointer;" 
                                                    onclick="toggleNotificationDetails(<?php echo $notification->id; ?>)">
                                                    <i class="fas fa-<?php echo $this->get_notification_icon($notification->type); ?> text-info me-2"></i>
                                                    <?php echo esc_html($notification->title); ?>
                                                    <i class="fas fa-chevron-down cmp-toggle-icon ms-2"></i>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo human_time_diff(strtotime($notification->created_at)); ?> <?php _e('ago', 'case-manager-pro'); ?>
                                                </small>
                                            </div>
                                            <?php if (!$notification->is_read): ?>
                                                <span class="badge bg-primary rounded-circle" style="width: 8px; height: 8px;"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="collapse mt-2" id="notification-details-<?php echo $notification->id; ?>">
                                            <div class="border-top pt-2">
                                                <p class="small text-muted mb-2"><?php echo esc_html($notification->message); ?></p>
                                                <?php if (!$notification->is_read): ?>
                                                    <button class="btn btn-success btn-sm cmp-mark-read" data-id="<?php echo $notification->id; ?>">
                                                        <i class="fas fa-check me-1"></i><?php _e('Mark as Read', 'case-manager-pro'); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-footer bg-white border-top text-center">
                                <a href="<?php echo add_query_arg('view', 'notifications'); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-bell me-2"></i><?php _e('View All', 'case-manager-pro'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function toggleNotificationDetails(notificationId) {
            var details = document.getElementById('notification-details-' + notificationId);
            var icon = document.querySelector('[data-notification-id="' + notificationId + '"] .cmp-toggle-icon');
            var collapse = new bootstrap.Collapse(details);
            
            if (details.classList.contains('show')) {
                collapse.hide();
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                collapse.show();
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
        </script>
        <?php
    }
    
    private function get_status_icon($status) {
        $icons = array(
            'pending' => 'clock',
            'in_progress' => 'spinner',
            'resolved' => 'check-circle',
            'closed' => 'times-circle'
        );
        return isset($icons[$status]) ? $icons[$status] : 'question-circle';
    }
    
    private function get_status_bootstrap_color($status) {
        $colors = array(
            'pending' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            'closed' => 'secondary'
        );
        return isset($colors[$status]) ? $colors[$status] : 'secondary';
    }
    
    private function get_notification_icon($type) {
        switch ($type) {
            case 'case_created':
                return 'plus-circle';
            case 'case_updated':
                return 'edit';
            case 'case_status_changed':
                return 'exchange-alt';
            case 'case_assigned':
                return 'user-plus';
            case 'case_comment':
                return 'comment';
            case 'case_file_uploaded':
                return 'file-upload';
            case 'case_deadline':
                return 'clock';
            case 'system':
                return 'cog';
            default:
                return 'bell';
        }
    }
    
    private function get_unread_notifications_count($user_id) {
        $notifications = CMP_Notifications::get_instance();
        return $notifications->get_unread_count($user_id);
    }
    
    private function render_user_profile($user_id) {
        $user = get_userdata($user_id);
        $user_meta = get_user_meta($user_id);
        
        if (isset($_POST['update_profile'])) {
            // Handle profile update
            $display_name = sanitize_text_field($_POST['display_name']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $bio = sanitize_textarea_field($_POST['bio']);
            
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
                'user_email' => $email
            ));
            
            update_user_meta($user_id, 'phone', $phone);
            update_user_meta($user_id, 'description', $bio);
            
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . __('Profile updated successfully!', 'case-manager-pro') . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
        
        ?>
        <div class="row">
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php echo get_avatar($user_id, 120, '', '', array('class' => 'rounded-circle border border-3 border-primary')); ?>
                        </div>
                        <h4 class="card-title mb-1"><?php echo esc_html($user->display_name); ?></h4>
                        <p class="text-muted mb-3">
                            <i class="fas fa-envelope me-2"></i><?php echo esc_html($user->user_email); ?>
                        </p>
                        
                        <!-- User Stats -->
                        <div class="row g-3 mb-3">
                            <?php
                            global $wpdb;
                            $cases_table = $wpdb->prefix . 'cmp_cases';
                            
                            $total_cases = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $cases_table WHERE user_id = %d",
                                $user_id
                            ));
                            
                            $pending_cases = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $cases_table WHERE user_id = %d AND status = 'pending'",
                                $user_id
                            ));
                            
                            $resolved_cases = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $cases_table WHERE user_id = %d AND status = 'resolved'",
                                $user_id
                            ));
                            ?>
                            <div class="col-4">
                                <div class="text-center">
                                    <h5 class="text-primary mb-1"><?php echo $total_cases; ?></h5>
                                    <small class="text-muted"><?php _e('Total Cases', 'case-manager-pro'); ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <h5 class="text-warning mb-1"><?php echo $pending_cases; ?></h5>
                                    <small class="text-muted"><?php _e('Pending', 'case-manager-pro'); ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <h5 class="text-success mb-1"><?php echo $resolved_cases; ?></h5>
                                    <small class="text-muted"><?php _e('Resolved', 'case-manager-pro'); ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Roles -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2"><?php _e('Roles', 'case-manager-pro'); ?></h6>
                            <?php foreach ($user->roles as $role): ?>
                                <span class="badge bg-secondary me-1"><?php echo esc_html(ucfirst($role)); ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Member Since -->
                        <div class="text-muted small">
                            <i class="fas fa-calendar me-2"></i>
                            <?php _e('Member since', 'case-manager-pro'); ?> 
                            <?php echo date_i18n(get_option('date_format'), strtotime($user->user_registered)); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            <?php _e('Quick Actions', 'case-manager-pro'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?php echo add_query_arg('view', 'submit'); ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i><?php _e('Submit New Case', 'case-manager-pro'); ?>
                            </a>
                            <a href="<?php echo add_query_arg('view', 'cases'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-folder me-2"></i><?php _e('View My Cases', 'case-manager-pro'); ?>
                            </a>
                            <a href="<?php echo add_query_arg('view', 'notifications'); ?>" class="btn btn-outline-info">
                                <i class="fas fa-bell me-2"></i><?php _e('Notifications', 'case-manager-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <!-- Profile Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-edit text-primary me-2"></i>
                            <?php _e('Edit Profile', 'case-manager-pro'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="display_name" class="form-label fw-bold">
                                            <i class="fas fa-user me-2"></i><?php _e('Display Name', 'case-manager-pro'); ?>
                                        </label>
                                        <input type="text" class="form-control" id="display_name" name="display_name" 
                                               value="<?php echo esc_attr($user->display_name); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-bold">
                                            <i class="fas fa-envelope me-2"></i><?php _e('Email Address', 'case-manager-pro'); ?>
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo esc_attr($user->user_email); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label fw-bold">
                                            <i class="fas fa-at me-2"></i><?php _e('Username', 'case-manager-pro'); ?>
                                        </label>
                                        <input type="text" class="form-control-plaintext" id="username" 
                                               value="<?php echo esc_attr($user->user_login); ?>" readonly>
                                        <div class="form-text text-muted">
                                            <?php _e('Username cannot be changed', 'case-manager-pro'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label fw-bold">
                                            <i class="fas fa-phone me-2"></i><?php _e('Phone Number', 'case-manager-pro'); ?>
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label fw-bold">
                                    <i class="fas fa-info-circle me-2"></i><?php _e('Bio', 'case-manager-pro'); ?>
                                </label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" 
                                          placeholder="<?php _e('Tell us about yourself...', 'case-manager-pro'); ?>"><?php echo esc_textarea(get_user_meta($user_id, 'description', true)); ?></textarea>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="card bg-light border-0 mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        <?php _e('Account Information', 'case-manager-pro'); ?>
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block"><?php _e('Registration Date', 'case-manager-pro'); ?></small>
                                            <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($user->user_registered)); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block"><?php _e('Last Login', 'case-manager-pro'); ?></small>
                                            <span>
                                                <?php 
                                                $last_login = get_user_meta($user_id, 'last_login', true);
                                                echo $last_login ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_login) : __('Never', 'case-manager-pro');
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?php echo wp_lostpassword_url(); ?>" class="btn btn-outline-warning">
                                    <i class="fas fa-key me-2"></i><?php _e('Change Password', 'case-manager-pro'); ?>
                                </a>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                        <i class="fas fa-undo me-2"></i><?php _e('Reset', 'case-manager-pro'); ?>
                                    </button>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php _e('Update Profile', 'case-manager-pro'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Form validation
            $('form').on('submit', function(e) {
                var displayName = $('#display_name').val().trim();
                var email = $('#email').val().trim();
                
                if (!displayName) {
                    e.preventDefault();
                    showToast('error', '<?php _e('Display name is required', 'case-manager-pro'); ?>');
                    $('#display_name').focus();
                    return false;
                }
                
                if (!email || !isValidEmail(email)) {
                    e.preventDefault();
                    showToast('error', '<?php _e('Valid email address is required', 'case-manager-pro'); ?>');
                    $('#email').focus();
                    return false;
                }
                
                // Show loading state
                var $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i><?php _e('Updating...', 'case-manager-pro'); ?>');
            });
            
            function isValidEmail(email) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        });
        </script>
        <?php
    }
} 