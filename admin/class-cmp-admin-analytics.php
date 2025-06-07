<?php
/**
 * Admin analytics page
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Admin_Analytics {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'cmp-cases',
            __('Advanced Analytics', 'case-manager-pro'),
            __('Advanced Analytics', 'case-manager-pro'),
            'manage_options',
            'cmp-advanced-analytics',
            array($this, 'analytics_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'cases_page_cmp-advanced-analytics') {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script(
            'cmp-analytics-js',
            CMP_PLUGIN_URL . 'assets/js/analytics.js',
            array('jquery', 'chart-js'),
            CMP_VERSION,
            true
        );
        
        wp_localize_script('cmp-analytics-js', 'cmp_analytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cmp_admin_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'case-manager-pro'),
                'error' => __('Error loading data', 'case-manager-pro'),
                'export_success' => __('Report exported successfully', 'case-manager-pro'),
                'export_error' => __('Error exporting report', 'case-manager-pro')
            )
        ));
    }
    
    public function analytics_page() {
        ?>
        <div class="wrap cmp-analytics-page">
            <h1><?php _e('Advanced Analytics', 'case-manager-pro'); ?></h1>
            
            <div class="cmp-analytics-controls">
                <div class="cmp-period-selector">
                    <label for="analytics-period"><?php _e('Time Period:', 'case-manager-pro'); ?></label>
                    <select id="analytics-period">
                        <option value="7"><?php _e('Last 7 days', 'case-manager-pro'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'case-manager-pro'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'case-manager-pro'); ?></option>
                        <option value="365"><?php _e('Last year', 'case-manager-pro'); ?></option>
                        <option value="all"><?php _e('All time', 'case-manager-pro'); ?></option>
                    </select>
                </div>
                
                <div class="cmp-export-controls">
                    <button type="button" id="export-overview" class="button" data-report="overview">
                        <?php _e('Export Overview', 'case-manager-pro'); ?>
                    </button>
                    <button type="button" id="export-performance" class="button" data-report="user_performance">
                        <?php _e('Export User Performance', 'case-manager-pro'); ?>
                    </button>
                    <button type="button" id="export-resolution" class="button" data-report="resolution_times">
                        <?php _e('Export Resolution Times', 'case-manager-pro'); ?>
                    </button>
                </div>
            </div>
            
            <div id="analytics-loading" class="cmp-loading" style="display: none;">
                <div class="cmp-spinner"></div>
                <p><?php _e('Loading analytics data...', 'case-manager-pro'); ?></p>
            </div>
            
            <div id="analytics-content" class="cmp-analytics-content">
                <!-- Overview Stats -->
                <div class="cmp-analytics-section">
                    <h2><?php _e('Overview Statistics', 'case-manager-pro'); ?></h2>
                    <div class="cmp-stats-grid" id="overview-stats">
                        <!-- Stats will be loaded here -->
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="cmp-analytics-section">
                    <h2><?php _e('Visual Analytics', 'case-manager-pro'); ?></h2>
                    
                    <div class="cmp-charts-grid">
                        <div class="cmp-chart-container">
                            <h3><?php _e('Case Trends', 'case-manager-pro'); ?></h3>
                            <canvas id="case-trends-chart"></canvas>
                        </div>
                        
                        <div class="cmp-chart-container">
                            <h3><?php _e('Status Distribution', 'case-manager-pro'); ?></h3>
                            <canvas id="status-distribution-chart"></canvas>
                        </div>
                        
                        <div class="cmp-chart-container">
                            <h3><?php _e('Resolution Time Distribution', 'case-manager-pro'); ?></h3>
                            <canvas id="resolution-time-chart"></canvas>
                        </div>
                        
                        <div class="cmp-chart-container">
                            <h3><?php _e('File Type Distribution', 'case-manager-pro'); ?></h3>
                            <canvas id="file-type-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- User Performance -->
                <div class="cmp-analytics-section">
                    <h2><?php _e('User Performance', 'case-manager-pro'); ?></h2>
                    <div class="cmp-table-container">
                        <table class="wp-list-table widefat fixed striped" id="user-performance-table">
                            <thead>
                                <tr>
                                    <th><?php _e('User', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Total Cases', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Completed', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Rejected', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Completion Rate', 'case-manager-pro'); ?></th>
                                    <th><?php _e('Avg Resolution Time', 'case-manager-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Resolution Time Analysis -->
                <div class="cmp-analytics-section">
                    <h2><?php _e('Resolution Time Analysis', 'case-manager-pro'); ?></h2>
                    <div class="cmp-resolution-analysis" id="resolution-analysis">
                        <!-- Analysis will be loaded here -->
                    </div>
                </div>
                
                <!-- File Statistics -->
                <div class="cmp-analytics-section">
                    <h2><?php _e('File Statistics', 'case-manager-pro'); ?></h2>
                    <div class="cmp-file-stats-grid" id="file-statistics">
                        <!-- File stats will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .cmp-analytics-page {
            max-width: 1400px;
        }
        
        .cmp-analytics-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cmp-period-selector label {
            margin-right: 10px;
            font-weight: 600;
        }
        
        .cmp-period-selector select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cmp-export-controls {
            display: flex;
            gap: 10px;
        }
        
        .cmp-analytics-content {
            display: none;
        }
        
        .cmp-analytics-section {
            background: #fff;
            margin: 20px 0;
            padding: 25px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cmp-analytics-section h2 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
            color: #333;
        }
        
        .cmp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .cmp-stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #0073aa;
        }
        
        .cmp-stat-card h3 {
            font-size: 2.5em;
            margin: 0;
            color: #0073aa;
            font-weight: 700;
        }
        
        .cmp-stat-card p {
            margin: 10px 0 0 0;
            color: #666;
            font-weight: 500;
        }
        
        .cmp-charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .cmp-chart-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            height: 400px;
        }
        
        .cmp-chart-container h3 {
            margin: 0 0 20px 0;
            text-align: center;
            color: #333;
        }
        
        .cmp-chart-container canvas {
            max-height: 300px;
        }
        
        .cmp-table-container {
            overflow-x: auto;
        }
        
        .cmp-resolution-analysis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .cmp-resolution-metric {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .cmp-resolution-metric h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .cmp-resolution-metric .value {
            font-size: 1.8em;
            font-weight: 700;
            color: #0073aa;
        }
        
        .cmp-file-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cmp-file-stat-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .cmp-file-stat-section h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .cmp-file-stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .cmp-file-stat-item:last-child {
            border-bottom: none;
        }
        
        .cmp-loading {
            text-align: center;
            padding: 60px 20px;
        }
        
        .cmp-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            animation: cmp-spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes cmp-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .cmp-analytics-controls {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .cmp-charts-grid {
                grid-template-columns: 1fr;
            }
            
            .cmp-chart-container {
                height: 300px;
            }
            
            .cmp-export-controls {
                flex-direction: column;
                width: 100%;
            }
            
            .cmp-export-controls button {
                width: 100%;
            }
        }
        </style>
        <?php
    }
} 