<?php
/**
 * Amazon S3 cloud storage provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Amazon_S3 {
    
    private $access_key;
    private $secret_key;
    private $bucket;
    private $region;
    
    public function __construct() {
        $this->access_key = get_option('cmp_s3_access_key', '');
        $this->secret_key = get_option('cmp_s3_secret_key', '');
        $this->bucket = get_option('cmp_s3_bucket', '');
        $this->region = get_option('cmp_s3_region', 'us-east-1');
    }
    
    public function is_configured() {
        return !empty($this->access_key) && 
               !empty($this->secret_key) && 
               !empty($this->bucket);
    }
    
    public function upload_file($file_path, $filename, $case_id) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Amazon S3 is not properly configured', 'case-manager-pro'));
        }
        
        $key = 'cases/' . $case_id . '/' . $filename;
        
        try {
            // Use WordPress HTTP API for S3 upload
            $file_content = file_get_contents($file_path);
            $content_type = mime_content_type($file_path);
            
            $url = $this->get_s3_url($key);
            $headers = $this->get_auth_headers('PUT', $key, $content_type, $file_content);
            
            $response = wp_remote_request($url, array(
                'method' => 'PUT',
                'headers' => $headers,
                'body' => $file_content,
                'timeout' => 300
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                return new WP_Error('upload_failed', sprintf(
                    __('S3 upload failed with status code: %d', 'case-manager-pro'),
                    $response_code
                ));
            }
            
            return array(
                'path' => $key,
                'download_url' => $this->get_download_url($key)
            );
            
        } catch (Exception $e) {
            return new WP_Error('upload_error', $e->getMessage());
        }
    }
    
    public function delete_file($file_path) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Amazon S3 is not properly configured', 'case-manager-pro'));
        }
        
        try {
            $url = $this->get_s3_url($file_path);
            $headers = $this->get_auth_headers('DELETE', $file_path);
            
            $response = wp_remote_request($url, array(
                'method' => 'DELETE',
                'headers' => $headers,
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 204 && $response_code !== 200) {
                return new WP_Error('delete_failed', sprintf(
                    __('S3 delete failed with status code: %d', 'case-manager-pro'),
                    $response_code
                ));
            }
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('delete_error', $e->getMessage());
        }
    }
    
    public function get_download_url($file_path, $expires = 3600) {
        if (!$this->is_configured()) {
            return '';
        }
        
        // Generate presigned URL for secure download
        $expiration = time() + $expires;
        $url = $this->get_s3_url($file_path);
        
        $string_to_sign = "GET\n\n\n{$expiration}\n/{$this->bucket}/{$file_path}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->secret_key, true));
        
        return $url . '?' . http_build_query(array(
            'AWSAccessKeyId' => $this->access_key,
            'Expires' => $expiration,
            'Signature' => $signature
        ));
    }
    
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Amazon S3 is not properly configured', 'case-manager-pro'));
        }
        
        try {
            $url = $this->get_s3_url('');
            $headers = $this->get_auth_headers('GET', '');
            
            $response = wp_remote_get($url, array(
                'headers' => $headers,
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                return true;
            } else {
                return new WP_Error('connection_failed', sprintf(
                    __('S3 connection test failed with status code: %d', 'case-manager-pro'),
                    $response_code
                ));
            }
            
        } catch (Exception $e) {
            return new WP_Error('connection_error', $e->getMessage());
        }
    }
    
    public function get_storage_usage() {
        // Note: Getting exact storage usage requires CloudWatch API
        // For now, return estimated usage based on database records
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cmp_case_files';
        $total_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(file_size) FROM {$table_name} WHERE cloud_provider = %s",
            'amazon_s3'
        ));
        
        return array(
            'used' => $total_size ?: 0,
            'total' => 0, // S3 doesn't have a fixed limit
            'percentage' => 0
        );
    }
    
    public function get_settings_fields() {
        return array(
            'cmp_s3_access_key' => array(
                'title' => __('Access Key ID', 'case-manager-pro'),
                'type' => 'text',
                'description' => __('Your AWS Access Key ID', 'case-manager-pro'),
                'required' => true
            ),
            'cmp_s3_secret_key' => array(
                'title' => __('Secret Access Key', 'case-manager-pro'),
                'type' => 'password',
                'description' => __('Your AWS Secret Access Key', 'case-manager-pro'),
                'required' => true
            ),
            'cmp_s3_bucket' => array(
                'title' => __('Bucket Name', 'case-manager-pro'),
                'type' => 'text',
                'description' => __('The S3 bucket name where files will be stored', 'case-manager-pro'),
                'required' => true
            ),
            'cmp_s3_region' => array(
                'title' => __('Region', 'case-manager-pro'),
                'type' => 'select',
                'description' => __('The AWS region where your bucket is located', 'case-manager-pro'),
                'options' => array(
                    'us-east-1' => 'US East (N. Virginia)',
                    'us-east-2' => 'US East (Ohio)',
                    'us-west-1' => 'US West (N. California)',
                    'us-west-2' => 'US West (Oregon)',
                    'eu-west-1' => 'Europe (Ireland)',
                    'eu-west-2' => 'Europe (London)',
                    'eu-west-3' => 'Europe (Paris)',
                    'eu-central-1' => 'Europe (Frankfurt)',
                    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                    'ap-northeast-2' => 'Asia Pacific (Seoul)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                    'ap-southeast-2' => 'Asia Pacific (Sydney)',
                    'ap-south-1' => 'Asia Pacific (Mumbai)',
                    'sa-east-1' => 'South America (São Paulo)'
                ),
                'default' => 'us-east-1'
            )
        );
    }
    
    private function get_s3_url($key = '') {
        $host = $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
        return 'https://' . $host . '/' . $key;
    }
    
    private function get_auth_headers($method, $key, $content_type = '', $content = '') {
        $date = gmdate('D, d M Y H:i:s T');
        $content_md5 = $content ? base64_encode(md5($content, true)) : '';
        
        $string_to_sign = $method . "\n" .
                         $content_md5 . "\n" .
                         $content_type . "\n" .
                         $date . "\n" .
                         '/' . $this->bucket . '/' . $key;
        
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->secret_key, true));
        
        $headers = array(
            'Date' => $date,
            'Authorization' => 'AWS ' . $this->access_key . ':' . $signature
        );
        
        if ($content_type) {
            $headers['Content-Type'] = $content_type;
        }
        
        if ($content_md5) {
            $headers['Content-MD5'] = $content_md5;
        }
        
        return $headers;
    }
} 