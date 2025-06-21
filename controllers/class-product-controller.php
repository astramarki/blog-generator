<?php
/**
 * Product Controller Class
 *
 * @package AI_Blog_Generator
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Product_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Controller Class
 *
 * Handles product management AJAX requests.
 */
class Product_Controller {
	use Ajax_Handler;

	/**
	 * Product model instance.
	 *
	 * @var Product_Model
	 */
	private $product_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->product_model = new Product_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Product CRUD operations
		add_action( 'wp_ajax_ai_blog_create_product', [ $this, 'create_product' ] );
		add_action( 'wp_ajax_ai_blog_update_product', [ $this, 'update_product' ] );
		add_action( 'wp_ajax_ai_blog_delete_product', [ $this, 'delete_product' ] );
		add_action( 'wp_ajax_ai_blog_get_product', [ $this, 'get_product' ] );
		add_action( 'wp_ajax_ai_blog_list_products', [ $this, 'list_products' ] );
		
		// Image management
		add_action( 'wp_ajax_ai_blog_add_product_image', [ $this, 'add_product_image' ] );
		add_action( 'wp_ajax_ai_blog_remove_product_image', [ $this, 'remove_product_image' ] );
		add_action( 'wp_ajax_ai_blog_update_image_order', [ $this, 'update_image_order' ] );
		add_action( 'wp_ajax_ai_blog_set_primary_image', [ $this, 'set_primary_image' ] );
		
		// Link management
		add_action( 'wp_ajax_ai_blog_add_product_link', [ $this, 'add_product_link' ] );
		add_action( 'wp_ajax_ai_blog_remove_product_link', [ $this, 'remove_product_link' ] );
		add_action( 'wp_ajax_ai_blog_update_link_order', [ $this, 'update_link_order' ] );
		
		// Seed image management
		add_action( 'wp_ajax_ai_blog_add_product_seed_image', [ $this, 'add_product_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_remove_product_seed_image', [ $this, 'remove_product_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_update_seed_image_order', [ $this, 'update_seed_image_order' ] );
		
		// WooCommerce import
		add_action( 'wp_ajax_ai_blog_get_woocommerce_products', [ $this, 'get_woocommerce_products' ] );
		add_action( 'wp_ajax_ai_blog_import_woocommerce_product', [ $this, 'import_woocommerce_product' ] );
		
		Logger::info( 'product_ajax_handlers_registered', 'Product AJAX handlers registered' );
	}

	/**
	 * Create a new product.
	 */
	public function create_product() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		// Get and validate data
		$product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( $_POST['product_name'] ) : '';
		$product_description = isset( $_POST['product_description'] ) ? wp_kses_post( $_POST['product_description'] ) : '';
		$ideal_uses = isset( $_POST['ideal_uses'] ) ? wp_kses_post( $_POST['ideal_uses'] ) : '';
		
		if ( empty( $product_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Product name is required.', 'ai-blog-generator' ) ] );
		}
		
		// Create product
		$product_data = [
			'product_name' => $product_name,
			'product_description' => $product_description,
			'ideal_uses' => $ideal_uses,
		];
		
		Logger::info( 'product_create_attempt', 'Attempting to create product', [ 'data' => $product_data ] );
		
		$product_id = $this->product_model->create( $product_data );
		
		if ( ! $product_id ) {
			Logger::error( 'product_create_failed', 'Failed to create product', [ 
				'data' => $product_data,
				'last_error' => error_get_last()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to create product. Check logs for details.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_created', 'Product created successfully', [ 'product_id' => $product_id ] );
		
		// Get the created product with relations
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Product created successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Update an existing product.
	 */
	public function update_product() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'ai-blog-generator' ) ] );
		}
		
		// Prepare update data
		$update_data = [];
		
		if ( isset( $_POST['product_name'] ) ) {
			$update_data['product_name'] = sanitize_text_field( $_POST['product_name'] );
		}
		
		if ( isset( $_POST['product_description'] ) ) {
			$update_data['product_description'] = wp_kses_post( $_POST['product_description'] );
		}
		
		if ( isset( $_POST['ideal_uses'] ) ) {
			$update_data['ideal_uses'] = wp_kses_post( $_POST['ideal_uses'] );
		}
		
		if ( empty( $update_data ) ) {
			wp_send_json_error( [ 'message' => __( 'No data to update.', 'ai-blog-generator' ) ] );
		}
		
		// Update product
		$result = $this->product_model->update( $product_id, $update_data );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update product.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_updated', 'Product updated successfully', [ 'product_id' => $product_id ] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Product updated successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Delete a product.
	 */
	public function delete_product() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'ai-blog-generator' ) ] );
		}
		
		// Delete product (images and links will be cascade deleted)
		$result = $this->product_model->delete( $product_id );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete product.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_deleted', 'Product deleted successfully', [ 'product_id' => $product_id ] );
		
		wp_send_json_success( [
			'message' => __( 'Product deleted successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Get a single product.
	 */
	public function get_product() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'ai-blog-generator' ) ] );
		}
		
		$product = $this->product_model->get_with_relations( $product_id );
		
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'ai-blog-generator' ) ] );
		}
		
		wp_send_json_success( [ 'product' => $product ] );
	}

	/**
	 * List all products.
	 */
	public function list_products() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		// Get pagination parameters
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		
		// Build query args
		$args = [
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'orderby' => 'product_name',
			'order' => 'ASC',
		];
		
		if ( $search ) {
			$args['search'] = $search;
			$args['search_columns'] = [ 'product_name', 'product_description' ];
		}
		
		// Get products
		$products = $this->product_model->get_all_with_relations( $args );
		$total = $this->product_model->count( $search ? [ 'search' => $search ] : [] );
		
		wp_send_json_success( [
			'products' => $products,
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );
	}

	/**
	 * Add image to product.
	 */
	public function add_product_image() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$is_primary = isset( $_POST['is_primary'] ) ? (bool) $_POST['is_primary'] : false;
		
		if ( ! $product_id || ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product or attachment ID.', 'ai-blog-generator' ) ] );
		}
		
		// Verify attachment exists
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid image attachment.', 'ai-blog-generator' ) ] );
		}
		
		// Get current image count for display order
		$current_images = $this->product_model->get_product_images( $product_id );
		$display_order = count( $current_images );
		
		// Add image
		$image_id = $this->product_model->add_image( $product_id, $attachment_id, $is_primary, $display_order );
		
		if ( ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to add image.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_image_added', 'Image added to product', [
			'product_id' => $product_id,
			'attachment_id' => $attachment_id,
			'is_primary' => $is_primary,
		] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Image added successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Remove image from product.
	 */
	public function remove_product_image() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		
		if ( ! $product_id || ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product or attachment ID.', 'ai-blog-generator' ) ] );
		}
		
		// Remove image
		$result = $this->product_model->remove_image( $product_id, $attachment_id );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to remove image.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_image_removed', 'Image removed from product', [
			'product_id' => $product_id,
			'attachment_id' => $attachment_id,
		] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Image removed successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Update image order.
	 */
	public function update_image_order() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$image_order = isset( $_POST['image_order'] ) ? array_map( 'absint', $_POST['image_order'] ) : [];
		
		if ( ! $product_id || empty( $image_order ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID or image order.', 'ai-blog-generator' ) ] );
		}
		
		// Update order
		$result = $this->product_model->update_image_order( $product_id, $image_order );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update image order.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_image_order_updated', 'Image order updated', [
			'product_id' => $product_id,
			'new_order' => $image_order,
		] );
		
		wp_send_json_success( [
			'message' => __( 'Image order updated successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Set primary image.
	 */
	public function set_primary_image() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		
		if ( ! $product_id || ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product or attachment ID.', 'ai-blog-generator' ) ] );
		}
		
		global $wpdb;
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES;
		
		// Unset all primary images for this product
		$wpdb->update(
			$table_name,
			[ 'is_primary' => 0 ],
			[ 'product_id' => $product_id ],
			[ '%d' ],
			[ '%d' ]
		);
		
		// Set new primary image
		$result = $wpdb->update(
			$table_name,
			[ 'is_primary' => 1 ],
			[
				'product_id' => $product_id,
				'attachment_id' => $attachment_id,
			],
			[ '%d' ],
			[ '%d', '%d' ]
		);
		
		if ( $result === false ) {
			wp_send_json_error( [ 'message' => __( 'Failed to set primary image.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_primary_image_set', 'Primary image set', [
			'product_id' => $product_id,
			'attachment_id' => $attachment_id,
		] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Primary image set successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Add link to product.
	 */
	public function add_product_link() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$link_url = isset( $_POST['link_url'] ) ? esc_url_raw( $_POST['link_url'] ) : '';
		$link_text = isset( $_POST['link_text'] ) ? sanitize_text_field( $_POST['link_text'] ) : '';
		$link_type = isset( $_POST['link_type'] ) ? sanitize_text_field( $_POST['link_type'] ) : 'product_page';
		
		if ( ! $product_id || ! $link_url ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID or link URL.', 'ai-blog-generator' ) ] );
		}
		
		// Validate link type
		$valid_types = [ 'product_page', 'purchase', 'documentation', 'other' ];
		if ( ! in_array( $link_type, $valid_types, true ) ) {
			$link_type = 'product_page';
		}
		
		// Get current link count for display order
		$current_links = $this->product_model->get_product_links( $product_id );
		$display_order = count( $current_links );
		
		// Add link
		$link_id = $this->product_model->add_link( $product_id, $link_url, $link_text, $link_type, $display_order );
		
		if ( ! $link_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to add link.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_link_added', 'Link added to product', [
			'product_id' => $product_id,
			'link_url' => $link_url,
			'link_type' => $link_type,
		] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Link added successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Remove link from product.
	 */
	public function remove_product_link() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		
		if ( ! $link_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid link ID.', 'ai-blog-generator' ) ] );
		}
		
		// Get product ID before removing
		global $wpdb;
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS;
		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT product_id FROM {$table_name} WHERE id = %d",
			$link_id
		) );
		
		// Remove link
		$result = $this->product_model->remove_link( $link_id );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to remove link.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_link_removed', 'Link removed from product', [
			'link_id' => $link_id,
			'product_id' => $product_id,
		] );
		
		// Get updated product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Link removed successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Update link order.
	 */
	public function update_link_order() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$link_order = isset( $_POST['link_order'] ) ? array_map( 'absint', $_POST['link_order'] ) : [];
		
		if ( ! $product_id || empty( $link_order ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID or link order.', 'ai-blog-generator' ) ] );
		}
		
		// Update order
		$result = $this->product_model->update_link_order( $product_id, $link_order );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update link order.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_link_order_updated', 'Link order updated', [
			'product_id' => $product_id,
			'new_order' => $link_order,
		] );
		
		wp_send_json_success( [
			'message' => __( 'Link order updated successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Get available WooCommerce products for import.
	 */
	public function get_woocommerce_products() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'WooCommerce is not active.', 'ai-blog-generator' ) ] );
		}
		
		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		
		$args = [
			'limit' => $per_page,
			'page' => $page,
		];
		
		if ( $search ) {
			$args['s'] = $search;
		}
		
		$products = $this->product_model->get_woocommerce_products( $args );
		
		wp_send_json_success( [
			'products' => $products,
			'has_more' => count( $products ) === $per_page,
		] );
	}

	/**
	 * Import product from WooCommerce.
	 */
	public function import_woocommerce_product() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$wc_product_id = isset( $_POST['wc_product_id'] ) ? absint( $_POST['wc_product_id'] ) : 0;
		
		if ( ! $wc_product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid WooCommerce product ID.', 'ai-blog-generator' ) ] );
		}
		
		// Import product
		$product_id = $this->product_model->import_from_woocommerce( $wc_product_id );
		
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to import product.', 'ai-blog-generator' ) ] );
		}
		
		// Get imported product
		$product = $this->product_model->get_with_relations( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Product imported successfully.', 'ai-blog-generator' ),
			'product' => $product,
		] );
	}

	/**
	 * Add seed image to product.
	 */
	public function add_product_seed_image() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		
		if ( ! $product_id || ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product or attachment ID.', 'ai-blog-generator' ) ] );
		}
		
		// Verify attachment exists and is PNG
		$mime_type = get_post_mime_type( $attachment_id );
		if ( $mime_type !== 'image/png' ) {
			wp_send_json_error( [ 'message' => __( 'Seed images must be PNG files.', 'ai-blog-generator' ) ] );
		}
		
		// Get current seed images for display order
		$current_images = $this->product_model->get_product_seed_images( $product_id );
		$display_order = count( $current_images );
		
		// Add seed image
		$image_id = $this->product_model->add_seed_image( $product_id, $attachment_id, $display_order );
		
		if ( ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to add seed image.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_seed_image_added', 'Seed image added to product', [
			'product_id' => $product_id,
			'attachment_id' => $attachment_id,
		] );
		
		// Get updated seed images
		$seed_images = $this->product_model->get_product_seed_images( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Seed image added successfully.', 'ai-blog-generator' ),
			'seed_images' => $seed_images,
		] );
	}

	/**
	 * Remove seed image from product.
	 */
	public function remove_product_seed_image() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		
		if ( ! $product_id || ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product or attachment ID.', 'ai-blog-generator' ) ] );
		}
		
		// Remove seed image
		$result = $this->product_model->remove_seed_image( $product_id, $attachment_id );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to remove seed image.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_seed_image_removed', 'Seed image removed from product', [
			'product_id' => $product_id,
			'attachment_id' => $attachment_id,
		] );
		
		// Get updated seed images
		$seed_images = $this->product_model->get_product_seed_images( $product_id );
		
		wp_send_json_success( [
			'message' => __( 'Seed image removed successfully.', 'ai-blog-generator' ),
			'seed_images' => $seed_images,
		] );
	}

	/**
	 * Update seed image order.
	 */
	public function update_seed_image_order() {
		if ( ! $this->verify_ajax_security() ) {
			return;
		}
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$image_order = isset( $_POST['image_order'] ) ? array_map( 'absint', $_POST['image_order'] ) : [];
		
		if ( ! $product_id || empty( $image_order ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID or image order.', 'ai-blog-generator' ) ] );
		}
		
		// Update order
		$result = $this->product_model->update_seed_image_order( $product_id, $image_order );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update seed image order.', 'ai-blog-generator' ) ] );
		}
		
		Logger::info( 'product_seed_image_order_updated', 'Seed image order updated', [
			'product_id' => $product_id,
			'new_order' => $image_order,
		] );
		
		wp_send_json_success( [
			'message' => __( 'Seed image order updated successfully.', 'ai-blog-generator' ),
		] );
	}
} 