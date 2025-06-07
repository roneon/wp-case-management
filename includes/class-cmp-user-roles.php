<?php
/**
 * User roles management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_User_Roles {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Add custom capabilities to existing roles
        $this->add_capabilities();
    }
    
    public static function create_roles() {
        // Case Submitter Role
        add_role('case_submitter', __('Case Submitter', 'case-manager-pro'), array(
            'read' => true,
            'cmp_submit_case' => true,
            'cmp_view_own_cases' => true,
            'cmp_edit_own_cases' => true,
            'cmp_delete_own_cases' => true,
            'cmp_upload_files' => true,
            'cmp_view_case_results' => true
        ));
        
        // Case Reviewer Role
        add_role('case_reviewer', __('Case Reviewer', 'case-manager-pro'), array(
            'read' => true,
            'cmp_view_all_cases' => true,
            'cmp_review_cases' => true,
            'cmp_comment_cases' => true,
            'cmp_view_private_comments' => true,
            'cmp_download_files' => true,
            'cmp_set_case_results' => true,
            'cmp_view_case_analytics' => true
        ));
        
        // Case Manager Role (Admin level)
        add_role('case_manager', __('Case Manager', 'case-manager-pro'), array(
            'read' => true,
            'cmp_view_all_cases' => true,
            'cmp_edit_all_cases' => true,
            'cmp_delete_all_cases' => true,
            'cmp_review_cases' => true,
            'cmp_comment_cases' => true,
            'cmp_view_private_comments' => true,
            'cmp_download_files' => true,
            'cmp_set_case_results' => true,
            'cmp_manage_settings' => true,
            'cmp_view_case_analytics' => true,
            'cmp_manage_users' => true,
            'cmp_export_data' => true
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'cmp_submit_case',
                'cmp_view_own_cases',
                'cmp_edit_own_cases',
                'cmp_delete_own_cases',
                'cmp_upload_files',
                'cmp_view_case_results',
                'cmp_view_all_cases',
                'cmp_edit_all_cases',
                'cmp_delete_all_cases',
                'cmp_review_cases',
                'cmp_comment_cases',
                'cmp_view_private_comments',
                'cmp_download_files',
                'cmp_set_case_results',
                'cmp_manage_settings',
                'cmp_view_case_analytics',
                'cmp_manage_users',
                'cmp_export_data'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    private function add_capabilities() {
        // Add capabilities to editor role
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_capabilities = array(
                'cmp_view_all_cases',
                'cmp_edit_all_cases',
                'cmp_review_cases',
                'cmp_comment_cases',
                'cmp_view_private_comments',
                'cmp_download_files',
                'cmp_set_case_results',
                'cmp_view_case_analytics'
            );
            
            foreach ($editor_capabilities as $cap) {
                $editor_role->add_cap($cap);
            }
        }
    }
    
    public static function remove_roles() {
        remove_role('case_submitter');
        remove_role('case_reviewer');
        remove_role('case_manager');
        
        // Remove capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'cmp_submit_case',
                'cmp_view_own_cases',
                'cmp_edit_own_cases',
                'cmp_delete_own_cases',
                'cmp_upload_files',
                'cmp_view_case_results',
                'cmp_view_all_cases',
                'cmp_edit_all_cases',
                'cmp_delete_all_cases',
                'cmp_review_cases',
                'cmp_comment_cases',
                'cmp_view_private_comments',
                'cmp_download_files',
                'cmp_set_case_results',
                'cmp_manage_settings',
                'cmp_view_case_analytics',
                'cmp_manage_users',
                'cmp_export_data'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
        
        // Remove capabilities from editor
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_capabilities = array(
                'cmp_view_all_cases',
                'cmp_edit_all_cases',
                'cmp_review_cases',
                'cmp_comment_cases',
                'cmp_view_private_comments',
                'cmp_download_files',
                'cmp_set_case_results',
                'cmp_view_case_analytics'
            );
            
            foreach ($editor_capabilities as $cap) {
                $editor_role->remove_cap($cap);
            }
        }
    }
    
    public function user_can_view_case($user_id, $case) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Case owner can always view their own cases
        if ($case->user_id == $user_id) {
            return true;
        }
        
        // Users with view all cases capability
        if (user_can($user, 'cmp_view_all_cases')) {
            return true;
        }
        
        return false;
    }
    
    public function user_can_edit_case($user_id, $case) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Case owner can edit their own cases if they have the capability
        if ($case->user_id == $user_id && user_can($user, 'cmp_edit_own_cases')) {
            return true;
        }
        
        // Users with edit all cases capability
        if (user_can($user, 'cmp_edit_all_cases')) {
            return true;
        }
        
        return false;
    }
    
    public function user_can_delete_case($user_id, $case) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Case owner can delete their own cases if they have the capability
        if ($case->user_id == $user_id && user_can($user, 'cmp_delete_own_cases')) {
            return true;
        }
        
        // Users with delete all cases capability
        if (user_can($user, 'cmp_delete_all_cases')) {
            return true;
        }
        
        return false;
    }
    
    public function user_can_comment_case($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return user_can($user, 'cmp_comment_cases');
    }
    
    public function user_can_view_private_comments($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return user_can($user, 'cmp_view_private_comments');
    }
    
    public function user_can_download_files($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return user_can($user, 'cmp_download_files');
    }
    
    public function user_can_set_results($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return user_can($user, 'cmp_set_case_results');
    }
    
    public function user_can_submit_cases($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return user_can($user, 'cmp_submit_case');
    }
    
    public function get_reviewers() {
        $reviewers = get_users(array(
            'role__in' => array('case_reviewer', 'case_manager', 'administrator', 'editor'),
            'meta_query' => array(
                array(
                    'key' => 'cmp_active_reviewer',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        // If no active reviewers found, get all users with review capabilities
        if (empty($reviewers)) {
            $reviewers = get_users(array(
                'role__in' => array('case_reviewer', 'case_manager', 'administrator', 'editor')
            ));
        }
        
        return $reviewers;
    }
    
    public function get_case_submitters() {
        return get_users(array(
            'role__in' => array('case_submitter', 'subscriber')
        ));
    }
} 