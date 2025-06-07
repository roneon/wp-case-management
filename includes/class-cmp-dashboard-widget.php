<?php
/**
 * Dashboard widget for analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Dashboard_Widget {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
    }
    
    public function add_dashboard_widgets() {
        if (current_user_can('cmp_view_case_analytics')) {
            wp_add_dashboard_widget(
                'cmp_analytics_widget',
                __('Case Manager Analytics', 'case-manager-pro'),
                array($this, 'analytics_widget_content')
            );
        }
    }
    
    public function analytics_widget_content() {
        $analytics = CMP_Analytics::get_instance();
        $data = $analytics->get_dashboard_widget_data();
        
        if (!$data) {
            echo '<p>' . __('No analytics data available.', 'case-manager-pro') . '</p>';
            return;
        }
        
        $overview = $data['overview'];
        $trends = $data['recent_trends'];
        ?>
        
        <div class="cmp-dashboard-widget">
            <div class="cmp-widget-stats">
                <div class="cmp-stat-item">
                    <span class="cmp-stat-number"><?php echo esc_html($overview['total_cases']); ?></span>
                    <span class="cmp-stat-label"><?php _e('Total Cases', 'case-manager-pro'); ?></span>
                </div>
                
                <div class="cmp-stat-item">
                    <span class="cmp-stat-number"><?php echo esc_html($overview['pending_cases']); ?></span>
                    <span class="cmp-stat-label"><?php _e('Pending', 'case-manager-pro'); ?></span>
                </div>
                
                <div class="cmp-stat-item">
                    <span class="cmp-stat-number"><?php echo esc_html($overview['completed_cases']); ?></span>
                    <span class="cmp-stat-label"><?php _e('Completed', 'case-manager-pro'); ?></span>
                </div>
                
                <div class="cmp-stat-item">
                    <span class="cmp-stat-number"><?php echo esc_html($overview['completion_rate']); ?>%</span>
                    <span class="cmp-stat-label"><?php _e('Success Rate', 'case-manager-pro'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($trends)): ?>
            <div class="cmp-widget-chart">
                <h4><?php _e('Last 7 Days Trend', 'case-manager-pro'); ?></h4>
                <canvas id="cmp-widget-chart" width="400" height="200"></canvas>
            </div>
            <?php endif; ?>
            
            <div class="cmp-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=cmp-advanced-analytics'); ?>" class="button button-primary">
                    <?php _e('View Full Analytics', 'case-manager-pro'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=cmp-cases'); ?>" class="button">
                    <?php _e('Manage Cases', 'case-manager-pro'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .cmp-dashboard-widget {
            padding: 0;
        }
        
        .cmp-widget-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .cmp-stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0073aa;
        }
        
        .cmp-stat-number {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #0073aa;
            line-height: 1;
        }
        
        .cmp-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .cmp-widget-chart {
            margin-bottom: 20px;
        }
        
        .cmp-widget-chart h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
        }
        
        .cmp-widget-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .cmp-widget-actions .button {
            flex: 1;
            text-align: center;
            justify-content: center;
        }
        
        @media (max-width: 782px) {
            .cmp-widget-stats {
                grid-template-columns: 1fr;
            }
            
            .cmp-widget-actions {
                flex-direction: column;
            }
        }
        </style>
        
        <?php if (!empty($trends)): ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof Chart !== 'undefined') {
                const ctx = document.getElementById('cmp-widget-chart');
                if (ctx) {
                    const chartData = <?php echo json_encode($trends); ?>;
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.map(item => {
                                const date = new Date(item.date);
                                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            }),
                            datasets: [{
                                label: 'Cases',
                                data: chartData.map(item => item.total_cases),
                                borderColor: '#0073aa',
                                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            }
        });
        </script>
        <?php endif; ?>
        
        <?php
    }
} 