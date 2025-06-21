<?php
/**
 * Products Management View
 *
 * @package AI_Blog_Generator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ai-blog-generator-products">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'ai-blog-generator' ); ?></h1>
	<a href="#" class="page-title-action" id="add-new-product"><?php esc_html_e( 'Add New Product', 'ai-blog-generator' ); ?></a>
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
		<a href="#" class="page-title-action" id="import-from-woocommerce"><?php esc_html_e( 'Import from WooCommerce', 'ai-blog-generator' ); ?></a>
	<?php endif; ?>
	
	<hr class="wp-header-end">
	
	<!-- Search Box -->
	<div class="ai-blog-search-box">
		<input type="search" id="product-search" placeholder="<?php esc_attr_e( 'Search products...', 'ai-blog-generator' ); ?>" />
	</div>
	
	<!-- Products Grid -->
	<div class="ai-blog-products-grid" id="products-grid">
		<!-- Products will be loaded here via AJAX -->
	</div>
	
	<!-- Pagination -->
	<div class="ai-blog-pagination" id="products-pagination">
		<!-- Pagination will be loaded here via AJAX -->
	</div>
</div>

<!-- Product Modal -->
<div id="product-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2 id="product-modal-title"><?php esc_html_e( 'Add New Product', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-blog-generator' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		
		<form id="product-form">
			<input type="hidden" id="product-id" name="product_id" value="" />
			
			<div class="ai-blog-modal-body">
				<!-- Basic Information -->
				<div class="form-section">
					<h3><?php esc_html_e( 'Basic Information', 'ai-blog-generator' ); ?></h3>
					
					<div class="form-group">
						<label for="product-name"><?php esc_html_e( 'Product Name', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
						<input type="text" id="product-name" name="product_name" required />
					</div>
					
					<div class="form-group">
						<label for="product-description"><?php esc_html_e( 'Product Description', 'ai-blog-generator' ); ?></label>
						<textarea id="product-description" name="product_description" rows="5"></textarea>
					</div>
					
					<div class="form-group">
						<label for="product-ideal-uses"><?php esc_html_e( 'Ideal Uses', 'ai-blog-generator' ); ?></label>
						<textarea id="product-ideal-uses" name="ideal_uses" rows="4"></textarea>
					</div>
				</div>
				
				<!-- Images Section -->
				<div class="form-section">
					<h3><?php esc_html_e( 'Product Images', 'ai-blog-generator' ); ?></h3>
					
					<div class="product-images-container" id="product-images-container">
						<!-- Images will be loaded here -->
					</div>
					
					<button type="button" class="button" id="add-product-image">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Add Image', 'ai-blog-generator' ); ?>
					</button>
				</div>
				
				<!-- Links Section -->
				<div class="form-section">
					<h3><?php esc_html_e( 'Product Links', 'ai-blog-generator' ); ?></h3>
					
					<div class="product-links-container" id="product-links-container">
						<!-- Links will be loaded here -->
					</div>
					
					<button type="button" class="button" id="add-product-link">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Add Link', 'ai-blog-generator' ); ?>
					</button>
				</div>
			</div>
			
			<div class="ai-blog-modal-footer">
				<button type="button" class="button button-secondary" id="cancel-product">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary" id="save-product">
					<?php esc_html_e( 'Save Product', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<!-- WooCommerce Import Modal -->
<?php if ( class_exists( 'WooCommerce' ) ) : ?>
<div id="woocommerce-import-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2><?php esc_html_e( 'Import from WooCommerce', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-blog-generator' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		
		<div class="ai-blog-modal-body">
			<!-- Search WooCommerce Products -->
			<div class="form-group">
				<label for="wc-product-search"><?php esc_html_e( 'Search WooCommerce Products', 'ai-blog-generator' ); ?></label>
				<input type="search" id="wc-product-search" placeholder="<?php esc_attr_e( 'Type to search...', 'ai-blog-generator' ); ?>" />
			</div>
			
			<!-- WooCommerce Products List -->
			<div class="wc-products-list" id="wc-products-list">
				<!-- Products will be loaded here -->
			</div>
		</div>
		
		<div class="ai-blog-modal-footer">
			<button type="button" class="button button-secondary" id="cancel-wc-import">
				<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
			</button>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- Link Modal -->
<div id="link-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content small">
		<div class="ai-blog-modal-header">
			<h2><?php esc_html_e( 'Add Product Link', 'ai-blog-generator' ); ?></h2>
			<button type="button" class="ai-blog-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-blog-generator' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		
		<form id="link-form">
			<div class="ai-blog-modal-body">
				<div class="form-group">
					<label for="link-url"><?php esc_html_e( 'URL', 'ai-blog-generator' ); ?> <span class="required">*</span></label>
					<input type="url" id="link-url" name="link_url" required />
				</div>
				
				<div class="form-group">
					<label for="link-text"><?php esc_html_e( 'Link Text', 'ai-blog-generator' ); ?></label>
					<input type="text" id="link-text" name="link_text" placeholder="<?php esc_attr_e( 'e.g., View Product', 'ai-blog-generator' ); ?>" />
				</div>
				
				<div class="form-group">
					<label for="link-type"><?php esc_html_e( 'Link Type', 'ai-blog-generator' ); ?></label>
					<select id="link-type" name="link_type">
						<option value="product_page"><?php esc_html_e( 'Product Page', 'ai-blog-generator' ); ?></option>
						<option value="purchase"><?php esc_html_e( 'Purchase Link', 'ai-blog-generator' ); ?></option>
						<option value="documentation"><?php esc_html_e( 'Documentation', 'ai-blog-generator' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'ai-blog-generator' ); ?></option>
					</select>
				</div>
			</div>
			
			<div class="ai-blog-modal-footer">
				<button type="button" class="button button-secondary" id="cancel-link">
					<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary" id="save-link">
					<?php esc_html_e( 'Add Link', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
</div> 