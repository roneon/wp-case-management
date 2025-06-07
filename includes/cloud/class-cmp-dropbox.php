<?php
/**
 * Dropbox Cloud Storage Provider
 *
 * @package CaseManagerPro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Dropbox {
    
    private $access_token;
    private $app_key;
    private $app_secret;
    private $folder_path;
    
    public function __construct() {
        $this->access_token = get_option('cmp_dropbox_access_token', '');
        $this->app_key = get_option('cmp_dropbox_app_key', '');
        $this->app_secret = get_option('cmp_dropbox_app_secret', '');
        $this->folder_path = get_option('cmp_dropbox_folder_path', '/CaseManagerPro');
    }
    
    /**
     * Test connection to Dropbox
     */
    public function test_connection() {
        try {
            if (!$this->access_token) {
                return new WP_Error('no_token', __('No access token configured', 'case-manager-pro'));
            }
            
            $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => 'null',
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (wp_remote_retrieve_response_code($response) === 200 && isset($data['name'])) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('Connected successfully as %s', 'case-manager-pro'), $data['name']['display_name'])
                );
            }
            
            return new WP_Error('connection_failed', __('Failed to connect to Dropbox', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Upload file to Dropbox
     */
    public function upload_file($file_path, $file_name, $case_id) {
        try {
            if (!$this->access_token) {
                return new WP_Error('no_token', __('No access token configured', 'case-manager-pro'));
            }
            
            // Create case folder path
            $case_folder = $this->folder_path . '/Case-' . $case_id;
            $this->create_case_folder($case_folder);
            
            $dropbox_path = $case_folder . '/' . $file_name;
            $file_content = file_get_contents($file_path);
            $file_size = filesize($file_path);
            
            // For files larger than 150MB, use upload session
            if ($file_size > 150 * 1024 * 1024) {
                return $this->upload_large_file($file_content, $dropbox_path);
            }
            
            // Regular upload for smaller files
            $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-API-Arg' => json_encode(array(
                        'path' => $dropbox_path,
                        'mode' => 'add',
                        'autorename' => true
                    ))
                ),
                'body' => $file_content,
                'timeout' => 300
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (wp_remote_retrieve_response_code($response) === 200 && isset($data['id'])) {
                // Get shareable link
                $share_link = $this->create_shared_link($data['path_lower']);
                
                return array(
                    'file_id' => $data['id'],
                    'file_url' => $share_link,
                    'download_url' => $this->get_download_url($data['path_lower'])
                );
            }
            
            return new WP_Error('upload_failed', __('Failed to upload file to Dropbox', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Delete file from Dropbox
     */
    public function delete_file($file_path) {
        try {
            if (!$this->access_token) {
                return new WP_Error('no_token', __('No access token configured', 'case-manager-pro'));
            }
            
            $response = wp_remote_post('https://api.dropboxapi.com/2/files/delete_v2', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'path' => $file_path
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (wp_remote_retrieve_response_code($response) === 200) {
                return true;
            }
            
            return new WP_Error('delete_failed', __('Failed to delete file from Dropbox', 'case-manager-pro'));
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Get download URL for file
     */
    public function get_download_url($file_path) {
        if (!$this->access_token) {
            return false;
        }
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/get_temporary_link', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $file_path
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['link'])) {
            return $data['link'];
        }
        
        return false;
    }
    
    /**
     * Create case folder in Dropbox
     */
    private function create_case_folder($folder_path) {
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/create_folder_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $folder_path,
                'autorename' => false
            )),
            'timeout' => 30
        ));
        
        // Folder might already exist, which is fine
        return !is_wp_error($response);
    }
    
    /**
     * Create shared link for file
     */
    private function create_shared_link($file_path) {
        $response = wp_remote_post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $file_path,
                'settings' => array(
                    'requested_visibility' => 'public'
                )
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['url'])) {
            return $data['url'];
        }
        
        return '';
    }
    
    /**
     * Upload large file using upload session
     */
    private function upload_large_file($file_content, $dropbox_path) {
        $chunk_size = 8 * 1024 * 1024; // 8MB chunks
        $file_size = strlen($file_content);
        $offset = 0;
        $session_id = null;
        
        while ($offset < $file_size) {
            $chunk = substr($file_content, $offset, $chunk_size);
            $is_last_chunk = ($offset + strlen($chunk)) >= $file_size;
            
            if ($session_id === null) {
                // Start upload session
                $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/start', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/octet-stream',
                        'Dropbox-API-Arg' => json_encode(array(
                            'close' => false
                        ))
                    ),
                    'body' => $chunk,
                    'timeout' => 300
                ));
                
                if (is_wp_error($response)) {
                    return $response;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!isset($data['session_id'])) {
                    return new WP_Error('session_failed', __('Failed to start upload session', 'case-manager-pro'));
                }
                
                $session_id = $data['session_id'];
            } else {
                if ($is_last_chunk) {
                    // Finish upload session
                    $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/finish', array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $this->access_token,
                            'Content-Type' => 'application/octet-stream',
                            'Dropbox-API-Arg' => json_encode(array(
                                'cursor' => array(
                                    'session_id' => $session_id,
                                    'offset' => $offset
                                ),
                                'commit' => array(
                                    'path' => $dropbox_path,
                                    'mode' => 'add',
                                    'autorename' => true
                                )
                            ))
                        ),
                        'body' => $chunk,
                        'timeout' => 300
                    ));
                } else {
                    // Append to upload session
                    $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/append_v2', array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $this->access_token,
                            'Content-Type' => 'application/octet-stream',
                            'Dropbox-API-Arg' => json_encode(array(
                                'cursor' => array(
                                    'session_id' => $session_id,
                                    'offset' => $offset
                                ),
                                'close' => false
                            ))
                        ),
                        'body' => $chunk,
                        'timeout' => 300
                    ));
                }
                
                if (is_wp_error($response)) {
                    return $response;
                }
            }
            
            $offset += strlen($chunk);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['id'])) {
            $share_link = $this->create_shared_link($data['path_lower']);
            
            return array(
                'file_id' => $data['id'],
                'file_url' => $share_link,
                'download_url' => $this->get_download_url($data['path_lower'])
            );
        }
        
        return new WP_Error('upload_failed', __('Failed to upload large file to Dropbox', 'case-manager-pro'));
    }
} 