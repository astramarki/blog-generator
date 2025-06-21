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

		<!-- Stats Section -->
		<?php 
		$total_personas = count($personas);
		$active_personas = count(array_filter($personas, function($p) { return $p->active; }));
		$inactive_personas = $total_personas - $active_personas;
		?>
		<div class="personas-stats">
			<div class="stat-item">
				<div class="stat-value"><?php echo esc_html($total_personas); ?></div>
				<div class="stat-label"><?php esc_html_e('Total Personas', 'ai-blog-generator'); ?></div>
			</div>
			<div class="stat-item">
				<div class="stat-value" style="color: #10b981;"><?php echo esc_html($active_personas); ?></div>
				<div class="stat-label"><?php esc_html_e('Active', 'ai-blog-generator'); ?></div>
			</div>
			<div class="stat-item">
				<div class="stat-value" style="color: #ef4444;"><?php echo esc_html($inactive_personas); ?></div>
				<div class="stat-label"><?php esc_html_e('Inactive', 'ai-blog-generator'); ?></div>
			</div>
		</div>

		<!-- Personas Grid -->
		<div class="personas-grid" id="personas-list">
			<?php if ( empty( $personas ) ) : ?>
				<div class="no-personas-message">
					<p><?php esc_html_e( 'No personas found. Click "Add New" to create your first persona.', 'ai-blog-generator' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $personas as $persona ) : ?>
					<div class="persona-card <?php echo $persona->active ? 'active' : 'inactive'; ?>" data-persona-id="<?php echo esc_attr( $persona->id ); ?>">
						<?php if ( ! $persona->active ) : ?>
							<div class="persona-status-badge"><?php esc_html_e( 'Inactive', 'ai-blog-generator' ); ?></div>
						<?php endif; ?>
						
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
							<?php if ( ! empty( $persona->bio ) ) : ?>
								<div class="persona-bio"><?php echo esc_html( $persona->bio ); ?></div>
							<?php endif; ?>
							
							<div class="persona-meta">
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
									<div class="persona-user">
										<span class="dashicons dashicons-admin-users"></span>
										<span><?php echo esc_html( $user->display_name ); ?></span>
									</div>
								<?php endif; endif; ?>
								
								<?php if ( ! empty( $persona->expertise ) ) : ?>
									<div class="persona-expertise">
										<span class="persona-expertise-label"><?php esc_html_e( 'Expertise', 'ai-blog-generator' ); ?></span>
										<div class="persona-expertise-content"><?php echo esc_html( $persona->expertise ); ?></div>
									</div>
								<?php endif; ?>
							</div>
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
/* Page Header */
.wrap {
	margin-right: 20px;
}

.wrap > h1 {
	font-size: 32px;
	font-weight: 700;
	color: #1e293b;
	margin-bottom: 8px;
	letter-spacing: -0.025em;
}

.page-title-action {
	background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
	color: white !important;
	padding: 8px 20px;
	border-radius: 8px;
	text-decoration: none;
	font-weight: 600;
	font-size: 14px;
	border: none;
	box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.page-title-action:hover {
	background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
	transform: translateY(-1px);
	box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
	color: white !important;
}

.page-title-action::before {
	content: '+';
	font-size: 18px;
	font-weight: 700;
}

.wp-header-end {
	margin: 24px 0;
	border: none;
	height: 1px;
	background: linear-gradient(90deg, transparent 0%, #e5e7eb 20%, #e5e7eb 80%, transparent 100%);
}

/* Personas Grid */
.personas-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
	gap: 24px;
	margin-top: 32px;
}

.no-personas-message {
	grid-column: 1 / -1;
	text-align: center;
	padding: 80px 40px;
	background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
	border: 2px dashed #cbd5e1;
	border-radius: 16px;
	color: #64748b;
}

.no-personas-message p {
	font-size: 16px;
	margin: 0;
}

/* Persona Card */
.persona-card {
	background: white;
	border: 1px solid #e5e7eb;
	border-radius: 16px;
	padding: 0;
	position: relative;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
	overflow: hidden;
}

.persona-card::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 4px;
	background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 50%, #ec4899 100%);
	opacity: 0;
	transition: opacity 0.3s ease;
}

.persona-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
	border-color: #cbd5e1;
}

.persona-card:hover::before {
	opacity: 1;
}

.persona-card.inactive {
	opacity: 0.6;
	background: #f8fafc;
}

.persona-card.inactive:hover {
	transform: translateY(-2px);
}

.persona-card-header {
	padding: 24px 24px 16px;
	border-bottom: 1px solid #f1f5f9;
}

.persona-card-header h3 {
	margin: 0 0 8px 0;
	font-size: 20px;
	font-weight: 700;
	color: #1e293b;
	letter-spacing: -0.025em;
	line-height: 1.2;
}

.persona-actions {
	display: flex;
	gap: 4px;
	margin-top: 12px;
}

.action-icon {
	color: #94a3b8;
	text-decoration: none;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: 8px;
	background: #f8fafc;
	border: 1px solid transparent;
}

.action-icon:hover {
	color: #3b82f6;
	background: #eff6ff;
	border-color: #dbeafe;
	transform: translateY(-1px);
}

.action-icon.delete-persona:hover {
	color: #ef4444;
	background: #fef2f2;
	border-color: #fee2e2;
}

.action-icon .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.persona-card-body {
	padding: 20px 24px 24px;
}

.persona-bio {
	color: #64748b;
	font-size: 14px;
	line-height: 1.6;
	margin-bottom: 16px;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.persona-meta {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.persona-tone {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.tone-label {
	color: #94a3b8;
	font-weight: 600;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-right: 4px;
}

.tone-value {
	color: #3b82f6;
	background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
	padding: 6px 14px;
	border-radius: 20px;
	font-weight: 600;
	font-size: 12px;
	border: 1px solid #bfdbfe;
	transition: all 0.2s ease;
}

.tone-value:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}

.persona-user {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: #f8fafc;
	border-radius: 8px;
	border: 1px solid #e5e7eb;
}

.persona-user .dashicons {
	color: #64748b;
}

.persona-user span:not(.dashicons) {
	color: #475569;
	font-size: 13px;
	font-weight: 500;
}

.persona-expertise {
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid #f1f5f9;
}

.persona-expertise-label {
	color: #94a3b8;
	font-weight: 600;
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-bottom: 6px;
	display: block;
}

.persona-expertise-content {
	color: #64748b;
	font-size: 13px;
	line-height: 1.5;
}

.persona-status-badge {
	position: absolute;
	top: 16px;
	right: 16px;
	background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
	color: white;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

/* Loading State */
.personas-grid.loading {
	position: relative;
	min-height: 400px;
}

.personas-grid.loading::after {
	content: '';
	position: absolute;
	top: 50%;
	left: 50%;
	width: 40px;
	height: 40px;
	margin: -20px 0 0 -20px;
	border: 4px solid #f3f4f6;
	border-radius: 50%;
	border-top-color: #3b82f6;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

/* Stats Section */
.personas-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
	margin: 24px 0;
	padding: 20px;
	background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
	border-radius: 12px;
	border: 1px solid #e5e7eb;
}

.stat-item {
	text-align: center;
}

.stat-value {
	font-size: 28px;
	font-weight: 700;
	color: #1e293b;
	margin-bottom: 4px;
}

.stat-label {
	font-size: 13px;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

/* Modal Styles */
.ai-blog-modal {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.7);
	z-index: 100000;
	align-items: center;
	justify-content: center;
	opacity: 1 !important;
	visibility: visible !important;
	transition: none !important;
	backdrop-filter: blur(5px);
	-webkit-backdrop-filter: blur(5px);
}

.ai-blog-modal-content {
	background: #fff;
	border-radius: 16px;
	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.05);
	width: 90vw;
	height: 90vh;
	max-width: 1600px;
	max-height: 900px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
	from {
		opacity: 0;
		transform: translateY(-20px) scale(0.95);
	}
	to {
		opacity: 1;
		transform: translateY(0) scale(1);
	}
}

.ai-blog-modal-header {
	padding: 28px 40px;
	border-bottom: 1px solid #e5e7eb;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
	position: relative;
}

.ai-blog-modal-header::after {
	content: '';
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	height: 1px;
	background: linear-gradient(90deg, transparent 0%, #e5e7eb 20%, #e5e7eb 80%, transparent 100%);
}

.ai-blog-modal-header h2 {
	margin: 0;
	font-size: 28px;
	font-weight: 700;
	color: #1e293b;
	letter-spacing: -0.025em;
}

.ai-blog-modal-close {
	background: #f1f5f9;
	border: none;
	font-size: 24px;
	cursor: pointer;
	padding: 0;
	width: 44px;
	height: 44px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #64748b;
	transition: all 0.2s ease;
	border-radius: 12px;
	font-weight: 300;
}

.ai-blog-modal-close:hover {
	color: #1e293b;
	background: #e2e8f0;
	transform: rotate(90deg);
}

.ai-blog-modal-body {
	padding: 40px;
	overflow-y: auto;
	flex: 1;
	background: #ffffff;
	scrollbar-width: thin;
	scrollbar-color: #cbd5e1 #f1f5f9;
}

.ai-blog-modal-body::-webkit-scrollbar {
	width: 10px;
}

.ai-blog-modal-body::-webkit-scrollbar-track {
	background: #f1f5f9;
	border-radius: 5px;
}

.ai-blog-modal-body::-webkit-scrollbar-thumb {
	background: #cbd5e1;
	border-radius: 5px;
	border: 2px solid #f1f5f9;
}

.ai-blog-modal-body::-webkit-scrollbar-thumb:hover {
	background: #94a3b8;
}

.ai-blog-modal-footer {
	padding: 24px 40px;
	border-top: 1px solid #e5e7eb;
	display: flex;
	justify-content: flex-end;
	gap: 16px;
	background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
	background: #f8f9fa;
}

.ai-blog-modal-footer .button {
	padding: 12px 24px;
	font-size: 14px;
	line-height: 1.5;
	font-weight: 600;
	min-height: 44px;
	border-radius: 10px;
	transition: all 0.2s ease;
	border: none;
	cursor: pointer;
}

.ai-blog-modal-footer .button-primary {
	background: #3b82f6;
	color: #fff;
	box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
}

.ai-blog-modal-footer .button-primary:hover {
	background: #2563eb;
	transform: translateY(-1px);
	box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.ai-blog-modal-footer .button-secondary {
	background: #f1f5f9;
	color: #475569;
	border: 2px solid #e2e8f0;
}

.ai-blog-modal-footer .button-secondary:hover {
	background: #e2e8f0;
	color: #1e293b;
}

/* Form Styles */
.form-table {
	margin-top: 0;
	border-spacing: 0;
}

.form-table tr {
	border-bottom: 1px solid #f1f5f9;
}

.form-table tr:last-child {
	border-bottom: none;
}

.form-table th {
	width: 280px;
	font-weight: 600;
	padding: 24px 24px 24px 0;
	vertical-align: top;
	color: #334155;
	font-size: 14px;
	letter-spacing: -0.01em;
}

.form-table td {
	padding: 24px 0;
}

.form-table input[type="text"],
.form-table select,
.form-table textarea {
	width: 100%;
	max-width: 700px;
	padding: 12px 16px;
	font-size: 15px;
	line-height: 1.5;
	border: 2px solid #e2e8f0;
	border-radius: 10px;
	background: #ffffff;
	transition: all 0.2s ease;
	color: #1e293b;
}

.form-table input[type="text"]:focus,
.form-table select:focus,
.form-table textarea:focus {
	border-color: #3b82f6;
	box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
	outline: none;
}

.form-table textarea {
	resize: vertical;
	min-height: 100px;
	font-family: inherit;
}

.form-table select {
	cursor: pointer;
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: right 16px center;
	padding-right: 40px;
}

.form-table .description {
	margin-top: 8px;
	font-size: 13px;
	color: #64748b;
	line-height: 1.5;
}

.form-table input[type="checkbox"] {
	width: 18px;
	height: 18px;
	margin-right: 10px;
	vertical-align: middle;
	cursor: pointer;
}

.form-table input[type="number"] {
	width: 80px;
	padding: 8px 12px;
	border: 2px solid #e2e8f0;
	border-radius: 8px;
	font-size: 14px;
	margin-left: 12px;
}

.form-table label {
	font-size: 14px;
	line-height: 1.5;
	color: #475569;
}

.form-table fieldset {
	margin: 0;
	padding: 0;
	border: none;
}

.form-table fieldset label {
	display: flex;
	align-items: center;
	margin-bottom: 12px;
	cursor: pointer;
	transition: color 0.2s ease;
}

.form-table fieldset label:hover {
	color: #1e293b;
}

.required {
	color: #ef4444;
	font-weight: 600;
	margin-left: 4px;
}

/* Notices */
#persona-notices {
	margin-top: 20px;
}

#persona-notices .notice {
	margin: 0 0 20px 0;
	border-radius: 12px;
	border-left-width: 4px;
	animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
	from {
		opacity: 0;
		transform: translateY(-10px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

#persona-notices .notice-success {
	background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
	border-left-color: #10b981;
	color: #065f46;
}

#persona-notices .notice-error {
	background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
	border-left-color: #ef4444;
	color: #991b1b;
}

#persona-notices .notice-warning {
	background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
	border-left-color: #f59e0b;
	color: #92400e;
}

/* Empty State Animation */
@keyframes float {
	0%, 100% { transform: translateY(0); }
	50% { transform: translateY(-10px); }
}

.no-personas-message::before {
	content: '👤';
	font-size: 64px;
	display: block;
	margin-bottom: 16px;
	animation: float 3s ease-in-out infinite;
}

/* Card Entry Animation */
.persona-card {
	animation: fadeInUp 0.4s ease-out;
	animation-fill-mode: both;
}

.persona-card:nth-child(1) { animation-delay: 0.1s; }
.persona-card:nth-child(2) { animation-delay: 0.2s; }
.persona-card:nth-child(3) { animation-delay: 0.3s; }
.persona-card:nth-child(4) { animation-delay: 0.4s; }
.persona-card:nth-child(5) { animation-delay: 0.5s; }
.persona-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes fadeInUp {
	from {
		opacity: 0;
		transform: translateY(20px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Action Icons Animation */
.action-icon {
	position: relative;
	overflow: hidden;
}

.action-icon::after {
	content: '';
	position: absolute;
	top: 50%;
	left: 50%;
	width: 0;
	height: 0;
	border-radius: 50%;
	background: rgba(59, 130, 246, 0.2);
	transform: translate(-50%, -50%);
	transition: width 0.3s, height 0.3s;
}

.action-icon:active::after {
	width: 100%;
	height: 100%;
}

/* Smooth Scrollbar for Page */
body.wp-admin {
	scrollbar-width: thin;
	scrollbar-color: #cbd5e1 #f1f5f9;
}

body.wp-admin::-webkit-scrollbar {
	width: 12px;
}

body.wp-admin::-webkit-scrollbar-track {
	background: #f1f5f9;
}

body.wp-admin::-webkit-scrollbar-thumb {
	background: #cbd5e1;
	border-radius: 6px;
	border: 3px solid #f1f5f9;
}

body.wp-admin::-webkit-scrollbar-thumb:hover {
	background: #94a3b8;
}

/* Responsive */
@media (max-width: 1400px) {
	.personas-grid {
		grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	}
}

@media (max-width: 1200px) {
	.ai-blog-modal-content {
		width: 95vw;
		height: 95vh;
	}
	
	.form-table th {
		width: 220px;
	}
	
	.personas-grid {
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 20px;
	}
}

@media (max-width: 960px) {
	.personas-stats {
		grid-template-columns: repeat(3, 1fr);
		gap: 12px;
		padding: 16px;
	}
	
	.stat-value {
		font-size: 24px;
	}
	
	.stat-label {
		font-size: 12px;
	}
}

@media (max-width: 782px) {
	.wrap > h1 {
		font-size: 24px;
	}
	
	.page-title-action {
		padding: 6px 16px;
		font-size: 13px;
	}
	
	.personas-grid {
		grid-template-columns: 1fr;
		gap: 16px;
		margin-top: 20px;
	}
	
	.persona-card {
		animation: none;
	}
	
	.persona-card-header {
		padding: 20px 20px 12px;
	}
	
	.persona-card-header h3 {
		font-size: 18px;
	}
	
	.persona-card-body {
		padding: 16px 20px 20px;
	}
	
	.action-icon {
		width: 32px;
		height: 32px;
	}
	
	.personas-stats {
		margin: 16px 0;
		border-radius: 8px;
	}
}

@media (max-width: 600px) {
	.personas-stats {
		grid-template-columns: 1fr;
		gap: 8px;
		padding: 12px;
	}
	
	.stat-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 8px 0;
		border-bottom: 1px solid #e5e7eb;
	}
	
	.stat-item:last-child {
		border-bottom: none;
	}
	
	.stat-value {
		font-size: 20px;
	}
	
	.stat-label {
		font-size: 13px;
		text-transform: none;
	}
}
	
	.ai-blog-modal {
		backdrop-filter: none;
		-webkit-backdrop-filter: none;
	}
	
	.ai-blog-modal-content {
		width: 100%;
		height: 100%;
		max-width: none;
		max-height: none;
		border-radius: 0;
		animation: none;
	}
	
	.ai-blog-modal-header {
		padding: 20px;
		border-radius: 0;
	}
	
	.ai-blog-modal-header h2 {
		font-size: 22px;
	}
	
	.ai-blog-modal-close {
		width: 40px;
		height: 40px;
	}
	
	.ai-blog-modal-body {
		padding: 20px;
	}
	
	.ai-blog-modal-footer {
		padding: 20px;
		flex-wrap: wrap;
		gap: 12px;
	}
	
	.ai-blog-modal-footer .button {
		flex: 1;
		min-width: 120px;
	}
	
	.form-table th {
		width: auto;
		display: block;
		padding: 16px 0 8px 0;
		font-size: 13px;
	}
	
	.form-table td {
		display: block;
		padding: 0 0 20px 0;
	}
	
	.form-table input[type="text"],
	.form-table select,
	.form-table textarea {
		max-width: none;
		font-size: 16px; /* Prevent zoom on iOS */
	}
	
	.form-table tr {
		border-bottom: none;
		padding-bottom: 16px;
		margin-bottom: 16px;
		border-bottom: 1px solid #f1f5f9;
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