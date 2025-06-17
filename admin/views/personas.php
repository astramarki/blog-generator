<?php
/**
 * Personas Page View
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure variables are set
$personas = isset( $personas ) ? $personas : [];
$tone_options = isset( $tone_options ) ? $tone_options : [];
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Writing Personas', 'ai-blog-generator' ); ?>
	</h1>
	<a href="#" class="page-title-action" id="add-new-persona">
		<?php esc_html_e( 'Add New Persona', 'ai-blog-generator' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="personas-container">
		<div class="personas-grid" id="personas-list">
			<?php if ( empty( $personas ) ) : ?>
				<div class="no-personas-message">
					<p><?php esc_html_e( 'No personas found. Click "Add New Persona" to create your first writing persona.', 'ai-blog-generator' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $personas as $persona ) : ?>
					<div class="persona-card" data-persona-id="<?php echo esc_attr( $persona['id'] ); ?>">
						<div class="persona-header">
							<h3><?php echo esc_html( $persona['name'] ); ?></h3>
							<span class="persona-tone <?php echo esc_attr( $persona['tone'] ); ?>">
								<?php echo esc_html( ucfirst( $persona['tone'] ) ); ?>
							</span>
						</div>
						
						<div class="persona-bio">
							<p><?php echo esc_html( $persona['bio'] ); ?></p>
						</div>
						
						<?php if ( ! empty( $persona['expertise'] ) ) : ?>
							<div class="persona-expertise">
								<strong><?php esc_html_e( 'Expertise:', 'ai-blog-generator' ); ?></strong>
								<p><?php echo esc_html( $persona['expertise'] ); ?></p>
							</div>
						<?php endif; ?>
						
						<?php if ( ! empty( $persona['writing_style'] ) ) : ?>
							<div class="persona-writing-style">
								<strong><?php esc_html_e( 'Writing Style:', 'ai-blog-generator' ); ?></strong>
								<p><?php echo esc_html( $persona['writing_style'] ); ?></p>
							</div>
						<?php endif; ?>
						
						<div class="persona-status">
							<span class="status-indicator <?php echo $persona['active'] ? 'active' : 'inactive'; ?>">
								<?php echo $persona['active'] ? esc_html__( 'Active', 'ai-blog-generator' ) : esc_html__( 'Inactive', 'ai-blog-generator' ); ?>
							</span>
						</div>
						
						<div class="persona-actions">
							<button class="button edit-persona" data-persona-id="<?php echo esc_attr( $persona['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'ai-blog-generator' ); ?>
							</button>
							<button class="button toggle-persona" data-persona-id="<?php echo esc_attr( $persona['id'] ); ?>" data-current-status="<?php echo esc_attr( $persona['active'] ); ?>">
								<?php echo $persona['active'] ? esc_html__( 'Deactivate', 'ai-blog-generator' ) : esc_html__( 'Activate', 'ai-blog-generator' ); ?>
							</button>
							<button class="button delete-persona" data-persona-id="<?php echo esc_attr( $persona['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'ai-blog-generator' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Persona Edit/Add Modal -->
<div id="persona-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2 id="persona-modal-title"><?php esc_html_e( 'Add New Persona', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close">&times;</button>
		</div>
		
		<div class="ai-blog-modal-body">
			<form id="persona-form">
				<input type="hidden" id="persona-id" name="persona_id" value="">
				
				<div class="form-field">
					<label for="persona-name"><?php esc_html_e( 'Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="text" id="persona-name" name="name" class="regular-text" required>
				</div>
				
				<div class="form-field">
					<label for="persona-bio"><?php esc_html_e( 'Biography', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<textarea id="persona-bio" name="bio" rows="4" class="large-text" required></textarea>
					<p class="description"><?php esc_html_e( 'Describe the persona\'s background, qualifications, and personality.', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="form-field">
					<label for="persona-expertise"><?php esc_html_e( 'Expertise', 'ai-blog-generator' ); ?></label>
					<textarea id="persona-expertise" name="expertise" rows="2" class="large-text"></textarea>
					<p class="description"><?php esc_html_e( 'Comma-separated list of expertise areas (e.g., Education, Marketing, Technology)', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="form-field">
					<label for="persona-writing-style"><?php esc_html_e( 'Writing Style', 'ai-blog-generator' ); ?></label>
					<textarea id="persona-writing-style" name="writing_style" rows="3" class="large-text"></textarea>
					<p class="description"><?php esc_html_e( 'Describe how this persona writes (e.g., uses personal anecdotes, data-driven, conversational).', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="form-field">
					<label for="persona-tone"><?php esc_html_e( 'Tone', 'ai-blog-generator' ); ?></label>
					<select id="persona-tone" name="tone" class="regular-text">
						<?php foreach ( $tone_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="form-field">
					<label for="persona-active">
						<input type="checkbox" id="persona-active" name="active" value="1" checked>
						<?php esc_html_e( 'Active', 'ai-blog-generator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Only active personas will be used for content generation.', 'ai-blog-generator' ); ?></p>
				</div>
			</form>
		</div>
		
		<div class="ai-blog-modal-footer">
			<button type="button" class="button button-secondary ai-blog-modal-close"><?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?></button>
			<button type="button" class="button button-primary" id="save-persona"><?php esc_html_e( 'Save Persona', 'ai-blog-generator' ); ?></button>
		</div>
	</div>
</div> 