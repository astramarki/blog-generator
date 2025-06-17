<?php
/**
 * Approved Blogs Page View (New Background Generation Version)
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<hr class="wp-header-end">
	
	<?php wp_nonce_field( 'ai_blog_admin_nonce', '_wpnonce' ); ?>
	
	<div class="ai-blog-admin-content" id="approved-blogs-container">
		<!-- Status Overview -->
		<div class="ai-blog-status-overview">
			<div class="ai-blog-status-card">
				<h3>Queue Status</h3>
				<div class="ai-blog-queue-stats">
					<span id="queue-count" class="queue-stat">-</span>
					<small>Ideas in Queue</small>
				</div>
			</div>
			<div class="ai-blog-status-card">
				<h3>Active Generations</h3>
				<div class="ai-blog-active-stats">
					<span id="active-count" class="active-stat">-</span>
					<small>Currently Generating</small>
				</div>
			</div>
			<div class="ai-blog-status-card">
				<h3>Daily Progress</h3>
				<div class="ai-blog-daily-stats">
					<span id="daily-progress" class="daily-stat">-/-</span>
					<small>Generated Today</small>
				</div>
			</div>
		</div>
		
		<!-- Main Controls -->
		<div class="ai-blog-main-controls">
			<div class="ai-blog-bulk-actions">
				<select id="bulk-action-select">
					<option value="">Bulk Actions</option>
					<option value="generate-selected">Generate Selected</option>
					<option value="remove-selected">Remove from Queue</option>
					<option value="move-to-top">Move to Top</option>
				</select>
				<button type="button" class="button" id="apply-bulk-action" disabled>Apply</button>
			</div>
			<div class="ai-blog-queue-controls">
				<button type="button" class="button" id="refresh-queue">
					<span class="dashicons dashicons-update"></span>
					Refresh
				</button>
				<button type="button" class="button button-secondary" id="generate-all">
					<span class="dashicons dashicons-admin-tools"></span>
					Generate All
				</button>
				<button type="button" class="button button-primary" id="add-more-ideas">
					<span class="dashicons dashicons-plus-alt"></span>
					Add More Ideas
				</button>
			</div>
		</div>
		
		<!-- Ideas Queue -->
		<div id="approved-ideas-container">
			<div id="loading-state" class="ai-blog-loading-state">
				<span class="dashicons dashicons-update spin"></span>
				<p>Loading approved ideas...</p>
			</div>
			
			<div id="empty-state" class="ai-blog-empty-state" style="display: none;">
				<span class="dashicons dashicons-lightbulb"></span>
				<h3>No Approved Ideas</h3>
				<p>You don't have any approved ideas ready for generation.</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-generator-ideas' ) ); ?>" class="button button-primary">
					Go to Blog Ideas
				</a>
			</div>
			
			<div id="ideas-list" style="display: none;">
				<!-- Ideas will be loaded here -->
			</div>
		</div>
	</div>
</div>

<!-- Edit Idea Modal -->
<div id="edit-idea-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2>Edit Approved Idea</h2>
			<span class="ai-blog-modal-close">&times;</span>
		</div>
		<form class="ai-blog-modal-body" id="edit-idea-form">
			<input type="hidden" id="edit-idea-id" name="idea_id">
			
			<div class="form-field">
				<label for="edit-idea-title">Title *</label>
				<input type="text" id="edit-idea-title" name="title" required>
			</div>
			
			<div class="form-field">
				<label for="edit-idea-description">Description *</label>
				<textarea id="edit-idea-description" name="description" rows="4" required></textarea>
			</div>
			
			<div class="form-field">
				<label for="edit-idea-category">Category</label>
				<select id="edit-idea-category" name="category_id">
					<option value="0">General</option>
					<?php if ( isset( $categories ) && is_array( $categories ) ) : ?>
						<?php foreach ( $categories as $category ) : ?>
							<?php if ( is_object( $category ) && isset( $category->term_id ) && isset( $category->name ) ) : ?>
								<option value="<?php echo esc_attr( $category->term_id ); ?>">
									<?php echo esc_html( $category->name ); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
		<?php endif; ?>
				</select>
			</div>
			
			<div class="form-field">
				<label for="edit-idea-persona">Writing Persona</label>
				<select id="edit-idea-persona" name="persona_id">
					<option value="">No specific persona</option>
					<?php if ( isset( $personas ) && is_array( $personas ) ) : ?>
						<?php foreach ( $personas as $persona ) : ?>
							<?php if ( is_object( $persona ) && isset( $persona->id ) && isset( $persona->name ) ) : ?>
								<option value="<?php echo esc_attr( $persona->id ); ?>">
									<?php echo esc_html( $persona->name . ( isset( $persona->tone ) ? ' (' . ucfirst( $persona->tone ) . ')' : '' ) ); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>
		</form>
		<div class="ai-blog-modal-footer">
			<button type="button" class="button ai-blog-modal-close">Cancel</button>
			<button type="button" class="button button-secondary" id="save-idea-only">Save Changes</button>
			<button type="button" class="button button-primary" id="save-and-generate">Save & Generate</button>
		</div>
	</div>
</div>

<style>
/* Status Overview */
.ai-blog-status-overview {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.ai-blog-status-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ai-blog-status-card h3 {
	margin: 0 0 15px 0;
	font-size: 14px;
	color: #646970;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.queue-stat, .active-stat, .daily-stat {
	display: block;
	font-size: 32px;
	font-weight: 600;
	color: #1d2327;
	margin-bottom: 5px;
}

.queue-stat { color: #0073aa; }
.active-stat { color: #00a32a; }
.daily-stat { color: #8c8f94; }

/* Main Controls */
.ai-blog-main-controls {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	padding: 15px;
	background: #f8f9fa;
	border: 1px solid #e1e5e9;
	border-radius: 6px;
}

.ai-blog-bulk-actions {
	display: flex;
	gap: 10px;
	align-items: center;
}

.ai-blog-queue-controls {
	display: flex;
	gap: 10px;
}

/* Ideas List */
#ideas-list {
	display: grid;
	gap: 15px;
}

.idea-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 20px;
	position: relative;
	transition: all 0.3s ease;
}

.idea-card:hover {
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.idea-card.generating {
	border-left: 4px solid #00a32a;
	background: #f0fff0;
}

.idea-card.error {
	border-left: 4px solid #dc3232;
	background: #fff0f0;
}

.idea-card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 15px;
}

.idea-card-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
	flex: 1;
	margin-right: 15px;
}

.idea-card-checkbox {
	margin-top: 2px;
}

.idea-card-meta {
	margin-bottom: 15px;
	font-size: 13px;
	color: #646970;
}

.idea-card-description {
	margin-bottom: 15px;
	color: #50575e;
	line-height: 1.5;
}

.idea-card-actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.idea-card-buttons {
	display: flex;
	gap: 8px;
}

/* Generation Status */
.generation-status {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px;
	background: #f0f8ff;
	border: 1px solid #0073aa;
	border-radius: 4px;
	margin-bottom: 15px;
}

.generation-status.error {
	background: #fff0f0;
	border-color: #dc3232;
}

.generation-progress {
	flex: 1;
}

.progress-bar {
	width: 100%;
	height: 8px;
	background: #e1e1e1;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 5px;
}

.progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #0073aa, #00a32a);
	border-radius: 4px;
	transition: width 0.3s ease;
}

.progress-text {
	font-size: 12px;
	color: #646970;
}

.cancel-generation {
	color: #dc3232;
	text-decoration: none;
	font-size: 12px;
}

.cancel-generation:hover {
	color: #a00;
}

/* Loading and Empty States */
.ai-blog-loading-state, .ai-blog-empty-state {
	text-align: center;
	padding: 60px 20px;
	color: #646970;
}

.ai-blog-loading-state .dashicons,
.ai-blog-empty-state .dashicons {
	font-size: 48px;
	color: #c3c4c7;
	margin-bottom: 20px;
}

.ai-blog-loading-state .spin {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

/* Modal Styles */
.ai-blog-modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.5);
	display: none;
	align-items: center;
	justify-content: center;
}

.ai-blog-modal-content {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 10px 25px rgba(0,0,0,0.3);
	max-width: 600px;
	width: 90%;
	max-height: 90%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.ai-blog-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #e1e5e9;
	background: #f8f9fa;
}

.ai-blog-modal-header h2 {
	margin: 0;
	font-size: 18px;
}

.ai-blog-modal-close {
	background: none;
	border: none;
	font-size: 20px;
	cursor: pointer;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
}

.ai-blog-modal-close:hover {
	background: #e1e5e9;
}

.ai-blog-modal-body {
	flex: 1;
	padding: 20px;
	overflow-y: auto;
}

.ai-blog-modal-footer {
	padding: 15px 20px;
	border-top: 1px solid #e1e5e9;
	background: #f8f9fa;
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

.form-field {
	margin-bottom: 20px;
}

.form-field label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
	color: #1d2327;
}

.form-field input,
.form-field textarea,
.form-field select {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	font-size: 14px;
}

.form-field input:focus,
.form-field textarea:focus,
.form-field select:focus {
	border-color: #0073aa;
	box-shadow: 0 0 0 1px #0073aa;
	outline: none;
}

/* Responsive */
@media (max-width: 768px) {
	.ai-blog-main-controls {
		flex-direction: column;
		gap: 15px;
		align-items: stretch;
	}
	
	.idea-card-header {
		flex-direction: column;
		gap: 10px;
	}
	
	.idea-card-actions {
		flex-direction: column;
		gap: 10px;
		align-items: stretch;
	}
}
</style> 
 
<script>
jQuery(document).ready(function($) {
	window.aiBlogApprovedBlogs = {
		init: function() {
			this.loadApprovedIdeas();
			this.bindEvents();
			this.startStatusPolling();
		},
		
		loadApprovedIdeas: function() {
			var self = this;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_get_approved_ideas',
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.renderIdeas(response.data.ideas, response.data.generation_statuses);
						self.updateOverviewStats(response.data);
					} else {
						self.showError(response.data.message);
					}
				},
				error: function() {
					self.showError('Failed to load approved ideas.');
				},
				complete: function() {
					$('#loading-state').hide();
				}
			});
		},
		
		renderIdeas: function(ideas, statuses) {
			var $list = $('#ideas-list');
			var $emptyState = $('#empty-state');
			
			if (!ideas || ideas.length === 0) {
				$list.hide();
				$emptyState.show();
				return;
			}
			
			$emptyState.hide();
			$list.empty().show();
			
			var self = this;
			ideas.forEach(function(idea) {
				var $card = self.createIdeaCard(idea, statuses[idea.id]);
				$list.append($card);
			});
			
			this.updateBulkActionsState();
		},
		
		createIdeaCard: function(idea, status) {
			var isGenerating = status && status.status !== 'complete' && status.status !== 'error';
			var hasError = status && status.status === 'error';
			
			var $card = $('<div class="idea-card">');
			if (isGenerating) $card.addClass('generating');
			if (hasError) $card.addClass('error');
			
			var $header = $('<div class="idea-card-header">');
			var $title = $('<h3 class="idea-card-title">').text(idea.title);
			var $checkbox = $('<input type="checkbox" class="idea-card-checkbox" data-idea-id="' + idea.id + '">');
			
			if (isGenerating) {
				$checkbox.prop('disabled', true);
			}
			
			$header.append($title).append($checkbox);
			$card.append($header);
			
			// Meta information
			var $meta = $('<div class="idea-card-meta">');
			if (idea.category) {
				$meta.append('<span><strong>Category:</strong> ' + this.escapeHtml(idea.category) + '</span><br>');
			}
			if (idea.persona_name) {
				$meta.append('<span><strong>Persona:</strong> ' + this.escapeHtml(idea.persona_name) + '</span>');
			}
			$card.append($meta);
			
			// Description
			if (idea.description) {
				var $desc = $('<div class="idea-card-description">').text(idea.description);
				$card.append($desc);
			}
			
			// Generation status
			if (status) {
				var $status = this.createStatusElement(status, idea.id);
				$card.append($status);
			}
			
			// Actions
			var $actions = $('<div class="idea-card-actions">');
			var $buttons = $('<div class="idea-card-buttons">');
			
			if (!isGenerating) {
				$buttons.append('<button type="button" class="button edit-idea" data-idea-id="' + idea.id + '">Edit</button>');
				$buttons.append('<button type="button" class="button button-primary generate-idea" data-idea-id="' + idea.id + '">Generate</button>');
			}
			
			$actions.append($buttons);
			$card.append($actions);
			
			return $card;
		},
		
		createStatusElement: function(status, ideaId) {
			var $status = $('<div class="generation-status">');
			if (status.status === 'error') {
				$status.addClass('error');
			}
			
			var $progress = $('<div class="generation-progress">');
			var $bar = $('<div class="progress-bar"><div class="progress-fill" style="width: ' + (status.progress || 0) + '%"></div></div>');
			var $text = $('<div class="progress-text">').text(status.message || 'Processing...');
			
			$progress.append($bar).append($text);
			$status.append($progress);
			
			if (status.status !== 'complete' && status.status !== 'error') {
				var $cancel = $('<a href="#" class="cancel-generation" data-idea-id="' + ideaId + '">Cancel</a>');
				$status.append($cancel);
			}
			
			return $status;
		},
		
		bindEvents: function() {
			var self = this;
			
			// Refresh button
			$('#refresh-queue').on('click', function() {
				self.loadApprovedIdeas();
			});
			
			// Generate all button
			$('#generate-all').on('click', function() {
				self.generateAll();
			});
			
			// Add more ideas button
			$('#add-more-ideas').on('click', function() {
				window.location.href = 'admin.php?page=ai-blog-generator-ideas';
			});
			
			// Bulk actions
			$('#bulk-action-select').on('change', function() {
				self.updateBulkActionsState();
			});
			
			$('#apply-bulk-action').on('click', function() {
				self.applyBulkAction();
			});
			
			// Checkbox changes
			$(document).on('change', '.idea-card-checkbox', function() {
				self.updateBulkActionsState();
			});
			
			// Individual actions
			$(document).on('click', '.edit-idea', function() {
				var ideaId = $(this).data('idea-id');
				self.editIdea(ideaId);
			});
			
			$(document).on('click', '.generate-idea', function() {
				var ideaId = $(this).data('idea-id');
				self.generateIdea(ideaId);
			});
			
			$(document).on('click', '.cancel-generation', function(e) {
				e.preventDefault();
				var ideaId = $(this).data('idea-id');
				self.cancelGeneration(ideaId);
			});
			
			// Modal events
			$('.ai-blog-modal-close').on('click', function() {
				$('.ai-blog-modal').hide();
			});
			
			$('#save-idea-only').on('click', function() {
				self.saveIdea(false);
			});
			
			$('#save-and-generate').on('click', function() {
				self.saveIdea(true);
			});
		},
		
		startStatusPolling: function() {
			var self = this;
			
			setInterval(function() {
				self.checkGenerationStatuses();
			}, 3000); // Check every 3 seconds
		},
		
		checkGenerationStatuses: function() {
			var self = this;
			var $cards = $('.idea-card.generating');
			
			if ($cards.length === 0) return;
			
			var ideaIds = [];
			$cards.each(function() {
				var $checkbox = $(this).find('.idea-card-checkbox');
				ideaIds.push($checkbox.data('idea-id'));
			});
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_get_generation_status',
					idea_ids: ideaIds,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success && response.data.statuses) {
						self.updateGenerationStatuses(response.data.statuses);
					}
				}
			});
		},
		
		updateGenerationStatuses: function(statuses) {
			var self = this;
			
			Object.keys(statuses).forEach(function(ideaId) {
				var status = statuses[ideaId];
				var $card = $('.idea-card-checkbox[data-idea-id="' + ideaId + '"]').closest('.idea-card');
				var $statusEl = $card.find('.generation-status');
				
				if (status.status === 'complete') {
					// Generation completed, reload the page
					setTimeout(function() {
						self.loadApprovedIdeas();
					}, 2000);
					return;
				}
				
				if (status.status === 'error') {
					$card.removeClass('generating').addClass('error');
					$card.find('.idea-card-checkbox').prop('disabled', false);
				}
				
				// Update status element
				var $newStatus = self.createStatusElement(status, ideaId);
				$statusEl.replaceWith($newStatus);
			});
		},
		
		generateIdea: function(ideaId) {
			var self = this;
			var $button = $('.generate-idea[data-idea-id="' + ideaId + '"]');
			
			$button.prop('disabled', true).text('Starting...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_start_background_generation',
					idea_id: ideaId,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						self.loadApprovedIdeas(); // Reload to show generating state
					} else {
						self.showError(response.data.message);
						$button.prop('disabled', false).text('Generate');
					}
				},
				error: function() {
					self.showError('Failed to start generation.');
					$button.prop('disabled', false).text('Generate');
				}
			});
		},
		
		generateAll: function() {
			var self = this;
			var ideaIds = [];
			
			$('.idea-card-checkbox:not(:disabled)').each(function() {
				ideaIds.push($(this).data('idea-id'));
			});
			
			if (ideaIds.length === 0) {
				this.showError('No ideas available for generation.');
				return;
			}
			
			if (!confirm('Start generation for all ' + ideaIds.length + ' ideas?')) {
				return;
			}
			
			var $button = $('#generate-all');
			$button.prop('disabled', true).text('Starting...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_bulk_start_background_generation',
					idea_ids: ideaIds,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						self.loadApprovedIdeas();
					} else {
						self.showError(response.data.message);
					}
				},
				error: function() {
					self.showError('Failed to start bulk generation.');
				},
				complete: function() {
					$button.prop('disabled', false).text('Generate All');
				}
			});
		},
		
		cancelGeneration: function(ideaId) {
			var self = this;
			
			if (!confirm('Cancel generation for this idea?')) {
				return;
			}
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_cancel_generation',
					idea_id: ideaId,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						self.loadApprovedIdeas();
					} else {
						self.showError(response.data.message);
					}
				}
			});
		},
		
		editIdea: function(ideaId) {
			var self = this;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_get_idea_for_edit',
					idea_id: ideaId,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showEditModal(response.data);
					} else {
						self.showError(response.data.message);
					}
				}
			});
		},
		
		showEditModal: function(idea) {
			$('#edit-idea-id').val(idea.id);
			$('#edit-idea-title').val(idea.title);
			$('#edit-idea-description').val(idea.description);
			$('#edit-idea-category').val(idea.category_id || '0');
			$('#edit-idea-persona').val(idea.persona_id || '');
			
			$('#edit-idea-modal').show();
		},
		
		saveIdea: function(generateAfter) {
			var self = this;
			var $form = $('#edit-idea-form');
			var formData = $form.serialize();
			
			if (generateAfter) {
				formData += '&generate_after=1';
			}
			
			formData += '&action=ai_blog_update_approved_idea&nonce=' + $('#_wpnonce').val();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						$('#edit-idea-modal').hide();
						self.loadApprovedIdeas();
					} else {
						self.showError(response.data.message);
					}
				}
			});
		},
		
		applyBulkAction: function() {
			var action = $('#bulk-action-select').val();
			var selectedIds = [];
			
			$('.idea-card-checkbox:checked:not(:disabled)').each(function() {
				selectedIds.push($(this).data('idea-id'));
			});
			
			if (selectedIds.length === 0) {
				this.showError('Please select at least one idea.');
				return;
			}
			
			switch (action) {
				case 'generate-selected':
					this.bulkGenerate(selectedIds);
					break;
				case 'remove-selected':
					this.bulkRemove(selectedIds);
					break;
				case 'move-to-top':
					this.bulkMoveToTop(selectedIds);
					break;
			}
		},
		
		bulkGenerate: function(ideaIds) {
			if (!confirm('Start generation for ' + ideaIds.length + ' selected ideas?')) {
				return;
			}
			
			var self = this;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_bulk_start_background_generation',
					idea_ids: ideaIds,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						self.loadApprovedIdeas();
					} else {
						self.showError(response.data.message);
					}
				}
			});
		},
		
		bulkRemove: function(ideaIds) {
			if (!confirm('Remove ' + ideaIds.length + ' ideas from the queue?')) {
				return;
			}
			
			var self = this;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_blog_bulk_deny_ideas',
					idea_ids: ideaIds,
					nonce: $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showSuccess(response.data.message);
						self.loadApprovedIdeas();
					} else {
						self.showError(response.data.message);
					}
				}
			});
		},
		
		updateBulkActionsState: function() {
			var selectedCount = $('.idea-card-checkbox:checked:not(:disabled)').length;
			var action = $('#bulk-action-select').val();
			
			$('#apply-bulk-action').prop('disabled', selectedCount === 0 || !action);
		},
		
		updateOverviewStats: function(data) {
			$('#queue-count').text(data.total_count || 0);
			// These would be updated with real data from the server
			$('#active-count').text('0');
			$('#daily-progress').text('0/2');
		},
		
		showSuccess: function(message) {
			// Simple alert for now - could be enhanced with toast notifications
			alert('Success: ' + message);
		},
		
		showError: function(message) {
			alert('Error: ' + message);
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
		}
	};
	
	// Initialize
	window.aiBlogApprovedBlogs.init();
});
</script> 