<?php
/**
 * Shortcodes management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CMP_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    public function register_shortcodes() {
        add_shortcode('cmp_case_list', array($this, 'case_list_shortcode'));
        add_shortcode('cmp_submit_case', array($this, 'submit_case_shortcode'));
        add_shortcode('cmp_case_details', array($this, 'case_details_shortcode'));
        add_shortcode('cmp_user_stats', array($this, 'user_stats_shortcode'));
    }
    
    public function case_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'status' => '',
            'limit' => 10,
            'show_pagination' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view cases.', 'case-manager-pro') . '</p>';
        }
        
        $db = CMP_Database::get_instance();
        $user_roles = CMP_User_Roles::get_instance();
        $current_user_id = get_current_user_id();
        
        // Check if user can view all cases or only their own
        if (current_user_can('cmp_view_all_cases')) {
            $user_id = $atts['user_id'] === 'all' ? null : intval($atts['user_id']);
        } else {
            $user_id = $current_user_id;
        }
        
        $cases = $db->get_cases($user_id, $atts['status'], intval($atts['limit']), 0);
        
        ob_start();
        ?>
        <div class="cmp-case-list">
            <?php if (empty($cases)): ?>
                <p class="cmp-no-cases"><?php _e('No cases found.', 'case-manager-pro'); ?></p>
            <?php else: ?>
                <div class="cmp-cases-grid">
                    <?php foreach ($cases as $case): ?>
                        <div class="cmp-case-card">
                            <div class="cmp-case-header">
                                <h3 class="cmp-case-title">
                                    <a href="?case_id=<?php echo $case->id; ?>">
                                        Case #<?php echo $case->id; ?>: <?php echo esc_html($case->title); ?>
                                    </a>
                                </h3>
                                <span class="cmp-case-status cmp-status-<?php echo $case->status; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                                </span>
                            </div>
                            <div class="cmp-case-content">
                                <p class="cmp-case-description">
                                    <?php echo esc_html(wp_trim_words($case->description, 20)); ?>
                                </p>
                                <div class="cmp-case-meta">
                                    <span class="cmp-case-date">
                                        <?php _e('Created:', 'case-manager-pro'); ?> 
                                        <?php echo date_i18n(get_option('date_format'), strtotime($case->created_at)); ?>
                                    </span>
                                    <?php if ($case->expires_at): ?>
                                        <span class="cmp-case-expires">
                                            <?php _e('Expires:', 'case-manager-pro'); ?> 
                                            <?php echo date_i18n(get_option('date_format'), strtotime($case->expires_at)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="cmp-case-actions">
                                <a href="?case_id=<?php echo $case->id; ?>" class="cmp-btn cmp-btn-primary">
                                    <?php _e('View Details', 'case-manager-pro'); ?>
                                </a>
                                <?php if ($user_roles->user_can_edit_case($current_user_id, $case)): ?>
                                    <a href="?case_id=<?php echo $case->id; ?>&action=edit" class="cmp-btn cmp-btn-secondary">
                                        <?php _e('Edit', 'case-manager-pro'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function submit_case_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => '',
            'show_title' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to submit a case.', 'case-manager-pro') . '</p>';
        }
        
        $user_roles = CMP_User_Roles::get_instance();
        if (!current_user_can('cmp_submit_case')) {
            return '<p>' . __('You do not have permission to submit cases.', 'case-manager-pro') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="cmp-submit-case-form">
            <?php if ($atts['show_title'] === 'true'): ?>
                <h3><?php _e('Submit New Case', 'case-manager-pro'); ?></h3>
            <?php endif; ?>
            
            <form id="cmp-case-submit-form" class="cmp-form" enctype="multipart/form-data">
                <?php wp_nonce_field('cmp_submit_case', 'cmp_case_nonce'); ?>
                
                <div class="cmp-form-row">
                    <div class="cmp-form-group">
                        <label for="case_title"><?php _e('Case Title', 'case-manager-pro'); ?> *</label>
                        <input type="text" id="case_title" name="case_title" required>
                    </div>
                </div>
                
                <div class="cmp-form-row">
                    <div class="cmp-form-group">
                        <label for="case_description"><?php _e('Description', 'case-manager-pro'); ?> *</label>
                        <textarea id="case_description" name="case_description" rows="6" required></textarea>
                    </div>
                </div>
                
                <div class="cmp-form-row">
                    <div class="cmp-form-group">
                        <label for="case_priority"><?php _e('Priority', 'case-manager-pro'); ?></label>
                        <select id="case_priority" name="case_priority">
                            <option value="low"><?php _e('Low', 'case-manager-pro'); ?></option>
                            <option value="medium" selected><?php _e('Medium', 'case-manager-pro'); ?></option>
                            <option value="high"><?php _e('High', 'case-manager-pro'); ?></option>
                            <option value="urgent"><?php _e('Urgent', 'case-manager-pro'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="cmp-form-row">
                    <div class="cmp-form-group">
                        <label for="case_files"><?php _e('Attach Files', 'case-manager-pro'); ?></label>
                        <input type="file" id="case_files" name="case_files[]" multiple>
                        <small class="cmp-form-help">
                            <?php 
                            $max_size = get_option('cmp_max_file_size', 2048);
                            $allowed_types = get_option('cmp_allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,zip,rar');
                            printf(__('Max file size: %dMB. Allowed types: %s', 'case-manager-pro'), $max_size, $allowed_types);
                            ?>
                        </small>
                    </div>
                </div>
                
                <div class="cmp-form-row">
                    <div class="cmp-form-actions">
                        <button type="submit" class="cmp-btn cmp-btn-primary">
                            <?php _e('Submit Case', 'case-manager-pro'); ?>
                        </button>
                        <button type="reset" class="cmp-btn cmp-btn-secondary">
                            <?php _e('Reset', 'case-manager-pro'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="cmp-form-messages"></div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#cmp-case-submit-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'cmp_submit_case');
                
                var $submitBtn = $(this).find('button[type="submit"]');
                var originalText = $submitBtn.text();
                
                $submitBtn.prop('disabled', true).text('<?php _e('Submitting...', 'case-manager-pro'); ?>');
                
                $.ajax({
                    url: cmp_frontend.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#cmp-form-messages').html('<div class="cmp-message cmp-message-success">' + response.data.message + '</div>');
                            $('#cmp-case-submit-form')[0].reset();
                            
                            <?php if ($atts['redirect_url']): ?>
                                setTimeout(function() {
                                    window.location.href = '<?php echo esc_url($atts['redirect_url']); ?>';
                                }, 2000);
                            <?php endif; ?>
                        } else {
                            $('#cmp-form-messages').html('<div class="cmp-message cmp-message-error">' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $('#cmp-form-messages').html('<div class="cmp-message cmp-message-error">' + cmp_frontend.strings.error + '</div>');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function case_details_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view case details.', 'case-manager-pro') . '</p>';
        }
        
        $case_id = intval($atts['id']);
        if (!$case_id && isset($_GET['case_id'])) {
            $case_id = intval($_GET['case_id']);
        }
        
        if (!$case_id) {
            return '<p>' . __('Case ID is required.', 'case-manager-pro') . '</p>';
        }
        
        $db = CMP_Database::get_instance();
        $case = $db->get_case($case_id);
        
        if (!$case) {
            return '<p>' . __('Case not found.', 'case-manager-pro') . '</p>';
        }
        
        $user_roles = CMP_User_Roles::get_instance();
        if (!$user_roles->user_can_view_case(get_current_user_id(), $case)) {
            return '<p>' . __('You do not have permission to view this case.', 'case-manager-pro') . '</p>';
        }
        
        $user = get_user_by('id', $case->user_id);
        $files = $db->get_case_files($case_id);
        $comments = $db->get_case_comments($case_id, $user_roles->user_can_view_private_comments(get_current_user_id()));
        
        ob_start();
        ?>
        <div class="cmp-case-details">
            <div class="cmp-case-header">
                <h2>Case #<?php echo $case->id; ?>: <?php echo esc_html($case->title); ?></h2>
                <span class="cmp-case-status cmp-status-<?php echo $case->status; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $case->status)); ?>
                </span>
            </div>
            
            <div class="cmp-case-info">
                <div class="cmp-case-meta">
                    <div class="cmp-meta-item">
                        <strong><?php _e('Submitter:', 'case-manager-pro'); ?></strong>
                        <?php echo $user ? $user->display_name : __('Unknown', 'case-manager-pro'); ?>
                    </div>
                    <div class="cmp-meta-item">
                        <strong><?php _e('Created:', 'case-manager-pro'); ?></strong>
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->created_at)); ?>
                    </div>
                    <?php if ($case->updated_at): ?>
                        <div class="cmp-meta-item">
                            <strong><?php _e('Last Updated:', 'case-manager-pro'); ?></strong>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($case->updated_at)); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($case->expires_at): ?>
                        <div class="cmp-meta-item">
                            <strong><?php _e('Expires:', 'case-manager-pro'); ?></strong>
                            <?php echo date_i18n(get_option('date_format'), strtotime($case->expires_at)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="cmp-case-description">
                    <h3><?php _e('Description', 'case-manager-pro'); ?></h3>
                    <div class="cmp-description-content">
                        <?php echo nl2br(esc_html($case->description)); ?>
                    </div>
                </div>
                
                <?php if ($case->result): ?>
                    <div class="cmp-case-result">
                        <h3><?php _e('Result', 'case-manager-pro'); ?></h3>
                        <div class="cmp-result-content">
                            <?php echo nl2br(esc_html($case->result)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($files)): ?>
                <div class="cmp-case-files">
                    <h3><?php _e('Attached Files', 'case-manager-pro'); ?></h3>
                    <div class="cmp-files-list">
                        <?php foreach ($files as $file): ?>
                            <div class="cmp-file-item">
                                <div class="cmp-file-info">
                                    <span class="cmp-file-name"><?php echo esc_html($file->original_filename); ?></span>
                                    <span class="cmp-file-size"><?php echo size_format($file->file_size); ?></span>
                                    <span class="cmp-file-date"><?php echo date_i18n(get_option('date_format'), strtotime($file->uploaded_at)); ?></span>
                                </div>
                                <?php if ($user_roles->user_can_download_files(get_current_user_id())): ?>
                                    <div class="cmp-file-actions">
                                        <a href="#" class="cmp-btn cmp-btn-small cmp-download-file" data-file-id="<?php echo $file->id; ?>">
                                            <?php _e('Download', 'case-manager-pro'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="cmp-case-comments">
                <h3><?php _e('Comments', 'case-manager-pro'); ?></h3>
                
                <div class="cmp-comments-list">
                    <?php if (empty($comments)): ?>
                        <p><?php _e('No comments yet.', 'case-manager-pro'); ?></p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="cmp-comment <?php echo $comment->is_private ? 'cmp-comment-private' : ''; ?>">
                                <div class="cmp-comment-header">
                                    <strong><?php echo esc_html($comment->display_name); ?></strong>
                                    <span class="cmp-comment-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($comment->created_at)); ?></span>
                                    <?php if ($comment->is_private): ?>
                                        <span class="cmp-private-label"><?php _e('Private', 'case-manager-pro'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cmp-comment-content">
                                    <?php echo nl2br(esc_html($comment->comment)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($user_roles->user_can_comment_case(get_current_user_id())): ?>
                    <div class="cmp-add-comment">
                        <h4><?php _e('Add Comment', 'case-manager-pro'); ?></h4>
                        <form id="cmp-comment-form">
                            <textarea id="comment-text" rows="4" placeholder="<?php _e('Enter your comment...', 'case-manager-pro'); ?>"></textarea>
                            <?php if ($user_roles->user_can_view_private_comments(get_current_user_id())): ?>
                                <div class="cmp-comment-options">
                                    <label>
                                        <input type="checkbox" id="comment-private"> 
                                        <?php _e('Private comment (only visible to reviewers)', 'case-manager-pro'); ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="cmp-btn cmp-btn-primary"><?php _e('Add Comment', 'case-manager-pro'); ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Download file
            $('.cmp-download-file').on('click', function(e) {
                e.preventDefault();
                var fileId = $(this).data('file-id');
                
                $.ajax({
                    url: cmp_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cmp_download_file',
                        file_id: fileId,
                        nonce: cmp_frontend.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.open(response.data.download_url, '_blank');
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Add comment
            $('#cmp-comment-form').on('submit', function(e) {
                e.preventDefault();
                
                var comment = $('#comment-text').val();
                var isPrivate = $('#comment-private').is(':checked');
                
                if (!comment.trim()) {
                    alert('<?php _e('Please enter a comment', 'case-manager-pro'); ?>');
                    return;
                }
                
                $.ajax({
                    url: cmp_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cmp_add_case_comment',
                        case_id: <?php echo $case->id; ?>,
                        comment: comment,
                        is_private: isPrivate ? 1 : 0,
                        nonce: cmp_frontend.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function user_stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id()
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view statistics.', 'case-manager-pro') . '</p>';
        }
        
        $user_id = intval($atts['user_id']);
        $db = CMP_Database::get_instance();
        
        // Get user's case statistics
        global $wpdb;
        $table_cases = $wpdb->prefix . 'cmp_cases';
        
        $total_cases = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cases} WHERE user_id = %d",
            $user_id
        ));
        
        $pending_cases = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cases} WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        $completed_cases = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cases} WHERE user_id = %d AND status = 'completed'",
            $user_id
        ));
        
        $rejected_cases = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cases} WHERE user_id = %d AND status = 'rejected'",
            $user_id
        ));
        
        ob_start();
        ?>
        <div class="cmp-user-stats">
            <div class="cmp-stats-grid">
                <div class="cmp-stat-item">
                    <div class="cmp-stat-number"><?php echo $total_cases; ?></div>
                    <div class="cmp-stat-label"><?php _e('Total Cases', 'case-manager-pro'); ?></div>
                </div>
                <div class="cmp-stat-item">
                    <div class="cmp-stat-number"><?php echo $pending_cases; ?></div>
                    <div class="cmp-stat-label"><?php _e('Pending', 'case-manager-pro'); ?></div>
                </div>
                <div class="cmp-stat-item">
                    <div class="cmp-stat-number"><?php echo $completed_cases; ?></div>
                    <div class="cmp-stat-label"><?php _e('Completed', 'case-manager-pro'); ?></div>
                </div>
                <div class="cmp-stat-item">
                    <div class="cmp-stat-number"><?php echo $rejected_cases; ?></div>
                    <div class="cmp-stat-label"><?php _e('Rejected', 'case-manager-pro'); ?></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 