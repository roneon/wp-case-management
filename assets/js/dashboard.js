/**
 * Case Manager Pro - Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Dashboard object
    window.CMP_Dashboard = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // Form submissions
            $(document).on('submit', '#cmp-case-form', this.handleCaseSubmit);
            
            // File upload validation
            $(document).on('change', 'input[type="file"]', this.validateFiles);
            
            // Navigation
            $(document).on('click', '.cmp-nav-link', this.handleNavigation);
            
            // Mark notifications as read
            $(document).on('click', '.cmp-mark-read', this.markNotificationRead);
            $(document).on('click', '#cmp-mark-all-read', this.markAllNotificationsRead);
        },

        initComponents: function() {
            // Initialize any dashboard-specific components
            this.initFileUpload();
        },

        handleCaseSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Çift gönderim önleme kontrolü
            if ($submitBtn.hasClass('submitting')) {
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).addClass('submitting').text('Submitting...');
            $('#cmp-form-messages').html('<div class="cmp-loading">Submitting case...</div>');
            
            var formData = new FormData(this);
            formData.append('action', 'cmp_submit_case');
            
            $.ajax({
                url: cmp_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000, // 30 saniye timeout
                success: function(response) {
                    if (response.success) {
                        $('#cmp-form-messages').html('<div class="cmp-success">' + response.data.message + '</div>');
                        $form[0].reset();
                        
                        // File input'ları da temizle
                        $form.find('input[type="file"]').val('');
                        $form.find('.file-errors').hide();
                        
                        // Redirect to cases view after 2 seconds
                        setTimeout(function() {
                            window.location.href = '?view=cases';
                        }, 2000);
                    } else {
                        $('#cmp-form-messages').html('<div class="cmp-error">' + (response.data || 'An error occurred') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    var errorMessage = 'An error occurred. Please try again.';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please check your connection and try again.';
                    }
                    
                    $('#cmp-form-messages').html('<div class="cmp-error">' + errorMessage + '</div>');
                },
                complete: function() {
                    // 2 saniye sonra butonu aktif et (çift gönderim önleme için)
                    setTimeout(function() {
                        $submitBtn.prop('disabled', false).removeClass('submitting').text(originalText);
                    }, 2000);
                }
            });
        },

        validateFiles: function() {
            var $input = $(this);
            var files = this.files;
            var maxSize = 2048 * 1024 * 1024; // 2MB in bytes
            var allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            var errors = [];

            if (files.length === 0) return;

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var fileName = file.name;
                var fileSize = file.size;
                var fileExtension = fileName.split('.').pop().toLowerCase();

                // Check file size
                if (fileSize > maxSize) {
                    errors.push(fileName + ' is too large. Maximum size is 2MB.');
                }

                // Check file type
                if (allowedTypes.indexOf(fileExtension) === -1) {
                    errors.push(fileName + ' has an invalid file type. Allowed types: ' + allowedTypes.join(', '));
                }
            }

            // Display errors
            var $errorContainer = $input.siblings('.file-errors');
            if ($errorContainer.length === 0) {
                $errorContainer = $('<div class="file-errors"></div>');
                $input.after($errorContainer);
            }

            if (errors.length > 0) {
                $errorContainer.html('<div class="cmp-error">' + errors.join('<br>') + '</div>').show();
                $input.val(''); // Clear the input
            } else {
                $errorContainer.hide();
            }
        },

        handleNavigation: function(e) {
            // Let the default navigation work
            // This is just for any additional handling if needed
        },

        markNotificationRead: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var notificationId = $btn.data('id');
            var $notification = $btn.closest('.cmp-notification-item');
            
            $.ajax({
                url: cmp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_mark_notification_read',
                    notification_id: notificationId,
                    nonce: cmp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notification.removeClass('unread').addClass('read');
                        $notification.find('.cmp-notification-status').html('');
                        
                        // Update badge count
                        var $badge = $('.cmp-notification-badge');
                        var count = parseInt($badge.text()) - 1;
                        if (count > 0) {
                            $badge.text(count);
                        } else {
                            $badge.remove();
                            $('#cmp-mark-all-read').remove();
                        }
                    }
                }
            });
        },

        markAllNotificationsRead: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: cmp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_clear_all_notifications',
                    nonce: cmp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.cmp-notification-item').removeClass('unread').addClass('read');
                        $('.cmp-notification-status').html('');
                        $('.cmp-notification-badge').remove();
                        $('#cmp-mark-all-read').remove();
                    }
                }
            });
        },

        initFileUpload: function() {
            // File upload drag and drop functionality
            $('.cmp-file-upload-area').each(function() {
                var $area = $(this);
                var $input = $area.find('input[type="file"]');
                
                if ($input.length === 0) return;
                
                $area.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    $area.addClass('cmp-drag-over');
                });
                
                $area.on('dragleave dragend drop', function(e) {
                    e.preventDefault();
                    $area.removeClass('cmp-drag-over');
                });
                
                $area.on('drop', function(e) {
                    e.preventDefault();
                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $input[0].files = files;
                        $input.trigger('change');
                    }
                });
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CMP_Dashboard.init();
    });

})(jQuery); 