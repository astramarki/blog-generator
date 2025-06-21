/**
 * Brand Features Admin JavaScript
 *
 * @package AI_Blog_Generator
 */

(function($) {
    'use strict';

    /**
     * Brand Features Manager
     */
    window.aiBlogBrandFeatures = {
        
        currentPage: 1,
        currentSearch: '',
        currentCategory: '',
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadFeatures();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Add new feature
            $('#add-new-brand-feature').on('click', function() {
                self.showModal();
            });
            
            // Search
            $('#brand-feature-search').on('keyup', _.debounce(function() {
                self.currentSearch = $(this).val();
                self.currentPage = 1;
                self.loadFeatures();
            }, 300));
            
            // Category filter
            $('#brand-feature-category-filter').on('change', function() {
                self.currentCategory = $(this).val();
                self.currentPage = 1;
                self.loadFeatures();
            });
            
            // Modal events
            $('.ai-blog-modal-close, #cancel-brand-feature').on('click', function() {
                self.closeModal();
            });
            
            // Form submit
            $('#brand-feature-form').on('submit', function(e) {
                e.preventDefault();
                self.saveFeature();
            });
            
            // Delegated events for dynamic content
            $(document).on('click', '.edit-brand-feature', function() {
                var featureId = $(this).data('id');
                self.editFeature(featureId);
            });
            
            $(document).on('click', '.toggle-brand-feature', function() {
                var featureId = $(this).data('id');
                self.toggleFeature(featureId);
            });
            
            $(document).on('click', '.delete-brand-feature', function() {
                var featureId = $(this).data('id');
                var featureName = $(this).data('name');
                self.deleteFeature(featureId, featureName);
            });
            
            // Pagination
            $(document).on('click', '#brand-features-pagination a', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page) {
                    self.currentPage = page;
                    self.loadFeatures();
                }
            });
        },
        
        /**
         * Load brand features
         */
        loadFeatures: function() {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_blog_get_brand_features',
                    nonce: aiBlogAjax.nonce,
                    page: self.currentPage,
                    search: self.currentSearch,
                    category: self.currentCategory
                },
                beforeSend: function() {
                    $('#brand-features-grid').html('<div class="ai-blog-loading"><span class="spinner is-active"></span><span>Loading brand features...</span></div>');
                },
                success: function(response) {
                    if (response.success) {
                        self.renderFeatures(response.data.features);
                        self.renderPagination(response.data.pagination);
                    } else {
                        self.showError(response.data || 'Failed to load brand features');
                    }
                },
                error: function() {
                    self.showError('Failed to load brand features');
                }
            });
        },
        
        /**
         * Render brand features
         */
        renderFeatures: function(features) {
            var self = this;
            var html = '';
            
            if (features.length === 0) {
                html = '<div class="ai-blog-no-items">No brand features found.</div>';
            } else {
                features.forEach(function(feature) {
                    var categoryLabel = self.getCategoryLabel(feature.category);
                    var categoryClass = 'category-' + feature.category.replace('_', '-');
                    
                    html += '<div class="ai-blog-brand-feature-card" data-id="' + feature.id + '">';
                    html += '<div class="brand-feature-header">';
                    html += '<h3>' + self.escapeHtml(feature.name) + '</h3>';
                    html += '<span class="brand-feature-category ' + categoryClass + '">' + categoryLabel + '</span>';
                    html += '</div>';
                    
                    if (feature.description) {
                        html += '<div class="brand-feature-description">' + self.escapeHtml(feature.description) + '</div>';
                    }
                    
                    html += '<div class="brand-feature-url">';
                    html += '<a href="' + feature.url + '" target="_blank">' + feature.url + '</a>';
                    html += '</div>';
                    
                    html += '<div class="brand-feature-meta">';
                    html += '<span class="brand-feature-status ' + (feature.active == 1 ? 'active' : 'inactive') + '">';
                    html += feature.active == 1 ? 'Active' : 'Inactive';
                    html += '</span>';
                    html += '</div>';
                    
                    html += '<div class="brand-feature-actions">';
                    html += '<button type="button" class="button-icon edit-brand-feature" data-id="' + feature.id + '" title="Edit">';
                    html += '<span class="dashicons dashicons-edit"></span>';
                    html += '</button>';
                    html += '<button type="button" class="button-icon toggle-brand-feature" data-id="' + feature.id + '" title="' + (feature.active == 1 ? 'Deactivate' : 'Activate') + '">';
                    html += '<span class="dashicons dashicons-' + (feature.active == 1 ? 'hidden' : 'visibility') + '"></span>';
                    html += '</button>';
                    html += '<button type="button" class="button-icon delete-brand-feature" data-id="' + feature.id + '" data-name="' + self.escapeHtml(feature.name) + '" title="Delete">';
                    html += '<span class="dashicons dashicons-trash"></span>';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                });
            }
            
            $('#brand-features-grid').html(html);
        },
        
        /**
         * Render pagination
         */
        renderPagination: function(pagination) {
            if (pagination.total_pages <= 1) {
                $('#brand-features-pagination').empty();
                return;
            }
            
            var html = '<div class="tablenav-pages">';
            html += '<span class="displaying-num">' + pagination.total + ' items</span>';
            html += '<span class="pagination-links">';
            
            // Previous
            if (pagination.current_page > 1) {
                html += '<a class="prev-page" href="#" data-page="' + (pagination.current_page - 1) + '">&laquo; Previous</a>';
            }
            
            // Page numbers
            for (var i = 1; i <= pagination.total_pages; i++) {
                if (i === pagination.current_page) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }
            
            // Next
            if (pagination.current_page < pagination.total_pages) {
                html += '<a class="next-page" href="#" data-page="' + (pagination.current_page + 1) + '">Next &raquo;</a>';
            }
            
            html += '</span>';
            html += '</div>';
            
            $('#brand-features-pagination').html(html);
        },
        
        /**
         * Show modal
         */
        showModal: function(feature) {
            if (feature) {
                $('#brand-feature-modal-title').text('Edit Brand Feature');
                $('#save-brand-feature').text('Update Feature');
                $('#brand-feature-id').val(feature.id);
                $('#brand-feature-name').val(feature.name);
                $('#brand-feature-description').val(feature.description || '');
                $('#brand-feature-category').val(feature.category);
                $('#brand-feature-url').val(feature.url);
                $('#brand-feature-active').prop('checked', feature.active == 1);
            } else {
                $('#brand-feature-modal-title').text('Add New Brand Feature');
                $('#save-brand-feature').text('Save Feature');
                $('#brand-feature-form')[0].reset();
                $('#brand-feature-id').val('');
            }
            
            $('#brand-feature-modal').addClass('ai-blog-modal-active');
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#brand-feature-modal').removeClass('ai-blog-modal-active');
        },
        
        /**
         * Edit feature
         */
        editFeature: function(featureId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_blog_get_brand_feature',
                    nonce: aiBlogAjax.nonce,
                    feature_id: featureId
                },
                success: function(response) {
                    if (response.success) {
                        self.showModal(response.data.feature);
                    } else {
                        self.showError(response.data || 'Failed to load brand feature');
                    }
                },
                error: function() {
                    self.showError('Failed to load brand feature');
                }
            });
        },
        
        /**
         * Save feature
         */
        saveFeature: function() {
            var self = this;
            var featureId = $('#brand-feature-id').val();
            var isNew = !featureId;
            
            var data = {
                action: isNew ? 'ai_blog_create_brand_feature' : 'ai_blog_update_brand_feature',
                nonce: aiBlogAjax.nonce,
                name: $('#brand-feature-name').val(),
                description: $('#brand-feature-description').val(),
                category: $('#brand-feature-category').val(),
                url: $('#brand-feature-url').val(),
                active: $('#brand-feature-active').is(':checked') ? 1 : 0
            };
            
            if (!isNew) {
                data.feature_id = featureId;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    $('#save-brand-feature').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.closeModal();
                        self.loadFeatures();
                    } else {
                        self.showError(response.data || 'Failed to save brand feature');
                    }
                },
                error: function() {
                    self.showError('Failed to save brand feature');
                },
                complete: function() {
                    $('#save-brand-feature').prop('disabled', false).text(isNew ? 'Save Feature' : 'Update Feature');
                }
            });
        },
        
        /**
         * Toggle feature active status
         */
        toggleFeature: function(featureId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_toggle_brand_feature',
                    nonce: aiBlogAjax.nonce,
                    feature_id: featureId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.loadFeatures();
                    } else {
                        self.showError(response.data || 'Failed to toggle brand feature');
                    }
                },
                error: function() {
                    self.showError('Failed to toggle brand feature');
                }
            });
        },
        
        /**
         * Delete feature
         */
        deleteFeature: function(featureId, featureName) {
            var self = this;
            
            if (!confirm('Are you sure you want to delete "' + featureName + '"? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_delete_brand_feature',
                    nonce: aiBlogAjax.nonce,
                    feature_id: featureId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.loadFeatures();
                    } else {
                        self.showError(response.data || 'Failed to delete brand feature');
                    }
                },
                error: function() {
                    self.showError('Failed to delete brand feature');
                }
            });
        },
        
        /**
         * Get category label
         */
        getCategoryLabel: function(category) {
            var labels = {
                'informational_page': 'Informational Page',
                'document': 'Document',
                'image': 'Image',
                'video': 'Video'
            };
            return labels[category] || category;
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            var $notice = $('#brand-feature-notice');
            $notice.removeClass('notice-error').addClass('notice-success');
            $notice.find('p').text(message);
            $notice.fadeIn();
            
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var $notice = $('#brand-feature-notice');
            $notice.removeClass('notice-success').addClass('notice-error');
            $notice.find('p').text(message);
            $notice.fadeIn();
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.ai-blog-brand-features-grid').length) {
            aiBlogBrandFeatures.init();
        }
    });
    
})(jQuery); 