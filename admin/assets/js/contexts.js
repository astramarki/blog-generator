/**
 * AI Blog Generator - Contexts Page JavaScript
 *
 * @package AI_Blog_Generator
 */

(function($) {
    'use strict';

    console.log('AI Blog Generator Contexts JS loaded');

    /**
     * Contexts Page Manager
     */
    window.aiBlogContexts = {

        /**
         * Configuration object
         */
        config: {
            ajaxUrl: (typeof aiBlogAjax !== 'undefined') ? aiBlogAjax.ajaxurl : '/wp-admin/admin-ajax.php',
            nonce: (typeof aiBlogAjax !== 'undefined') ? aiBlogAjax.nonce : '',
            debug: true
        },

        /**
         * Initialize contexts page functionality
         */
        init: function() {
            console.log('AI Blog Contexts: Initializing contexts page functionality');
            this.bindEvents();
            this.initializeComponents();
            this.log('Contexts page initialized');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Edit context button
            $(document).on('click', '.edit-context', function(e) {
                e.preventDefault();
                var contextId = $(this).data('context-id');
                console.log('Edit context button clicked for ID:', contextId);
                self.editContext(contextId);
            });

            // Add new context button
            $(document).on('click', '#add-new-context', function(e) {
                e.preventDefault();
                console.log('Add new context button clicked');
                self.addNewContext();
            });

            // Toggle context status button
            $(document).on('click', '.toggle-context', function(e) {
                e.preventDefault();
                var contextId = $(this).data('context-id');
                var $button = $(this);
                console.log('Toggle context button clicked for ID:', contextId);
                self.toggleContext(contextId, $button);
            });

            // Delete context button
            $(document).on('click', '.delete-context', function(e) {
                e.preventDefault();
                var contextId = $(this).data('context-id');
                console.log('Delete context button clicked for ID:', contextId);
                self.deleteContext(contextId);
            });

            // Handle form submission
            $(document).on('submit', '#context-form', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Context form submitted');
                self.saveContext();
            });

            // Handle modal close
            $(document).on('click', '.ai-blog-modal-close, .cancel-edit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Modal close button clicked');
                self.closeModal();
            });

            // Handle context type change
            $(document).on('change', '#context-type', function() {
                console.log('Context type changed to:', $(this).val());
            });

            // Handle usage category change
            $(document).on('change', '#context-usage', function() {
                var usage = $(this).val();
                console.log('Context usage changed to:', usage);
                self.toggleInclusionOptions(usage);
            });

            // Handle mutual exclusion of Avada and HTML checkboxes
            $(document).on('change', '#context-always-avada', function() {
                if ($(this).is(':checked')) {
                    $('#context-always-html').prop('checked', false);
                    console.log('Avada checked, unchecking HTML');
                }
            });

            $(document).on('change', '#context-always-html', function() {
                if ($(this).is(':checked')) {
                    $('#context-always-avada').prop('checked', false);
                    console.log('HTML checked, unchecking Avada');
                }
            });

            // Handle "Always include in content generation" checkbox
            $(document).on('change', '#context-always-content', function() {
                var isChecked = $(this).is(':checked');
                console.log('Always include in content generation changed:', isChecked);
                
                if (isChecked) {
                    // Check and disable both Avada and HTML options
                    $('#context-always-avada').prop('checked', true).prop('disabled', true);
                    $('#context-always-html').prop('checked', true).prop('disabled', true);
                    console.log('Content generation checked - enabled and disabled Avada and HTML');
                } else {
                    // Enable the options and maintain mutual exclusion
                    $('#context-always-avada').prop('disabled', false);
                    $('#context-always-html').prop('disabled', false);
                    
                    // Since they're mutually exclusive, uncheck one of them
                    if ($('#context-always-avada').is(':checked') && $('#context-always-html').is(':checked')) {
                        $('#context-always-html').prop('checked', false);
                    }
                    console.log('Content generation unchecked - enabled Avada and HTML options');
                }
            });

            // Handle modal backdrop click
            $(document).on('click', '#context-modal', function(e) {
                if ($(e.target).is('#context-modal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Modal backdrop clicked');
                    self.closeModal();
                }
            });

            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('#context-modal:visible').length) {
                    console.log('Escape key pressed, closing modal');
                    self.closeModal();
                }
            });

            // === SEED IMAGE FUNCTIONALITY ===
            
            // Upload seed image button
            $(document).on('click', '#upload-seed-image', function(e) {
                e.preventDefault();
                console.log('Upload seed image button clicked');
                self.showSeedImageModal();
            });

            // Edit seed image button
            $(document).on('click', '.edit-seed-image', function(e) {
                e.preventDefault();
                var seedId = $(this).data('seed-id');
                console.log('Edit seed image button clicked for ID:', seedId);
                self.editSeedImage(seedId);
            });

            // Delete seed image button
            $(document).on('click', '.delete-seed-image', function(e) {
                e.preventDefault();
                var seedId = $(this).data('seed-id');
                console.log('Delete seed image button clicked for ID:', seedId);
                self.deleteSeedImage(seedId);
            });

            // Seed image form submission
            $(document).on('submit', '#seed-image-form', function(e) {
                e.preventDefault();
                console.log('Seed image form submitted');
                self.saveSeedImage();
            });

            // Cancel seed image upload
            $(document).on('click', '.cancel-seed-upload', function(e) {
                e.preventDefault();
                console.log('Cancel seed image upload clicked');
                self.closeSeedImageModal();
            });

            // File input change for preview
            $(document).on('change', '#seed-image-file', function(e) {
                console.log('File input changed');
                self.previewSeedImage(e.target);
            });

            // Close seed image modal
            $(document).on('click', '#seed-image-upload-modal .ai-blog-modal-close', function(e) {
                e.preventDefault();
                console.log('Seed image modal close button clicked');
                self.closeSeedImageModal();
            });

            console.log('AI Blog Contexts: Event handlers bound successfully');
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            console.log('AI Blog Contexts: Initializing components');
            
            // Check if contexts are present
            var contextsCount = $('.ai-blog-context-card').length;
            console.log('Found', contextsCount, 'context cards on page');
            
            // Initialize any additional components here
            this.log('Components initialized');
        },

        /**
         * Edit context - load context data and show modal
         */
        editContext: function(contextId) {
            var self = this;
            
            console.log('AI Blog Contexts: Loading context for edit, ID:', contextId);
            
            // Show loading state
            this.showLoading('Loading context...');
            
            this.ajax('get_context', {
                context_id: contextId
            }, {
                success: function(data) {
                    console.log('Context data loaded successfully:', data);
                    self.hideLoading();
                    self.populateModal(data.context, 'edit');
                    self.showModal();
                },
                error: function(message) {
                    console.error('Failed to load context:', message);
                    self.hideLoading();
                    self.showError('Failed to load context: ' + message);
                }
            });
        },

        /**
         * Add new context - show empty modal
         */
        addNewContext: function() {
            console.log('AI Blog Contexts: Opening modal for new context');
            this.populateModal(null, 'add');
            this.showModal();
        },

        /**
         * Toggle context active status
         */
        toggleContext: function(contextId, $button) {
            var self = this;
            var isActive = $button.closest('.ai-blog-context-card').hasClass('active');
            var action = isActive ? 'deactivate' : 'activate';
            
            console.log('AI Blog Contexts: Toggling context', contextId, 'to', action);
            
            // Show loading state on button
            var originalText = $button.text();
            $button.text('Processing...').prop('disabled', true);
            
            this.ajax('toggle_context', {
                context_id: contextId
            }, {
                success: function(data) {
                    console.log('Context toggled successfully:', data);
                    
                    // Update UI
                    var $card = $button.closest('.ai-blog-context-card');
                    if (data.active) {
                        $card.removeClass('inactive').addClass('active');
                        $button.text('Deactivate');
                    } else {
                        $card.removeClass('active').addClass('inactive');
                        $button.text('Activate');
                    }
                    
                    $button.prop('disabled', false);
                    self.showSuccess(data.message);
                    
                    // Log the action
                    self.log('Context ' + action + 'd successfully', {
                        context_id: contextId,
                        new_status: data.active ? 'active' : 'inactive'
                    });
                },
                error: function(message) {
                    console.error('Failed to toggle context:', message);
                    $button.text(originalText).prop('disabled', false);
                    self.showError('Failed to toggle context: ' + message);
                }
            });
        },

        /**
         * Delete context with confirmation
         */
        deleteContext: function(contextId) {
            var self = this;
            var $card = $('.ai-blog-context-card[data-context-id="' + contextId + '"]');
            var contextName = $card.find('h3').text();
            
            console.log('AI Blog Contexts: Requesting deletion of context', contextId, '(' + contextName + ')');
            
            // Show confirmation dialog
            var confirmed = confirm(
                'Are you sure you want to delete the context "' + contextName + '"?\n\n' +
                'This action cannot be undone and will remove all associated data.'
            );
            
            if (!confirmed) {
                console.log('Context deletion cancelled by user');
                return;
            }
            
            console.log('Context deletion confirmed, proceeding...');
            
            // Show loading state
            this.showLoading('Deleting context...');
            
            this.ajax('delete_context', {
                context_id: contextId
            }, {
                success: function(data) {
                    console.log('Context deleted successfully:', data);
                    self.hideLoading();
                    
                    // Remove card from UI with animation
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        self.checkEmptyState();
                    });
                    
                    self.showSuccess(data.message);
                    
                    // Log the action
                    self.log('Context deleted successfully', {
                        context_id: contextId,
                        context_name: contextName
                    });
                },
                error: function(message) {
                    console.error('Failed to delete context:', message);
                    self.hideLoading();
                    self.showError('Failed to delete context: ' + message);
                }
            });
        },

        /**
         * Save context (create or update)
         */
        saveContext: function() {
            var self = this;
            var $form = $('#context-form');
            var contextId = $('#context-id').val();
            
            // Add debugging to check the value
            console.log('AI Blog Contexts: Context ID field value:', contextId);
            console.log('AI Blog Contexts: Context ID field element:', $('#context-id'));
            console.log('AI Blog Contexts: Context ID field length:', $('#context-id').length);
            
            var isEdit = contextId && contextId !== '';
            var action = isEdit ? 'update_context' : 'create_context';
            
            console.log('AI Blog Contexts: Saving context, action:', action, 'ID:', contextId, 'isEdit:', isEdit);
            
            // Validate form
            if (!this.validateForm($form)) {
                console.log('Form validation failed');
                return;
            }
            
            // Gather form data
            var formData = {
                name: $('#context-name').val(),
                type: $('#context-type').val(),
                usage_flags: $('#context-usage').val(),
                content: $('#context-content').val(),
                always_include_content: $('#context-always-content').is(':checked') ? 1 : 0,
                always_include_images: $('#context-always-images').is(':checked') ? 1 : 0,
                always_include_avada: $('#context-always-avada').is(':checked') ? 1 : 0,
                always_include_html: $('#context-always-html').is(':checked') ? 1 : 0
            };
            
            if (isEdit) {
                formData.context_id = contextId;
                
                // Extra check to ensure context_id is included
                if (!formData.context_id) {
                    console.error('AI Blog Contexts: Context ID is empty for edit operation!');
                    self.showError('Error: Context ID is missing. Please try again.');
                    $submitBtn.text(originalText).prop('disabled', false);
                    return;
                }
            }
            
            console.log('Form data:', formData);
            console.log('Form data keys:', Object.keys(formData));
            console.log('Context ID in form data:', formData.context_id);
            
            // Show loading state
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            $submitBtn.text('Saving...').prop('disabled', true);
            
            this.ajax(action, formData, {
                success: function(data) {
                    console.log('Context saved successfully:', data);
                    
                    $submitBtn.text(originalText).prop('disabled', false);
                    self.closeModal();
                    self.showSuccess(data.message);
                    
                    if (isEdit) {
                        // Update existing card
                        self.updateContextCard(data.context);
                    } else {
                        // Add new card or reload page
                        location.reload(); // Simple approach for now
                    }
                    
                    // Log the action
                    self.log('Context saved successfully', {
                        action: action,
                        context_id: data.context ? data.context.id : contextId,
                        context_name: formData.name
                    });
                },
                error: function(message) {
                    console.error('Failed to save context:', message);
                    $submitBtn.text(originalText).prop('disabled', false);
                    self.showError('Failed to save context: ' + message);
                }
            });
        },

        /**
         * Populate modal with context data
         */
        populateModal: function(context, mode) {
            console.log('AI Blog Contexts: Populating modal, mode:', mode, 'context:', context);
            if (context) {
                console.log('Context always_include_content value:', context.always_include_content, 'type:', typeof context.always_include_content);
                console.log('Context always_include_images value:', context.always_include_images, 'type:', typeof context.always_include_images);
            }
            
            var $modal = $('#context-modal');
            var $title = $modal.find('#modal-title');
            
            if (mode === 'edit' && context) {
                $title.text('Edit Context');
                $('#context-id').val(context.id);
                
                // Add debugging to verify the ID is set
                console.log('AI Blog Contexts: Setting context ID to:', context.id);
                console.log('AI Blog Contexts: Context ID field after setting:', $('#context-id').val());
                
                $('#context-name').val(context.name);
                $('#context-type').val(context.type);
                $('#context-usage').val(context.usage_flags || 'content');
                $('#context-content').val(context.content);

                $('#context-always-content').prop('checked', parseInt(context.always_include_content) === 1);
                $('#context-always-images').prop('checked', parseInt(context.always_include_images) === 1);
                $('#context-always-avada').prop('checked', parseInt(context.always_include_avada) === 1);
                $('#context-always-html').prop('checked', parseInt(context.always_include_html) === 1);
                
                // Handle disabled state for Avada/HTML based on content checkbox
                if (parseInt(context.always_include_content) === 1) {
                    $('#context-always-avada').prop('disabled', true);
                    $('#context-always-html').prop('disabled', true);
                } else {
                    $('#context-always-avada').prop('disabled', false);
                    $('#context-always-html').prop('disabled', false);
                }
            } else {
                $title.text('Add New Context');
                $('#context-id').val('');
                $('#context-name').val('');
                $('#context-type').val('general');
                $('#context-usage').val('content');
                $('#context-content').val('');
                $('#context-always-content').prop('checked', false);
                $('#context-always-images').prop('checked', false);
                $('#context-always-avada').prop('checked', false);
                $('#context-always-html').prop('checked', false);
                
                // Enable Avada/HTML checkboxes for new contexts
                $('#context-always-avada').prop('disabled', false);
                $('#context-always-html').prop('disabled', false);
            }
            
            // Show/hide inclusion options based on usage category
            this.toggleInclusionOptions($('#context-usage').val());
            
            console.log('Modal populated successfully');
        },

        /**
         * Update context card in UI
         */
        updateContextCard: function(context) {
            var $card = $('.ai-blog-context-card[data-context-id="' + context.id + '"]');
            
            if ($card.length) {
                console.log('Updating context card for ID:', context.id);
                
                // Update card content
                $card.find('h3').text(context.name);
                
                // Get the display label for the context type
                var typeLabel = context.type;
                if (aiBlogAjax && aiBlogAjax.contextTypes && aiBlogAjax.contextTypes[context.type]) {
                    typeLabel = aiBlogAjax.contextTypes[context.type];
                }
                $card.find('.context-type').text(typeLabel);
                
                $card.find('.context-content p').text(this.trimWords(context.content, 30));
                
                // Update badges
                var $badges = $card.find('.context-badges');
                
                // Remove existing inclusion badges
                $badges.find('.badge-always-content, .badge-always-images').remove();
                
                // Add inclusion badges if needed
                if (context.always_include_content == 1) {
                    $badges.append('<span class="badge badge-always-content" title="Always included in content generation"><i class="dashicons dashicons-edit"></i></span>');
                }
                
                if (context.always_include_images == 1) {
                    $badges.append('<span class="badge badge-always-images" title="Always included in image generation"><i class="dashicons dashicons-format-image"></i></span>');
                }
                
                // Update active status
                if (context.active) {
                    $card.removeClass('inactive').addClass('active');
                } else {
                    $card.removeClass('active').addClass('inactive');
                }
                
                console.log('Context card updated successfully');
            }
        },

        /**
         * Validate form data
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    var label = $form.find('label[for="' + $field.attr('id') + '"]').text();
                    errors.push(label + ' is required');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            if (!isValid) {
                console.log('Form validation errors:', errors);
                this.showError('Please fill in all required fields:\n• ' + errors.join('\n• '));
            }
            
            return isValid;
        },

        /**
         * Show modal
         */
        showModal: function() {
            console.log('AI Blog Contexts: Showing modal');
            
            var $modal = $('#context-modal');
            
            // Debug: Check if modal element exists and its current state
            console.log('Modal element found:', $modal.length > 0);
            console.log('Modal current display:', $modal.css('display'));
            console.log('Modal current visibility:', $modal.css('visibility'));
            console.log('Modal current z-index:', $modal.css('z-index'));
            console.log('Modal current position:', $modal.css('position'));
            console.log('Modal element:', $modal[0]);
            
            // Force the modal to be visible with explicit styles
            $modal.css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1',
                'z-index': '999999',
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'background-color': 'rgba(0,0,0,0.5)'
            });

            // Also ensure the modal content is properly positioned
            var $modalContent = $modal.find('.ai-blog-modal-content');
            $modalContent.css({
                'position': 'relative',
                'background': '#fff',
                'margin': '5% auto',
                'padding': '20px',
                'width': '90%',
                'max-width': '90%',
                'border-radius': '4px',
                'max-height': '80vh',
                'overflow-y': 'auto',
                'box-shadow': '0 4px 8px rgba(0,0,0,0.1)'
            });
            
            // Add CSS classes for additional styling
            $modal.addClass('ai-blog-modal-show force-visible');
            
            // Add body class to prevent scrolling
            $('body').addClass('ai-blog-modal-open');
            
            // Debug: Check modal state after showing
            setTimeout(function() {
                console.log('After setup - Modal display:', $modal.css('display'));
                console.log('After setup - Modal visibility:', $modal.css('visibility'));
                console.log('After setup - Modal opacity:', $modal.css('opacity'));
                console.log('After setup - Modal z-index:', $modal.css('z-index'));
                console.log('After setup - Modal position:', $modal.css('position'));
                console.log('After setup - Modal classes:', $modal.attr('class'));
                
                // Check if modal is actually visible in viewport
                var rect = $modal[0].getBoundingClientRect();
                console.log('Modal bounding rect:', rect);
                console.log('Modal is visible in viewport:', rect.top >= 0 && rect.left >= 0 && rect.bottom <= window.innerHeight && rect.right <= window.innerWidth);
                
                // Force focus on the modal to ensure it's on top
                $modal.focus();
            }, 100);
            
            // Focus first input
            setTimeout(function() {
                $('#context-name').focus();
            }, 350);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            console.log('AI Blog Contexts: Closing modal');
            
            var $modal = $('#context-modal');
            
            // Remove all modal classes
            $modal.removeClass('ai-blog-modal-show force-visible');
            
            // Reset all inline styles that might have been added
            $modal.removeAttr('style');
            
            // Hide the modal
            $modal.hide();
            
            // Remove body class
            $('body').removeClass('ai-blog-modal-open');
            
            // Clear form
            $('#context-form')[0].reset();
            $('#context-form').find('.error').removeClass('error');
            
            // Reset any checkboxes to their default state
            $('#context-always-content').prop('checked', false);
            $('#context-always-images').prop('checked', false);
            $('#context-always-avada').prop('checked', false).prop('disabled', false);
            $('#context-always-html').prop('checked', false).prop('disabled', false);
        },

        /**
         * Check if contexts grid is empty and show message
         */
        checkEmptyState: function() {
            var $grid = $('.ai-blog-contexts-grid');
            var $cards = $grid.find('.ai-blog-context-card');
            
            if ($cards.length === 0) {
                console.log('No contexts remaining, showing empty state');
                $grid.html(
                    '<div class="empty-state">' +
                    '<p>No contexts found. <a href="#" id="add-first-context">Add your first context</a> to get started.</p>' +
                    '</div>'
                );
            }
        },

        /**
         * Make AJAX request
         */
        makeAjaxRequest: function(data) {
            var self = this;
            
            // Extract action before prefixing to avoid overwrite
            var originalAction = data.action;
            delete data.action; // Remove action from data to prevent overwriting
            
            // Prepare request data with prefixed action
            var requestData = $.extend({
                action: 'ai_blog_' + originalAction,
                nonce: aiBlogAjax.nonce
            }, data);

            console.log('AI Blog Contexts: Making AJAX request:', originalAction, requestData);
            console.log('AI Blog Contexts: Final request data being sent:', requestData);
            console.log('AI Blog Contexts: AJAX URL:', aiBlogAjax.ajaxurl);

            return $.post(aiBlogAjax.ajaxurl, requestData)
                .done(function(response) {
                    console.log('AJAX response received:', originalAction, response);
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX request failed:', originalAction, status, error);
                    console.error('XHR details:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        readyState: xhr.readyState
                    });
                });
        },

        /**
         * Legacy AJAX method (kept for compatibility)
         */
        ajax: function(action, data, options) {
            var self = this;
            var settings = $.extend({
                success: function() {},
                error: function() {},
                beforeSend: function() {}
            }, options || {});

            // Prepare request data
            var requestData = $.extend({
                action: 'ai_blog_' + action,
                nonce: aiBlogAjax.nonce
            }, data || {});

            console.log('AI Blog Contexts: Making AJAX request:', action, requestData);

            // Execute before send callback
            settings.beforeSend();

            $.post(aiBlogAjax.ajaxurl, requestData)
                .done(function(response) {
                    console.log('AJAX response received:', action, response);
                    
                    if (response.success) {
                        settings.success(response.data);
                    } else {
                        var message = response.data ? response.data.message : 'Unknown error occurred';
                        settings.error(message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX request failed:', action, status, error);
                    settings.error('Request failed: ' + error);
                });
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            console.log('Success:', message);
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            console.error('Error:', message);
            this.showNotice(message, 'error');
        },

        /**
         * Show info message
         */
        showInfo: function(message) {
            console.log('Info:', message);
            this.showNotice(message, 'info');
        },

        /**
         * Show loading message
         */
        showLoading: function(message) {
            console.log('Loading:', message);
            this.showNotice(message, 'info', false);
        },

        /**
         * Hide loading message
         */
        hideLoading: function() {
            $('.ai-blog-notice.loading').remove();
        },

        /**
         * Show notice
         */
        showNotice: function(message, type, autoHide) {
            var $notice = $('<div class="ai-blog-notice notice notice-' + type + (type === 'info' ? ' loading' : '') + '">' +
                '<p>' + message + '</p>' +
                '</div>');

            // Remove existing notices of same type
            $('.ai-blog-notice.notice-' + type).remove();

            // Add notice to page
            $('.wrap h1').after($notice);

            // Auto-hide after 5 seconds unless it's a loading message
            if (autoHide !== false && type !== 'info') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Utility function to trim words
         */
        trimWords: function(text, limit) {
            var words = text.split(' ');
            if (words.length > limit) {
                return words.slice(0, limit).join(' ') + '...';
            }
            return text;
        },

        /**
         * Log function for debugging
         */
        log: function(message, data) {
            if (this.config.debug) {
                if (data) {
                    console.log('[AI Blog Contexts]', message, data);
                } else {
                    console.log('[AI Blog Contexts]', message);
                }
            }
        },

        // === SEED IMAGE METHODS ===

        /**
         * Show seed image upload modal
         */
        showSeedImageModal: function() {
            console.log('Showing seed image upload modal');
            
            // Reset form
            $('#seed-image-form')[0].reset();
            $('#seed-image-id').val('');
            $('#seed-modal-title').text('Upload Seed Image');
            $('#save-seed-image').text('Upload Seed Image');
            $('#seed-image-upload-row').show();
            $('#seed-image-file').prop('required', true);
            $('#image-preview').hide();
            
            var $modal = $('#seed-image-upload-modal');
            
            // Debug: Check if modal element exists and its current state
            console.log('Modal element found:', $modal.length > 0);
            console.log('Modal current display:', $modal.css('display'));
            console.log('Modal current visibility:', $modal.css('visibility'));
            console.log('Modal current z-index:', $modal.css('z-index'));
            console.log('Modal current position:', $modal.css('position'));
            console.log('Modal element:', $modal[0]);
            
            // Remove any existing show classes first
            $modal.removeClass('ai-blog-modal-show force-visible');
            
            // Force the modal to be visible with CSS classes and inline styles as backup
            $modal.addClass('ai-blog-modal-show force-visible');
            
            // Also force with inline styles as a backup
            $modal.css({
                'display': 'block !important',
                'visibility': 'visible !important',
                'opacity': '1 !important',
                'z-index': '999999 !important',
                'position': 'fixed !important',
                'top': '0 !important',
                'left': '0 !important',
                'width': '100% !important',
                'height': '100% !important',
                'background-color': 'rgba(0,0,0,0.5) !important'
            });
            
            // Add body class to prevent scrolling
            $('body').addClass('ai-blog-modal-open');
            
            // Debug: Check modal state after showing
            setTimeout(function() {
                console.log('After setup - Modal display:', $modal.css('display'));
                console.log('After setup - Modal visibility:', $modal.css('visibility'));
                console.log('After setup - Modal opacity:', $modal.css('opacity'));
                console.log('After setup - Modal z-index:', $modal.css('z-index'));
                console.log('After setup - Modal position:', $modal.css('position'));
                console.log('After setup - Modal classes:', $modal.attr('class'));
                
                // Check if modal is actually visible in viewport
                var rect = $modal[0].getBoundingClientRect();
                console.log('Modal bounding rect:', rect);
                console.log('Modal is visible in viewport:', rect.top >= 0 && rect.left >= 0 && rect.bottom <= window.innerHeight && rect.right <= window.innerWidth);
                
                // Check for any elements that might be covering the modal
                var elementsAtModalPosition = document.elementsFromPoint(window.innerWidth/2, window.innerHeight/2);
                console.log('Elements at modal center position:', elementsAtModalPosition);
                
                // Force focus on the modal to ensure it's on top
                $modal.focus();
            }, 100);
            
            // Focus on first input with longer delay
            setTimeout(function() {
                $('#seed-product-name').focus();
                console.log('Product name field focused');
            }, 500);
        },

        /**
         * Close seed image modal
         */
        closeSeedImageModal: function() {
            console.log('Closing seed image modal');
            
            var $modal = $('#seed-image-upload-modal');
            
            // Remove the visibility classes
            $modal.removeClass('ai-blog-modal-show force-visible');
            
            // Reset inline styles
            $modal.css({
                'display': '',
                'visibility': '',
                'opacity': '',
                'z-index': '',
                'position': '',
                'top': '',
                'left': '',
                'width': '',
                'height': '',
                'background-color': ''
            });
            
            // Remove body class
            $('body').removeClass('ai-blog-modal-open');
            
            // Hide with fade out animation
            $modal.fadeOut(300);
        },

        /**
         * Edit seed image
         */
        editSeedImage: function(seedId) {
            console.log('Editing seed image ID:', seedId);
            
            var self = this;
            
            // Get seed image data
            this.makeAjaxRequest({
                action: 'get_seed_image',
                seed_id: seedId
            })
            .then(function(response) {
                console.log('Seed image data received:', response);
                
                if (response.success && response.data) {
                    // Populate form
                    $('#seed-image-id').val(response.data.id);
                    $('#seed-product-name').val(response.data.product_name);
                    $('#seed-context-link').val(response.data.context_id || '');
                    
                    // Update modal for editing
                    $('#seed-modal-title').text('Edit Seed Image');
                    $('#save-seed-image').text('Update Seed Image');
                    $('#seed-image-upload-row').hide();
                    $('#seed-image-file').prop('required', false);
                    
                    // Show current image preview if available
                    if (response.data.image_url) {
                        $('#preview-image').attr('src', response.data.image_url);
                        $('#image-preview').show();
                    }
                    
                    // Show modal
                    $('#seed-image-upload-modal').fadeIn(300);
                    
                    // Focus on product name
                    setTimeout(function() {
                        $('#seed-product-name').focus().select();
                    }, 350);
                } else {
                    self.showError('Failed to load seed image data: ' + (response.data?.message || 'Unknown error'));
                }
            })
            .catch(function(error) {
                console.error('Error loading seed image:', error);
                self.showError('Failed to load seed image data');
            });
        },

        /**
         * Delete seed image
         */
        deleteSeedImage: function(seedId) {
            console.log('Deleting seed image ID:', seedId);
            
            var self = this;
            var seedItem = $('.seed-image-item[data-seed-id="' + seedId + '"]');
            var productName = seedItem.find('.product-name').text();
            
            if (!confirm('Are you sure you want to delete the seed image "' + productName + '"?\n\nThis action cannot be undone.')) {
                console.log('Seed image deletion cancelled by user');
                return;
            }
            
            // Show loading state
            seedItem.addClass('loading');
            
            // Debug: Log what we're about to send
            var requestData = {
                action: 'delete_seed_image',
                seed_id: seedId
            };
            
            console.log('AI Blog Contexts: About to send delete request with data:', requestData);
            console.log('AI Blog Contexts: aiBlogAjax object:', aiBlogAjax);
            console.log('AI Blog Contexts: Available nonce:', aiBlogAjax ? aiBlogAjax.nonce : 'MISSING');
            console.log('AI Blog Contexts: AJAX URL:', aiBlogAjax ? aiBlogAjax.ajaxurl : 'MISSING');
            
            this.makeAjaxRequest(requestData)
            .then(function(response) {
                console.log('Delete seed image response:', response);
                
                if (response.success) {
                    // Remove from DOM with animation
                    seedItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if no seed images left
                        if ($('.seed-image-item').length === 0) {
                            $('#seed-images-grid').html('<div class="no-seed-images"><p>No seed images uploaded yet. Click "Upload New Seed Image" to get started.</p></div>');
                        }
                    });
                    
                    self.showSuccess('Seed image deleted successfully');
                } else {
                    seedItem.removeClass('loading');
                    console.error('Delete seed image failed:', response);
                    self.showError('Failed to delete seed image: ' + (response.data?.message || 'Unknown error'));
                }
            })
            .catch(function(error) {
                console.error('Error deleting seed image:', error);
                seedItem.removeClass('loading');
                self.showError('Failed to delete seed image');
            });
        },

        /**
         * Save seed image (upload or update)
         */
        saveSeedImage: function() {
            console.log('Saving seed image');
            
            var self = this;
            var seedId = $('#seed-image-id').val();
            var isEdit = !!seedId;
            var form = $('#seed-image-form')[0];
            var formData = new FormData(form);
            
            // Validate required fields
            var productName = $('#seed-product-name').val().trim();
            if (!productName) {
                self.showError('Product name is required');
                $('#seed-product-name').focus();
                return;
            }
            
            // For new uploads, validate file
            if (!isEdit) {
                var fileInput = $('#seed-image-file')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    self.showError('Please select a PNG image file');
                    $('#seed-image-file').focus();
                    return;
                }
                
                var file = fileInput.files[0];
                
                // Validate file type
                if (file.type !== 'image/png') {
                    self.showError('Only PNG files are allowed');
                    $('#seed-image-file').focus();
                    return;
                }
                
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    self.showError('File size must be less than 10MB');
                    $('#seed-image-file').focus();
                    return;
                }
            }
            
            // Add AJAX data
            formData.append('action', isEdit ? 'ai_blog_update_seed_image' : 'ai_blog_upload_seed_image');
            formData.append('nonce', aiBlogAjax.nonce);
            
            // Show loading state
            var submitBtn = $('#save-seed-image');
            var originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text(isEdit ? 'Updating...' : 'Uploading...');
            
            // Make AJAX request with FormData
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 60000 // 60 seconds for file upload
            })
            .done(function(response) {
                console.log('Save seed image response:', response);
                console.log('Response type:', typeof response);
                console.log('Response.success:', response.success);
                console.log('Response.data:', response.data);
                
                // Reset button state
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    console.log('SUCCESS PATH: Closing modal and showing success message');
                    self.closeSeedImageModal();
                    self.showSuccess(isEdit ? 'Seed image updated successfully' : 'Seed image uploaded successfully');
                    
                    // Reload the page to show updated seed images
                    setTimeout(function() {
                        console.log('SUCCESS PATH: Reloading page');
                        window.location.reload();
                    }, 1000);
                } else {
                    console.log('FAILURE PATH: response.success is false');
                    console.log('Error message:', response.data?.message || 'Unknown error');
                    self.showError('Failed to save seed image: ' + (response.data?.message || 'Unknown error'));
                }
            })
            .fail(function(xhr, status, error) {
                console.error('AJAX error saving seed image:', { xhr, status, error });
                
                // Reset button state
                submitBtn.prop('disabled', false).text(originalText);
                
                if (status === 'timeout') {
                    self.showError('Upload timeout. Please try again with a smaller file.');
                } else {
                    self.showError('Failed to save seed image. Please try again.');
                }
            });
        },

        /**
         * Preview seed image file
         */
        previewSeedImage: function(fileInput) {
            console.log('Previewing seed image file');
            
            var self = this;
            
            if (!fileInput.files || !fileInput.files[0]) {
                $('#image-preview').hide();
                return;
            }
            
            var file = fileInput.files[0];
            
            // Validate file type
            if (file.type !== 'image/png') {
                self.showError('Only PNG files are allowed');
                fileInput.value = '';
                $('#image-preview').hide();
                return;
            }
            
            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                self.showError('File size must be less than 10MB');
                fileInput.value = '';
                $('#image-preview').hide();
                return;
            }
            
            // Create preview
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#preview-image').attr('src', e.target.result);
                $('#image-preview').show();
            };
            reader.readAsDataURL(file);
        },

        /**
         * Toggle inclusion options based on usage category
         */
        toggleInclusionOptions: function(usage) {
            console.log('Toggling inclusion options for usage:', usage);
            
            var $contentRow = $('#context-always-content').closest('tr');
            var $imagesRow = $('#context-always-images').closest('tr');
            
            // Hide both by default
            $contentRow.hide();
            $imagesRow.hide();
            
            // Show appropriate checkbox based on usage category
            if (usage === 'content') {
                $contentRow.show();
                $imagesRow.hide();
            } else if (usage === 'images') {
                $contentRow.hide();
                $imagesRow.show();
            } else if (usage === 'ideas') {
                // Ideas don't use always_include options
                $contentRow.hide();
                $imagesRow.hide();
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('DOM ready, initializing AI Blog Contexts');
        window.aiBlogContexts.init();
    });

})(jQuery); 