<?php
/**
 * Analytics and reporting class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Analytics {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_cmp_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_cmp_export_report', array($this, 'export_report'));
    }
    
    /**
     * Get comprehensive analytics data
     */
    public function get_analytics_data() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('cmp_view_case_analytics') && !current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $data = array(
            'overview' => $this->get_overview_stats($period),
            'case_trends' => $this->get_case_trends($period),
            'user_performance' => $this->get_user_performance($period),
            'resolution_times' => $this->get_resolution_times($period),
            'file_statistics' => $this->get_file_statistics($period),
            'status_distribution' => $this->get_status_distribution($period)
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Get overview statistics
     */
    public function get_overview_stats($period = 30) {
        global $wpdb;
        
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $table_files = $wpdb->prefix . 'cmp_files';
        $table_comments = $wpdb->prefix . 'cmp_case_comments';
        
        $date_condition = $this->get_date_condition($period);
        
        // Total cases
        $total_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE {$date_condition}");
        
        // Cases by status
        $pending_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'pending' AND {$date_condition}");
        $in_progress_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'in_progress' AND {$date_condition}");
        $completed_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'completed' AND {$date_condition}");
        $rejected_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cases} WHERE status = 'rejected' AND {$date_condition}");
        
        // Files statistics - cmp_files tablosunu kullan
        $date_condition_c = $this->get_date_condition($period, 'c');
        $total_files = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_files} f 
            INNER JOIN {$table_cases} c ON f.case_id = c.id 
            WHERE {$date_condition_c}
        ");
        
        $total_file_size = $wpdb->get_var("
            SELECT SUM(f.file_size) FROM {$table_files} f 
            INNER JOIN {$table_cases} c ON f.case_id = c.id 
            WHERE {$date_condition_c}
        ");
        
        // Comments statistics
        $date_condition_c = $this->get_date_condition($period, 'c');
        $total_comments = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_comments} cm 
            INNER JOIN {$table_cases} c ON cm.case_id = c.id 
            WHERE {$date_condition_c}
        ");
        
        // Average resolution time
        $avg_resolution_time = $wpdb->get_var("
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) 
            FROM {$table_cases} 
            WHERE status = 'completed' AND {$date_condition}
        ");
        
        return array(
            'total_cases' => intval($total_cases),
            'pending_cases' => intval($pending_cases),
            'in_progress_cases' => intval($in_progress_cases),
            'completed_cases' => intval($completed_cases),
            'rejected_cases' => intval($rejected_cases),
            'total_files' => intval($total_files),
            'total_file_size' => intval($total_file_size),
            'total_comments' => intval($total_comments),
            'avg_resolution_time' => round(floatval($avg_resolution_time), 2),
            'completion_rate' => $total_cases > 0 ? round(($completed_cases / $total_cases) * 100, 2) : 0
        );
    }
    
    /**
     * Get case trends over time
     */
    public function get_case_trends($period = 30) {
        global $wpdb;
        
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $date_condition = $this->get_date_condition($period);
        
        $results = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_cases,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_cases,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_cases
            FROM {$table_cases} 
            WHERE {$date_condition}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        $trends = array();
        foreach ($results as $row) {
            $trends[] = array(
                'date' => $row->date,
                'total_cases' => intval($row->total_cases),
                'completed_cases' => intval($row->completed_cases),
                'rejected_cases' => intval($row->rejected_cases)
            );
        }
        
        return $trends;
    }
    
    /**
     * Get user performance statistics
     */
    public function get_user_performance($period = 30) {
        global $wpdb;
        
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $date_condition = $this->get_date_condition($period);
        
        $results = $wpdb->get_results("
            SELECT 
                u.ID as user_id,
                u.display_name,
                COUNT(c.id) as total_cases,
                SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as completed_cases,
                SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected_cases,
                AVG(CASE WHEN c.status = 'completed' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) as avg_resolution_time
            FROM {$wpdb->users} u
            INNER JOIN {$table_cases} c ON u.ID = c.user_id
            WHERE {$date_condition}
            GROUP BY u.ID, u.display_name
            ORDER BY total_cases DESC
            LIMIT 20
        ");
        
        $performance = array();
        foreach ($results as $row) {
            $performance[] = array(
                'user_id' => intval($row->user_id),
                'display_name' => $row->display_name,
                'total_cases' => intval($row->total_cases),
                'completed_cases' => intval($row->completed_cases),
                'rejected_cases' => intval($row->rejected_cases),
                'avg_resolution_time' => round(floatval($row->avg_resolution_time), 2),
                'completion_rate' => $row->total_cases > 0 ? round(($row->completed_cases / $row->total_cases) * 100, 2) : 0
            );
        }
        
        return $performance;
    }
    
    /**
     * Get resolution time analysis
     */
    public function get_resolution_times($period = 30) {
        global $wpdb;
        
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $date_condition = $this->get_date_condition($period);
        
        $results = $wpdb->get_results("
            SELECT 
                TIMESTAMPDIFF(HOUR, created_at, updated_at) as resolution_hours
            FROM {$table_cases} 
            WHERE status = 'completed' AND {$date_condition}
            ORDER BY resolution_hours ASC
        ");
        
        $times = array_map(function($row) {
            return floatval($row->resolution_hours);
        }, $results);
        
        if (empty($times)) {
            return array(
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'median' => 0,
                'distribution' => array()
            );
        }
        
        sort($times);
        $count = count($times);
        
        // Calculate distribution
        $distribution = array(
            '0-24h' => 0,
            '24-48h' => 0,
            '48-72h' => 0,
            '3-7d' => 0,
            '7d+' => 0
        );
        
        foreach ($times as $time) {
            if ($time <= 24) {
                $distribution['0-24h']++;
            } elseif ($time <= 48) {
                $distribution['24-48h']++;
            } elseif ($time <= 72) {
                $distribution['48-72h']++;
            } elseif ($time <= 168) { // 7 days
                $distribution['3-7d']++;
            } else {
                $distribution['7d+']++;
            }
        }
        
        return array(
            'min' => round(min($times), 2),
            'max' => round(max($times), 2),
            'avg' => round(array_sum($times) / $count, 2),
            'median' => round($times[floor($count / 2)], 2),
            'distribution' => $distribution
        );
    }
    
    /**
     * Get file statistics
     */
    public function get_file_statistics($period = 30) {
        global $wpdb;
        
        $table_files = $wpdb->prefix . 'cmp_files';
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $date_condition = $this->get_date_condition($period, 'c');
        
        // File type distribution
        $file_types = $wpdb->get_results("
            SELECT 
                SUBSTRING_INDEX(f.filename, '.', -1) as file_type,
                COUNT(*) as count,
                SUM(f.file_size) as total_size
            FROM {$table_files} f
            INNER JOIN {$table_cases} c ON f.case_id = c.id
            WHERE {$date_condition}
            GROUP BY file_type
            ORDER BY count DESC
        ");
        
        // Cloud provider distribution
        $cloud_providers = $wpdb->get_results("
            SELECT 
                f.storage_provider as cloud_provider,
                COUNT(*) as count,
                SUM(f.file_size) as total_size
            FROM {$table_files} f
            INNER JOIN {$table_cases} c ON f.case_id = c.id
            WHERE {$date_condition}
            GROUP BY f.storage_provider
            ORDER BY count DESC
        ");
        
        return array(
            'file_types' => $file_types,
            'cloud_providers' => $cloud_providers
        );
    }
    
    /**
     * Get status distribution
     */
    public function get_status_distribution($period = 30) {
        global $wpdb;
        
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $date_condition = $this->get_date_condition($period);
        
        $results = $wpdb->get_results("
            SELECT 
                status,
                COUNT(*) as count
            FROM {$table_cases} 
            WHERE {$date_condition}
            GROUP BY status
            ORDER BY count DESC
        ");
        
        $distribution = array();
        foreach ($results as $row) {
            $distribution[] = array(
                'status' => $row->status,
                'count' => intval($row->count)
            );
        }
        
        return $distribution;
    }
    
    /**
     * Export report
     */
    public function export_report() {
        check_ajax_referer('cmp_admin_nonce', 'nonce');
        
        if (!current_user_can('cmp_view_case_analytics')) {
            wp_die(__('Unauthorized', 'case-manager-pro'), 403);
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $report_type = sanitize_text_field($_POST['report_type'] ?? 'overview');
        
        switch ($report_type) {
            case 'overview':
                $data = $this->get_overview_stats($period);
                break;
            case 'user_performance':
                $data = $this->get_user_performance($period);
                break;
            case 'resolution_times':
                $data = $this->get_resolution_times($period);
                break;
            default:
                wp_send_json_error(__('Invalid report type', 'case-manager-pro'));
                return;
        }
        
        if ($format === 'csv') {
            $csv_data = $this->generate_csv($data, $report_type);
            wp_send_json_success(array(
                'csv_data' => $csv_data,
                'filename' => 'cmp_report_' . $report_type . '_' . date('Y-m-d') . '.csv'
            ));
        } else {
            wp_send_json_error(__('Unsupported format', 'case-manager-pro'));
        }
    }
    
    /**
     * Generate CSV data
     */
    private function generate_csv($data, $report_type) {
        $csv = '';
        
        switch ($report_type) {
            case 'overview':
                $csv .= "Metric,Value\n";
                foreach ($data as $key => $value) {
                    $csv .= '"' . ucwords(str_replace('_', ' ', $key)) . '","' . $value . '"' . "\n";
                }
                break;
                
            case 'user_performance':
                $csv .= "User,Total Cases,Completed,Rejected,Avg Resolution Time (hours),Completion Rate (%)\n";
                foreach ($data as $row) {
                    $csv .= '"' . $row['display_name'] . '",' . $row['total_cases'] . ',' . $row['completed_cases'] . ',' . $row['rejected_cases'] . ',' . $row['avg_resolution_time'] . ',' . $row['completion_rate'] . "\n";
                }
                break;
                
            case 'resolution_times':
                $csv .= "Metric,Value\n";
                $csv .= '"Minimum (hours)","' . $data['min'] . '"' . "\n";
                $csv .= '"Maximum (hours)","' . $data['max'] . '"' . "\n";
                $csv .= '"Average (hours)","' . $data['avg'] . '"' . "\n";
                $csv .= '"Median (hours)","' . $data['median'] . '"' . "\n";
                $csv .= "\nDistribution,Count\n";
                foreach ($data['distribution'] as $range => $count) {
                    $csv .= '"' . $range . '",' . $count . "\n";
                }
                break;
        }
        
        return $csv;
    }
    
    /**
     * Get date condition for SQL queries
     */
    private function get_date_condition($period, $table_alias = '') {
        $table_prefix = $table_alias ? $table_alias . '.' : '';
        
        switch ($period) {
            case '7':
                return "{$table_prefix}created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30':
                return "{$table_prefix}created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90':
                return "{$table_prefix}created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case '365':
                return "{$table_prefix}created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            case 'all':
                return "1=1";
            default:
                return "{$table_prefix}created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    /**
     * Get dashboard widget data
     */
    public function get_dashboard_widget_data() {
        $overview = $this->get_overview_stats(30);
        $trends = $this->get_case_trends(7); // Last 7 days for widget
        
        return array(
            'overview' => $overview,
            'recent_trends' => array_slice($trends, -7) // Last 7 days
        );
    }
} 