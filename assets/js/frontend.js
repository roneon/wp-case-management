/**
 * Case Manager Pro - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Main CMP object
    window.CMP = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // File upload validation
            $(document).on('change', 'input[type="file"]', this.validateFiles);
            
            // Form submissions
            $(document).on('submit', '.cmp-form', this.handleFormSubmit);
            
            // File downloads
            $(document).on('click', '.cmp-download-file', this.handleFileDownload);
            
            // Case status updates
            $(document).on('click', '.cmp-update-status', this.handleStatusUpdate);
            
            // Comment submissions
            $(document).on('submit', '.cmp-comment-form', this.handleCommentSubmit);
            
            // Dashboard navigation
            $(document).on('click', '.cmp-nav-item', this.handleNavigation);
            
            // Search functionality
            $(document).on('input', '.cmp-search-input', this.debounce(this.handleSearch, 300));
            
            // Filter functionality
            $(document).on('change', '.cmp-filter-select', this.handleFilter);
            
            // Pagination
            $(document).on('click', '.cmp-pagination a', this.handlePagination);
        },

        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Initialize modals
            this.initModals();
            
            // Initialize file upload areas
            this.initFileUpload();
            
            // Initialize auto-save for forms
            this.initAutoSave();
            
            // Initialize notifications
            this.initNotifications();
        },

        validateFiles: function(e) {
            var files = e.target.files;
            var maxSize = 2048 * 1024 * 1024; // 2MB in bytes (default)
            var allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar']; // default types
            var errors = [];

            // Use cmp_ajax if available, otherwise use defaults
            if (typeof cmp_ajax !== 'undefined' && cmp_ajax.settings) {
                maxSize = parseInt(cmp_ajax.settings.max_file_size || 2048) * 1024 * 1024;
                allowedTypes = (cmp_ajax.settings.allowed_file_types || 'pdf,doc,docx,jpg,jpeg,png,zip,rar').split(',');
            }

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var fileExtension = file.name.split('.').pop().toLowerCase();

                // Check file size
                if (file.size > maxSize) {
                    errors.push(file.name + ': File is too large. Maximum size is ' + (maxSize / 1024 / 1024) + 'MB.');
                }

                // Check file type
                if (allowedTypes.indexOf(fileExtension) === -1) {
                    errors.push(file.name + ': Invalid file type. Allowed types: ' + allowedTypes.join(', '));
                }
            }

            if (errors.length > 0) {
                CMP.showMessage(errors.join('\n'), 'error');
                e.target.value = '';
                return false;
            }

            return true;
        },

        handleFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Validate form
            if (!CMP.validateForm($form)) {
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Loading...');
            CMP.showLoading($form);
            
            var formData = new FormData(this);
            
            // Get AJAX URL - use cmp_ajax if available, otherwise use default
            var ajaxUrl = (typeof cmp_ajax !== 'undefined' && cmp_ajax.ajax_url) ? 
                         cmp_ajax.ajax_url : 
                         (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        CMP.showMessage(response.data.message || 'Success!', 'success');
                        
                        // Reset form if specified
                        if (response.data.reset_form) {
                            $form[0].reset();
                        }
                        
                        // Redirect if specified
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        }
                        
                        // Reload if specified
                        if (response.data.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        CMP.showMessage(response.data || 'An error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    CMP.showMessage('An error occurred', 'error');
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                    CMP.hideLoading($form);
                }
            });
        },

        handleFileDownload: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var fileId = $btn.data('file-id');
            
            if (!fileId) {
                CMP.showMessage('File ID is required', 'error');
                return;
            }
            
            $btn.prop('disabled', true);
            
            // Get AJAX URL and nonce
            var ajaxUrl = (typeof cmp_ajax !== 'undefined' && cmp_ajax.ajax_url) ? 
                         cmp_ajax.ajax_url : 
                         (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            var nonce = (typeof cmp_ajax !== 'undefined' && cmp_ajax.nonce) ? cmp_ajax.nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cmp_download_file',
                    file_id: fileId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create temporary download link
                        var link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename || 'download';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        CMP.showMessage(response.data || 'Download failed', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Download failed', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        handleStatusUpdate: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var caseId = $btn.data('case-id');
            var status = $btn.data('status') || $('#case-status').val();
            
            if (!caseId || !status) {
                CMP.showMessage('Case ID and status are required', 'error');
                return;
            }
            
            $btn.prop('disabled', true);
            
            // Get AJAX URL and nonce
            var ajaxUrl = (typeof cmp_ajax !== 'undefined' && cmp_ajax.ajax_url) ? 
                         cmp_ajax.ajax_url : 
                         (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            var nonce = (typeof cmp_ajax !== 'undefined' && cmp_ajax.nonce) ? cmp_ajax.nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cmp_update_case_status',
                    case_id: caseId,
                    status: status,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        CMP.showMessage('Status updated successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        CMP.showMessage(response.data || 'Update failed', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Update failed', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        handleCommentSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var caseId = $form.data('case-id') || $form.find('[name="case_id"]').val();
            var comment = $form.find('textarea').val();
            var isPrivate = $form.find('input[type="checkbox"]').is(':checked') ? 1 : 0;
            
            if (!comment.trim()) {
                CMP.showMessage('Please enter a comment', 'error');
                return;
            }
            
            var $submitBtn = $form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true);
            
            // Get AJAX URL and nonce
            var ajaxUrl = (typeof cmp_ajax !== 'undefined' && cmp_ajax.ajax_url) ? 
                         cmp_ajax.ajax_url : 
                         (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            var nonce = (typeof cmp_ajax !== 'undefined' && cmp_ajax.nonce) ? cmp_ajax.nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cmp_add_case_comment',
                    case_id: caseId,
                    comment: comment,
                    is_private: isPrivate,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        CMP.showMessage('Comment added successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        CMP.showMessage(response.data || 'Failed to add comment', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Failed to add comment', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                }
            });
        },

        handleNavigation: function(e) {
            e.preventDefault();
            
            var $nav = $(this);
            var target = $nav.data('target');
            
            // Update active state
            $('.cmp-nav-item').removeClass('active');
            $nav.addClass('active');
            
            // Show/hide content sections
            $('.cmp-dashboard-section').hide();
            $('#' + target).show().addClass('cmp-fade-in');
            
            // Update URL hash
            window.location.hash = target;
        },

        handleSearch: function(e) {
            var query = $(this).val();
            var $container = $(this).closest('.cmp-search-container');
            var target = $container.data('target');
            
            CMP.performSearch(query, target);
        },

        handleFilter: function(e) {
            var $select = $(this);
            var filterType = $select.data('filter');
            var filterValue = $select.val();
            var $container = $select.closest('.cmp-filter-container');
            var target = $container.data('target');
            
            CMP.performFilter(filterType, filterValue, target);
        },

        handlePagination: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var page = $link.data('page');
            var $container = $link.closest('.cmp-pagination-container');
            var target = $container.data('target');
            
            CMP.loadPage(page, target);
        },

        performSearch: function(query, target) {
            var $target = $(target || '.cmp-search-results');
            
            CMP.showLoading($target);
            
            $.ajax({
                url: cmp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_search_cases',
                    query: query,
                    nonce: cmp_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $target.html(response.data.html);
                    } else {
                        CMP.showMessage(response.data || 'Search failed', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Search failed', 'error');
                },
                complete: function() {
                    CMP.hideLoading($target);
                }
            });
        },

        performFilter: function(filterType, filterValue, target) {
            var $target = $(target || '.cmp-filter-results');
            
            CMP.showLoading($target);
            
            $.ajax({
                url: cmp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_filter_cases',
                    filter_type: filterType,
                    filter_value: filterValue,
                    nonce: cmp_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $target.html(response.data.html);
                    } else {
                        CMP.showMessage(response.data || 'Filter failed', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Filter failed', 'error');
                },
                complete: function() {
                    CMP.hideLoading($target);
                }
            });
        },

        loadPage: function(page, target) {
            var $target = $(target || '.cmp-pagination-results');
            
            CMP.showLoading($target);
            
            $.ajax({
                url: cmp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_load_page',
                    page: page,
                    nonce: cmp_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $target.html(response.data.html);
                    } else {
                        CMP.showMessage(response.data || 'Failed to load page', 'error');
                    }
                },
                error: function() {
                    CMP.showMessage('Failed to load page', 'error');
                },
                complete: function() {
                    CMP.hideLoading($target);
                }
            });
        },

        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    errors.push($field.prev('label').text() + ' is required');
                    $field.addClass('cmp-field-error');
                } else {
                    $field.removeClass('cmp-field-error');
                }
            });
            
            // Check email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !CMP.isValidEmail(value)) {
                    isValid = false;
                    errors.push('Please enter a valid email address');
                    $field.addClass('cmp-field-error');
                } else {
                    $field.removeClass('cmp-field-error');
                }
            });
            
            if (!isValid) {
                CMP.showMessage(errors.join('\n'), 'error');
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="cmp-message cmp-message-' + type + '">' + message + '</div>');
            
            // Remove existing messages
            $('.cmp-message').remove();
            
            // Add new message
            if ($('.cmp-form-messages').length) {
                $('.cmp-form-messages').html($message);
            } else {
                $('body').prepend($message);
            }
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $message.offset().top - 100
            }, 500);
        },

        showLoading: function($container) {
            var $loading = $('<div class="cmp-loading"><div class="cmp-spinner"></div></div>');
            $container.append($loading);
        },

        hideLoading: function($container) {
            $container.find('.cmp-loading').remove();
        },

        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $element = $(this);
                var tooltip = $element.data('tooltip');
                
                $element.on('mouseenter', function() {
                    var $tooltip = $('<div class="cmp-tooltip">' + tooltip + '</div>');
                    $('body').append($tooltip);
                    
                    var offset = $element.offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    });
                });
                
                $element.on('mouseleave', function() {
                    $('.cmp-tooltip').remove();
                });
            });
        },

        initModals: function() {
            // Modal triggers
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                CMP.openModal(modalId);
            });
            
            // Close modal
            $(document).on('click', '.cmp-modal-close, .cmp-modal-overlay', function(e) {
                if (e.target === this) {
                    CMP.closeModal();
                }
            });
            
            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    CMP.closeModal();
                }
            });
        },

        openModal: function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length) {
                $modal.addClass('cmp-modal-open');
                $('body').addClass('cmp-modal-active');
            }
        },

        closeModal: function() {
            $('.cmp-modal').removeClass('cmp-modal-open');
            $('body').removeClass('cmp-modal-active');
        },

        initFileUpload: function() {
            $('.cmp-file-upload-area').each(function() {
                var $area = $(this);
                var $input = $area.find('input[type="file"]');
                
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
                    $input[0].files = files;
                    $input.trigger('change');
                });
            });
        },

        initAutoSave: function() {
            var autoSaveInterval = 30000; // 30 seconds
            
            $('.cmp-auto-save').each(function() {
                var $form = $(this);
                var formId = $form.attr('id');
                
                if (!formId) return;
                
                setInterval(function() {
                    CMP.autoSaveForm($form, formId);
                }, autoSaveInterval);
                
                // Load saved data on page load
                CMP.loadAutoSavedData($form, formId);
            });
        },

        autoSaveForm: function($form, formId) {
            var formData = $form.serialize();
            localStorage.setItem('cmp_autosave_' + formId, formData);
        },

        loadAutoSavedData: function($form, formId) {
            var savedData = localStorage.getItem('cmp_autosave_' + formId);
            if (savedData) {
                var params = new URLSearchParams(savedData);
                params.forEach(function(value, key) {
                    var $field = $form.find('[name="' + key + '"]');
                    if ($field.length) {
                        $field.val(value);
                    }
                });
            }
        },

        clearAutoSavedData: function(formId) {
            localStorage.removeItem('cmp_autosave_' + formId);
        },

        initNotifications: function() {
            // Check for new notifications periodically
            if (cmp_frontend.user_logged_in) {
                setInterval(function() {
                    CMP.checkNotifications();
                }, 60000); // Check every minute
            }
        },

        checkNotifications: function() {
            $.ajax({
                url: cmp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'cmp_check_notifications',
                    nonce: cmp_frontend.nonce
                },
                success: function(response) {
                    if (response.success && response.data.count > 0) {
                        CMP.updateNotificationBadge(response.data.count);
                    }
                }
            });
        },

        updateNotificationBadge: function(count) {
            var $badge = $('.cmp-notification-badge');
            if ($badge.length) {
                $badge.text(count).show();
            }
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Utility functions
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    CMP.showMessage('Copied to clipboard', 'success');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                CMP.showMessage('Copied to clipboard', 'success');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CMP.init();
        
        // Handle hash navigation on page load
        if (window.location.hash) {
            var target = window.location.hash.substring(1);
            $('.cmp-nav-item[data-target="' + target + '"]').trigger('click');
        }
    });

})(jQuery); 