/**
 * Approved Ideas V2 JavaScript
 * 
 * Handles all AJAX operations, auto-refresh, and user interactions
 * for the Approved Ideas V2 page with extensive console logging.
 */

(function($) {
    'use strict';

    // Global state management
    window.ApprovedIdeasV2 = {
        initialized: false,
        autoRefreshEnabled: true,
        refreshTimer: null,
        refreshInterval: 5000, // 5 seconds
        currentIdeas: [],
        selectedIdeas: [],
        isRefreshing: false,
        logRefreshTimer: null,
        fastRefreshTimer: null,
        submittingIdeas: [],
        hideCompleted: true, // Default to hiding completed
        
        // Configuration
        config: {
            refreshInterval: 5000,
            maxRetries: 3,
            retryDelay: 2000
        },
        
        // Statistics
        stats: {
            approved: 0,
            generating: 0,
            generated: 0,
            denied: 0
        }
    };

    console.log('📱 Approved Ideas V2 JavaScript loaded');

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Prevent double initialization
        
        if (window.ApprovedIdeasV2 && window.ApprovedIdeasV2.initialized) {
            console.log('⚠️ Approved Ideas V2 already initialized, skipping duplicate initialization');
            return;
        }
        
        console.log('🚀 Initializing Approved Ideas V2...');
        initializeApprovedIdeas();
    });

    /**
     * Initialize the Approved Ideas interface
     */
    function initializeApprovedIdeas() {
        // Prevent double initialization
        if (window.ApprovedIdeasV2 && window.ApprovedIdeasV2.initialized) {
            console.log('⚠️ Approved Ideas V2 already initialized, skipping');
            return;
        }
        
        console.log('🔧 Starting Approved Ideas V2 initialization');
        
        // Verify required dependencies
        if (!verifyDependencies()) {
            console.error('❌ Dependencies verification failed');
            return;
        }

        // Bind event handlers
        bindEventHandlers();
        
        // Load initial data
        loadApprovedIdeas();
        
        // Start auto-refresh
        startAutoRefresh();
        
        window.ApprovedIdeasV2.initialized = true;
        console.log('✅ Approved Ideas V2 initialized successfully');
    }

    /**
     * Verify required dependencies
     */
    function verifyDependencies() {
        console.log('🔍 Verifying dependencies...');
        
        const dependencies = {
            jQuery: typeof $ !== 'undefined',
            ajaxurl: typeof ajaxurl !== 'undefined' || (ai_blog_admin && ai_blog_admin.ajaxurl),
            nonce: ai_blog_admin && ai_blog_admin.nonce
        };

        console.log('Dependencies check:', dependencies);

        for (const [dep, available] of Object.entries(dependencies)) {
            if (!available) {
                console.error(`❌ Missing dependency: ${dep}`);
                showNotice(`Missing dependency: ${dep}`, 'error');
                return false;
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
        
        // Unbind existing handlers first to prevent duplicates
        $('#toggleAutoRefresh').off('click.approvedIdeasV2');
        $('#refreshIdeas').off('click.approvedIdeasV2');
        $('#selectAllCheckbox').off('change.approvedIdeasV2');
        $('#ideasTableBody').off('change.approvedIdeasV2', '.idea-checkbox');
        $('#bulkSubmitForGeneration').off('click.approvedIdeasV2');
        $('#bulkDenySelected').off('click.approvedIdeasV2');
        $('#ideasTableBody').off('click.approvedIdeasV2', '.btn-generate');
        $('#ideasTableBody').off('click.approvedIdeasV2', '.btn-deny');
        $('#ideasTableBody').off('click.approvedIdeasV2', '.btn-view-details');
        $('#confirmGeneration').off('click.approvedIdeasV2');
        $('#confirmBulkGeneration').off('click.approvedIdeasV2');
        $('#editIdeaForm').off('submit.approvedIdeasV2');
        $('#refreshLogBtn').off('click.approvedIdeasV2');
        $('#cancelGenerationBtn').off('click.approvedIdeasV2');
        $('#cancelAllGenerationsBtn').off('click.approvedIdeasV2');
        $('#resetStuckGenerationsBtn').off('click.approvedIdeasV2');
        $('#logViewerModal').off('hidden.bs.modal.approvedIdeasV2');
        $('#hideCompleteFilter').off('change.approvedIdeasV2');
        $(window).off('beforeunload.approvedIdeasV2');
        $(window).off('focus.approvedIdeasV2');
        $(window).off('blur.approvedIdeasV2');

        // Auto-refresh toggle
        $('#toggleAutoRefresh').on('click.approvedIdeasV2', handleToggleAutoRefresh);
        
        // Manual refresh
        $('#refreshIdeas').on('click.approvedIdeasV2', handleManualRefresh);
        
        // Hide completed filter
        $('#hideCompleteFilter').on('change.approvedIdeasV2', handleHideCompleteFilter);
        
        // Select all checkbox
        $('#selectAllCheckbox').on('change.approvedIdeasV2', handleSelectAll);
        
        // Individual checkboxes (delegated)
        $('#ideasTableBody').on('change.approvedIdeasV2', '.idea-checkbox', handleIndividualSelect);
        
        // Bulk actions
        $('#bulkSubmitForGeneration').on('click.approvedIdeasV2', handleBulkSubmitForGeneration);
        $('#bulkDenySelected').on('click.approvedIdeasV2', handleBulkDenySelected);
        
        // Individual actions (delegated)
        $('#ideasTableBody').on('click.approvedIdeasV2', '.btn-generate', handleIndividualGenerate);
        $('#ideasTableBody').on('click.approvedIdeasV2', '.btn-deny', handleIndividualDeny);
        $('#ideasTableBody').on('click.approvedIdeasV2', '.btn-view-details', handleViewDetails);
        
        // Modal confirmations
        $('#confirmGeneration').on('click.approvedIdeasV2', handleConfirmGeneration);
        $('#confirmBulkGeneration').on('click.approvedIdeasV2', handleConfirmBulkGeneration);
        
        // Edit idea form
        $('#editIdeaForm').on('submit.approvedIdeasV2', handleEditIdeaSubmit);
        
        // Log viewer actions
        $('#refreshLogBtn').on('click.approvedIdeasV2', refreshGenerationLog);
        $('#cancelGenerationBtn').on('click.approvedIdeasV2', handleCancelGeneration);
        
        // Generation management
        $('#cancelAllGenerationsBtn').on('click.approvedIdeasV2', handleCancelAllGenerations);
        $('#resetStuckGenerationsBtn').on('click.approvedIdeasV2', handleResetStuckGenerations);
        
        // Cleanup log refresh timer when log modal is hidden
        $('#logViewerModal').on('hidden.bs.modal.approvedIdeasV2', function() {
            if (window.ApprovedIdeasV2.logRefreshTimer) {
                clearInterval(window.ApprovedIdeasV2.logRefreshTimer);
                window.ApprovedIdeasV2.logRefreshTimer = null;
                console.log('🔄 Log refresh timer stopped (modal hidden)');
            }
        });
        
        // Window events
        $(window).on('beforeunload.approvedIdeasV2', handleBeforeUnload);
        $(window).on('focus.approvedIdeasV2', handleWindowFocus);
        $(window).on('blur.approvedIdeasV2', handleWindowBlur);

        console.log('✅ Event handlers bound successfully');
    }

    /**
     * Load approved ideas from server
     */
    function loadApprovedIdeas() {
        console.log('📊 Loading approved ideas...');
        showLoading(true);

        const ajaxData = {
            action: 'ai_blog_v2_get_approved_ideas',
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
                handleLoadIdeasResponse(response);
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                handleLoadIdeasError(xhr, status, error);
            }
        });
    }

    /**
     * Handle successful ideas load response
     */
    function handleLoadIdeasResponse(response) {
        console.log('✅ Processing ideas response...');
        
        try {
            let data = response;
            if (typeof response === 'string') {
                data = JSON.parse(response);
            }

            if (data.success) {
                console.log(`📋 Loaded ${data.data.ideas.length} approved ideas`);
                
                // Check if any cleanup was performed
                if (data.data.cleanup_performed && data.data.cleanup_performed.cleaned > 0) {
                    console.log('🧹 Automatic cleanup performed:', data.data.cleanup_performed);
                    showNotice(`Automatic cleanup: ${data.data.cleanup_performed.message}`, 'info');
                }
                
                updateIdeasDisplay(data.data.ideas);
                updateStatistics(data.data.statistics);
                window.ApprovedIdeasV2.currentIdeas = data.data.ideas;
            } else {
                console.error('❌ Server returned error:', data.message);
                showNotice(data.message || 'Failed to load approved ideas', 'error');
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
     * Handle ideas load error
     */
    function handleLoadIdeasError(xhr, status, error) {
        console.error('❌ Failed to load approved ideas:', {
            status: status,
            error: error,
            responseText: xhr.responseText
        });
        
        showNotice('Failed to load approved ideas. Please try again.', 'error');
        showEmptyState();
        showLoading(false);
    }

    /**
     * Update ideas display in table
     */
    function updateIdeasDisplay(ideas) {
        console.log(`🔄 Updating display with ${ideas.length} ideas`);
        
        const tbody = $('#ideasTableBody');
        const table = $('#ideasTable');
        const emptyState = $('#emptyState');
        
        // Filter ideas based on hide completed setting
        let filteredIdeas = ideas;
        if (window.ApprovedIdeasV2.hideCompleted) {
            filteredIdeas = ideas.filter(function(idea) {
                return idea.status !== 'generated';
            });
            console.log(`🔽 Filtered out ${ideas.length - filteredIdeas.length} completed ideas`);
        }
        
        if (filteredIdeas.length === 0) {
            console.log('📭 No ideas to display, showing empty state');
            showEmptyState();
            return;
        }

        // Hide empty state and show table
        emptyState.addClass('d-none');
        table.removeClass('d-none');

        // Clear current content
        tbody.empty();
        
        // Remove duplicates from ideas array based on ID
        const uniqueIdeas = [];
        const seenIds = new Set();
        
        filteredIdeas.forEach(function(idea) {
            if (!seenIds.has(idea.id)) {
                seenIds.add(idea.id);
                uniqueIdeas.push(idea);
            } else {
                console.warn(`⚠️ Duplicate idea found and removed: ID ${idea.id}`);
            }
        });
        
        console.log(`🔍 Filtered ${filteredIdeas.length} ideas to ${uniqueIdeas.length} unique ideas`);

        // Add each unique idea
        uniqueIdeas.forEach(function(idea) {
            const row = createIdeaRow(idea);
            tbody.append(row);
        });

        // Update count with filtered unique ideas
        $('#approvedCount').text(uniqueIdeas.length);
        
        // Store all ideas in cache (not just filtered)
        window.ApprovedIdeasV2.currentIdeas = ideas;
        
        // Show table and hide loading/empty states
        table.removeClass('d-none');
        $('#loadingState').addClass('d-none');
        $('#emptyState').addClass('d-none');
        
        // Debug: Check table visibility after explicit show/hide
        const tableVisible = !table.hasClass('d-none');
        const loadingVisible = !$('#loadingState').hasClass('d-none');
        const emptyVisible = !$('#emptyState').hasClass('d-none');
        
        console.log('✅ Ideas display updated successfully');
        console.log(`📊 Table visibility: table=${tableVisible}, loading=${loadingVisible}, empty=${emptyVisible}`);
    }

    /**
     * Create table row for an idea
     */
    function createIdeaRow(idea) {
        console.log(`🏗️ Creating row for idea: ${idea.title}`);
        
        const personaName = getPersonaName(idea.persona_id);
        const statusDisplay = createStatusDisplay(idea);
        const actions = createActionButtons(idea);
        
        const isSelected = window.ApprovedIdeasV2.selectedIdeas.includes(idea.id);
        
        return `
            <tr data-idea-id="${idea.id}" class="${isSelected ? 'table-active' : ''}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input idea-checkbox" type="checkbox" 
                               value="${idea.id}" ${isSelected ? 'checked' : ''}>
                    </div>
                </td>
                <td>
                    <div class="idea-title-cell">
                        <strong>${escapeHtml(idea.title)}</strong>
                        <br>
                        <small class="text-muted">${escapeHtml(truncateText(idea.description, 100))}</small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-info">${escapeHtml(personaName)}</span>
                </td>
                <td>
                    ${statusDisplay}
                </td>
                <td>
                    ${actions}
                </td>
            </tr>
        `;
    }

    /**
     * Create status display (badge or progress bar)
     */
    function createStatusDisplay(idea) {
        console.log(`📊 Creating status display for idea ${idea.id}, status: ${idea.status}, generation_status: ${idea.generation_status}`);
        
        if (idea.status === 'generating') {
            return createProgressBar(idea.generation_status || 'Starting...');
        } else if (idea.status === 'approved') {
            return '<span class="badge status-ready">Ready</span>';
        } else if (idea.status === 'generated') {
            return '<span class="badge status-generated">Complete</span>';
        } else if (idea.status === 'denied') {
            return '<span class="badge status-error">Denied</span>';
        } else if (idea.status === 'failed') {
            // Show failed status with error message but make it retryable
            const errorMsg = idea.generation_error ? ` - ${escapeHtml(idea.generation_error)}` : '';
            return `<span class="badge status-retry">Failed${errorMsg} (Retryable)</span>`;
        } else {
            return `<span class="badge status-queue">${escapeHtml(idea.status)}</span>`;
        }
    }

    /**
     * Create progress bar for generating ideas
     */
    function createProgressBar(statusText) {
        const progressStages = {
            'Compiling Context': 10,
            'Submitting request': 20,
            'Waiting for Content Response': 40,
            'Compiling image prompts': 60,
            'Submitting Images Request': 70,
            'Waiting for images Response': 80,
            'Saving Images': 90,
            'Publishing Post': 95,
            'Complete!': 100
        };
        
        const percentage = progressStages[statusText] || 5;
        
        return `
            <div class="generation-progress">
                <div class="progress-bar" style="width: ${percentage}%"></div>
                <div class="progress-text">${escapeHtml(statusText)}</div>
            </div>
        `;
    }

    /**
     * Create action buttons for an idea
     */
    function createActionButtons(idea) {
        if (idea.status === 'approved' || idea.status === 'failed') {
            // Both approved and failed ideas can be generated (failed = retry)
            const buttonText = idea.status === 'failed' ? 'Retry' : 'Generate';
            const buttonIcon = idea.status === 'failed' ? 'fa-redo' : 'fa-play';
            
            return `
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success btn-sm btn-generate" 
                            data-idea-id="${idea.id}" data-idea-title="${escapeHtml(idea.title)}"
                            title="${buttonText} Blog Post">
                        <i class="fas ${buttonIcon}"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm btn-deny" 
                            data-idea-id="${idea.id}"
                            title="Deny Idea">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-view-details" 
                            data-idea-id="${idea.id}"
                            title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            `;
        } else if (idea.status === 'generating') {
            return `
                <button type="button" class="btn btn-outline-secondary btn-sm btn-view-details" 
                        data-idea-id="${idea.id}"
                        title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            `;
        } else {
            return `
                <button type="button" class="btn btn-outline-secondary btn-sm btn-view-details" 
                        data-idea-id="${idea.id}"
                        title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            `;
        }
    }

    /**
     * Update statistics display
     */
    function updateStatistics(stats) {
        console.log('📈 Updating statistics:', stats);
        
        $('#stat-approved').text(stats.approved || 0);
        $('#stat-generating').text(stats.generating || 0);
        $('#stat-generated').text(stats.generated || 0);
        $('#stat-denied').text(stats.denied || 0);
        
        window.ApprovedIdeasV2.stats = stats;
    }

    /**
     * Handle auto-refresh toggle
     */
    function handleToggleAutoRefresh() {
        console.log('🔄 Toggling auto-refresh...');
        
        if (window.ApprovedIdeasV2.autoRefreshEnabled) {
            stopAutoRefresh();
            $('#toggleAutoRefresh').html('<i class="fas fa-play me-1"></i>Resume Updates');
            $('#refreshStatus').removeClass('bg-success').addClass('bg-danger').text('OFF');
            console.log('⏸️ Auto-refresh paused');
        } else {
            startAutoRefresh();
            $('#toggleAutoRefresh').html('<i class="fas fa-pause me-1"></i>Pause Updates');
            $('#refreshStatus').removeClass('bg-danger').addClass('bg-success').text('ON');
            console.log('▶️ Auto-refresh resumed');
        }
    }

    /**
     * Start auto-refresh timer (now targets only generating ideas)
     */
    function startAutoRefresh() {
        console.log('▶️ Starting targeted auto-refresh timer...');
        
        // Clear any existing timer first to prevent duplicates
        if (window.ApprovedIdeasV2.refreshTimer) {
            console.log('🔄 Clearing existing refresh timer before starting new one');
            clearInterval(window.ApprovedIdeasV2.refreshTimer);
            window.ApprovedIdeasV2.refreshTimer = null;
        }
        
        // Clear any existing fast refresh timer too
        if (window.ApprovedIdeasV2.fastRefreshTimer) {
            console.log('🔄 Clearing existing fast refresh timer');
            clearInterval(window.ApprovedIdeasV2.fastRefreshTimer);
            window.ApprovedIdeasV2.fastRefreshTimer = null;
        }
        
        window.ApprovedIdeasV2.autoRefreshEnabled = true;
        window.ApprovedIdeasV2.refreshTimer = setInterval(function() {
            if (!window.ApprovedIdeasV2.isRefreshing) {
                console.log('🎯 Targeted auto-refresh triggered');
                refreshGeneratingIdeas();
            }
        }, window.ApprovedIdeasV2.refreshInterval);
        
        console.log(`✅ Targeted auto-refresh started with ${window.ApprovedIdeasV2.refreshInterval}ms interval`);
    }

    /**
     * Stop auto-refresh timer
     */
    function stopAutoRefresh() {
        console.log('⏸️ Stopping auto-refresh timer...');
        
        if (window.ApprovedIdeasV2.refreshTimer) {
            clearInterval(window.ApprovedIdeasV2.refreshTimer);
            window.ApprovedIdeasV2.refreshTimer = null;
        }
        
        window.ApprovedIdeasV2.autoRefreshEnabled = false;
        console.log('✅ Auto-refresh stopped');
    }

    /**
     * Handle manual refresh
     */
    function handleManualRefresh() {
        console.log('🔄 Manual refresh triggered');
        refreshIdeas();
    }

    /**
     * Handle hide complete filter toggle
     */
    function handleHideCompleteFilter() {
        const isChecked = $('#hideCompleteFilter').is(':checked');
        console.log(`🔽 Hide completed filter changed to: ${isChecked}`);
        
        window.ApprovedIdeasV2.hideCompleted = isChecked;
        
        // Re-display ideas with new filter setting
        if (window.ApprovedIdeasV2.currentIdeas.length > 0) {
            updateIdeasDisplay(window.ApprovedIdeasV2.currentIdeas);
        }
    }

    /**
     * Refresh ideas data (full reload - only used for manual refresh)
     */
    function refreshIdeas() {
        if (window.ApprovedIdeasV2.isRefreshing) {
            console.log('⏭️ Refresh already in progress, skipping');
            return;
        }
        
        console.log('🔄 Refreshing all ideas data...');
        window.ApprovedIdeasV2.isRefreshing = true;
        
        // Show refresh indicator
        $('#refreshIndicator').addClass('show');
        
        loadApprovedIdeas();
        
        // Hide refresh indicator after a delay
        setTimeout(function() {
            $('#refreshIndicator').removeClass('show');
            window.ApprovedIdeasV2.isRefreshing = false;
        }, 1000);
    }

    /**
     * Refresh status for ideas that are currently generating
     */
    function refreshGeneratingIdeas() {
        if (window.ApprovedIdeasV2.isRefreshing) {
            return;
        }
        
        // Get IDs of ideas that are currently generating
        const generatingIds = getGeneratingIdeaIds();
        
        if (generatingIds.length === 0) {
            return;
        }
        
        console.log(`🎯 Fetching status updates for ${generatingIds.length} generating ideas: ${generatingIds.join(', ')}`);
        
        window.ApprovedIdeasV2.isRefreshing = true;
        
        const ajaxData = {
            action: 'ai_blog_v2_get_idea_status_updates',
            idea_ids: generatingIds,
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 Status Update AJAX Request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            timeout: 15000,
            success: function(response) {
                console.log('📥 Status Update AJAX Response:', response);
                handleStatusUpdateResponse(response, generatingIds);
            },
            error: function(xhr, status, error) {
                console.error('❌ Status Update AJAX Error:', {
                    generatingIds: generatingIds,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                // Don't show error for status update failures as they're background operations
            },
            complete: function() {
                window.ApprovedIdeasV2.isRefreshing = false;
            }
        });
    }

    /**
     * Handle status update response
     */
    function handleStatusUpdateResponse(response, requestedIds) {
        console.log('📥 Processing status update response...');
        
        try {
            let data = response;
            if (typeof response === 'string') {
                data = JSON.parse(response);
            }

            if (data.success && data.data.ideas) {
                const updatedIdeas = data.data.ideas;
                console.log(`📊 Received status updates for ${updatedIdeas.length} ideas`);
                
                // Remove duplicates from the response (just in case)
                const uniqueUpdatedIdeas = [];
                const processedIds = new Set();
                
                updatedIdeas.forEach(function(idea) {
                    if (!processedIds.has(idea.id)) {
                        processedIds.add(idea.id);
                        uniqueUpdatedIdeas.push(idea);
                    } else {
                        console.warn(`⚠️ Duplicate idea in response, skipping: ${idea.id}`);
                    }
                });
                
                console.log(`🔍 Processing ${uniqueUpdatedIdeas.length} unique ideas from response`);
                
                let completedCount = 0;
                let stillGeneratingCount = 0;
                
                // Update each unique idea
                uniqueUpdatedIdeas.forEach(function(idea) {
                    // Update cached data
                    const cachedIndex = window.ApprovedIdeasV2.currentIdeas.findIndex(i => i.id == idea.id);
                    if (cachedIndex !== -1) {
                        const oldStatus = window.ApprovedIdeasV2.currentIdeas[cachedIndex].status;
                        window.ApprovedIdeasV2.currentIdeas[cachedIndex] = idea;
                        
                        // Check if status changed to completed
                        if (oldStatus === 'generating' && idea.status === 'generated') {
                            completedCount++;
                            showNotice(`Blog post "${idea.title}" has been generated successfully!`, 'success');
                        } else if (oldStatus === 'generating' && idea.status === 'failed') {
                            showNotice(`Generation failed for "${idea.title}". Check the error details in view mode.`, 'error');
                        } else if (idea.status === 'generating') {
                            stillGeneratingCount++;
                        }
                    }
                    
                    // Update the row
                    updateIdeaRow(idea);
                });
                
                // Update statistics if provided
                if (data.data.statistics) {
                    updateStatistics(data.data.statistics);
                }
                
                console.log(`✅ Status update completed: ${completedCount} completed, ${stillGeneratingCount} still generating`);
                
                // If no ideas are still generating, stop fast refresh
                if (stillGeneratingCount === 0 && window.ApprovedIdeasV2.fastRefreshTimer) {
                    console.log('🛑 All generations completed, stopping fast refresh');
                    stopFastRefresh();
                }
                
            } else {
                console.warn('⚠️ Status update response was not successful or had no data:', data);
            }
        } catch (e) {
            console.error('❌ Error parsing status update response:', e);
        }
    }

    /**
     * Get IDs of ideas that are currently generating
     */
    function getGeneratingIdeaIds() {
        const generatingIds = [];
        const seenIds = new Set();
        
        console.log('🔍 Getting generating idea IDs...');
        
        // Primary source: Check cached data for ideas with 'generating' status
        if (window.ApprovedIdeasV2.currentIdeas && Array.isArray(window.ApprovedIdeasV2.currentIdeas)) {
            window.ApprovedIdeasV2.currentIdeas.forEach(function(idea) {
                if (idea.status === 'generating' && !seenIds.has(idea.id)) {
                    seenIds.add(idea.id);
                    generatingIds.push(idea.id);
                    console.log(`✅ Found generating idea in cache: ${idea.id} (${idea.title})`);
                }
            });
        }
        
        // Fallback: Check DOM for any progress bars (only if cache is empty or suspicious)
        // This should rarely be needed if cache is working properly
        const domProgressBars = $('#ideasTableBody .generation-progress');
        console.log(`🔍 Found ${domProgressBars.length} progress bars in DOM`);
        
        if (domProgressBars.length > 0) {
            domProgressBars.each(function() {
                const row = $(this).closest('tr');
                const ideaId = parseInt(row.data('idea-id'));
                
                if (ideaId && !seenIds.has(ideaId)) {
                    seenIds.add(ideaId);
                    generatingIds.push(ideaId);
                    console.log(`⚠️ Found generating idea in DOM (not in cache): ${ideaId}`);
                } else if (ideaId && seenIds.has(ideaId)) {
                    console.log(`🔄 Skipping duplicate idea from DOM: ${ideaId}`);
                }
            });
        }
        
        // Final deduplication (just to be absolutely sure)
        const uniqueIds = [...new Set(generatingIds)];
        
        if (uniqueIds.length !== generatingIds.length) {
            console.warn(`⚠️ Removed ${generatingIds.length - uniqueIds.length} duplicate IDs from generating list`);
        }
        
        console.log(`📊 Final generating IDs: [${uniqueIds.join(', ')}] (${uniqueIds.length} total)`);
        
        return uniqueIds;
    }

    /**
     * Update individual idea row with new data
     */
    function updateIdeaRow(idea) {
        console.log(`🔄 Updating row for idea ${idea.id}`);
        
        const row = $(`tr[data-idea-id="${idea.id}"]`);
        if (row.length === 0) {
            console.warn(`⚠️ Row not found for idea ${idea.id}`);
            return;
        }
        
        // Update status cell with progress bar or badge
        const statusCell = row.find('td:nth-child(4)');
        const oldStatus = statusCell.html();
        const newStatusDisplay = createStatusDisplay(idea);
        
        if (oldStatus !== newStatusDisplay) {
            statusCell.html(newStatusDisplay);
            
            // Add visual feedback for update
            row.addClass('table-info');
            setTimeout(function() {
                row.removeClass('table-info');
            }, 1000);
            
            console.log(`✅ Updated idea ${idea.id} status display`);
        }
        
        // Update actions cell if status changed
        const actionsCell = row.find('td:nth-child(5)');
        const newActions = createActionButtons(idea);
        actionsCell.html(newActions);
        
        // If generation completed, show a notification
        if (idea.status === 'generated' && idea.generation_status && idea.generation_status.includes('Complete')) {
            showNotice(`Blog post "${idea.title}" has been generated successfully!`, 'success');
        }
    }

    /**
     * Handle select all checkbox
     */
    function handleSelectAll() {
        console.log('☑️ Select all triggered');
        
        const isChecked = $('#selectAllCheckbox').is(':checked');
        $('.idea-checkbox').prop('checked', isChecked);
        
        if (isChecked) {
            window.ApprovedIdeasV2.selectedIdeas = $('.idea-checkbox').map(function() {
                return parseInt($(this).val());
            }).get();
        } else {
            window.ApprovedIdeasV2.selectedIdeas = [];
        }
        
        updateSelectedRows();
        updateBulkActionButtons();
        
        console.log(`Selected ideas: ${window.ApprovedIdeasV2.selectedIdeas.length}`);
    }

    /**
     * Handle individual checkbox selection
     */
    function handleIndividualSelect() {
        console.log('☑️ Individual select triggered');
        
        const ideaId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            if (!window.ApprovedIdeasV2.selectedIdeas.includes(ideaId)) {
                window.ApprovedIdeasV2.selectedIdeas.push(ideaId);
            }
        } else {
            window.ApprovedIdeasV2.selectedIdeas = window.ApprovedIdeasV2.selectedIdeas.filter(id => id !== ideaId);
        }
        
        updateSelectedRows();
        updateBulkActionButtons();
        updateSelectAllCheckbox();
        
        console.log(`Selected ideas: ${window.ApprovedIdeasV2.selectedIdeas.length}`);
    }

    /**
     * Update selected row styling
     */
    function updateSelectedRows() {
        $('#ideasTableBody tr').each(function() {
            const ideaId = parseInt($(this).data('idea-id'));
            const isSelected = window.ApprovedIdeasV2.selectedIdeas.includes(ideaId);
            
            $(this).toggleClass('table-active', isSelected);
        });
    }

    /**
     * Update bulk action button states
     */
    function updateBulkActionButtons() {
        const hasSelected = window.ApprovedIdeasV2.selectedIdeas.length > 0;
        
        $('#bulkSubmitForGeneration, #bulkDenySelected').prop('disabled', !hasSelected);
    }

    /**
     * Update select all checkbox state
     */
    function updateSelectAllCheckbox() {
        const totalCheckboxes = $('.idea-checkbox').length;
        const selectedCheckboxes = $('.idea-checkbox:checked').length;
        
        if (selectedCheckboxes === 0) {
            $('#selectAllCheckbox').prop('indeterminate', false).prop('checked', false);
        } else if (selectedCheckboxes === totalCheckboxes) {
            $('#selectAllCheckbox').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#selectAllCheckbox').prop('indeterminate', true);
        }
    }

    /**
     * Handle bulk submit for generation
     */
    function handleBulkSubmitForGeneration() {
        console.log('📤 Bulk submit for generation triggered');
        
        if (window.ApprovedIdeasV2.selectedIdeas.length === 0) {
            showNotice('Please select ideas to generate', 'warning');
            return;
        }
        
        $('#bulkGenerationCount').text(window.ApprovedIdeasV2.selectedIdeas.length);
        $('#bulkGenerationConfirmModal').modal('show');
    }

    /**
     * Handle confirm bulk generation
     */
    function handleConfirmBulkGeneration() {
        console.log('✅ Bulk generation confirmed');
        
        $('#bulkGenerationConfirmModal').modal('hide');
        
        const selectedIds = [...window.ApprovedIdeasV2.selectedIdeas];
        console.log(`🚀 Starting bulk generation for ${selectedIds.length} ideas:`, selectedIds);
        
        // Submit all ideas at once for better queue management
        submitBulkIdeasForGeneration(selectedIds);
        
        // Clear selection
        window.ApprovedIdeasV2.selectedIdeas = [];
        $('.idea-checkbox').prop('checked', false);
        updateBulkActionButtons();
        updateSelectAllCheckbox();
    }

    /**
     * Handle individual generate button
     */
    function handleIndividualGenerate() {
        const ideaId = parseInt($(this).data('idea-id'));
        const ideaTitle = $(this).data('idea-title');
        
        console.log(`🎯 Individual generate triggered for idea ${ideaId}: ${ideaTitle}`);
        
        $('#confirmIdeaTitle').text(ideaTitle);
        $('#confirmGeneration').data('idea-id', ideaId);
        $('#generationConfirmModal').modal('show');
    }

    /**
     * Handle individual deny button
     */
    function handleIndividualDeny() {
        const ideaId = parseInt($(this).data('idea-id'));
        console.log(`🚫 Individual deny triggered for idea ${ideaId}`);
        
        if (confirm('Are you sure you want to deny this idea?')) {
            denyIdea(ideaId);
        }
    }

    /**
     * Handle confirm individual generation
     */
    function handleConfirmGeneration() {
        const ideaId = parseInt($(this).data('idea-id'));
        console.log(`✅ Individual generation confirmed for idea ${ideaId}`);
        
        $('#generationConfirmModal').modal('hide');
        submitIdeaForGeneration(ideaId);
    }

    /**
     * Submit bulk ideas for generation
     */
    function submitBulkIdeasForGeneration(ideaIds) {
        console.log(`🚀 Submitting ${ideaIds.length} ideas for bulk generation...`);
        
        const ajaxData = {
            action: 'ai_blog_v2_submit_for_generation',
            idea_ids: ideaIds,
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 Bulk Generation AJAX Request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('📥 Bulk Generation AJAX Response:', response);
                handleBulkGenerationResponse(response, ideaIds);
            },
            error: function(xhr, status, error) {
                console.error('❌ Bulk Generation AJAX Error:', {
                    ideaIds: ideaIds,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                showNotice(`Failed to start bulk generation`, 'error');
            }
        });
    }
    
    /**
     * Handle bulk generation response
     */
    function handleBulkGenerationResponse(response, ideaIds) {
        console.log('📥 Bulk generation response received:', response);
        
        if (response.success) {
            const data = response.data;
            console.log(`✅ Bulk generation submission successful`);
            showNotice(data.message || 'Bulk generation started successfully', 'success');
            
            // Handle queue-based response
            if (data.queue_result) {
                console.log('📊 Queue result:', data.queue_result);
                
                // Update queue status display
                if (data.queue_status) {
                    updateQueueStatusDisplay(data.queue_status);
                } else {
                    // Fetch queue status if not provided
                    fetchQueueStatus();
                }
            }
            
            // Update all affected ideas
            if (data.ideas && data.ideas.length > 0) {
                data.ideas.forEach(function(idea) {
                    // Update cached data
                    const cachedIndex = window.ApprovedIdeasV2.currentIdeas.findIndex(i => i.id == idea.id);
                    if (cachedIndex !== -1) {
                        window.ApprovedIdeasV2.currentIdeas[cachedIndex] = idea;
                    }
                    
                    // Update the row
                    updateIdeaRow(idea);
                });
                console.log(`✅ Updated ${data.ideas.length} idea(s) in UI`);
            }
            
            // Start faster refresh for active generations
            if (data.queue_result && (data.queue_result.started.length > 0 || data.queue_result.queued.length > 0)) {
                startFastRefresh();
                
                // Also fetch queue status after a short delay to ensure it's visible
                setTimeout(fetchQueueStatus, 1000);
            }
            
        } else {
            console.error(`❌ Bulk generation failed:`, response.data);
            showNotice(response.data || 'Failed to start bulk generation', 'error');
        }
    }

    /**
     * Submit idea for generation
     */
    function submitIdeaForGeneration(ideaId) {
        console.log(`🚀 Submitting idea ${ideaId} for generation...`);
        
        // Prevent double submission
        if (window.ApprovedIdeasV2.submittingIdeas && window.ApprovedIdeasV2.submittingIdeas.includes(ideaId)) {
            console.log(`⚠️ Already submitting idea ${ideaId}, skipping duplicate request`);
            return;
        }
        
        // Initialize submitting ideas array if it doesn't exist
        if (!window.ApprovedIdeasV2.submittingIdeas) {
            window.ApprovedIdeasV2.submittingIdeas = [];
        }
        
        // Add to submitting list
        window.ApprovedIdeasV2.submittingIdeas.push(ideaId);
        
        const ajaxData = {
            action: 'ai_blog_v2_submit_for_generation',
            idea_ids: [ideaId], // Send as array for consistency with bulk operations
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 Generation AJAX Request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('📥 Generation AJAX Response:', response);
                handleGenerationResponse(response, ideaId);
            },
            error: function(xhr, status, error) {
                console.error('❌ Generation AJAX Error:', {
                    ideaId: ideaId,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                showNotice(`Failed to start generation for idea ${ideaId}`, 'error');
            },
            complete: function() {
                // Remove from submitting list when request completes
                if (window.ApprovedIdeasV2.submittingIdeas) {
                    const index = window.ApprovedIdeasV2.submittingIdeas.indexOf(ideaId);
                    if (index > -1) {
                        window.ApprovedIdeasV2.submittingIdeas.splice(index, 1);
                    }
                }
            }
        });
    }

    /**
     * Handle generation response with immediate UI update
     */
    function handleGenerationResponse(response, ideaId) {
        console.log('📥 Generation response received:', response);
        
        if (response.success) {
            const data = response.data;
            console.log(`✅ Generation submission successful`);
            showNotice(data.message || 'Blog generation started successfully', 'success');
            
            // Handle queue-based response
            if (data.queue_result) {
                console.log('📊 Queue result:', data.queue_result);
                
                // Update queue status display
                if (data.queue_status) {
                    updateQueueStatusDisplay(data.queue_status);
                }
            }
            
            // Update all affected ideas
            if (data.ideas && data.ideas.length > 0) {
                data.ideas.forEach(function(idea) {
                    // Update cached data
                    const cachedIndex = window.ApprovedIdeasV2.currentIdeas.findIndex(i => i.id == idea.id);
                    if (cachedIndex !== -1) {
                        window.ApprovedIdeasV2.currentIdeas[cachedIndex] = idea;
                    }
                    
                    // Update the row
                    updateIdeaRow(idea);
                });
                console.log(`✅ Updated ${data.ideas.length} idea(s) in UI`);
            }
            
            // Start faster refresh for active generations
            if (data.queue_result && (data.queue_result.started.length > 0 || data.queue_result.queued.length > 0)) {
                startFastRefresh();
            }
            
        } else {
            console.error(`❌ Generation failed:`, response.data);
            showNotice(response.data || 'Failed to start generation', 'error');
        }
    }

    /**
     * Handle bulk deny selected
     */
    function handleBulkDenySelected() {
        console.log('🚫 Bulk deny triggered');
        
        if (window.ApprovedIdeasV2.selectedIdeas.length === 0) {
            showNotice('Please select ideas to deny', 'warning');
            return;
        }
        
        if (confirm(`Are you sure you want to deny ${window.ApprovedIdeasV2.selectedIdeas.length} selected ideas?`)) {
            const selectedIds = [...window.ApprovedIdeasV2.selectedIdeas];
            
            selectedIds.forEach(function(ideaId) {
                denyIdea(ideaId);
            });
            
            // Clear selection
            window.ApprovedIdeasV2.selectedIdeas = [];
            $('.idea-checkbox').prop('checked', false);
            updateBulkActionButtons();
            updateSelectAllCheckbox();
        }
    }

    /**
     * Deny an idea
     */
    function denyIdea(ideaId) {
        console.log(`🚫 Denying idea ${ideaId}...`);
        
        const ajaxData = {
            action: 'ai_blog_v2_deny_idea',
            idea_id: ideaId,
            nonce: ai_blog_admin.nonce
        };

        console.log('📤 Deny AJAX Request:', ajaxData);

        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('📥 Deny AJAX Response:', response);
                handleDenyResponse(response, ideaId);
            },
            error: function(xhr, status, error) {
                console.error('❌ Deny AJAX Error:', {
                    ideaId: ideaId,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                showNotice(`Failed to deny idea ${ideaId}`, 'error');
            }
        });
    }

    /**
     * Handle deny response
     */
    function handleDenyResponse(response, ideaId) {
        try {
            let data = response;
            if (typeof response === 'string') {
                data = JSON.parse(response);
            }

            if (data.success) {
                console.log(`✅ Idea ${ideaId} denied successfully`);
                showNotice('Idea denied successfully', 'success');
                
                // Remove the row from display
                $(`tr[data-idea-id="${ideaId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    
                    // Check if table is now empty
                    if ($('#ideasTableBody tr').length === 0) {
                        showEmptyState();
                    }
                });
                
                // Update statistics (full refresh needed since row was removed)
                setTimeout(function() {
                    refreshIdeas();
                }, 500);
            } else {
                console.error(`❌ Failed to deny idea ${ideaId}:`, data.message);
                showNotice(data.message || 'Failed to deny idea', 'error');
            }
        } catch (e) {
            console.error('❌ Error parsing deny response:', e);
            showNotice('Error processing server response', 'error');
        }
    }

    /**
     * Handle view details button click
     */
    function handleViewDetails() {
        console.log('👁️ View details button clicked');
        
        const ideaId = $(this).data('idea-id');
        const idea = window.ApprovedIdeasV2.currentIdeas.find(i => i.id == ideaId);
        
        if (!idea) {
            console.error('❌ Idea not found:', ideaId);
            showNotice('Error: Idea not found', 'error');
            return;
        }
        
        console.log(`📋 Viewing details for idea ${ideaId}: ${idea.title} (status: ${idea.status})`);
        
        // If idea is generating, show log viewer instead of edit modal
        if (idea.status === 'generating') {
            showLogViewer(ideaId, idea.title);
        } else {
            showEditModal(ideaId, idea);
        }
    }

    /**
     * Show edit modal for approved/generated ideas
     */
    function showEditModal(ideaId, idea) {
        console.log(`📝 Showing edit modal for idea ${ideaId}`);
        
        // Populate edit form
        $('#editIdeaId').val(idea.id);
        $('#editIdeaTitle').val(idea.title);
        $('#editIdeaDescription').val(idea.description);
        $('#editIdeaPersona').val(idea.persona_id || '');
        
        // Show the edit modal
        $('#editIdeaModal').modal('show');
    }

    /**
     * Show log viewer for generating ideas
     */
    function showLogViewer(ideaId, ideaTitle) {
        console.log(`📄 Showing log viewer for generating idea ${ideaId}`);
        
        // Update modal title
        $('#logViewerModalLabel').text(`Generation Log: ${ideaTitle}`);
        
        // Store current idea ID for log operations
        window.ApprovedIdeasV2.currentLogIdeaId = ideaId;
        
        // Clear previous content
        $('#logContent').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading generation log...</div>');
        
        // Show the modal
        $('#logViewerModal').modal('show');
        
        // Ensure modal body is scrollable and starts at bottom
        $('#logViewerModal').on('shown.bs.modal', function() {
            const modalBody = $('#logViewerModal .modal-body')[0];
            if (modalBody) {
                modalBody.scrollTop = modalBody.scrollHeight;
            }
        });
        
        // Load the log content
        refreshGenerationLog();
        
        // Start auto-refresh for log content
        if (window.ApprovedIdeasV2.logRefreshTimer) {
            clearInterval(window.ApprovedIdeasV2.logRefreshTimer);
        }
        
        window.ApprovedIdeasV2.logRefreshTimer = setInterval(function() {
            refreshGenerationLog();
        }, 5000); // Refresh every 5 seconds (increased from 3)
        
        console.log('✅ Log viewer opened with auto-refresh enabled');
    }

    /**
     * Refresh generation log content (with tail support)
     */
    function refreshGenerationLog() {
        const ideaId = window.ApprovedIdeasV2.currentLogIdeaId;
        
        if (!ideaId) {
            console.error('❌ No idea ID set for log viewer');
            return;
        }
        
        // Initialize log state if needed
        if (!window.ApprovedIdeasV2.logState) {
            window.ApprovedIdeasV2.logState = {};
        }
        if (!window.ApprovedIdeasV2.logState[ideaId]) {
            window.ApprovedIdeasV2.logState[ideaId] = {
                lastLine: 0,
                lines: []
            };
        }
        
        const logState = window.ApprovedIdeasV2.logState[ideaId];
        console.log(`🔄 Refreshing log for idea ${ideaId} (from line ${logState.lastLine})`);
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_get_generation_log',
                idea_id: ideaId,
                last_line: logState.lastLine,
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const logData = response.data;
                    
                    // Add new lines to our state
                    if (logData.lines && logData.lines.length > 0) {
                        logState.lines = logState.lines.concat(logData.lines);
                        logState.lastLine = logData.last_line;
                        
                        // Keep only last 1000 lines to prevent memory issues
                        if (logState.lines.length > 1000) {
                            logState.lines = logState.lines.slice(-1000);
                        }
                    }
                    
                    // Format log content with syntax highlighting
                    let formattedContent = '<div class="log-viewer-content">';
                    
                    if (logState.lines.length > 0) {
                        logState.lines.forEach(function(lineData) {
                            const line = lineData.text || lineData;
                            const type = lineData.type || 'info';
                            let lineClass = `log-line log-${type}`;
                            
                            formattedContent += `<div class="${lineClass}">${escapeHtml(line)}</div>`;
                        });
                    } else if (logData.message) {
                        formattedContent += `<div class="text-muted">${escapeHtml(logData.message)}</div>`;
                    } else {
                        formattedContent += '<div class="text-muted">Waiting for log output...</div>';
                    }
                    
                    formattedContent += '</div>';
                    
                    // Update log content
                    $('#logContent').html(formattedContent);
                    
                    // Update status
                    if (logData.status) {
                        $('#logGenerationStatus').text(logData.status);
                    }
                    if (logData.generation_status) {
                        $('#logGenerationProgress').text(logData.generation_status);
                    }
                    
                    // Auto-scroll to bottom if new lines were added
                    if (logData.lines && logData.lines.length > 0) {
                        // The scrollable element is the modal-body, not the logContent itself
                        const modalBody = $('#logViewerModal .modal-body')[0];
                        if (modalBody) {
                            // Check if user is near bottom (within 50px) before auto-scrolling
                            const isNearBottom = modalBody.scrollHeight - modalBody.scrollTop - modalBody.clientHeight < 50;
                            
                            // Only auto-scroll if user hasn't scrolled up
                            if (isNearBottom) {
                                // Use requestAnimationFrame for smoother scrolling after render
                                requestAnimationFrame(function() {
                                    // Double-check the element still exists
                                    const modalBodyCheck = $('#logViewerModal .modal-body')[0];
                                    if (modalBodyCheck) {
                                        modalBodyCheck.scrollTop = modalBodyCheck.scrollHeight;
                                        
                                        // Fallback: Try again after a longer delay if needed
                                        setTimeout(function() {
                                            const finalCheck = $('#logViewerModal .modal-body')[0];
                                            if (finalCheck && finalCheck.scrollTop < finalCheck.scrollHeight - finalCheck.clientHeight - 50) {
                                                finalCheck.scrollTop = finalCheck.scrollHeight;
                                            }
                                        }, 100);
                                    }
                                });
                            } else {
                                console.log('⚠️ User has scrolled up, not auto-scrolling to bottom');
                            }
                        }
                    }
                    
                    // Stop refresh if generation is complete
                    if (logData.complete) {
                        console.log('✅ Generation complete, stopping log refresh');
                        if (window.ApprovedIdeasV2.logRefreshTimer) {
                            clearInterval(window.ApprovedIdeasV2.logRefreshTimer);
                            window.ApprovedIdeasV2.logRefreshTimer = null;
                        }
                        
                        // Show completion message
                        if (logData.status === 'generated') {
                            $('#logContent').append('<div class="log-line log-success mt-3">🎉 Generation completed successfully!</div>');
                        } else if (logData.status === 'failed' || logData.generation_status === 'Failed') {
                            $('#logContent').append('<div class="log-line log-error mt-3">❌ Generation failed. Check the log for errors.</div>');
                        }
                    }
                    
                    console.log(`✅ Log updated with ${logData.lines ? logData.lines.length : 0} new lines`);
                } else {
                    if (!$('#logContent .alert').length) {
                        $('#logContent').html('<div class="alert alert-warning">Failed to load log content: ' + (response.data || 'Unknown error') + '</div>');
                    }
                    console.error('❌ Failed to load log:', response);
                }
            },
            error: function(xhr, status, error) {
                if (!$('#logContent .alert').length) {
                    $('#logContent').html('<div class="alert alert-danger">Error loading log: ' + error + '</div>');
                }
                console.error('❌ Log load error:', error);
            }
        });
    }

    /**
     * Handle edit idea form submission
     */
    function handleEditIdeaSubmit(e) {
        e.preventDefault();
        
        const formData = {
            action: 'ai_blog_v2_edit_idea',
            idea_id: $('#editIdeaId').val(),
            title: $('#editIdeaTitle').val(),
            description: $('#editIdeaDescription').val(),
            persona_id: $('#editIdeaPersona').val() || 0,
            nonce: ai_blog_admin.nonce
        };
        
        console.log('📤 Editing idea:', formData);
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    console.log('✅ Idea edited successfully');
                    showNotice('Idea updated successfully', 'success');
                    $('#editIdeaModal').modal('hide');
                    
                    // Refresh the ideas to show updated data
                    setTimeout(refreshIdeas, 500);
                } else {
                    console.error('❌ Failed to edit idea:', response.data);
                    showNotice(response.data || 'Failed to update idea', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error editing idea:', error);
                showNotice('Error updating idea: ' + error, 'error');
            }
        });
    }

    /**
     * Handle cancel generation button
     */
    function handleCancelGeneration() {
        // Get the idea ID from the stored value (set when log viewer opens)
        const ideaId = window.ApprovedIdeasV2.currentLogIdeaId;
        console.log(`🛑 Cancel generation for idea ${ideaId}`);
        
        if (!ideaId) {
            console.error('❌ No idea ID available for cancellation');
            showNotice('Error: No idea ID available', 'error');
            return;
        }
        
        if (!confirm('Are you sure you want to cancel this generation? This cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_cancel_generation',
                idea_id: ideaId,
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Generation canceled successfully');
                    showNotice('Generation canceled successfully', 'success');
                    $('#logViewerModal').modal('hide');
                    
                    // Stop log refresh timer
                    if (window.ApprovedIdeasV2.logRefreshTimer) {
                        clearInterval(window.ApprovedIdeasV2.logRefreshTimer);
                        window.ApprovedIdeasV2.logRefreshTimer = null;
                    }
                    
                    // Refresh ideas to show updated status
                    setTimeout(refreshIdeas, 500);
                } else {
                    console.error('❌ Failed to cancel generation:', response.data);
                    showNotice(response.data || 'Failed to cancel generation', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error canceling generation:', error);
                showNotice('Error canceling generation: ' + error, 'error');
            }
        });
    }

    /**
     * Handle cancel all generations
     */
    function handleCancelAllGenerations() {
        console.log('🛑 Cancel all generations');
        
        if (!confirm('Are you sure you want to cancel ALL active generations? This cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_cancel_all_generations',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ All generations canceled successfully');
                    showNotice(response.data.message, 'success');
                    $('#generationManagementModal').modal('hide');
                    
                    // Refresh ideas to show updated statuses
                    setTimeout(refreshIdeas, 1000);
                } else {
                    console.error('❌ Failed to cancel all generations:', response.data);
                    showNotice(response.data || 'Failed to cancel all generations', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error canceling all generations:', error);
                showNotice('Error canceling all generations: ' + error, 'error');
            }
        });
    }

    /**
     * Handle reset stuck generations
     */
    function handleResetStuckGenerations() {
        console.log('🔄 Reset stuck generations');
        
        if (!confirm('Are you sure you want to reset generations that have been stuck for 10+ minutes?')) {
            return;
        }
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_reset_stuck_generations',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Stuck generations reset successfully');
                    showNotice(response.data.message, 'success');
                    $('#generationManagementModal').modal('hide');
                    
                    // Refresh ideas to show updated statuses
                    setTimeout(refreshIdeas, 1000);
                } else {
                    console.error('❌ Failed to reset stuck generations:', response.data);
                    showNotice(response.data || 'Failed to reset stuck generations', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error resetting stuck generations:', error);
                showNotice('Error resetting stuck generations: ' + error, 'error');
            }
        });
    }

    /**
     * Create status badge
     */
    function createStatusBadge(status) {
        const badges = {
            'approved': '<span class="badge bg-success">Approved</span>',
            'generating': '<span class="badge bg-warning">Generating</span>',
            'generated': '<span class="badge bg-info">Generated</span>',
            'denied': '<span class="badge bg-danger">Denied</span>'
        };
        
        return badges[status] || `<span class="badge bg-secondary">${escapeHtml(status)}</span>`;
    }

    /**
     * Handle window focus (resume auto-refresh)
     */
    function handleWindowFocus() {
        console.log('👁️ Window focused, checking auto-refresh...');
        if (window.ApprovedIdeasV2.autoRefreshEnabled && !window.ApprovedIdeasV2.refreshTimer) {
            startAutoRefresh();
            refreshGeneratingIdeas(); // Immediate targeted refresh on focus
        }
    }

    /**
     * Handle window blur (optionally pause auto-refresh)
     */
    function handleWindowBlur() {
        console.log('👁️ Window blurred');
        // Keep auto-refresh running even when window is not focused
        // since generation status needs to be monitored
    }

    /**
     * Handle before unload
     */
    function handleBeforeUnload() {
        console.log('👋 Page unloading, cleaning up...');
        stopAutoRefresh();
    }

    /**
     * Show/hide loading state
     */
    function showLoading(show) {
        if (show) {
            $('#loadingState').removeClass('d-none');
            $('#ideasTable').addClass('d-none');
            $('#emptyState').addClass('d-none');
        } else {
            $('#loadingState').addClass('d-none');
            // Don't automatically show table here - let updateIdeasDisplay handle it
        }
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        $('#ideasTable').addClass('d-none');
        $('#emptyState').removeClass('d-none');
        $('#loadingState').addClass('d-none');
        $('#approvedCount').text('0');
    }

    /**
     * Show notification
     */
    function showNotice(message, type = 'info') {
        console.log(`📢 Notice [${type}]: ${message}`);
        
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const notice = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('#aiAdminNotices').prepend(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.alert('close');
        }, 5000);
    }

    /**
     * Utility functions
     */
    function getPersonaName(personaId) {
        if (ai_blog_admin.personas && ai_blog_admin.personas[personaId]) {
            return ai_blog_admin.personas[personaId];
        }
        return personaId ? `Persona ${personaId}` : 'Default';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    /**
     * Update queue status display
     */
    function updateQueueStatusDisplay(queueStatus) {
        console.log('📊 Updating queue status display:', queueStatus);
        
        // Create or update queue status indicator
        let $queueStatus = $('#queueStatusIndicator');
        if ($queueStatus.length === 0) {
            // Create queue status indicator with enhanced styling
            const queueHtml = `
                <div id="queueStatusIndicator" class="alert alert-info mt-3" style="display: none;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="alert-heading mb-2">
                                <i class="fas fa-tasks me-2"></i>
                                Generation Queue Status
                            </h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <strong>Active Generations:</strong> 
                                        <span class="badge bg-warning text-dark fs-6">
                                            <span id="activeGenerationsCount">0</span> / 5
                                        </span>
                                    </div>
                                    <div>
                                        <strong>Queued Items:</strong> 
                                        <span class="badge bg-secondary fs-6">
                                            <span id="queuedItemsCount">0</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div id="activeGenerationsList"></div>
                                    <div id="queuedItemsList" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" aria-label="Close" onclick="$('#queueStatusIndicator').slideUp();"></button>
                    </div>
                </div>
            `;
            
            // Insert at the top of the page, right after the page header
            if ($('#ideasContainer').length > 0) {
                $('#ideasContainer').prepend(queueHtml);
            } else {
                // Fallback: insert after statistics cards
                $('.ai-admin-statistics').after(queueHtml);
            }
            $queueStatus = $('#queueStatusIndicator');
        }
        
        // Update counts
        $('#activeGenerationsCount').text(queueStatus.active_count || 0);
        $('#queuedItemsCount').text(queueStatus.queue_length || 0);
        
        // Update active generations list
        let activeList = '';
        if (queueStatus.active_generations && queueStatus.active_generations.length > 0) {
            activeList = '<strong>Currently Generating:</strong><ul class="mb-0 small">';
            queueStatus.active_generations.forEach(function(gen) {
                const duration = Math.floor(gen.duration / 60);
                activeList += `<li>${escapeHtml(gen.title)} - ${escapeHtml(gen.status)} (${duration} min)</li>`;
            });
            activeList += '</ul>';
        }
        $('#activeGenerationsList').html(activeList);
        
        // Update queued items list
        let queuedList = '';
        if (queueStatus.queued_ideas && queueStatus.queued_ideas.length > 0) {
            queuedList = '<strong>Waiting in Queue:</strong><ul class="mb-0 small">';
            // Show first 5 queued items
            const itemsToShow = queueStatus.queued_ideas.slice(0, 5);
            itemsToShow.forEach(function(item) {
                queuedList += `<li>Position ${item.position}: ${escapeHtml(item.title)}</li>`;
            });
            if (queueStatus.queued_ideas.length > 5) {
                queuedList += `<li><em>... and ${queueStatus.queued_ideas.length - 5} more</em></li>`;
            }
            queuedList += '</ul>';
        }
        $('#queuedItemsList').html(queuedList);
        
        // Show/hide based on activity
        if (queueStatus.active_count > 0 || queueStatus.queue_length > 0) {
            $queueStatus.slideDown();
            
            // Also show a notification if items are queued
            if (queueStatus.queue_length > 0 && !window.ApprovedIdeasV2.queueNotificationShown) {
                showNotice(
                    `${queueStatus.queue_length} idea(s) are queued and will start generating automatically when slots become available. Maximum ${5} concurrent generations allowed.`,
                    'info'
                );
                window.ApprovedIdeasV2.queueNotificationShown = true;
            }
        } else {
            $queueStatus.slideUp();
            window.ApprovedIdeasV2.queueNotificationShown = false;
        }
    }

    /**
     * Start fast refresh mode for active generations
     */
    function startFastRefresh() {
        console.log('⚡ Starting fast refresh mode');
        
        // Store original interval
        if (!window.ApprovedIdeasV2.originalInterval) {
            window.ApprovedIdeasV2.originalInterval = window.ApprovedIdeasV2.refreshInterval;
        }
        
        // Set faster interval
        window.ApprovedIdeasV2.refreshInterval = 2000; // 2 seconds
        window.ApprovedIdeasV2.fastRefreshActive = true;
        
        // Restart auto-refresh with new interval
        if (window.ApprovedIdeasV2.autoRefreshEnabled) {
            stopAutoRefresh();
            startAutoRefresh();
        }
        
        // Schedule return to normal speed after 5 minutes
        if (window.ApprovedIdeasV2.fastRefreshTimer) {
            clearTimeout(window.ApprovedIdeasV2.fastRefreshTimer);
        }
        
        window.ApprovedIdeasV2.fastRefreshTimer = setTimeout(function() {
            stopFastRefresh();
        }, 300000); // 5 minutes
    }

    /**
     * Stop fast refresh mode
     */
    function stopFastRefresh() {
        console.log('🐌 Returning to normal refresh speed');
        
        if (window.ApprovedIdeasV2.originalInterval) {
            window.ApprovedIdeasV2.refreshInterval = window.ApprovedIdeasV2.originalInterval;
        }
        
        window.ApprovedIdeasV2.fastRefreshActive = false;
        
        // Restart auto-refresh with normal interval
        if (window.ApprovedIdeasV2.autoRefreshEnabled) {
            stopAutoRefresh();
            startAutoRefresh();
        }
        
        if (window.ApprovedIdeasV2.fastRefreshTimer) {
            clearTimeout(window.ApprovedIdeasV2.fastRefreshTimer);
            window.ApprovedIdeasV2.fastRefreshTimer = null;
        }
    }

    /**
     * Fetch queue status from server
     */
    function fetchQueueStatus() {
        console.log('📊 Fetching queue status...');
        
        $.ajax({
            url: ai_blog_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_get_queue_status',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Queue status fetched:', response.data);
                    updateQueueStatusDisplay(response.data);
                } else {
                    console.error('❌ Failed to fetch queue status:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error fetching queue status:', error);
            }
        });
    }

    // Export for debugging
    window.ApprovedIdeasV2.debug = {
        loadApprovedIdeas: loadApprovedIdeas,
        refreshIdeas: refreshIdeas,
        startAutoRefresh: startAutoRefresh,
        stopAutoRefresh: stopAutoRefresh,
        submitIdeaForGeneration: submitIdeaForGeneration,
        denyIdea: denyIdea,
        updateQueueStatusDisplay: updateQueueStatusDisplay,
        startFastRefresh: startFastRefresh,
        stopFastRefresh: stopFastRefresh
    };

    console.log('🎯 Approved Ideas V2 JavaScript ready');

})(jQuery); 