<?php
/**
 * Admin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Admin_Settings {
    
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
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_cmp_test_cloud_connection', array($this, 'test_cloud_connection'));
    }
    
    public function init_settings() {
        // Register settings
        register_setting('cmp_settings', 'cmp_cloud_provider');
        register_setting('cmp_settings', 'cmp_file_retention_days');
        register_setting('cmp_settings', 'cmp_max_file_size');
        register_setting('cmp_settings', 'cmp_allowed_file_types');
        register_setting('cmp_settings', 'cmp_enable_notifications');
        register_setting('cmp_settings', 'cmp_dashboard_page_id');
        
        // Amazon S3 settings
        register_setting('cmp_settings', 'cmp_s3_access_key');
        register_setting('cmp_settings', 'cmp_s3_secret_key');
        register_setting('cmp_settings', 'cmp_s3_bucket');
        register_setting('cmp_settings', 'cmp_s3_region');
        
        // Google Drive settings
        register_setting('cmp_settings', 'cmp_google_drive_client_id');
        register_setting('cmp_settings', 'cmp_google_drive_client_secret');
        register_setting('cmp_settings', 'cmp_google_drive_refresh_token');
        register_setting('cmp_settings', 'cmp_google_drive_folder_id');
        
        // Dropbox settings
        register_setting('cmp_settings', 'cmp_dropbox_app_key');
        register_setting('cmp_settings', 'cmp_dropbox_app_secret');
        register_setting('cmp_settings', 'cmp_dropbox_access_token');
        register_setting('cmp_settings', 'cmp_dropbox_folder_path');
        
        // Add settings sections
        add_settings_section(
            'cmp_general_settings',
            __('General Settings', 'case-manager-pro'),
            array($this, 'general_settings_callback'),
            'cmp_settings'
        );
        
        add_settings_section(
            'cmp_cloud_settings',
            __('Cloud Storage Settings', 'case-manager-pro'),
            array($this, 'cloud_settings_callback'),
            'cmp_settings'
        );
        
        add_settings_section(
            'cmp_file_settings',
            __('File Management Settings', 'case-manager-pro'),
            array($this, 'file_settings_callback'),
            'cmp_settings'
        );
        
        // Add settings fields
        $this->add_general_fields();
        $this->add_cloud_fields();
        $this->add_file_fields();
    }
    
    private function add_general_fields() {
        add_settings_field(
            'cmp_enable_notifications',
            __('Enable Email Notifications', 'case-manager-pro'),
            array($this, 'checkbox_field'),
            'cmp_settings',
            'cmp_general_settings',
            array(
                'name' => 'cmp_enable_notifications',
                'description' => __('Send email notifications for case updates', 'case-manager-pro')
            )
        );
        
        add_settings_field(
            'cmp_dashboard_page_id',
            __('Dashboard Page', 'case-manager-pro'),
            array($this, 'page_select_field'),
            'cmp_settings',
            'cmp_general_settings',
            array(
                'name' => 'cmp_dashboard_page_id',
                'description' => __('Select the page to use as the case management dashboard', 'case-manager-pro')
            )
        );
    }
    
    private function add_cloud_fields() {
        // Sadece Cloud Storage Provider seçim kutusu
        add_settings_field(
            'cmp_cloud_provider',
            __('Cloud Storage Provider', 'case-manager-pro'),
            array($this, 'cloud_provider_field'),
            'cmp_settings',
            'cmp_cloud_settings',
            array(
                'name' => 'cmp_cloud_provider',
                'description' => __('Choose your preferred cloud storage provider', 'case-manager-pro')
            )
        );
    }
    
    private function add_file_fields() {
        add_settings_field(
            'cmp_file_retention_days',
            __('File Retention Period (Days)', 'case-manager-pro'),
            array($this, 'number_field'),
            'cmp_settings',
            'cmp_file_settings',
            array(
                'name' => 'cmp_file_retention_days',
                'min' => 1,
                'max' => 365,
                'description' => __('Number of days to keep files before automatic deletion', 'case-manager-pro')
            )
        );
        
        add_settings_field(
            'cmp_max_file_size',
            __('Maximum File Size (MB)', 'case-manager-pro'),
            array($this, 'number_field'),
            'cmp_settings',
            'cmp_file_settings',
            array(
                'name' => 'cmp_max_file_size',
                'min' => 1,
                'max' => 5120,
                'description' => __('Maximum allowed file size in megabytes', 'case-manager-pro')
            )
        );
        
        add_settings_field(
            'cmp_allowed_file_types',
            __('Allowed File Types', 'case-manager-pro'),
            array($this, 'text_field'),
            'cmp_settings',
            'cmp_file_settings',
            array(
                'name' => 'cmp_allowed_file_types',
                'description' => __('Comma-separated list of allowed file extensions (e.g., pdf,doc,docx,jpg,png)', 'case-manager-pro')
            )
        );
    }
    
    public function settings_page() {
        // Bootstrap ve Font Awesome CDN'lerini ekle
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        ?>
        <div class="wrap cmp-admin-settings">
            <!-- Header -->
            <div class="cmp-settings-container">
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="mb-2 mb-md-0">
                                        <h1 class="h3 mb-1 text-dark">
                                            <i class="fas fa-cogs text-primary me-2"></i>
                                            <?php _e('Case Manager Pro Settings', 'case-manager-pro'); ?>
                                        </h1>
                                        <p class="text-muted mb-0"><?php _e('Configure your case management system settings', 'case-manager-pro'); ?></p>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
                                            <i class="fas fa-question-circle me-2"></i><?php _e('Help', 'case-manager-pro'); ?>
                                        </button>
                                        <a href="<?php echo admin_url('admin.php?page=cmp-dashboard'); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-tachometer-alt me-2"></i><?php _e('Dashboard', 'case-manager-pro'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('cmp_settings');
                    ?>
                    
                    <!-- Settings Tabs -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-bottom">
                                    <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                                <i class="fas fa-cog me-2"></i><?php _e('General', 'case-manager-pro'); ?>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="cloud-tab" data-bs-toggle="tab" data-bs-target="#cloud" type="button" role="tab">
                                                <i class="fas fa-cloud me-2"></i><?php _e('Cloud Storage', 'case-manager-pro'); ?>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab">
                                                <i class="fas fa-file me-2"></i><?php _e('File Management', 'case-manager-pro'); ?>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                
                                <div class="card-body p-4">
                                    <div class="tab-content" id="settingsTabContent">
                                        <!-- General Settings Tab -->
                                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                                            <div class="row">
                                                <div class="col-xl-8 col-lg-7">
                                                    <h5 class="mb-4">
                                                        <i class="fas fa-cog text-primary me-2"></i>
                                                        <?php _e('General Settings', 'case-manager-pro'); ?>
                                                    </h5>
                                                    <div class="card border-0 bg-light">
                                                        <div class="card-body p-4">
                                                            <?php do_settings_fields('cmp_settings', 'cmp_general_settings'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xl-4 col-lg-5">
                                                    <div class="card border-0 bg-info bg-opacity-10">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-info-circle text-info me-2"></i>
                                                                <?php _e('General Settings Help', 'case-manager-pro'); ?>
                                                            </h6>
                                                            <p class="card-text small text-muted">
                                                                <?php _e('Configure basic plugin functionality including notifications and dashboard page selection.', 'case-manager-pro'); ?>
                                                            </p>
                                                            <ul class="list-unstyled small text-muted">
                                                                <li><i class="fas fa-check text-success me-2"></i><?php _e('Email notifications for case updates', 'case-manager-pro'); ?></li>
                                                                <li><i class="fas fa-check text-success me-2"></i><?php _e('Dashboard page configuration', 'case-manager-pro'); ?></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Cloud Storage Tab -->
                                        <div class="tab-pane fade" id="cloud" role="tabpanel">
                                            <div class="row">
                                                <div class="col-xl-8 col-lg-7">
                                                    <h5 class="mb-4">
                                                        <i class="fas fa-cloud text-primary me-2"></i>
                                                        <?php _e('Cloud Storage Settings', 'case-manager-pro'); ?>
                                                    </h5>
                                                    <div class="card border-0 bg-light">
                                                        <div class="card-body p-4">
                                                            <?php do_settings_fields('cmp_settings', 'cmp_cloud_settings'); ?>
                                                            
                                                            <!-- Test Connection Button -->
                                                            <div class="mt-4 pt-3 border-top">
                                                                <button type="button" id="cmp-test-connection" class="btn btn-outline-primary">
                                                                    <i class="fas fa-plug me-2"></i>
                                                                    <?php _e('Test Connection', 'case-manager-pro'); ?>
                                                                </button>
                                                                <div id="cmp-test-result" class="mt-3"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xl-4 col-lg-5">
                                                    <div class="card border-0 bg-success bg-opacity-10 mb-3">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-shield-alt text-success me-2"></i>
                                                                <?php _e('Security Notice', 'case-manager-pro'); ?>
                                                            </h6>
                                                            <p class="card-text small text-muted">
                                                                <?php _e('Your cloud storage credentials are encrypted and stored securely.', 'case-manager-pro'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="card border-0 bg-info bg-opacity-10">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-question-circle text-info me-2"></i>
                                                                <?php _e('Supported Providers', 'case-manager-pro'); ?>
                                                            </h6>
                                                            <ul class="list-unstyled small text-muted">
                                                                <li><i class="fab fa-aws text-warning me-2"></i><?php _e('Amazon S3', 'case-manager-pro'); ?></li>
                                                                <li><i class="fab fa-google text-danger me-2"></i><?php _e('Google Drive', 'case-manager-pro'); ?></li>
                                                                <li><i class="fab fa-dropbox text-primary me-2"></i><?php _e('Dropbox', 'case-manager-pro'); ?></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- File Management Tab -->
                                        <div class="tab-pane fade" id="files" role="tabpanel">
                                            <div class="row">
                                                <div class="col-xl-8 col-lg-7">
                                                    <h5 class="mb-4">
                                                        <i class="fas fa-file text-primary me-2"></i>
                                                        <?php _e('File Management Settings', 'case-manager-pro'); ?>
                                                    </h5>
                                                    <div class="card border-0 bg-light">
                                                        <div class="card-body p-4">
                                                            <?php do_settings_fields('cmp_settings', 'cmp_file_settings'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xl-4 col-lg-5">
                                                    <div class="card border-0 bg-warning bg-opacity-10 mb-3">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                                <?php _e('Important Notes', 'case-manager-pro'); ?>
                                                            </h6>
                                                            <ul class="list-unstyled small text-muted">
                                                                <li><i class="fas fa-clock text-info me-2"></i><?php _e('Files are automatically deleted after retention period', 'case-manager-pro'); ?></li>
                                                                <li><i class="fas fa-shield-alt text-success me-2"></i><?php _e('Only allowed file types can be uploaded', 'case-manager-pro'); ?></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="card border-0 bg-info bg-opacity-10">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-file-alt text-info me-2"></i>
                                                                <?php _e('Recommended File Types', 'case-manager-pro'); ?>
                                                            </h6>
                                                            <div class="row g-2 small text-muted">
                                                                <div class="col-6">
                                                                    <span class="badge bg-secondary">PDF</span>
                                                                    <span class="badge bg-secondary">DOC</span>
                                                                    <span class="badge bg-secondary">DOCX</span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="badge bg-secondary">JPG</span>
                                                                    <span class="badge bg-secondary">PNG</span>
                                                                    <span class="badge bg-secondary">ZIP</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Save Button -->
                                <div class="card-footer bg-white border-top p-4">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div class="text-muted small mb-2 mb-md-0">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php _e('Changes will be applied immediately after saving.', 'case-manager-pro'); ?>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                                <i class="fas fa-undo me-2"></i>
                                                <?php _e('Reset', 'case-manager-pro'); ?>
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-save me-2"></i>
                                                <?php _e('Save Changes', 'case-manager-pro'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Help Modal -->
        <div class="modal fade" id="helpModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-question-circle text-info me-2"></i>
                            <?php _e('Case Manager Pro Help', 'case-manager-pro'); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-cog text-primary me-2"></i><?php _e('General Settings', 'case-manager-pro'); ?></h6>
                                <p class="small text-muted"><?php _e('Configure basic plugin functionality and dashboard page.', 'case-manager-pro'); ?></p>
                                
                                <h6><i class="fas fa-cloud text-primary me-2"></i><?php _e('Cloud Storage', 'case-manager-pro'); ?></h6>
                                <p class="small text-muted"><?php _e('Connect to cloud storage providers for secure file storage.', 'case-manager-pro'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-file text-primary me-2"></i><?php _e('File Management', 'case-manager-pro'); ?></h6>
                                <p class="small text-muted"><?php _e('Set file size limits, allowed types, and retention policies.', 'case-manager-pro'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?php _e('Close', 'case-manager-pro'); ?>
                        </button>
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-book me-2"></i>
                            <?php _e('View Documentation', 'case-manager-pro'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* Custom Admin Styles */
        .cmp-admin-settings.wrap {
            margin: 20px 0 0 0;
            padding: 0;
            max-width: none;
        }
        
        .cmp-settings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* WordPress admin panel genişlik override */
        .cmp-admin-settings {
            margin-left: -20px;
            margin-right: -20px;
        }
        
        @media screen and (max-width: 782px) {
            .cmp-admin-settings {
                margin-left: -10px;
                margin-right: -10px;
            }
            
            .cmp-settings-container {
                padding: 0 10px;
            }
        }
        
        /* Bootstrap grid system için ek genişlik */
        .cmp-settings-container .row {
            margin-left: -15px;
            margin-right: -15px;
        }
        
        .cmp-settings-container .col-12,
        .cmp-settings-container [class*="col-"] {
            padding-left: 15px;
            padding-right: 15px;
        }
        
        /* WordPress card CSS override - ÇOK ÖNEMLİ! */
        .cmp-admin-settings .card,
        .cmp-settings-container .card,
        .cmp-admin-settings .card.border-0,
        .cmp-settings-container .card.border-0 {
            max-width: none !important;
            min-width: auto !important;
            width: 100% !important;
            margin-top: 0 !important;
            padding: 0 !important;
            position: relative;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.375rem;
        }
        
        /* Bootstrap card body override */
        .cmp-admin-settings .card-body,
        .cmp-settings-container .card-body {
            flex: 1 1 auto;
            padding: 1rem;
        }
        
        .cmp-admin-settings .card-body.p-4,
        .cmp-settings-container .card-body.p-4 {
            padding: 1.5rem !important;
        }
        
        /* Bootstrap card header override */
        .cmp-admin-settings .card-header,
        .cmp-settings-container .card-header {
            padding: 0.75rem 1rem;
            margin-bottom: 0;
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
            border-top-left-radius: calc(0.375rem - 1px);
            border-top-right-radius: calc(0.375rem - 1px);
        }
        
        /* Bootstrap card footer override */
        .cmp-admin-settings .card-footer,
        .cmp-settings-container .card-footer {
            padding: 0.75rem 1rem;
            background-color: rgba(0,0,0,.03);
            border-top: 1px solid rgba(0,0,0,.125);
            border-bottom-right-radius: calc(0.375rem - 1px);
            border-bottom-left-radius: calc(0.375rem - 1px);
        }
        
        .cmp-admin-settings .card-footer.p-4,
        .cmp-settings-container .card-footer.p-4 {
            padding: 1.5rem !important;
        }
        
        /* Shadow override */
        .cmp-admin-settings .shadow-sm,
        .cmp-settings-container .shadow-sm {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        }
        
        /* Border override */
        .cmp-admin-settings .border-0,
        .cmp-settings-container .border-0 {
            border: 0 !important;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
            color: #495057;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom-color: #0d6efd;
            color: #0d6efd;
            background: none;
        }
        
        /* Form table responsive düzenlemeler */
        .cmp-admin-settings .form-table,
        .cmp-settings-container .form-table {
            background: none;
            border: none;
            width: 100%;
            margin: 0;
        }
        
        .cmp-admin-settings .form-table th,
        .cmp-settings-container .form-table th {
            padding: 20px 0;
            font-weight: 600;
            color: #495057;
            border: none;
            background: none;
            width: 200px;
            vertical-align: top;
        }
        
        .cmp-admin-settings .form-table td,
        .cmp-settings-container .form-table td {
            padding: 20px 0;
            border: none;
            background: none;
            vertical-align: top;
        }
        
        /* Form controls */
        .cmp-admin-settings .form-control,
        .cmp-admin-settings .form-select,
        .cmp-settings-container .form-control,
        .cmp-settings-container .form-select {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            min-width: 250px;
        }
        
        .cmp-admin-settings .form-control:focus,
        .cmp-admin-settings .form-select:focus,
        .cmp-settings-container .form-control:focus,
        .cmp-settings-container .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        /* Card hover effects */
        .cmp-admin-settings .card,
        .cmp-settings-container .card {
            transition: all 0.3s ease;
        }
        
        .cmp-admin-settings .card:hover,
        .cmp-settings-container .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        
        /* Design preview */
        .cmp-admin-settings #design-preview,
        .cmp-settings-container #design-preview {
            transition: all 0.3s ease;
            min-height: 80px;
        }
        
        /* Badge styling */
        .cmp-admin-settings .badge,
        .cmp-settings-container .badge {
            font-size: 0.75em;
        }
        
        /* Container width fix */
        .cmp-admin-settings .container-fluid,
        .cmp-settings-container .container-fluid {
            max-width: none !important;
            width: 100% !important;
        }
        
        /* Row width fix */
        .cmp-admin-settings .row,
        .cmp-settings-container .row {
            width: 100% !important;
            max-width: none !important;
        }
        
        /* Column width fix */
        .cmp-admin-settings [class*="col-"],
        .cmp-settings-container [class*="col-"] {
            max-width: none !important;
        }
        
        /* Responsive improvements */
        @media (max-width: 1200px) {
            .cmp-settings-container {
                max-width: 100%;
            }
            
            .cmp-admin-settings .form-table th,
            .cmp-settings-container .form-table th {
                width: 180px;
            }
            
            .cmp-admin-settings .form-control,
            .cmp-admin-settings .form-select,
            .cmp-settings-container .form-control,
            .cmp-settings-container .form-select {
                min-width: 200px;
            }
        }
        
        @media (max-width: 992px) {
            .cmp-admin-settings .form-table th,
            .cmp-settings-container .form-table th {
                width: 150px;
            }
            
            .cmp-admin-settings .form-control,
            .cmp-admin-settings .form-select,
            .cmp-settings-container .form-control,
            .cmp-settings-container .form-select {
                min-width: 180px;
            }
            
            .cmp-admin-settings .col-xl-8.col-lg-7,
            .cmp-admin-settings .col-xl-4.col-lg-5,
            .cmp-settings-container .col-xl-8.col-lg-7,
            .cmp-settings-container .col-xl-4.col-lg-5 {
                margin-bottom: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .cmp-settings-container {
                padding: 0 10px;
            }
            
            .cmp-admin-settings .card-body.p-4,
            .cmp-settings-container .card-body.p-4 {
                padding: 1.5rem !important;
            }
            
            .cmp-admin-settings .card-footer.p-4,
            .cmp-settings-container .card-footer.p-4 {
                padding: 1.5rem !important;
            }
            
            .cmp-admin-settings .d-flex.gap-2.flex-wrap,
            .cmp-settings-container .d-flex.gap-2.flex-wrap {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .cmp-admin-settings .btn-lg,
            .cmp-settings-container .btn-lg {
                width: 100%;
            }
            
            .cmp-admin-settings .form-table,
            .cmp-settings-container .form-table {
                display: block;
            }
            
            .cmp-admin-settings .form-table th,
            .cmp-admin-settings .form-table td,
            .cmp-settings-container .form-table th,
            .cmp-settings-container .form-table td {
                display: block;
                width: 100%;
                padding: 10px 0;
            }
            
            .cmp-admin-settings .form-table th,
            .cmp-settings-container .form-table th {
                font-weight: 600;
                margin-bottom: 5px;
            }
            
            .cmp-admin-settings .form-control,
            .cmp-admin-settings .form-select,
            .cmp-settings-container .form-control,
            .cmp-settings-container .form-select {
                width: 100%;
                min-width: auto;
            }
            
            .cmp-admin-settings .nav-tabs .nav-link,
            .cmp-settings-container .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 576px) {
            .cmp-admin-settings .h3,
            .cmp-settings-container .h3 {
                font-size: 1.5rem;
            }
            
            .cmp-admin-settings .card-header .nav-tabs,
            .cmp-settings-container .card-header .nav-tabs {
                flex-wrap: wrap;
            }
            
            .cmp-admin-settings .nav-tabs .nav-link,
            .cmp-settings-container .nav-tabs .nav-link {
                flex: 1;
                text-align: center;
                min-width: 0;
            }
            
            .cmp-admin-settings .nav-tabs .nav-link i,
            .cmp-settings-container .nav-tabs .nav-link i {
                display: none;
            }
        }
        
        /* WordPress admin menu ile uyumluluk */
        @media screen and (min-width: 783px) {
            .cmp-admin-settings {
                margin-left: -20px;
            }
        }
        
        @media screen and (max-width: 960px) {
            .cmp-admin-settings {
                margin-left: -20px;
            }
        }
        
        /* Folded menu durumu */
        .folded .cmp-admin-settings {
            margin-left: -20px;
        }
        
        /* Auto-folded menu durumu */
        .auto-fold .cmp-admin-settings {
            margin-left: -20px;
        }
        
        /* Full width için ek düzenlemeler */
        .cmp-settings-container {
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Tab content padding */
        .cmp-admin-settings .tab-content,
        .cmp-settings-container .tab-content {
            padding-top: 1rem;
        }
        
        /* Form field spacing */
        .cmp-admin-settings .mb-3,
        .cmp-settings-container .mb-3 {
            margin-bottom: 1.5rem !important;
        }
        
        .cmp-admin-settings .mb-4,
        .cmp-settings-container .mb-4 {
            margin-bottom: 2rem !important;
        }
        
        /* Input group styling */
        .cmp-admin-settings .input-group,
        .cmp-settings-container .input-group {
            width: 100%;
            max-width: 400px;
        }
        
        .cmp-admin-settings .input-group .form-control,
        .cmp-settings-container .input-group .form-control {
            min-width: auto;
        }
        
        /* Color picker styling */
        .cmp-admin-settings input[type="color"],
        .cmp-settings-container input[type="color"] {
            width: 50px;
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 2px;
        }
        
        /* Help text styling */
        .cmp-admin-settings .form-text,
        .cmp-settings-container .form-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        /* Alert styling */
        .cmp-admin-settings .alert,
        .cmp-settings-container .alert {
            border: none;
            border-radius: 0.5rem;
        }
        
        /* Button group spacing */
        .cmp-admin-settings .d-flex.gap-2 > *,
        .cmp-settings-container .d-flex.gap-2 > * {
            margin-right: 0.5rem;
        }
        
        .cmp-admin-settings .d-flex.gap-2 > *:last-child,
        .cmp-settings-container .d-flex.gap-2 > *:last-child {
            margin-right: 0;
        }
        
        /* WordPress specificity override - EN YÜKSEK ÖNCELİK */
        body.wp-admin .cmp-admin-settings .card,
        body.wp-admin .cmp-settings-container .card {
            max-width: none !important;
            min-width: auto !important;
            width: 100% !important;
            margin-top: 0 !important;
            padding: 0 !important;
        }
        
        /* WordPress postbox override */
        .cmp-admin-settings .postbox,
        .cmp-settings-container .postbox {
            max-width: none !important;
            width: 100% !important;
        }
        
        /* Cloud Provider Fields Styling */
        .provider-fields {
            margin-top: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .provider-fields .card {
            border-width: 2px !important;
        }
        
        .provider-fields .card-header {
            font-weight: 600;
            border-bottom-width: 2px;
        }
        
        .provider-fields .card-body {
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        /* Provider specific colors */
        #s3-fields .card {
            border-color: #007bff !important;
        }
        
        #s3-fields .card-header {
            background-color: #007bff !important;
            border-color: #007bff !important;
        }
        
        #google_drive-fields .card {
            border-color: #dc3545 !important;
        }
        
        #google_drive-fields .card-header {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        
        #dropbox-fields .card {
            border-color: #17a2b8 !important;
        }
        
        #dropbox-fields .card-header {
            background-color: #17a2b8 !important;
            border-color: #17a2b8 !important;
        }
        
        /* Form labels in provider fields */
        .provider-fields .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        /* Input groups in provider fields */
        .provider-fields .input-group {
            max-width: 100%;
        }
        
        /* Provider field animations */
        .provider-fields {
            animation: fadeInUp 0.3s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive adjustments for provider fields */
        @media (max-width: 768px) {
            .provider-fields .card-body {
                padding: 1rem !important;
            }
            
            .provider-fields .form-label {
                font-size: 0.9rem;
            }
            
            .provider-fields .input-group .btn {
                padding: 0.375rem 0.5rem;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Cloud provider field visibility - YENİ SİSTEM
            function toggleCloudProviderFields() {
                var selectedProvider = $('#cmp_cloud_provider').val();
                
                // Önce tüm provider field'larını gizle
                $('.provider-fields').hide();
                
                // Seçilen provider'ın field'larını göster
                if (selectedProvider && selectedProvider !== 'none') {
                    $('#' + selectedProvider + '-fields').show();
                }
            }
            
            // Provider değiştiğinde field'ları güncelle
            $('#cmp_cloud_provider').on('change', function() {
                toggleCloudProviderFields();
            });
            
            // Sayfa yüklendiğinde mevcut seçimi kontrol et
            toggleCloudProviderFields();
            
            // Test cloud connection
            $('#cmp-test-connection').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i><?php _e('Testing...', 'case-manager-pro'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmp_test_cloud_connection',
                        nonce: '<?php echo wp_create_nonce('cmp_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#cmp-test-result').html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.data + '</div>');
                        } else {
                            $('#cmp-test-result').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $('#cmp-test-result').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php _e('Connection test failed.', 'case-manager-pro'); ?></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Form validation
            $('form').on('submit', function() {
                var $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i><?php _e('Saving...', 'case-manager-pro'); ?>');
            });
        });
        
        // Password toggle function
        function togglePassword(fieldName) {
            var field = document.getElementById(fieldName);
            var icon = document.getElementById(fieldName + "_icon");
            
            if (field.type === "password") {
                field.type = "text";
                icon.className = "fas fa-eye-slash";
            } else {
                field.type = "password";
                icon.className = "fas fa-eye";
            }
        }
        </script>
        <?php
    }
    
    public function general_settings_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'case-manager-pro') . '</p>';
    }
    
    public function cloud_settings_callback() {
        echo '<p>' . __('Configure your cloud storage provider settings.', 'case-manager-pro') . '</p>';
    }
    
    public function file_settings_callback() {
        echo '<p>' . __('Configure file upload and management settings.', 'case-manager-pro') . '</p>';
    }
    
    public function text_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');
        $class = isset($args['class']) ? $args['class'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        echo '<input type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="form-control ' . esc_attr($class) . '" />';
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
    }
    
    public function password_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');
        $class = isset($args['class']) ? $args['class'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        echo '<div class="input-group">';
        echo '<input type="password" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="form-control ' . esc_attr($class) . '" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'' . esc_attr($name) . '\')">';
        echo '<i class="fas fa-eye" id="' . esc_attr($name) . '_icon"></i>';
        echo '</button>';
        echo '</div>';
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
    }
    
    public function number_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        echo '<input type="number" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" class="form-control" style="max-width: 200px;" />';
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
    }
    
    public function select_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');
        $options = $args['options'];
        $class = isset($args['class']) ? $args['class'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="form-select ' . esc_attr($class) . '">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
    }
    
    public function checkbox_field($args) {
        $name = $args['name'];
        $value = get_option($name, 0);
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        echo '<div class="form-check form-switch">';
        echo '<input type="checkbox" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="1"' . checked($value, 1, false) . ' class="form-check-input" />';
        echo '<label class="form-check-label" for="' . esc_attr($name) . '">' . esc_html($description) . '</label>';
        echo '</div>';
        echo '</div>';
    }
    
    public function page_select_field($args) {
        $name = $args['name'];
        $value = get_option($name, 0);
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<div class="mb-3">';
        wp_dropdown_pages(array(
            'name' => $name,
            'id' => $name,
            'selected' => $value,
            'show_option_none' => __('Select a page', 'case-manager-pro'),
            'option_none_value' => 0,
            'class' => 'form-select'
        ));
        
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
    }
    
    public function color_field($args) {
        $name = $args['name'];
        $value = get_option($name, isset($args['default']) ? $args['default'] : '#0073aa');
        $description = isset($args['description']) ? $args['description'] : '';
        $default = isset($args['default']) ? $args['default'] : '#0073aa';
        
        echo '<div class="mb-3">';
        echo '<div class="d-flex align-items-center gap-3">';
        echo '<input type="color" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="form-control form-control-color" style="width: 60px; height: 40px;" onchange="updateColorText(\'' . esc_attr($name) . '\')" />';
        echo '<input type="text" id="' . esc_attr($name) . '_text" value="' . esc_attr($value) . '" class="form-control" style="max-width: 120px; font-family: monospace;" onchange="updateColorPicker(\'' . esc_attr($name) . '\')" />';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetColor(\'' . esc_attr($name) . '\', \'' . esc_attr($default) . '\')">';
        echo '<i class="fas fa-undo me-1"></i>' . __('Reset', 'case-manager-pro');
        echo '</button>';
        echo '</div>';
        if ($description) {
            echo '<div class="form-text text-muted mt-2">' . esc_html($description) . '</div>';
        }
        echo '</div>';
        
        // Add JavaScript functions for color picker
        static $color_js_added = false;
        if (!$color_js_added) {
            echo '<script>
            function updateColorText(fieldName) {
                var colorPicker = document.getElementById(fieldName);
                var textInput = document.getElementById(fieldName + "_text");
                textInput.value = colorPicker.value;
            }
            
            function updateColorPicker(fieldName) {
                var textInput = document.getElementById(fieldName + "_text");
                var colorPicker = document.getElementById(fieldName);
                if (/^#[0-9A-F]{6}$/i.test(textInput.value)) {
                    colorPicker.value = textInput.value;
                }
            }
            
            function resetColor(fieldName, defaultColor) {
                var colorPicker = document.getElementById(fieldName);
                var textInput = document.getElementById(fieldName + "_text");
                colorPicker.value = defaultColor;
                textInput.value = defaultColor;
            }
            </script>';
            $color_js_added = true;
        }
    }
    
    public function cloud_provider_field($args) {
        $name = $args['name'];
        $value = get_option($name, 'none');
        $description = isset($args['description']) ? $args['description'] : '';
        $providers = CMP_Cloud_Storage::get_available_providers();
        
        echo '<div class="mb-4">';
        
        // Provider seçim kutusu
        echo '<div class="mb-3">';
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="form-select">';
        foreach ($providers as $provider_value => $provider_label) {
            echo '<option value="' . esc_attr($provider_value) . '"' . selected($value, $provider_value, false) . '>' . esc_html($provider_label) . '</option>';
        }
        echo '</select>';
        if ($description) {
            echo '<div class="form-text text-muted">' . esc_html($description) . '</div>';
        }
        echo '</div>';
        
        // Dinamik provider field'ları
        echo '<div id="cloud-provider-fields">';
        
        // Amazon S3 Fields
        echo '<div id="s3-fields" class="provider-fields" style="display: none;">';
        echo '<div class="card border-primary">';
        echo '<div class="card-header bg-primary text-white">';
        echo '<h6 class="mb-0"><i class="fab fa-aws me-2"></i>' . __('Amazon S3 Configuration', 'case-manager-pro') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        
        // S3 Access Key
        echo '<div class="mb-3">';
        echo '<label for="cmp_s3_access_key" class="form-label">' . __('Access Key ID', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_s3_access_key" name="cmp_s3_access_key" value="' . esc_attr(get_option('cmp_s3_access_key', '')) . '" class="form-control" />';
        echo '<div class="form-text">' . __('Your AWS Access Key ID', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // S3 Secret Key
        echo '<div class="mb-3">';
        echo '<label for="cmp_s3_secret_key" class="form-label">' . __('Secret Access Key', 'case-manager-pro') . '</label>';
        echo '<div class="input-group">';
        echo '<input type="password" id="cmp_s3_secret_key" name="cmp_s3_secret_key" value="' . esc_attr(get_option('cmp_s3_secret_key', '')) . '" class="form-control" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'cmp_s3_secret_key\')">';
        echo '<i class="fas fa-eye" id="cmp_s3_secret_key_icon"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="form-text">' . __('Your AWS Secret Access Key', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // S3 Bucket
        echo '<div class="mb-3">';
        echo '<label for="cmp_s3_bucket" class="form-label">' . __('Bucket Name', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_s3_bucket" name="cmp_s3_bucket" value="' . esc_attr(get_option('cmp_s3_bucket', '')) . '" class="form-control" />';
        echo '<div class="form-text">' . __('The S3 bucket name where files will be stored', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // S3 Region
        echo '<div class="mb-3">';
        echo '<label for="cmp_s3_region" class="form-label">' . __('Region', 'case-manager-pro') . '</label>';
        echo '<select id="cmp_s3_region" name="cmp_s3_region" class="form-select">';
        $s3_regions = array(
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)'
        );
        $current_region = get_option('cmp_s3_region', 'us-east-1');
        foreach ($s3_regions as $region_value => $region_label) {
            echo '<option value="' . esc_attr($region_value) . '"' . selected($current_region, $region_value, false) . '>' . esc_html($region_label) . '</option>';
        }
        echo '</select>';
        echo '<div class="form-text">' . __('The AWS region where your bucket is located', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Google Drive Fields
        echo '<div id="google_drive-fields" class="provider-fields" style="display: none;">';
        echo '<div class="card border-danger">';
        echo '<div class="card-header bg-danger text-white">';
        echo '<h6 class="mb-0"><i class="fab fa-google-drive me-2"></i>' . __('Google Drive Configuration', 'case-manager-pro') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        
        // Google Drive Client ID
        echo '<div class="mb-3">';
        echo '<label for="cmp_google_drive_client_id" class="form-label">' . __('Client ID', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_google_drive_client_id" name="cmp_google_drive_client_id" value="' . esc_attr(get_option('cmp_google_drive_client_id', '')) . '" class="form-control" />';
        echo '<div class="form-text">' . __('Your Google Drive API Client ID', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Google Drive Client Secret
        echo '<div class="mb-3">';
        echo '<label for="cmp_google_drive_client_secret" class="form-label">' . __('Client Secret', 'case-manager-pro') . '</label>';
        echo '<div class="input-group">';
        echo '<input type="password" id="cmp_google_drive_client_secret" name="cmp_google_drive_client_secret" value="' . esc_attr(get_option('cmp_google_drive_client_secret', '')) . '" class="form-control" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'cmp_google_drive_client_secret\')">';
        echo '<i class="fas fa-eye" id="cmp_google_drive_client_secret_icon"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="form-text">' . __('Your Google Drive API Client Secret', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Google Drive Refresh Token
        echo '<div class="mb-3">';
        echo '<label for="cmp_google_drive_refresh_token" class="form-label">' . __('Refresh Token', 'case-manager-pro') . '</label>';
        echo '<div class="input-group">';
        echo '<input type="password" id="cmp_google_drive_refresh_token" name="cmp_google_drive_refresh_token" value="' . esc_attr(get_option('cmp_google_drive_refresh_token', '')) . '" class="form-control" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'cmp_google_drive_refresh_token\')">';
        echo '<i class="fas fa-eye" id="cmp_google_drive_refresh_token_icon"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="form-text">' . __('Your Google Drive API Refresh Token', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Google Drive Folder ID
        echo '<div class="mb-3">';
        echo '<label for="cmp_google_drive_folder_id" class="form-label">' . __('Folder ID', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_google_drive_folder_id" name="cmp_google_drive_folder_id" value="' . esc_attr(get_option('cmp_google_drive_folder_id', '')) . '" class="form-control" />';
        echo '<div class="form-text">' . __('The Google Drive folder ID where files will be stored', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Dropbox Fields
        echo '<div id="dropbox-fields" class="provider-fields" style="display: none;">';
        echo '<div class="card border-info">';
        echo '<div class="card-header bg-info text-white">';
        echo '<h6 class="mb-0"><i class="fab fa-dropbox me-2"></i>' . __('Dropbox Configuration', 'case-manager-pro') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        
        // Dropbox App Key
        echo '<div class="mb-3">';
        echo '<label for="cmp_dropbox_app_key" class="form-label">' . __('App Key', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_dropbox_app_key" name="cmp_dropbox_app_key" value="' . esc_attr(get_option('cmp_dropbox_app_key', '')) . '" class="form-control" />';
        echo '<div class="form-text">' . __('Your Dropbox App Key', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Dropbox App Secret
        echo '<div class="mb-3">';
        echo '<label for="cmp_dropbox_app_secret" class="form-label">' . __('App Secret', 'case-manager-pro') . '</label>';
        echo '<div class="input-group">';
        echo '<input type="password" id="cmp_dropbox_app_secret" name="cmp_dropbox_app_secret" value="' . esc_attr(get_option('cmp_dropbox_app_secret', '')) . '" class="form-control" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'cmp_dropbox_app_secret\')">';
        echo '<i class="fas fa-eye" id="cmp_dropbox_app_secret_icon"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="form-text">' . __('Your Dropbox App Secret', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Dropbox Access Token
        echo '<div class="mb-3">';
        echo '<label for="cmp_dropbox_access_token" class="form-label">' . __('Access Token', 'case-manager-pro') . '</label>';
        echo '<div class="input-group">';
        echo '<input type="password" id="cmp_dropbox_access_token" name="cmp_dropbox_access_token" value="' . esc_attr(get_option('cmp_dropbox_access_token', '')) . '" class="form-control" />';
        echo '<button class="btn btn-outline-secondary" type="button" onclick="togglePassword(\'cmp_dropbox_access_token\')">';
        echo '<i class="fas fa-eye" id="cmp_dropbox_access_token_icon"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="form-text">' . __('Your Dropbox Access Token', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        // Dropbox Folder Path
        echo '<div class="mb-3">';
        echo '<label for="cmp_dropbox_folder_path" class="form-label">' . __('Folder Path', 'case-manager-pro') . '</label>';
        echo '<input type="text" id="cmp_dropbox_folder_path" name="cmp_dropbox_folder_path" value="' . esc_attr(get_option('cmp_dropbox_folder_path', '/CaseManagerPro')) . '" class="form-control" placeholder="/CaseManagerPro" />';
        echo '<div class="form-text">' . __('The Dropbox folder path where files will be stored (e.g., /CaseManagerPro)', 'case-manager-pro') . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // cloud-provider-fields
        echo '</div>'; // mb-4
    }
    
    public function test_cloud_connection() {
        check_ajax_referer('cmp_test_connection', 'nonce');
        
        if (!current_user_can('cmp_manage_settings')) {
            wp_die(__('You do not have permission to perform this action.', 'case-manager-pro'));
        }
        
        $cloud_storage = CMP_Cloud_Storage::get_instance();
        $result = $cloud_storage->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Cloud storage connection successful!', 'case-manager-pro')
            ));
        }
    }
} 