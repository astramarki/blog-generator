<?php
/**
 * Brand Features Admin Page
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AI_Blog_Generator\Controllers\Brand_Feature_Controller;

// Get category options
$category_options = Brand_Feature_Controller::get_category_options();
?>

<div class="wrap ai-blog-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Brand Features', 'ai-blog-generator' ); ?></h1>
	<button type="button" class="page-title-action" id="add-new-brand-feature">
		<?php esc_html_e( 'Add New Feature', 'ai-blog-generator' ); ?>
	</button>
	
	<hr class="wp-header-end">
	
	<div class="ai-blog-admin-notice" id="brand-feature-notice" style="display: none;">
		<p></p>
	</div>
	
	<!-- Search and Filter Bar -->
	<div class="ai-blog-filter-bar">
		<div class="ai-blog-search-box">
			<input type="search" id="brand-feature-search" placeholder="<?php esc_attr_e( 'Search brand features...', 'ai-blog-generator' ); ?>" />
		</div>
		
		<div class="ai-blog-filter-select">
			<select id="brand-feature-category-filter">
				<option value=""><?php esc_html_e( 'All Categories', 'ai-blog-generator' ); ?></option>
				<?php foreach ( $category_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	
	<!-- Brand Features Grid -->
	<div class="ai-blog-brand-features-grid" id="brand-features-grid">
		<div class="ai-blog-loading">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Loading brand features...', 'ai-blog-generator' ); ?></span>
		</div>
	</div>
	
	<!-- Pagination -->
	<div class="ai-blog-pagination" id="brand-features-pagination"></div>
</div>

<!-- Brand Feature Modal -->
<div class="ai-blog-modal" id="brand-feature-modal">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2 id="brand-feature-modal-title"><?php esc_html_e( 'Add New Brand Feature', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close modal', 'ai-blog-generator' ); ?>">
				<span class="dashicons dashicons-no"></span>
			</button>
		</div>
		
		<form id="brand-feature-form">
			<input type="hidden" id="brand-feature-id" name="feature_id" value="" />
			
			<div class="ai-blog-modal-body">
				<div class="ai-blog-form-group">
					<label for="brand-feature-name"><?php esc_html_e( 'Feature Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="text" id="brand-feature-name" name="name" class="ai-blog-form-control" required />
					<p class="description"><?php esc_html_e( 'The name of the brand feature or service.', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="brand-feature-description"><?php esc_html_e( 'Description', 'ai-blog-generator' ); ?></label>
					<textarea id="brand-feature-description" name="description" rows="4" class="ai-blog-form-control"></textarea>
					<p class="description"><?php esc_html_e( 'A brief description of this feature or service.', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="brand-feature-category"><?php esc_html_e( 'Category', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<select id="brand-feature-category" name="category" class="ai-blog-form-control" required>
						<?php foreach ( $category_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The type of content this feature links to.', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="ai-blog-form-group">
					<label for="brand-feature-url"><?php esc_html_e( 'URL', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="url" id="brand-feature-url" name="url" class="ai-blog-form-control" required />
					<p class="description"><?php esc_html_e( 'The full URL to the feature page or resource.', 'ai-blog-generator' ); ?></p>
				</div>
				
				<div class="ai-blog-form-group">
					<label class="ai-blog-checkbox-label">
						<input type="checkbox" id="brand-feature-active" name="active" value="1" checked />
						<?php esc_html_e( 'Active', 'ai-blog-generator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Only active features will be used for internal linking in generated content.', 'ai-blog-generator' ); ?></p>
				</div>
			</div>
			
			<div class="ai-blog-modal-footer">
				<button type="button" class="button button-secondary" id="cancel-brand-feature">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary" id="save-brand-feature">
					<?php esc_html_e( 'Save Feature', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
</div> 