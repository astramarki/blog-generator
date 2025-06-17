/**
 * Compact Logs Page JavaScript - Simple Implementation
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Assets/JS
 */

jQuery(document).ready(function($) {
    console.log('Ultra-compact logs page JavaScript loaded - v1.0.6');
    
    // Ensure modal is hidden on page load
    $('#log-details-modal').css('display', 'none');

    // Modal functionality
    function showLogDetailsModal(contextData) {
        const modal = $('#log-details-modal');
        const content = $('#log-details-content');
        
        // Clear previous content
        content.empty();
        
        if (contextData && typeof contextData === 'object') {
            let html = '<div class="context-data">';
            html += JSON.stringify(contextData, null, 2);
            html += '</div>';
            content.html(html);
        } else {
            content.html('<p>No additional details available.</p>');
        }
        
        // Show modal using CSS to ensure it displays properly
        modal.css('display', 'flex');
    }

    // View details button clicks
    $(document).on('click', '.ai-view-details', function(e) {
        e.preventDefault();
        
        const contextData = $(this).data('context');
        showLogDetailsModal(contextData);
    });

    // Close modal functionality
    $(document).on('click', '.modal-close, .ai-log-modal', function(e) {
        if (e.target === this) {
            $('#log-details-modal').css('display', 'none');
        }
    });

    // Escape key closes modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#log-details-modal').css('display', 'none');
        }
    });

    // Refresh logs button
    $('#refresh-logs').on('click', function(e) {
        e.preventDefault();
        window.location.reload();
    });

    // Export logs functionality
    $('#export-logs').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ai_blog_export_logs',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.content], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename || 'logs.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    // Show success message
                    showNotice('Logs exported successfully!', 'success');
                } else {
                    showNotice('Export failed: ' + (response.data.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotice('Export failed: Network error', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Clear old logs functionality
    $('#clear-old-logs').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear old logs? This action cannot be undone.')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ai_blog_clear_logs',
                nonce: ai_blog_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Old logs cleared successfully!', 'success');
                    // Refresh page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice('Clear failed: ' + (response.data.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotice('Clear failed: Network error', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Simple notification system
    function showNotice(message, type) {
        type = type || 'info';
        
        // Remove existing notices
        $('.ai-blog-notice').remove();
        
        // Create notice element
        const notice = $('<div class="ai-blog-notice notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Add dismiss button functionality
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut();
        });
        
        // Insert after page title
        $('.wrap h1').first().after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }

    // Auto-refresh functionality (optional, disabled by default)
    let autoRefresh = false;
    let refreshInterval;

    function startAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        refreshInterval = setInterval(function() {
            if (autoRefresh) {
                console.log('Auto-refreshing logs...');
                window.location.reload();
            }
        }, 30000); // Refresh every 30 seconds
    }

    // Toggle auto-refresh (if live updates toggle exists)
    $(document).on('change', '#live-updates-toggle', function() {
        autoRefresh = $(this).is(':checked');
        
        if (autoRefresh) {
            startAutoRefresh();
            showNotice('Live updates enabled (30s interval)', 'info');
        } else {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
            showNotice('Live updates disabled', 'info');
        }
    });

    // Form validation
    $('form.logs-filter-form').on('submit', function() {
        const dateFrom = $('input[name="date_from"]').val();
        const dateTo = $('input[name="date_to"]').val();
        
        if (dateFrom && dateTo && dateFrom > dateTo) {
            alert('Start date cannot be after end date.');
            return false;
        }
        
        return true;
    });

    // Add loading states to form submissions
    $('form.logs-filter-form').on('submit', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        submitBtn.prop('disabled', true).text('Filtering...');
        
        // Re-enable after page loads (fallback)
        setTimeout(function() {
            submitBtn.prop('disabled', false).text(originalText);
        }, 5000);
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+R or F5 - Refresh
        if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
            e.preventDefault();
            $('#refresh-logs').click();
        }
        
        // Ctrl+E - Export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            $('#export-logs').click();
        }
    });

    // Initialize tooltips (if needed)
    $('[title]').each(function() {
        $(this).attr('data-toggle', 'tooltip');
    });

    console.log('Ultra-compact logs page JavaScript initialized successfully - v1.0.6');
}); 