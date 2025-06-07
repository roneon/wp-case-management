<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Cases table
        $table_cases = $wpdb->prefix . 'cmp_cases';
        $sql_cases = "CREATE TABLE $table_cases (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            priority varchar(20) DEFAULT 'medium',
            status varchar(50) DEFAULT 'pending',
            result longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY priority (priority),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Case files table
        $table_files = $wpdb->prefix . 'cmp_case_files';
        $sql_files = "CREATE TABLE $table_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            case_id mediumint(9) NOT NULL,
            filename varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_size bigint(20) DEFAULT 0,
            file_type varchar(100),
            cloud_path varchar(500),
            cloud_provider varchar(50),
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY case_id (case_id),
            KEY cloud_provider (cloud_provider)
        ) $charset_collate;";
        
        // Files table (for cloud storage compatibility)
        $table_cmp_files = $wpdb->prefix . 'cmp_files';
        $sql_cmp_files = "CREATE TABLE $table_cmp_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            case_id mediumint(9) NOT NULL,
            original_filename varchar(255) NOT NULL,
            stored_filename varchar(255) NOT NULL,
            filename varchar(255) NOT NULL,
            file_path varchar(500),
            file_url varchar(500),
            file_size bigint(20) DEFAULT 0,
            mime_type varchar(100),
            attachment_id bigint(20),
            storage_provider varchar(50) DEFAULT 'local',
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            uploaded_by bigint(20),
            is_temporary tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY case_id (case_id),
            KEY storage_provider (storage_provider),
            KEY attachment_id (attachment_id),
            KEY uploaded_by (uploaded_by),
            KEY is_temporary (is_temporary)
        ) $charset_collate;";
        
        // Comments table
        $table_comments = $wpdb->prefix . 'cmp_case_comments';
        $sql_comments = "CREATE TABLE $table_comments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            case_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            comment longtext NOT NULL,
            is_private tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY case_id (case_id),
            KEY user_id (user_id),
            KEY is_private (is_private)
        ) $charset_collate;";
        
        // Notifications table
        $table_notifications = $wpdb->prefix . 'cmp_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            case_id mediumint(9),
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message longtext,
            url varchar(500),
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY case_id (case_id),
            KEY is_read (is_read),
            KEY type (type)
        ) $charset_collate;";
        
        // Activity log table
        $table_activity = $wpdb->prefix . 'cmp_activity_log';
        $sql_activity = "CREATE TABLE $table_activity (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            case_id mediumint(9),
            action varchar(100) NOT NULL,
            description longtext,
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY case_id (case_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_cases);
        dbDelta($sql_files);
        dbDelta($sql_cmp_files);
        dbDelta($sql_comments);
        dbDelta($sql_notifications);
        dbDelta($sql_activity);
        
        // Update database version
        update_option('cmp_db_version', CMP_VERSION);
        
        // Run migrations if needed
        self::run_migrations();
    }
    
    public static function run_migrations() {
        global $wpdb;
        
        $current_version = get_option('cmp_db_version', '0.0.0');
        
        // Migration for version 1.0.0 - Add priority column
        if (version_compare($current_version, '1.0.0', '<')) {
            $table_cases = $wpdb->prefix . 'cmp_cases';
            
            // Check if priority column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_cases} LIKE 'priority'");
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_cases} ADD COLUMN priority varchar(20) DEFAULT 'medium' AFTER description");
                $wpdb->query("ALTER TABLE {$table_cases} ADD INDEX priority (priority)");
            }
        }
        
        // Migration for version 1.0.1 - Add url column to notifications
        if (version_compare($current_version, '1.0.1', '<')) {
            $table_notifications = $wpdb->prefix . 'cmp_notifications';
            
            // Check if url column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_notifications} LIKE 'url'");
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_notifications} ADD COLUMN url varchar(500) AFTER message");
            }
        }
        
        // Update version after migrations
        update_option('cmp_db_version', CMP_VERSION);
    }
    
    public function get_cases($user_id = null, $status = null, $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        $where_clauses = array();
        $values = array();
        
        if ($user_id) {
            $where_clauses[] = 'user_id = %d';
            $values[] = $user_id;
        }
        
        if ($status) {
            $where_clauses[] = 'status = %s';
            $values[] = $status;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $values[] = $limit;
        $values[] = $offset;
        
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    public function get_case($case_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $case_id
        ));
    }
    
    public function create_case($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        
        $retention_days = get_option('cmp_file_retention_days', 30);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));
        
        $defaults = array(
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_case($case_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $case_id)
        );
    }
    
    public function delete_case($case_id) {
        global $wpdb;
        
        // Delete case files first
        $this->delete_case_files($case_id);
        
        // Delete comments
        $wpdb->delete(
            $wpdb->prefix . 'cmp_case_comments',
            array('case_id' => $case_id)
        );
        
        // Delete notifications
        $wpdb->delete(
            $wpdb->prefix . 'cmp_notifications',
            array('case_id' => $case_id)
        );
        
        // Delete activity log
        $wpdb->delete(
            $wpdb->prefix . 'cmp_activity_log',
            array('case_id' => $case_id)
        );
        
        // Delete case
        return $wpdb->delete(
            $wpdb->prefix . 'cmp_cases',
            array('id' => $case_id)
        );
    }
    
    public function get_case_files($case_id) {
        global $wpdb;
        
        // Önce cmp_case_files tablosundan al (yeni sistem)
        $case_files_table = $wpdb->prefix . 'cmp_case_files';
        $case_files = $wpdb->get_results($wpdb->prepare(
            "SELECT *, 'case_files' as source_table FROM {$case_files_table} WHERE case_id = %d ORDER BY uploaded_at DESC",
            $case_id
        ));
        
        // Sonra cmp_files tablosundan al (eski sistem/local storage)
        $files_table = $wpdb->prefix . 'cmp_files';
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT *, 'files' as source_table FROM {$files_table} WHERE case_id = %d ORDER BY uploaded_at DESC",
            $case_id
        ));
        
        // İki tablodan gelen dosyaları birleştir
        $all_files = array_merge($case_files, $files);
        
        // Dosyaları upload tarihine göre sırala
        usort($all_files, function($a, $b) {
            return strtotime($b->uploaded_at) - strtotime($a->uploaded_at);
        });
        
        // Dosya bilgilerini normalize et
        foreach ($all_files as &$file) {
            // Eğer original_filename yoksa filename kullan
            if (!isset($file->original_filename) || empty($file->original_filename)) {
                $file->original_filename = $file->filename;
            }
            
            // Eğer file_url yoksa ve attachment_id varsa WordPress URL'ini al
            if (!isset($file->file_url) && isset($file->attachment_id)) {
                $file->file_url = wp_get_attachment_url($file->attachment_id);
            }
        }
        
        return $all_files;
    }
    
    public function add_case_file($case_id, $file_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_case_files';
        
        $defaults = array(
            'case_id' => $case_id,
            'uploaded_at' => current_time('mysql')
        );
        
        $file_data = wp_parse_args($file_data, $defaults);
        
        $result = $wpdb->insert($table_name, $file_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public function delete_case_files($case_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'cmp_case_files',
            array('case_id' => $case_id)
        );
    }
    
    public function get_case_comments($case_id, $include_private = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_case_comments';
        $users_table = $wpdb->users;
        
        $where_private = $include_private ? '' : 'AND c.is_private = 0';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name, u.user_email 
             FROM {$table_name} c 
             LEFT JOIN {$users_table} u ON c.user_id = u.ID 
             WHERE c.case_id = %d {$where_private}
             ORDER BY c.created_at ASC",
            $case_id
        ));
    }
    
    public function add_case_comment($case_id, $user_id, $comment, $is_private = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_case_comments';
        
        return $wpdb->insert($table_name, array(
            'case_id' => $case_id,
            'user_id' => $user_id,
            'comment' => $comment,
            'is_private' => $is_private ? 1 : 0,
            'created_at' => current_time('mysql')
        ));
    }
    
    public function log_activity($user_id, $case_id, $action, $description = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_activity_log';
        
        // Null değerleri güvenli hale getir
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        return $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'case_id' => $case_id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Get user case count
     */
    public function get_user_case_count($user_id, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        $where_clauses = array('user_id = %d');
        $values = array($user_id);
        
        if ($status) {
            $where_clauses[] = 'status = %s';
            $values[] = $status;
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
        
        return $wpdb->get_var($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get user cases with pagination
     */
    public function get_user_cases($user_id, $page = 1, $per_page = 20, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_cases';
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = array('user_id = %d');
        $values = array($user_id);
        
        if ($status) {
            $where_clauses[] = 'status = %s';
            $values[] = $status;
        }
        
        $values[] = $per_page;
        $values[] = $offset;
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    public static function upgrade_tables() {
        global $wpdb;
        
        // cmp_files tablosuna yeni kolonları ekle
        $table_files = $wpdb->prefix . 'cmp_files';
        
        // original_filename kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'original_filename'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN original_filename varchar(255) NOT NULL DEFAULT '' AFTER case_id");
        }
        
        // stored_filename kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'stored_filename'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN stored_filename varchar(255) NOT NULL DEFAULT '' AFTER original_filename");
        }
        
        // file_url kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'file_url'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN file_url varchar(500) AFTER file_path");
        }
        
        // mime_type kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'mime_type'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN mime_type varchar(100) AFTER file_size");
        }
        
        // attachment_id kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'attachment_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN attachment_id bigint(20) AFTER mime_type");
            $wpdb->query("ALTER TABLE {$table_files} ADD INDEX attachment_id (attachment_id)");
        }
        
        // uploaded_by kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'uploaded_by'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN uploaded_by bigint(20) AFTER uploaded_at");
            $wpdb->query("ALTER TABLE {$table_files} ADD INDEX uploaded_by (uploaded_by)");
        }
        
        // is_temporary kolonu var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_files} LIKE 'is_temporary'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_files} ADD COLUMN is_temporary tinyint(1) DEFAULT 0 AFTER uploaded_by");
            $wpdb->query("ALTER TABLE {$table_files} ADD INDEX is_temporary (is_temporary)");
        }
    }
} 