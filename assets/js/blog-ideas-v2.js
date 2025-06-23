/**
 * Idea Generator JavaScript
 * 
 * Handles all AJAX interactions and UI updates for the Idea Generator interface.
 * Provides extensive console logging for debugging and feedback.
 */

(function($) {
    'use strict';

    // Global variables
    let selectedIdeas = new Set();
    let currentIdeas = [];
    let isGenerating = false;

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('🚀 Idea Generator initialized');
        
        // Initialize the interface
        initializeBlogIdeasV2();
        
        // Load initial data
        loadStatistics();
        loadPendingIdeas();
        
        // Set up event listeners
        setupEventListeners();
    });

    /**
     * Initialize the Idea Generator interface
     */
    function initializeBlogIdeasV2() {
        console.log('📋 Initializing Idea Generator interface');
        
        // Set up AJAX error handling
        setupAjaxErrorHandling();
        
        // Initialize UI state
        updateBulkActionButtons();
    }

    /**
     * Set up global AJAX error handling
     */
    function setupAjaxErrorHandling() {
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            console.error('❌ AJAX Error:', {
                url: settings.url,
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                thrownError: thrownError
            });
            
            showNotification('An error occurred while communicating with the server.', 'error');
        });
        
        // Set default AJAX settings
        $.ajaxSetup({
            timeout: 30000, // 30 second timeout
            beforeSend: function(xhr, settings) {
                console.log('📡 AJAX Request:', {
                    url: settings.url,
                    type: settings.type,
                    data: settings.data
                });
            }
        });
    }

    /**
     * Set up all event listeners
     */
    function setupEventListeners() {
        console.log('🔗 Setting up event listeners');
        
        // Generate Ideas Modal
        $('#generateIdeasBtn').on('click', handleGenerateIdeas);
        $('#generateIdeasModal').on('hidden.bs.modal', resetGenerateForm);
        
        // Bulk Actions
        $('#bulkApproveSelected').on('click', handleBulkApprove);
        $('#bulkDenySelected').on('click', handleBulkDeny);
        $('#selectAllIdeas').on('click', handleSelectAll);
        $('#selectAllCheckbox').on('change', handleSelectAllCheckbox);
        
        // Refresh
        $('#refreshIdeas').on('click', function() {
            console.log('🔄 Manual refresh triggered');
            loadStatistics();
            loadPendingIdeas();
        });
        
        // Approve Ideas Modal
        $('#approveSelectedIdeas').on('click', handleApproveSelectedIdeas);
    }

    /**
     * Load statistics from the server
     */
    function loadStatistics() {
        console.log('📊 Loading statistics...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_get_statistics',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                console.log('📊 Statistics loaded:', response);
                
                if (response.success) {
                    updateStatisticsDisplay(response.data);
                } else {
                    console.error('❌ Failed to load statistics:', response.data);
                    showNotification('Failed to load statistics: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Statistics load error:', error);
                showNotification('Failed to load statistics', 'error');
            }
        });
    }

    /**
     * Update the statistics display
     */
    function updateStatisticsDisplay(stats) {
        console.log('📈 Updating statistics display:', stats);
        
        $('#stat-total').text(stats.total_generated || 0);
        $('#stat-approved').text(stats.approved || 0);
        $('#stat-denied').text(stats.denied || 0);
        $('#stat-generated').text(stats.generated || 0);
    }

    /**
     * Load pending ideas from the server
     */
    function loadPendingIdeas() {
        console.log('💡 Loading pending ideas...');
        
        // Show loading state
        $('#loadingState').show();
        $('#emptyState').hide();
        $('#ideasTableBody').empty();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_get_pending_ideas',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                console.log('💡 Pending ideas loaded:', response);
                
                if (response.success) {
                    currentIdeas = response.data.ideas;
                    updateIdeasDisplay(currentIdeas);
                    updatePendingCount(response.data.count);
                } else {
                    console.error('❌ Failed to load pending ideas:', response.data);
                    showNotification('Failed to load pending ideas: ' + response.data, 'error');
                    showEmptyState();
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Pending ideas load error:', error);
                showNotification('Failed to load pending ideas', 'error');
                showEmptyState();
            },
            complete: function() {
                $('#loadingState').hide();
            }
        });
    }

    /**
     * Update the ideas display
     */
    function updateIdeasDisplay(ideas) {
        console.log('🖼️ Updating ideas display with', ideas.length, 'ideas');
        
        const tbody = $('#ideasTableBody');
        tbody.empty();
        
        if (ideas.length === 0) {
            showEmptyState();
            return;
        }
        
        // Show the table and hide empty state when we have ideas
        $('#emptyState').addClass('d-none');
        $('#ideasTableBody').parent().parent().show();
        
        ideas.forEach(function(idea) {
            const row = createIdeaRow(idea);
            tbody.append(row);
        });
        
        // Reset selections
        selectedIdeas.clear();
        updateBulkActionButtons();
        $('#selectAllCheckbox').prop('checked', false);
        
        console.log('✅ Ideas table updated and shown with', ideas.length, 'ideas');
    }

    /**
     * Create a table row for an idea
     */
    function createIdeaRow(idea) {
        console.log('🏗️ Creating row for idea:', idea.title);
        
        const personaName = idea.persona_name || 'No Persona';
        const categoriesDisplay = idea.category_names && idea.category_names.length > 0 
            ? `<span class="badge bg-secondary">${idea.category_names.length}</span>`
            : '<span class="text-muted">None</span>';
        
        const row = $(`
            <tr data-idea-id="${idea.id}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input idea-checkbox" type="checkbox" value="${idea.id}">
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <strong class="me-2">${escapeHtml(idea.title)}</strong>
                        <button type="button" class="btn btn-sm btn-outline-info rounded-circle p-1" 
                                data-bs-toggle="tooltip" data-bs-placement="top" title="View description"
                                onclick="showIdeaDescription(${idea.id})">
                            <i class="fas fa-info fa-xs"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <span class="badge bg-primary">${escapeHtml(personaName)}</span>
                </td>
                <td>
                    <small class="text-muted">${idea.created_at_ny}</small>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            onclick="showCategories(${idea.id})"
                            data-bs-toggle="tooltip" title="View categories">
                        ${categoriesDisplay}
                    </button>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-success" 
                                onclick="approveIdea(${idea.id})"
                                data-bs-toggle="tooltip" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="denyIdea(${idea.id})"
                                data-bs-toggle="tooltip" title="Deny">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `);
        
        // Add event listener for checkbox
        row.find('.idea-checkbox').on('change', function() {
            const ideaId = parseInt($(this).val());
            if ($(this).is(':checked')) {
                selectedIdeas.add(ideaId);
                console.log('✅ Selected idea:', ideaId);
            } else {
                selectedIdeas.delete(ideaId);
                console.log('❌ Deselected idea:', ideaId);
            }
            updateBulkActionButtons();
        });
        
        return row;
    }

    /**
     * Handle generate ideas button click
     */
    function handleGenerateIdeas() {
        if (isGenerating) {
            console.log('⚠️ Generation already in progress');
            return;
        }
        
        console.log('🎯 Starting idea generation process');
        
        const count = parseInt($('#ideaCount').val());
        const contextPrompt = $('#contextPrompt').val().trim();
        
        // Validate inputs
        if (!count || count < 1 || count > 30) {
            showNotification('Please select a valid number of ideas (1-30)', 'error');
            return;
        }
        
        console.log('📝 Generation parameters:', {
            count: count,
            contextPrompt: contextPrompt,
            contextLength: contextPrompt.length
        });
        
        // Set generating state
        isGenerating = true;
        
        // Hide generate modal and show loading modal
        $('#generateIdeasModal').modal('hide');
        $('#generatingModal').modal('show');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_generate_ideas',
                nonce: ai_blog_admin.nonce,
                count: count,
                context_prompt: contextPrompt
            },
            timeout: 120000, // 2 minute timeout for generation
            success: function(response) {
                console.log('🎉 Generation response:', response);
                
                if (response.success) {
                    handleGenerationSuccess(response.data);
                } else {
                    console.error('❌ Generation failed:', response.data);
                    handleGenerationError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Generation request error:', error);
                handleGenerationError('Request failed: ' + error);
            },
            complete: function() {
                isGenerating = false;
                $('#generatingModal').modal('hide');
            }
        });
    }

    /**
     * Handle successful idea generation
     */
    function handleGenerationSuccess(data) {
        console.log('✅ Ideas generated successfully:', data);
        
        const ideas = data.ideas;
        const cost = data.cost || 0;
        const tokensUsed = data.tokens_used || 0;
        const serviceUsed = data.service_used || 'Unknown';
        
        console.log('💰 Generation cost:', cost, 'USD');
        console.log('🔤 Tokens used:', tokensUsed);
        console.log('🤖 Service used:', serviceUsed);
        
        showNotification(`Successfully generated ${ideas.length} ideas using ${serviceUsed}`, 'success');
        
        // Show approve ideas modal
        showApproveIdeasModal(ideas);
    }

    /**
     * Show the approve ideas modal
     */
    function showApproveIdeasModal(ideas) {
        console.log('📝 Showing approve ideas modal with', ideas.length, 'ideas');
        
        const container = $('#generatedIdeasContainer');
        container.empty();
        
        ideas.forEach(function(idea, index) {
            const ideaCard = createIdeaCard(idea, index);
            container.append(ideaCard);
        });
        
        $('#approveIdeasModal').modal('show');
    }

    /**
     * Create an idea card for the approve modal
     */
    function createIdeaCard(idea, index) {
        const personaName = idea.persona_name || 'No Persona';
        const categories = idea.categories || [];
        const reasoning = idea.reasoning || '';
        
        const card = $(`
            <div class="idea-card" data-idea-index="${index}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="form-check">
                        <input class="form-check-input generated-idea-checkbox" type="checkbox" 
                               value="${index}" id="idea${index}" checked>
                        <label class="form-check-label fw-bold" for="idea${index}">
                            ${escapeHtml(idea.title)}
                        </label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-info rounded-circle p-1" 
                            data-bs-toggle="tooltip" title="View description">
                        <i class="fas fa-info fa-xs"></i>
                    </button>
                </div>
                <p class="text-muted mb-2">${escapeHtml(idea.description)}</p>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">Persona:</small>
                        <span class="badge bg-primary ms-1">${escapeHtml(personaName)}</span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Categories:</small>
                        ${categories.map(cat => `<span class="badge bg-secondary ms-1">${escapeHtml(cat)}</span>`).join('')}
                    </div>
                </div>
                ${reasoning ? `
                    <div class="mt-2">
                        <small class="text-muted">Reasoning:</small>
                        <small class="d-block text-secondary">${escapeHtml(reasoning)}</small>
                    </div>
                ` : ''}
            </div>
        `);
        
        // Store idea data on the card element
        card.data('ideaData', idea);
        
        return card;
    }

    /**
     * Handle approve selected ideas
     */
    function handleApproveSelectedIdeas() {
        const selectedIndexes = [];
        const selectedIdeasData = [];
        
        $('.generated-idea-checkbox:checked').each(function() {
            const index = parseInt($(this).val());
            selectedIndexes.push(index);
            
            const ideaCard = $(`.idea-card[data-idea-index="${index}"]`);
            const ideaData = ideaCard.data('ideaData');
            selectedIdeasData.push(ideaData);
        });
        
        console.log('💾 Saving selected ideas:', selectedIndexes);
        console.log('📊 Ideas data to save:', selectedIdeasData);
        
        if (selectedIndexes.length === 0) {
            showNotification('No ideas selected', 'warning');
            return;
        }
        
        // Close modal 
        $('#approveIdeasModal').modal('hide');
        
        // Save the selected ideas to database
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_save_generated_ideas',
                nonce: ai_blog_admin.nonce,
                ideas: selectedIdeasData
            },
            success: function(response) {
                console.log('💾 Save ideas response:', response);
                
                if (response.success) {
                    const result = response.data.result;
                    showNotification(`${result.created_count} of ${result.total_count} ideas saved successfully`, 'success');
                    
                    // Update statistics and reload ideas
                    updateStatisticsDisplay(response.data.statistics);
                    loadPendingIdeas();
                } else {
                    console.error('❌ Save ideas failed:', response.data);
                    showNotification('Failed to save ideas: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Save ideas error:', error);
                showNotification('Failed to save ideas to database', 'error');
            }
        });
    }

    // Utility functions
    function handleGenerationError(error) {
        console.error('💥 Generation error:', error);
        showNotification('Failed to generate ideas: ' + error, 'error');
    }

    function showEmptyState() {
        $('#emptyState').removeClass('d-none');
        $('#ideasTableBody').parent().parent().hide();
        updatePendingCount(0);
    }

    function updatePendingCount(count) {
        $('#pendingCount').text(count);
    }

    function updateBulkActionButtons() {
        const hasSelection = selectedIdeas.size > 0;
        
        $('#bulkApproveSelected').prop('disabled', !hasSelection);
        $('#bulkDenySelected').prop('disabled', !hasSelection);
        
        // Update select all button text
        const allSelected = selectedIdeas.size === currentIdeas.length && currentIdeas.length > 0;
        $('#selectAllIdeas').html(allSelected 
            ? '<i class="fas fa-square me-1"></i>Deselect All'
            : '<i class="fas fa-check-square me-1"></i>Select All'
        );
        
        console.log('🔄 Bulk buttons updated. Selected:', selectedIdeas.size);
    }

    function handleBulkApprove() {
        if (selectedIdeas.size === 0) {
            showNotification('No ideas selected', 'warning');
            return;
        }
        
        console.log('✅ Bulk approving ideas:', Array.from(selectedIdeas));
        
        const ideaIds = Array.from(selectedIdeas);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_bulk_approve_ideas',
                nonce: ai_blog_admin.nonce,
                idea_ids: ideaIds
            },
            success: function(response) {
                console.log('✅ Bulk approve response:', response);
                
                if (response.success) {
                    const result = response.data.result;
                    showNotification(`${result.updated_count} of ${result.total_count} ideas approved`, 'success');
                    
                    // Update statistics and reload ideas
                    updateStatisticsDisplay(response.data.statistics);
                    loadPendingIdeas();
                } else {
                    console.error('❌ Bulk approve failed:', response.data);
                    showNotification('Failed to approve ideas: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Bulk approve error:', error);
                showNotification('Failed to approve ideas', 'error');
            }
        });
    }

    function handleBulkDeny() {
        if (selectedIdeas.size === 0) {
            showNotification('No ideas selected', 'warning');
            return;
        }
        
        console.log('❌ Bulk denying ideas:', Array.from(selectedIdeas));
        
        const ideaIds = Array.from(selectedIdeas);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_bulk_deny_ideas',
                nonce: ai_blog_admin.nonce,
                idea_ids: ideaIds
            },
            success: function(response) {
                console.log('❌ Bulk deny response:', response);
                
                if (response.success) {
                    const result = response.data.result;
                    showNotification(`${result.updated_count} of ${result.total_count} ideas denied`, 'success');
                    
                    // Update statistics and reload ideas
                    updateStatisticsDisplay(response.data.statistics);
                    loadPendingIdeas();
                } else {
                    console.error('❌ Bulk deny failed:', response.data);
                    showNotification('Failed to deny ideas: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Bulk deny error:', error);
                showNotification('Failed to deny ideas', 'error');
            }
        });
    }

    function handleSelectAllCheckbox() {
        const isChecked = $('#selectAllCheckbox').is(':checked');
        console.log('📋 Select all checkbox:', isChecked);
        
        $('.idea-checkbox').prop('checked', isChecked);
        
        selectedIdeas.clear();
        if (isChecked) {
            $('.idea-checkbox').each(function() {
                selectedIdeas.add(parseInt($(this).val()));
            });
        }
        
        updateBulkActionButtons();
    }

    function handleSelectAll() {
        const allSelected = selectedIdeas.size === currentIdeas.length && currentIdeas.length > 0;
        
        if (allSelected) {
            // Deselect all
            selectedIdeas.clear();
            $('.idea-checkbox').prop('checked', false);
            $('#selectAllCheckbox').prop('checked', false);
            console.log('❌ Deselected all ideas');
        } else {
            // Select all
            selectedIdeas.clear();
            $('.idea-checkbox').each(function() {
                selectedIdeas.add(parseInt($(this).val()));
                $(this).prop('checked', true);
            });
            $('#selectAllCheckbox').prop('checked', true);
            console.log('✅ Selected all ideas');
        }
        
        updateBulkActionButtons();
    }

    function resetGenerateForm() {
        $('#generateIdeasForm')[0].reset();
        $('#ideaCount').val('5');
        $('#contextPrompt').val('');
    }

    function showNotification(message, type = 'info') {
        console.log(`📢 Notification (${type}):`, message);
        
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const icon = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        }[type] || 'fas fa-info-circle';
        
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="${icon} me-2"></i>
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        
        $('#aiAdminNotices').prepend(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notification.alert('close');
        }, 5000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Global functions for onclick handlers
    window.approveIdea = function(ideaId) {
        console.log('✅ Approving idea:', ideaId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_approve_idea',
                nonce: ai_blog_admin.nonce,
                idea_id: ideaId
            },
            success: function(response) {
                console.log('✅ Approve response:', response);
                
                if (response.success) {
                    showNotification('Idea approved successfully', 'success');
                    updateStatisticsDisplay(response.data.statistics);
                    loadPendingIdeas();
                } else {
                    console.error('❌ Approve failed:', response.data);
                    showNotification('Failed to approve idea: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Approve error:', error);
                showNotification('Failed to approve idea', 'error');
            }
        });
    };

    window.denyIdea = function(ideaId) {
        console.log('❌ Denying idea:', ideaId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_blog_v2_deny_idea',
                nonce: ai_blog_admin.nonce,
                idea_id: ideaId
            },
            success: function(response) {
                console.log('❌ Deny response:', response);
                
                if (response.success) {
                    showNotification('Idea denied successfully', 'success');
                    updateStatisticsDisplay(response.data.statistics);
                    loadPendingIdeas();
                } else {
                    console.error('❌ Deny failed:', response.data);
                    showNotification('Failed to deny idea: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Deny error:', error);
                showNotification('Failed to deny idea', 'error');
            }
        });
    };

    window.showIdeaDescription = function(ideaId) {
        console.log('📖 Showing description for idea:', ideaId);
        
        const idea = currentIdeas.find(i => i.id == ideaId);
        if (!idea) {
            console.error('❌ Idea not found:', ideaId);
            return;
        }
        
        $('#ideaModalTitle').text(idea.title);
        $('#ideaModalDescription').text(idea.description);
        $('#ideaModalPersona').text(idea.persona_name || 'No Persona');
        $('#ideaModalCreated').text(idea.created_at_ny);
        
        $('#ideaDescriptionModal').modal('show');
    };

    window.showCategories = function(ideaId) {
        console.log('📂 Showing categories for idea:', ideaId);
        
        const idea = currentIdeas.find(i => i.id == ideaId);
        if (!idea) {
            console.error('❌ Idea not found:', ideaId);
            return;
        }
        
        const content = $('#categoriesModalContent');
        content.empty();
        
        if (idea.category_names && idea.category_names.length > 0) {
            idea.category_names.forEach(function(categoryName) {
                content.append(`<span class="badge bg-secondary me-2 mb-2">${escapeHtml(categoryName)}</span>`);
            });
        } else {
            content.append('<p class="text-muted">No categories assigned</p>');
        }
        
        $('#categoriesModal').modal('show');
    };

})(jQuery); 