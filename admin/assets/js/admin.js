/**
 * AI Blog Generator Admin JavaScript
 *
 * @package AI_Blog_Generator
 */

(function($) {
    'use strict';



    /**
     * Main namespace for all plugin functions
     */
    window.aiBlogGenerator = {
        
        /**
         * Configuration object
         */
        config: {
            ajaxUrl: (typeof aiBlogAjax !== 'undefined') ? aiBlogAjax.ajaxurl : '/wp-admin/admin-ajax.php',
            nonce: (typeof aiBlogAjax !== 'undefined') ? aiBlogAjax.nonce : '',
            strings: (typeof aiBlogAjax !== 'undefined') ? (aiBlogAjax.strings || {}) : {},
            debug: true // Always enable debug for now
        },

        /**
         * Current operations in progress
         */
        operations: {},

        /**
         * Initialize all components
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.log('AI Blog Generator initialized');
        },

        /**
         * Console logging wrapper
         */
        log: function(message, data) {
            if (this.config.debug) {
                console.log('[AI Blog Generator] ' + message, data || '');
            }
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Idea actions
            $(document).on('click', '.ai-blog-approve-idea', function(e) {
                e.preventDefault();
                self.handleIdeaAction($(this), 'approve');
            });

            $(document).on('click', '.ai-blog-deny-idea', function(e) {
                e.preventDefault();
                self.handleIdeaAction($(this), 'deny');
            });

            // Connection testing
            $(document).on('click', '.ai-blog-test-connection', function(e) {
                e.preventDefault();
                self.testApiConnection($(this));
            });

            // Schedule updates
            $(document).on('change', '.ai-blog-schedule-date, .ai-blog-schedule-time', function() {
                self.updateSchedule($(this));
            });

            // Bulk actions
            $(document).on('click', '.ai-blog-bulk-action-submit', function(e) {
                e.preventDefault();
                self.handleBulkAction($(this));
            });

            // Select all checkbox
            $(document).on('change', '#ai-blog-select-all', function() {
                $('.ai-blog-item-checkbox').prop('checked', $(this).prop('checked'));
                self.updateBulkActionUI();
            });

            // Individual checkbox
            $(document).on('change', '.ai-blog-item-checkbox', function() {
                self.updateBulkActionUI();
            });

            // Generate actions
            $(document).on('click', '.ai-blog-generate-ideas', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                if (modalId) {
                    self.openModal(modalId);
                } else {
                    self.generateIdeas($(this));
                }
            });

            $(document).on('click', '.ai-blog-generate-ideas-submit', function(e) {
                e.preventDefault();
                self.submitGenerateIdeas($(this));
            });

            $(document).on('click', '.ai-blog-generate-blog', function(e) {
                e.preventDefault();
                self.generateBlog($(this));
            });

            // Context management
            $(document).on('click', '.ai-blog-save-context', function(e) {
                e.preventDefault();
                self.saveContext($(this));
            });

            $(document).on('click', '.ai-blog-delete-context', function(e) {
                e.preventDefault();
                self.deleteContext($(this));
            });

            $(document).on('click', '.ai-blog-toggle-context', function(e) {
                e.preventDefault();
                self.toggleContext($(this));
            });

            // Image actions
            $(document).on('click', '.ai-blog-upload-seed-image', function(e) {
                e.preventDefault();
                self.uploadSeedImage($(this));
            });

            // Settings save
            $(document).on('submit', '#ai-blog-settings-form', function(e) {
                e.preventDefault();
                self.saveSettings($(this));
            });

            // Model selection change handler
            $(document).on('change', '#anthropic_model', function() {
                self.updateModelPricing($(this));
            });

            // Tab navigation
            $(document).on('click', '.ai-blog-tab-link', function(e) {
                e.preventDefault();
                self.switchTab($(this));
            });

            // Modal handlers
            $(document).on('click', '.ai-blog-modal-trigger', function(e) {
                e.preventDefault();
                self.openModal($(this).data('modal'));
            });

            $(document).on('click', '.ai-blog-modal-close', function(e) {
                e.preventDefault();
                self.closeModal($(this).closest('.ai-blog-modal'));
            });

            // Ideas confirmation modal handlers
            $(document).on('change', '.ai-blog-idea-toggle', function() {
                self.handleIdeaToggle($(this));
            });

            $(document).on('click', '.ai-blog-save-selected-ideas', function(e) {
                e.preventDefault();
                self.saveSelectedIdeas($(this));
            });

            // Filter handlers
            $(document).on('change', '.ai-blog-filter', function() {
                self.applyFilters();
            });

            // Pagination
            $(document).on('click', '.ai-blog-pagination a', function(e) {
                e.preventDefault();
                self.loadPage($(this).data('page'));
            });

            // Approved blog handlers
            $(document).on('click', '.edit-approved-idea', function(e) {
                e.preventDefault();
                self.editApprovedIdea($(this));
            });

            $(document).on('click', '.generate-idea-now', function(e) {
                e.preventDefault();
                self.generateFromApprovedIdea($(this));
            });

            $(document).on('click', '.ai-blog-save-idea-edit', function(e) {
                e.preventDefault();
                self.saveApprovedIdeaEdit($(this));
            });

            $(document).on('click', '.ai-blog-cancel-idea-edit', function(e) {
                e.preventDefault();
                self.cancelApprovedIdeaEdit($(this));
            });
        },

        /**
         * Initialize UI components
         */
        initializeComponents: function() {
            // Initialize date pickers
            if ($.fn.datepicker) {
                $('.ai-blog-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0
                });
            }

            // Initialize select2 if available
            if ($.fn.select2) {
                $('.ai-blog-select2').select2({
                    width: '100%'
                });
            }

            // Initialize tooltips
            this.initTooltips();

            // Check for any pending operations
            this.checkPendingOperations();
        },

        /**
         * Make AJAX request with standard handling
         */
        ajax: function(action, data, callbacks) {
            var self = this;
            
            // Ensure we have required data
            data = data || {};
            data.action = 'ai_blog_' + action;
            data.nonce = this.config.nonce;

            // Generate operation ID
            var operationId = 'op_' + Date.now();
            this.operations[operationId] = true;

            // Default callbacks
            callbacks = $.extend({
                beforeSend: function() {},
                success: function() {},
                error: function() {},
                complete: function() {}
            }, callbacks);

            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    self.log('AJAX request started: ' + action, data);
                    callbacks.beforeSend();
                },
                success: function(response) {
                    self.log('AJAX response: ' + action, response);
                    
                    if (response.success) {
                        callbacks.success(response.data);
                    } else {
                        self.showError(response.data.message || self.config.strings.error_generic);
                        callbacks.error(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.log('AJAX error: ' + action, {xhr: xhr, status: status, error: error});
                    self.showError(self.config.strings.error_generic);
                    callbacks.error({message: error});
                },
                complete: function() {
                    delete self.operations[operationId];
                    callbacks.complete();
                }
            });
        },

        /**
         * Handle idea approval/denial
         */
        handleIdeaAction: function($button, action) {
            var self = this;
            var ideaId = $button.data('idea-id');
            var $row = $button.closest('tr');

            if (!ideaId) {
                this.showError('Invalid idea ID');
                return;
            }

            // Confirm action
            if (!confirm(this.config.strings['confirm_' + action] || 'Are you sure?')) {
                return;
            }

            this.ajax(action + '_idea', {
                idea_id: ideaId
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    $row.addClass('ai-blog-processing');
                },
                success: function(data) {
                    // Update UI based on action
                    if (action === 'approve') {
                        // Show success message with link to approved blogs page
                        var approvedPageUrl = self.config.adminUrl + 'admin.php?page=ai-blog-generator-approved-ideas-v2';
                        var message = data.message + ' <a href="' + approvedPageUrl + '" class="button button-small">View Approved Ideas</a>';
                        self.showSuccess(message);
                        
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            self.updateStatistics(data.statistics);
                        });
                    } else if (action === 'deny') {
                        self.showSuccess(data.message);
                        $row.find('.ai-blog-status').html('<span class="ai-blog-badge ai-blog-badge-denied">Denied</span>');
                        $button.remove();
                    }
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                    $row.removeClass('ai-blog-processing');
                }
            });
        },

        /**
         * Test API connection
         */
        testApiConnection: function($button) {
            var self = this;
            var service = $button.data('service');
            var $container = $button.closest('.ai-blog-api-test');
            var $result = $container.find('.ai-blog-test-result');
            var $apiKeyInput = $container.find('input[type="password"], input[type="text"]');
            var apiKey = $apiKeyInput.val().trim();

            console.log('Test API Connection clicked:', {
                service: service,
                hasApiKey: !!apiKey,
                apiKeyLength: apiKey.length,
                button: $button,
                container: $container,
                result: $result
            });

            if (!apiKey) {
                $result.html('<span class="ai-blog-error">Please enter an API key first</span>').show();
                return;
            }

            // Clear previous results
            $result.empty().hide();

            this.ajax('test_api_connection', {
                service: service,
                api_key: apiKey
            }, {
                beforeSend: function() {
                    console.log('Starting API test for service:', service);
                    self.setButtonLoading($button, true);
                    $result.html('<span class="ai-blog-testing"><span class="dashicons dashicons-update-alt"></span> Testing connection...</span>').show();
                },
                success: function(data) {
                    console.log('API test successful:', data);
                    var message = typeof data === 'string' ? data : (data.message || 'Connection successful!');
                    $result.html('<span class="ai-blog-success"><span class="dashicons dashicons-yes-alt"></span> ' + self.escapeHtml(message) + '</span>').show();
                    
                    // Auto-hide success message after 5 seconds
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                },
                error: function(data) {
                    console.log('API test failed:', data);
                    var message = 'Connection failed';
                    
                    if (typeof data === 'string') {
                        message = data;
                    } else if (data && data.message) {
                        message = data.message;
                    }
                    
                    // Provide more helpful error messages
                    if (message.toLowerCase().includes('unauthorized') || message.toLowerCase().includes('api key')) {
                        message = 'Invalid API key. Please check your API key and try again.';
                    } else if (message.toLowerCase().includes('rate limit')) {
                        message = 'Rate limit exceeded. Please wait a moment and try again.';
                    } else if (message.toLowerCase().includes('network') || message.toLowerCase().includes('timeout')) {
                        message = 'Network error. Please check your internet connection and try again.';
                    }
                    
                    $result.html('<span class="ai-blog-error"><span class="dashicons dashicons-dismiss"></span> ' + self.escapeHtml(message) + '</span>').show();
                },
                complete: function() {
                    console.log('API test completed');
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Update post schedule
         */
        updateSchedule: function($input) {
            var self = this;
            var $container = $input.closest('.ai-blog-schedule-container');
            var postId = $container.data('post-id');
            var date = $container.find('.ai-blog-schedule-date').val();
            var time = $container.find('.ai-blog-schedule-time').val();

            if (!postId || !date || !time) {
                return;
            }

            // Clear any existing timeout
            if (this.scheduleTimeout) {
                clearTimeout(this.scheduleTimeout);
            }

            // Debounce the update
            this.scheduleTimeout = setTimeout(function() {
                self.ajax('update_schedule', {
                    post_id: postId,
                    schedule_date: date,
                    schedule_time: time
                }, {
                    beforeSend: function() {
                        $container.addClass('ai-blog-updating');
                    },
                    success: function(data) {
                        self.showSuccess('Schedule updated');
                        $container.find('.ai-blog-schedule-status').html(data.status_html);
                    },
                    complete: function() {
                        $container.removeClass('ai-blog-updating');
                    }
                });
            }, 1000);
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function($button) {
            var self = this;
            var action = $('#ai-blog-bulk-action').val();
            var $form = $button.closest('form');
            var itemIds = [];

            // Collect selected items
            $('.ai-blog-item-checkbox:checked').each(function() {
                itemIds.push($(this).val());
            });

            if (!action) {
                this.showError('Please select an action');
                return;
            }

            if (itemIds.length === 0) {
                this.showError('Please select at least one item');
                return;
            }

            // Confirm action
            if (!confirm(this.config.strings.confirm_bulk || 'Apply this action to ' + itemIds.length + ' items?')) {
                return;
            }

            this.ajax('bulk_' + action, {
                item_ids: itemIds,
                bulk_nonce: $form.find('#ai_blog_bulk_nonce').val()
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    $('.ai-blog-item-checkbox:checked').closest('tr').addClass('ai-blog-processing');
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    
                    // Reload the page or update UI
                    if (data.reload) {
                        window.location.reload();
                    } else if (data.removed_ids) {
                        data.removed_ids.forEach(function(id) {
                            $('#ai-blog-item-' + id).fadeOut(400, function() {
                                $(this).remove();
                            });
                        });
                    }
                    
                    // Update statistics if provided
                    if (data.statistics) {
                        self.updateStatistics(data.statistics);
                    }
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                    $('.ai-blog-processing').removeClass('ai-blog-processing');
                    $('#ai-blog-select-all').prop('checked', false);
                    self.updateBulkActionUI();
                }
            });
        },

        /**
         * Generate ideas
         */
        generateIdeas: function($button) {
            var self = this;
            var count = $button.data('count') || $('#ai-blog-ideas-count').val() || 5;

            this.ajax('generate_ideas', {
                count: count
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    self.showInfo('Generating ideas... This may take a moment.');
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    
                    // Add new ideas to the list
                    if (data.ideas && data.ideas.length > 0) {
                        self.addNewIdeas(data.ideas);
                    }
                    
                    // Update statistics
                    if (data.statistics) {
                        self.updateStatistics(data.statistics);
                    }
                },
                error: function(data) {
                    if (data.budget_exceeded) {
                        self.showError('Budget limit exceeded. Please check your settings.');
                    }
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Submit generate ideas form from modal
         */
        submitGenerateIdeas: function($button) {
            var self = this;
            var $form = $('#generate-ideas-form');
            var count = parseInt($('#ai-blog-ideas-count').val()) || 5;
            var customPrompt = $('#ai-blog-custom-prompt').val().trim();
            var contextId = parseInt($('#ai-blog-context-select').val()) || 0;

            console.log('Generate Ideas Form Data:', {
                count: count,
                customPrompt: customPrompt,
                contextId: contextId
            });

            // Validate input
            if (count < 1 || count > 10) {
                this.showError('Please enter a valid number of ideas (1-10)');
                return;
            }

            var requestData = {
                count: count,
                context_id: contextId
            };

            if (customPrompt) {
                requestData.custom_prompt = customPrompt;
            }

            this.ajax('generate_ideas', requestData, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    self.showInfo('Generating ' + count + ' ideas... This may take a moment.');
                },
                success: function(data) {
                    // Close the generation modal
                    self.closeModal($('#generate-ideas-modal'));
                    
                    // Reset form
                    $form[0].reset();
                    $('#ai-blog-ideas-count').val(5);
                    $('#ai-blog-context-select').val('');
                    
                    // Show ideas in confirmation modal
                    if (data.ideas && data.ideas.length > 0) {
                        self.showIdeasConfirmationModal(data.ideas, data);
                        } else {
                        self.showWarning('No ideas were generated. Please try again.');
                    }
                },
                error: function(data) {
                    if (data.budget_exceeded) {
                        self.showError('Budget limit exceeded. Please check your settings.');
                    } else {
                        self.showError(data.message || 'Failed to generate ideas. Please try again.');
                    }
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Generate blog from idea
         */
        generateBlog: function($button) {
            var self = this;
            var ideaId = $button.data('idea-id');
            console.log('Generate Blog Button Clicked:', {
                ideaId: ideaId,
                button: $button
            });
            var $container = $button.closest('.ai-blog-idea-item');

            this.ajax('generate_blog', {
                idea_id: ideaId
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    $container.addClass('ai-blog-generating');
                    self.showInfo('Generating blog post... This may take 1-2 minutes.');
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    
                    // Update UI
                    if (data.post_url) {
                        $container.find('.ai-blog-actions').html(
                            '<a href="' + data.post_url + '" class="button" target="_blank">View Draft</a>'
                        );
                    }
                    
                    // Update status
                    $container.find('.ai-blog-status').html(
                        '<span class="ai-blog-badge ai-blog-badge-generated">Generated</span>'
                    );
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                    $container.removeClass('ai-blog-generating');
                }
            });
        },

        /**
         * Save context
         */
        saveContext: function($button) {
            var self = this;
            var $form = $button.closest('form');
            var formData = {};
            
            // Convert serialized form data to object
            var serializedArray = $form.serializeArray();
            $.each(serializedArray, function(i, field) {
                formData[field.name] = field.value;
            });

            this.ajax('save_context', formData, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    
                    // Close modal if in modal
                    var $modal = $form.closest('.ai-blog-modal');
                    if ($modal.length) {
                        self.closeModal($modal);
                    }
                    
                    // Refresh context list
                    self.refreshContextList();
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Delete context
         */
        deleteContext: function($button) {
            var self = this;
            var contextId = $button.data('context-id');
            var $item = $button.closest('.ai-blog-context-item');

            if (!confirm(this.config.strings.confirm_delete || 'Are you sure you want to delete this context?')) {
                return;
            }

            this.ajax('delete_context', {
                context_id: contextId
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    $item.addClass('ai-blog-deleting');
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    $item.fadeOut(400, function() {
                        $(this).remove();
                    });
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Toggle context active status
         */
        toggleContext: function($button) {
            var self = this;
            var contextId = $button.data('context-id');
            var $item = $button.closest('.ai-blog-context-item');

            this.ajax('toggle_context', {
                context_id: contextId
            }, {
                beforeSend: function() {
                    $button.prop('disabled', true);
                },
                success: function(data) {
                    // Update button and item state
                    if (data.active) {
                        $button.text('Active').removeClass('ai-blog-inactive').addClass('ai-blog-active');
                        $item.removeClass('ai-blog-context-inactive');
                    } else {
                        $button.text('Inactive').removeClass('ai-blog-active').addClass('ai-blog-inactive');
                        $item.addClass('ai-blog-context-inactive');
                    }
                    
                    self.showSuccess(data.message);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Save settings
         */
        saveSettings: function($form) {
            var self = this;
            var formData = {};
            
            // Convert serialized form data to object
            var serializedArray = $form.serializeArray();
            $.each(serializedArray, function(i, field) {
                formData[field.name] = field.value;
            });

            // Handle checkboxes explicitly (they won't be in serializeArray if unchecked)
            $form.find('input[type="checkbox"]').each(function() {
                var $checkbox = $(this);
                var name = $checkbox.attr('name');
                if (name) {
                    formData[name] = $checkbox.is(':checked') ? '1' : '0';
                }
            });

            console.log('Saving settings with data:', formData);
            console.log('Using nonce:', this.config.nonce);
            console.log('AJAX URL:', this.config.ajaxUrl);

            this.ajax('save_settings', formData, {
                beforeSend: function() {
                    console.log('Starting settings save request...');
                    $form.find('#submit').prop('disabled', true).val('Saving...');
                },
                success: function(data) {
                    console.log('Settings save successful:', data);
                    self.showSuccess(data.message || 'Settings saved successfully');
                    
                    // Update any UI elements if needed
                    if (data.updated_fields) {
                        self.updateSettingsUI(data.updated_fields);
                    }
                    
                    // Log what was actually updated
                    if (data.updated_settings) {
                        console.log('Updated settings:', data.updated_settings);
                    }
                },
                error: function(data) {
                    console.error('Settings save failed:', data);
                    self.showError(data.message || 'Failed to save settings. Please try again.');
                },
                complete: function() {
                    console.log('Settings save request completed');
                    $form.find('#submit').prop('disabled', false).val('Save Settings');
                }
            });
        },

        /**
         * UI Helper Functions
         */
        
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.data('original-text', $button.text());
                $button.data('original-html', $button.html());
                $button.html('<span class="dashicons dashicons-update-alt"></span> Testing...').prop('disabled', true).addClass('ai-blog-loading');
            } else {
                var originalHtml = $button.data('original-html');
                var originalText = $button.data('original-text');
                
                if (originalHtml) {
                    $button.html(originalHtml);
                } else if (originalText) {
                    $button.text(originalText);
                }
                $button.prop('disabled', false).removeClass('ai-blog-loading');
            }
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showInfo: function(message) {
            this.showNotice(message, 'info');
        },

        showWarning: function(message) {
            this.showNotice(message, 'warning');
        },

        showNotice: function(message, type) {
            var self = this;
            var noticeId = 'notice_' + Date.now();
            
            var $notice = $('<div>')
                .attr('id', noticeId)
                .addClass('notice notice-' + type + ' is-dismissible ai-blog-notice')
                .html('<p>' + message + '</p>')
                .hide();

            // Add dismiss button
            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('notice-dismiss')
                .html('<span class="screen-reader-text">Dismiss this notice.</span>')
                .on('click', function() {
                    self.dismissNotice(noticeId);
                });

            $notice.append($button);

            // Add to page
            $('.wp-header-end').after($notice);
            $notice.slideDown();

            // Auto dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    self.dismissNotice(noticeId);
                }, 5000);
            }
        },

        dismissNotice: function(noticeId) {
            $('#' + noticeId).slideUp(400, function() {
                $(this).remove();
            });
        },

        updateStatistics: function(stats) {
            if (!stats) return;

            // Update any statistics displays
            $.each(stats, function(key, value) {
                $('.ai-blog-stat-' + key).text(value);
            });

            // Update progress bars
            if (stats.progress) {
                $('.ai-blog-progress-bar').css('width', stats.progress + '%');
                $('.ai-blog-progress-text').text(stats.progress + '%');
            }
        },

        updateBulkActionUI: function() {
            var checkedCount = $('.ai-blog-item-checkbox:checked').length;
            var totalCount = $('.ai-blog-item-checkbox').length;

            // Update select all checkbox
            $('#ai-blog-select-all').prop('checked', checkedCount === totalCount && totalCount > 0);

            // Update bulk action button
            if (checkedCount > 0) {
                $('.ai-blog-bulk-action-submit').prop('disabled', false);
                $('.ai-blog-selected-count').text('(' + checkedCount + ' selected)').show();
            } else {
                $('.ai-blog-bulk-action-submit').prop('disabled', true);
                $('.ai-blog-selected-count').hide();
            }
        },

        switchTab: function($tab) {
            var tabId = $tab.data('tab');
            
            // Update tab states
            $('.ai-blog-tab-link').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update content
            $('.ai-blog-tab-content').hide();
            $('#ai-blog-tab-' + tabId).show();
            
            // Save preference
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', window.location.pathname + window.location.search + '#' + tabId);
            }
        },

        openModal: function(modalId) {
            var $modal = $('#' + modalId);
            $modal.addClass('ai-blog-modal-active');
            $('body').addClass('ai-blog-modal-open');
        },

        closeModal: function($modal) {
            $modal.removeClass('ai-blog-modal-active');
            $('body').removeClass('ai-blog-modal-open');
        },

        applyFilters: function() {
            var self = this;
            var filters = {};
            
            $('.ai-blog-filter').each(function() {
                var $filter = $(this);
                var name = $filter.attr('name');
                var value = $filter.val();
                
                if (value) {
                    filters[name] = value;
                }
            });

            // Add loading state
            $('.ai-blog-content').addClass('ai-blog-loading-overlay');

            // Load filtered data
            this.ajax('apply_filters', filters, {
                success: function(data) {
                    if (data.html) {
                        $('.ai-blog-content').html(data.html);
                    }
                },
                complete: function() {
                    $('.ai-blog-content').removeClass('ai-blog-loading-overlay');
                }
            });
        },

        loadPage: function(page) {
            var self = this;
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('paged', page);

            // Add loading state
            $('.ai-blog-content').addClass('ai-blog-loading-overlay');

            // Load page via AJAX
            $.get(currentUrl.toString(), function(response) {
                var $response = $(response);
                var $newContent = $response.find('.ai-blog-content').html();
                
                $('.ai-blog-content').html($newContent);
                
                // Update URL without reload
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', currentUrl.toString());
                }
            }).always(function() {
                $('.ai-blog-content').removeClass('ai-blog-loading-overlay');
            });
        },

        addNewIdeas: function(ideas) {
            var self = this;
            var $container = $('.ai-blog-ideas-list tbody');
            
            if (!$container.length) {
                // Reload page if no container found
                window.location.reload();
                return;
            }

            // Add each idea
            ideas.forEach(function(idea) {
                var $row = self.createIdeaRow(idea);
                $row.hide().prependTo($container).fadeIn(600);
            });

            // Remove "no ideas" message if exists
            $('.ai-blog-no-items').remove();
        },

        createIdeaRow: function(idea) {
            // This would be customized based on your actual row structure
            var html = '<tr id="ai-blog-idea-' + idea.id + '" class="ai-blog-new-item">';
            html += '<td><input type="checkbox" class="ai-blog-item-checkbox" value="' + idea.id + '"></td>';
            html += '<td class="ai-blog-idea-title">' + this.escapeHtml(idea.title) + '</td>';
            html += '<td class="ai-blog-idea-description">' + this.escapeHtml(idea.description) + '</td>';
            html += '<td class="ai-blog-status"><span class="ai-blog-badge ai-blog-badge-pending">Pending</span></td>';
            html += '<td class="ai-blog-actions">';
            html += '<button class="button button-primary ai-blog-approve-idea" data-idea-id="' + idea.id + '">Approve</button> ';
            html += '<button class="button ai-blog-deny-idea" data-idea-id="' + idea.id + '">Deny</button>';
            html += '</td>';
            html += '</tr>';
            
            return $(html);
        },

        refreshContextList: function() {
            // Implement context list refresh
            window.location.reload(); // Simple implementation
        },

        updateSettingsUI: function(fields) {
            // Update any dependent UI elements after settings change
            $.each(fields, function(field, value) {
                $('.ai-blog-setting-dependent[data-field="' + field + '"]').each(function() {
                    // Custom update logic based on field
                });
            });
        },

        initTooltips: function() {
            // Initialize any tooltips
            $('.ai-blog-tooltip').each(function() {
                var $this = $(this);
                var content = $this.data('tooltip');
                
                if (content) {
                    $this.attr('title', content);
                }
            });
        },

        checkPendingOperations: function() {
            // Check for any operations that were in progress
            var pendingCount = $('.ai-blog-generating, .ai-blog-processing').length;
            
            if (pendingCount > 0) {
                this.showInfo('There are ' + pendingCount + ' operations in progress.');
            }
        },

        /**
         * Show ideas confirmation modal with generated ideas
         */
        showIdeasConfirmationModal: function(ideas, responseData) {
            var self = this;
            var $container = $('#generated-ideas-container');
            
            // Store original ideas data for saving later
            this.originalIdeasData = ideas;
            
            // Clear previous content
            $container.empty();
            
            // Add cost information if available
            if (responseData.cost && responseData.cost > 0) {
                var costInfo = '<div class="ai-blog-cost-info" style="background: #f0f6fc; border: 1px solid #c3d8ef; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
                costInfo += '<strong>Generation Cost:</strong> $' + parseFloat(responseData.cost).toFixed(4);
                if (responseData.tokens_used) {
                    costInfo += ' (' + responseData.tokens_used + ' tokens)';
                }
                costInfo += '</div>';
                $container.append(costInfo);
            }
            
            // Add modal header message
            var headerMessage = '<div class="ai-blog-ideas-header" style="margin-bottom: 20px;">';
            headerMessage += '<p class="description">Here are ' + ideas.length + ' unique blog post ideas for Poster Studio Express:</p>';
            headerMessage += '</div>';
            $container.append(headerMessage);
            
            // Create idea cards
            ideas.forEach(function(idea, index) {
                var $ideaCard = self.createIdeaCard(idea, index);
                $container.append($ideaCard);
            });
            
            // Show the confirmation modal
            this.openModal('ideas-confirmation-modal');
            
            // Update save button state
            this.updateSaveButtonState();
            
            // Show success message about generation
            if (responseData.message) {
                this.showSuccess(responseData.message);
            }
        },

        /**
         * Create an idea card for the confirmation modal
         */
        createIdeaCard: function(idea, index) {
            console.log('Creating idea card for idea:', idea);
            
            var cardHtml = '<div class="ai-blog-idea-card" data-idea-index="' + index + '"';
            
            // Store idea data as data attributes
            if (idea.category_id) {
                cardHtml += ' data-category-id="' + idea.category_id + '"';
            }
            if (idea.persona_id) {
                cardHtml += ' data-persona-id="' + idea.persona_id + '"';
            }
            cardHtml += '>';
            
            // Header with title and toggle
            cardHtml += '<div class="ai-blog-idea-card-header">';
            cardHtml += '<h3 class="ai-blog-idea-card-title">' + this.escapeHtml(idea.title || 'Untitled') + '</h3>';
            cardHtml += '<div class="ai-blog-idea-card-toggle">';
            cardHtml += '<input type="checkbox" class="ai-blog-idea-toggle" data-idea-index="' + index + '" checked>';
            cardHtml += '<label class="ai-blog-toggle-label">Approve</label>';
            cardHtml += '</div>';
            cardHtml += '</div>';
            
            // Body with description and metadata
            cardHtml += '<div class="ai-blog-idea-card-body">';
            if (idea.description) {
                cardHtml += '<p class="ai-blog-idea-description">' + this.escapeHtml(idea.description) + '</p>';
            }
            cardHtml += '<div class="ai-blog-idea-meta">';
            if (idea.category) {
                cardHtml += '<span><strong>Category:</strong> ' + this.escapeHtml(idea.category) + '</span>';
            }
            if (idea.primary_keyword) {
                cardHtml += '<span><strong>Keyword:</strong> ' + this.escapeHtml(idea.primary_keyword) + '</span>';
            }
            if (idea.persona_id) {
                // Look up persona name from the stored data or use ID
                var personaText = 'Persona ' + idea.persona_id;
                if (this.config.personas && this.config.personas[idea.persona_id]) {
                    personaText = this.config.personas[idea.persona_id].name;
                }
                cardHtml += '<span><strong>Suggested Writer:</strong> ' + this.escapeHtml(personaText) + '</span>';
            }
            cardHtml += '</div>';
            cardHtml += '</div>';
            
            cardHtml += '</div>';
            
            return $(cardHtml);
        },

        /**
         * Handle idea toggle (approve/deny)
         */
        handleIdeaToggle: function($toggle) {
            var $card = $toggle.closest('.ai-blog-idea-card');
            var $label = $card.find('.ai-blog-toggle-label');
            var isApproved = $toggle.is(':checked');
            
            if (isApproved) {
                $card.removeClass('denied').addClass('approved');
                $label.text('Approve');
            } else {
                $card.removeClass('approved').addClass('denied');
                $label.text('Deny');
            }
            
            // Update save button state
            this.updateSaveButtonState();
        },

        /**
         * Update save button state based on selections
         */
        updateSaveButtonState: function() {
            var selectedCount = $('.ai-blog-idea-toggle:checked').length;
            var $saveButton = $('.ai-blog-save-selected-ideas');
            
            if (selectedCount > 0) {
                $saveButton.prop('disabled', false);
                $saveButton.find('span').last().text('Save Selected Ideas (' + selectedCount + ')');
            } else {
                $saveButton.prop('disabled', true);
                $saveButton.find('span').last().text('Save Selected Ideas');
            }
        },

        /**
         * Save selected ideas to database
         */
        saveSelectedIdeas: function($button) {
            var self = this;
            var selectedIdeas = [];
            
            // Store the original ideas data when showing the confirmation modal
            if (!this.originalIdeasData) {
                this.showError('Original ideas data not found. Please try generating ideas again.');
                return;
            }
            
            // Collect selected ideas using original data
            $('.ai-blog-idea-toggle:checked').each(function() {
                var index = $(this).data('idea-index');
                
                if (self.originalIdeasData[index]) {
                    var ideaData = {
                        title: self.originalIdeasData[index].title || '',
                        description: self.originalIdeasData[index].description || '',
                        category: self.originalIdeasData[index].category || 'General',
                        primary_keyword: self.originalIdeasData[index].primary_keyword || '',
                        persona_id: self.originalIdeasData[index].persona_id || null
                    };
                    
                    selectedIdeas.push(ideaData);
                }
            });
            
            if (selectedIdeas.length === 0) {
                this.showError('Please select at least one idea to save.');
                return;
            }
            
            console.log('Saving selected ideas:', selectedIdeas);
            
            this.ajax('save_selected_ideas', {
                selected_ideas: selectedIdeas
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                    self.showInfo('Saving ' + selectedIdeas.length + ' selected ideas...');
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    
                    // Clear the original ideas data
                    self.originalIdeasData = null;
                    
                    // Close the confirmation modal
                    self.closeModal($('#ideas-confirmation-modal'));
                    
                    // Update statistics if available
                    if (data.statistics) {
                        self.updateStatistics(data.statistics);
                    }
                    
                    // Reload the page to show new ideas
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                },
                error: function(data) {
                    self.showError(data.message || 'Failed to save ideas. Please try again.');
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Extract meta value from idea card
         */
        extractMetaValue: function($card, label) {
            var $meta = $card.find('.ai-blog-idea-meta span');
            var value = '';
            
            $meta.each(function() {
                var text = $(this).text();
                if (text.indexOf(label + ':') === 0) {
                    value = text.substring((label + ':').length).trim();
                    return false; // break
                }
            });
            
            return value;
        },

        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Upload seed image handler
         */
        uploadSeedImage: function($button) {
            var self = this;
            var $form = $button.closest('form');
            var formData = new FormData($form[0]);

            // Use native XMLHttpRequest for file upload
            var xhr = new XMLHttpRequest();
            
            xhr.open('POST', this.config.ajaxUrl, true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var percentComplete = (e.loaded / e.total) * 100;
                    self.updateUploadProgress(percentComplete);
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.addSeedImageToList(response.data.image);
                        $form[0].reset();
                    } else {
                        self.showError(response.data.message);
                    }
                } else {
                    self.showError('Upload failed');
                }
                self.setButtonLoading($button, false);
            };
            
            xhr.onerror = function() {
                self.showError('Upload failed');
                self.setButtonLoading($button, false);
            };
            
            // Add action and nonce
            formData.append('action', 'ai_blog_upload_seed_image');
            formData.append('nonce', this.config.nonce);
            
            self.setButtonLoading($button, true);
            xhr.send(formData);
        },

        updateUploadProgress: function(percent) {
            $('.ai-blog-upload-progress').show().find('.ai-blog-progress-bar').css('width', percent + '%');
        },

        addSeedImageToList: function(image) {
            // Implementation depends on your UI structure
            var $container = $('.ai-blog-seed-images');
            var $item = $('<div class="ai-blog-seed-image-item">');
            // Build image item HTML
            $container.append($item);
        },

        /**
         * Update model pricing information when model selection changes
         */
        updateModelPricing: function($select) {
            var model = $select.val();
            var pricingInfo = this.getModelPricing(model);
            $('#model-pricing-info').text(pricingInfo);
        },

        /**
         * Get pricing information for a specific model
         */
        getModelPricing: function(model) {
            var pricing = {
                'claude-sonnet-4-20250514': 'Claude Sonnet 4: $3/1M input tokens, $15/1M output tokens',
                'claude-opus-4-20250514': 'Claude Opus 4: $15/1M input tokens, $75/1M output tokens'
            };

            return pricing[model] || 'Pricing information not available';
        },

        // Approved blog handlers
        editApprovedIdea: function($button) {
            var self = this;
            var ideaId = $button.data('idea-id');

            this.ajax('get_idea_for_edit', {
                idea_id: ideaId
            }, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                },
                success: function(data) {
                    self.showIdeaEditModal(data);
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        generateFromApprovedIdea: function($button) {
            var self = this;
            var ideaId = $button.data('idea-id');
            var $row = $button.closest('tr');
            var $actionsCell = $row.find('.column-actions');

            // Show generating status
            $actionsCell.html('<span class="ai-blog-generating-status"><span class="dashicons dashicons-update-alt"></span> Generating...</span>');

            this.ajax('start_background_generation', {
                idea_id: ideaId
            }, {
                success: function(data) {
                    self.showSuccess(data.message);
                    // Start status checking
                    self.startGenerationStatusCheck(ideaId, $row);
                },
                error: function(data) {
                    // Restore buttons on error
                    self.restoreApprovedIdeaButtons($row, ideaId);
                }
            });
        },

        saveApprovedIdeaEdit: function($button) {
            var self = this;
            var $form = $button.closest('form');
            var ideaId = $form.find('#edit-idea-id').val();
            var generateAfter = $form.find('#generate-after-edit').is(':checked');

            var formData = {
                idea_id: ideaId,
                title: $form.find('#edit-idea-title').val(),
                description: $form.find('#edit-idea-description').val(),
                category_id: $form.find('#edit-idea-category').val(),
                persona_id: $form.find('#edit-idea-persona').val(),
                generate_after: generateAfter ? '1' : '0'
            };

            this.ajax('update_approved_idea', formData, {
                beforeSend: function() {
                    self.setButtonLoading($button, true);
                },
                success: function(data) {
                    self.showSuccess(data.message);
                    self.closeModal($('#edit-idea-modal'));
                    
                    if (data.generated && data.blog_id) {
                        // Start status checking for generated blog
                        var $row = $('tr[data-idea-id="' + ideaId + '"]');
                        self.startGenerationStatusCheck(ideaId, $row);
                    } else {
                        // Just refresh the page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                },
                complete: function() {
                    self.setButtonLoading($button, false);
                }
            });
        },

        cancelApprovedIdeaEdit: function($button) {
            this.closeModal($('#edit-idea-modal'));
        },

        showIdeaEditModal: function(ideaData) {
            var modalHtml = '<div id="edit-idea-modal" class="ai-blog-modal">' +
                '<div class="ai-blog-modal-content">' +
                '<div class="ai-blog-modal-header">' +
                '<h2>Edit Approved Idea</h2>' +
                '<button type="button" class="ai-blog-modal-close">&times;</button>' +
                '</div>' +
                '<form class="ai-blog-modal-body">' +
                '<input type="hidden" id="edit-idea-id" value="' + this.escapeHtml(ideaData.id) + '">' +
                '<div class="form-row">' +
                '<label for="edit-idea-title">Title:</label>' +
                '<input type="text" id="edit-idea-title" value="' + this.escapeHtml(ideaData.title) + '" required>' +
                '</div>' +
                '<div class="form-row">' +
                '<label for="edit-idea-description">Description:</label>' +
                '<textarea id="edit-idea-description" rows="4" required>' + this.escapeHtml(ideaData.description) + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                '<label for="edit-idea-category">Category:</label>' +
                '<select id="edit-idea-category">' +
                '<option value="0">General</option>' +
                // Add more categories dynamically if available
                '</select>' +
                '</div>' +
                '<div class="form-row">' +
                '<label for="edit-idea-persona">Writing Persona:</label>' +
                '<select id="edit-idea-persona">' +
                '<option value="">None</option>' +
                '</select>' +
                '</div>' +
                '<div class="form-row">' +
                '<label>' +
                '<input type="checkbox" id="generate-after-edit"> Generate blog post after saving changes' +
                '</label>' +
                '</div>' +
                '</form>' +
                '<div class="ai-blog-modal-footer">' +
                '<button type="button" class="button ai-blog-cancel-idea-edit">Cancel</button>' +
                '<button type="button" class="button button-primary ai-blog-save-idea-edit">Save Changes</button>' +
                '</div>' +
                '</div>' +
                '</div>';

            // Remove existing modal and add new one
            $('#edit-idea-modal').remove();
            $('body').append(modalHtml);

            // Set selected values
            if (ideaData.category_id) {
                $('#edit-idea-category').val(ideaData.category_id);
            }
            if (ideaData.persona_id) {
                $('#edit-idea-persona').val(ideaData.persona_id);
            }

            this.openModal('edit-idea-modal');
        },

        startGenerationStatusCheck: function(ideaId, $row) {
            var self = this;
            var attempts = 0;
            var maxAttempts = 60; // Check for up to 5 minutes
            
            var checkStatus = function() {
                attempts++;
                
                self.ajax('get_generation_status', {
                    idea_id: ideaId
                }, {
                    success: function(data) {
                        if (data.statuses && data.statuses[ideaId]) {
                            var status = data.statuses[ideaId];
                            self.updateGenerationUI($row, status);
                            
                            // Continue checking if not complete
                            if (status.stage !== 'complete' && attempts < maxAttempts) {
                                setTimeout(checkStatus, 5000); // Check every 5 seconds
                            } else if (status.stage === 'complete') {
                                // Generation complete, refresh the page
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            }
                        } else if (data.completed_ideas && data.completed_ideas.includes(ideaId)) {
                            // Generation completed
                            $row.find('.column-actions').html('<span class="ai-blog-success">✓ Generated</span>');
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else if (attempts < maxAttempts) {
                            // Continue checking
                            setTimeout(checkStatus, 5000);
                        } else {
                            // Max attempts reached, restore buttons
                            self.restoreApprovedIdeaButtons($row, ideaId);
                        }
                    },
                    error: function() {
                        if (attempts < maxAttempts) {
                            setTimeout(checkStatus, 10000); // Wait longer on error
                        } else {
                            self.restoreApprovedIdeaButtons($row, ideaId);
                        }
                    }
                });
            };

            // Start checking after a brief delay
            setTimeout(checkStatus, 3000);
        },

        updateGenerationUI: function($row, status) {
            var $actionsCell = $row.find('.column-actions');
            var progressText = status.message || 'Generating...';
            var progressPercent = status.progress || 0;
            
            var statusHtml = '<div class="ai-blog-generation-progress">' +
                '<div class="progress-text">' + this.escapeHtml(progressText) + '</div>' +
                '<div class="progress-bar-container">' +
                '<div class="progress-bar" style="width: ' + progressPercent + '%"></div>' +
                '</div>' +
                '<div class="progress-percent">' + progressPercent + '%</div>' +
                '</div>';
            
            $actionsCell.html(statusHtml);
        },

        restoreApprovedIdeaButtons: function($row, ideaId) {
            var $actionsCell = $row.find('.column-actions');
            var buttonsHtml = '<button type="button" class="button edit-approved-idea" data-idea-id="' + ideaId + '">' +
                '<span class="dashicons dashicons-edit"></span> Edit' +
                '</button> ' +
                '<button type="button" class="button button-primary generate-idea-now" data-idea-id="' + ideaId + '">' +
                '<span class="dashicons dashicons-lightbulb"></span> Generate' +
                '</button>';
            
            $actionsCell.html(buttonsHtml);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.aiBlogGenerator.init();
    });

})(jQuery); 








