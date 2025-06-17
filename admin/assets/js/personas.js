/**
 * Personas Admin JavaScript
 *
 * @package AI_Blog_Generator
 */

(function($) {
	'use strict';

	/**
	 * Personas Management Object
	 */
	window.aiBlogPersonas = {
		
		/**
		 * Initialize personas functionality
		 */
		init: function() {
			this.bindEvents();
			this.currentPersonaId = null;
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Add new persona
			$(document).on('click', '#add-new-persona', this.showAddModal.bind(this));
			
			// Edit persona
			$(document).on('click', '.edit-persona', this.editPersona.bind(this));
			
			// Toggle persona status
			$(document).on('click', '.toggle-persona', this.togglePersona.bind(this));
			
			// Delete persona
			$(document).on('click', '.delete-persona', this.deletePersona.bind(this));
			
			// Save persona
			$(document).on('click', '#save-persona', this.savePersona.bind(this));
			
			// Close modal
			$(document).on('click', '.ai-blog-modal-close', this.closeModal.bind(this));
			
			// Close modal on ESC key
			$(document).on('keyup', function(e) {
				if (e.key === 'Escape') {
					this.closeModal();
				}
			}.bind(this));
		},

		/**
		 * Show add persona modal
		 */
		showAddModal: function(e) {
			e.preventDefault();
			this.currentPersonaId = null;
			
			// Reset form
			$('#persona-form')[0].reset();
			$('#persona-id').val('');
			$('#persona-modal-title').text('Add New Persona');
			$('#persona-active').prop('checked', true);
			
			// Show modal
			$('#persona-modal').fadeIn();
		},

		/**
		 * Edit persona
		 */
		editPersona: function(e) {
			e.preventDefault();
			
			var personaId = $(e.currentTarget).data('persona-id');
			this.currentPersonaId = personaId;
			
			// Show loading state
			var $button = $(e.currentTarget);
			var originalText = $button.text();
			$button.prop('disabled', true).text('Loading...');
			
			// Get persona data
			this.makeAjaxRequest({
				action: 'ai_blog_get_persona',
				persona_id: personaId
			})
			.done(function(response) {
				if (response.success && response.data.persona) {
					var persona = response.data.persona;
					
					// Populate form
					$('#persona-id').val(persona.id);
					$('#persona-name').val(persona.name);
					$('#persona-bio').val(persona.bio);
					$('#persona-expertise').val(persona.expertise || '');
					$('#persona-writing-style').val(persona.writing_style || '');
					$('#persona-tone').val(persona.tone || 'professional');
					$('#persona-active').prop('checked', persona.active == 1);
					
					// Update modal title
					$('#persona-modal-title').text('Edit Persona: ' + persona.name);
					
					// Show modal
					$('#persona-modal').fadeIn();
				} else {
					this.showMessage(response.data.message || 'Failed to load persona', 'error');
				}
			}.bind(this))
			.fail(function() {
				this.showMessage('Error loading persona', 'error');
			}.bind(this))
			.always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		},

		/**
		 * Save persona
		 */
		savePersona: function(e) {
			e.preventDefault();
			
			// Validate form
			var name = $('#persona-name').val().trim();
			var bio = $('#persona-bio').val().trim();
			
			if (!name) {
				this.showMessage('Please enter a persona name', 'error');
				$('#persona-name').focus();
				return;
			}
			
			if (!bio) {
				this.showMessage('Please enter a persona biography', 'error');
				$('#persona-bio').focus();
				return;
			}
			
			// Show loading state
			var $button = $('#save-persona');
			var originalText = $button.text();
			$button.prop('disabled', true).text('Saving...');
			
			// Prepare data
			var data = {
				action: this.currentPersonaId ? 'ai_blog_update_persona' : 'ai_blog_create_persona',
				persona_id: $('#persona-id').val(),
				name: name,
				bio: bio,
				expertise: $('#persona-expertise').val().trim(),
				writing_style: $('#persona-writing-style').val().trim(),
				tone: $('#persona-tone').val(),
				active: $('#persona-active').is(':checked') ? 1 : 0
			};
			
			// Save persona
			this.makeAjaxRequest(data)
			.done(function(response) {
				if (response.success) {
					this.showMessage(response.data.message || 'Persona saved successfully', 'success');
					this.closeModal();
					
					// Reload page to show updated persona
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					this.showMessage(response.data.message || 'Failed to save persona', 'error');
				}
			}.bind(this))
			.fail(function() {
				this.showMessage('Error saving persona', 'error');
			}.bind(this))
			.always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		},

		/**
		 * Toggle persona active status
		 */
		togglePersona: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var personaId = $button.data('persona-id');
			var currentStatus = $button.data('current-status');
			var newStatus = currentStatus == 1 ? 0 : 1;
			
			// Show loading state
			var originalText = $button.text();
			$button.prop('disabled', true).text('Updating...');
			
			// Toggle status
			this.makeAjaxRequest({
				action: 'ai_blog_toggle_persona',
				persona_id: personaId
			})
			.done(function(response) {
				if (response.success) {
					this.showMessage(response.data.message || 'Persona status updated', 'success');
					
					// Update UI
					var $card = $button.closest('.persona-card');
					var $statusIndicator = $card.find('.status-indicator');
					
					if (newStatus == 1) {
						$button.text('Deactivate').data('current-status', 1);
						$statusIndicator.removeClass('inactive').addClass('active').text('Active');
					} else {
						$button.text('Activate').data('current-status', 0);
						$statusIndicator.removeClass('active').addClass('inactive').text('Inactive');
					}
				} else {
					this.showMessage(response.data.message || 'Failed to update persona status', 'error');
				}
			}.bind(this))
			.fail(function() {
				this.showMessage('Error updating persona status', 'error');
			}.bind(this))
			.always(function() {
				$button.prop('disabled', false);
			});
		},

		/**
		 * Delete persona
		 */
		deletePersona: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var personaId = $button.data('persona-id');
			var personaName = $button.closest('.persona-card').find('h3').text();
			
			// Confirm deletion
			if (!confirm('Are you sure you want to delete the persona "' + personaName + '"? This action cannot be undone.')) {
				return;
			}
			
			// Show loading state
			var originalText = $button.text();
			$button.prop('disabled', true).text('Deleting...');
			
			// Delete persona
			this.makeAjaxRequest({
				action: 'ai_blog_delete_persona',
				persona_id: personaId
			})
			.done(function(response) {
				if (response.success) {
					this.showMessage(response.data.message || 'Persona deleted successfully', 'success');
					
					// Remove card from UI
					$button.closest('.persona-card').fadeOut(function() {
						$(this).remove();
						
						// Check if no personas left
						if ($('.persona-card').length === 0) {
							$('#personas-list').html(
								'<div class="no-personas-message">' +
								'<p>No personas found. Click "Add New Persona" to create your first writing persona.</p>' +
								'</div>'
							);
						}
					});
				} else {
					this.showMessage(response.data.message || 'Failed to delete persona', 'error');
				}
			}.bind(this))
			.fail(function() {
				this.showMessage('Error deleting persona', 'error');
			}.bind(this))
			.always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		},

		/**
		 * Close modal
		 */
		closeModal: function() {
			$('#persona-modal').fadeOut();
			this.currentPersonaId = null;
		},

		/**
		 * Make AJAX request
		 */
		makeAjaxRequest: function(data) {
			// Add nonce
			data.nonce = aiBlogAjax.nonce;
			
			return $.ajax({
				url: aiBlogAjax.ajaxurl,
				type: 'POST',
				data: data,
				dataType: 'json'
			});
		},

		/**
		 * Show message
		 */
		showMessage: function(message, type) {
			// Remove any existing messages
			$('.ai-blog-message').remove();
			
			// Create message element
			var $message = $('<div class="notice ai-blog-message is-dismissible"></div>');
			
			// Add appropriate class
			if (type === 'success') {
				$message.addClass('notice-success');
			} else if (type === 'error') {
				$message.addClass('notice-error');
			} else {
				$message.addClass('notice-info');
			}
			
			// Add message text
			$message.html('<p>' + message + '</p>');
			
			// Add dismiss button
			$message.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
			
			// Insert after page title
			$('.wrap h1').after($message);
			
			// Auto-dismiss after 5 seconds for success messages
			if (type === 'success') {
				setTimeout(function() {
					$message.fadeOut(function() {
						$(this).remove();
					});
				}, 5000);
			}
			
			// Handle dismiss button
			$message.find('.notice-dismiss').on('click', function() {
				$message.fadeOut(function() {
					$(this).remove();
				});
			});
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		if ($('.personas-container').length > 0) {
			aiBlogPersonas.init();
		}
	});

})(jQuery); 