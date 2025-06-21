<?php
/**
 * Personas management page
 *
 * @package AI_Blog_Generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get personas
$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
$personas = $persona_model->get_all( [], 'name ASC' );

// Get WordPress users for dropdown
$users = get_users( [
	'orderby' => 'display_name',
	'order' => 'ASC',
] );

// Get tone options
$tone_options = $persona_model->get_tone_options();

// Get contexts
$context_model = new \AI_Blog_Generator\Models\Context_Model();
$contexts = $context_model->get_active();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Blog Personas', 'ai-blog-generator' ); ?></h1>
	<a href="#" class="page-title-action" id="add-new-persona"><?php esc_html_e( 'Add New', 'ai-blog-generator' ); ?></a>
	
	<hr class="wp-header-end">

	<div id="ai-blog-personas-app">
		<!-- Notices -->
		<div id="persona-notices"></div>

		<!-- Personas Grid -->
		<div class="personas-grid" id="personas-list">
			<?php if ( empty( $personas ) ) : ?>
				<div class="no-personas-message">
					<p><?php esc_html_e( 'No personas found. Click "Add New" to create your first persona.', 'ai-blog-generator' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $personas as $persona ) : ?>
					<div class="persona-card <?php echo $persona->active ? 'active' : 'inactive'; ?>" data-persona-id="<?php echo esc_attr( $persona->id ); ?>">
						<div class="persona-card-header">
							<h3><?php echo esc_html( $persona->name ); ?></h3>
							<div class="persona-actions">
								<a href="#" class="action-icon edit-persona" data-persona-id="<?php echo esc_attr( $persona->id ); ?>" title="<?php esc_attr_e( 'Edit', 'ai-blog-generator' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</a>
								<a href="#" class="action-icon toggle-persona" data-persona-id="<?php echo esc_attr( $persona->id ); ?>" title="<?php echo $persona->active ? esc_attr__( 'Deactivate', 'ai-blog-generator' ) : esc_attr__( 'Activate', 'ai-blog-generator' ); ?>">
									<span class="dashicons dashicons-<?php echo $persona->active ? 'hidden' : 'visibility'; ?>"></span>
								</a>
								<a href="#" class="action-icon delete-persona" data-persona-id="<?php echo esc_attr( $persona->id ); ?>" title="<?php esc_attr_e( 'Delete', 'ai-blog-generator' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</a>
							</div>
						</div>
						<div class="persona-card-body">
							<div class="persona-tone">
								<span class="tone-label"><?php esc_html_e( 'Tones:', 'ai-blog-generator' ); ?></span>
								<?php 
								$tones = explode(',', $persona->tone);
								foreach ($tones as $tone) :
									$tone = trim($tone);
									if (!empty($tone)) :
								?>
									<span class="tone-value"><?php echo esc_html( $tone_options[ $tone ] ?? $tone ); ?></span>
								<?php 
									endif;
								endforeach; 
								?>
							</div>
							<?php if ( $persona->wordpress_user_id ) : 
								$user = get_user_by( 'ID', $persona->wordpress_user_id );
								if ( $user ) :
							?>
								<div class="persona-user" style="margin-top: 10px; font-size: 13px; color: #646970;">
									<span class="dashicons dashicons-admin-users" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
									<?php echo esc_html( $user->display_name ); ?>
								</div>
							<?php endif; endif; ?>
							<?php if ( ! $persona->active ) : ?>
								<div class="persona-status-overlay">
									<span><?php esc_html_e( 'Inactive', 'ai-blog-generator' ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- Edit/Create Modal -->
		<div id="persona-modal" class="ai-blog-modal">
			<div class="ai-blog-modal-content">
				<div class="ai-blog-modal-header">
					<h2 id="modal-title"><?php esc_html_e( 'Add New Persona', 'ai-blog-generator' ); ?></h2>
					<button class="ai-blog-modal-close">&times;</button>
				</div>
				<div class="ai-blog-modal-body">
					<form id="persona-form">
						<input type="hidden" id="persona-id" name="persona_id" value="">
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="persona-name"><?php esc_html_e( 'Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<input type="text" id="persona-name" name="name" class="regular-text" required>
									<p class="description"><?php esc_html_e( 'The persona\'s name (e.g., "Tech Expert", "Lifestyle Blogger")', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-bio"><?php esc_html_e( 'Biography', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<textarea id="persona-bio" name="bio" rows="4" class="large-text" required></textarea>
									<p class="description"><?php esc_html_e( 'A brief biography that will help shape the persona\'s writing voice.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-expertise"><?php esc_html_e( 'Expertise', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<textarea id="persona-expertise" name="expertise" rows="3" class="large-text"></textarea>
									<p class="description"><?php esc_html_e( 'Comma-separated list of expertise areas (e.g., "technology, AI, machine learning")', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-writing-style"><?php esc_html_e( 'Writing Style', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<textarea id="persona-writing-style" name="writing_style" rows="3" class="large-text"></textarea>
									<p class="description"><?php esc_html_e( 'Describe the writing style (e.g., "Clear and concise with technical accuracy")', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Tones', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php esc_html_e( 'Select Tones', 'ai-blog-generator' ); ?></legend>
										<?php foreach ( $tone_options as $value => $label ) : ?>
											<label style="display: block; margin-bottom: 8px;">
												<input type="checkbox" name="tones[]" value="<?php echo esc_attr( $value ); ?>" class="persona-tone-checkbox">
												<?php echo esc_html( $label ); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Select one or more tones that describe this persona\'s writing style.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-layout-style"><?php esc_html_e( 'Layout Style', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<textarea id="persona-layout-style" name="layout_style" rows="3" class="large-text"></textarea>
									<p class="description"><?php esc_html_e( 'Preferred content layout and structure preferences.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-layout-rules"><?php esc_html_e( 'Layout Rules', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<textarea id="persona-layout-rules" name="layout_rules" rows="3" class="large-text"></textarea>
									<p class="description"><?php esc_html_e( 'Specific layout rules or guidelines (e.g., "Always include a summary section")', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-wordpress-user"><?php esc_html_e( 'WordPress User', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<select id="persona-wordpress-user" name="wordpress_user_id" class="regular-text">
										<option value=""><?php esc_html_e( '— Auto-create User —', 'ai-blog-generator' ); ?></option>
										<?php foreach ( $users as $user ) : ?>
											<option value="<?php echo esc_attr( $user->ID ); ?>">
												<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Associate this persona with a WordPress user account. If none is selected, a new author account will be automatically created.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="persona-active"><?php esc_html_e( 'Status', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<label>
										<input type="checkbox" id="persona-active" name="active" value="1" checked>
										<?php esc_html_e( 'Active', 'ai-blog-generator' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Only active personas can be used for content generation.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Image Settings', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<fieldset>
										<label style="display: block; margin-bottom: 10px;">
											<input type="checkbox" id="persona-uses-seed-images" name="uses_seed_mages" value="1">
											<?php esc_html_e( 'Use seed images for generation', 'ai-blog-generator' ); ?>
										</label>
										<label style="display: block; margin-bottom: 10px;">
											<?php esc_html_e( 'Number of images:', 'ai-blog-generator' ); ?>
											<input type="number" id="persona-number-of-images" name="number_of_images" min="0" max="10" style="width: 60px; margin-left: 10px;">
										</label>
										<label style="display: block; margin-bottom: 10px;">
											<input type="checkbox" id="persona-uses-charts" name="uses_charts" value="1">
											<?php esc_html_e( 'Include charts and graphs', 'ai-blog-generator' ); ?>
										</label>
									</fieldset>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Layout Settings', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<fieldset>
										<label style="display: block; margin-bottom: 10px;">
											<input type="checkbox" id="persona-uses-avada-layouts" name="uses_avada_layouts" value="1">
											<?php esc_html_e( 'Use Avada layouts', 'ai-blog-generator' ); ?>
										</label>
										<label style="display: block; margin-bottom: 10px;">
											<input type="checkbox" id="persona-uses-plain-html" name="uses_plain_html" value="1">
											<?php esc_html_e( 'Use plain HTML (no page builder)', 'ai-blog-generator' ); ?>
										</label>
									</fieldset>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Include Contexts', 'ai-blog-generator' ); ?></label>
								</th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php esc_html_e( 'Select Contexts', 'ai-blog-generator' ); ?></legend>
										<?php if ( ! empty( $contexts ) ) : ?>
											<?php foreach ( $contexts as $context ) : ?>
												<label style="display: block; margin-bottom: 8px;">
													<input type="checkbox" name="contexts[]" value="<?php echo esc_attr( $context->id ); ?>" class="persona-context-checkbox">
													<?php echo esc_html( $context->name ); ?>
													<?php if ( ! empty( $context->description ) ) : ?>
														<span style="color: #646970; font-size: 12px;">(<?php echo esc_html( $context->description ); ?>)</span>
													<?php endif; ?>
												</label>
											<?php endforeach; ?>
										<?php else : ?>
											<p class="description"><?php esc_html_e( 'No contexts available. Create contexts first.', 'ai-blog-generator' ); ?></p>
										<?php endif; ?>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Select which contexts should be included when this persona generates content.', 'ai-blog-generator' ); ?></p>
								</td>
							</tr>
						</table>
					</form>
				</div>
				<div class="ai-blog-modal-footer">
					<button type="button" class="button button-secondary cancel-modal"><?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?></button>
					<button type="button" class="button button-primary" id="save-persona"><?php esc_html_e( 'Save Persona', 'ai-blog-generator' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
/* Personas Grid */
.personas-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.no-personas-message {
	grid-column: 1 / -1;
	text-align: center;
	padding: 40px;
	background: #f0f0f1;
	border: 1px dashed #c3c4c7;
	border-radius: 4px;
}

/* Persona Card */
.persona-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	position: relative;
	transition: all 0.2s ease;
	box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.persona-card:hover {
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	border-color: #8c8f94;
}

.persona-card.inactive {
	opacity: 0.7;
	background: #f9f9f9;
}

.persona-card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 15px;
}

.persona-card-header h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
	flex: 1;
	padding-right: 10px;
}

.persona-actions {
	display: flex;
	gap: 8px;
}

.action-icon {
	color: #787c82;
	text-decoration: none;
	transition: color 0.2s ease;
	display: inline-block;
	width: 24px;
	height: 24px;
	text-align: center;
	line-height: 24px;
}

.action-icon:hover {
	color: #2271b1;
}

.action-icon.delete-persona:hover {
	color: #d63638;
}

.action-icon .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
}

.persona-card-body {
	position: relative;
}

.persona-tone {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	flex-wrap: wrap;
}

.tone-label {
	color: #646970;
	font-weight: 500;
	margin-right: 4px;
}

.tone-value {
	color: #2271b1;
	background: #f0f8ff;
	padding: 4px 12px;
	border-radius: 3px;
	font-weight: 500;
	font-size: 13px;
	margin-bottom: 4px;
}

.persona-status-overlay {
	position: absolute;
	top: -10px;
	right: -10px;
	background: #d63638;
	color: #fff;
	padding: 4px 12px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
	text-transform: uppercase;
}

/* Modal Styles */
.ai-blog-modal {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
	z-index: 100000;
	align-items: center;
	justify-content: center;
	opacity: 1 !important;
	visibility: visible !important;
	transition: none !important;
}

.ai-blog-modal-content {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
	width: 80vw;
	height: 80vh;
	max-width: 1200px;
	max-height: 800px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.ai-blog-modal-header {
	padding: 24px 30px;
	border-bottom: 1px solid #e0e0e0;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #f8f9fa;
}

.ai-blog-modal-header h2 {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
	color: #1d2327;
}

.ai-blog-modal-close {
	background: none;
	border: none;
	font-size: 28px;
	cursor: pointer;
	padding: 0;
	width: 36px;
	height: 36px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #646970;
	transition: all 0.2s ease;
	border-radius: 4px;
}

.ai-blog-modal-close:hover {
	color: #1d2327;
	background: rgba(0, 0, 0, 0.05);
}

.ai-blog-modal-body {
	padding: 30px;
	overflow-y: auto;
	flex: 1;
	background: #fff;
}

.ai-blog-modal-footer {
	padding: 20px 30px;
	border-top: 1px solid #e0e0e0;
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	background: #f8f9fa;
}

.ai-blog-modal-footer .button {
	padding: 8px 16px;
	font-size: 14px;
	line-height: 1.5;
	min-height: 36px;
}

.ai-blog-modal-footer .button-primary {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

.ai-blog-modal-footer .button-primary:hover {
	background: #135e96;
	border-color: #135e96;
}

/* Form Styles */
.form-table {
	margin-top: 0;
}

.form-table th {
	width: 200px;
	font-weight: 600;
	padding: 20px 10px 20px 0;
	vertical-align: top;
	color: #23282d;
}

.form-table td {
	padding: 20px 10px;
}

.form-table input[type="text"],
.form-table select,
.form-table textarea {
	width: 100%;
	max-width: 600px;
	padding: 8px 12px;
	font-size: 14px;
	line-height: 1.5;
	border: 1px solid #ddd;
	border-radius: 4px;
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.07);
	transition: border-color 0.2s;
}

.form-table input[type="text"]:focus,
.form-table select:focus,
.form-table textarea:focus {
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
	outline: none;
}

.form-table textarea {
	resize: vertical;
	min-height: 80px;
}

.form-table .description {
	margin-top: 8px;
	font-size: 13px;
	color: #646970;
	line-height: 1.5;
}

.form-table input[type="checkbox"] {
	margin-right: 8px;
}

.form-table label {
	font-size: 14px;
	line-height: 1.5;
}

.form-table fieldset {
	margin: 0;
	padding: 0;
	border: none;
}

.form-table fieldset label {
	display: block;
	margin-bottom: 8px;
	cursor: pointer;
}

.form-table fieldset label:hover {
	color: #2271b1;
}

.required {
	color: #d63638;
	font-weight: normal;
	margin-left: 4px;
}

/* Notices */
#persona-notices {
	margin-top: 20px;
}

#persona-notices .notice {
	margin: 0 0 20px 0;
}

/* Responsive */
@media (max-width: 782px) {
	.personas-grid {
		grid-template-columns: 1fr;
	}
	
	.ai-blog-modal-content {
		width: 95vw;
		height: 95vh;
		max-width: none;
		max-height: none;
	}
	
	.ai-blog-modal-header {
		padding: 16px 20px;
	}
	
	.ai-blog-modal-body {
		padding: 20px;
	}
	
	.ai-blog-modal-footer {
		padding: 16px 20px;
	}
	
	.form-table th {
		width: auto;
		display: block;
		padding-bottom: 8px;
		padding-top: 16px;
	}
	
	.form-table td {
		display: block;
		padding: 0 0 16px 0;
	}
	
	.form-table input[type="text"],
	.form-table select,
	.form-table textarea {
		max-width: none;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	console.log('Personas page script loaded');
	
	// Check if aiBlogAjax is available
	if (typeof aiBlogAjax === 'undefined') {
		console.error('AI Blog Generator: aiBlogAjax is not defined. Using fallback values.');
		// Define fallback aiBlogAjax object
		window.aiBlogAjax = {
			ajaxurl: ajaxurl || '/wp-admin/admin-ajax.php',
			nonce: '<?php echo wp_create_nonce( 'ai_blog_admin_nonce' ); ?>'
		};
	}

	// Modal elements
	const $modal = $('#persona-modal');
	const $modalTitle = $('#modal-title');
	const $form = $('#persona-form');
	const $personaId = $('#persona-id');
	const $saveButton = $('#save-persona');
	const $notices = $('#persona-notices');
	
	console.log('Modal element found:', $modal.length > 0);
	console.log('Add button found:', $('#add-new-persona').length > 0);

	// Show add new persona modal
	$('#add-new-persona').on('click', function(e) {
		console.log('Add new persona clicked');
		e.preventDefault();
		e.stopPropagation();
		showModal('add');
		return false;
	});

	// Show edit persona modal
	$(document).on('click', '.edit-persona', function(e) {
		console.log('Edit persona clicked');
		e.preventDefault();
		e.stopPropagation();
		const personaId = $(this).data('persona-id');
		showModal('edit', personaId);
		return false;
	});

	// Toggle persona status
	$(document).on('click', '.toggle-persona', function(e) {
		e.preventDefault();
		const $icon = $(this);
		const personaId = $icon.data('persona-id');
		const $card = $icon.closest('.persona-card');
		
		$icon.css('pointer-events', 'none');
		
		$.post(ajaxurl, {
			action: 'ai_blog_toggle_persona',
			persona_id: personaId,
			nonce: aiBlogAjax.nonce
		})
		.done(function(response) {
			if (response.success) {
				location.reload(); // Reload to update the cards
			} else {
				showNotice('error', response.data.message || 'Failed to toggle persona status.');
			}
		})
		.fail(function() {
			showNotice('error', 'Network error. Please try again.');
		})
		.always(function() {
			$icon.css('pointer-events', 'auto');
		});
	});

	// Delete persona
	$(document).on('click', '.delete-persona', function(e) {
		e.preventDefault();
		const personaId = $(this).data('persona-id');
		const personaName = $(this).closest('.persona-card').find('h3').text();
		
		if (!confirm(`Are you sure you want to delete the persona "${personaName}"? This action cannot be undone.`)) {
			return;
		}
		
		const $icon = $(this);
		const $card = $(this).closest('.persona-card');
		$icon.css('pointer-events', 'none');
		
		$.post(ajaxurl, {
			action: 'ai_blog_delete_persona',
			persona_id: personaId,
			nonce: aiBlogAjax.nonce
		})
		.done(function(response) {
			if (response.success) {
				$card.fadeOut(function() {
					$(this).remove();
					if ($('.persona-card').length === 0) {
						$('#personas-list').html('<div class="no-personas-message"><p>No personas found. Click "Add New" to create your first persona.</p></div>');
					}
				});
				showNotice('success', response.data.message);
			} else {
				showNotice('error', response.data.message || 'Failed to delete persona.');
			}
		})
		.fail(function() {
			showNotice('error', 'Network error. Please try again.');
		})
		.always(function() {
			$icon.css('pointer-events', 'auto');
		});
	});

	// Save persona
	$saveButton.on('click', function() {
		const $button = $(this);
		const isEdit = $personaId.val() !== '';
		
		// Basic validation
		if (!$('#persona-name').val().trim()) {
			showNotice('error', 'Persona name is required.');
			return;
		}
		
		if (!$('#persona-bio').val().trim()) {
			showNotice('error', 'Persona biography is required.');
			return;
		}
		
		$button.prop('disabled', true).text('Saving...');
		
		const formData = {
			action: isEdit ? 'ai_blog_update_persona' : 'ai_blog_create_persona',
			nonce: aiBlogAjax.nonce
		};
		
		// Collect form data
		$form.find('input, textarea, select').each(function() {
			const $field = $(this);
			const name = $field.attr('name');
			if (name && name !== 'tones[]' && name !== 'contexts[]') {
				if ($field.attr('type') === 'checkbox') {
					formData[name] = $field.is(':checked') ? 1 : 0;
				} else if ($field.attr('type') === 'number') {
					formData[name] = $field.val() || null;
				} else {
					formData[name] = $field.val();
				}
			}
		});
		
		// Collect selected tones
		const selectedTones = [];
		$('.persona-tone-checkbox:checked').each(function() {
			selectedTones.push($(this).val());
		});
		formData.tone = selectedTones.join(',');
		
		// Collect selected contexts
		const selectedContexts = [];
		$('.persona-context-checkbox:checked').each(function() {
			selectedContexts.push($(this).val());
		});
		formData.include_contexts = selectedContexts.join(',');
		
		$.post(ajaxurl, formData)
		.done(function(response) {
			if (response.success) {
				showNotice('success', response.data.message);
				hideModal();
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice('error', response.data.message || 'Failed to save persona.');
			}
		})
		.fail(function() {
			showNotice('error', 'Network error. Please try again.');
		})
		.always(function() {
			$button.prop('disabled', false).text('Save Persona');
		});
	});

	// Close modal
	$('.ai-blog-modal-close, .cancel-modal').on('click', function() {
		hideModal();
	});

	// Close modal on background click
	$modal.on('click', function(e) {
		if (e.target === this) {
			hideModal();
		}
	});

	// Show modal function
	function showModal(mode, personaId) {
		console.log('showModal called with mode:', mode, 'personaId:', personaId);
		
		if (mode === 'add') {
			$modalTitle.text('Add New Persona');
			$form[0].reset();
			$personaId.val('');
			// Force show the modal
			$modal.show();
			$modal.css({
				'display': 'flex',
				'position': 'fixed',
				'top': '0',
				'left': '0',
				'width': '100%',
				'height': '100%',
				'background-color': 'rgba(0, 0, 0, 0.5)',
				'z-index': '100000',
				'opacity': '1',
				'visibility': 'visible'
			});
			// Ensure opacity is set after a brief delay to override any transitions
			setTimeout(function() {
				$modal.css('opacity', '1');
			}, 10);
		} else if (mode === 'edit' && personaId) {
			$modalTitle.text('Edit Persona');
			
			// Load persona data
			$.post(ajaxurl, {
				action: 'ai_blog_get_persona',
				persona_id: personaId,
				nonce: aiBlogAjax.nonce
			})
			.done(function(response) {
				if (response.success && response.data.persona) {
					const persona = response.data.persona;
					$personaId.val(persona.id);
					$('#persona-name').val(persona.name || '');
					$('#persona-bio').val(persona.bio || '');
					$('#persona-expertise').val(persona.expertise || '');
					$('#persona-writing-style').val(persona.writing_style || '');
					
					// Handle multiple tones
					$('.persona-tone-checkbox').prop('checked', false);
					if (persona.tone) {
						const tones = persona.tone.split(',').map(t => t.trim());
						tones.forEach(function(tone) {
							$(`.persona-tone-checkbox[value="${tone}"]`).prop('checked', true);
						});
					}
					
					$('#persona-layout-style').val(persona.layout_style || '');
					$('#persona-layout-rules').val(persona.layout_rules || '');
					$('#persona-wordpress-user').val(persona.wordpress_user_id || '');
					$('#persona-active').prop('checked', persona.active);
					
					// Set new fields
					$('#persona-uses-seed-images').prop('checked', persona.uses_seed_mages);
					$('#persona-number-of-images').val(persona.number_of_images || '');
					$('#persona-uses-charts').prop('checked', persona.uses_charts);
					$('#persona-uses-avada-layouts').prop('checked', persona.uses_avada_layouts);
					$('#persona-uses-plain-html').prop('checked', persona.uses_plain_html);
					
					// Handle contexts
					$('.persona-context-checkbox').prop('checked', false);
					if (persona.include_contexts) {
						const contexts = persona.include_contexts.split(',').map(c => c.trim());
						contexts.forEach(function(contextId) {
							$(`.persona-context-checkbox[value="${contextId}"]`).prop('checked', true);
						});
					}
					// Force show the modal
					$modal.show();
					$modal.css({
						'display': 'flex',
						'position': 'fixed',
						'top': '0',
						'left': '0',
						'width': '100%',
						'height': '100%',
						'background-color': 'rgba(0, 0, 0, 0.5)',
						'z-index': '100000',
						'opacity': '1',
						'visibility': 'visible'
					});
					// Ensure opacity is set after a brief delay to override any transitions
					setTimeout(function() {
						$modal.css('opacity', '1');
					}, 10);
				} else {
					showNotice('error', 'Failed to load persona data.');
				}
			})
			.fail(function() {
				showNotice('error', 'Network error. Please try again.');
			});
		}
	}

	// Hide modal function
	function hideModal() {
		$modal.hide();
		$modal.css('display', 'none');
		$form[0].reset();
		$personaId.val('');
	}

	// Show notice function
	function showNotice(type, message) {
		const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
		const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
		
		$notices.html($notice);
		
		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$notice.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}
});
</script> 