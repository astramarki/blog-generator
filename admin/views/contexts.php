<?php
/**
 * Contexts Page View
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
	<a href="#" class="page-title-action" id="add-new-context">
		<?php esc_html_e( 'Add New Context', 'ai-blog-generator' ); ?>
	</a>
	<hr class="wp-header-end">
	
	<p class="description">
		<?php esc_html_e( 'Contexts provide background information for AI content generation. Add business details, product information, SEO guidelines, and more.', 'ai-blog-generator' ); ?>
	</p>
	
	<!-- Context List -->
	<div class="ai-blog-contexts-grid">
			<?php foreach ( $contexts as $context ) : ?>
				<div class="ai-blog-context-card <?php echo $context->active ? 'active' : 'inactive'; ?>" 
					data-context-id="<?php echo esc_attr( $context->id ); ?>">
					<div class="context-header">
						<h3><?php echo esc_html( $context->name ); ?></h3>
						<div class="context-badges">
							<span class="context-type badge badge-<?php echo esc_attr( $context->type ); ?>">
								<?php echo esc_html( $context_types[ $context->type ] ?? $context->type ); ?>
							</span>
							<?php 
							$usage_labels = [
								'ideas' => __( 'Ideas', 'ai-blog-generator' ),
								'content' => __( 'Content', 'ai-blog-generator' ),
								'images' => __( 'Images', 'ai-blog-generator' ),
							];
							$usage = ! empty( $context->usage_flags ) ? $context->usage_flags : 'content';
							?>
							<span class="badge badge-usage badge-usage-<?php echo esc_attr( $usage ); ?>" title="<?php esc_attr_e( 'Usage Category', 'ai-blog-generator' ); ?>">
								<?php echo esc_html( $usage_labels[$usage] ?? $usage ); ?>
							</span>
							<?php if ( ! empty( $context->always_include_content ) ) : ?>
								<span class="badge badge-always-content" title="<?php esc_attr_e( 'Always included in content generation', 'ai-blog-generator' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $context->always_include_images ) ) : ?>
								<span class="badge badge-always-images" title="<?php esc_attr_e( 'Always included in image generation', 'ai-blog-generator' ); ?>">
									<span class="dashicons dashicons-format-image"></span>
								</span>
							<?php endif; ?>
						</div>
					</div>
					
					<div class="context-content">
						<p><?php echo esc_html( wp_trim_words( $context->content, 30 ) ); ?></p>
					</div>
					
					<div class="context-actions">
						<a href="#" class="action-icon edit-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>"
							title="<?php esc_attr_e( 'Edit', 'ai-blog-generator' ); ?>">
							<span class="dashicons dashicons-edit"></span>
						</a>
						<a href="#" class="action-icon toggle-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>"
							title="<?php echo $context->active ? esc_attr__( 'Deactivate', 'ai-blog-generator' ) : esc_attr__( 'Activate', 'ai-blog-generator' ); ?>">
							<span class="dashicons dashicons-<?php echo $context->active ? 'hidden' : 'visibility'; ?>"></span>
						</a>
						<a href="#" class="action-icon delete-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>"
							title="<?php esc_attr_e( 'Delete', 'ai-blog-generator' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</a>
					</div>
					

				</div>
			<?php endforeach; ?>
		</div>
		
		<!-- Seed Images Section -->
		<div class="ai-blog-seed-images-section">
			<h2><?php esc_html_e( 'Seed Images', 'ai-blog-generator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload PNG seed images for consistent product representation in generated images. Each image requires a product name.', 'ai-blog-generator' ); ?>
			</p>
			
			<button type="button" class="button button-primary" id="upload-seed-image">
				<?php esc_html_e( 'Upload New Seed Image', 'ai-blog-generator' ); ?>
			</button>
			
			<div class="ai-blog-seed-images-grid" id="seed-images-grid">
				<?php 
				// Get seed images using Database_Manager for security
				use AI_Blog_Generator\Models\Database_Manager;
				
				$db_manager = Database_Manager::get_instance();
				$seed_images_data = $db_manager->get_all( 'seed_images', [], 'created_at DESC' );
				
				foreach ( $seed_images_data as $seed_image ) :
					// Get WordPress attachment details
					$attachment_id = attachment_url_to_postid( $seed_image->image_url );
					$image_html = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail' ) : '<img src="' . esc_url( $seed_image->image_url ) . '" style="max-width: 150px; height: auto;">';
				?>
					<div class="seed-image-item" data-seed-id="<?php echo esc_attr( $seed_image->id ); ?>">
						<div class="seed-image-preview">
							<?php echo $image_html; ?>
						</div>
						<div class="seed-image-info">
							<strong class="product-name"><?php echo esc_html( $seed_image->product_name ); ?></strong>
							<p class="image-url"><?php echo esc_html( basename( $seed_image->image_url ) ); ?></p>
							<?php if ( $seed_image->context_id ) : ?>
								<p class="context-link">
									<small>Linked to Context ID: <?php echo esc_html( $seed_image->context_id ); ?></small>
								</p>
							<?php endif; ?>
						</div>
						<div class="seed-image-actions">
							<a href="#" class="action-icon edit-seed-image" 
								data-seed-id="<?php echo esc_attr( $seed_image->id ); ?>"
								title="<?php esc_attr_e( 'Edit', 'ai-blog-generator' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</a>
							<a href="#" class="action-icon delete-seed-image" 
								data-seed-id="<?php echo esc_attr( $seed_image->id ); ?>"
								title="<?php esc_attr_e( 'Delete', 'ai-blog-generator' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
				
				<?php if ( empty( $seed_images_data ) ) : ?>
					<div class="no-seed-images">
						<p><?php esc_html_e( 'No seed images uploaded yet. Click "Upload New Seed Image" to get started.', 'ai-blog-generator' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
</div>

<!-- Context Edit Modal -->
<div id="context-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content large">
		<div class="ai-blog-modal-header">
			<h2 id="modal-title" class="ai-blog-modal-title"><?php esc_html_e( 'Edit Context', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-blog-generator' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		
		<form id="context-form">
			<input type="hidden" id="context-id" name="context_id" value="">
			
			<div class="ai-blog-modal-body">
				<div class="ai-blog-form-group">
					<label for="context-name"><?php esc_html_e( 'Name', 'ai-blog-generator' ); ?></label>
					<input type="text" id="context-name" name="name" class="ai-blog-form-control" required>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="context-type"><?php esc_html_e( 'Type', 'ai-blog-generator' ); ?></label>
					<select id="context-type" name="type" class="ai-blog-form-control" required>
						<?php foreach ( $context_types as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="context-usage"><?php esc_html_e( 'Usage Category', 'ai-blog-generator' ); ?></label>
					<select id="context-usage" name="usage_flags" class="ai-blog-form-control" required>
						<option value="ideas"><?php esc_html_e( 'Idea Generation', 'ai-blog-generator' ); ?></option>
						<option value="content"><?php esc_html_e( 'Content Generation', 'ai-blog-generator' ); ?></option>
						<option value="images"><?php esc_html_e( 'Image Generation', 'ai-blog-generator' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select which generation process this context will be used for. Contexts are specific to their usage category.', 'ai-blog-generator' ); ?>
					</p>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="context-content"><?php esc_html_e( 'Content', 'ai-blog-generator' ); ?></label>
					<textarea id="context-content" name="content" rows="25" class="ai-blog-form-control ai-blog-code-editor" 
						style="font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.4; white-space: pre; overflow-wrap: normal; tab-size: 4; -moz-tab-size: 4;" 
						spellcheck="false" autocorrect="off" autocapitalize="off" wrap="off" required></textarea>
					<p class="description">
						<?php esc_html_e( 'Enter the context information that will guide AI content generation. Supports HTML, Avada shortcodes, and JavaScript code. All content will be preserved exactly as entered.', 'ai-blog-generator' ); ?>
					</p>
				</div>

				<div class="ai-blog-form-group">
					<label class="ai-blog-checkbox-label">
						<input type="checkbox" id="context-always-content" name="always_include_content" value="1">
						<?php esc_html_e( 'Always include this context in content generation', 'ai-blog-generator' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When checked, this context will be included in all content generation, regardless of persona selection.', 'ai-blog-generator' ); ?>
					</p>
				</div>
				
				<div class="ai-blog-form-group">
					<label class="ai-blog-checkbox-label">
						<input type="checkbox" id="context-always-images" name="always_include_images" value="1">
						<?php esc_html_e( 'Always include this context in image generation', 'ai-blog-generator' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When checked, this context will be included in all image generation, regardless of persona selection.', 'ai-blog-generator' ); ?>
					</p>
				</div>

				<div class="ai-blog-form-group">
					<label class="ai-blog-checkbox-label">
						<input type="checkbox" id="context-always-avada" name="always_include_avada" value="1">
						<?php esc_html_e( 'Always include with Avada Builder Layouts', 'ai-blog-generator' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When checked, this context will be automatically included when generating content with Avada layouts.', 'ai-blog-generator' ); ?>
					</p>
				</div>
				
				<div class="ai-blog-form-group">
					<label class="ai-blog-checkbox-label">
						<input type="checkbox" id="context-always-html" name="always_include_html" value="1">
						<?php esc_html_e( 'Always include with HTML Layouts', 'ai-blog-generator' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When checked, this context will be automatically included when generating content with HTML format.', 'ai-blog-generator' ); ?>
					</p>
				</div>
			</div>
			
			<div class="ai-blog-modal-footer">
				<button type="button" class="button cancel-edit">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Context', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<!-- Seed Image Upload Modal -->
<div id="seed-image-upload-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2 id="seed-modal-title" class="ai-blog-modal-title"><?php esc_html_e( 'Upload Seed Image', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-blog-generator' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		
		<form id="seed-image-form" enctype="multipart/form-data">
			<input type="hidden" id="seed-image-id" name="seed_id" value="">
			
			<div class="ai-blog-modal-body">
				<div class="ai-blog-form-group">
					<label for="seed-product-name"><?php esc_html_e( 'Product Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="text" id="seed-product-name" name="product_name" class="ai-blog-form-control" required>
					<p class="description">
						<?php esc_html_e( 'Enter a descriptive name for this product/item.', 'ai-blog-generator' ); ?>
					</p>
				</div>
				
				<div class="ai-blog-form-group" id="seed-image-upload-row">
					<label for="seed-image-file"><?php esc_html_e( 'Image File', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="file" id="seed-image-file" name="image_file" accept=".png,image/png" required>
					<p class="description">
						<?php esc_html_e( 'Only PNG files are allowed. Maximum file size: 10MB.', 'ai-blog-generator' ); ?>
					</p>
					<div id="image-preview" style="margin-top: 10px; display: none;">
						<img id="preview-image" src="" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
					</div>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="seed-context-link"><?php esc_html_e( 'Link to Context', 'ai-blog-generator' ); ?></label>
					<select id="seed-context-link" name="context_id" class="ai-blog-form-control">
						<option value=""><?php esc_html_e( 'No specific context', 'ai-blog-generator' ); ?></option>
						<?php foreach ( $contexts as $context ) : ?>
							<option value="<?php echo esc_attr( $context->id ); ?>">
								<?php echo esc_html( $context->name ); ?> (<?php echo esc_html( ucfirst( $context->type ) ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Optionally link this seed image to a specific context.', 'ai-blog-generator' ); ?>
					</p>
				</div>
			</div>
			
			<div class="ai-blog-modal-footer">
				<button type="button" class="button cancel-seed-upload">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary" id="save-seed-image">
					<?php esc_html_e( 'Upload Seed Image', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

 
 