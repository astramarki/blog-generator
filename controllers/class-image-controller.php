<?php
/**
 * Image Controller Class
 *
 * Handles seed image management operations.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Controller Class
 */
class Image_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize if needed
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Note: upload_seed_image is handled by Context_Controller for contexts page
		// This controller handles general seed image management via media library
		add_action( 'wp_ajax_ai_blog_delete_seed_image_media', [ $this, 'delete_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_update_seed_image_media', [ $this, 'update_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_get_seed_images', [ $this, 'get_seed_images' ] );
		
		// Image generation
		add_action( 'wp_ajax_ai_blog_generate_post_images', [ $this, 'generate_post_images' ] );
		add_action( 'wp_ajax_ai_blog_regenerate_image', [ $this, 'regenerate_image' ] );
		
		// Media library integration
		add_action( 'wp_ajax_ai_blog_set_featured_image', [ $this, 'set_featured_image' ] );
		add_action( 'wp_ajax_ai_blog_attach_images_to_post', [ $this, 'attach_images_to_post' ] );
	}

	/**
	 * Upload a seed image.
	 */
	public function upload_seed_image() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Check if file was uploaded
		if ( empty( $_FILES['seed_image'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'ai-blog-generator' ) ] );
		}

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$file_type = $_FILES['seed_image']['type'];
		
		if ( ! in_array( $file_type, $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file type. Please upload an image file.', 'ai-blog-generator' ) ] );
		}

		// Handle file upload
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		$attachment_id = media_handle_upload( 'seed_image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			Logger::error( 'Seed image upload failed', [
				'error' => $attachment_id->get_error_message(),
				'action' => 'upload_seed_image',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Upload failed: %s', 'ai-blog-generator' ), 
					$attachment_id->get_error_message() 
				) 
			] );
		}

		// Mark as seed image
		update_post_meta( $attachment_id, '_ai_blog_seed_image', '1' );

		// Get additional metadata
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		// Update attachment metadata
		if ( $title ) {
			wp_update_post( [
				'ID' => $attachment_id,
				'post_title' => $title,
			] );
		}

		if ( $description ) {
			wp_update_post( [
				'ID' => $attachment_id,
				'post_content' => $description,
			] );
		}

		if ( $category ) {
			update_post_meta( $attachment_id, '_ai_blog_seed_category', $category );
		}

		// Get image details
		$image_url = wp_get_attachment_url( $attachment_id );
		$image_thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

		// Log the action
		Logger::info( 'Seed image uploaded', [
			'attachment_id' => $attachment_id,
			'title' => $title,
			'category' => $category,
			'action' => 'upload_seed_image',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Seed image uploaded successfully.', 'ai-blog-generator' ),
			'image' => [
				'id' => $attachment_id,
				'url' => $image_url,
				'thumbnail' => $image_thumb[0],
				'title' => $title ?: get_the_title( $attachment_id ),
				'description' => $description,
				'category' => $category,
			],
		] );
	}

	/**
	 * Delete a seed image.
	 */
	public function delete_seed_image() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid image ID.', 'ai-blog-generator' ) ] );
		}

		// Check if it's a seed image
		$is_seed_image = get_post_meta( $image_id, '_ai_blog_seed_image', true );
		if ( ! $is_seed_image ) {
			wp_send_json_error( [ 'message' => __( 'Not a seed image.', 'ai-blog-generator' ) ] );
		}

		// Check if image is being used by any context
		global $wpdb;
		$table = $wpdb->prefix . AI_BLOG_GENERATOR_TABLE_CONTEXTS;
		$in_use = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE seed_image_id = %d",
			$image_id
		) );

		if ( $in_use > 0 ) {
			wp_send_json_error( [ 'message' => __( 'Cannot delete seed image that is in use by contexts.', 'ai-blog-generator' ) ] );
		}

		// Delete the attachment
		$deleted = wp_delete_attachment( $image_id, true );
		if ( ! $deleted ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete seed image.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Seed image deleted', [
			'image_id' => $image_id,
			'action' => 'delete_seed_image',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Seed image deleted successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Update seed image metadata.
	 */
	public function update_seed_image() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid image ID.', 'ai-blog-generator' ) ] );
		}

		// Check if it's a seed image
		$is_seed_image = get_post_meta( $image_id, '_ai_blog_seed_image', true );
		if ( ! $is_seed_image ) {
			wp_send_json_error( [ 'message' => __( 'Not a seed image.', 'ai-blog-generator' ) ] );
		}

		// Get update data
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		// Update attachment
		$update_data = [ 'ID' => $image_id ];
		
		if ( $title ) {
			$update_data['post_title'] = $title;
		}
		
		if ( $description !== null ) {
			$update_data['post_content'] = $description;
		}

		$updated = wp_update_post( $update_data );
		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update seed image.', 'ai-blog-generator' ) ] );
		}

		// Update category metadata
		if ( $category !== null ) {
			update_post_meta( $image_id, '_ai_blog_seed_category', $category );
		}

		// Log the action
		Logger::info( 'Seed image updated', [
			'image_id' => $image_id,
			'action' => 'update_seed_image',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Seed image updated successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Get all seed images.
	 */
	public function get_seed_images() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get category filter
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		// Query seed images
		$args = [
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'meta_key' => '_ai_blog_seed_image',
			'meta_value' => '1',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		];

		if ( $category ) {
			$args['meta_query'] = [
				[
					'key' => '_ai_blog_seed_category',
					'value' => $category,
					'compare' => '=',
				],
			];
		}

		$images = get_posts( $args );
		$image_data = [];

		foreach ( $images as $image ) {
			$thumb = wp_get_attachment_image_src( $image->ID, 'thumbnail' );
			$full = wp_get_attachment_image_src( $image->ID, 'full' );
			
			$image_data[] = [
				'id' => $image->ID,
				'title' => $image->post_title,
				'description' => $image->post_content,
				'category' => get_post_meta( $image->ID, '_ai_blog_seed_category', true ),
				'thumbnail' => $thumb[0],
				'url' => $full[0],
				'width' => $full[1],
				'height' => $full[2],
				'uploaded' => $image->post_date,
			];
		}

		wp_send_json_success( [
			'images' => $image_data,
			'total' => count( $image_data ),
		] );
	}

	/**
	 * Generate images for a blog post.
	 */
	public function generate_post_images() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( $_POST['prompt'] ) : '';
		$count = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 1;
		$seed_image_id = isset( $_POST['seed_image_id'] ) ? absint( $_POST['seed_image_id'] ) : 0;

		if ( ! $post_id || empty( $prompt ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid input data.', 'ai-blog-generator' ) ] );
		}

		// Check if image generation is enabled
		if ( ! get_option( 'ai_blog_enable_image_generation', true ) ) {
			wp_send_json_error( [ 'message' => __( 'Image generation is disabled.', 'ai-blog-generator' ) ] );
		}

		// Check budget
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		if ( ! $budget_manager->can_generate() ) {
			wp_send_json_error( [ 
				'message' => __( 'Budget limit reached. Cannot generate images.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Log generation start
		Logger::info( 'Starting image generation', [
			'post_id' => $post_id,
			'count' => $count,
			'has_seed' => $seed_image_id > 0,
			'action' => 'generate_post_images',
		] );

		// Generate images
		$generator = new \AI_Blog_Generator\Services\Image_Generator();
		$result = $generator->generate_images( $prompt, $count, $seed_image_id );

		if ( is_wp_error( $result ) ) {
			Logger::error( 'Image generation failed', [
				'post_id' => $post_id,
				'error' => $result->get_error_message(),
				'action' => 'generate_post_images',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Image generation failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		// Attach images to post
		foreach ( $result as $image_id ) {
			wp_update_post( [
				'ID' => $image_id,
				'post_parent' => $post_id,
			] );
			
			// Add AI generation metadata
			update_post_meta( $image_id, '_ai_blog_generated', '1' );
			update_post_meta( $image_id, '_ai_blog_prompt', $prompt );
			if ( $seed_image_id ) {
				update_post_meta( $image_id, '_ai_blog_seed_source', $seed_image_id );
			}
		}

		// Set first image as featured if none exists
		if ( ! has_post_thumbnail( $post_id ) && ! empty( $result ) ) {
			set_post_thumbnail( $post_id, $result[0] );
		}

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of images generated */
				_n( '%d image generated successfully.', '%d images generated successfully.', count( $result ), 'ai-blog-generator' ),
				count( $result )
			),
			'images' => array_map( function( $id ) {
				$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
				$full = wp_get_attachment_image_src( $id, 'full' );
				return [
					'id' => $id,
					'thumbnail' => $thumb[0],
					'url' => $full[0],
				];
			}, $result ),
		] );
	}

	/**
	 * Regenerate a specific image.
	 */
	public function regenerate_image() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid image ID.', 'ai-blog-generator' ) ] );
		}

		// Get original prompt
		$prompt = get_post_meta( $image_id, '_ai_blog_prompt', true );
		if ( ! $prompt ) {
			wp_send_json_error( [ 'message' => __( 'No prompt found for this image.', 'ai-blog-generator' ) ] );
		}

		// Get seed image if used
		$seed_image_id = get_post_meta( $image_id, '_ai_blog_seed_source', true );

		// Check budget
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		if ( ! $budget_manager->can_generate() ) {
			wp_send_json_error( [ 
				'message' => __( 'Budget limit reached.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Generate new image
		$generator = new \AI_Blog_Generator\Services\Image_Generator();
		$result = $generator->generate_images( $prompt, 1, $seed_image_id );

		if ( is_wp_error( $result ) ) {
			Logger::error( 'Image regeneration failed', [
				'image_id' => $image_id,
				'error' => $result->get_error_message(),
				'action' => 'regenerate_image',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Regeneration failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		// Copy metadata to new image
		$new_image_id = $result[0];
		$post_parent = wp_get_post_parent_id( $image_id );
		
		if ( $post_parent ) {
			wp_update_post( [
				'ID' => $new_image_id,
				'post_parent' => $post_parent,
			] );
		}

		update_post_meta( $new_image_id, '_ai_blog_generated', '1' );
		update_post_meta( $new_image_id, '_ai_blog_prompt', $prompt );
		if ( $seed_image_id ) {
			update_post_meta( $new_image_id, '_ai_blog_seed_source', $seed_image_id );
		}

		// Check if old image was featured
		$featured_posts = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
			$image_id
		) );

		// Delete old image
		wp_delete_attachment( $image_id, true );

		// Update featured image references
		foreach ( $featured_posts as $post_id ) {
			set_post_thumbnail( $post_id, $new_image_id );
		}

		// Log the action
		Logger::info( 'Image regenerated', [
			'old_image_id' => $image_id,
			'new_image_id' => $new_image_id,
			'action' => 'regenerate_image',
			'user_id' => get_current_user_id(),
		] );

		$thumb = wp_get_attachment_image_src( $new_image_id, 'thumbnail' );
		$full = wp_get_attachment_image_src( $new_image_id, 'full' );

		wp_send_json_success( [
			'message' => __( 'Image regenerated successfully.', 'ai-blog-generator' ),
			'image' => [
				'id' => $new_image_id,
				'thumbnail' => $thumb[0],
				'url' => $full[0],
			],
		] );
	}

	/**
	 * Set featured image for a post.
	 */
	public function set_featured_image() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		if ( ! $post_id || ! $image_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post or image ID.', 'ai-blog-generator' ) ] );
		}

		// Set featured image
		$result = set_post_thumbnail( $post_id, $image_id );
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to set featured image.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Featured image set', [
			'post_id' => $post_id,
			'image_id' => $image_id,
			'action' => 'set_featured_image',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Featured image set successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Attach images to a post.
	 */
	public function attach_images_to_post() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_ids = isset( $_POST['image_ids'] ) && is_array( $_POST['image_ids'] ) 
			? array_map( 'absint', $_POST['image_ids'] ) 
			: [];

		if ( ! $post_id || empty( $image_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid input data.', 'ai-blog-generator' ) ] );
		}

		$attached_count = 0;
		foreach ( $image_ids as $image_id ) {
			$updated = wp_update_post( [
				'ID' => $image_id,
				'post_parent' => $post_id,
			] );
			
			if ( ! is_wp_error( $updated ) ) {
				$attached_count++;
			}
		}

		// Log the action
		Logger::info( 'Images attached to post', [
			'post_id' => $post_id,
			'image_count' => $attached_count,
			'action' => 'attach_images_to_post',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of images attached */
				_n( '%d image attached successfully.', '%d images attached successfully.', $attached_count, 'ai-blog-generator' ),
				$attached_count
			),
			'attached_count' => $attached_count,
		] );
	}
} 
 
 