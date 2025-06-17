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
	
	<div class="ai-blog-admin-content">
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
						<span class="context-type badge badge-<?php echo esc_attr( $context->type ); ?>">
							<?php echo esc_html( $context_types[ $context->type ] ?? $context->type ); ?>
						</span>
					</div>
					
					<div class="context-content">
						<p><?php echo esc_html( wp_trim_words( $context->content, 30 ) ); ?></p>
					</div>
					
					<div class="context-actions">
						<button type="button" class="button edit-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>">
							<?php esc_html_e( 'Edit', 'ai-blog-generator' ); ?>
						</button>
						<button type="button" class="button toggle-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>">
							<?php echo $context->active ? esc_html__( 'Deactivate', 'ai-blog-generator' ) : esc_html__( 'Activate', 'ai-blog-generator' ); ?>
						</button>
						<button type="button" class="button delete-context" 
							data-context-id="<?php echo esc_attr( $context->id ); ?>">
							<?php esc_html_e( 'Delete', 'ai-blog-generator' ); ?>
						</button>
					</div>
					
					<?php if ( $context->type === 'products' && ! empty( $context->seed_image_id ) ) : ?>
						<div class="context-seed-image">
							<?php echo wp_get_attachment_image( $context->seed_image_id, 'thumbnail' ); ?>
						</div>
					<?php endif; ?>
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
							<button type="button" class="button button-small edit-seed-image" 
								data-seed-id="<?php echo esc_attr( $seed_image->id ); ?>">
								<?php esc_html_e( 'Edit', 'ai-blog-generator' ); ?>
							</button>
							<button type="button" class="button button-small delete-seed-image" 
								data-seed-id="<?php echo esc_attr( $seed_image->id ); ?>">
								<?php esc_html_e( 'Delete', 'ai-blog-generator' ); ?>
							</button>
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
</div>

<!-- Context Edit Modal -->
<div id="context-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content large">
		<span class="ai-blog-modal-close">&times;</span>
		<h2 id="modal-title"><?php esc_html_e( 'Edit Context', 'ai-blog-generator' ); ?></h2>
		
		<form id="context-form">
			<input type="hidden" id="context-id" name="context_id" value="">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="context-name"><?php esc_html_e( 'Name', 'ai-blog-generator' ); ?></label>
					</th>
					<td>
						<input type="text" id="context-name" name="name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="context-type"><?php esc_html_e( 'Type', 'ai-blog-generator' ); ?></label>
					</th>
					<td>
						<select id="context-type" name="type" required>
							<?php foreach ( $context_types as $type => $label ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="context-content"><?php esc_html_e( 'Content', 'ai-blog-generator' ); ?></label>
					</th>
					<td>
						<textarea id="context-content" name="content" rows="10" class="large-text code" required></textarea>
						<p class="description">
							<?php esc_html_e( 'Enter the context information that will guide AI content generation.', 'ai-blog-generator' ); ?>
						</p>
					</td>
				</tr>
				<tr id="seed-image-row" style="display: none;">
					<th scope="row">
						<label for="context-seed-image"><?php esc_html_e( 'Seed Image', 'ai-blog-generator' ); ?></label>
					</th>
					<td>
						<select id="context-seed-image" name="seed_image_id">
							<option value=""><?php esc_html_e( 'No seed image', 'ai-blog-generator' ); ?></option>
							<?php foreach ( $seed_images as $image ) : ?>
								<option value="<?php echo esc_attr( $image->ID ); ?>">
									<?php echo esc_html( $image->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Context', 'ai-blog-generator' ); ?>
				</button>
				<button type="button" class="button cancel-edit">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<!-- Seed Image Upload Modal -->
<div id="seed-image-upload-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<span class="ai-blog-modal-close">&times;</span>
		<h2 id="seed-modal-title"><?php esc_html_e( 'Upload Seed Image', 'ai-blog-generator' ); ?></h2>
		
		<form id="seed-image-form" enctype="multipart/form-data">
			<input type="hidden" id="seed-image-id" name="seed_id" value="">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="seed-product-name"><?php esc_html_e( 'Product Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" id="seed-product-name" name="product_name" class="regular-text" required>
						<p class="description">
							<?php esc_html_e( 'Enter a descriptive name for this product/item.', 'ai-blog-generator' ); ?>
						</p>
					</td>
				</tr>
				<tr id="seed-image-upload-row">
					<th scope="row">
						<label for="seed-image-file"><?php esc_html_e( 'Image File', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="file" id="seed-image-file" name="image_file" accept=".png,image/png" required>
						<p class="description">
							<?php esc_html_e( 'Only PNG files are allowed. Maximum file size: 10MB.', 'ai-blog-generator' ); ?>
						</p>
						<div id="image-preview" style="margin-top: 10px; display: none;">
							<img id="preview-image" src="" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="seed-context-link"><?php esc_html_e( 'Link to Context', 'ai-blog-generator' ); ?></label>
					</th>
					<td>
						<select id="seed-context-link" name="context_id">
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
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<button type="submit" class="button button-primary" id="save-seed-image">
					<?php esc_html_e( 'Upload Seed Image', 'ai-blog-generator' ); ?>
				</button>
				<button type="button" class="button cancel-seed-upload">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
.ai-blog-contexts-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin: 20px 0;
}
.ai-blog-context-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	position: relative;
}
.ai-blog-context-card.inactive {
	opacity: 0.6;
}
.context-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
}
.context-header h3 {
	margin: 0;
	font-size: 16px;
}
.context-content {
	margin-bottom: 15px;
	min-height: 60px;
}
.context-content p {
	margin: 0;
	color: #666;
}
.context-actions {
	display: flex;
	gap: 5px;
}
.context-actions .button {
	font-size: 12px;
	padding: 4px 8px;
	height: auto;
}
.badge {
	display: inline-block;
	padding: 3px 8px;
	font-size: 11px;
	font-weight: 600;
	line-height: 1;
	color: #fff;
	text-align: center;
	white-space: nowrap;
	vertical-align: baseline;
	border-radius: 3px;
}
.badge-general { background-color: #0073aa; }
.badge-products { background-color: #00a0d2; }
.badge-seo { background-color: #46b450; }
.badge-keywords { background-color: #826eb4; }
.badge-image { background-color: #ffb900; }
.badge-layout { background-color: #e91e63; }
.context-seed-image {
	margin-top: 15px;
	text-align: center;
}
.context-seed-image img {
	max-width: 100px;
	height: auto;
}
.ai-blog-seed-images-section {
	margin-top: 40px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}
.ai-blog-seed-images-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}
.seed-image-item {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 8px;
	padding: 15px;
	text-align: center;
	transition: box-shadow 0.2s ease;
}
.seed-image-item:hover {
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.seed-image-preview {
	margin-bottom: 12px;
}
.seed-image-preview img {
	max-width: 100%;
	height: auto;
	max-height: 150px;
	border-radius: 4px;
	border: 1px solid #e2e4e7;
}
.seed-image-info {
	margin-bottom: 12px;
	text-align: left;
}
.seed-image-info .product-name {
	display: block;
	margin-bottom: 8px;
	font-size: 14px;
	color: #1d2327;
}
.seed-image-info .image-url {
	margin: 0 0 5px 0;
	font-size: 12px;
	color: #666;
	word-break: break-all;
}
.seed-image-info .context-link {
	margin: 0;
	font-size: 11px;
}
.seed-image-info .context-link small {
	color: #0073aa;
}
.seed-image-actions {
	display: flex;
	gap: 8px;
	justify-content: center;
}
.seed-image-actions .button-small {
	padding: 4px 8px;
	font-size: 12px;
	height: auto;
	line-height: 1.2;
}
.no-seed-images {
	grid-column: 1 / -1;
	text-align: center;
	padding: 40px 20px;
	background: #f8f9fa;
	border: 2px dashed #c3c4c7;
	border-radius: 8px;
	color: #646970;
}
.required {
	color: #d63638;
}
#image-preview img {
	display: block;
	margin-top: 10px;
}
.ai-blog-modal {
	position: fixed !important;
	z-index: 100000 !important;
	left: 0 !important;
	top: 0 !important;
	width: 100% !important;
	height: 100% !important;
	background-color: rgba(0,0,0,0.5) !important;
	display: none !important;
	visibility: hidden !important;
}
.ai-blog-modal.ai-blog-modal-show {
	display: block !important;
	visibility: visible !important;
}
.ai-blog-modal-content {
	background-color: #fefefe !important;
	margin: 5% auto !important;
	padding: 20px !important;
	border: 1px solid #888 !important;
	width: 80% !important;
	max-width: 600px !important;
	border-radius: 4px !important;
	max-height: 80vh !important;
	overflow-y: auto !important;
	position: relative !important;
	box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}
.ai-blog-modal-content.large {
	max-width: 800px !important;
}
.ai-blog-modal-close {
	color: #aaa !important;
	float: right !important;
	font-size: 28px !important;
	font-weight: bold !important;
	cursor: pointer !important;
	line-height: 1 !important;
	padding: 0 !important;
	background: none !important;
	border: none !important;
}
.ai-blog-modal-close:hover,
.ai-blog-modal-close:focus {
	color: #000 !important;
}
/* Ensure modal appears above WordPress admin elements */
.ai-blog-modal {
	z-index: 999999 !important;
}
/* Prevent body scroll when modal is open */
body.ai-blog-modal-open {
	overflow: hidden !important;
}
/* Force modal visibility for debugging */
.ai-blog-modal.force-visible {
	display: block !important;
	visibility: visible !important;
	opacity: 1 !important;
}
@media screen and (max-width: 782px) {
	.ai-blog-contexts-grid {
		grid-template-columns: 1fr;
	}
	.context-actions {
		flex-wrap: wrap;
	}
	.context-actions .button {
		flex: 1;
		min-width: 70px;
	}
}
.ai-blog-modal-content .form-table th {
	width: 150px;
}
.ai-blog-modal-content .form-table td {
	padding-left: 10px;
}
</style> 
 