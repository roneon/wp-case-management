<?php
/**
 * Google Drive Cloud Storage Provider
 *
 * @package CaseManagerPro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Google_Drive {
    
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token;
    private $folder_id;
    
    public function __construct() {
        $this->client_id = get_option('cmp_google_drive_client_id', '');
        $this->client_secret = get_option('cmp_google_drive_client_secret', '');
        $this->refresh_token = get_option('cmp_google_drive_refresh_token', '');
        $this->folder_id = get_option('cmp_google_drive_folder_id', '');
    }
    
    /**
     * Test connection to Google Drive
     */
    public function test_connection() {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return new WP_Error('auth_failed', __('Failed to get access token', 'case-manager-pro'));
            }
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (wp_remote_retrieve_response_code($response) === 200 && isset($data['user'])) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('Connected successfully as %s', 'case-manager-pro'), $data['user']['displayName'])
                );
            }
            
            return new WP_Error('connection_failed', __('Failed to connect to Google Drive', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Upload file to Google Drive
     */
    public function upload_file($file_path, $file_name, $case_id) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return new WP_Error('auth_failed', __('Failed to get access token', 'case-manager-pro'));
            }
            
            // Create case folder if it doesn't exist
            $case_folder_id = $this->create_case_folder($case_id, $access_token);
            if (is_wp_error($case_folder_id)) {
                return $case_folder_id;
            }
            
            // Upload file
            $boundary = wp_generate_uuid4();
            $metadata = array(
                'name' => $file_name,
                'parents' => array($case_folder_id)
            );
            
            $file_content = file_get_contents($file_path);
            $mime_type = wp_check_filetype($file_name)['type'] ?: 'application/octet-stream';
            
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
            $body .= json_encode($metadata) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$mime_type}\r\n\r\n";
            $body .= $file_content . "\r\n";
            $body .= "--{$boundary}--";
            
            $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'multipart/related; boundary=' . $boundary
                ),
                'body' => $body,
                'timeout' => 300
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (wp_remote_retrieve_response_code($response) === 200 && isset($data['id'])) {
                return array(
                    'file_id' => $data['id'],
                    'file_url' => 'https://drive.google.com/file/d/' . $data['id'] . '/view',
                    'download_url' => $this->get_download_url($data['id'])
                );
            }
            
            return new WP_Error('upload_failed', __('Failed to upload file to Google Drive', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Delete file from Google Drive
     */
    public function delete_file($file_id) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return new WP_Error('auth_failed', __('Failed to get access token', 'case-manager-pro'));
            }
            
            $response = wp_remote_request('https://www.googleapis.com/drive/v3/files/' . $file_id, array(
                'method' => 'DELETE',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (wp_remote_retrieve_response_code($response) === 204) {
                return true;
            }
            
            return new WP_Error('delete_failed', __('Failed to delete file from Google Drive', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Get download URL for file
     */
    public function get_download_url($file_id) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        return add_query_arg(array(
            'access_token' => $access_token
        ), 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media');
    }
    
    /**
     * Get access token using refresh token
     */
    private function get_access_token() {
        if ($this->access_token && $this->is_token_valid()) {
            return $this->access_token;
        }
        
        if (!$this->refresh_token) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            set_transient('cmp_google_drive_access_token', $this->access_token, $data['expires_in'] - 60);
            return $this->access_token;
        }
        
        return false;
    }
    
    /**
     * Check if access token is valid
     */
    private function is_token_valid() {
        return get_transient('cmp_google_drive_access_token') !== false;
    }
    
    /**
     * Create case folder in Google Drive
     */
    private function create_case_folder($case_id, $access_token) {
        $folder_name = 'Case-' . $case_id;
        
        // Check if folder already exists
        $search_response = wp_remote_get(
            'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => "name='{$folder_name}' and parents in '{$this->folder_id}' and mimeType='application/vnd.google-apps.folder'",
                'fields' => 'files(id,name)'
            )),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                ),
                'timeout' => 30
            )
        );
        
        if (!is_wp_error($search_response)) {
            $search_body = wp_remote_retrieve_body($search_response);
            $search_data = json_decode($search_body, true);
            
            if (isset($search_data['files']) && !empty($search_data['files'])) {
                return $search_data['files'][0]['id'];
            }
        }
        
        // Create new folder
        $metadata = array(
            'name' => $folder_name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => array($this->folder_id)
        );
        
        $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($metadata),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['id'])) {
            return $data['id'];
        }
        
        return new WP_Error('folder_creation_failed', __('Failed to create case folder in Google Drive', 'case-manager-pro'));
    }
} 