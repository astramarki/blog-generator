/**
 * Drafted Posts JavaScript
 * 
 * Handles all AJAX operations and user interactions for the Drafted Posts page.
 * Matches the functionality and style of the approved ideas page.
 */

(function($) {
    'use strict';

    // Global state management
    window.DraftedPosts = {
        initialized: false,
        selectedPosts: [],
        currentFilter: '',
        draftedPosts: [],
        
        // Statistics
        stats: {
            drafts: 0,
            scheduled: 0,
            published: 0,
            totalCost: 0
        }
    };

    console.log('📄 Drafted Posts JavaScript loaded');

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (window.DraftedPosts && window.DraftedPosts.initialized) {
            console.log('⚠️ Drafted Posts already initialized, skipping');
            return;
        }
        
        console.log('🚀 Initializing Drafted Posts...');
        initializeDraftedPosts();
    });

    /**
     * Initialize the Drafted Posts interface
     */
    function initializeDraftedPosts() {
        console.log('🔧 Starting Drafted Posts initialization');
        
        // Verify dependencies
        if (!verifyDependencies()) {
            console.error('❌ Dependencies verification failed');
            return;
        }

        // Load initial data
        loadDraftedPosts();
        
        // Bind event handlers
        bindEventHandlers();
        
        // Initialize filter
        initializeFilter();
        
        window.DraftedPosts.initialized = true;
        console.log('✅ Drafted Posts initialized successfully');
    }

    /**
     * Verify required dependencies
     */
    function verifyDependencies() {
        console.log('🔍 Verifying dependencies...');
        
        // First check if ai_blog_admin exists
        if (typeof ai_blog_admin === 'undefined') {
            console.error('❌ ai_blog_admin object not found');
            showNotice('Configuration error: Admin object not loaded', 'error');
            return false;
        }
        
        const dependencies = {
            jQuery: typeof $ !== 'undefined',
            ajaxurl: ai_blog_admin && ai_blog_admin.ajaxurl,
            nonce: ai_blog_admin && ai_blog_admin.nonce,
            Bootstrap: typeof bootstrap !== 'undefined' && bootstrap.Modal
        };

        console.log('Dependencies check:', dependencies);
        console.log('ai_blog_admin object:', ai_blog_admin);

        for (const [dep, available] of Object.entries(dependencies)) {
            if (!available) {
                console.error(`❌ Missing dependency: ${dep}`);
                if (dep !== 'Bootstrap') {
                    showNotice(`Missing dependency: ${dep}`, 'error');
                    return false;
                }
            }
        }

        console.log('✅ All dependencies verified');
        return true;
    }

    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        console.log('🔗 Binding event handlers...');
        
        // Unbind existing handlers to prevent duplicates
        $('#refreshDrafts').off('click.draftedPosts');
        $('#selectAllCheckbox').off('change.draftedPosts');
        $('#draftsTableBody').off('change.draftedPosts', '.post-checkbox');
        $('#publishAllDrafts').off('click.draftedPosts');
        $('#scheduleAllDrafts').off('click.draftedPosts');
        $('#deleteSelected').off('click.draftedPosts');
        $('#draftsTableBody').off('click.draftedPosts', '.publish-now');
        $('#draftsTableBody').off('click.draftedPosts', '.schedule-post');
        $('#confirmPublish').off('click.draftedPosts');
        $('#confirmScheduleAll').off('click.draftedPosts');
        $('#confirmDelete').off('click.draftedPosts');
        $('#statusFilter').off('change.draftedPosts');

        // Refresh button
        $('#refreshDrafts').on('click.draftedPosts', handleRefresh);
        
        // Select all checkbox
        $('#selectAllCheckbox').on('change.draftedPosts', handleSelectAll);
        
        // Individual checkboxes (delegated)
        $('#draftsTableBody').on('change.draftedPosts', '.post-checkbox', handleIndividualSelect);
        
        // Bulk actions
        $('#publishAllDrafts').on('click.draftedPosts', handlePublishAll);
        $('#scheduleAllDrafts').on('click.draftedPosts', handleScheduleAll);
        $('#deleteSelected').on('click.draftedPosts', handleDeleteSelected);
        
        // Individual actions (delegated)
        $('#draftsTableBody').on('click.draftedPosts', '.publish-now', handlePublishNow);
        $('#draftsTableBody').on('click.draftedPosts', '.schedule-post', handleSchedulePost);
        $('#draftsTableBody').on('click.draftedPosts', '.download-prompts', handleDownloadPrompts);
        
        // Modal confirmations
        $('#confirmPublish').on('click.draftedPosts', handleConfirmPublish);
        $('#confirmScheduleAll').on('click.draftedPosts', handleConfirmScheduleAll);
        $('#confirmDelete').on('click.draftedPosts', handleConfirmDelete);
        
        // Filter
        $('#statusFilter').on('change.draftedPosts', handleFilterChange);

        console.log('✅ Event handlers bound successfully');
    }

    /**
     * Load drafted posts from server
     */
    function loadDraftedPosts() {
        console.log('📊 Loading drafted posts...');
        showLoading(true);

        const ajaxData = {
            action: 'ai_blog_get_drafted_posts',
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 AJAX Request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            timeout: 30000,
            success: function(response) {
                console.log('📥 AJAX Response:', response);
                handleLoadPostsResponse(response);
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                handleLoadPostsError(xhr, status, error);
            }
        });
    }

    /**
     * Handle successful posts load response
     */
    function handleLoadPostsResponse(response) {
        console.log('✅ Processing posts response...');
        
        try {
            let data = response;
            if (typeof response === 'string') {
                data = JSON.parse(response);
            }

            if (data.success) {
                console.log(`📋 Loaded ${data.data.posts.length} drafted posts`);
                
                window.DraftedPosts.draftedPosts = data.data.posts;
                updatePostsDisplay(data.data.posts);
                updateStatistics(data.data.statistics);
            } else {
                console.error('❌ Server returned error:', data.message);
                showNotice(data.message || 'Failed to load drafted posts', 'error');
                showEmptyState();
            }
        } catch (e) {
            console.error('❌ Error parsing response:', e);
            showNotice('Error parsing server response', 'error');
            showEmptyState();
        }

        showLoading(false);
    }

    /**
     * Handle posts load error
     */
    function handleLoadPostsError(xhr, status, error) {
        console.error('❌ Failed to load drafted posts');
        showNotice('Failed to load drafted posts. Please try again.', 'error');
        showEmptyState();
        showLoading(false);
    }

    /**
     * Update posts display in table
     */
    function updatePostsDisplay(posts) {
        console.log(`🔄 Updating display with ${posts.length} posts`);
        
        const tbody = $('#draftsTableBody');
        const container = $('#draftsTableContainer');
        const emptyState = $('#emptyState');
        
        // Apply filter if active
        let filteredPosts = posts;
        if (window.DraftedPosts.currentFilter) {
            filteredPosts = posts.filter(function(post) {
                return post.status === window.DraftedPosts.currentFilter;
            });
        }
        
        if (filteredPosts.length === 0) {
            console.log('📭 No posts to display');
            showEmptyState();
            return;
        }

        // Show table and hide empty state
        container.removeClass('d-none');
        emptyState.addClass('d-none');
        
        // Update count
        $('#draftCount').text(filteredPosts.length);
        
        // Keep existing HTML if posts already loaded (initial page load)
        if (tbody.find('tr').length === 0) {
            // Only rebuild if table is empty
            tbody.empty();
            filteredPosts.forEach(function(post) {
                const row = createPostRow(post);
                tbody.append(row);
            });
        }
        
        console.log('✅ Posts display updated successfully');
    }

    /**
     * Create table row for a post
     */
    function createPostRow(post) {
        console.log(`🏗️ Creating row for post: ${post.post_title}`);
        
        const categories = post.categories || [];
        const categoryBadges = categories.map(cat => 
            `<span class="badge bg-secondary me-1">${escapeHtml(cat.name)}</span>`
        ).join('');
        
        const isSelected = window.DraftedPosts.selectedPosts.includes(post.id);
        
        return `
            <tr data-post-id="${post.post_id}" 
                data-blog-id="${post.id}"
                data-idea-id="${post.idea_id}"
                data-status="${post.status}"
                class="${isSelected ? 'table-active' : ''}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input post-checkbox" type="checkbox" 
                               value="${post.id}" ${isSelected ? 'checked' : ''}>
                    </div>
                </td>
                <td>
                    <div class="post-title-cell">
                        <strong>
                            <a href="${post.edit_link}" class="text-decoration-none" target="_blank">
                                ${escapeHtml(post.post_title)}
                            </a>
                        </strong>
                        <br>
                        <small class="text-muted">
                            From: ${escapeHtml(post.idea_title)}
                        </small>
                    </div>
                </td>
                <td>
                    ${categoryBadges || '<span class="text-muted">Uncategorized</span>'}
                </td>
                <td>
                    <small>${formatDate(post.created_at)}</small>
                </td>
                <td>
                    <input type="datetime-local" class="form-control form-control-sm schedule-time" 
                        data-blog-id="${post.id}"
                        value="${formatDateTimeLocal(post.scheduled_time || getDefaultScheduleTime())}" />
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success btn-sm publish-now" 
                            data-post-id="${post.post_id}"
                            data-blog-id="${post.id}"
                            title="Publish Now">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-warning btn-sm schedule-post" 
                            data-post-id="${post.post_id}"
                            data-blog-id="${post.id}"
                            title="Schedule">
                            <i class="fas fa-clock"></i>
                        </button>
                        <a href="${post.preview_link}" 
                           class="btn btn-outline-secondary btn-sm" target="_blank"
                           title="Preview">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" class="btn btn-info btn-sm download-prompts" 
                            data-idea-id="${post.idea_id}"
                            title="Download Prompts">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Update statistics display
     */
    function updateStatistics(stats) {
        console.log('📈 Updating statistics:', stats);
        
        $('#stat-drafts').text(stats.drafts || 0);
        $('#stat-scheduled').text(stats.scheduled || 0);
        $('#stat-published').text(stats.published || 0);
        
        window.DraftedPosts.stats = stats;
    }

    /**
     * Initialize filter
     */
    function initializeFilter() {
        const savedFilter = localStorage.getItem('draftedPostsFilter');
        if (savedFilter) {
            $('#statusFilter').val(savedFilter);
            window.DraftedPosts.currentFilter = savedFilter;
        }
    }

    /**
     * Handle manual refresh
     */
    function handleRefresh() {
        console.log('🔄 Manual refresh triggered');
        loadDraftedPosts();
    }

    /**
     * Handle select all checkbox
     */
    function handleSelectAll() {
        const isChecked = $(this).prop('checked');
        console.log(`☑️ Select all: ${isChecked}`);
        
        $('.post-checkbox').prop('checked', isChecked);
        
        if (isChecked) {
            // Add all visible post IDs to selected
            window.DraftedPosts.selectedPosts = [];
            $('.post-checkbox').each(function() {
                window.DraftedPosts.selectedPosts.push($(this).val());
            });
        } else {
            window.DraftedPosts.selectedPosts = [];
        }
        
        updateBulkActionButtons();
    }

    /**
     * Handle individual checkbox selection
     */
    function handleIndividualSelect() {
        const postId = $(this).val();
        const isChecked = $(this).prop('checked');
        
        if (isChecked) {
            if (!window.DraftedPosts.selectedPosts.includes(postId)) {
                window.DraftedPosts.selectedPosts.push(postId);
            }
        } else {
            window.DraftedPosts.selectedPosts = window.DraftedPosts.selectedPosts.filter(id => id !== postId);
        }
        
        updateBulkActionButtons();
        updateSelectAllCheckbox();
    }

    /**
     * Update bulk action buttons state
     */
    function updateBulkActionButtons() {
        const hasSelection = window.DraftedPosts.selectedPosts.length > 0;
        $('#publishAllDrafts, #scheduleAllDrafts, #deleteSelected').prop('disabled', !hasSelection);
    }

    /**
     * Update select all checkbox state
     */
    function updateSelectAllCheckbox() {
        const totalCheckboxes = $('.post-checkbox').length;
        const checkedCheckboxes = $('.post-checkbox:checked').length;
        
        $('#selectAllCheckbox').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
    }

    /**
     * Show Bootstrap modal safely
     */
    function showModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error(`Modal not found: ${modalId}`);
            return;
        }
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else if ($.fn.modal) {
            $('#' + modalId).modal('show');
        } else {
            console.error('Bootstrap modal functionality not available');
            // Fallback: show modal manually
            modalElement.style.display = 'block';
            modalElement.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }
    
    /**
     * Hide Bootstrap modal safely
     */
    function hideModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
        } else if ($.fn.modal) {
            $('#' + modalId).modal('hide');
        } else {
            // Fallback: hide modal manually
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Remove backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) backdrop.remove();
        }
    }

    /**
     * Handle publish all button
     */
    function handlePublishAll() {
        console.log('📢 Publish all triggered');
        
        const selectedCount = window.DraftedPosts.selectedPosts.length;
        if (selectedCount === 0) {
            showNotice('Please select posts to publish', 'warning');
            return;
        }
        
        $('#publishCount').text(selectedCount);
        showModal('publishConfirmModal');
    }

    /**
     * Handle schedule all button
     */
    function handleScheduleAll() {
        console.log('⏰ Schedule all triggered');
        
        if (window.DraftedPosts.selectedPosts.length === 0) {
            showNotice('Please select posts to schedule', 'warning');
            return;
        }
        
        showModal('scheduleAllModal');
    }

    /**
     * Handle delete selected button
     */
    function handleDeleteSelected() {
        console.log('🗑️ Delete selected triggered');
        
        const selectedCount = window.DraftedPosts.selectedPosts.length;
        if (selectedCount === 0) {
            showNotice('Please select posts to delete', 'warning');
            return;
        }
        
        $('#deleteCount').text(selectedCount);
        showModal('deleteConfirmModal');
    }

    /**
     * Handle publish now button
     */
    function handlePublishNow() {
        const blogId = $(this).data('blog-id');
        const postId = $(this).data('post-id');
        
        console.log(`📢 Publishing post immediately: ${blogId}`);
        
        publishPost(blogId, postId);
    }

    /**
     * Handle schedule post button
     */
    function handleSchedulePost() {
        const blogId = $(this).data('blog-id');
        const postId = $(this).data('post-id');
        const scheduleTime = $(this).closest('tr').find('.schedule-time').val();
        
        console.log(`⏰ Scheduling post: ${blogId} for ${scheduleTime}`);
        
        schedulePost(blogId, postId, scheduleTime);
    }

    /**
     * Handle confirm publish
     */
    function handleConfirmPublish() {
        console.log('✅ Confirming bulk publish');
        hideModal('publishConfirmModal');
        
        publishMultiplePosts(window.DraftedPosts.selectedPosts);
    }

    /**
     * Handle confirm schedule all
     */
    function handleConfirmScheduleAll() {
        console.log('✅ Confirming bulk schedule');
        
        const startDate = $('#scheduleStartDate').val();
        const days = parseInt($('#scheduleDays').val());
        const timeFrom = $('#scheduleTimeFrom').val();
        const timeTo = $('#scheduleTimeTo').val();
        
        hideModal('scheduleAllModal');
        
        scheduleMultiplePosts(window.DraftedPosts.selectedPosts, {
            startDate: startDate,
            days: days,
            timeFrom: timeFrom,
            timeTo: timeTo
        });
    }

    /**
     * Handle confirm delete
     */
    function handleConfirmDelete() {
        console.log('✅ Confirming delete');
        hideModal('deleteConfirmModal');
        
        deleteMultiplePosts(window.DraftedPosts.selectedPosts);
    }

    /**
     * Handle filter change
     */
    function handleFilterChange() {
        const filter = $(this).val();
        console.log(`🔍 Filter changed to: ${filter || 'All'}`);
        
        window.DraftedPosts.currentFilter = filter;
        localStorage.setItem('draftedPostsFilter', filter);
        
        updatePostsDisplay(window.DraftedPosts.draftedPosts);
    }

    /**
     * Publish a single post
     */
    function publishPost(blogId, postId) {
        const ajaxData = {
            action: 'ai_blog_publish_post',
            blog_id: blogId,
            post_id: postId,
            nonce: ai_blog_admin.nonce
        };

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice('Post published successfully!', 'success');
                    removePostRow(blogId);
                    updateStatistics(response.data.statistics);
                } else {
                    showNotice(response.data || 'Failed to publish post', 'error');
                }
            },
            error: function() {
                showNotice('Failed to publish post', 'error');
            }
        });
    }

    /**
     * Schedule a single post
     */
    function schedulePost(blogId, postId, scheduleTime) {
        const ajaxData = {
            action: 'ai_blog_schedule_post',
            blog_id: blogId,
            post_id: postId,
            scheduled_time: scheduleTime,
            nonce: ai_blog_admin.nonce
        };

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice('Post scheduled successfully!', 'success');
                    $(`tr[data-blog-id="${blogId}"]`).attr('data-status', 'scheduled');
                    updateStatistics(response.data.statistics);
                } else {
                    showNotice(response.data || 'Failed to schedule post', 'error');
                }
            },
            error: function() {
                showNotice('Failed to schedule post', 'error');
            }
        });
    }

    /**
     * Publish multiple posts
     */
    function publishMultiplePosts(blogIds) {
        console.log(`📢 Publishing ${blogIds.length} posts`);
        
        const ajaxData = {
            action: 'ai_blog_bulk_publish_posts',
            blog_ids: blogIds,
            nonce: ai_blog_admin.nonce
        };

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice(`${response.data.published} posts published successfully!`, 'success');
                    
                    // Remove published posts from display
                    blogIds.forEach(function(blogId) {
                        removePostRow(blogId);
                    });
                    
                    // Clear selection
                    window.DraftedPosts.selectedPosts = [];
                    updateBulkActionButtons();
                    updateStatistics(response.data.statistics);
                } else {
                    showNotice(response.data || 'Failed to publish posts', 'error');
                }
            },
            error: function() {
                showNotice('Failed to publish posts', 'error');
            }
        });
    }

    /**
     * Schedule multiple posts
     */
    function scheduleMultiplePosts(blogIds, options) {
        console.log(`⏰ Scheduling ${blogIds.length} posts`, options);
        
        const ajaxData = {
            action: 'ai_blog_bulk_schedule_posts',
            blog_ids: blogIds,
            schedule_options: options,
            nonce: ai_blog_admin.nonce
        };

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice(`${response.data.scheduled} posts scheduled successfully!`, 'success');
                    
                    // Update status for scheduled posts
                    blogIds.forEach(function(blogId) {
                        $(`tr[data-blog-id="${blogId}"]`).attr('data-status', 'scheduled');
                    });
                    
                    // Clear selection
                    window.DraftedPosts.selectedPosts = [];
                    updateBulkActionButtons();
                    updateStatistics(response.data.statistics);
                } else {
                    showNotice(response.data || 'Failed to schedule posts', 'error');
                }
            },
            error: function() {
                showNotice('Failed to schedule posts', 'error');
            }
        });
    }

    /**
     * Delete multiple posts
     */
    function deleteMultiplePosts(blogIds) {
        console.log(`🗑️ Deleting ${blogIds.length} posts`);
        
        const ajaxData = {
            action: 'ai_blog_delete_posts',
            blog_ids: blogIds,
            nonce: ai_blog_admin.nonce
        };

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice(`${response.data.deleted} posts deleted successfully!`, 'success');
                    
                    // Remove deleted posts from display
                    blogIds.forEach(function(blogId) {
                        removePostRow(blogId);
                    });
                    
                    // Clear selection
                    window.DraftedPosts.selectedPosts = [];
                    updateBulkActionButtons();
                    updateStatistics(response.data.statistics);
                } else {
                    showNotice(response.data || 'Failed to delete posts', 'error');
                }
            },
            error: function() {
                showNotice('Failed to delete posts', 'error');
            }
        });
    }

    /**
     * Remove post row from table with animation
     */
    function removePostRow(blogId) {
        const $row = $(`tr[data-blog-id="${blogId}"]`);
        $row.addClass('removing');
        
        setTimeout(function() {
            $row.remove();
            
            // Check if table is empty
            if ($('#draftsTableBody tr').length === 0) {
                showEmptyState();
            }
        }, 300);
    }

    /**
     * Show loading state
     */
    function showLoading(show) {
        const $loading = $('#loadingState');
        const $container = $('#draftsTableContainer');
        const $empty = $('#emptyState');
        
        if (show) {
            $loading.removeClass('d-none');
            $container.addClass('d-none');
            $empty.addClass('d-none');
        } else {
            $loading.addClass('d-none');
        }
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        $('#draftsTableContainer').addClass('d-none');
        $('#emptyState').removeClass('d-none');
        $('#draftCount').text('0');
    }

    /**
     * Show notice message
     */
    function showNotice(message, type = 'info') {
        const alertClass = {
            'info': 'alert-info',
            'success': 'alert-success',
            'warning': 'alert-warning',
            'error': 'alert-danger'
        }[type] || 'alert-info';

        const notice = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        // Insert notice at the top of the page
        $('.ai-drafted-posts .container-fluid').prepend(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Format date for display
     */
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString();
    }

    /**
     * Format date for datetime-local input
     */
    function formatDateTimeLocal(dateStr) {
        if (!dateStr || dateStr === '0000-00-00 00:00:00') {
            return getDefaultScheduleTime();
        }
        const date = new Date(dateStr);
        return date.toISOString().slice(0, 16);
    }

    /**
     * Get default schedule time (tomorrow at random time)
     */
    function getDefaultScheduleTime() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(9 + Math.floor(Math.random() * 8)); // 9 AM to 5 PM
        tomorrow.setMinutes(Math.floor(Math.random() * 60));
        return tomorrow.toISOString().slice(0, 16);
    }

    /**
     * Handle download prompts button
     */
    function handleDownloadPrompts() {
        const ideaId = $(this).data('idea-id');
        
        console.log(`📥 Downloading prompts for idea: ${ideaId}`);
        
        // Add visual feedback
        const $button = $(this);
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        downloadPrompts(ideaId, function() {
            // Reset button on completion
            $button.prop('disabled', false).html(originalHtml);
        });
    }

    /**
     * Download prompts file
     */
    function downloadPrompts(ideaId, callback) {
        const ajaxData = {
            action: 'ai_blog_download_prompts',
            idea_id: ideaId,
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 Sending AJAX request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                console.log('📥 AJAX response:', response);
                
                if (response.success && response.data) {
                    try {
                        // Create a download link and trigger it
                        const blob = new Blob([response.data.content], { type: 'text/plain' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename || `idea_${ideaId}_prompts.txt`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        console.log('✅ Prompts file download triggered');
                        showNotice('Prompts file downloaded successfully!', 'success');
                    } catch (error) {
                        console.error('❌ Error creating download:', error);
                        showNotice('Failed to create download file.', 'error');
                    }
                } else {
                    console.error('❌ Server returned error:', response);
                    const message = (response.data && response.data.message) ? response.data.message : 
                                   response.data || 'Failed to download prompts file';
                    showNotice(message, 'error');
                }
                
                if (callback) callback();
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    responseJSON: xhr.responseJSON
                });
                
                let errorMessage = 'Failed to download prompts file';
                
                // Try to get error message from response
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    if (typeof xhr.responseJSON.data === 'string') {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                }
                
                showNotice(errorMessage, 'error');
                
                if (callback) callback();
            }
        });
    }

})(jQuery); 